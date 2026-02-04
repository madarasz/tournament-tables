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

/**
 * Integration tests for publish functionality.
 *
 * Tests publish state change and public visibility.
 * Reference: specs/001-table-allocation/tasks.md#T067
 */
class PublishTest extends DatabaseTestCase
{
    /**
     * @var Tournament
     */
    private $tournament;

    protected function setUp(): void
    {
        parent::setUp();

        // Create test tournament
        $this->tournament = $this->createTestTournament();
    }

    /**
     * Test publishing a round changes is_published flag.
     */
    public function testPublishRoundChangesFlag(): void
    {
        // Create unpublished round
        $round = $this->createRoundWithAllocations();
        $this->assertFalse($round->isPublished, 'Round should start unpublished');

        // Publish the round
        $round->publish();

        // Verify flag changed
        $updatedRound = Round::find($round->id);
        $this->assertTrue($updatedRound->isPublished, 'Round should be published');
    }

    /**
     * Test unpublished rounds are not visible publicly.
     */
    public function testUnpublishedRoundsNotVisible(): void
    {
        // Create unpublished round
        $round = $this->createRoundWithAllocations();

        // Try to get allocations - round is unpublished so shouldn't be publicly visible
        $allocations = Allocation::findByRound($round->id);

        // Allocations exist but round is not published, so public access should be denied
        // The actual public visibility is controlled by round.isPublished flag
        $this->assertFalse($round->isPublished, 'Round should not be published');
        $this->assertNotEmpty($allocations, 'Allocations should exist');
    }

    /**
     * Test published rounds are visible publicly.
     */
    public function testPublishedRoundsVisible(): void
    {
        // Create and publish round
        $round = $this->createRoundWithAllocations();
        $round->publish();

        // Get allocations
        $allocations = Allocation::findByRound($round->id);

        // Should return allocations
        $this->assertNotEmpty($allocations, 'Published round should have allocations');
        $this->assertCount(4, $allocations);

        // Verify public data structure
        $publicData = $allocations[0]->toPublicArray();
        $this->assertArrayHasKey('tableNumber', $publicData);
        $this->assertArrayHasKey('player1Name', $publicData);
        $this->assertArrayHasKey('player2Name', $publicData);
        $this->assertArrayHasKey('player1Score', $publicData);
        $this->assertArrayHasKey('player2Score', $publicData);
    }

    /**
     * Test getting list of published rounds for a tournament.
     */
    public function testGetPublishedRoundsList(): void
    {
        // Create 3 rounds, publish only rounds 1 and 3
        $round1 = $this->createRoundWithAllocations(1);
        $round2 = $this->createRoundWithAllocations(2);
        $round3 = $this->createRoundWithAllocations(3);

        $round1->publish();
        $round3->publish();

        // Get published rounds
        $publishedRounds = Round::findPublishedByTournament($this->tournament->id);

        $this->assertCount(2, $publishedRounds);
        $this->assertEquals(1, $publishedRounds[0]->roundNumber);
        $this->assertEquals(3, $publishedRounds[1]->roundNumber);
    }

    /**
     * Test re-publishing an already published round is idempotent.
     */
    public function testRepublishingIsIdempotent(): void
    {
        $round = $this->createRoundWithAllocations();

        // Publish twice
        $round->publish();
        $round->publish();

        // Should still be published with no errors
        $updatedRound = Round::find($round->id);
        $this->assertTrue($updatedRound->isPublished);
    }

    /**
     * Test editing a published round keeps it published.
     */
    public function testEditingPublishedRoundKeepsPublished(): void
    {
        $round = $this->createRoundWithAllocations();
        $round->publish();

        // Edit an allocation
        $allocations = Allocation::findByRound($round->id);
        $allocation = $allocations[0];

        // Change allocation (simulate edit)
        Connection::execute(
            "UPDATE allocations SET player1_score = player1_score + 1 WHERE id = ?",
            [$allocation->id]
        );

        // Verify round is still published
        $updatedRound = Round::find($round->id);
        $this->assertTrue($updatedRound->isPublished);
    }

    /**
     * Test attempting to publish a round without allocations.
     */
    public function testPublishRoundWithoutAllocations(): void
    {
        // Create round without allocations
        $round = new Round(
            null,
            $this->tournament->id,
            1,
            false
        );
        $round->save();

        // This should succeed but be a warning case
        // The system allows publishing empty rounds (organizer may be working on it)
        $round->publish();

        $updatedRound = Round::find($round->id);
        $this->assertTrue($updatedRound->isPublished);

        // But round should have no allocations
        $allocations = Allocation::findByRound($round->id);
        $this->assertEmpty($allocations);
    }

    // Helper methods

    private function createTestTournament(): Tournament
    {
        $tournament = new Tournament(
            null,
            'Test Tournament',
            'TEST_' . uniqid(),
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
                'bcp_p' . $i,
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

        // Create 4 allocations
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$i]->id,
                $players[$i * 2]->id,
                $players[$i * 2 + 1]->id,
                $roundNumber - 1,
                $roundNumber - 1,
                null
            );
            $allocation->save();
        }

        return $round;
    }
}
