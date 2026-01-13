<?php

declare(strict_types=1);

namespace KTTables\Services;

/**
 * Secure token generator for admin authentication.
 *
 * Reference: specs/001-table-allocation/plan.md (FR-002)
 */
class TokenGenerator
{
    /**
     * Generate a 16-character URL-safe base64 token.
     */
    public static function generate(): string
    {
        // Generate 12 bytes of random data
        // base64 encoding of 12 bytes = 16 characters
        $bytes = random_bytes(12);

        // Use URL-safe base64 encoding (replace + with -, / with _)
        $token = base64_encode($bytes);
        $token = strtr($token, '+/', '-_');

        // Remove padding (=)
        $token = rtrim($token, '=');

        return $token;
    }
}
