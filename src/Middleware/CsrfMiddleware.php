<?php

declare(strict_types=1);

namespace TournamentTables\Middleware;

use TournamentTables\Services\CsrfService;

/**
 * CSRF protection middleware.
 *
 * Validates CSRF tokens for state-changing requests from HTML forms.
 * Note: API endpoints using X-Admin-Token are protected by token auth instead.
 *
 * Reference: specs/001-table-allocation/tasks.md#T089
 */
class CsrfMiddleware
{
    /**
     * HTTP methods that require CSRF protection.
     */
    private const PROTECTED_METHODS = ['POST', 'PUT', 'PATCH', 'DELETE'];

    /**
     * Routes that are exempt from CSRF (API endpoints with token auth).
     */
    private const EXEMPT_ROUTES = [
        '/api/',
    ];

    /**
     * Check if the request passes CSRF validation.
     *
     * @param string $method HTTP method
     * @param string $uri Request URI
     * @param array|null $body Request body
     * @return true|string Returns true if valid, error message otherwise
     */
    public static function check(string $method, string $uri, ?array $body)
    {
        // Only check state-changing methods
        if (!in_array($method, self::PROTECTED_METHODS, true)) {
            return true;
        }

        // Skip CSRF for API routes (they use token authentication)
        foreach (self::EXEMPT_ROUTES as $exempt) {
            if (strpos($uri, $exempt) === 0) {
                return true;
            }
        }

        // Get and validate token
        $token = CsrfService::getTokenFromRequest($body);
        if (!CsrfService::validateToken($token)) {
            return 'Invalid or missing CSRF token';
        }

        return true;
    }
}
