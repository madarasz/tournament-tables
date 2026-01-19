<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPApiService;

/**
 * Unit tests for BCPApiService.
 *
 * Tests the URL building methods.
 * Note: API integration is tested in integration tests.
 */
class BCPApiServiceTest extends TestCase
{
    /** @var BCPApiService */
    private $apiService;

    /** @var string|false */
    private $originalMockUrl;

    protected function setUp(): void
    {
        // Save and clear mock URL to test actual URL building
        $this->originalMockUrl = getenv('BCP_MOCK_API_URL');
        putenv('BCP_MOCK_API_URL');

        $this->apiService = new BCPApiService();
    }

    protected function tearDown(): void
    {
        // Restore original mock URL
        if ($this->originalMockUrl !== false) {
            putenv('BCP_MOCK_API_URL=' . $this->originalMockUrl);
        }
    }

    // -------------------------------------------------------------------------
    // Event URL Building Tests
    // -------------------------------------------------------------------------

    /**
     * Test buildEventUrl generates correct API URL.
     */
    public function testBuildEventUrlGeneratesCorrectUrl(): void
    {
        $url = $this->apiService->buildEventUrl('testEvent123');

        $this->assertEquals(
            'https://newprod-api.bestcoastpairings.com/v1/events/testEvent123',
            $url
        );
    }

    /**
     * Test buildEventUrl with various event IDs.
     */
    public function testBuildEventUrlWithVariousEventIds(): void
    {
        // Short ID
        $this->assertStringEndsWith('/abc', $this->apiService->buildEventUrl('abc'));

        // Long alphanumeric ID
        $longId = 'NKsseGHSYuIw';
        $this->assertStringEndsWith("/{$longId}", $this->apiService->buildEventUrl($longId));

        // Mixed case ID
        $this->assertStringEndsWith('/AbC123xYz', $this->apiService->buildEventUrl('AbC123xYz'));
    }

    // -------------------------------------------------------------------------
    // Pairings URL Building Tests
    // -------------------------------------------------------------------------

    /**
     * Test buildPairingsUrl generates correct API URL.
     */
    public function testBuildPairingsUrlGeneratesCorrectUrl(): void
    {
        $url = $this->apiService->buildPairingsUrl('testEvent123', 1);

        $this->assertEquals(
            'https://newprod-api.bestcoastpairings.com/v1/events/testEvent123/pairings?eventId=testEvent123&round=1&pairingType=Pairing',
            $url
        );
    }

    /**
     * Test buildPairingsUrl with different round numbers.
     */
    public function testBuildPairingsUrlWithDifferentRounds(): void
    {
        $url1 = $this->apiService->buildPairingsUrl('event123', 1);
        $this->assertStringContainsString('round=1', $url1);

        $url2 = $this->apiService->buildPairingsUrl('event123', 3);
        $this->assertStringContainsString('round=3', $url2);

        $url5 = $this->apiService->buildPairingsUrl('event123', 5);
        $this->assertStringContainsString('round=5', $url5);
    }

    // -------------------------------------------------------------------------
    // Event ID Extraction Tests
    // -------------------------------------------------------------------------

    /**
     * Test extractEventId from valid URL.
     */
    public function testExtractEventIdFromValidUrl(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/t6OOun8POR60';
        $this->assertEquals('t6OOun8POR60', $this->apiService->extractEventId($url));
    }

    /**
     * Test extractEventId from URL with query parameters.
     */
    public function testExtractEventIdFromUrlWithQueryParams(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/abc123xyz?active_tab=pairings';
        $this->assertEquals('abc123xyz', $this->apiService->extractEventId($url));
    }

    /**
     * Test extractEventId from URL with trailing slash.
     */
    public function testExtractEventIdFromUrlWithTrailingSlash(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/abc123xyz/';
        $this->assertEquals('abc123xyz', $this->apiService->extractEventId($url));
    }

    /**
     * Test invalid URL throws exception.
     */
    public function testInvalidUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid BCP event URL');

        $this->apiService->extractEventId('https://example.com/not-bcp');
    }

    /**
     * Test wrong domain throws exception.
     */
    public function testWrongDomainThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->apiService->extractEventId('https://www.otherpairings.com/event/abc123');
    }

    /**
     * Test missing event path throws exception.
     */
    public function testMissingEventPathThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->apiService->extractEventId('https://www.bestcoastpairings.com/abc123');
    }

    // -------------------------------------------------------------------------
    // Configuration Tests
    // -------------------------------------------------------------------------

    /**
     * Test default retry configuration.
     */
    public function testDefaultRetryConfiguration(): void
    {
        $this->assertEquals(3, $this->apiService->getMaxRetries());
        $this->assertEquals(1000, $this->apiService->getBaseDelayMs());
        $this->assertEquals(2.0, $this->apiService->getBackoffMultiplier());
    }
}
