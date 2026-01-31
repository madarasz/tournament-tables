<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Database\Connection;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Services\AllocationGenerationService;

/**
 * Round management controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/rounds
 */
class RoundController extends BaseController
{
    /** @var AllocationGenerationService */
    private $generationService;

    public function __construct()
    {
        $this->generationService = new AllocationGenerationService();
    }

    /**
     * POST /api/tournaments/{id}/rounds/{n}/import - Import pairings from BCP.
     *
     * Reference: FR-006, FR-015
     */
    public function import(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        // Validate round number
        if ($roundNumber < 1) {
            $this->validationError(['roundNumber' => ['Round number must be positive']]);
            return;
        }

        try {
            // Get tournament to extract BCP event ID
            $tournament = Tournament::find($tournamentId);
            if ($tournament === null) {
                $this->notFound('Tournament');
                return;
            }

            // Extract event ID from BCP URL
            $scraper = new BCPApiService();
            $eventId = $scraper->extractEventId($tournament->bcpUrl);

            // Fetch pairings from BCP
            $pairings = $scraper->fetchPairings($eventId, $roundNumber);

            if (empty($pairings)) {
                $this->error('no_pairings', 'No pairings found for this round', 400);
                return;
            }

            // Fetch total scores from BCP placings API
            $totalScores = [];
            try {
                $totalScores = $scraper->fetchPlayerTotalScores($eventId);
            } catch (\Exception $e) {
                // Log warning but continue - total scores are not critical
                error_log("Warning: Could not fetch total scores: " . $e->getMessage());
            }

            // Import pairings in a transaction
            Connection::beginTransaction();

            try {
                // Find or create round
                $round = Round::findOrCreate($tournamentId, $roundNumber);

                // Clear existing allocations for this round (FR-015: refresh)
                $round->clearAllocations();

                // Import players and create allocations
                $playersImported = 0;
                $pairingsImported = 0;

                // Get all tables for this tournament
                $tables = Table::findByTournament($tournamentId);

                // If no tables exist (first import), create them from pairing count
                if (empty($tables)) {
                    $tableCount = count($pairings);
                    $tables = Table::createForTournament($tournamentId, $tableCount);
                }

                $tableIndex = 0;

                foreach ($pairings as $pairing) {
                    // Get total scores for each player (default to 0 if not found)
                    $player1TotalScore = $totalScores[$pairing->player1BcpId] ?? 0;

                    // Find or create player1 with total score
                    $player1 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player1BcpId,
                        $pairing->player1Name,
                        $player1TotalScore,
                        $pairing->player1Faction
                    );

                    // Handle bye pairings (no opponent)
                    if ($pairing->isBye()) {
                        $reason = [
                            'timestamp' => date('c'),
                            'totalCost' => 0,
                            'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                            'reasons' => ['Bye - no opponent this round'],
                            'alternativesConsidered' => [],
                            'isRound1' => $roundNumber === 1,
                            'isBye' => true,
                            'conflicts' => [],
                        ];

                        // Create bye allocation with null table_id and player2_id
                        $allocation = new Allocation(
                            null,
                            $round->id,
                            null, // No table for bye
                            $player1->id,
                            null, // No player2 for bye
                            $pairing->player1Score,
                            0,
                            $reason,
                            null  // No BCP table for bye
                        );
                        $allocation->save();

                        $playersImported += 1;
                        $pairingsImported++;
                        continue;
                    }

                    // Regular pairing - find or create player2
                    $player2TotalScore = $totalScores[$pairing->player2BcpId] ?? 0;
                    $player2 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player2BcpId,
                        $pairing->player2Name,
                        $player2TotalScore,
                        $pairing->player2Faction
                    );

                    // Determine table assignment
                    $table = null;
                    $reason = [];

                    if ($roundNumber === 1 && $pairing->bcpTableNumber !== null) {
                        // Round 1: Use BCP's table assignment
                        $table = Table::findByTournamentAndNumber($tournamentId, $pairing->bcpTableNumber);
                        $reason = [
                            'timestamp' => date('c'),
                            'totalCost' => 0,
                            'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                            'reasons' => ['Round 1 - BCP original assignment'],
                            'alternativesConsidered' => [],
                            'isRound1' => true,
                            'conflicts' => [],
                        ];
                    } else {
                        // Round 2+: Assign tables sequentially as placeholders
                        // Allocation generation runs automatically after import to optimize assignments
                        if ($tableIndex < count($tables)) {
                            $table = $tables[$tableIndex];
                            $tableIndex++;
                        }

                        $reason = [
                            'timestamp' => date('c'),
                            'totalCost' => 0,
                            'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                            'reasons' => ['Imported from BCP - pending optimization'],
                            'alternativesConsidered' => [],
                            'isRound1' => false,
                            'conflicts' => [],
                        ];
                    }

                    // Create allocation if we have a valid table
                    if ($table !== null) {
                        $allocation = new Allocation(
                            null,
                            $round->id,
                            $table->id,
                            $player1->id,
                            $player2->id,
                            $pairing->player1Score,
                            $pairing->player2Score,
                            $reason,
                            $pairing->bcpTableNumber
                        );
                        $allocation->save();
                    }

                    $playersImported += 2;
                    $pairingsImported++;
                }

                Connection::commit();

                // For round 2+, automatically run allocation generation to optimize table assignments
                if ($roundNumber > 1) {
                    $generationResult = $this->generationService->generate($tournamentId, $roundNumber, $round);

                    $this->success([
                        'roundNumber' => $roundNumber,
                        'pairingsImported' => $pairingsImported,
                        'playersImported' => $playersImported,
                        'message' => "Imported {$pairingsImported} pairings for round {$roundNumber} and generated optimized allocations",
                        'allocations' => $generationResult['allocations'],
                        'conflicts' => $generationResult['conflicts'],
                        'summary' => $generationResult['summary'],
                    ]);
                } else {
                    $this->success([
                        'roundNumber' => $roundNumber,
                        'pairingsImported' => $pairingsImported,
                        'playersImported' => $playersImported,
                        'message' => "Imported {$pairingsImported} pairings for round {$roundNumber}",
                    ]);
                }
            } catch (\Exception $e) {
                Connection::rollBack();
                throw $e;
            }
        } catch (\RuntimeException $e) {
            // BCP scraping failed
            $this->error('bcp_unavailable', $e->getMessage(), 502);
        } catch (\InvalidArgumentException $e) {
            // Invalid BCP URL
            $this->error('invalid_bcp_url', $e->getMessage(), 400);
        }
    }

    /**
     * POST /api/tournaments/{id}/rounds/{n}/generate - Generate allocations.
     *
     * Reference: FR-007
     */
    public function generate(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        // Validate round number
        if ($roundNumber < 1) {
            $this->validationError(['roundNumber' => ['Round number must be positive']]);
            return;
        }

        // Get round
        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            $this->error('no_round', 'Round not found. Import pairings first.', 400);
            return;
        }

        // Get existing allocations to verify pairings exist
        $existingAllocations = Allocation::findByRound($round->id);

        if (empty($existingAllocations)) {
            $this->error('no_pairings', 'No pairings available for this round. Import pairings first.', 400);
            return;
        }

        try {
            $result = $this->generationService->generate($tournamentId, $roundNumber, $round);

            $this->success([
                'roundNumber' => $roundNumber,
                'allocations' => $result['allocations'],
                'conflicts' => $result['conflicts'],
                'summary' => $result['summary'],
            ]);
        } catch (\Exception $e) {
            $this->error('generation_failed', 'Failed to generate allocations: ' . $e->getMessage(), 500);
        }
    }

    /**
     * POST /api/tournaments/{id}/rounds/{n}/publish - Publish allocations.
     *
     * Reference: FR-011
     */
    public function publish(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            $this->notFound('Round');
            return;
        }

        // Check for table collisions before publishing
        if ($round->hasTableCollisions()) {
            $collisions = $round->getTableCollisions();
            $tableNumbers = array_column($collisions, 'tableNumber');
            $this->error(
                'table_collision',
                'Cannot publish: Table collision detected on table(s) ' . implode(', ', $tableNumbers) .
                '. Each table can only be assigned to one pairing per round.',
                400
            );
            return;
        }

        $round->publish();

        $this->success([
            'roundNumber' => $round->roundNumber,
            'message' => "Round {$roundNumber} allocations are now public",
        ]);
    }

    /**
     * GET /api/tournaments/{id}/rounds/{n} - Get round allocations (admin view).
     */
    public function show(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            $this->notFound('Round');
            return;
        }

        $allocations = $round->getAllocations();
        $conflicts = [];

        foreach ($allocations as $allocation) {
            foreach ($allocation->getConflicts() as $conflict) {
                $conflicts[] = $conflict;
            }
        }

        $this->success([
            'roundNumber' => $round->roundNumber,
            'isPublished' => $round->isPublished,
            'allocations' => $this->toArrayMap($allocations),
            'conflicts' => $conflicts,
        ]);
    }
}
