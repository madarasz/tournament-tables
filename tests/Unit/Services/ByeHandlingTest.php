<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\Pairing;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\BCPApiService;

/**
 * Unit tests for bye (odd player count) handling.
 *
 * When a tournament has an odd number of players, one player gets a "bye"
 * each round - they don't play a game. These tests verify correct handling
 * of bye pairings throughout the system.
 */
class ByeHandlingTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Pairing Class Tests
    // -------------------------------------------------------------------------

    /**
     * Test that a pairing with null player2 is detected as a bye.
     */
    public function testPairingWithNullPlayer2IsBye(): void
    {
        $pairing = new Pairing(
            'player1Id',
            'Player One',
            0,
            null, // player2BcpId
            null, // player2Name
            0,
            null  // bcpTableNumber
        );

        $this->assertTrue($pairing->isBye());
    }

    /**
     * Test that a pairing with player2 is not a bye.
     */
    public function testPairingWithPlayer2IsNotBye(): void
    {
        $pairing = new Pairing(
            'player1Id',
            'Player One',
            10,
            'player2Id',
            'Player Two',
            10,
            1
        );

        $this->assertFalse($pairing->isBye());
    }

    /**
     * Test getCombinedTotalScore for bye returns only player1's score.
     */
    public function testByePairingCombinedTotalScoreReturnsPlayer1Only(): void
    {
        $pairing = new Pairing(
            'player1Id',
            'Player One',
            15, // round score
            null,
            null,
            0,
            null,
            100, // player1TotalScore
            0    // player2TotalScore (ignored for bye)
        );

        $this->assertEquals(100, $pairing->getCombinedTotalScore());
    }

    /**
     * Test getCombinedTotalScore for regular pairing returns sum of both.
     */
    public function testRegularPairingCombinedTotalScoreReturnsBothPlayers(): void
    {
        $pairing = new Pairing(
            'player1Id',
            'Player One',
            10,
            'player2Id',
            'Player Two',
            10,
            1,
            100, // player1TotalScore
            80   // player2TotalScore
        );

        $this->assertEquals(180, $pairing->getCombinedTotalScore());
    }

    /**
     * Test getMinBcpId for bye returns player1's ID.
     */
    public function testByePairingGetMinBcpIdReturnsPlayer1(): void
    {
        $pairing = new Pairing(
            'zzz_player1',
            'Player One',
            0,
            null,
            null,
            0,
            null
        );

        $this->assertEquals('zzz_player1', $pairing->getMinBcpId());
    }

    /**
     * Test getMinBcpId for regular pairing returns the lower ID.
     */
    public function testRegularPairingGetMinBcpIdReturnsLowerOfBoth(): void
    {
        $pairing = new Pairing(
            'zzz_player1',
            'Player One',
            0,
            'aaa_player2',
            'Player Two',
            0,
            1
        );

        $this->assertEquals('aaa_player2', $pairing->getMinBcpId());
    }

    // -------------------------------------------------------------------------
    // BCPApiService Parsing Tests
    // -------------------------------------------------------------------------

    /**
     * Test parseApiResponse correctly identifies bye pairings.
     */
    public function testParseApiResponseIdentifiesByePairings(): void
    {
        $apiService = new BCPApiService();

        $apiResponse = [
            'active' => [
                // Regular pairing
                [
                    'player1' => [
                        'id' => 'p1id',
                        'user' => ['firstName' => 'John', 'lastName' => 'Doe'],
                    ],
                    'player2' => [
                        'id' => 'p2id',
                        'user' => ['firstName' => 'Jane', 'lastName' => 'Smith'],
                    ],
                    'player1Game' => ['points' => 10],
                    'player2Game' => ['points' => 8],
                    'table' => 1,
                ],
                // Bye pairing (no player2)
                [
                    'player1' => [
                        'id' => 'byePlayerId',
                        'user' => ['firstName' => 'Bye', 'lastName' => 'Player'],
                    ],
                    'player1Game' => ['points' => 0],
                    // No player2 at all
                ],
            ],
        ];

        $pairings = $apiService->parseApiResponse($apiResponse);

        $this->assertCount(2, $pairings);

        // First pairing is regular
        $this->assertFalse($pairings[0]->isBye());
        $this->assertEquals('p1id', $pairings[0]->player1BcpId);
        $this->assertEquals('p2id', $pairings[0]->player2BcpId);

        // Second pairing is bye (sorted last due to null table number)
        $byePairing = $pairings[1];
        $this->assertTrue($byePairing->isBye());
        $this->assertEquals('byePlayerId', $byePairing->player1BcpId);
        $this->assertNull($byePairing->player2BcpId);
        $this->assertNull($byePairing->player2Name);
    }

    /**
     * Test parseApiResponse handles player2 with empty ID as bye.
     */
    public function testParseApiResponseHandlesEmptyPlayer2IdAsBye(): void
    {
        $apiService = new BCPApiService();

        $apiResponse = [
            'active' => [
                [
                    'player1' => [
                        'id' => 'p1id',
                        'user' => ['firstName' => 'John', 'lastName' => 'Doe'],
                    ],
                    'player2' => [
                        'id' => '', // Empty ID
                        'user' => ['firstName' => '', 'lastName' => ''],
                    ],
                    'player1Game' => ['points' => 10],
                    'player2Game' => ['points' => 0],
                ],
            ],
        ];

        $pairings = $apiService->parseApiResponse($apiResponse);

        $this->assertCount(1, $pairings);
        $this->assertTrue($pairings[0]->isBye());
    }

    // -------------------------------------------------------------------------
    // AllocationService Tests
    // -------------------------------------------------------------------------

    /**
     * Test allocation generation separates bye from regular pairings.
     */
    public function testAllocationServiceSeparatesByePairings(): void
    {
        $service = new AllocationService(new CostCalculator());

        $pairings = [
            $this->createRegularPairing('p1', 'p2', 10, 10),
            $this->createByePairing('byePlayer'),
            $this->createRegularPairing('p3', 'p4', 8, 8),
        ];

        $tables = $this->createTables(2); // Only 2 tables for 2 regular pairings
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 2, $history);

        // Should have 3 allocations total
        $this->assertCount(3, $result->allocations);

        // Find the bye allocation
        $byeAllocation = null;
        $regularAllocations = [];
        foreach ($result->allocations as $allocation) {
            if ($allocation['player2'] === null || ($allocation['reason']['isBye'] ?? false)) {
                $byeAllocation = $allocation;
            } else {
                $regularAllocations[] = $allocation;
            }
        }

        // Bye allocation should exist
        $this->assertNotNull($byeAllocation);
        $this->assertNull($byeAllocation['tableNumber']);
        $this->assertNull($byeAllocation['player2']);
        $this->assertTrue($byeAllocation['reason']['isBye'] ?? false);
        $this->assertEquals('byePlayer', $byeAllocation['player1']['bcpId']);

        // Regular allocations should have table numbers
        $this->assertCount(2, $regularAllocations);
        foreach ($regularAllocations as $alloc) {
            $this->assertNotNull($alloc['tableNumber']);
            $this->assertNotNull($alloc['player2']);
        }
    }

    /**
     * Test bye allocations don't use tables.
     */
    public function testByeAllocationsDoNotUseTables(): void
    {
        $service = new AllocationService(new CostCalculator());

        // 3 pairings with 1 bye
        $pairings = [
            $this->createRegularPairing('p1', 'p2', 10, 10),
            $this->createByePairing('byePlayer'),
            $this->createRegularPairing('p3', 'p4', 8, 8),
        ];

        // Only 2 tables - should be enough since bye doesn't need a table
        $tables = $this->createTables(2);
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 2, $history);

        // Should not throw an exception despite having 3 pairings and only 2 tables
        $this->assertCount(3, $result->allocations);

        // Count tables used by regular allocations
        $tablesUsed = [];
        foreach ($result->allocations as $allocation) {
            if ($allocation['tableNumber'] !== null) {
                $tablesUsed[] = $allocation['tableNumber'];
            }
        }

        // Should only use 2 tables (for 2 regular pairings)
        $this->assertCount(2, $tablesUsed);
        $this->assertCount(2, array_unique($tablesUsed));
    }

    /**
     * Test bye allocation has correct reason structure.
     */
    public function testByeAllocationReasonStructure(): void
    {
        $service = new AllocationService(new CostCalculator());

        $pairings = [
            $this->createByePairing('byePlayer'),
        ];

        $tables = $this->createTables(1);
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 2, $history);

        $this->assertCount(1, $result->allocations);

        $byeAllocation = $result->allocations[0];
        $reason = $byeAllocation['reason'];

        $this->assertArrayHasKey('timestamp', $reason);
        $this->assertArrayHasKey('totalCost', $reason);
        $this->assertArrayHasKey('costBreakdown', $reason);
        $this->assertArrayHasKey('reasons', $reason);
        $this->assertArrayHasKey('isBye', $reason);
        $this->assertArrayHasKey('conflicts', $reason);

        $this->assertTrue($reason['isBye']);
        $this->assertEquals(0, $reason['totalCost']);
        $this->assertEmpty($reason['conflicts']);
        $this->assertContains('Bye - no opponent this round', $reason['reasons']);
    }

    /**
     * Test Round 1 bye allocations.
     */
    public function testRound1ByeAllocations(): void
    {
        $service = new AllocationService(new CostCalculator());

        $pairings = [
            $this->createRegularPairing('p1', 'p2', 0, 0, 1), // BCP table 1
            $this->createByePairing('byePlayer'),
        ];

        $tables = $this->createTables(2);
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 1, $history); // Round 1

        $this->assertCount(2, $result->allocations);

        // Find bye allocation
        $byeAllocation = null;
        foreach ($result->allocations as $allocation) {
            if ($allocation['player2'] === null) {
                $byeAllocation = $allocation;
                break;
            }
        }

        $this->assertNotNull($byeAllocation);
        $this->assertNull($byeAllocation['tableNumber']);
    }

    /**
     * Test multiple byes in same round (unusual but possible).
     */
    public function testMultipleByesInSameRound(): void
    {
        $service = new AllocationService(new CostCalculator());

        $pairings = [
            $this->createRegularPairing('p1', 'p2', 10, 10),
            $this->createByePairing('byePlayer1'),
            $this->createByePairing('byePlayer2'),
        ];

        $tables = $this->createTables(1);
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 2, $history);

        $this->assertCount(3, $result->allocations);

        $byeCount = 0;
        foreach ($result->allocations as $allocation) {
            if ($allocation['player2'] === null) {
                $byeCount++;
            }
        }

        $this->assertEquals(2, $byeCount);
    }

    /**
     * Test bye allocation has no conflicts.
     */
    public function testByeAllocationHasNoConflicts(): void
    {
        $service = new AllocationService(new CostCalculator());

        $pairings = [
            $this->createByePairing('byePlayer'),
        ];

        $tables = $this->createTables(1);
        $history = $this->createMockHistoryEmpty();

        $result = $service->generateAllocations($pairings, $tables, 2, $history);

        // Overall conflicts should be empty
        $this->assertEmpty($result->conflicts);

        // Bye allocation should have no conflicts
        $byeAllocation = $result->allocations[0];
        $this->assertEmpty($byeAllocation['reason']['conflicts']);
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Create a regular pairing for testing.
     */
    private function createRegularPairing(
        string $player1BcpId,
        string $player2BcpId,
        int $player1Score,
        int $player2Score,
        ?int $bcpTableNumber = null
    ): Pairing {
        return new Pairing(
            $player1BcpId,
            "Player {$player1BcpId}",
            $player1Score,
            $player2BcpId,
            "Player {$player2BcpId}",
            $player2Score,
            $bcpTableNumber,
            $player1Score,
            $player2Score
        );
    }

    /**
     * Create a bye pairing for testing.
     */
    private function createByePairing(string $playerBcpId, int $totalScore = 0): Pairing
    {
        return new Pairing(
            $playerBcpId,
            "Player {$playerBcpId}",
            0,     // round score
            null,  // player2BcpId
            null,  // player2Name
            0,     // player2Score
            null,  // bcpTableNumber
            $totalScore,
            0
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
}
