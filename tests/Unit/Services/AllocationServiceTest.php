<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;
use TournamentTables\Services\AllocationResult;

/**
 * Unit tests for AllocationService.
 *
 * Reference: specs/001-table-allocation/research.md#algorithm-overview
 * Tests priority rules FR-007.1-4
 */
class AllocationServiceTest extends TestCase
{
    /**
     * @var AllocationService
     */
    private $service;

    protected function setUp(): void
    {
        $this->service = new AllocationService(new CostCalculator());
    }

    /**
     * FR-007.1: Round 1 uses BCP's original table assignments.
     */
    public function testRound1UsesBcpAssignments(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 0, 0, 5), // BCP assigned table 5
            $this->createPairing('p3', 'p4', 0, 0, 3), // BCP assigned table 3
            $this->createPairing('p5', 'p6', 0, 0, 1), // BCP assigned table 1
        ];

        $tables = $this->createTables(10);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            1, // Round 1
            $history
        );

        $this->assertCount(3, $result->allocations);

        // Should preserve BCP table assignments
        $this->assertEquals(5, $result->allocations[0]['tableNumber']);
        $this->assertEquals('p1', $result->allocations[0]['player1']['bcpId']);

        $this->assertEquals(3, $result->allocations[1]['tableNumber']);
        $this->assertEquals('p3', $result->allocations[1]['player1']['bcpId']);

        $this->assertEquals(1, $result->allocations[2]['tableNumber']);
        $this->assertEquals('p5', $result->allocations[2]['player1']['bcpId']);

        // Round 1 should have isRound1 flag
        foreach ($result->allocations as $allocation) {
            $this->assertTrue($allocation['reason']['isRound1']);
        }
    }

    /**
     * FR-007.2: Avoid tables players have used before.
     */
    public function testAvoidsTablesPlayersUsedBefore(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        $tables = $this->createTables(3);

        // Player 1 previously used table 1
        $history = $this->createMock(TournamentHistory::class);
        $history->method('hasPlayerUsedTable')
            ->willReturnCallback(function ($playerId, $tableNumber) {
                return $playerId === 'p1' && $tableNumber === 1;
            });
        $history->method('hasPlayerExperiencedTerrain')
            ->willReturn(false);

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2, // Round 2+
            $history
        );

        $this->assertCount(1, $result->allocations);
        // Should NOT assign table 1 (used by p1), should assign table 2 (lowest available)
        $this->assertEquals(2, $result->allocations[0]['tableNumber']);
    }

    /**
     * FR-007.3: Prefer terrain types not yet experienced.
     */
    public function testPrefersNewTerrainTypes(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        // Tables with different terrain types
        $tables = [
            ['tableNumber' => 1, 'terrainTypeId' => 1, 'terrainTypeName' => 'Volkus'],
            ['tableNumber' => 2, 'terrainTypeId' => 2, 'terrainTypeName' => 'Tomb World'],
            ['tableNumber' => 3, 'terrainTypeId' => 3, 'terrainTypeName' => 'Octarius'],
        ];

        // Player 1 previously experienced Volkus
        $history = $this->createMock(TournamentHistory::class);
        $history->method('hasPlayerUsedTable')
            ->willReturn(false);
        $history->method('hasPlayerExperiencedTerrain')
            ->willReturnCallback(function ($playerId, $terrainTypeId) {
                return $playerId === 'p1' && $terrainTypeId === 1;
            });

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $this->assertCount(1, $result->allocations);
        // Should assign table 2 (Tomb World - new terrain) over table 1 (Volkus - experienced)
        $this->assertEquals(2, $result->allocations[0]['tableNumber']);
    }

    /**
     * FR-007.4: Higher-scoring players get lower table numbers.
     */
    public function testHigherScoringPlayersGetLowerTables(): void
    {
        $pairings = [
            $this->createPairing('lowScore1', 'lowScore2', 0, 0, null),
            $this->createPairing('highScore1', 'highScore2', 4, 4, null),
            $this->createPairing('midScore1', 'midScore2', 2, 2, null),
        ];

        $tables = $this->createTables(3);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $this->assertCount(3, $result->allocations);

        // High score pairing should get table 1
        $highScoreAllocation = $this->findAllocationByPlayer($result->allocations, 'highScore1');
        $this->assertEquals(1, $highScoreAllocation['tableNumber']);

        // Mid score pairing should get table 2
        $midScoreAllocation = $this->findAllocationByPlayer($result->allocations, 'midScore1');
        $this->assertEquals(2, $midScoreAllocation['tableNumber']);

        // Low score pairing should get table 3
        $lowScoreAllocation = $this->findAllocationByPlayer($result->allocations, 'lowScore1');
        $this->assertEquals(3, $lowScoreAllocation['tableNumber']);
    }

    /**
     * Test stable sort - tied scores sorted by BCP ID.
     */
    public function testStableSortByBcpIdOnTiedScores(): void
    {
        $pairings = [
            $this->createPairing('zzz1', 'zzz2', 2, 2, null),
            $this->createPairing('aaa1', 'aaa2', 2, 2, null),
            $this->createPairing('mmm1', 'mmm2', 2, 2, null),
        ];

        $tables = $this->createTables(3);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        // With same scores, should sort deterministically by BCP ID (ascending)
        $this->assertEquals('aaa1', $result->allocations[0]['player1']['bcpId']);
        $this->assertEquals('mmm1', $result->allocations[1]['player1']['bcpId']);
        $this->assertEquals('zzz1', $result->allocations[2]['player1']['bcpId']);
    }

    /**
     * Test conflict detection when table reuse is unavoidable.
     */
    public function testConflictDetectionForTableReuse(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        $tables = $this->createTables(1); // Only 1 table

        // Player 1 already used table 1
        $history = $this->createMock(TournamentHistory::class);
        $history->method('hasPlayerUsedTable')
            ->willReturnCallback(function ($playerId, $tableNumber) {
                return $playerId === 'p1' && $tableNumber === 1;
            });
        $history->method('hasPlayerExperiencedTerrain')
            ->willReturn(false);

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        // Should still assign table (best effort), but flag conflict
        $this->assertCount(1, $result->allocations);
        $this->assertEquals(1, $result->allocations[0]['tableNumber']);

        // Should have conflict flagged
        $this->assertNotEmpty($result->allocations[0]['reason']['conflicts']);
        $this->assertContains('TABLE_REUSE', array_column($result->allocations[0]['reason']['conflicts'], 'type'));
    }

    /**
     * Test allocation reason audit trail structure.
     */
    public function testAllocationReasonStructure(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        $tables = $this->createTables(3);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $reason = $result->allocations[0]['reason'];

        $this->assertArrayHasKey('timestamp', $reason);
        $this->assertArrayHasKey('totalCost', $reason);
        $this->assertArrayHasKey('costBreakdown', $reason);
        $this->assertArrayHasKey('tableReuse', $reason['costBreakdown']);
        $this->assertArrayHasKey('terrainReuse', $reason['costBreakdown']);
        $this->assertArrayHasKey('tableNumber', $reason['costBreakdown']);
        $this->assertArrayHasKey('reasons', $reason);
        $this->assertArrayHasKey('alternativesConsidered', $reason);
        $this->assertArrayHasKey('isRound1', $reason);
        $this->assertArrayHasKey('conflicts', $reason);
    }

    /**
     * Test alternatives considered in allocation reason.
     */
    public function testAlternativesConsidered(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        $tables = $this->createTables(5);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $alternatives = $result->allocations[0]['reason']['alternativesConsidered'];

        // Should show costs for tables 2, 3, 4, 5 (not selected table 1)
        $this->assertCount(4, $alternatives);
        $this->assertArrayHasKey(2, $alternatives);
        $this->assertArrayHasKey(3, $alternatives);
        $this->assertArrayHasKey(4, $alternatives);
        $this->assertArrayHasKey(5, $alternatives);
    }

    /**
     * Test multiple pairings use different tables.
     */
    public function testMultiplePairingsUseDifferentTables(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 4, 4, null),
            $this->createPairing('p3', 'p4', 3, 3, null),
            $this->createPairing('p5', 'p6', 2, 2, null),
        ];

        $tables = $this->createTables(3);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $usedTables = array_column($result->allocations, 'tableNumber');
        $this->assertCount(3, array_unique($usedTables));
    }

    /**
     * Test overall result structure.
     */
    public function testAllocationResultStructure(): void
    {
        $pairings = [
            $this->createPairing('p1', 'p2', 2, 2, null),
        ];

        $tables = $this->createTables(3);
        $history = $this->createMockHistoryEmpty();

        $result = $this->service->generateAllocations(
            $pairings,
            $tables,
            2,
            $history
        );

        $this->assertInstanceOf(AllocationResult::class, $result);
        $this->assertIsArray($result->allocations);
        $this->assertIsArray($result->conflicts);
        $this->assertIsString($result->summary);
    }

    /**
     * Create a pairing for testing.
     *
     * Total scores default to matching round scores for backward compatibility.
     */
    private function createPairing(
        string $player1BcpId,
        string $player2BcpId,
        int $player1Score,
        int $player2Score,
        ?int $bcpTableNumber,
        ?int $player1TotalScore = null,
        ?int $player2TotalScore = null
    ): Pairing {
        return new Pairing(
            $player1BcpId,
            "Player {$player1BcpId}",
            $player1Score,
            $player2BcpId,
            "Player {$player2BcpId}",
            $player2Score,
            $bcpTableNumber,
            $player1TotalScore ?? $player1Score,
            $player2TotalScore ?? $player2Score
        );
    }

    /**
     * Create simple tables array for testing.
     */
    private function createTables(int $count): array
    {
        $tables = [];
        for ($i = 1; $i <= $count; $i++) {
            $tables[] = [
                'tableNumber' => $i,
                'terrainTypeId' => null,
                'terrainTypeName' => null,
            ];
        }
        return $tables;
    }

    /**
     * Create mock history with no previous usage.
     */
    private function createMockHistoryEmpty(): TournamentHistory
    {
        $mock = $this->createMock(TournamentHistory::class);
        $mock->method('hasPlayerUsedTable')->willReturn(false);
        $mock->method('hasPlayerExperiencedTerrain')->willReturn(false);
        return $mock;
    }

    /**
     * Find allocation by player BCP ID.
     */
    private function findAllocationByPlayer(array $allocations, string $bcpId): ?array
    {
        foreach ($allocations as $allocation) {
            if ($allocation['player1']['bcpId'] === $bcpId) {
                return $allocation;
            }
        }
        return null;
    }
}
