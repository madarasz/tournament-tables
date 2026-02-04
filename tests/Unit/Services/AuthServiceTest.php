<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\AuthService;

/**
 * Tests for AuthService.
 *
 * Reference: specs/001-table-allocation/tasks.md T033
 */
class AuthServiceTest extends TestCase
{
    /** @var AuthService */
    private $service;

    protected function setUp(): void
    {
        $this->service = new AuthService();
    }

    // ========== Token Validation Tests ==========

    public function testValidateTokenRejectsEmptyToken(): void
    {
        $result = $this->service->validateToken('');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('required', strtolower($result['error']));
    }

    public function testValidateTokenRejectsWhitespaceOnlyToken(): void
    {
        $result = $this->service->validateToken('   ');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateTokenRejectsTooShortToken(): void
    {
        $result = $this->service->validateToken('abc123');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('format', strtolower($result['error']));
    }

    public function testValidateTokenRejectsTooLongToken(): void
    {
        $result = $this->service->validateToken('abcdefghijklmnopqrstuvwxyz');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertStringContainsString('format', strtolower($result['error']));
    }

    public function testValidateTokenRequiresExactly16Characters(): void
    {
        // Test 15 characters
        $result15 = $this->service->validateToken('abc123xyz456789');
        $this->assertFalse($result15['valid']);
        $this->assertStringContainsString('format', strtolower($result15['error']));

        // Test 17 characters
        $result17 = $this->service->validateToken('abc123xyz45678901');
        $this->assertFalse($result17['valid']);
        $this->assertStringContainsString('format', strtolower($result17['error']));
    }

    public function testValidateTokenResultContainsExpectedKeys(): void
    {
        $result = $this->service->validateToken('');

        $this->assertArrayHasKey('valid', $result);
        $this->assertTrue(is_bool($result['valid']));
    }

    public function testValidateTokenReturnsErrorKeyOnFailure(): void
    {
        $result = $this->service->validateToken('invalid');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertTrue(is_string($result['error']));
    }
}
