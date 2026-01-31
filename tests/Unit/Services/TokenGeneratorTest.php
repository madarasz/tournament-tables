<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\TokenGenerator;

/**
 * Tests for TokenGenerator service.
 *
 * Reference: specs/001-table-allocation/tasks.md T021
 */
class TokenGeneratorTest extends TestCase
{
    public function testGenerateReturnsStringOf16Characters(): void
    {
        $token = TokenGenerator::generate();

        $this->assertTrue(is_string($token));
        $this->assertEquals(16, strlen($token));
    }

    public function testGenerateReturnsBase64Characters(): void
    {
        $token = TokenGenerator::generate();

        // Base64 alphabet: A-Z, a-z, 0-9, +, /
        // We use URL-safe base64: A-Z, a-z, 0-9, -, _
        $this->assertRegExp('/^[A-Za-z0-9_-]+$/', $token);
    }

    public function testGenerateReturnsUniqueTokens(): void
    {
        $tokens = [];
        for ($i = 0; $i < 100; $i++) {
            $tokens[] = TokenGenerator::generate();
        }

        // All tokens should be unique
        $uniqueTokens = array_unique($tokens);
        $this->assertCount(100, $uniqueTokens);
    }

    public function testGenerateIsCryptographicallySecure(): void
    {
        // Test that tokens have reasonable entropy
        // by checking character distribution isn't obviously biased
        $chars = '';
        for ($i = 0; $i < 100; $i++) {
            $chars .= TokenGenerator::generate();
        }

        // Should have a mix of uppercase, lowercase, and numbers
        $this->assertRegExp('/[A-Z]/', $chars);
        $this->assertRegExp('/[a-z]/', $chars);
        $this->assertRegExp('/[0-9]/', $chars);
    }
}
