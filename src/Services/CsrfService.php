<?php

declare(strict_types=1);

namespace KTTables\Services;

/**
 * CSRF protection service.
 *
 * Implements CSRF token generation and validation for form submissions.
 * Reference: specs/001-table-allocation/tasks.md#T089
 */
class CsrfService
{
    private const TOKEN_NAME = 'csrf_token';
    private const TOKEN_LENGTH = 32;
    private const TOKEN_LIFETIME = 3600; // 1 hour

    /**
     * Generate a new CSRF token and store in session.
     *
     * @return string The generated token
     */
    public static function generateToken(): string
    {
        self::ensureSession();

        $token = bin2hex(random_bytes(self::TOKEN_LENGTH));
        $_SESSION[self::TOKEN_NAME] = [
            'value' => $token,
            'expires' => time() + self::TOKEN_LIFETIME,
        ];

        return $token;
    }

    /**
     * Get the current CSRF token, generating one if needed.
     *
     * @return string The CSRF token
     */
    public static function getToken(): string
    {
        self::ensureSession();

        // Check if token exists and is still valid
        if (isset($_SESSION[self::TOKEN_NAME])) {
            $tokenData = $_SESSION[self::TOKEN_NAME];
            if ($tokenData['expires'] > time()) {
                return $tokenData['value'];
            }
        }

        // Generate new token
        return self::generateToken();
    }

    /**
     * Validate a CSRF token.
     *
     * @param string|null $token Token to validate
     * @return bool True if token is valid
     */
    public static function validateToken(?string $token): bool
    {
        if ($token === null || $token === '') {
            return false;
        }

        self::ensureSession();

        if (!isset($_SESSION[self::TOKEN_NAME])) {
            return false;
        }

        $tokenData = $_SESSION[self::TOKEN_NAME];

        // Check expiry
        if ($tokenData['expires'] < time()) {
            unset($_SESSION[self::TOKEN_NAME]);
            return false;
        }

        // Constant-time comparison to prevent timing attacks
        return hash_equals($tokenData['value'], $token);
    }

    /**
     * Get token from request (header or body).
     *
     * @param array|null $body Request body
     * @return string|null Token if found
     */
    public static function getTokenFromRequest(?array $body): ?string
    {
        // Check X-CSRF-Token header first (for AJAX/HTMX requests)
        if (isset($_SERVER['HTTP_X_CSRF_TOKEN'])) {
            return $_SERVER['HTTP_X_CSRF_TOKEN'];
        }

        // Check request body
        if (isset($body['_csrf_token'])) {
            return $body['_csrf_token'];
        }

        return null;
    }

    /**
     * Generate HTML hidden input for form.
     *
     * @return string HTML input element
     */
    public static function getHiddenInput(): string
    {
        $token = self::getToken();
        return sprintf(
            '<input type="hidden" name="_csrf_token" value="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Generate meta tag for HTMX/AJAX requests.
     *
     * @return string HTML meta tag
     */
    public static function getMetaTag(): string
    {
        $token = self::getToken();
        return sprintf(
            '<meta name="csrf-token" content="%s">',
            htmlspecialchars($token, ENT_QUOTES, 'UTF-8')
        );
    }

    /**
     * Ensure session is started.
     */
    private static function ensureSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Regenerate token after successful form submission.
     * Call this after processing a protected form.
     */
    public static function regenerateToken(): void
    {
        self::ensureSession();
        unset($_SESSION[self::TOKEN_NAME]);
        self::generateToken();
    }
}
