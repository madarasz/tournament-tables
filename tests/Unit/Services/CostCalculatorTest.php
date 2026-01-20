<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\CostResult;
use TournamentTables\Services\TournamentHistory;

/**
 * Unit tests for CostCalculator.
 *
 * Reference: specs/001-table-allocation/research.md#cost-function
 * Tests priority rules FR-007.2, FR-007.3, FR-007.4
 */
class CostCalculatorTest extends TestCase
{
    /**
     * @var CostCalculator
     */
    private $calculator;

    protected function setUp(): void
    {
        $this->calculator = new CostCalculator();
    }

    /**
     * Test cost constant values per research.md#cost-function.
     */
    public function testCostConstants(): void
    {
        $this->assertEquals(100000, CostCalculator::COST_TABLE_REUSE);
        $this->assertEquals(10000, CostCalculator::COST_TERRAIN_REUSE);
        $this->assertEquals(1, CostCalculator::COST_BCP_TABLE_MISMATCH);
    }

    /**
     * Test BCP table match costs zero.
     *
     * P3: Prefer original BCP table assignments.
     */
    public function testBcpTableMatchCostsZero(): void
    {
        $history = $this->createMockHistory(false, false);

        // Table 5 with BCP original = 5 (match)
        $result = $this->calculator->calculate(1, 5, null, null, $history, null, 5);
        $this->assertEquals(0, $result->totalCost);
        $this->assertEquals(0, $result->costBreakdown['bcpTableMismatch']);
        $this->assertEquals(0, $result->costBreakdown['tableReuse']);
        $this->assertEquals(0, $result->costBreakdown['terrainReuse']);
    }

    /**
     * Test BCP table mismatch costs 1.
     *
     * P3: Non-matching table incurs cost of 1.
     */
    public function testBcpTableMismatchCostsOne(): void
    {
        $history = $this->createMockHistory(false, false);

        // Table 3 with BCP original = 5 (mismatch)
        $result = $this->calculator->calculate(1, 3, null, null, $history, null, 5);
        $this->assertEquals(1, $result->totalCost);
        $this->assertEquals(1, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test null BCP table costs zero.
     *
     * When no original BCP table is specified, P3 cost is 0.
     */
    public function testNullBcpTableCostsZero(): void
    {
        $history = $this->createMockHistory(false, false);

        // No BCP original table specified
        $result = $this->calculator->calculate(1, 3, null, null, $history, null, null);
        $this->assertEquals(0, $result->totalCost);
        $this->assertEquals(0, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test table reuse adds high cost.
     *
     * P1: Avoid tables players have used before (FR-007.2).
     */
    public function testTableReuseCostForPlayer1(): void
    {
        $history = $this->createMockHistory(true, false);

        // Table 3 with BCP original = 3 (match, so P3 = 0)
        $result = $this->calculator->calculate(1, 3, null, null, $history, null, 3);

        // 100000 (table reuse) + 0 (BCP match) = 100000
        $this->assertEquals(100000, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertCount(1, $result->reasons);
        $this->assertStringContainsString('previously played on table', $result->reasons[0]);
    }

    /**
     * Test table reuse with BCP mismatch combines costs.
     */
    public function testTableReuseCostWithBcpMismatch(): void
    {
        $history = $this->createMockHistory(true, false);

        // Table 3 with BCP original = 5 (mismatch, so P3 = 1)
        $result = $this->calculator->calculate(1, 3, null, null, $history, null, 5);

        // 100000 (table reuse) + 1 (BCP mismatch) = 100001
        $this->assertEquals(100001, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertEquals(1, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test both players having used the table doubles the cost.
     */
    public function testTableReuseCostForBothPlayers(): void
    {
        $history = $this->createMockHistoryBothPlayers(true, false);

        // Pass both player1Id and player2Id to check both players
        // Table 3 with BCP original = 3 (match)
        $result = $this->calculator->calculate(1, 3, null, null, $history, 2, 3);

        // 200000 (table reuse x2) + 0 (BCP match) = 200000
        $this->assertEquals(200000, $result->totalCost);
        $this->assertEquals(200000, $result->costBreakdown['tableReuse']);
        $this->assertCount(2, $result->reasons);
    }

    /**
     * Test terrain reuse adds medium cost.
     *
     * P2: Prefer terrain types not yet experienced (FR-007.3).
     */
    public function testTerrainReuseCostForPlayer1(): void
    {
        $history = $this->createMockHistory(false, true);

        // Table 3 with BCP original = 3 (match)
        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history, null, 3);

        // 10000 (terrain reuse) + 0 (BCP match) = 10000
        $this->assertEquals(10000, $result->totalCost);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertCount(1, $result->reasons);
        $this->assertStringContainsString('previously experienced', $result->reasons[0]);
    }

    /**
     * Test terrain reuse with BCP mismatch combines costs.
     */
    public function testTerrainReuseCostWithBcpMismatch(): void
    {
        $history = $this->createMockHistory(false, true);

        // Table 3 with BCP original = 5 (mismatch)
        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history, null, 5);

        // 10000 (terrain reuse) + 1 (BCP mismatch) = 10001
        $this->assertEquals(10001, $result->totalCost);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertEquals(1, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test both players having experienced terrain doubles cost.
     */
    public function testTerrainReuseCostForBothPlayers(): void
    {
        $history = $this->createMockHistoryBothPlayers(false, true);

        // Pass both player1Id and player2Id to check both players
        // Table 3 with BCP original = 3 (match)
        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history, 2, 3);

        // 20000 (terrain reuse x2) + 0 (BCP match) = 20000
        $this->assertEquals(20000, $result->totalCost);
        $this->assertEquals(20000, $result->costBreakdown['terrainReuse']);
        $this->assertCount(2, $result->reasons);
    }

    /**
     * Test null terrain type doesn't add terrain cost.
     */
    public function testNullTerrainNoCost(): void
    {
        $history = $this->createMockHistory(false, true); // Would return true for terrain check

        // Table 3 with BCP original = 3 (match)
        $result = $this->calculator->calculate(1, 3, null, null, $history, null, 3);

        $this->assertEquals(0, $result->totalCost);
        $this->assertEquals(0, $result->costBreakdown['terrainReuse']);
        $this->assertEquals(0, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test combined costs.
     */
    public function testCombinedCosts(): void
    {
        $history = $this->createMockHistory(true, true);

        // Table 5 with BCP original = 3 (mismatch)
        $result = $this->calculator->calculate(1, 5, 1, 'Volkus', $history, null, 3);

        // 100000 (table reuse) + 10000 (terrain reuse) + 1 (BCP mismatch) = 110001
        $this->assertEquals(110001, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertEquals(1, $result->costBreakdown['bcpTableMismatch']);
        $this->assertCount(2, $result->reasons);
    }

    /**
     * Test combined costs with BCP match.
     */
    public function testCombinedCostsWithBcpMatch(): void
    {
        $history = $this->createMockHistory(true, true);

        // Table 5 with BCP original = 5 (match)
        $result = $this->calculator->calculate(1, 5, 1, 'Volkus', $history, null, 5);

        // 100000 (table reuse) + 10000 (terrain reuse) + 0 (BCP match) = 110000
        $this->assertEquals(110000, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertEquals(0, $result->costBreakdown['bcpTableMismatch']);
    }

    /**
     * Test priority ordering (table reuse > terrain reuse > BCP mismatch).
     */
    public function testPriorityOrdering(): void
    {
        $noHistory = $this->createMockHistory(false, false);
        $tableReuse = $this->createMockHistory(true, false);
        $terrainReuse = $this->createMockHistory(false, true);

        // Table 1 with table reuse (BCP match) should cost more than table 20 with terrain reuse (BCP match)
        $table1WithTableReuse = $this->calculator->calculate(1, 1, null, null, $tableReuse, null, 1);
        $table20WithTerrainReuse = $this->calculator->calculate(1, 20, 1, 'Volkus', $terrainReuse, null, 20);
        // Table 20 with BCP mismatch (no other constraints)
        $table20WithBcpMismatch = $this->calculator->calculate(1, 20, null, null, $noHistory, null, 5);

        $this->assertGreaterThan($table20WithTerrainReuse->totalCost, $table1WithTableReuse->totalCost);
        $this->assertGreaterThan($table20WithBcpMismatch->totalCost, $table20WithTerrainReuse->totalCost);
    }

    /**
     * Test CostResult structure.
     */
    public function testCostResultStructure(): void
    {
        $history = $this->createMockHistory(true, true);

        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history, null, 5);

        $this->assertInstanceOf(CostResult::class, $result);
        $this->assertIsInt($result->totalCost);
        $this->assertIsArray($result->costBreakdown);
        $this->assertArrayHasKey('tableReuse', $result->costBreakdown);
        $this->assertArrayHasKey('terrainReuse', $result->costBreakdown);
        $this->assertArrayHasKey('bcpTableMismatch', $result->costBreakdown);
        $this->assertIsArray($result->reasons);
    }

    /**
     * Create a mock TournamentHistory.
     */
    private function createMockHistory(bool $hasUsedTable, bool $hasExperiencedTerrain): TournamentHistory
    {
        $mock = $this->createMock(TournamentHistory::class);

        $mock->method('hasPlayerUsedTable')
            ->willReturn($hasUsedTable);

        $mock->method('hasPlayerExperiencedTerrain')
            ->willReturn($hasExperiencedTerrain);

        return $mock;
    }

    /**
     * Create a mock where both players have history.
     */
    private function createMockHistoryBothPlayers(bool $hasUsedTable, bool $hasExperiencedTerrain): TournamentHistory
    {
        $mock = $this->createMock(TournamentHistory::class);

        // Both player1 and player2 return true
        $mock->method('hasPlayerUsedTable')
            ->willReturn($hasUsedTable);

        $mock->method('hasPlayerExperiencedTerrain')
            ->willReturn($hasExperiencedTerrain);

        return $mock;
    }
}
