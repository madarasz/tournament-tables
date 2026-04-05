<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use TournamentTables\Controllers\ViewController;
use TournamentTables\Models\Allocation;
use TournamentTables\Models\Player;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Tournament;
use TournamentTables\Tests\DatabaseTestCase;

/**
 * Integration tests for public tournament default view selection.
 */
#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class PublicTournamentDefaultViewTest extends DatabaseTestCase
{
    /**
     * @var array<string, mixed>
     */
    private $originalGet;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalGet = $_GET;
        $_GET = [];
    }

    protected function tearDown(): void
    {
        $_GET = $this->originalGet;
        parent::tearDown();
    }

    public function testFinishedTournamentDefaultsToLeaderboardView(): void
    {
        $tournament = $this->createTournamentWithPublishedRound(
            $this->formatUtcDate('-3 days'),
            $this->formatUtcDate('-2 days')
        );

        $html = $this->renderPublicTournamentPage($tournament->id);

        $this->assertStringContainsString('class="tc-page leaderboard-active"', $html);
        $this->assertStringContainsString('data-testid="leaderboard-section"', $html);
    }

    public function testFinishedTournamentRespectsExplicitRoundQuery(): void
    {
        $tournament = $this->createTournamentWithPublishedRound(
            $this->formatUtcDate('-3 days'),
            $this->formatUtcDate('-2 days')
        );

        $html = $this->renderPublicTournamentPage($tournament->id, ['round' => '1']);

        $this->assertStringNotContainsString('class="tc-page leaderboard-active"', $html);
        $this->assertStringContainsString('id="hero-round-title">Round 1</h1>', $html);
    }

    public function testActiveTournamentDefaultsToLatestPublishedRound(): void
    {
        $tournament = $this->createTournamentWithPublishedRound(
            $this->formatUtcDate('-1 day'),
            $this->formatUtcDate('+1 day')
        );

        $html = $this->renderPublicTournamentPage($tournament->id);

        $this->assertStringNotContainsString('class="tc-page leaderboard-active"', $html);
        $this->assertStringContainsString('id="hero-round-title">Round 1</h1>', $html);
    }

    /**
     * @param array<string, string> $query
     */
    private function renderPublicTournamentPage(int $tournamentId, array $query = []): string
    {
        $_GET = $query;

        $controller = new ViewController();
        ob_start();
        $controller->publicTournament(['id' => $tournamentId], null);
        return (string) ob_get_clean();
    }

    private function createTournamentWithPublishedRound(?string $eventDate, ?string $eventEndDate): Tournament
    {
        $tournament = new Tournament(
            null,
            'Public Default View ' . uniqid('', true),
            'TEST_PDV_' . uniqid(),
            'https://www.bestcoastpairings.com/event/TEST_PDV',
            2,
            bin2hex(random_bytes(8)),
            null,
            null,
            $eventDate,
            $eventEndDate,
            'Test Venue'
        );
        $tournament->save();

        $table1 = new Table(null, $tournament->id, 1, null);
        $table1->save();
        $table2 = new Table(null, $tournament->id, 2, null);
        $table2->save();

        $player1 = new Player(null, $tournament->id, 'pdv_p1_' . uniqid(), 'PlayerOne', 20, null, 1);
        $player1->save();
        $player2 = new Player(null, $tournament->id, 'pdv_p2_' . uniqid(), 'PlayerTwo', 18, null, 2);
        $player2->save();

        $round = new Round(null, $tournament->id, 1, true);
        $round->save();

        $allocation = new Allocation(
            null,
            $round->id,
            $table1->id,
            $player1->id,
            $player2->id,
            20,
            18,
            ['conflicts' => []],
            1
        );
        $allocation->save();

        return $tournament;
    }

    private function formatUtcDate(string $relative): string
    {
        return (new \DateTimeImmutable('now', new \DateTimeZone('UTC')))
            ->modify($relative)
            ->format('Y-m-d\TH:i:s.000\Z');
    }
}
