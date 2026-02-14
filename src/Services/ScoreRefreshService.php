<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Database\Connection;

/**
 * Service to refresh per-round scores from BCP for existing allocations.
 *
 * Fetches fresh pairing data from BCP and updates the player1_score / player2_score
 * on existing allocations without recreating them.
 */
class ScoreRefreshService
{
    /** @var BCPApiService */
    private $bcpService;

    public function __construct(?BCPApiService $bcpService = null)
    {
        $this->bcpService = $bcpService ?: new BCPApiService();
    }

    /**
     * Refresh scores for a specific round by re-fetching pairings from BCP.
     *
     * @param int $tournamentId Tournament ID
     * @param int $roundNumber Round number to refresh scores for
     * @return array{updated: int, message: string} Result summary
     * @throws \RuntimeException If BCP API fails
     */
    public function refreshScoresForRound(int $tournamentId, int $roundNumber): array
    {
        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            return ['updated' => 0, 'message' => 'Tournament not found'];
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            return ['updated' => 0, 'message' => 'Round not found'];
        }

        // Get existing allocations for this round
        $allocations = Allocation::findByRound($round->id);
        if (empty($allocations)) {
            return ['updated' => 0, 'message' => 'No allocations to update'];
        }

        // Fetch fresh pairings from BCP
        $eventId = $this->bcpService->extractEventId($tournament->bcpUrl);
        $pairings = $this->bcpService->fetchPairings($eventId, $roundNumber);

        if (empty($pairings)) {
            return ['updated' => 0, 'message' => 'No pairings returned from BCP'];
        }

        // Build a lookup: bcpPlayerId -> Player model for all players in this tournament
        $players = Player::findByTournament($tournamentId);
        $playerByBcpId = [];
        foreach ($players as $player) {
            $playerByBcpId[$player->bcpPlayerId] = $player;
        }

        // Build a lookup: "player1Id-player2Id" -> Allocation for matching
        $allocationByPlayers = [];
        foreach ($allocations as $allocation) {
            $key = $allocation->player1Id . '-' . ($allocation->player2Id ?: 'bye');
            $allocationByPlayers[$key] = $allocation;
            // Also index by reversed player order in case BCP returns them swapped
            if ($allocation->player2Id !== null) {
                $reverseKey = $allocation->player2Id . '-' . $allocation->player1Id;
                $allocationByPlayers[$reverseKey] = $allocation;
            }
        }

        $updated = 0;

        foreach ($pairings as $pairing) {
            // Look up our internal player IDs from BCP IDs
            $p1 = $playerByBcpId[$pairing->player1BcpId] ?? null;
            if ($p1 === null) {
                continue;
            }

            if ($pairing->isBye()) {
                $key = $p1->id . '-bye';
                $allocation = $allocationByPlayers[$key] ?? null;
                if ($allocation !== null) {
                    $result = Allocation::updateScores(
                        $allocation->id,
                        $pairing->player1Score,
                        0
                    );
                    if ($result) {
                        $updated++;
                    }
                }
                continue;
            }

            $p2 = $playerByBcpId[$pairing->player2BcpId] ?? null;
            if ($p2 === null) {
                continue;
            }

            // Try to find the allocation by player pair
            $key = $p1->id . '-' . $p2->id;
            $allocation = $allocationByPlayers[$key] ?? null;

            if ($allocation !== null) {
                // Determine correct score order based on which player is player1 in the allocation
                if ($allocation->player1Id === $p1->id) {
                    $p1Score = $pairing->player1Score;
                    $p2Score = $pairing->player2Score;
                } else {
                    $p1Score = $pairing->player2Score;
                    $p2Score = $pairing->player1Score;
                }

                $result = Allocation::updateScores($allocation->id, $p1Score, $p2Score);
                if ($result) {
                    $updated++;
                }
            }
        }

        return [
            'updated' => $updated,
            'message' => "Refreshed scores for {$updated} allocation(s) in round {$roundNumber}",
        ];
    }
}
