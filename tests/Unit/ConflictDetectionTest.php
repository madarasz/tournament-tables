<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * Tests for conflict detection.
 *
 * Validates FR-010: Conflict detection rate = 100%.
 * Reference: specs/001-table-allocation/tasks.md#T092
 */
class ConflictDetectionTest extends TestCase
{
    /**
     * @var AllocationService
     */
    private $allocationService;

    protected function setUp(): void
    {
        $this->allocationService = new AllocationService(new CostCalculator());
    }

    /**
     * Test that table reuse conflicts are always detected.
     *
     * @group conflict
     */
    public function testTableReuseConflictAlwaysDetected(): void
    {
        // Create mock history that indicates player used table 1
        $history = $this->createMockHistoryWithTableUsage(['bcp_player_1' => [1]]);

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_2', 'Player 2', 2, null),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Must detect the conflict
        $this->assertNotEmpty($result->conflicts, 'Must detect table reuse conflict');

        // Verify conflict type
        $hasTableReuseConflict = false;
        foreach ($result->conflicts as $conflict) {
            if ($conflict['type'] === 'TABLE_REUSE') {
                $hasTableReuseConflict = true;
                break;
            }
        }
        $this->assertTrue($hasTableReuseConflict, 'Must specifically identify TABLE_REUSE conflict');
    }

    /**
     * Test that terrain reuse conflicts are always detected.
     *
     * @group conflict
     */
    public function testTerrainReuseConflictAlwaysDetected(): void
    {
        // Create mock history that indicates player experienced terrain type 1
        $history = $this->createMockHistoryWithTerrainUsage(['bcp_player_1' => [1]]);

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_2', 'Player 2', 2, null),
        ];

        $tables = [
            ['tableNumber' => 5, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Must detect the conflict
        $this->assertNotEmpty($result->conflicts, 'Must detect terrain reuse conflict');

        // Verify conflict type
        $hasTerrainReuseConflict = false;
        foreach ($result->conflicts as $conflict) {
            if ($conflict['type'] === 'TERRAIN_REUSE') {
                $hasTerrainReuseConflict = true;
                break;
            }
        }
        $this->assertTrue($hasTerrainReuseConflict, 'Must specifically identify TERRAIN_REUSE conflict');
    }

    /**
     * Test multiple conflicts are all detected.
     *
     * @group conflict
     */
    public function testMultipleConflictsAllDetected(): void
    {
        // Player 1 used table 1, Player 2 used table 2, both experienced terrain type 1
        $history = $this->createMockHistoryWithBothUsage(
            ['bcp_player_1' => [1], 'bcp_player_2' => [2]],
            ['bcp_player_1' => [1], 'bcp_player_2' => [1]]
        );

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_2', 'Player 2', 2, null),
            new Pairing('bcp_player_3', 'Player 3', 1, 'bcp_player_4', 'Player 4', 1, null),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
            ['tableNumber' => 2, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Count conflict types
        $tableReuseCount = 0;
        $terrainReuseCount = 0;

        foreach ($result->conflicts as $conflict) {
            if ($conflict['type'] === 'TABLE_REUSE') {
                $tableReuseCount++;
            } elseif ($conflict['type'] === 'TERRAIN_REUSE') {
                $terrainReuseCount++;
            }
        }

        // Should detect both types
        $this->assertGreaterThan(0, $tableReuseCount, 'Must detect table reuse conflicts');
        $this->assertGreaterThan(0, $terrainReuseCount, 'Must detect terrain reuse conflicts');
    }

    /**
     * Test no false positives when no conflicts exist.
     *
     * @group conflict
     */
    public function testNoFalsePositivesWhenClean(): void
    {
        // Create clean history - no prior usage
        $history = $this->createCleanHistory();

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_2', 'Player 2', 2, null),
            new Pairing('bcp_player_3', 'Player 3', 1, 'bcp_player_4', 'Player 4', 1, null),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
            ['tableNumber' => 2, 'terrainTypeId' => 2, 'terrainTypeName' => 'Industrial'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Should have no conflicts
        $this->assertEmpty($result->conflicts, 'Should not report conflicts when none exist');
    }

    /**
     * Test conflict detection is comprehensive for all players in a pairing.
     *
     * @group conflict
     */
    public function testConflictDetectionCoversBothPlayers(): void
    {
        // Player 2 (not player 1) used table 1
        $history = $this->createMockHistoryWithTableUsage(['bcp_player_2' => [1]]);

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 2, 'bcp_player_2', 'Player 2', 2, null),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Must still detect the conflict even though it's player 2
        $this->assertNotEmpty($result->conflicts, 'Must detect conflict for player 2');
    }

    /**
     * Test round 1 never has conflicts (BCP original assignments).
     *
     * @group conflict
     */
    public function testRound1NeverHasConflicts(): void
    {
        $history = $this->createCleanHistory();

        $pairings = [
            new Pairing('bcp_player_1', 'Player 1', 0, 'bcp_player_2', 'Player 2', 0, 1),
            new Pairing('bcp_player_3', 'Player 3', 0, 'bcp_player_4', 'Player 4', 0, 2),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
            ['tableNumber' => 2, 'terrainTypeId' => 2, 'terrainTypeName' => 'Industrial'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 1, $history);

        // Round 1 should never have conflicts
        $this->assertEmpty($result->conflicts, 'Round 1 should never have conflicts');
    }

    /**
     * Test conflict detection accuracy over multiple scenarios.
     *
     * FR-010: Must achieve 100% detection rate.
     *
     * @group conflict
     */
    public function testConflictDetectionAccuracy100Percent(): void
    {
        $expectedConflicts = 0;
        $detectedConflicts = 0;

        // Scenario 1: Table reuse
        $history = $this->createMockHistoryWithTableUsage(['bcp_test_1' => [1]]);
        $pairings = [new Pairing('bcp_test_1', 'Test 1', 1, 'bcp_test_2', 'Test 2', 1, null)];
        $tables = [['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null]];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);
        $expectedConflicts++;
        if (!empty($result->conflicts)) {
            $detectedConflicts++;
        }

        // Scenario 2: Terrain reuse
        $history = $this->createMockHistoryWithTerrainUsage(['bcp_test_3' => [1]]);
        $pairings = [new Pairing('bcp_test_3', 'Test 3', 1, 'bcp_test_4', 'Test 4', 1, null)];
        $tables = [['tableNumber' => 5, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban']];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);
        $expectedConflicts++;
        if (!empty($result->conflicts)) {
            $detectedConflicts++;
        }

        // Scenario 3: Both table and terrain reuse
        $history = $this->createMockHistoryWithBothUsage(['bcp_test_5' => [1]], ['bcp_test_5' => [1]]);
        $pairings = [new Pairing('bcp_test_5', 'Test 5', 1, 'bcp_test_6', 'Test 6', 1, null)];
        $tables = [['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban']];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);
        $expectedConflicts++;
        if (!empty($result->conflicts)) {
            $detectedConflicts++;
        }

        // Assert 100% detection rate
        $this->assertEquals(
            $expectedConflicts,
            $detectedConflicts,
            sprintf(
                'Detection rate: %d/%d (%.1f%%) - must be 100%%',
                $detectedConflicts,
                $expectedConflicts,
                ($detectedConflicts / $expectedConflicts) * 100
            )
        );
    }

    // Helper methods for creating mock history

    private function createCleanHistory(): TournamentHistory
    {
        return new class(1, 2) extends TournamentHistory {
            protected function queryPlayerTableHistory($playerId): array
            {
                return [];
            }

            protected function queryPlayerTerrainHistory($playerId): array
            {
                return [];
            }
        };
    }

    private function createMockHistoryWithTableUsage(array $playerTables): TournamentHistory
    {
        return new class(1, 2, $playerTables) extends TournamentHistory {
            private $playerTables;

            public function __construct(int $tournamentId, int $round, array $playerTables = [])
            {
                parent::__construct($tournamentId, $round);
                $this->playerTables = $playerTables;
            }

            protected function queryPlayerTableHistory($playerId): array
            {
                $tables = $this->playerTables[$playerId] ?? [];
                return array_map(fn($t) => ['table_number' => $t], $tables);
            }

            protected function queryPlayerTerrainHistory($playerId): array
            {
                return [];
            }
        };
    }

    private function createMockHistoryWithTerrainUsage(array $playerTerrains): TournamentHistory
    {
        return new class(1, 2, $playerTerrains) extends TournamentHistory {
            private $playerTerrains;

            public function __construct(int $tournamentId, int $round, array $playerTerrains = [])
            {
                parent::__construct($tournamentId, $round);
                $this->playerTerrains = $playerTerrains;
            }

            protected function queryPlayerTableHistory($playerId): array
            {
                return [];
            }

            protected function queryPlayerTerrainHistory($playerId): array
            {
                $terrains = $this->playerTerrains[$playerId] ?? [];
                return array_map(fn($t) => ['id' => $t, 'name' => "Terrain {$t}"], $terrains);
            }
        };
    }

    private function createMockHistoryWithBothUsage(array $playerTables, array $playerTerrains): TournamentHistory
    {
        return new class(1, 2, $playerTables, $playerTerrains) extends TournamentHistory {
            private $playerTables;
            private $playerTerrains;

            public function __construct(int $tournamentId, int $round, array $playerTables = [], array $playerTerrains = [])
            {
                parent::__construct($tournamentId, $round);
                $this->playerTables = $playerTables;
                $this->playerTerrains = $playerTerrains;
            }

            protected function queryPlayerTableHistory($playerId): array
            {
                $tables = $this->playerTables[$playerId] ?? [];
                return array_map(fn($t) => ['table_number' => $t], $tables);
            }

            protected function queryPlayerTerrainHistory($playerId): array
            {
                $terrains = $this->playerTerrains[$playerId] ?? [];
                return array_map(fn($t) => ['id' => $t, 'name' => "Terrain {$t}"], $terrains);
            }
        };
    }
}
