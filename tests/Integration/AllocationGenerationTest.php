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
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * Integration tests for allocation generation.
 *
 * Tests end-to-end allocation with database integration.
 * Reference: specs/001-table-allocation/tasks.md#T042
 */
class AllocationGenerationTest extends TestCase
{
    /**
     * @var Tournament
     */
    private $tournament;

    /**
     * @var AllocationService
     */
    private $allocationService;

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
        $this->allocationService = new AllocationService(new CostCalculator());
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * Test full allocation workflow - tournament creation to round 2 allocation.
     */
    public function testFullAllocationWorkflow(): void
    {
        // Create round 1 with manual allocations (simulating BCP import)
        $round1 = $this->createRound1WithAllocations();

        // Create round 2 pairings
        $pairings = $this->createRound2Pairings();

        // Get tables for allocation
        $tables = $this->getTablesAsArray();

        // Generate allocations for round 2
        $history = new TournamentHistory($this->tournament->id, 2);
        $result = $this->allocationService->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        // Verify allocations generated
        $this->assertCount(4, $result->allocations);

        // Verify no player is assigned to a table they used in round 1
        $this->assertNoTableReuse($result->allocations);
    }

    /**
     * Test conflict detection when table reuse is unavoidable.
     */
    public function testConflictDetectionWhenTableReuseUnavoidable(): void
    {
        // Create a tournament with only 2 tables but 4 players who will play again
        $this->cleanupTestData();
        $this->tournament = $this->createSmallTournament(2);

        // Round 1: p1 vs p2 on table 1, p3 vs p4 on table 2
        $round1 = $this->createRound1Small();

        // Round 2: p1 vs p3, p2 vs p4 - each player will get a table they used
        $pairings = [
            new Pairing('bcp_p1', 'Player 1', 1, 'bcp_p3', 'Player 3', 1, null),
            new Pairing('bcp_p2', 'Player 2', 0, 'bcp_p4', 'Player 4', 0, null),
        ];

        $tables = $this->getTablesAsArray();
        $history = new TournamentHistory($this->tournament->id, 2);

        $result = $this->allocationService->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        // Should still generate allocations but flag conflicts
        $this->assertCount(2, $result->allocations);
        $this->assertNotEmpty($result->conflicts, 'Should detect conflicts');

        // At least one allocation should have TABLE_REUSE conflict
        $hasTableReuseConflict = false;
        foreach ($result->allocations as $allocation) {
            foreach ($allocation['reason']['conflicts'] as $conflict) {
                if ($conflict['type'] === 'TABLE_REUSE') {
                    $hasTableReuseConflict = true;
                    break 2;
                }
            }
        }
        $this->assertTrue($hasTableReuseConflict, 'Should flag TABLE_REUSE conflict');
    }

    /**
     * Test allocation persists to database correctly.
     */
    public function testAllocationPersistsToDatabase(): void
    {
        // Create round 1
        $round1 = Round::findOrCreate($this->tournament->id, 1);

        // Create players
        $p1 = Player::findOrCreate($this->tournament->id, 'bcp_p1', 'Player 1');
        $p2 = Player::findOrCreate($this->tournament->id, 'bcp_p2', 'Player 2');

        // Get table 1
        $table = Table::findByTournamentAndNumber($this->tournament->id, 1);

        // Create allocation
        $allocation = new Allocation(
            null,
            $round1->id,
            $table->id,
            $p1->id,
            $p2->id,
            2,
            1,
            [
                'timestamp' => date('c'),
                'totalCost' => 1,
                'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 1],
                'reasons' => [],
                'alternativesConsidered' => [],
                'isRound1' => true,
                'conflicts' => [],
            ]
        );

        $allocation->save();

        // Reload from database
        $loaded = Allocation::find($allocation->id);

        $this->assertNotNull($loaded);
        $this->assertEquals($round1->id, $loaded->roundId);
        $this->assertEquals($table->id, $loaded->tableId);
        $this->assertEquals($p1->id, $loaded->player1Id);
        $this->assertEquals($p2->id, $loaded->player2Id);
        $this->assertEquals(2, $loaded->player1Score);
        $this->assertEquals(1, $loaded->player2Score);
        $this->assertIsArray($loaded->allocationReason);
        $this->assertTrue($loaded->allocationReason['isRound1']);
    }

    /**
     * Test TournamentHistory queries work correctly.
     */
    public function testTournamentHistoryQueriesDatabase(): void
    {
        // Create round 1 with allocation on table 3
        $round1 = Round::findOrCreate($this->tournament->id, 1);
        $p1 = Player::findOrCreate($this->tournament->id, 'bcp_test_player', 'Test Player');
        $p2 = Player::findOrCreate($this->tournament->id, 'bcp_opponent', 'Opponent');

        $table3 = Table::findByTournamentAndNumber($this->tournament->id, 3);

        $allocation = new Allocation(
            null,
            $round1->id,
            $table3->id,
            $p1->id,
            $p2->id,
            0,
            0,
            null
        );
        $allocation->save();

        // Query history for round 2
        $history = new TournamentHistory($this->tournament->id, 2);

        // Player should have used table 3
        $this->assertTrue($history->hasPlayerUsedTable($p1->id, 3));
        $this->assertFalse($history->hasPlayerUsedTable($p1->id, 1));
    }

    /**
     * Test score-based ordering in allocation.
     */
    public function testScoreBasedOrderingInAllocation(): void
    {
        $pairings = [
            new Pairing('bcp_low1', 'Low 1', 0, 'bcp_low2', 'Low 2', 0, null),
            new Pairing('bcp_high1', 'High 1', 4, 'bcp_high2', 'High 2', 4, null),
            new Pairing('bcp_mid1', 'Mid 1', 2, 'bcp_mid2', 'Mid 2', 2, null),
        ];

        $tables = $this->getTablesAsArray();
        $history = new TournamentHistory($this->tournament->id, 2);

        $result = $this->allocationService->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        // Find allocations by player
        $highAlloc = null;
        $midAlloc = null;
        $lowAlloc = null;

        foreach ($result->allocations as $alloc) {
            if ($alloc['player1']['bcpId'] === 'bcp_high1') {
                $highAlloc = $alloc;
            } elseif ($alloc['player1']['bcpId'] === 'bcp_mid1') {
                $midAlloc = $alloc;
            } elseif ($alloc['player1']['bcpId'] === 'bcp_low1') {
                $lowAlloc = $alloc;
            }
        }

        // High score should be on lowest table
        $this->assertLessThan($midAlloc['tableNumber'], $highAlloc['tableNumber']);
        $this->assertLessThan($lowAlloc['tableNumber'], $midAlloc['tableNumber']);
    }

    // Helper methods

    private function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupTestData(): void
    {
        try {
            Connection::execute("DELETE FROM tournaments WHERE bcp_event_id LIKE 'test_alloc_%' OR bcp_event_id LIKE 'test_small_%'");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function createTestTournament(): Tournament
    {
        Connection::beginTransaction();

        try {
            Connection::execute(
                "INSERT INTO tournaments (name, bcp_event_id, bcp_url, table_count, admin_token)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    'Test Tournament Allocation',
                    'test_alloc_' . uniqid('', true),
                    'https://www.bestcoastpairings.com/event/test123',
                    8,
                    bin2hex(random_bytes(8)),
                ]
            );

            $tournamentId = Connection::lastInsertId();

            // Create 8 tables with terrain types
            for ($i = 1; $i <= 8; $i++) {
                Connection::execute(
                    "INSERT INTO tables (tournament_id, table_number, terrain_type_id)
                     VALUES (?, ?, ?)",
                    [$tournamentId, $i, ($i % 4) + 1] // Cycle through terrain types 1-4
                );
            }

            Connection::commit();

            return Tournament::find($tournamentId);
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    private function createSmallTournament(int $tableCount): Tournament
    {
        Connection::beginTransaction();

        try {
            Connection::execute(
                "INSERT INTO tournaments (name, bcp_event_id, bcp_url, table_count, admin_token)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    'Test Tournament Small',
                    'test_small_' . uniqid('', true),
                    'https://www.bestcoastpairings.com/event/small123',
                    $tableCount,
                    bin2hex(random_bytes(8)),
                ]
            );

            $tournamentId = Connection::lastInsertId();

            for ($i = 1; $i <= $tableCount; $i++) {
                Connection::execute(
                    "INSERT INTO tables (tournament_id, table_number, terrain_type_id)
                     VALUES (?, ?, ?)",
                    [$tournamentId, $i, null]
                );
            }

            Connection::commit();

            return Tournament::find($tournamentId);
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    private function createRound1WithAllocations(): Round
    {
        $round = Round::findOrCreate($this->tournament->id, 1);

        // Create 8 players and allocate them to tables 1-4
        $players = [];
        for ($i = 1; $i <= 8; $i++) {
            $players[$i] = Player::findOrCreate(
                $this->tournament->id,
                "bcp_player_{$i}",
                "Player {$i}"
            );
        }

        $tables = Table::findByTournament($this->tournament->id);

        // Create 4 pairings on tables 1-4
        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$i]->id,
                $players[$i * 2 + 1]->id,
                $players[$i * 2 + 2]->id,
                0,
                0,
                ['isRound1' => true, 'conflicts' => []]
            );
            $allocation->save();
        }

        return $round;
    }

    private function createRound1Small(): Round
    {
        $round = Round::findOrCreate($this->tournament->id, 1);

        $p1 = Player::findOrCreate($this->tournament->id, 'bcp_p1', 'Player 1');
        $p2 = Player::findOrCreate($this->tournament->id, 'bcp_p2', 'Player 2');
        $p3 = Player::findOrCreate($this->tournament->id, 'bcp_p3', 'Player 3');
        $p4 = Player::findOrCreate($this->tournament->id, 'bcp_p4', 'Player 4');

        $tables = Table::findByTournament($this->tournament->id);

        // p1 vs p2 on table 1
        $alloc1 = new Allocation(null, $round->id, $tables[0]->id, $p1->id, $p2->id, 1, 0, null);
        $alloc1->save();

        // p3 vs p4 on table 2
        $alloc2 = new Allocation(null, $round->id, $tables[1]->id, $p3->id, $p4->id, 1, 0, null);
        $alloc2->save();

        return $round;
    }

    private function createRound2Pairings(): array
    {
        // Different pairings for round 2 - players with different scores
        return [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_3', 'Player 3', 2, null),
            new Pairing('bcp_player_2', 'Player 2', 2, 'bcp_player_4', 'Player 4', 2, null),
            new Pairing('bcp_player_5', 'Player 5', 1, 'bcp_player_7', 'Player 7', 1, null),
            new Pairing('bcp_player_6', 'Player 6', 0, 'bcp_player_8', 'Player 8', 0, null),
        ];
    }

    private function getTablesAsArray(): array
    {
        $tables = Table::findByTournament($this->tournament->id);
        return array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);
    }

    private function assertNoTableReuse(array $allocations): void
    {
        // Get round 1 allocations from database
        $round1 = Round::findByTournamentAndNumber($this->tournament->id, 1);
        $round1Allocations = Allocation::findByRound($round1->id);

        // Build map of player -> tables used
        $playerTables = [];
        foreach ($round1Allocations as $alloc) {
            $table = Table::find($alloc->tableId);
            $p1 = Player::find($alloc->player1Id);
            $p2 = Player::find($alloc->player2Id);

            $playerTables[$p1->bcpPlayerId][] = $table->tableNumber;
            $playerTables[$p2->bcpPlayerId][] = $table->tableNumber;
        }

        // Check round 2 allocations don't reuse tables
        foreach ($allocations as $alloc) {
            $tableNum = $alloc['tableNumber'];
            $p1BcpId = $alloc['player1']['bcpId'];
            $p2BcpId = $alloc['player2']['bcpId'];

            if (isset($playerTables[$p1BcpId])) {
                $this->assertNotContains(
                    $tableNum,
                    $playerTables[$p1BcpId],
                    "Player {$p1BcpId} should not be assigned to previously used table {$tableNum}"
                );
            }

            if (isset($playerTables[$p2BcpId])) {
                $this->assertNotContains(
                    $tableNum,
                    $playerTables[$p2BcpId],
                    "Player {$p2BcpId} should not be assigned to previously used table {$tableNum}"
                );
            }
        }
    }
}
