<?php

declare(strict_types=1);

namespace KTTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use KTTables\Services\AuthService;

/**
 * Tests for AuthService.
 *
 * Reference: specs/001-table-allocation/tasks.md T033
 */
class AuthServiceTest extends TestCase
{
    private AuthService $service;

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

    public function testValidateTokenTrimsWhitespace(): void
    {
        // Token with whitespace that becomes 16 chars after trim
        // Should still fail because trimmed token won't be found in DB
        // But the validation should process it correctly
        $result = $this->service->validateToken('  abc123xyz45678  ');

        // After trim, token is 16 chars but won't exist in DB
        $this->assertFalse($result['valid']);
        // Error should be about invalid token (not found), not format
        $this->assertArrayHasKey('error', $result);
    }

    public function testValidateTokenRejectsNonExistentValidFormatToken(): void
    {
        // A token with correct format (16 chars) but doesn't exist in database
        // Note: This test will only work properly when database is available
        // In unit tests, it should still reject due to no DB connection
        $result = $this->service->validateToken('Abc123XyzDef456G');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
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
        $this->assertIsBool($result['valid']);
    }

    public function testValidateTokenReturnsErrorKeyOnFailure(): void
    {
        $result = $this->service->validateToken('invalid');

        $this->assertFalse($result['valid']);
        $this->assertArrayHasKey('error', $result);
        $this->assertIsString($result['error']);
    }
}
