<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Services\TournamentService;
use TournamentTables\Services\BCPScraperService;
use TournamentTables\Services\Pairing;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Middleware\AdminAuthMiddleware;
use TournamentTables\Database\Connection;

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

        if (!empty($errors)) {
            $this->validationError($errors);
            return;
        }

        try {
            // Create tournament
            // tableCount is optional - if not provided, tables will be created from Round 1
            $tableCount = isset($body['tableCount']) ? (int) $body['tableCount'] : 0;
            $result = $this->service->createTournament(
                $body['name'],
                $body['bcpUrl'],
                $tableCount
            );

            // Attempt to auto-import Round 1 and create tables
            $autoImportResult = $this->attemptAutoImportRound1($result['tournament']);

            // Set admin token cookie (30-day retention) per FR-003
            // Cookie has HttpOnly, SameSite=Lax, and Secure (when HTTPS) flags
            $this->setCookie('admin_token', $result['adminToken'], 30 * 24 * 60 * 60);

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
                $this->ensureSession();
                $_SESSION['tournament_just_created'] = [
                    'id' => $result['tournament']->id,
                    'adminToken' => $result['adminToken'],
                    'autoImport' => $autoImportResult,
                ];
                $this->redirect('/tournament/' . $result['tournament']->id);
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
     * Attempt to auto-import Round 1 and create tables from pairings.
     *
     * @param Tournament $tournament The newly created tournament
     * @return array{success: bool, tableCount?: int, pairingsImported?: int, error?: string}
     */
    private function attemptAutoImportRound1(Tournament $tournament): array
    {
        try {
            // Extract event ID from BCP URL
            $scraper = new BCPScraperService();
            $eventId = $scraper->extractEventId($tournament->bcpUrl);

            // Fetch pairings from BCP for Round 1
            $pairings = $scraper->fetchPairings($eventId, 1);

            if (empty($pairings)) {
                return [
                    'success' => false,
                    'error' => 'Round 1 not yet published on BCP',
                ];
            }

            // Derive table count from number of pairings
            $tableCount = count($pairings);

            // Import pairings in a transaction
            Connection::beginTransaction();

            try {
                // Create tables (Table 1, Table 2, ..., Table n)
                Table::createForTournament($tournament->id, $tableCount);

                // Create round
                $round = Round::findOrCreate($tournament->id, 1);

                // Clear existing allocations (shouldn't be any, but for safety)
                $round->clearAllocations();

                // Import players and create allocations
                $pairingsImported = 0;

                foreach ($pairings as $pairing) {
                    // Find or create players
                    $player1 = Player::findOrCreate(
                        $tournament->id,
                        $pairing->player1BcpId,
                        $pairing->player1Name
                    );
                    $player2 = Player::findOrCreate(
                        $tournament->id,
                        $pairing->player2BcpId,
                        $pairing->player2Name
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
                            $reason
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
                'error' => 'Failed to import Round 1: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Ensure session is started.
     */
    private function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Redirect to a URL.
     *
     * @param string $url URL to redirect to
     */
    private function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
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
     * DELETE /api/tournaments/{id} - Delete a tournament.
     *
     * Cascade deletes all related data (tables, rounds, players, allocations).
     * Admin authentication required.
     */
    public function delete(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        // Verify authenticated tournament matches requested tournament
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized('Token does not match this tournament');
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
