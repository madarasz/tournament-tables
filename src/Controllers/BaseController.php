<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

/**
 * Base controller with common functionality.
 */
abstract class BaseController
{
    /**
     * Send a JSON response.
     *
     * @param mixed $data Data to encode
     * @param int $statusCode HTTP status code
     */
    protected function json($data, int $statusCode = 200): void
    {
        http_response_code($statusCode);
        header('Content-Type: application/json');
        echo json_encode($data);
    }

    /**
     * Send a success response.
     *
     * @param mixed $data Response data
     * @param int $statusCode HTTP status code
     */
    protected function success($data, int $statusCode = 200): void
    {
        $this->json($data, $statusCode);
    }

    /**
     * Send an error response.
     *
     * @param string $error Error code
     * @param string $message Error message
     * @param int $statusCode HTTP status code
     * @param array $fields Optional field-level errors
     */
    protected function error(string $error, string $message, int $statusCode = 400, array $fields = []): void
    {
        $response = [
            'error' => $error,
            'message' => $message,
        ];

        if (!empty($fields)) {
            $response['fields'] = $fields;
        }

        $this->json($response, $statusCode);
    }

    /**
     * Send a validation error response.
     *
     * @param array $fields Field-level validation errors
     */
    protected function validationError(array $fields): void
    {
        $this->error('validation_error', 'Invalid input', 400, $fields);
    }

    /**
     * Send a not found error.
     *
     * @param string $resource Resource type that was not found
     */
    protected function notFound(string $resource = 'Resource'): void
    {
        $this->error('not_found', "{$resource} not found", 404);
    }

    /**
     * Send an unauthorized error.
     *
     * @param string $message Error message
     */
    protected function unauthorized(string $message = 'Invalid or missing authentication'): void
    {
        $this->error('unauthorized', $message, 401);
    }

    /**
     * Set a cookie with secure defaults.
     *
     * @param string $name Cookie name
     * @param string $value Cookie value
     * @param int $maxAge Max age in seconds (default 30 days)
     * @param bool $httpOnly Whether to set HttpOnly flag (default true)
     */
    protected function setCookie(string $name, string $value, int $maxAge = 2592000, bool $httpOnly = true): void
    {
        $expires = time() + $maxAge;
        $path = '/';
        $domain = '';
        $secure = $this->isHttps();

        // PHP 7.1 compatible setcookie call
        // SameSite is added via path workaround for PHP < 7.3
        setcookie($name, $value, $expires, $path . '; SameSite=Lax', $domain, $secure, $httpOnly);
    }

    /**
     * Get a cookie value.
     *
     * @param string $name Cookie name
     * @return string|null Cookie value or null
     */
    protected function getCookie(string $name): ?string
    {
        return $_COOKIE[$name] ?? null;
    }

    /**
     * Clear a cookie.
     *
     * @param string $name Cookie name
     */
    protected function clearCookie(string $name): void
    {
        $expires = time() - 3600;
        $path = '/';
        $domain = '';
        $secure = $this->isHttps();
        $httpOnly = true;

        // PHP 7.1 compatible setcookie call
        // SameSite is added via path workaround for PHP < 7.3
        setcookie($name, '', $expires, $path . '; SameSite=Lax', $domain, $secure, $httpOnly);
    }

    /**
     * Get the multi-token cookie as an array of tournaments.
     *
     * @return array Tournament map: [tournamentId => ['token' => string, 'name' => string, 'lastAccessed' => int]]
     */
    protected function getMultiTokenCookie(): array
    {
        $cookieValue = $_COOKIE['admin_token'] ?? null;
        if ($cookieValue === null) {
            return [];
        }

        $decoded = json_decode($cookieValue, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Failed to decode admin_token cookie: ' . json_last_error_msg());
            return [];
        }

        if (!is_array($decoded) || !isset($decoded['tournaments']) || !is_array($decoded['tournaments'])) {
            return [];
        }

        return $decoded['tournaments'];
    }

    /**
     * Set the multi-token cookie with an array of tournaments.
     *
     * @param array $tournaments Tournament map
     */
    protected function setMultiTokenCookie(array $tournaments): void
    {
        $cookieValue = json_encode(['tournaments' => $tournaments]);

        if (strlen($cookieValue) > 4096) {
            error_log('Cookie size exceeds 4KB limit. Evicting additional tournaments.');
            // Evict until under limit
            while (strlen($cookieValue) > 4096 && count($tournaments) > 1) {
                $this->evictLRUTournament($tournaments);
                $cookieValue = json_encode(['tournaments' => $tournaments]);
            }
        }

        // HttpOnly disabled so JavaScript can read the token for X-Admin-Token header
        // This is acceptable because each token only grants access to one specific tournament
        $this->setCookie('admin_token', $cookieValue, 30 * 24 * 60 * 60, false);
    }

    /**
     * Add or update a tournament token in the multi-token cookie.
     *
     * @param int $tournamentId Tournament ID
     * @param string $token Admin token
     * @param string $name Tournament name
     */
    protected function addTournamentToken(int $tournamentId, string $token, string $name): void
    {
        $tournaments = $this->getMultiTokenCookie();

        // LRU eviction if limit reached and tournament is new
        if (count($tournaments) >= 20 && !isset($tournaments[$tournamentId])) {
            $this->evictLRUTournament($tournaments);
        }

        $tournaments[$tournamentId] = [
            'token' => $token,
            'name' => $name,
            'lastAccessed' => time()
        ];

        $this->setMultiTokenCookie($tournaments);
    }

    /**
     * Update the last accessed timestamp for a tournament.
     *
     * @param int $tournamentId Tournament ID
     */
    protected function updateLastAccessed(int $tournamentId): void
    {
        $tournaments = $this->getMultiTokenCookie();

        if (isset($tournaments[$tournamentId])) {
            $tournaments[$tournamentId]['lastAccessed'] = time();
            $this->setMultiTokenCookie($tournaments);
        }
    }

    /**
     * Evict the least-recently-used tournament from the tournament map.
     *
     * @param array $tournaments Tournament map (passed by reference)
     */
    private function evictLRUTournament(array &$tournaments): void
    {
        if (empty($tournaments)) {
            return;
        }

        $oldestId = null;
        $oldestTime = PHP_INT_MAX;

        foreach ($tournaments as $id => $data) {
            $lastAccessed = isset($data['lastAccessed']) ? (int) $data['lastAccessed'] : 0;
            if ($lastAccessed < $oldestTime) {
                $oldestTime = $lastAccessed;
                $oldestId = $id;
            }
        }

        if ($oldestId !== null) {
            unset($tournaments[$oldestId]);
        }
    }

    /**
     * Get a request header.
     *
     * @param string $name Header name
     * @return string|null Header value or null
     */
    protected function getHeader(string $name): ?string
    {
        // Convert header name to $_SERVER key format
        $key = 'HTTP_' . strtoupper(str_replace('-', '_', $name));
        return $_SERVER[$key] ?? null;
    }

    /**
     * Get the current authenticated tournament from middleware.
     *
     * @return \TournamentTables\Models\Tournament|null
     */
    protected function getAuthenticatedTournament(): ?\TournamentTables\Models\Tournament
    {
        global $authenticatedTournament;
        return $authenticatedTournament ?? null;
    }

    /**
     * Verify that the authenticated tournament matches the requested tournament ID.
     *
     * Sends an unauthorized response and returns false if validation fails.
     *
     * @param int $tournamentId Tournament ID to verify against
     * @param string $message Optional custom error message
     * @return bool True if authorized, false if not (response already sent)
     */
    protected function verifyTournamentAuth(int $tournamentId, string $message = 'Token does not match this tournament'): bool
    {
        $authTournament = \TournamentTables\Middleware\AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $tournamentId) {
            $this->unauthorized($message);
            return false;
        }
        return true;
    }

    /**
     * Verify auth and get tournament, sending appropriate error responses if failed.
     *
     * @param int $tournamentId Tournament ID to look up
     * @return \TournamentTables\Models\Tournament|null Tournament if found and authorized, null otherwise (response already sent)
     */
    protected function getTournamentOrFail(int $tournamentId): ?\TournamentTables\Models\Tournament
    {
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return null;
        }

        $tournament = \TournamentTables\Models\Tournament::find($tournamentId);
        if ($tournament === null) {
            $this->notFound('Tournament');
            return null;
        }

        return $tournament;
    }

    /**
     * Verify auth and get round, sending appropriate error responses if failed.
     *
     * @param int $tournamentId Tournament ID
     * @param int $roundNumber Round number to look up
     * @return \TournamentTables\Models\Round|null Round if found and authorized, null otherwise (response already sent)
     */
    protected function getRoundOrFail(int $tournamentId, int $roundNumber): ?\TournamentTables\Models\Round
    {
        if (!$this->verifyTournamentAuth($tournamentId)) {
            return null;
        }

        $round = \TournamentTables\Models\Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            $this->notFound('Round');
            return null;
        }

        return $round;
    }

    /**
     * Ensure session is started.
     */
    protected function ensureSession(): void
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
    protected function redirect(string $url): void
    {
        header('Location: ' . $url);
        exit;
    }

    /**
     * Render a view template directly to output.
     *
     * @param string $view View name (relative to Views directory)
     * @param array $data Data to pass to view
     */
    protected function renderView(string $view, array $data = []): void
    {
        // Sanitize view name to prevent path traversal
        $view = str_replace(['..', '/', '\\'], '', basename($view));
        extract($data);

        $viewPath = __DIR__ . '/../Views/' . $view . '.php';
        if (file_exists($viewPath)) {
            require $viewPath;
        } else {
            http_response_code(500);
            echo "View not found: {$view}";
        }
    }

    /**
     * Convert an array of model objects to arrays using toArray().
     *
     * @param array $items Array of objects with toArray() method
     * @return array Array of arrays
     */
    protected function toArrayMap(array $items): array
    {
        return array_map(function ($item) {
            return $item->toArray();
        }, $items);
    }

    /**
     * Check if the current request is using HTTPS.
     *
     * @return bool True if HTTPS, false otherwise
     */
    protected function isHttps(): bool
    {
        return isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
    }
}
