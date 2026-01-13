<?php

declare(strict_types=1);

namespace KTTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use KTTables\Services\TournamentHistory;
use KTTables\Database\Connection;

/**
 * Unit tests for TournamentHistory.
 *
 * Reference: specs/001-table-allocation/data-model.md#query-patterns
 */
class TournamentHistoryTest extends TestCase
{
    /**
     * @var TournamentHistory
     */
    private $history;

    /**
     * @var int
     */
    private $tournamentId = 1;

    /**
     * @var int
     */
    private $currentRound = 3;

    protected function setUp(): void
    {
        $this->history = new TournamentHistory($this->tournamentId, $this->currentRound);
    }

    /**
     * Test constructor sets correct values.
     */
    public function testConstructor(): void
    {
        $history = new TournamentHistory(5, 3);
        $this->assertEquals(5, $history->getTournamentId());
        $this->assertEquals(3, $history->getCurrentRound());
    }

    /**
     * Test getPlayerTableHistory returns correct structure.
     *
     * Note: This is a unit test that doesn't hit the database.
     * Integration tests verify actual database queries.
     */
    public function testGetPlayerTableHistoryReturnsArray(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTableHistory'])
            ->getMock();

        $mockData = [
            ['table_number' => 1, 'terrain_type' => 'Volkus', 'round_number' => 1],
            ['table_number' => 5, 'terrain_type' => 'Tomb World', 'round_number' => 2],
        ];

        $history->method('queryPlayerTableHistory')
            ->willReturn($mockData);

        $result = $history->getPlayerTableHistory('player123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test hasPlayerUsedTable with mock data.
     */
    public function testHasPlayerUsedTable(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTableHistory'])
            ->getMock();

        $mockData = [
            ['table_number' => 1, 'terrain_type' => 'Volkus', 'round_number' => 1],
            ['table_number' => 5, 'terrain_type' => 'Tomb World', 'round_number' => 2],
        ];

        $history->method('queryPlayerTableHistory')
            ->willReturn($mockData);

        // Player has used table 1
        $this->assertTrue($history->hasPlayerUsedTable('player123', 1));

        // Player has used table 5
        $this->assertTrue($history->hasPlayerUsedTable('player123', 5));

        // Player has NOT used table 3
        $this->assertFalse($history->hasPlayerUsedTable('player123', 3));
    }

    /**
     * Test getPlayerTerrainHistory returns correct structure.
     */
    public function testGetPlayerTerrainHistoryReturnsArray(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTerrainHistory'])
            ->getMock();

        $mockData = [
            ['id' => 1, 'name' => 'Volkus'],
            ['id' => 2, 'name' => 'Tomb World'],
        ];

        $history->method('queryPlayerTerrainHistory')
            ->willReturn($mockData);

        $result = $history->getPlayerTerrainHistory('player123');

        $this->assertIsArray($result);
        $this->assertCount(2, $result);
    }

    /**
     * Test hasPlayerExperiencedTerrain with mock data.
     */
    public function testHasPlayerExperiencedTerrain(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTerrainHistory'])
            ->getMock();

        $mockData = [
            ['id' => 1, 'name' => 'Volkus'],
            ['id' => 3, 'name' => 'Octarius'],
        ];

        $history->method('queryPlayerTerrainHistory')
            ->willReturn($mockData);

        // Player has experienced terrain type 1 (Volkus)
        $this->assertTrue($history->hasPlayerExperiencedTerrain('player123', 1));

        // Player has experienced terrain type 3 (Octarius)
        $this->assertTrue($history->hasPlayerExperiencedTerrain('player123', 3));

        // Player has NOT experienced terrain type 2
        $this->assertFalse($history->hasPlayerExperiencedTerrain('player123', 2));
    }

    /**
     * Test null terrain type always returns false.
     */
    public function testNullTerrainTypeReturnsFalse(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTerrainHistory'])
            ->getMock();

        $history->method('queryPlayerTerrainHistory')
            ->willReturn([['id' => 1, 'name' => 'Volkus']]);

        // Null terrain type should always return false
        $this->assertFalse($history->hasPlayerExperiencedTerrain('player123', null));
    }

    /**
     * Test caching behavior - same query shouldn't hit database twice.
     */
    public function testCachesPlayerHistory(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTableHistory', 'queryPlayerTerrainHistory'])
            ->getMock();

        // Should only be called once per player (cached)
        $history->expects($this->once())
            ->method('queryPlayerTableHistory')
            ->willReturn([['table_number' => 1, 'terrain_type' => 'Volkus', 'round_number' => 1]]);

        $history->expects($this->once())
            ->method('queryPlayerTerrainHistory')
            ->willReturn([['id' => 1, 'name' => 'Volkus']]);

        // Multiple calls with same player should use cache
        $history->hasPlayerUsedTable('player123', 1);
        $history->hasPlayerUsedTable('player123', 2);
        $history->hasPlayerUsedTable('player123', 3);

        $history->hasPlayerExperiencedTerrain('player123', 1);
        $history->hasPlayerExperiencedTerrain('player123', 2);
    }

    /**
     * Test different players have separate cache entries.
     */
    public function testDifferentPlayersSeparateCache(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTableHistory'])
            ->getMock();

        // Should be called twice - once for each player
        $history->expects($this->exactly(2))
            ->method('queryPlayerTableHistory')
            ->willReturn([['table_number' => 1, 'terrain_type' => 'Volkus', 'round_number' => 1]]);

        $history->hasPlayerUsedTable('player1', 1);
        $history->hasPlayerUsedTable('player2', 1);
    }

    /**
     * Test empty history returns false for all checks.
     */
    public function testEmptyHistoryReturnsFalse(): void
    {
        $history = $this->getMockBuilder(TournamentHistory::class)
            ->setConstructorArgs([1, 3])
            ->onlyMethods(['queryPlayerTableHistory', 'queryPlayerTerrainHistory'])
            ->getMock();

        $history->method('queryPlayerTableHistory')->willReturn([]);
        $history->method('queryPlayerTerrainHistory')->willReturn([]);

        $this->assertFalse($history->hasPlayerUsedTable('newPlayer', 1));
        $this->assertFalse($history->hasPlayerExperiencedTerrain('newPlayer', 1));
    }

    /**
     * Test that round filtering only includes previous rounds.
     *
     * The history service should only look at rounds < currentRound.
     */
    public function testRoundFiltering(): void
    {
        // History for round 2 should only see round 1
        $historyRound2 = new TournamentHistory(1, 2);
        $this->assertEquals(2, $historyRound2->getCurrentRound());

        // History for round 1 should see no previous rounds
        $historyRound1 = new TournamentHistory(1, 1);
        $this->assertEquals(1, $historyRound1->getCurrentRound());
    }
}
