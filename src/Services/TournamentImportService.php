<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Database\Connection;

/**
 * Service for importing tournament data from BCP.
 *
 * Extracted from TournamentController to separate business logic from HTTP handling.
 */
class TournamentImportService
{
    /** @var BCPApiService */
    private $bcpService;

    public function __construct(BCPApiService $bcpService = null)
    {
        $this->bcpService = $bcpService ?? new BCPApiService();
    }

    /**
     * Attempt to auto-import Round 1 and create tables from pairings.
     *
     * @param Tournament $tournament The tournament to import into
     * @return array{success: bool, tableCount?: int, pairingsImported?: int, error?: string}
     */
    public function autoImportRound1(Tournament $tournament): array
    {
        try {
            // Extract event ID from BCP URL
            $eventId = $this->bcpService->extractEventId($tournament->bcpUrl);

            // Fetch pairings from BCP for Round 1
            $pairings = $this->bcpService->fetchPairings($eventId, 1);

            if (empty($pairings)) {
                return [
                    'success' => false,
                    'error' => 'Round 1 not yet published on BCP',
                ];
            }

            // Derive table count from max BCP table number
            $tableCount = $this->deriveTableCount($pairings);

            // Fetch total scores from BCP placings API (gracefully handle failures)
            $totalScores = $this->fetchPlayerScores($eventId);

            // Import pairings in a transaction
            return $this->importPairings($tournament, $pairings, $totalScores, $tableCount);
        } catch (\RuntimeException $e) {
            // BCP scraping failed
            return [
                'success' => false,
                'error' => 'BCP API unavailable or Round 1 not published yet',
            ];
        } catch (\InvalidArgumentException $e) {
            // Invalid BCP URL (shouldn't happen as we validated earlier)
            return [
                'success' => false,
                'error' => 'Invalid BCP URL',
            ];
        } catch (\Exception $e) {
            // Other errors
            return [
                'success' => false,
                'error' => 'Failed to import Round 1',
            ];
        }
    }

    /**
     * Derive the table count from pairings.
     *
     * Bye pairings (odd player count) are excluded from table count calculation.
     *
     * @param Pairing[] $pairings Array of pairings
     * @return int Table count
     */
    private function deriveTableCount(array $pairings): int
    {
        // Count only regular pairings (not byes) for initial table count
        $regularPairings = array_filter($pairings, function (Pairing $p) {
            return !$p->isBye();
        });
        $tableCount = count($regularPairings);

        foreach ($pairings as $pairing) {
            if ($pairing->bcpTableNumber !== null && $pairing->bcpTableNumber > $tableCount) {
                $tableCount = $pairing->bcpTableNumber;
            }
        }
        return $tableCount;
    }

    /**
     * Fetch player total scores from BCP (with graceful error handling).
     *
     * @param string $eventId BCP event ID
     * @return array<string, int> Map of player BCP ID to total score
     */
    private function fetchPlayerScores(string $eventId): array
    {
        try {
            return $this->bcpService->fetchPlayerTotalScores($eventId);
        } catch (\Exception $e) {
            // Continue without total scores - not critical for round 1
            return [];
        }
    }

    /**
     * Import pairings into the tournament.
     *
     * @param Tournament $tournament Tournament to import into
     * @param Pairing[] $pairings Pairings to import
     * @param array<string, int> $totalScores Player total scores
     * @param int $tableCount Number of tables to create
     * @return array{success: bool, tableCount: int, pairingsImported: int}
     */
    private function importPairings(
        Tournament $tournament,
        array $pairings,
        array $totalScores,
        int $tableCount
    ): array {
        Connection::beginTransaction();

        try {
            // Create tables only if none exist
            $existingTables = Table::findByTournament($tournament->id);
            if (empty($existingTables)) {
                Table::createForTournament($tournament->id, $tableCount);
            }

            // Create round
            $round = Round::findOrCreate($tournament->id, 1);

            // Clear existing allocations (shouldn't be any, but for safety)
            $round->clearAllocations();

            // Import players and create allocations
            $pairingsImported = 0;

            foreach ($pairings as $pairing) {
                // Get total score for player 1 (always exists)
                $player1TotalScore = $totalScores[$pairing->player1BcpId] ?? 0;

                // Find or create player 1 with total score and faction
                $player1 = Player::findOrCreate(
                    $tournament->id,
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
                        'reasons' => ['Bye - no opponent this round (auto-imported)'],
                        'alternativesConsidered' => [],
                        'isRound1' => true,
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
                    $pairingsImported++;
                    continue;
                }

                // Regular pairing - find or create player 2
                $player2TotalScore = $totalScores[$pairing->player2BcpId] ?? 0;
                $player2 = Player::findOrCreate(
                    $tournament->id,
                    $pairing->player2BcpId,
                    $pairing->player2Name,
                    $player2TotalScore,
                    $pairing->player2Faction
                );

                // Round 1: Use BCP's table assignment
                $table = null;
                if ($pairing->bcpTableNumber !== null) {
                    $table = Table::findByTournamentAndNumber($tournament->id, $pairing->bcpTableNumber);
                }

                // Create allocation if we have a valid table
                if ($table !== null) {
                    $reason = [
                        'timestamp' => date('c'),
                        'totalCost' => 0,
                        'costBreakdown' => ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 0],
                        'reasons' => ['Round 1 - BCP original assignment (auto-imported)'],
                        'alternativesConsidered' => [],
                        'isRound1' => true,
                        'conflicts' => [],
                    ];

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
                    $pairingsImported++;
                }
            }

            Connection::commit();

            return [
                'success' => true,
                'tableCount' => $tableCount,
                'pairingsImported' => $pairingsImported,
            ];
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }
}
