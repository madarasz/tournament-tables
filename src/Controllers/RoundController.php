<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Middleware\AdminAuthMiddleware;
use TournamentTables\Database\Connection;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Services\AllocationService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Services\Pairing;

/**
 * Round management controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/rounds
 */
class RoundController extends BaseController
{
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
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
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
                    $player2TotalScore = $totalScores[$pairing->player2BcpId] ?? 0;

                    // Find or create players with total scores
                    $player1 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player1BcpId,
                        $pairing->player1Name,
                        $player1TotalScore
                    );
                    $player2 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player2BcpId,
                        $pairing->player2Name,
                        $player2TotalScore
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
                    $generationResult = $this->runAllocationGeneration($tournamentId, $roundNumber, $round);

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
     * Run allocation generation for a round.
     *
     * Helper method used by both import (for round 2+) and generate actions.
     *
     * @param int $tournamentId Tournament ID
     * @param int $roundNumber Round number
     * @param Round $round Round model
     * @return array Result with allocations, conflicts, and summary
     */
    private function runAllocationGeneration(int $tournamentId, int $roundNumber, Round $round): array
    {
        // Get existing allocations to extract pairings
        $existingAllocations = Allocation::findByRound($round->id);

        // Build BCP table lookup from existing allocations before deleting
        // Key: player1BcpId:player2BcpId, Value: bcpTableNumber
        $bcpTableLookup = [];
        foreach ($existingAllocations as $alloc) {
            if ($alloc->bcpTableNumber !== null) {
                $p1 = Player::find($alloc->player1Id);
                $p2 = Player::find($alloc->player2Id);
                if ($p1 && $p2) {
                    $key = $p1->bcpPlayerId . ':' . $p2->bcpPlayerId;
                    $bcpTableLookup[$key] = $alloc->bcpTableNumber;
                }
            }
        }

        // Build pairings from existing allocations
        $pairings = [];
        foreach ($existingAllocations as $allocation) {
            $player1 = Player::find($allocation->player1Id);
            $player2 = Player::find($allocation->player2Id);

            if ($player1 === null || $player2 === null) {
                continue;
            }

            // Look up BCP table number from preserved lookup
            $bcpTableKey = $player1->bcpPlayerId . ':' . $player2->bcpPlayerId;
            $bcpTableNumber = $bcpTableLookup[$bcpTableKey] ?? null;

            $pairings[] = new Pairing(
                $player1->bcpPlayerId,
                $player1->name,
                $allocation->player1Score,
                $player2->bcpPlayerId,
                $player2->name,
                $allocation->player2Score,
                $bcpTableNumber,
                $player1->totalScore,
                $player2->totalScore
            );
        }

        // Get tables
        $tables = Table::findByTournament($tournamentId);
        $tablesArray = array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'id' => $t->id,
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);

        // Generate allocations
        $allocationService = new AllocationService(new CostCalculator());
        $history = new TournamentHistory($tournamentId, $roundNumber);

        $result = $allocationService->generateAllocations(
            $pairings,
            $tablesArray,
            $roundNumber,
            $history
        );

        // Save allocations in transaction
        Connection::beginTransaction();

        try {
            // Clear existing allocations
            $round->clearAllocations();

            // Create player ID lookup
            $playerLookup = [];
            foreach (Player::findByTournament($tournamentId) as $player) {
                $playerLookup[$player->bcpPlayerId] = $player->id;
            }

            // Create table ID lookup
            $tableLookup = [];
            foreach ($tables as $table) {
                $tableLookup[$table->tableNumber] = $table->id;
            }

            // Save new allocations
            $savedAllocations = [];
            foreach ($result->allocations as $allocData) {
                $player1Id = $playerLookup[$allocData['player1']['bcpId']] ?? null;
                $player2Id = $playerLookup[$allocData['player2']['bcpId']] ?? null;
                $tableId = $tableLookup[$allocData['tableNumber']] ?? null;

                if ($player1Id === null || $player2Id === null || $tableId === null) {
                    continue;
                }

                // Retrieve preserved BCP table number from lookup
                $bcpTableKey = $allocData['player1']['bcpId'] . ':' . $allocData['player2']['bcpId'];
                $bcpTableNumber = $bcpTableLookup[$bcpTableKey] ?? null;

                $allocation = new Allocation(
                    null,
                    $round->id,
                    $tableId,
                    $player1Id,
                    $player2Id,
                    $allocData['player1']['score'],
                    $allocData['player2']['score'],
                    $allocData['reason'],
                    $bcpTableNumber
                );
                $allocation->save();

                $savedAllocations[] = $allocation->toArray();
            }

            Connection::commit();

            return [
                'allocations' => $savedAllocations,
                'conflicts' => $result->conflicts,
                'summary' => $result->summary,
            ];
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
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
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
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
            $result = $this->runAllocationGeneration($tournamentId, $roundNumber, $round);

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
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
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
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
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
            'allocations' => array_map(function ($a) {
                return $a->toArray();
            }, $allocations),
            'conflicts' => $conflicts,
        ]);
    }
}
