<?php

declare(strict_types=1);

namespace KTTables\Controllers;

use KTTables\Models\Tournament;
use KTTables\Models\Round;
use KTTables\Middleware\AdminAuthMiddleware;

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

        // TODO: Implement BCP import in Phase 5 (US1)
        $this->error('not_implemented', 'BCP import not yet implemented', 501);
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

        // TODO: Implement allocation generation in Phase 5 (US1)
        $this->error('not_implemented', 'Allocation generation not yet implemented', 501);
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
