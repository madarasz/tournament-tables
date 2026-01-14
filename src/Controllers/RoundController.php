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
use TournamentTables\Services\BCPScraperService;
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
            $scraper = new BCPScraperService();
            $eventId = $scraper->extractEventId($tournament->bcpUrl);

            // Fetch pairings from BCP
            $pairings = $scraper->fetchPairings($eventId, $roundNumber);

            if (empty($pairings)) {
                $this->error('no_pairings', 'No pairings found for this round', 400);
                return;
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

                // Get all tables for this tournament (needed for assigning to rounds > 1)
                $tables = Table::findByTournament($tournamentId);
                $tableIndex = 0;

                foreach ($pairings as $pairing) {
                    // Find or create players
                    $player1 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player1BcpId,
                        $pairing->player1Name
                    );
                    $player2 = Player::findOrCreate(
                        $tournamentId,
                        $pairing->player2BcpId,
                        $pairing->player2Name
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
                        // These will be optimized when "Generate allocations" is called
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
                            $reason
                        );
                        $allocation->save();
                    }

                    $playersImported += 2;
                    $pairingsImported++;
                }

                Connection::commit();

                $this->success([
                    'roundNumber' => $roundNumber,
                    'pairingsImported' => $pairingsImported,
                    'playersImported' => $playersImported,
                    'message' => "Imported {$pairingsImported} pairings for round {$roundNumber}",
                ]);
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

        // Get existing allocations to extract pairings
        $existingAllocations = Allocation::findByRound($round->id);

        if (empty($existingAllocations)) {
            $this->error('no_pairings', 'No pairings available for this round. Import pairings first.', 400);
            return;
        }

        // Build pairings from existing allocations
        $pairings = [];
        foreach ($existingAllocations as $allocation) {
            $player1 = Player::find($allocation->player1Id);
            $player2 = Player::find($allocation->player2Id);

            if ($player1 === null || $player2 === null) {
                continue;
            }

            $pairings[] = new Pairing(
                $player1->bcpPlayerId,
                $player1->name,
                $allocation->player1Score,
                $player2->bcpPlayerId,
                $player2->name,
                $allocation->player2Score,
                null // No BCP table for regeneration
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

                $savedAllocations[] = $allocation->toArray();
            }

            Connection::commit();

            $this->success([
                'roundNumber' => $roundNumber,
                'allocations' => $savedAllocations,
                'conflicts' => $result->conflicts,
                'summary' => $result->summary,
            ]);
        } catch (\Exception $e) {
            Connection::rollBack();
            $this->error('generation_failed', 'Failed to save allocations: ' . $e->getMessage(), 500);
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
