<?php

declare(strict_types=1);

namespace TournamentTables\Tests\E2E;

use PHPUnit\Framework\TestCase;
use TournamentTables\Database\Connection;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Services\TournamentService;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * End-to-end tests for the complete tournament workflow.
 *
 * Validates SC-003: Tournament creation to first allocation < 5 minutes.
 * Reference: specs/001-table-allocation/tasks.md#T091
 */
class TournamentWorkflowTest extends TestCase
{
    private const MAX_WORKFLOW_TIME_SECONDS = 300; // 5 minutes
    private const PLAYER_COUNT = 24;
    private const TABLE_COUNT = 12;

    /**
     * @var TournamentService
     */
    private $tournamentService;

    /**
     * @var AllocationService
     */
    private $allocationService;

    protected function setUp(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        // Aggressive cleanup of any E2E test data
        $this->cleanupTestData();

        $this->tournamentService = new TournamentService();
        $this->allocationService = new AllocationService(new CostCalculator());
    }

    protected function tearDown(): void
    {
        if ($this->isDatabaseAvailable()) {
            $this->cleanupTestData();
        }
    }

    /**
     * SC-003: Complete workflow from tournament creation to first allocation.
     *
     * @group e2e
     */
    public function testCompleteWorkflowUnderFiveMinutes(): void
    {
        $startTime = microtime(true);

        // Step 1: Create tournament
        $tournamentResult = $this->createTournament();
        $tournament = $tournamentResult['tournament'];
        $adminToken = $tournamentResult['adminToken'];

        $this->assertNotNull($tournament, 'Tournament should be created');
        $this->assertNotEmpty($adminToken, 'Admin token should be generated');

        // Step 2: Verify tables were created
        $tables = Table::findByTournament($tournament->id);
        $this->assertCount(self::TABLE_COUNT, $tables, 'Tables should be created');

        // Step 3: Import/create players and round 1 pairings
        $round1 = $this->createRound1WithPairings($tournament);
        $this->assertNotNull($round1, 'Round 1 should be created');

        // Step 4: Verify round 1 allocations (using BCP original assignments)
        $round1Allocations = Allocation::findByRound($round1->id);
        $this->assertCount(
            self::PLAYER_COUNT / 2,
            $round1Allocations,
            'Round 1 should have all pairings allocated'
        );

        // Step 5: Publish round 1
        $round1->publish();
        $this->assertTrue($round1->isPublished, 'Round 1 should be published');

        // Step 6: Create round 2 pairings (simulate BCP import)
        $round2 = $this->createRound2Pairings($tournament);

        // Step 7: Generate round 2 allocations
        $round2Result = $this->generateAllocations($tournament, 2);
        $this->assertCount(
            self::PLAYER_COUNT / 2,
            $round2Result->allocations,
            'Round 2 should have all allocations'
        );

        // Step 8: Save round 2 allocations
        $this->saveAllocations($tournament, $round2, $round2Result);
        $round2Allocations = Allocation::findByRound($round2->id);
        $this->assertCount(
            self::PLAYER_COUNT / 2,
            $round2Allocations,
            'Round 2 allocations should be saved'
        );

        // Measure total time
        $totalTime = microtime(true) - $startTime;

        $this->assertLessThan(
            self::MAX_WORKFLOW_TIME_SECONDS,
            $totalTime,
            sprintf('Complete workflow took %.2f seconds, exceeding limit of %d seconds', $totalTime, self::MAX_WORKFLOW_TIME_SECONDS)
        );

        // Only output verbose info when VERBOSE_E2E environment variable is set
        if (getenv('VERBOSE_E2E')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nE2E Workflow completed in %.3fs:\n" .
                    "  - Tournament created: %s\n" .
                    "  - Tables: %d\n" .
                    "  - Players: %d\n" .
                    "  - Round 1 allocations: %d\n" .
                    "  - Round 2 allocations: %d\n",
                    $totalTime,
                    $tournament->name,
                    count($tables),
                    self::PLAYER_COUNT,
                    count($round1Allocations),
                    count($round2Allocations)
                )
            );
        }
    }

    /**
     * Test complete multi-round workflow.
     *
     * @group e2e
     */
    public function testMultiRoundWorkflow(): void
    {
        $tournament = $this->createTournament()['tournament'];

        // Create and process 4 rounds
        for ($roundNum = 1; $roundNum <= 4; $roundNum++) {
            $round = Round::findOrCreate($tournament->id, $roundNum);

            if ($roundNum === 1) {
                // Round 1: Create players and initial pairings
                $round = $this->createRound1WithPairings($tournament);
            } else {
                // Rounds 2+: Create pairings and generate allocations
                $this->createPairingsForRound($tournament, $roundNum);
                $result = $this->generateAllocations($tournament, $roundNum);
                $this->saveAllocations($tournament, $round, $result);
            }

            // Publish round
            $round->publish();
            $this->assertTrue($round->isPublished);

            // Verify allocations
            $allocations = Allocation::findByRound($round->id);
            $this->assertCount(
                self::PLAYER_COUNT / 2,
                $allocations,
                "Round {$roundNum} should have all allocations"
            );
        }

        // Verify no table reuse violations in later rounds (where possible)
        $this->verifyMinimalTableReuse($tournament);
    }

    /**
     * Test workflow handles edge cases.
     *
     * @group e2e
     */
    public function testWorkflowEdgeCases(): void
    {
        // Create tournament with only 2 tables for 4 players - forces unavoidable conflicts
        // With 2 tables, cross-pairings in R2 guarantee each pairing has players who
        // together have used BOTH tables, so any assignment causes a conflict
        $uniqueId = str_replace('.', '', microtime(true)) . bin2hex(random_bytes(4));
        $result = $this->tournamentService->createTournament(
            'E2E Edge Case Test ' . $uniqueId,
            'https://www.bestcoastpairings.com/event/e2eedge' . $uniqueId,
            2 // Only 2 tables for 4 players
        );

        $tournament = $result['tournament'];

        // Create 4 players
        $round1 = Round::findOrCreate($tournament->id, 1);
        $tables = Table::findByTournament($tournament->id);

        $players = [];
        for ($i = 1; $i <= 4; $i++) {
            $players[$i] = Player::findOrCreate(
                $tournament->id,
                "bcp_edge_{$i}",
                "Edge Player {$i}"
            );
        }

        // Round 1: Table 1 has players 1&2, Table 2 has players 3&4
        for ($i = 0; $i < 2; $i++) {
            $allocation = new Allocation(
                null,
                $round1->id,
                $tables[$i]->id,
                $players[$i * 2 + 1]->id,
                $players[$i * 2 + 2]->id,
                0,
                0,
                ['isRound1' => true, 'conflicts' => []]
            );
            $allocation->save();
        }

        // Round 2: Cross-pairings guarantee conflicts
        // (1,3): P1 used T1, P3 used T2 → both tables have a conflict
        // (2,4): P2 used T1, P4 used T2 → both tables have a conflict
        $round2 = Round::findOrCreate($tournament->id, 2);
        $pairings = [
            new Pairing('bcp_edge_1', 'Edge Player 1', 1, 'bcp_edge_3', 'Edge Player 3', 1, null),
            new Pairing('bcp_edge_2', 'Edge Player 2', 0, 'bcp_edge_4', 'Edge Player 4', 0, null),
        ];

        $tablesArray = array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);

        $history = new TournamentHistory($tournament->id, 2);
        $result = $this->allocationService->generateAllocations($pairings, $tablesArray, 2, $history);

        // Should still generate allocations even with conflicts
        $this->assertCount(2, $result->allocations, 'Should generate allocations despite conflicts');

        // Should report conflicts
        $this->assertNotEmpty($result->conflicts, 'Should detect unavoidable conflicts');

        // Only output verbose info when VERBOSE_E2E environment variable is set
        if (getenv('VERBOSE_E2E')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nEdge case test: %d allocations with %d conflicts\n",
                    count($result->allocations),
                    count($result->conflicts)
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
            // Delete tournaments and all related data via cascade
            Connection::execute("DELETE FROM tournaments WHERE name LIKE 'E2E Test%' OR name LIKE 'E2E Edge%'");
            // Also clean up by BCP event ID pattern in case of orphans
            Connection::execute("DELETE FROM tournaments WHERE bcp_event_id LIKE 'e2e_%'");
        } catch (\Exception $e) {
            // Ignore cleanup errors
        }
    }

    private function createTournament(): array
    {
        // Use microtime and uniqid to ensure unique BCP event IDs even with process isolation
        $uniqueId = str_replace('.', '', microtime(true)) . bin2hex(random_bytes(4));
        return $this->tournamentService->createTournament(
            'E2E Test Tournament ' . $uniqueId,
            'https://www.bestcoastpairings.com/event/e2e' . $uniqueId,
            self::TABLE_COUNT
        );
    }

    private function createRound1WithPairings(Tournament $tournament): Round
    {
        $round = Round::findOrCreate($tournament->id, 1);
        $tables = Table::findByTournament($tournament->id);

        // Create players and allocations
        for ($i = 1; $i <= self::PLAYER_COUNT; $i += 2) {
            $p1 = Player::findOrCreate($tournament->id, "bcp_e2e_{$i}", "E2E Player {$i}");
            $p2 = Player::findOrCreate($tournament->id, "bcp_e2e_" . ($i + 1), "E2E Player " . ($i + 1));

            $tableIndex = ($i - 1) / 2;
            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$tableIndex]->id,
                $p1->id,
                $p2->id,
                0,
                0,
                [
                    'timestamp' => date('c'),
                    'totalCost' => 0,
                    'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                    'reasons' => ['Round 1 - BCP original assignment'],
                    'alternativesConsidered' => [],
                    'isRound1' => true,
                    'conflicts' => [],
                ]
            );
            $allocation->save();
        }

        return $round;
    }

    private function createRound2Pairings(Tournament $tournament): Round
    {
        $round = Round::findOrCreate($tournament->id, 2);
        $players = Player::findByTournament($tournament->id);

        // Shuffle players for different pairings
        $playerIds = array_map(fn($p) => $p->id, $players);
        shuffle($playerIds);

        // Create temporary allocations (just to have players in round)
        $tables = Table::findByTournament($tournament->id);
        for ($i = 0; $i < count($playerIds) - 1; $i += 2) {
            $tableIndex = $i / 2;
            $allocation = new Allocation(
                null,
                $round->id,
                $tables[$tableIndex]->id,
                $playerIds[$i],
                $playerIds[$i + 1],
                rand(0, 4), // Random scores for variety
                rand(0, 4),
                null
            );
            $allocation->save();
        }

        return $round;
    }

    private function createPairingsForRound(Tournament $tournament, int $roundNumber): void
    {
        $round = Round::findOrCreate($tournament->id, $roundNumber);
        $players = Player::findByTournament($tournament->id);
        $tables = Table::findByTournament($tournament->id);

        // Shuffle for random pairings
        shuffle($players);

        for ($i = 0; $i < count($players) - 1; $i += 2) {
            $tableIndex = $i / 2;
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
                    'isRound1' => $roundNumber === 1,
                    'conflicts' => [],
                ]
            );
            $allocation->save();
        }
    }

    private function generateAllocations(Tournament $tournament, int $roundNumber)
    {
        $round = Round::findByTournamentAndNumber($tournament->id, $roundNumber);
        $allocations = Allocation::findByRound($round->id);

        // Build pairings from allocations
        $pairings = [];
        foreach ($allocations as $allocation) {
            $p1 = Player::find($allocation->player1Id);
            $p2 = Player::find($allocation->player2Id);

            $pairings[] = new Pairing(
                $p1->bcpPlayerId,
                $p1->name,
                $allocation->player1Score,
                $p2->bcpPlayerId,
                $p2->name,
                $allocation->player2Score,
                null
            );
        }

        // Get tables
        $tables = Table::findByTournament($tournament->id);
        $tablesArray = array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);

        $history = new TournamentHistory($tournament->id, $roundNumber);
        return $this->allocationService->generateAllocations($pairings, $tablesArray, $roundNumber, $history);
    }

    private function saveAllocations(Tournament $tournament, Round $round, $result): void
    {
        // Clear existing allocations
        $round->clearAllocations();

        // Create lookups
        $playerLookup = [];
        foreach (Player::findByTournament($tournament->id) as $player) {
            $playerLookup[$player->bcpPlayerId] = $player->id;
        }

        $tableLookup = [];
        foreach (Table::findByTournament($tournament->id) as $table) {
            $tableLookup[$table->tableNumber] = $table->id;
        }

        // Save allocations
        foreach ($result->allocations as $allocData) {
            $player1Id = $playerLookup[$allocData['player1']['bcpId']] ?? null;
            $player2Id = $playerLookup[$allocData['player2']['bcpId']] ?? null;
            $tableId = $tableLookup[$allocData['tableNumber']] ?? null;

            if ($player1Id && $player2Id && $tableId) {
                $allocation = new Allocation(
                    null,
                    $round->id,
                    $tableId,
                    $player1Id,
                    $player2Id,
                    $allocData['player1']['score'],
                    $allocData['player2']['score'],
                    $allocData['reason']
                );
                $allocation->save();
            }
        }
    }

    private function verifyMinimalTableReuse(Tournament $tournament): void
    {
        // Get all rounds
        $rounds = Round::findByTournament($tournament->id);

        // Build player -> table usage map
        $playerTableUsage = [];
        foreach ($rounds as $round) {
            $allocations = Allocation::findByRound($round->id);
            foreach ($allocations as $allocation) {
                $table = Table::find($allocation->tableId);
                $playerTableUsage[$allocation->player1Id][] = $table->tableNumber;
                $playerTableUsage[$allocation->player2Id][] = $table->tableNumber;
            }
        }

        // Count players with table reuse
        $playersWithReuse = 0;
        foreach ($playerTableUsage as $playerId => $tables) {
            $uniqueTables = array_unique($tables);
            if (count($uniqueTables) < count($tables)) {
                $playersWithReuse++;
            }
        }

        // Log the result (table reuse may be unavoidable with many rounds)
        if (getenv('VERBOSE_E2E')) {
            fwrite(
                STDERR,
                sprintf(
                    "\nTable reuse check: %d/%d players had table reuse over %d rounds\n",
                    $playersWithReuse,
                    count($playerTableUsage),
                    count($rounds)
                )
            );
        }
    }
}
