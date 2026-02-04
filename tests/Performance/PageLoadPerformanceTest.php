<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Performance;

use PHPUnit\Framework\TestCase;
use TournamentTables\Database\Connection;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;

/**
 * Performance tests for page load times.
 *
 * Validates SC-004: Page load < 3 seconds.
 * Reference: specs/001-table-allocation/tasks.md#T085
 */
class PageLoadPerformanceTest extends TestCase
{
    private const MAX_PAGE_LOAD_SECONDS = 3;
    private const MAX_API_RESPONSE_SECONDS = 2;

    /**
     * @var Tournament|null
     */
    private $tournament;

    protected function setUp(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        $this->cleanupTestData();
        $this->tournament = $this->createTestTournament();
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * Test tournament details API response time.
     *
     * @group performance
     */
    public function testTournamentDetailsApiPerformance(): void
    {
        $startTime = microtime(true);

        // Load tournament with all relations
        $tournament = Tournament::find($this->tournament->id);
        $response = $tournament->toArray();
        $response['tables'] = array_map(function ($t) { return $t->toArray(); }, $tournament->getTables());
        $response['rounds'] = array_map(function ($r) { return $r->toArray(); }, $tournament->getRounds());

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(
            self::MAX_API_RESPONSE_SECONDS,
            $duration,
            sprintf('Tournament details API took %.3fs', $duration)
        );

        if (getenv('VERBOSE_PERF')) {
            fwrite(STDERR, sprintf("\nTournament details: %.3fs\n", $duration));
        }
    }

    /**
     * Test round allocations API response time.
     *
     * @group performance
     */
    public function testRoundAllocationsApiPerformance(): void
    {
        $round = $this->createPublishedRoundWithAllocations();

        $startTime = microtime(true);

        // Load allocations with all relations (what admin view does)
        $allocations = $round->getAllocations();
        $conflicts = [];
        $response = [];

        foreach ($allocations as $allocation) {
            $response[] = $allocation->toArray();
            foreach ($allocation->getConflicts() as $conflict) {
                $conflicts[] = $conflict;
            }
        }

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(
            self::MAX_API_RESPONSE_SECONDS,
            $duration,
            sprintf('Round allocations API took %.3fs', $duration)
        );

        if (getenv('VERBOSE_PERF')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nRound allocations: %d allocations in %.3fs\n",
                    count($response),
                    $duration
                )
            );
        }
    }

    /**
     * Test large tournament data retrieval performance.
     *
     * @group performance
     */
    public function testLargeTournamentDataPerformance(): void
    {
        // Create multiple rounds with allocations
        for ($roundNum = 1; $roundNum <= 6; $roundNum++) {
            $round = Round::findOrCreate($this->tournament->id, $roundNum);
            $round->isPublished = true;
            $round->save();
            $this->createAllocationsForRound($round, 20);
        }

        $startTime = microtime(true);

        // Load all tournament data (worst case scenario)
        $tournament = Tournament::find($this->tournament->id);
        $tables = $tournament->getTables();
        $rounds = $tournament->getRounds();
        $players = $tournament->getPlayers();

        // Load all allocations for all rounds
        $allAllocations = [];
        foreach ($rounds as $round) {
            $allAllocations[$round->roundNumber] = $round->getAllocations();
        }

        $duration = microtime(true) - $startTime;

        $this->assertLessThan(
            self::MAX_PAGE_LOAD_SECONDS,
            $duration,
            sprintf('Large tournament data load took %.3fs', $duration)
        );

        $totalAllocations = array_sum(array_map('count', $allAllocations));
        if (getenv('VERBOSE_PERF')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nLarge tournament: %d tables, %d rounds, %d players, %d allocations in %.3fs\n",
                    count($tables),
                    count($rounds),
                    count($players),
                    $totalAllocations,
                    $duration
                )
            );
        }
    }

    // Helper methods

    private function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    private function cleanupTestData(): void
    {
        try {
            Connection::execute("DELETE FROM tournaments WHERE name LIKE 'Page Load Test%'");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function createTestTournament(): Tournament
    {
        Connection::beginTransaction();

        try {
            Connection::execute(
                "INSERT INTO tournaments (name, bcp_event_id, bcp_url, table_count, admin_token)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    'Page Load Test ' . time(),
                    'pageload' . str_replace('.', '', microtime(true)) . bin2hex(random_bytes(4)),
                    'https://www.bestcoastpairings.com/event/pageload123',
                    20,
                    bin2hex(random_bytes(8)),
                ]
            );

            $tournamentId = Connection::lastInsertId();

            // Create tables (without terrain types to avoid foreign key issues)
            for ($i = 1; $i <= 20; $i++) {
                Connection::execute(
                    "INSERT INTO tables (tournament_id, table_number, terrain_type_id)
                     VALUES (?, ?, ?)",
                    [$tournamentId, $i, null]
                );
            }

            // Create players
            for ($i = 1; $i <= 40; $i++) {
                Connection::execute(
                    "INSERT INTO players (tournament_id, bcp_player_id, name)
                     VALUES (?, ?, ?)",
                    [$tournamentId, "bcp_page_{$i}", "Page Test Player {$i}"]
                );
            }

            Connection::commit();

            return Tournament::find($tournamentId);
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    private function createPublishedRoundWithAllocations(): Round
    {
        $round = Round::findOrCreate($this->tournament->id, 1);
        $round->isPublished = true;
        $round->save();

        $this->createAllocationsForRound($round, 20);

        return $round;
    }

    private function createAllocationsForRound(Round $round, int $pairingCount): void
    {
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        for ($i = 0; $i < min($pairingCount, count($tables), count($players) / 2); $i++) {
            $p1Index = $i * 2;
            $p2Index = $i * 2 + 1;

            if (!isset($players[$p1Index]) || !isset($players[$p2Index]) || !isset($tables[$i])) {
                break;
            }

            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$i]->id,
                $players[$p1Index]->id,
                $players[$p2Index]->id,
                rand(0, 4),
                rand(0, 4),
                [
                    'timestamp' => date('c'),
                    'totalCost' => rand(0, 100),
                    'costBreakdown' => [
                        'tableReuse' => 0,
                        'terrainReuse' => 0,
                        'tableNumber' => $i + 1,
                    ],
                    'reasons' => [],
                    'alternativesConsidered' => [],
                    'isRound1' => $round->roundNumber === 1,
                    'conflicts' => [],
                ]
            );
            $allocation->save();
        }
    }
}
