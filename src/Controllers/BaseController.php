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
     */
    protected function setCookie(string $name, string $value, int $maxAge = 2592000): void
    {
        $options = [
            'expires' => time() + $maxAge,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // Set secure flag if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $options['secure'] = true;
        }

        setcookie($name, $value, $options);
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
        $options = [
            'expires' => time() - 3600,
            'path' => '/',
            'httponly' => true,
            'samesite' => 'Lax',
        ];

        // Set secure flag if using HTTPS
        if (isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
            $options['secure'] = true;
        }

        setcookie($name, '', $options);
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

        $this->setCookie('admin_token', $cookieValue, 30 * 24 * 60 * 60);
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
}
