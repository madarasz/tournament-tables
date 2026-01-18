<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPScraperService;

/**
 * Unit tests for BCPScraperService HTML parsing functionality.
 *
 * Tests the parseHtmlForTournamentName() method with various HTML inputs.
 */
class BCPScraperServiceTest extends TestCase
{
    /** @var BCPScraperService */
    private $scraper;

    protected function setUp(): void
    {
        $this->scraper = new BCPScraperService();
    }

    /**
     * Test parsing valid HTML with h3 element returns tournament name.
     */
    public function testParseHtmlForTournamentNameSuccess(): void
    {
        $html = '<html><body><h3>Contrast Clash - October</h3><p>Some content</p></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        $this->assertEquals('Contrast Clash - October', $name);
    }

    /**
     * Test parsing HTML with multiple h3 elements returns first one.
     */
    public function testParseHtmlForTournamentNameReturnsFirstH3(): void
    {
        $html = '<html><body><h3>First Tournament</h3><h3>Second Tournament</h3></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        $this->assertEquals('First Tournament', $name);
    }

    /**
     * Test parsing HTML with whitespace around name trims it.
     */
    public function testParseHtmlForTournamentNameTrimsWhitespace(): void
    {
        $html = '<html><body><h3>   Kill Team GT 2026   </h3></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        $this->assertEquals('Kill Team GT 2026', $name);
    }

    /**
     * Test parsing HTML without h3 element throws exception.
     */
    public function testParseHtmlForTournamentNameMissingH3(): void
    {
        $html = '<html><body><h1>Page Title</h1><p>No h3 here</p></body></html>';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tournament name not found on BCP page');

        $this->scraper->parseHtmlForTournamentName($html);
    }

    /**
     * Test parsing HTML with empty h3 element throws exception.
     */
    public function testParseHtmlForTournamentNameEmptyName(): void
    {
        $html = '<html><body><h3></h3></body></html>';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tournament name not found on BCP page');

        $this->scraper->parseHtmlForTournamentName($html);
    }

    /**
     * Test parsing HTML with whitespace-only h3 element throws exception.
     */
    public function testParseHtmlForTournamentNameWhitespaceOnlyName(): void
    {
        $html = '<html><body><h3>   </h3></body></html>';

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Tournament name not found on BCP page');

        $this->scraper->parseHtmlForTournamentName($html);
    }

    /**
     * Test parsing HTML with name exceeding 255 chars truncates it.
     */
    public function testParseHtmlForTournamentNameTooLong(): void
    {
        $longName = str_repeat('A', 300);
        $html = "<html><body><h3>{$longName}</h3></body></html>";

        $name = $this->scraper->parseHtmlForTournamentName($html);

        // Should be truncated to 252 chars + "..."
        $this->assertEquals(255, strlen($name));
        $this->assertStringEndsWith('...', $name);
    }

    /**
     * Test parsing HTML with special characters sanitizes them.
     */
    public function testParseHtmlForTournamentNameSanitizesHtml(): void
    {
        $html = '<html><body><h3>Tournament <script>alert("xss")</script> Name</h3></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        // Script content should be included as text (DOMDocument extracts textContent)
        // but HTML entities should be escaped
        $this->assertStringNotContainsString('<', $name);
        $this->assertStringNotContainsString('>', $name);
    }

    /**
     * Test parsing HTML with HTML entities decodes them.
     */
    public function testParseHtmlForTournamentNameDecodesEntities(): void
    {
        $html = '<html><body><h3>Tournament &amp; Event</h3></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        // DOMDocument decodes entities, then htmlspecialchars re-encodes &
        $this->assertEquals('Tournament &amp; Event', $name);
    }

    /**
     * Test parsing malformed HTML still extracts name.
     */
    public function testParseHtmlForTournamentNameHandlesMalformedHtml(): void
    {
        // Unclosed tags and missing elements
        $html = '<h3>Malformed Tournament<div>Content';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        $this->assertStringContainsString('Malformed Tournament', $name);
    }

    /**
     * Test parsing HTML with nested elements in h3.
     */
    public function testParseHtmlForTournamentNameWithNestedElements(): void
    {
        $html = '<html><body><h3><span>Kill Team</span> <strong>GT</strong></h3></body></html>';

        $name = $this->scraper->parseHtmlForTournamentName($html);

        $this->assertEquals('Kill Team GT', $name);
    }
}
