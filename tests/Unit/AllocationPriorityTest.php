<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\CostResult;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * Tests for allocation priority rules.
 *
 * Validates FR-007: Priority-weighted allocation algorithm.
 * Reference: specs/001-table-allocation/tasks.md#T093
 */
class AllocationPriorityTest extends TestCase
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
     * Test pairings are sorted by combined score (descending).
     *
     * FR-007: Higher-scoring pairings get priority table assignment.
     *
     * @group priority
     */
    public function testHigherScorePairingsGetPriority(): void
    {
        $history = $this->createCleanHistory();

        // Total scores (last two params) are used for sorting - higher total scores get lower table numbers
        $pairings = [
            new Pairing('bcp_low1', 'Low 1', 0, 'bcp_low2', 'Low 2', 0, null, 0, 0),      // Total: 0
            new Pairing('bcp_high1', 'High 1', 4, 'bcp_high2', 'High 2', 4, null, 4, 4),  // Total: 8
            new Pairing('bcp_mid1', 'Mid 1', 2, 'bcp_mid2', 'Mid 2', 2, null, 2, 2),      // Total: 4
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 3, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

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

        // High score gets lowest table number
        $this->assertNotNull($highAlloc, 'High score pairing should be allocated');
        $this->assertNotNull($midAlloc, 'Mid score pairing should be allocated');
        $this->assertNotNull($lowAlloc, 'Low score pairing should be allocated');

        $this->assertLessThan(
            $midAlloc['tableNumber'],
            $highAlloc['tableNumber'],
            'Higher score pairing should get lower table number'
        );

        $this->assertLessThan(
            $lowAlloc['tableNumber'],
            $midAlloc['tableNumber'],
            'Mid score pairing should get lower table number than low score'
        );
    }

    /**
     * Test table reuse avoidance is prioritized.
     *
     * FR-007.2: No player plays at same table twice (when possible).
     *
     * @group priority
     */
    public function testTableReuseAvoidanceIsPrioritized(): void
    {
        // Player 1 used table 1 in a previous round
        $history = $this->createMockHistoryWithTableUsage(['bcp_player1' => [1]]);

        $pairings = [
            new Pairing('bcp_player1', 'Player 1', 2, 'bcp_player2', 'Player 2', 2, null, 2, 2),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        $this->assertCount(1, $result->allocations);

        // Should choose table 2 to avoid reuse
        $this->assertEquals(
            2,
            $result->allocations[0]['tableNumber'],
            'Should avoid table 1 which player previously used'
        );
    }

    /**
     * Test terrain reuse avoidance is prioritized.
     *
     * FR-007.3: No player plays on same terrain twice (when possible).
     *
     * @group priority
     */
    public function testTerrainReuseAvoidanceIsPrioritized(): void
    {
        // Player 1 experienced terrain type 1 (Urban)
        $history = $this->createMockHistoryWithTerrainUsage(['bcp_player1' => [1]]);

        $pairings = [
            new Pairing('bcp_player1', 'Player 1', 2, 'bcp_player2', 'Player 2', 2, null, 2, 2),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
            ['tableNumber' => 2, 'terrainTypeId' => 2, 'terrainTypeName' => 'Industrial'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        $this->assertCount(1, $result->allocations);

        // Should choose table 2 (Industrial) to avoid Urban terrain reuse
        $this->assertEquals(
            2,
            $result->allocations[0]['tableNumber'],
            'Should avoid table with terrain player previously experienced'
        );
    }

    /**
     * Test original BCP table preference.
     *
     * FR-007: Prefer original BCP table assignments when no constraints.
     *
     * @group priority
     */
    public function testOriginalBcpTablePreferred(): void
    {
        $history = $this->createCleanHistory();

        // Pairing with original BCP table = 3
        $pairings = [
            new Pairing('bcp_player1', 'Player 1', 2, 'bcp_player2', 'Player 2', 2, 3, 2, 2),
        ];

        // Tables in random order
        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 5, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 3, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 4, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        $this->assertCount(1, $result->allocations);

        // Should choose table 3 (original BCP table)
        $this->assertEquals(
            3,
            $result->allocations[0]['tableNumber'],
            'Should prefer original BCP table when no constraints'
        );
    }

    /**
     * Test priority of table reuse over terrain reuse.
     *
     * FR-007: Table reuse is more costly than terrain reuse.
     *
     * @group priority
     */
    public function testTableReusePriorityOverTerrainReuse(): void
    {
        // Player used table 1, experienced terrain type 2
        $history = $this->createMockHistoryWithBothUsage(
            ['bcp_player1' => [1]],
            ['bcp_player1' => [2]]
        );

        $pairings = [
            new Pairing('bcp_player1', 'Player 1', 2, 'bcp_player2', 'Player 2', 2, null, 2, 2),
        ];

        // Table 1: used before (table reuse), terrain 1 (no terrain conflict)
        // Table 2: not used (no table conflict), terrain 2 (terrain reuse)
        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Urban'],
            ['tableNumber' => 2, 'terrainTypeId' => 2, 'terrainTypeName' => 'Industrial'],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // Should choose table 2 (accepts terrain reuse to avoid table reuse)
        $this->assertEquals(
            2,
            $result->allocations[0]['tableNumber'],
            'Should prioritize avoiding table reuse over terrain reuse'
        );
    }

    /**
     * Test deterministic allocation with tie-breaking.
     *
     * Same input should always produce same output.
     *
     * @group priority
     */
    public function testDeterministicAllocation(): void
    {
        $history = $this->createCleanHistory();

        $pairings = [
            new Pairing('bcp_a1', 'Player A1', 2, 'bcp_a2', 'Player A2', 2, null, 2, 2),
            new Pairing('bcp_b1', 'Player B1', 2, 'bcp_b2', 'Player B2', 2, null, 2, 2),
            new Pairing('bcp_c1', 'Player C1', 2, 'bcp_c2', 'Player C2', 2, null, 2, 2),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 3, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        // Run multiple times
        $results = [];
        for ($i = 0; $i < 5; $i++) {
            $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);
            $allocation = array_map(fn($a) => [
                'p1' => $a['player1']['bcpId'],
                'table' => $a['tableNumber'],
            ], $result->allocations);
            $results[] = serialize($allocation);
        }

        // All results should be identical
        $uniqueResults = array_unique($results);
        $this->assertCount(
            1,
            $uniqueResults,
            'Allocation should be deterministic - same input must produce same output'
        );
    }

    /**
     * Test stable sorting by BCP ID for equal scores.
     *
     * @group priority
     */
    public function testStableSortingByBcpId(): void
    {
        $history = $this->createCleanHistory();

        // All pairings have the same total score
        $pairings = [
            new Pairing('bcp_z1', 'Player Z1', 2, 'bcp_z2', 'Player Z2', 2, null, 2, 2),
            new Pairing('bcp_a1', 'Player A1', 2, 'bcp_a2', 'Player A2', 2, null, 2, 2),
            new Pairing('bcp_m1', 'Player M1', 2, 'bcp_m2', 'Player M2', 2, null, 2, 2),
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 3, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);

        // With equal scores, should be sorted by BCP ID (alphabetically)
        // bcp_a1 should get table 1, bcp_m1 should get table 2, bcp_z1 should get table 3
        $allocByBcpId = [];
        foreach ($result->allocations as $alloc) {
            $allocByBcpId[$alloc['player1']['bcpId']] = $alloc['tableNumber'];
        }

        $this->assertLessThan(
            $allocByBcpId['bcp_m1'],
            $allocByBcpId['bcp_a1'],
            'bcp_a1 should get lower table number than bcp_m1'
        );
        $this->assertLessThan(
            $allocByBcpId['bcp_z1'],
            $allocByBcpId['bcp_m1'],
            'bcp_m1 should get lower table number than bcp_z1'
        );
    }

    /**
     * Test Round 1 uses BCP original table assignments.
     *
     * FR-007.1: For round 1, use BCP's table assignments.
     *
     * @group priority
     */
    public function testRound1UsesBcpOriginalAssignments(): void
    {
        $history = $this->createCleanHistory();

        $pairings = [
            new Pairing('bcp_p1', 'Player 1', 0, 'bcp_p2', 'Player 2', 0, 3, 0, 0), // BCP assigned table 3
            new Pairing('bcp_p3', 'Player 3', 0, 'bcp_p4', 'Player 4', 0, 1, 0, 0), // BCP assigned table 1
        ];

        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 2, 'terrainTypeId' => null, 'terrainTypeName' => null],
            ['tableNumber' => 3, 'terrainTypeId' => null, 'terrainTypeName' => null],
        ];

        $result = $this->allocationService->generateAllocations($pairings, $tables, 1, $history);

        // Should use BCP assignments for round 1
        $allocByPlayer = [];
        foreach ($result->allocations as $alloc) {
            $allocByPlayer[$alloc['player1']['bcpId']] = $alloc['tableNumber'];
        }

        $this->assertEquals(3, $allocByPlayer['bcp_p1'], 'Player 1 should get BCP-assigned table 3');
        $this->assertEquals(1, $allocByPlayer['bcp_p3'], 'Player 3 should get BCP-assigned table 1');
    }

    /**
     * Test cost calculation weights are applied correctly.
     *
     * @group priority
     */
    public function testCostCalculationWeights(): void
    {
        // Verify the cost constants are in the correct order
        $this->assertGreaterThan(
            CostCalculator::COST_TERRAIN_REUSE,
            CostCalculator::COST_TABLE_REUSE,
            'Table reuse cost should be higher than terrain reuse cost'
        );

        $this->assertGreaterThan(
            CostCalculator::COST_BCP_TABLE_MISMATCH,
            CostCalculator::COST_TERRAIN_REUSE,
            'Terrain reuse cost should be higher than BCP table mismatch cost'
        );

        // Verify specific values match spec (P1 > P2 > P3)
        $this->assertEquals(100000, CostCalculator::COST_TABLE_REUSE);
        $this->assertEquals(10000, CostCalculator::COST_TERRAIN_REUSE);
        $this->assertEquals(1, CostCalculator::COST_BCP_TABLE_MISMATCH);
    }

    // Helper methods

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
