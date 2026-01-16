<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Models\Tournament;

/**
 * Authentication service.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/auth
 */
class AuthService
{
    /**
     * Validate an admin token.
     *
     * @param string $token Token to validate
     * @return array{valid: bool, tournament?: Tournament, error?: string}
     */
    public function validateToken(string $token): array
    {
        $token = trim($token);

        if (empty($token)) {
            return ['valid' => false, 'error' => 'Token is required'];
        }

        if (strlen($token) !== 16) {
            return ['valid' => false, 'error' => 'Invalid token format'];
        }

        try {
            $tournament = Tournament::findByToken($token);
            if ($tournament === null) {
                return ['valid' => false, 'error' => 'Invalid token'];
            }

            return ['valid' => true, 'tournament' => $tournament];
        } catch (\PDOException $e) {
            return ['valid' => false, 'error' => 'Invalid token'];
        }
    }
}
