<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TournamentTables\Database\Connection;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Services\AllocationEditService;
use TournamentTables\Services\CostCalculator;

/**
 * Integration tests for allocation editing.
 *
 * Tests edit persistence and conflict recalculation.
 * Reference: specs/001-table-allocation/tasks.md#T066
 */
class AllocationEditTest extends TestCase
{
    /**
     * @var Tournament
     */
    private $tournament;

    /**
     * @var AllocationEditService
     */
    private $editService;

    protected function setUp(): void
    {
        // Skip if no database connection
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        // Clean up any existing test data
        $this->cleanupTestData();

        // Create test tournament
        $this->tournament = $this->createTestTournament();

        // Initialize service
        $db = Connection::getInstance();
        $this->editService = new AllocationEditService($db, new CostCalculator());
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * Test editing table assignment persists to database.
     */
    public function testEditTableAssignmentPersistence(): void
    {
        // Create round with allocations
        $round = $this->createRoundWithAllocations();
        $allocations = Allocation::findByRound($round->id);

        $this->assertCount(4, $allocations);

        $originalAllocation = $allocations[0];

        // Get a table that's not currently used in this round
        $tables = Table::findByTournament($this->tournament->id);
        $usedTableIds = array_map(function ($a) {
            return $a->tableId;
        }, $allocations);

        $newTableId = null;
        foreach ($tables as $table) {
            if (!in_array($table->id, $usedTableIds, true)) {
                $newTableId = $table->id;
                break;
            }
        }

        $this->assertNotNull($newTableId, 'Should have an unused table available');

        // Edit the allocation
        $result = $this->editService->editTableAssignment($originalAllocation->id, $newTableId);

        $this->assertTrue($result['success']);

        // Verify change persisted
        $updatedAllocation = Allocation::find($originalAllocation->id);
        $this->assertEquals($newTableId, $updatedAllocation->tableId);
    }

    /**
     * Test conflict recalculation after edit.
     */
    public function testConflictRecalculationAfterEdit(): void
    {
        // Create round 1 and 2 with allocations
        $round1 = $this->createRound1WithAllocations();
        $round2 = $this->createRound2WithAllocations();

        $round2Allocations = Allocation::findByRound($round2->id);
        $this->assertCount(4, $round2Allocations);

        // Get first player's ID from the players we created
        $players = Player::findByTournament($this->tournament->id);
        $player1Id = $players[0]->id;

        // Get player 1's allocation in round 2
        $player1Round2Allocation = null;
        foreach ($round2Allocations as $alloc) {
            if ($alloc->player1Id === $player1Id || $alloc->player2Id === $player1Id) {
                $player1Round2Allocation = $alloc;
                break;
            }
        }

        $this->assertNotNull($player1Round2Allocation);

        // Get the table player 1 used in round 1
        $round1Allocations = Allocation::findByRound($round1->id);
        $player1Round1Table = null;
        foreach ($round1Allocations as $alloc) {
            if ($alloc->player1Id === $player1Id || $alloc->player2Id === $player1Id) {
                $player1Round1Table = $alloc->tableId;
                break;
            }
        }

        $this->assertNotNull($player1Round1Table);

        // Edit player 1's round 2 allocation to use the same table as round 1
        $result = $this->editService->editTableAssignment(
            $player1Round2Allocation->id,
            $player1Round1Table
        );

        $this->assertTrue($result['success']);

        // Verify conflict was detected and recorded
        $this->assertArrayHasKey('conflicts', $result);
        $this->assertNotEmpty($result['conflicts']);

        $hasTableReuseConflict = false;
        foreach ($result['conflicts'] as $conflict) {
            if ($conflict['type'] === 'TABLE_REUSE') {
                $hasTableReuseConflict = true;
                break;
            }
        }

        $this->assertTrue($hasTableReuseConflict, 'Should detect table reuse conflict');
    }

    /**
     * Test swapping tables between two allocations.
     */
    public function testSwapTablesPersistence(): void
    {
        // Create round with allocations
        $round = $this->createRoundWithAllocations();
        $allocations = Allocation::findByRound($round->id);

        $this->assertGreaterThanOrEqual(2, count($allocations));

        $allocation1 = $allocations[0];
        $allocation2 = $allocations[1];

        $originalTable1 = $allocation1->tableId;
        $originalTable2 = $allocation2->tableId;

        // Swap the tables
        $result = $this->editService->swapTables($allocation1->id, $allocation2->id);

        $this->assertTrue($result['success']);

        // Verify swap persisted
        $updatedAllocation1 = Allocation::find($allocation1->id);
        $updatedAllocation2 = Allocation::find($allocation2->id);

        $this->assertEquals($originalTable2, $updatedAllocation1->tableId);
        $this->assertEquals($originalTable1, $updatedAllocation2->tableId);
    }

    /**
     * Test attempting to edit with invalid table ID.
     */
    public function testEditWithInvalidTableId(): void
    {
        $round = $this->createRoundWithAllocations();
        $allocations = Allocation::findByRound($round->id);

        $allocation = $allocations[0];
        $invalidTableId = 99999;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Table not found');

        $this->editService->editTableAssignment($allocation->id, $invalidTableId);
    }

    /**
     * Test BCP table number persistence.
     */
    public function testBcpTableNumberPersistence(): void
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Create allocation with BCP table number
        $allocation = new Allocation(
            null,
            $round->id,
            $tables[0]->id,
            $players[0]->id,
            $players[1]->id,
            0,
            0,
            null,
            5 // BCP table number
        );
        $allocation->save();

        // Retrieve and verify
        $retrieved = Allocation::find($allocation->id);
        $this->assertEquals(5, $retrieved->bcpTableNumber);
    }

    /**
     * Test BCP table number can be null.
     */
    public function testBcpTableNumberCanBeNull(): void
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Create allocation without BCP table number
        $allocation = new Allocation(
            null,
            $round->id,
            $tables[0]->id,
            $players[0]->id,
            $players[1]->id,
            0,
            0,
            null,
            null // No BCP table number
        );
        $allocation->save();

        // Retrieve and verify
        $retrieved = Allocation::find($allocation->id);
        $this->assertNull($retrieved->bcpTableNumber);
    }

    /**
     * Test hasBcpTableDifference returns false when bcpTableNumber is null.
     */
    public function testHasBcpTableDifferenceReturnsFalseWhenNull(): void
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        $allocation = new Allocation(
            null,
            $round->id,
            $tables[0]->id,
            $players[0]->id,
            $players[1]->id,
            0,
            0,
            null,
            null // No BCP table number
        );
        $allocation->save();

        $this->assertFalse($allocation->hasBcpTableDifference());
    }

    /**
     * Test hasBcpTableDifference returns false when table matches BCP table.
     */
    public function testHasBcpTableDifferenceReturnsFalseWhenMatches(): void
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Assign to table 1 with BCP table number 1 (same)
        $allocation = new Allocation(
            null,
            $round->id,
            $tables[0]->id, // table_number = 1
            $players[0]->id,
            $players[1]->id,
            0,
            0,
            null,
            1 // BCP table number = 1
        );
        $allocation->save();

        $this->assertFalse($allocation->hasBcpTableDifference());
    }

    /**
     * Test hasBcpTableDifference returns true when table differs from BCP table.
     */
    public function testHasBcpTableDifferenceReturnsTrueWhenDiffers(): void
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Assign to table 1 with BCP table number 5 (different)
        $allocation = new Allocation(
            null,
            $round->id,
            $tables[0]->id, // table_number = 1
            $players[0]->id,
            $players[1]->id,
            0,
            0,
            null,
            5 // BCP table number = 5
        );
        $allocation->save();

        $this->assertTrue($allocation->hasBcpTableDifference());
    }

    // Helper methods

    private function isDatabaseAvailable(): bool
    {
        try {
            $db = Connection::getInstance();
            // Check if required tables exist
            $result = $db->query("SHOW TABLES LIKE 'allocations'");
            if ($result->rowCount() === 0) {
                return false;
            }
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupTestData(): void
    {
        $db = Connection::getInstance();
        $db->exec("DELETE FROM allocations WHERE 1=1");
        $db->exec("DELETE FROM players WHERE 1=1");
        $db->exec("DELETE FROM rounds WHERE 1=1");
        $db->exec("DELETE FROM tables WHERE 1=1");
        $db->exec("DELETE FROM tournaments WHERE bcp_event_id LIKE 'TEST%'");
    }

    private function createTestTournament(): Tournament
    {
        $tournament = new Tournament();
        $tournament->name = 'Test Tournament';
        $tournament->bcpEventId = 'TEST_' . uniqid();
        $tournament->bcpUrl = 'https://www.bestcoastpairings.com/event/TEST';
        $tournament->tableCount = 8;
        $tournament->adminToken = bin2hex(random_bytes(8));
        $tournament->save();

        // Create tables
        for ($i = 1; $i <= 8; $i++) {
            $table = new Table();
            $table->tournamentId = $tournament->id;
            $table->tableNumber = $i;
            $table->terrainTypeId = null;
            $table->save();
        }

        // Create players
        for ($i = 1; $i <= 8; $i++) {
            $player = new Player();
            $player->tournamentId = $tournament->id;
            $player->bcpPlayerId = 'bcp_p' . $i;
            $player->name = 'Player ' . $i;
            $player->save();
        }

        return $tournament;
    }

    private function createRoundWithAllocations(): Round
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 1;
        $round->isPublished = false;
        $round->save();

        // Create 4 allocations
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation();
            $allocation->roundId = $round->id;
            $allocation->tableId = $tables[$i]->id;
            $allocation->player1Id = $players[$i * 2]->id;
            $allocation->player2Id = $players[$i * 2 + 1]->id;
            $allocation->player1Score = 0;
            $allocation->player2Score = 0;
            $allocation->save();
        }

        return $round;
    }

    private function createRound1WithAllocations(): Round
    {
        return $this->createRoundWithAllocations();
    }

    private function createRound2WithAllocations(): Round
    {
        $round = new Round();
        $round->tournamentId = $this->tournament->id;
        $round->roundNumber = 2;
        $round->isPublished = false;
        $round->save();

        // Create allocations using different table assignments
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation();
            $allocation->roundId = $round->id;
            $allocation->tableId = $tables[$i + 4]->id; // Use different tables
            $allocation->player1Id = $players[$i * 2]->id;
            $allocation->player2Id = $players[$i * 2 + 1]->id;
            $allocation->player1Score = 1;
            $allocation->player2Score = 1;
            $allocation->save();
        }

        return $round;
    }
}
