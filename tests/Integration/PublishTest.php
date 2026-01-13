<?php

declare(strict_types=1);

namespace KTTables\Tests\Integration;

use PHPUnit\Framework\TestCase;
use KTTables\Database\Connection;
use KTTables\Models\Tournament;
use KTTables\Models\Round;
use KTTables\Models\Table;
use KTTables\Models\Player;
use KTTables\Models\Allocation;

/**
 * Integration tests for publish functionality.
 *
 * Tests publish state change and public visibility.
 * Reference: specs/001-table-allocation/tasks.md#T067
 */
class PublishTest extends TestCase
{
    /**
     * @var Tournament
     */
    private $tournament;

    protected function setUp(): void
    {
        // Skip if no database connection
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        // Clean up any existing test data
        $this->cleanupTestData();

        // Create test tournament
        $this->tournament = $this->createTestTournament();
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * Test publishing a round changes is_published flag.
     */
    public function testPublishRoundChangesFlag(): void
    {
        // Create unpublished round
        $round = $this->createRoundWithAllocations();
        $this->assertFalse($round->is_published, 'Round should start unpublished');

        // Publish the round
        Round::publish($round->id);

        // Verify flag changed
        $updatedRound = Round::getById($round->id);
        $this->assertTrue($updatedRound['is_published'], 'Round should be published');
    }

    /**
     * Test unpublished rounds are not visible publicly.
     */
    public function testUnpublishedRoundsNotVisible(): void
    {
        // Create unpublished round
        $round = $this->createRoundWithAllocations();

        // Try to get public allocations
        $publicAllocations = Allocation::getPublicByRound($this->tournament->id, $round->round_number);

        // Should return empty or throw exception
        $this->assertEmpty($publicAllocations, 'Unpublished round should not be visible publicly');
    }

    /**
     * Test published rounds are visible publicly.
     */
    public function testPublishedRoundsVisible(): void
    {
        // Create and publish round
        $round = $this->createRoundWithAllocations();
        Round::publish($round->id);

        // Get public allocations
        $publicAllocations = Allocation::getPublicByRound($this->tournament->id, $round->round_number);

        // Should return allocations
        $this->assertNotEmpty($publicAllocations, 'Published round should be visible publicly');
        $this->assertCount(4, $publicAllocations);

        // Verify public data structure (no internal IDs, just display info)
        $allocation = $publicAllocations[0];
        $this->assertArrayHasKey('tableNumber', $allocation);
        $this->assertArrayHasKey('player1Name', $allocation);
        $this->assertArrayHasKey('player2Name', $allocation);
        $this->assertArrayHasKey('player1Score', $allocation);
        $this->assertArrayHasKey('player2Score', $allocation);
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

        Round::publish($round1->id);
        Round::publish($round3->id);

        // Get published rounds
        $publishedRounds = Round::getPublishedByTournament($this->tournament->id);

        $this->assertCount(2, $publishedRounds);
        $this->assertEquals(1, $publishedRounds[0]['round_number']);
        $this->assertEquals(3, $publishedRounds[1]['round_number']);
    }

    /**
     * Test re-publishing an already published round is idempotent.
     */
    public function testRepublishingIsIdempotent(): void
    {
        $round = $this->createRoundWithAllocations();

        // Publish twice
        Round::publish($round->id);
        Round::publish($round->id);

        // Should still be published with no errors
        $updatedRound = Round::getById($round->id);
        $this->assertTrue($updatedRound['is_published']);
    }

    /**
     * Test editing a published round keeps it published.
     */
    public function testEditingPublishedRoundKeepsPublished(): void
    {
        $round = $this->createRoundWithAllocations();
        Round::publish($round->id);

        // Edit an allocation
        $allocations = Allocation::getByRound($round->id);
        $allocation = $allocations[0];

        // Change allocation (simulate edit)
        $db = Connection::getInstance()->getPdo();
        $stmt = $db->prepare("UPDATE allocations SET player1_score = player1_score + 1 WHERE id = ?");
        $stmt->execute([$allocation['id']]);

        // Verify round is still published
        $updatedRound = Round::getById($round->id);
        $this->assertTrue($updatedRound['is_published']);
    }

    /**
     * Test attempting to publish a round without allocations.
     */
    public function testPublishRoundWithoutAllocations(): void
    {
        // Create round without allocations
        $round = new Round();
        $round->tournament_id = $this->tournament->id;
        $round->round_number = 1;
        $round->is_published = false;
        $round->save();

        // This should succeed but be a warning case
        // The system allows publishing empty rounds (organizer may be working on it)
        Round::publish($round->id);

        $updatedRound = Round::getById($round->id);
        $this->assertTrue($updatedRound['is_published']);

        // But public view should return empty
        $publicAllocations = Allocation::getPublicByRound($this->tournament->id, $round->round_number);
        $this->assertEmpty($publicAllocations);
    }

    // Helper methods

    private function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance()->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupTestData(): void
    {
        $db = Connection::getInstance()->getPdo();
        $db->exec("DELETE FROM allocations WHERE 1=1");
        $db->exec("DELETE FROM players WHERE 1=1");
        $db->exec("DELETE FROM rounds WHERE 1=1");
        $db->exec("DELETE FROM tables WHERE 1=1");
        $db->exec("DELETE FROM tournaments WHERE bcp_event_id LIKE 'TEST%'");
    }

    private function createTestTournament(): Tournament
    {
        $tournament = new Tournament();
        $tournament->name = 'Test Tournament';
        $tournament->bcp_event_id = 'TEST_' . uniqid();
        $tournament->bcp_url = 'https://www.bestcoastpairings.com/event/TEST';
        $tournament->table_count = 8;
        $tournament->admin_token = bin2hex(random_bytes(8));
        $tournament->save();

        // Create tables
        for ($i = 1; $i <= 8; $i++) {
            $table = new Table();
            $table->tournament_id = $tournament->id;
            $table->table_number = $i;
            $table->terrain_type_id = null;
            $table->save();
        }

        // Create players
        for ($i = 1; $i <= 8; $i++) {
            $player = new Player();
            $player->tournament_id = $tournament->id;
            $player->bcp_player_id = 'bcp_p' . $i;
            $player->name = 'Player ' . $i;
            $player->save();
        }

        return $tournament;
    }

    private function createRoundWithAllocations(int $roundNumber = 1): Round
    {
        $round = new Round();
        $round->tournament_id = $this->tournament->id;
        $round->round_number = $roundNumber;
        $round->is_published = false;
        $round->save();

        // Create 4 allocations
        $tables = Table::getByTournament($this->tournament->id);
        $players = Player::getByTournament($this->tournament->id);

        for ($i = 0; $i < 4; $i++) {
            $allocation = new Allocation();
            $allocation->round_id = $round->id;
            $allocation->table_id = $tables[$i]['id'];
            $allocation->player1_id = $players[$i * 2]['id'];
            $allocation->player2_id = $players[$i * 2 + 1]['id'];
            $allocation->player1_score = $roundNumber - 1;
            $allocation->player2_score = $roundNumber - 1;
            $allocation->save();
        }

        return $round;
    }
}
