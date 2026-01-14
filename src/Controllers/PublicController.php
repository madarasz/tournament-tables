<?php

declare(strict_types=1);

namespace KTTables\Controllers;

use KTTables\Models\Tournament;
use KTTables\Models\Round;

/**
 * Public (unauthenticated) controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/public
 */
class PublicController extends BaseController
{
    /**
     * GET /api/public/tournaments/{id} - Get public tournament info.
     */
    public function showTournament(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            $this->notFound('Tournament');
            return;
        }

        // Get only published rounds
        $publishedRounds = Round::findPublishedByTournament($tournamentId);
        $publishedRoundNumbers = array_map(function ($r) {
            return $r->roundNumber;
        }, $publishedRounds);

        $this->success([
            'id' => $tournament->id,
            'name' => $tournament->name,
            'tableCount' => $tournament->tableCount,
            'publishedRounds' => $publishedRoundNumbers,
        ]);
    }

    /**
     * GET /api/public/tournaments/{id}/rounds/{n} - Get published allocations.
     *
     * Reference: FR-012
     */
    public function showRound(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        // Validate round number range (per data-model.md validation rules)
        if ($roundNumber < 1 || $roundNumber > 20) {
            $this->validationError(['roundNumber' => ['Round number must be between 1 and 20']]);
            return;
        }

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            $this->notFound('Tournament');
            return;
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            $this->notFound('Round');
            return;
        }

        // Only show published rounds
        if (!$round->isPublished) {
            $this->error('not_found', 'Round not published', 404);
            return;
        }

        $allocations = $round->getAllocations();

        $this->success([
            'tournamentName' => $tournament->name,
            'roundNumber' => $round->roundNumber,
            'allocations' => array_map(function ($a) {
                return $a->toPublicArray();
            }, $allocations),
        ]);
    }
}
