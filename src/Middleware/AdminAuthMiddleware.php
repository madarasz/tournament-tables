<?php

declare(strict_types=1);

namespace TournamentTables\Middleware;

use TournamentTables\Models\Tournament;

/**
 * Admin authentication middleware.
 *
 * Checks for valid admin token in X-Admin-Token header or admin_token cookie.
 * Reference: specs/001-table-allocation/contracts/api.yaml#securitySchemes
 */
class AdminAuthMiddleware
{
    /**
     * Check authentication.
     *
     * @return true|string Returns true if authenticated, error message otherwise
     */
    public static function check()
    {
        $token = self::getToken();

        if ($token === null) {
            return 'Missing authentication token';
        }

        if (strlen($token) !== 16) {
            return 'Invalid token format';
        }

        $tournament = Tournament::findByToken($token);
        if ($tournament === null) {
            return 'Invalid authentication token';
        }

        // Store authenticated tournament for later use
        global $authenticatedTournament;
        $authenticatedTournament = $tournament;

        return true;
    }

    /**
     * Get the admin token from header or cookie.
     */
    public static function getToken(): ?string
    {
        // Check header first (API clients)
        $headerToken = self::getHeaderToken();
        if ($headerToken !== null) {
            return $headerToken;
        }

        // Fall back to cookie (browser clients)
        return self::getCookieToken();
    }

    /**
     * Get token from X-Admin-Token header.
     */
    private static function getHeaderToken(): ?string
    {
        $key = 'HTTP_X_ADMIN_TOKEN';
        return isset($_SERVER[$key]) && !empty($_SERVER[$key])
            ? $_SERVER[$key]
            : null;
    }

    /**
     * Get token from admin_token cookie.
     * Supports multi-token JSON format.
     */
    private static function getCookieToken(): ?string
    {
        $cookieValue = $_COOKIE['admin_token'] ?? null;
        if ($cookieValue === null || empty($cookieValue)) {
            return null;
        }

        // Try JSON format (multi-token)
        $decoded = json_decode($cookieValue, true);
        if (is_array($decoded) && isset($decoded['tournaments'])) {
            $tournaments = $decoded['tournaments'];

            // Extract tournament ID from request URI
            $uri = $_SERVER['REQUEST_URI'] ?? '';
            $tournamentId = self::getTournamentIdFromUri($uri);

            if ($tournamentId !== null && isset($tournaments[$tournamentId])) {
                return $tournaments[$tournamentId]['token'];
            }

            return null;
        }

        return null;
    }

    /**
     * Extract tournament ID from request URI.
     *
     * @param string $uri Request URI
     * @return int|null Tournament ID or null if not found
     */
    private static function getTournamentIdFromUri(string $uri): ?int
    {
        // Match patterns like /tournament/123 or /api/tournaments/123/...
        if (preg_match('#/tournaments?/(\d+)#', $uri, $matches)) {
            return (int)$matches[1];
        }
        return null;
    }

    /**
     * Get the authenticated tournament (call after check()).
     */
    public static function getTournament(): ?Tournament
    {
        global $authenticatedTournament;
        return $authenticatedTournament ?? null;
    }
}
