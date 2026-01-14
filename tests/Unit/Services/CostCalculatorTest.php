<?php

declare(strict_types=1);

namespace KTTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use KTTables\Services\CostCalculator;
use KTTables\Services\CostResult;
use KTTables\Services\TournamentHistory;

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
        $this->assertEquals(1, CostCalculator::COST_TABLE_NUMBER);
    }

    /**
     * Test base cost is table number.
     *
     * P3: Lower table numbers preferred.
     */
    public function testBaseCostIsTableNumber(): void
    {
        $history = $this->createMockHistory(false, false);

        // Table 1
        $result = $this->calculator->calculate(1, 1, null, null, $history);
        $this->assertEquals(1, $result->totalCost);
        $this->assertEquals(1, $result->costBreakdown['tableNumber']);
        $this->assertEquals(0, $result->costBreakdown['tableReuse']);
        $this->assertEquals(0, $result->costBreakdown['terrainReuse']);

        // Table 5
        $result = $this->calculator->calculate(1, 5, null, null, $history);
        $this->assertEquals(5, $result->totalCost);
        $this->assertEquals(5, $result->costBreakdown['tableNumber']);
    }

    /**
     * Test table reuse adds high cost.
     *
     * P1: Avoid tables players have used before (FR-007.2).
     */
    public function testTableReuseCostForPlayer1(): void
    {
        $history = $this->createMockHistory(true, false);

        $result = $this->calculator->calculate(1, 3, null, null, $history);

        // 100000 (table reuse) + 3 (table number) = 100003
        $this->assertEquals(100003, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertCount(1, $result->reasons);
        $this->assertStringContainsString('previously played on table', $result->reasons[0]);
    }

    /**
     * Test both players having used the table doubles the cost.
     */
    public function testTableReuseCostForBothPlayers(): void
    {
        $history = $this->createMockHistoryBothPlayers(true, false);

        // Pass both player1Id and player2Id to check both players
        $result = $this->calculator->calculate(1, 3, null, null, $history, 2);

        // 200000 (table reuse x2) + 3 (table number) = 200003
        $this->assertEquals(200003, $result->totalCost);
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

        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history);

        // 10000 (terrain reuse) + 3 (table number) = 10003
        $this->assertEquals(10003, $result->totalCost);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertCount(1, $result->reasons);
        $this->assertStringContainsString('previously experienced', $result->reasons[0]);
    }

    /**
     * Test both players having experienced terrain doubles cost.
     */
    public function testTerrainReuseCostForBothPlayers(): void
    {
        $history = $this->createMockHistoryBothPlayers(false, true);

        // Pass both player1Id and player2Id to check both players
        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history, 2);

        // 20000 (terrain reuse x2) + 3 (table number) = 20003
        $this->assertEquals(20003, $result->totalCost);
        $this->assertEquals(20000, $result->costBreakdown['terrainReuse']);
        $this->assertCount(2, $result->reasons);
    }

    /**
     * Test null terrain type doesn't add terrain cost.
     */
    public function testNullTerrainNoCost(): void
    {
        $history = $this->createMockHistory(false, true); // Would return true for terrain check

        $result = $this->calculator->calculate(1, 3, null, null, $history);

        $this->assertEquals(3, $result->totalCost);
        $this->assertEquals(0, $result->costBreakdown['terrainReuse']);
    }

    /**
     * Test combined costs.
     */
    public function testCombinedCosts(): void
    {
        $history = $this->createMockHistory(true, true);

        $result = $this->calculator->calculate(1, 5, 1, 'Volkus', $history);

        // 100000 (table reuse) + 10000 (terrain reuse) + 5 (table number) = 110005
        $this->assertEquals(110005, $result->totalCost);
        $this->assertEquals(100000, $result->costBreakdown['tableReuse']);
        $this->assertEquals(10000, $result->costBreakdown['terrainReuse']);
        $this->assertEquals(5, $result->costBreakdown['tableNumber']);
        $this->assertCount(2, $result->reasons);
    }

    /**
     * Test priority ordering (table reuse > terrain reuse > table number).
     */
    public function testPriorityOrdering(): void
    {
        $noHistory = $this->createMockHistory(false, false);
        $tableReuse = $this->createMockHistory(true, false);
        $terrainReuse = $this->createMockHistory(false, true);

        // Table 1 with table reuse should cost more than table 20 with terrain reuse
        $table1WithTableReuse = $this->calculator->calculate(1, 1, null, null, $tableReuse);
        $table20WithTerrainReuse = $this->calculator->calculate(1, 20, 1, 'Volkus', $terrainReuse);
        $table20Clean = $this->calculator->calculate(1, 20, null, null, $noHistory);

        $this->assertGreaterThan($table20WithTerrainReuse->totalCost, $table1WithTableReuse->totalCost);
        $this->assertGreaterThan($table20Clean->totalCost, $table20WithTerrainReuse->totalCost);
    }

    /**
     * Test CostResult structure.
     */
    public function testCostResultStructure(): void
    {
        $history = $this->createMockHistory(true, true);

        $result = $this->calculator->calculate(1, 3, 1, 'Volkus', $history);

        $this->assertInstanceOf(CostResult::class, $result);
        $this->assertIsInt($result->totalCost);
        $this->assertIsArray($result->costBreakdown);
        $this->assertArrayHasKey('tableReuse', $result->costBreakdown);
        $this->assertArrayHasKey('terrainReuse', $result->costBreakdown);
        $this->assertArrayHasKey('tableNumber', $result->costBreakdown);
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
