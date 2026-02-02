<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Services\TournamentService;
use TournamentTables\Services\TournamentImportService;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Models\Tournament;

/**
 * Tournament management controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/tournaments
 */
class TournamentController extends BaseController
{
    /** @var TournamentService */
    private $service;

    /** @var TournamentImportService */
    private $importService;

    public function __construct()
    {
        $this->service = new TournamentService();
        $this->importService = new TournamentImportService();
    }

    /**
     * POST /api/tournaments - Create a new tournament.
     *
     * Tournament name is automatically fetched from the BCP event page.
     *
     * Reference: FR-001, FR-002, FR-003
     */
    public function create(array $params, ?array $body): void
    {
        // Validate required fields
        $errors = [];

        $bcpUrl = trim($body['bcpUrl'] ?? '');
        if ($bcpUrl === '') {
            $errors['bcpUrl'] = ['BCP URL is required'];
        }

        if (!empty($errors)) {
            $this->validationError($errors);
            return;
        }

        try {
            // Fetch tournament name from BCP page
            $scraper = new BCPApiService();
            try {
                $scraper->extractEventId($bcpUrl);
                $tournamentName = $scraper->fetchTournamentName($bcpUrl);
            } catch (\InvalidArgumentException $e) {
                $this->validationError([
                    'bcpUrl' => [
                        'BCP URL must match https://www.bestcoastpairings.com/event/{eventId}',
                    ],
                ]);
                return;
            } catch (\RuntimeException $e) {
                $this->validationError([
                    'bcpUrl' => ['Unable to fetch tournament name from BCP. Please check the URL and try again.']
                ]);
                return;
            }

            // Create tournament
            // tableCount is optional - if not provided, tables will be created from Round 1
            $tableCount = isset($body['tableCount']) ? (int) $body['tableCount'] : 0;
            $result = $this->service->createTournament(
                $tournamentName,
                $bcpUrl,
                $tableCount
            );

            // Attempt to auto-import Round 1 and create tables
            $autoImportResult = $this->importService->autoImportRound1($result['tournament']);

            // Check if this is an API request (JSON) or browser form submission
            $contentType = $_SERVER['CONTENT_TYPE'] ?? '';
            $isJsonRequest = strpos($contentType, 'application/json') !== false;

            if ($isJsonRequest) {
                // API request - return JSON response (for test helpers and API clients)
                $response = [
                    'tournament' => $result['tournament']->toArray(),
                    'adminToken' => $result['adminToken'],
                ];

                // Include auto-import result
                if ($autoImportResult['success']) {
                    $response['autoImport'] = [
                        'success' => true,
                        'tableCount' => $autoImportResult['tableCount'],
                        'pairingsImported' => $autoImportResult['pairingsImported'],
                    ];
                } else {
                    $response['autoImport'] = [
                        'success' => false,
                        'error' => $autoImportResult['error'],
                    ];
                }

                $this->success($response, 201);
            } else {
                // Browser form submission - redirect to dashboard with success message
                // Start session first (before setting cookies) to avoid header conflicts
                $this->ensureSession();
                $_SESSION['tournament_just_created'] = [
                    'id' => $result['tournament']->id,
                    'adminToken' => $result['adminToken'],
                    'autoImport' => $autoImportResult,
                ];

                // Add tournament token to multi-token cookie (30-day retention) per FR-003
                // Cookie has HttpOnly, SameSite=Lax, and Secure (when HTTPS) flags
                $this->addTournamentToken(
                    $result['tournament']->id,
                    $result['adminToken'],
                    $result['tournament']->name
                );

                $this->redirect('/admin/tournament/' . $result['tournament']->id);
            }
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

        $tournament = $this->getTournamentOrFail($tournamentId);
        if ($tournament === null) {
            return;
        }

        // Build full tournament response with tables and rounds
        $response = $tournament->toArray();
        $response['tables'] = $this->toArrayMap($tournament->getTables());
        $response['rounds'] = $this->toArrayMap($tournament->getRounds());

        $this->success($response);
    }

    /**
     * DELETE /api/tournaments/{id} - Delete a tournament.
     *
     * Cascade deletes all related data (tables, rounds, players, allocations).
     * Admin authentication required.
     */
    public function delete(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        try {
            $this->service->deleteTournament($tournamentId);
            $this->success(['message' => 'Tournament deleted successfully']);
        } catch (\InvalidArgumentException $e) {
            $this->notFound('Tournament');
        } catch (\Exception $e) {
            $this->error('internal_error', 'Failed to delete tournament', 500);
        }
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
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return;
        }

        if (!isset($body['tables']) || !is_array($body['tables'])) {
            $this->validationError(['tables' => ['Tables array is required']]);
            return;
        }

        try {
            $tables = $this->service->updateTables($tournamentId, $body['tables']);

            $this->success([
                'tables' => $this->toArrayMap($tables),
            ]);
        } catch (\InvalidArgumentException $e) {
            $this->notFound('Tournament');
        } catch (\Exception $e) {
            $this->error('internal_error', 'Failed to update tables', 500);
        }
    }
}
