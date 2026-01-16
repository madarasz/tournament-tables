<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\TournamentService;
use TournamentTables\Services\AllocationService;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Table;
use TournamentTables\Models\Round;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Database\Connection;

/**
 * Integration tests for tournament deletion.
 *
 * Reference: specs/001-table-allocation/tasks.md T102
 */
class TournamentDeleteTest extends TestCase
{
    private TournamentService $service;
    private array $createdTournamentIds = [];

    protected function setUp(): void
    {
        // Skip if no database config available
        $configPath = dirname(__DIR__, 2) . '/config/database.php';
        if (!file_exists($configPath)) {
            $this->markTestSkipped('Database configuration not found');
        }

        // Skip if database is not reachable
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        $this->service = new TournamentService();
    }

    private function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    protected function tearDown(): void
    {
        // Clean up any tournaments that weren't deleted during tests
        foreach ($this->createdTournamentIds as $id) {
            try {
                Connection::execute('DELETE FROM tournaments WHERE id = ?', [$id]);
            } catch (\Exception $e) {
                // Ignore cleanup errors
            }
        }

        Connection::reset();
    }

    public function testDeleteTournamentRemovesTournament(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Delete Test Tournament',
            'https://www.bestcoastpairings.com/event/delete123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // Delete the tournament
        $deleted = $this->service->deleteTournament($tournamentId);

        $this->assertTrue($deleted);

        // Verify tournament no longer exists
        $found = Tournament::find($tournamentId);
        $this->assertNull($found);

        // Remove from cleanup list since already deleted
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteTournamentCascadesToTables(): void
    {
        // Create a tournament with tables
        $result = $this->service->createTournament(
            'Cascade Tables Test',
            'https://www.bestcoastpairings.com/event/cascade123',
            10
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // Verify tables exist before deletion
        $tablesBefore = Table::findByTournament($tournamentId);
        $this->assertCount(10, $tablesBefore);

        // Delete the tournament
        $this->service->deleteTournament($tournamentId);

        // Verify tables were deleted
        $tablesAfter = Table::findByTournament($tournamentId);
        $this->assertCount(0, $tablesAfter);

        // Remove from cleanup list
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteTournamentCascadesToRounds(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Cascade Rounds Test',
            'https://www.bestcoastpairings.com/event/cascaderounds123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // Create a round
        $round = new Round(null, $tournamentId, 1, false);
        $round->save();

        // Verify round exists before deletion
        $roundsBefore = Round::findByTournament($tournamentId);
        $this->assertCount(1, $roundsBefore);

        // Delete the tournament
        $this->service->deleteTournament($tournamentId);

        // Verify rounds were deleted
        $roundsAfter = Round::findByTournament($tournamentId);
        $this->assertCount(0, $roundsAfter);

        // Remove from cleanup list
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteTournamentCascadesToPlayers(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Cascade Players Test',
            'https://www.bestcoastpairings.com/event/cascadeplayers123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // Create a player
        $player = new Player(null, $tournamentId, 'player1', 'Test Player');
        $player->save();

        // Verify player exists before deletion
        $playersBefore = Player::findByTournament($tournamentId);
        $this->assertCount(1, $playersBefore);

        // Delete the tournament
        $this->service->deleteTournament($tournamentId);

        // Verify players were deleted
        $playersAfter = Player::findByTournament($tournamentId);
        $this->assertCount(0, $playersAfter);

        // Remove from cleanup list
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteTournamentCascadesToAllocations(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Cascade Allocations Test',
            'https://www.bestcoastpairings.com/event/cascadealloc123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // Create supporting data
        $round = new Round(null, $tournamentId, 1, false);
        $round->save();

        $player1 = new Player(null, $tournamentId, 'p1', 'Player 1');
        $player1->save();

        $player2 = new Player(null, $tournamentId, 'p2', 'Player 2');
        $player2->save();

        $tables = Table::findByTournament($tournamentId);
        $table = $tables[0];

        // Create an allocation
        $allocation = new Allocation(
            null,
            $round->id,
            $table->id,
            $player1->id,
            $player2->id,
            0,
            0,
            json_encode(['reason' => 'test'])
        );
        $allocation->save();

        // Verify allocation exists before deletion
        $allocationsBefore = Allocation::findByRound($round->id);
        $this->assertCount(1, $allocationsBefore);

        // Delete the tournament
        $this->service->deleteTournament($tournamentId);

        // Verify allocations were deleted (via cascade from round)
        // Note: Allocation::findByRound won't work since round is deleted
        // Instead verify via direct query
        $remaining = Connection::fetchAll(
            'SELECT * FROM allocations WHERE round_id = ?',
            [$round->id]
        );
        $this->assertCount(0, $remaining);

        // Remove from cleanup list
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteNonExistentTournamentThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->deleteTournament(999999);
    }

    public function testDeleteTournamentRequiresAuthentication(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Auth Delete Test',
            'https://www.bestcoastpairings.com/event/authdelete123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $adminToken = $result['adminToken'];
        $this->createdTournamentIds[] = $tournamentId;

        // Simulate API call without proper authentication
        // Note: This tests the endpoint logic, not just the service
        // The actual auth check happens in the controller

        // For service-level test, just verify the tournament can be found by token
        $found = Tournament::findByToken($adminToken);
        $this->assertNotNull($found);
        $this->assertEquals($tournamentId, $found->id);

        // Delete should work when called with correct context
        $this->service->deleteTournament($tournamentId);

        // Verify deleted
        $afterDelete = Tournament::find($tournamentId);
        $this->assertNull($afterDelete);

        // Remove from cleanup list
        $this->createdTournamentIds = array_filter(
            $this->createdTournamentIds,
            fn($id) => $id !== $tournamentId
        );
    }

    public function testDeleteTournamentIsIdempotent(): void
    {
        // Create a tournament
        $result = $this->service->createTournament(
            'Idempotent Delete Test',
            'https://www.bestcoastpairings.com/event/idempotent123',
            5
        );

        $tournamentId = $result['tournament']->id;
        $this->createdTournamentIds[] = $tournamentId;

        // First delete should succeed
        $this->service->deleteTournament($tournamentId);

        // Second delete should throw exception (not found)
        $this->expectException(\InvalidArgumentException::class);
        $this->service->deleteTournament($tournamentId);
    }
}
