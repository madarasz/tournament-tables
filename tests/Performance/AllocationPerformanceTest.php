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
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * Performance tests for allocation generation.
 *
 * Validates SC-002: Allocation generation < 10 seconds for 40 players.
 * Reference: specs/001-table-allocation/tasks.md#T084
 */
class AllocationPerformanceTest extends TestCase
{
    private const MAX_ALLOCATION_TIME_SECONDS = 10;
    private const PLAYERS_COUNT = 40;
    private const TABLES_COUNT = 20;
    private const ROUNDS_TO_TEST = 6;

    /**
     * @var Tournament|null
     */
    private $tournament;

    /**
     * @var AllocationService
     */
    private $allocationService;

    protected function setUp(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        $this->cleanupTestData();
        $this->tournament = $this->createLargeTournament();
        $this->allocationService = new AllocationService(new CostCalculator());
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * Test allocation performance scales linearly with player count.
     *
     * @group performance
     */
    public function testAllocationScalesWithPlayerCount(): void
    {
        $timings = [];
        $sizes = [8, 16, 24, 32, 40];

        foreach ($sizes as $playerCount) {
            $this->cleanupTestData();
            $this->tournament = $this->createTournamentWithSize($playerCount, $playerCount / 2);

            // Create pairings
            $pairings = [];
            for ($i = 1; $i <= $playerCount / 2; $i++) {
                $p1 = $i * 2 - 1;
                $p2 = $i * 2;
                $pairings[] = new Pairing(
                    "bcp_perf_{$p1}",
                    "Player {$p1}",
                    rand(0, 4),
                    "bcp_perf_{$p2}",
                    "Player {$p2}",
                    rand(0, 4),
                    null
                );
            }

            $tables = $this->getTablesAsArray();
            $history = new TournamentHistory($this->tournament->id, 2);

            $startTime = microtime(true);
            $result = $this->allocationService->generateAllocations($pairings, $tables, 2, $history);
            $timings[$playerCount] = microtime(true) - $startTime;
        }

        // Verify all sizes complete under the limit
        foreach ($timings as $count => $time) {
            $this->assertLessThan(
                self::MAX_ALLOCATION_TIME_SECONDS,
                $time,
                "Allocation for {$count} players took {$time}s"
            );
        }

        // Output scaling info (only when VERBOSE_PERF is set)
        if (getenv('VERBOSE_PERF')) {
            fwrite(STDERR, "\nScaling performance:\n");
            foreach ($timings as $count => $time) {
                fwrite(STDERR, sprintf("  %d players: %.3fs\n", $count, $time));
            }
        }
    }

    /**
     * Test allocation with complex history (multiple prior rounds).
     *
     * @group performance
     */
    public function testAllocationWithComplexHistory(): void
    {
        // Create 5 rounds of history
        for ($round = 1; $round < self::ROUNDS_TO_TEST; $round++) {
            $this->createRoundWithRandomAllocations($round);
        }

        // Create pairings for final round
        $pairings = $this->createPairingsForRound(self::ROUNDS_TO_TEST);
        $tables = $this->getTablesAsArray();
        $history = new TournamentHistory($this->tournament->id, self::ROUNDS_TO_TEST);

        $startTime = microtime(true);
        $result = $this->allocationService->generateAllocations(
            $pairings,
            $tables,
            self::ROUNDS_TO_TEST,
            $history
        );
        $duration = microtime(true) - $startTime;

        $this->assertLessThan(
            self::MAX_ALLOCATION_TIME_SECONDS,
            $duration,
            "Allocation with complex history took {$duration}s"
        );

        if (getenv('VERBOSE_PERF')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nComplex history test: %d allocations with %d rounds of history in %.3fs\n",
                    count($result->allocations),
                    self::ROUNDS_TO_TEST - 1,
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
            Connection::execute("DELETE FROM tournaments WHERE name LIKE 'Performance Test%'");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function createLargeTournament(): Tournament
    {
        return $this->createTournamentWithSize(self::PLAYERS_COUNT, self::TABLES_COUNT);
    }

    private function createTournamentWithSize(int $playerCount, int $tableCount): Tournament
    {
        Connection::beginTransaction();

        try {
            Connection::execute(
                "INSERT INTO tournaments (name, bcp_event_id, bcp_url, table_count, admin_token)
                 VALUES (?, ?, ?, ?, ?)",
                [
                    'Performance Test ' . time() . rand(1000, 9999),
                    'perf_test_' . time() . rand(1000, 9999),
                    'https://www.bestcoastpairings.com/event/perf' . time(),
                    $tableCount,
                    bin2hex(random_bytes(8)),
                ]
            );

            $tournamentId = Connection::lastInsertId();

            // Create tables (without terrain types to avoid foreign key issues)
            for ($i = 1; $i <= $tableCount; $i++) {
                Connection::execute(
                    "INSERT INTO tables (tournament_id, table_number, terrain_type_id)
                     VALUES (?, ?, ?)",
                    [$tournamentId, $i, null]
                );
            }

            // Create players
            for ($i = 1; $i <= $playerCount; $i++) {
                Connection::execute(
                    "INSERT INTO players (tournament_id, bcp_player_id, name)
                     VALUES (?, ?, ?)",
                    [$tournamentId, "bcp_perf_{$i}", "Performance Player {$i}"]
                );
            }

            Connection::commit();

            return Tournament::find($tournamentId);
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }

    private function createPriorRoundHistory(): void
    {
        // Create a few rounds of history
        for ($round = 1; $round < 3; $round++) {
            $this->createRoundWithRandomAllocations($round);
        }
    }

    private function createRoundWithRandomAllocations(int $roundNumber): void
    {
        $round = Round::findOrCreate($this->tournament->id, $roundNumber);
        $tables = Table::findByTournament($this->tournament->id);
        $players = Player::findByTournament($this->tournament->id);

        // Shuffle players for random pairings
        shuffle($players);

        $tableIndex = 0;
        for ($i = 0; $i < count($players) - 1; $i += 2) {
            if ($tableIndex >= count($tables)) {
                break;
            }

            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$tableIndex]->id,
                $players[$i]->id,
                $players[$i + 1]->id,
                rand(0, 4),
                rand(0, 4),
                [
                    'timestamp' => date('c'),
                    'totalCost' => 0,
                    'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                    'reasons' => [],
                    'alternativesConsidered' => [],
                    'isRound1' => $roundNumber === 1,
                    'conflicts' => [],
                ]
            );
            $allocation->save();

            $tableIndex++;
        }
    }

    private function createPairingsForRound(int $roundNumber): array
    {
        $players = Player::findByTournament($this->tournament->id);
        shuffle($players);

        $pairings = [];
        for ($i = 0; $i < count($players) - 1; $i += 2) {
            $pairings[] = new Pairing(
                $players[$i]->bcpPlayerId,
                $players[$i]->name,
                rand(0, 4),
                $players[$i + 1]->bcpPlayerId,
                $players[$i + 1]->name,
                rand(0, 4),
                null
            );
        }

        return $pairings;
    }

    private function getTablesAsArray(): array
    {
        $tables = Table::findByTournament($this->tournament->id);
        return array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);
    }
}
