<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Service for validating and extracting information from BCP URLs.
 *
 * Centralizes the BCP URL pattern to avoid duplication across services.
 */
class BcpUrlValidator
{
    /**
     * Pattern to match BCP event URLs.
     *
     * Captures:
     * - Group 1: Event ID (alphanumeric characters)
     *
     * Matches URLs like:
     * - https://www.bestcoastpairings.com/event/ABC123
     * - https://www.bestcoastpairings.com/event/ABC123/
     * - https://www.bestcoastpairings.com/event/ABC123?param=value
     */
    public const BCP_URL_PATTERN = '#^https://www\.bestcoastpairings\.com/event/([A-Za-z0-9]+)(?:[/?]|$)#';

    /**
     * Check if a URL is a valid BCP event URL.
     *
     * @param string $url URL to validate
     * @return bool True if valid BCP URL
     */
    public static function isValid(string $url): bool
    {
        $url = trim($url);

        if (empty($url)) {
            return false;
        }

        return preg_match(self::BCP_URL_PATTERN, $url) === 1;
    }

    /**
     * Extract event ID from a BCP URL.
     *
     * @param string $url BCP event URL
     * @return string|null Event ID or null if URL is invalid
     */
    public static function extractEventId(string $url): ?string
    {
        $url = trim($url);

        if (preg_match(self::BCP_URL_PATTERN, $url, $matches)) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Validate BCP URL and return validation result.
     *
     * @param string $url URL to validate
     * @return array{valid: bool, eventId?: string, error?: string}
     */
    public static function validate(string $url): array
    {
        $url = trim($url);

        if (empty($url)) {
            return ['valid' => false, 'error' => 'BCP URL is required'];
        }

        // Check it starts with https://
        if (strpos($url, 'https://') !== 0) {
            return ['valid' => false, 'error' => 'URL must use HTTPS'];
        }

        // Check domain
        if (strpos($url, 'bestcoastpairings.com') === false) {
            return ['valid' => false, 'error' => 'URL must be from bestcoastpairings.com'];
        }

        // Extract event ID
        $eventId = self::extractEventId($url);
        if ($eventId === null) {
            return [
                'valid' => false,
                'error' => 'Invalid BCP URL format. Must be https://www.bestcoastpairings.com/event/{event ID}'
            ];
        }

        return ['valid' => true, 'eventId' => $eventId];
    }

    /**
     * Normalize a BCP URL (strip query params, ensure consistent format).
     *
     * @param string $url BCP event URL
     * @return string Normalized URL
     */
    public static function normalize(string $url): string
    {
        $eventId = self::extractEventId($url);

        if ($eventId !== null) {
            return 'https://www.bestcoastpairings.com/event/' . $eventId;
        }

        return $url;
    }
}
