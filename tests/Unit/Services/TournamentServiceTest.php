<?php

declare(strict_types=1);

namespace KTTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use KTTables\Services\TournamentService;

/**
 * Tests for TournamentService.
 *
 * Reference: specs/001-table-allocation/tasks.md T022
 */
class TournamentServiceTest extends TestCase
{
    private TournamentService $service;

    protected function setUp(): void
    {
        $this->service = new TournamentService();
    }

    // ========== BCP URL Validation Tests ==========

    public function testValidateBcpUrlAcceptsValidUrl(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/t6OOun8POR60';
        $result = $this->service->validateBcpUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertEquals('t6OOun8POR60', $result['eventId']);
    }

    public function testValidateBcpUrlRejectsHttpUrl(): void
    {
        $url = 'http://www.bestcoastpairings.com/event/t6OOun8POR60';
        $result = $this->service->validateBcpUrl($url);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('HTTPS', $result['error']);
    }

    public function testValidateBcpUrlRejectsWrongDomain(): void
    {
        $url = 'https://www.example.com/event/t6OOun8POR60';
        $result = $this->service->validateBcpUrl($url);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('bestcoastpairings.com', $result['error']);
    }

    public function testValidateBcpUrlRejectsUrlWithoutEventId(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/';
        $result = $this->service->validateBcpUrl($url);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('event ID', $result['error']);
    }

    public function testValidateBcpUrlRejectsWrongPath(): void
    {
        $url = 'https://www.bestcoastpairings.com/events/t6OOun8POR60';
        $result = $this->service->validateBcpUrl($url);

        $this->assertFalse($result['valid']);
    }

    public function testValidateBcpUrlExtractsEventIdCorrectly(): void
    {
        $testCases = [
            'https://www.bestcoastpairings.com/event/abc123' => 'abc123',
            'https://www.bestcoastpairings.com/event/ABC123XYZ' => 'ABC123XYZ',
            'https://www.bestcoastpairings.com/event/a1b2c3d4e5f6' => 'a1b2c3d4e5f6',
        ];

        foreach ($testCases as $url => $expectedId) {
            $result = $this->service->validateBcpUrl($url);
            $this->assertTrue($result['valid'], "URL should be valid: {$url}");
            $this->assertEquals($expectedId, $result['eventId']);
        }
    }

    public function testValidateBcpUrlHandlesTrailingSlash(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/t6OOun8POR60/';
        $result = $this->service->validateBcpUrl($url);

        // Should still work with trailing slash
        $this->assertTrue($result['valid']);
        $this->assertEquals('t6OOun8POR60', $result['eventId']);
    }

    public function testValidateBcpUrlRejectsUrlWithQueryParams(): void
    {
        // Query params should be stripped for storage but URL still validates
        $url = 'https://www.bestcoastpairings.com/event/t6OOun8POR60?active_tab=pairings';
        $result = $this->service->validateBcpUrl($url);

        $this->assertTrue($result['valid']);
        $this->assertEquals('t6OOun8POR60', $result['eventId']);
    }

    // ========== Table Count Validation Tests ==========

    public function testValidateTableCountAcceptsValidRange(): void
    {
        $validCounts = [1, 5, 10, 20, 50, 100];

        foreach ($validCounts as $count) {
            $result = $this->service->validateTableCount($count);
            $this->assertTrue($result['valid'], "Count {$count} should be valid");
        }
    }

    public function testValidateTableCountRejectsZero(): void
    {
        $result = $this->service->validateTableCount(0);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('at least 1', $result['error']);
    }

    public function testValidateTableCountRejectsNegative(): void
    {
        $result = $this->service->validateTableCount(-5);

        $this->assertFalse($result['valid']);
    }

    public function testValidateTableCountRejectsOver100(): void
    {
        $result = $this->service->validateTableCount(101);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('100', $result['error']);
    }

    // ========== Tournament Name Validation Tests ==========

    public function testValidateNameAcceptsValidName(): void
    {
        $result = $this->service->validateName('Kill Team GT January 2026');

        $this->assertTrue($result['valid']);
    }

    public function testValidateNameRejectsEmptyString(): void
    {
        $result = $this->service->validateName('');

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('required', $result['error']);
    }

    public function testValidateNameRejectsWhitespaceOnly(): void
    {
        $result = $this->service->validateName('   ');

        $this->assertFalse($result['valid']);
    }

    public function testValidateNameRejectsTooLong(): void
    {
        $longName = str_repeat('a', 256);
        $result = $this->service->validateName($longName);

        $this->assertFalse($result['valid']);
        $this->assertStringContainsString('255', $result['error']);
    }

    public function testValidateNameAccepts255Characters(): void
    {
        $name = str_repeat('a', 255);
        $result = $this->service->validateName($name);

        $this->assertTrue($result['valid']);
    }
}
