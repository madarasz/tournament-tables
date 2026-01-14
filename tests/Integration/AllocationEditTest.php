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
        $db = Connection::getInstance()->getPdo();
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
        $allocations = Allocation::getByRound($round->id);

        $this->assertCount(4, $allocations);

        $originalAllocation = $allocations[0];
        $originalTableId = $originalAllocation['table_id'];

        // Get a different table
        $tables = Table::getByTournament($this->tournament->id);
        $newTableId = null;
        foreach ($tables as $table) {
            if ($table['id'] !== $originalTableId) {
                $newTableId = $table['id'];
                break;
            }
        }

        $this->assertNotNull($newTableId, 'Should have a different table available');

        // Edit the allocation
        $result = $this->editService->editTableAssignment($originalAllocation['id'], $newTableId);

        $this->assertTrue($result['success']);

        // Verify change persisted
        $updatedAllocation = Allocation::getById($originalAllocation['id']);
        $this->assertEquals($newTableId, $updatedAllocation['table_id']);
    }

    /**
     * Test conflict recalculation after edit.
     */
    public function testConflictRecalculationAfterEdit(): void
    {
        // Create round 1 and 2 with allocations
        $round1 = $this->createRound1WithAllocations();
        $round2 = $this->createRound2WithAllocations();

        $round2Allocations = Allocation::getByRound($round2->id);
        $this->assertCount(4, $round2Allocations);

        // Get player 1's allocation in round 2
        $player1Round2Allocation = null;
        foreach ($round2Allocations as $alloc) {
            if ($alloc['player1_id'] === 1 || $alloc['player2_id'] === 1) {
                $player1Round2Allocation = $alloc;
                break;
            }
        }

        $this->assertNotNull($player1Round2Allocation);

        // Get the table player 1 used in round 1
        $round1Allocations = Allocation::getByRound($round1->id);
        $player1Round1Table = null;
        foreach ($round1Allocations as $alloc) {
            if ($alloc['player1_id'] === 1 || $alloc['player2_id'] === 1) {
                $player1Round1Table = $alloc['table_id'];
                break;
            }
        }

        $this->assertNotNull($player1Round1Table);

        // Edit player 1's round 2 allocation to use the same table as round 1
        $result = $this->editService->editTableAssignment(
            $player1Round2Allocation['id'],
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
        $allocations = Allocation::getByRound($round->id);

        $this->assertGreaterThanOrEqual(2, count($allocations));

        $allocation1 = $allocations[0];
        $allocation2 = $allocations[1];

        $originalTable1 = $allocation1['table_id'];
        $originalTable2 = $allocation2['table_id'];

        // Swap the tables
        $result = $this->editService->swapTables($allocation1['id'], $allocation2['id']);

        $this->assertTrue($result['success']);

        // Verify swap persisted
        $updatedAllocation1 = Allocation::getById($allocation1['id']);
        $updatedAllocation2 = Allocation::getById($allocation2['id']);

        $this->assertEquals($originalTable2, $updatedAllocation1['table_id']);
        $this->assertEquals($originalTable1, $updatedAllocation2['table_id']);
    }

    /**
     * Test attempting to edit with invalid table ID.
     */
    public function testEditWithInvalidTableId(): void
    {
        $round = $this->createRoundWithAllocations();
        $allocations = Allocation::getByRound($round->id);

        $allocation = $allocations[0];
        $invalidTableId = 99999;

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Table not found');

        $this->editService->editTableAssignment($allocation['id'], $invalidTableId);
    }

    // Helper methods

    private function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupTestData(): void
    {
        $db = Connection::getInstance()->getPdo();
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
        $tournament->bcp_event_id = 'TEST_' . uniqid();
        $tournament->bcp_url = 'https://www.bestcoastpairings.com/event/TEST';
        $tournament->table_count = 8;
        $tournament->admin_token = bin2hex(random_bytes(8));
        $tournament->save();

        // Create tables
        for ($i = 1; $i <= 8; $i++) {
            $table = new Table();
            $table->tournament_id = $tournament->id;
            $table->table_number = $i;
            $table->terrain_type_id = null;
            $table->save();
        }

        // Create players
        for ($i = 1; $i <= 8; $i++) {
            $player = new Player();
            $player->tournament_id = $tournament->id;
            $player->bcp_player_id = 'bcp_p' . $i;
            $player->name = 'Player ' . $i;
            $player->save();
        }

        return $tournament;
    }

    private function createRoundWithAllocations(): Round
    {
        $round = new Round();
        $round->tournament_id = $this->tournament->id;
        $round->round_number = 1;
        $round->is_published = false;
        $round->save();

        // Create 4 allocations
        $tables = Table::getByTournament($this->tournament->id);
        $players = Player::getByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation();
            $allocation->round_id = $round->id;
            $allocation->table_id = $tables[$i]['id'];
            $allocation->player1_id = $players[$i * 2]['id'];
            $allocation->player2_id = $players[$i * 2 + 1]['id'];
            $allocation->player1_score = 0;
            $allocation->player2_score = 0;
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
        $round->tournament_id = $this->tournament->id;
        $round->round_number = 2;
        $round->is_published = false;
        $round->save();

        // Create allocations using different table assignments
        $tables = Table::getByTournament($this->tournament->id);
        $players = Player::getByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation();
            $allocation->round_id = $round->id;
            $allocation->table_id = $tables[$i + 4]['id']; // Use different tables
            $allocation->player1_id = $players[$i * 2]['id'];
            $allocation->player2_id = $players[$i * 2 + 1]['id'];
            $allocation->player1_score = 1;
            $allocation->player2_score = 1;
            $allocation->save();
        }

        return $round;
    }
}
