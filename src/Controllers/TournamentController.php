<?php

declare(strict_types=1);

namespace KTTables\Controllers;

use KTTables\Services\TournamentService;
use KTTables\Models\Tournament;
use KTTables\Middleware\AdminAuthMiddleware;

/**
 * Tournament management controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/tournaments
 */
class TournamentController extends BaseController
{
    private TournamentService $service;

    public function __construct()
    {
        $this->service = new TournamentService();
    }

    /**
     * POST /api/tournaments - Create a new tournament.
     *
     * Reference: FR-001, FR-002, FR-003
     */
    public function create(array $params, ?array $body): void
    {
        // Validate required fields
        $errors = [];

        if (empty($body['name'])) {
            $errors['name'] = ['Tournament name is required'];
        }

        if (empty($body['bcpUrl'])) {
            $errors['bcpUrl'] = ['BCP URL is required'];
        }

        if (!isset($body['tableCount'])) {
            $errors['tableCount'] = ['Table count is required'];
        }

        if (!empty($errors)) {
            $this->validationError($errors);
            return;
        }

        try {
            $result = $this->service->createTournament(
                $body['name'],
                $body['bcpUrl'],
                (int) $body['tableCount']
            );

            // Set admin token cookie (30-day retention) per FR-003
            // Cookie has HttpOnly, SameSite=Lax, and Secure (when HTTPS) flags
            $this->setCookie('admin_token', $result['adminToken'], 30 * 24 * 60 * 60);

            $this->success([
                'tournament' => $result['tournament']->toArray(),
                'adminToken' => $result['adminToken'],
            ], 201);
        } catch (\InvalidArgumentException $e) {
            $this->validationError(['_general' => [$e->getMessage()]]);
        } catch (\RuntimeException $e) {
            $this->error('conflict', $e->getMessage(), 409);
        } catch (\Exception $e) {
            $this->error('internal_error', 'Failed to create tournament', 500);
        }
    }

    /**
     * GET /api/tournaments/{id} - Get tournament details.
     */
    public function show(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
            return;
        }

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            $this->notFound('Tournament');
            return;
        }

        // Build full tournament response with tables and rounds
        $response = $tournament->toArray();
        $response['tables'] = array_map(function ($t) {
            return $t->toArray();
        }, $tournament->getTables());
        $response['rounds'] = array_map(function ($r) {
            return $r->toArray();
        }, $tournament->getRounds());

        $this->success($response);
    }

    /**
     * PUT /api/tournaments/{id}/tables - Update table terrain types.
     *
     * Reference: FR-005
     */
    public function updateTables(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
            return;
        }

        if (!isset($body['tables']) || !is_array($body['tables'])) {
            $this->validationError(['tables' => ['Tables array is required']]);
            return;
        }

        try {
            $tables = $this->service->updateTables($tournamentId, $body['tables']);

            $this->success([
                'tables' => array_map(function ($t) {
                    return $t->toArray();
                }, $tables),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->notFound('Tournament');
        } catch (\Exception $e) {
            $this->error('internal_error', 'Failed to update tables', 500);
        }
    }
}
