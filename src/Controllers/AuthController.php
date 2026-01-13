<?php

declare(strict_types=1);

namespace KTTables\Controllers;

use KTTables\Services\AuthService;

/**
 * Authentication controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/auth
 */
class AuthController extends BaseController
{
    private AuthService $service;

    public function __construct()
    {
        $this->service = new AuthService();
    }

    /**
     * POST /api/auth - Authenticate with admin token.
     *
     * Reference: FR-004
     */
    public function authenticate(array $params, ?array $body): void
    {
        if (empty($body['token'])) {
            $this->validationError(['token' => ['Token is required']]);
            return;
        }

        $result = $this->service->validateToken($body['token']);

        if (!$result['valid']) {
            $this->unauthorized($result['error']);
            return;
        }

        // Set admin token cookie (30-day retention)
        $this->setCookie('admin_token', $body['token'], 30 * 24 * 60 * 60);

        $tournament = $result['tournament'];

        $this->success([
            'tournamentId' => $tournament->id,
            'tournamentName' => $tournament->name,
            'message' => 'Authentication successful',
        ]);
    }
}
