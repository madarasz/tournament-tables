<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use TournamentTables\Tests\DatabaseTestCase;
use TournamentTables\Database\Connection;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Controllers\PublicController;

/**
 * Integration tests for public view functionality.
 *
 * Tests unauthenticated access and round visibility.
 * Reference: specs/001-table-allocation/tasks.md#T077
 *
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class PublicViewTest extends DatabaseTestCase
{
    /**
     * @var Tournament
     */
    private $tournament;

    /**
     * @var PublicController
     */
    private $controller;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tournament
        $this->tournament = $this->createTestTournament();

        // Create controller
        $this->controller = new PublicController();
    }

    /**
     * Test unauthenticated access to public tournament info.
     */
    public function testUnauthenticatedAccessToTournamentInfo(): void
    {
        // Create and publish a round
        $round = $this->createRoundWithAllocations(1);
        $round->publish();

        // Access tournament info without authentication
        ob_start();
        $this->controller->showTournament(['id' => $this->tournament->id], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should succeed without authentication
        $this->assertTrue(is_array($response));
        $this->assertEquals($this->tournament->id, $response['id']);
        $this->assertEquals($this->tournament->name, $response['name']);
        $this->assertEquals($this->tournament->tableCount, $response['tableCount']);
        $this->assertArrayHasKey('publishedRounds', $response);
        $this->assertContains(1, $response['publishedRounds']);
    }

    /**
     * Test unauthenticated access to published round allocations.
     */
    public function testUnauthenticatedAccessToPublishedRound(): void
    {
        // Create and publish a round
        $round = $this->createRoundWithAllocations(1);
        $round->publish();

        // Access round without authentication
        ob_start();
        $this->controller->showRound(['id' => $this->tournament->id, 'n' => 1], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should succeed without authentication
        $this->assertTrue(is_array($response));
        $this->assertEquals($this->tournament->name, $response['tournamentName']);
        $this->assertEquals(1, $response['roundNumber']);
        $this->assertArrayHasKey('allocations', $response);
        $this->assertCount(4, $response['allocations']);
    }

    /**
     * Test unauthenticated access to unpublished round fails.
     */
    public function testUnauthenticatedAccessToUnpublishedRoundFails(): void
    {
        // Create unpublished round
        $round = $this->createRoundWithAllocations(1);
        // Don't publish

        // Try to access round without authentication
        ob_start();
        $this->controller->showRound(['id' => $this->tournament->id, 'n' => 1], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Should return error
        $this->assertTrue(is_array($response));
        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('not_found', $response['error']);
    }

    /**
     * Test only published rounds appear in tournament info.
     */
    public function testOnlyPublishedRoundsVisibleInTournamentInfo(): void
    {
        // Create 3 rounds, only publish rounds 1 and 3
        $round1 = $this->createRoundWithAllocations(1);
        $round2 = $this->createRoundWithAllocations(2);
        $round3 = $this->createRoundWithAllocations(3);

        $round1->publish();
        // Round 2 stays unpublished
        $round3->publish();

        // Get tournament info
        ob_start();
        $this->controller->showTournament(['id' => $this->tournament->id], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        // Only rounds 1 and 3 should be visible
        $this->assertCount(2, $response['publishedRounds']);
        $this->assertContains(1, $response['publishedRounds']);
        $this->assertContains(3, $response['publishedRounds']);
        $this->assertNotContains(2, $response['publishedRounds']);
    }

    /**
     * Test public allocation data structure matches contract.
     */
    public function testPublicAllocationDataStructure(): void
    {
        // Create and publish round
        $round = $this->createRoundWithAllocations(1);
        $round->publish();

        // Get round allocations
        ob_start();
        $this->controller->showRound(['id' => $this->tournament->id, 'n' => 1], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $allocation = $response['allocations'][0];

        // Verify public data structure per contracts/api.yaml#PublicAllocation
        $this->assertArrayHasKey('tableNumber', $allocation);
        $this->assertArrayHasKey('terrainType', $allocation);
        $this->assertArrayHasKey('player1Name', $allocation);
        $this->assertArrayHasKey('player1Score', $allocation);
        $this->assertArrayHasKey('player2Name', $allocation);
        $this->assertArrayHasKey('player2Score', $allocation);

        // Verify internal IDs are NOT exposed
        $this->assertArrayNotHasKey('id', $allocation);
        $this->assertArrayNotHasKey('player1Id', $allocation);
        $this->assertArrayNotHasKey('player2Id', $allocation);
        $this->assertArrayNotHasKey('conflicts', $allocation);
    }

    /**
     * Test public view returns allocations ordered by table number.
     */
    public function testAllocationsOrderedByTableNumber(): void
    {
        // Create and publish round
        $round = $this->createRoundWithAllocations(1);
        $round->publish();

        // Get round allocations
        ob_start();
        $this->controller->showRound(['id' => $this->tournament->id, 'n' => 1], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);
        $allocations = $response['allocations'];

        // Verify ordered by table number
        $tableNumbers = array_column($allocations, 'tableNumber');
        $sortedNumbers = $tableNumbers;
        sort($sortedNumbers);

        $this->assertEquals($sortedNumbers, $tableNumbers);
    }

    /**
     * Test accessing non-existent tournament returns 404.
     */
    public function testNonExistentTournamentReturns404(): void
    {
        // Access non-existent tournament
        ob_start();
        $this->controller->showTournament(['id' => 99999], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('not_found', $response['error']);
    }

    /**
     * Test accessing non-existent round returns 404.
     */
    public function testNonExistentRoundReturns404(): void
    {
        // Access non-existent round (use 15 to stay within valid range 1-20)
        ob_start();
        $this->controller->showRound(['id' => $this->tournament->id, 'n' => 15], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertArrayHasKey('error', $response);
        $this->assertEquals('not_found', $response['error']);
    }

    /**
     * Test empty tournament (no published rounds).
     */
    public function testEmptyTournamentNoPublishedRounds(): void
    {
        // Tournament exists but no published rounds
        ob_start();
        $this->controller->showTournament(['id' => $this->tournament->id], null);
        $output = ob_get_clean();

        $response = json_decode($output, true);

        $this->assertTrue(is_array($response));
        $this->assertEquals($this->tournament->id, $response['id']);
        $this->assertEmpty($response['publishedRounds']);
    }

    // Helper methods

    private function createTestTournament(): Tournament
    {
        $tournament = new Tournament(
            null,
            'Test Tournament for Public View',
            'TEST_PV_' . uniqid(),
            'https://www.bestcoastpairings.com/event/TEST',
            8,
            bin2hex(random_bytes(8))
        );
        $tournament->save();

        // Create tables
        for ($i = 1; $i <= 8; $i++) {
            $table = new Table(
                null,
                $tournament->id,
                $i,
                null
            );
            $table->save();
        }

        // Create players
        for ($i = 1; $i <= 8; $i++) {
            $player = new Player(
                null,
                $tournament->id,
                'bcp_pv_p' . $i,
                'Player ' . $i
            );
            $player->save();
        }

        return $tournament;
    }

    private function createRoundWithAllocations(int $roundNumber = 1): Round
    {
        $round = new Round(
            null,
            $this->tournament->id,
            $roundNumber,
            false
        );
        $round->save();

        // Get tables and players
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Create 4 allocations (8 players = 4 pairings)
        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$i]->id,
                $players[$i * 2]->id,
                $players[$i * 2 + 1]->id,
                $roundNumber - 1, // player1Score
                $roundNumber - 1, // player2Score
                [
                    'timestamp' => date('c'),
                    'totalCost' => $i + 1,
                    'costBreakdown' => [
                        'tableReuse' => 0,
                        'terrainReuse' => 0,
                        'tableNumber' => $i + 1,
                    ],
                    'reasons' => [],
                    'alternativesConsidered' => [],
                    'isRound1' => $roundNumber === 1,
                    'conflicts' => [],
                ]
            );
            $allocation->save();
        }

        return $round;
    }
}
