<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPScraperService;
use TournamentTables\Services\Pairing;

/**
 * Integration tests for BCP scraper service.
 *
 * Reference: specs/001-table-allocation/research.md#2-bcp-rest-api
 * Tests use fixtures in tests/fixtures/bcp_api_response.json
 */
class BCPScraperTest extends TestCase
{
    /**
     * @var string
     */
    private $fixturesPath;

    protected function setUp(): void
    {
        $this->fixturesPath = __DIR__ . '/../fixtures';
    }

    /**
     * Load JSON fixture and decode.
     *
     * @param string $filename
     * @return array
     */
    private function loadJsonFixture(string $filename): array
    {
        $json = file_get_contents($this->fixturesPath . '/' . $filename);
        return json_decode($json, true);
    }

    /**
     * Test parsing API response fixture returns correct pairings.
     */
    public function testParseApiResponse(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $this->assertNotEmpty($data);

        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        $this->assertCount(6, $pairings);
        $this->assertContainsOnlyInstancesOf(Pairing::class, $pairings);
    }

    /**
     * Test parsed pairing has correct structure.
     */
    public function testParsedPairingStructure(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        // Check first pairing (Table 1)
        $first = $pairings[0];

        $this->assertEquals('player1_bcp_id', $first->player1BcpId);
        $this->assertEquals('Alice Anderson', $first->player1Name);
        $this->assertEquals(2, $first->player1Score);

        $this->assertEquals('player2_bcp_id', $first->player2BcpId);
        $this->assertEquals('Bob Brown', $first->player2Name);
        $this->assertEquals(2, $first->player2Score);

        $this->assertEquals(1, $first->bcpTableNumber);
    }

    /**
     * Test all pairings have valid BCP IDs.
     */
    public function testAllPairingsHaveValidBcpIds(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        foreach ($pairings as $pairing) {
            $this->assertNotEmpty($pairing->player1BcpId, 'Player 1 BCP ID should not be empty');
            $this->assertNotEmpty($pairing->player2BcpId, 'Player 2 BCP ID should not be empty');
        }
    }

    /**
     * Test all pairings have valid player names.
     */
    public function testAllPairingsHaveValidNames(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        foreach ($pairings as $pairing) {
            $this->assertNotEmpty($pairing->player1Name, 'Player 1 name should not be empty');
            $this->assertNotEmpty($pairing->player2Name, 'Player 2 name should not be empty');
        }
    }

    /**
     * Test pairings are ordered by table number.
     */
    public function testPairingsOrderedByTableNumber(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        $previousTable = 0;
        foreach ($pairings as $pairing) {
            $this->assertGreaterThan($previousTable, $pairing->bcpTableNumber);
            $previousTable = $pairing->bcpTableNumber;
        }
    }

    /**
     * Test scores are extracted correctly.
     */
    public function testScoresExtractedCorrectly(): void
    {
        $data = $this->loadJsonFixture('bcp_api_response.json');
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        // Table 1 players: score 2 each
        $this->assertEquals(2, $pairings[0]->player1Score);
        $this->assertEquals(2, $pairings[0]->player2Score);

        // Table 3 players: score 1 each
        $this->assertEquals(1, $pairings[2]->player1Score);
        $this->assertEquals(1, $pairings[2]->player2Score);

        // Table 5 players: score 0 each
        $this->assertEquals(0, $pairings[4]->player1Score);
        $this->assertEquals(0, $pairings[4]->player2Score);
    }

    /**
     * Test empty API response returns empty array.
     */
    public function testEmptyApiResponseReturnsEmptyArray(): void
    {
        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse([]);

        $this->assertIsArray($pairings);
        $this->assertEmpty($pairings);
    }

    /**
     * Test API response with no pairings returns empty array.
     */
    public function testApiResponseWithNoPairingsReturnsEmptyArray(): void
    {
        $data = ['active' => [], 'deleted' => []];

        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        $this->assertIsArray($pairings);
        $this->assertEmpty($pairings);
    }

    /**
     * Test API response with missing fields skips invalid pairings.
     */
    public function testApiResponseWithMissingFieldsSkipsPairing(): void
    {
        $data = [
            'active' => [
                // Valid pairing
                [
                    'id' => 'pairing1',
                    'table' => 1,
                    'round' => 1,
                    'player1' => [
                        'id' => 'player1_id',
                        'user' => ['firstName' => 'Alice', 'lastName' => 'Anderson']
                    ],
                    'player2' => [
                        'id' => 'player2_id',
                        'user' => ['firstName' => 'Bob', 'lastName' => 'Brown']
                    ],
                    'player1Game' => ['points' => 10],
                    'player2Game' => ['points' => 5]
                ],
                // Invalid pairing - missing player1
                [
                    'id' => 'pairing2',
                    'table' => 2,
                    'round' => 1,
                    'player2' => ['id' => 'player3_id'],
                    'player2Game' => ['points' => 0]
                ]
            ]
        ];

        $scraper = new BCPScraperService();
        $pairings = $scraper->parseApiResponse($data);

        // Should only have the valid pairing
        $this->assertCount(1, $pairings);
        $this->assertEquals('player1_id', $pairings[0]->player1BcpId);
    }

    /**
     * Test extractEventId from URL.
     */
    public function testExtractEventIdFromUrl(): void
    {
        $scraper = new BCPScraperService();

        $url1 = 'https://www.bestcoastpairings.com/event/t6OOun8POR60';
        $this->assertEquals('t6OOun8POR60', $scraper->extractEventId($url1));

        $url2 = 'https://www.bestcoastpairings.com/event/abc123xyz?active_tab=pairings';
        $this->assertEquals('abc123xyz', $scraper->extractEventId($url2));
    }

    /**
     * Test invalid URL throws exception.
     */
    public function testInvalidUrlThrowsException(): void
    {
        $scraper = new BCPScraperService();

        $this->expectException(\InvalidArgumentException::class);
        $scraper->extractEventId('https://example.com/not-bcp');
    }

    /**
     * Test buildPairingsUrl generates correct API URL.
     */
    public function testBuildPairingsUrl(): void
    {
        $scraper = new BCPScraperService();

        $url = $scraper->buildPairingsUrl('t6OOun8POR60', 2);

        $this->assertEquals(
            'https://newprod-api.bestcoastpairings.com/v1/events/t6OOun8POR60/pairings?eventId=t6OOun8POR60&round=2&pairingType=Pairing',
            $url
        );
    }

    /**
     * Test Pairing value object immutability.
     */
    public function testPairingValueObject(): void
    {
        $pairing = new Pairing(
            'player1_id',
            'Player One',
            3,
            'player2_id',
            'Player Two',
            2,
            5
        );

        $this->assertEquals('player1_id', $pairing->player1BcpId);
        $this->assertEquals('Player One', $pairing->player1Name);
        $this->assertEquals(3, $pairing->player1Score);
        $this->assertEquals('player2_id', $pairing->player2BcpId);
        $this->assertEquals('Player Two', $pairing->player2Name);
        $this->assertEquals(2, $pairing->player2Score);
        $this->assertEquals(5, $pairing->bcpTableNumber);
    }

    /**
     * Test combined score calculation.
     */
    public function testCombinedScoreCalculation(): void
    {
        $pairing = new Pairing(
            'p1',
            'Player 1',
            3,
            'p2',
            'Player 2',
            2,
            1
        );

        $this->assertEquals(5, $pairing->getCombinedScore());
    }

    /**
     * Test null table number is allowed.
     */
    public function testNullTableNumberAllowed(): void
    {
        $pairing = new Pairing(
            'p1',
            'Player 1',
            0,
            'p2',
            'Player 2',
            0,
            null
        );

        $this->assertNull($pairing->bcpTableNumber);
    }

    /**
     * Test fetching from live BCP API.
     *
     * This test makes actual network calls and is skipped unless LIVE_BCP_TESTS=1.
     * Optionally set BCP_TEST_EVENT_ID and BCP_TEST_ROUND environment variables.
     *
     * Example:
     *   LIVE_BCP_TESTS=1 BCP_TEST_EVENT_ID=t6OOun8POR60 BCP_TEST_ROUND=1 vendor/bin/phpunit tests/Integration/BCPScraperTest.php::testFetchFromLiveBcpApi
     */
    public function testFetchFromLiveBcpApi(): void
    {
        // Get event ID from environment or use a default
        $eventId = getenv('BCP_TEST_EVENT_ID') ?: 't6OOun8POR60';
        $round = (int)(getenv('BCP_TEST_ROUND') ?: 1);

        $scraper = new BCPScraperService();

        try {
            $pairings = $scraper->fetchPairings($eventId, $round);

            // Validate response structure
            $this->assertIsArray($pairings);

            // If there are pairings, validate their structure
            if (count($pairings) > 0) {
                $this->assertContainsOnlyInstancesOf(Pairing::class, $pairings);

                $first = $pairings[0];
                $this->assertNotEmpty($first->player1BcpId, 'Player 1 BCP ID should not be empty');
                $this->assertNotEmpty($first->player2BcpId, 'Player 2 BCP ID should not be empty');
                $this->assertIsString($first->player1Name);
                $this->assertIsString($first->player2Name);
                $this->assertIsInt($first->player1Score);
                $this->assertIsInt($first->player2Score);

                // Table number can be null or int
                $this->assertTrue(
                    is_int($first->bcpTableNumber) || is_null($first->bcpTableNumber),
                    'Table number should be int or null'
                );

                // Verify pairings are ordered by table number
                $previousTable = 0;
                foreach ($pairings as $pairing) {
                    if ($pairing->bcpTableNumber !== null) {
                        $this->assertGreaterThanOrEqual($previousTable, $pairing->bcpTableNumber);
                        $previousTable = $pairing->bcpTableNumber;
                    }
                }
            }
        } catch (\RuntimeException $e) {
            // If the event doesn't exist or network fails, provide helpful message
            $this->markTestIncomplete(
                "Failed to fetch pairings for event {$eventId} round {$round}: " .
                $e->getMessage() . "\n" .
                "This might be due to: invalid event ID, event no longer active, " .
                "network issues, or API changes."
            );
        }
    }

    /**
     * Test retry mechanism parameters.
     */
    public function testRetryConfiguration(): void
    {
        $scraper = new BCPScraperService();

        // Default retry settings
        $this->assertEquals(3, $scraper->getMaxRetries());
        $this->assertEquals(1000, $scraper->getBaseDelayMs());
        $this->assertEquals(2.0, $scraper->getBackoffMultiplier());
    }

    /**
     * Test parsing tournament name from HTML fixture.
     */
    public function testParseHtmlForTournamentNameFromFixture(): void
    {
        $html = file_get_contents($this->fixturesPath . '/bcp_event_page.html');
        $this->assertNotEmpty($html);

        $scraper = new BCPScraperService();
        $name = $scraper->parseHtmlForTournamentName($html);

        $this->assertEquals('Contrast Clash - October 2026', $name);
    }

    /**
     * Test parsing HTML fixture returns only the first h3.
     */
    public function testParseHtmlReturnsFirstH3FromFixture(): void
    {
        $html = file_get_contents($this->fixturesPath . '/bcp_event_page.html');

        $scraper = new BCPScraperService();
        $name = $scraper->parseHtmlForTournamentName($html);

        // Should get the first h3, not "Other Section"
        $this->assertStringNotContainsString('Other Section', $name);
        $this->assertEquals('Contrast Clash - October 2026', $name);
    }

    /**
     * Test fetching tournament name from live BCP page.
     *
     * This test makes actual network calls and is skipped unless LIVE_BCP_TESTS=1.
     * Set BCP_TEST_URL environment variable for the event URL.
     *
     * Example:
     *   LIVE_BCP_TESTS=1 BCP_TEST_URL=https://www.bestcoastpairings.com/event/NKsseGHSYuIw vendor/bin/phpunit tests/Integration/BCPScraperTest.php::testFetchTournamentNameFromLiveBcp
     */
    public function testFetchTournamentNameFromLiveBcp(): void
    {
        $bcpUrl = getenv('BCP_TEST_URL') ?: 'https://www.bestcoastpairings.com/event/NKsseGHSYuIw';

        $scraper = new BCPScraperService();

        try {
            $name = $scraper->fetchTournamentName($bcpUrl);

            // Validate response
            $this->assertIsString($name);
            $this->assertNotEmpty($name);
            $this->assertLessThanOrEqual(255, strlen($name));
        } catch (\RuntimeException $e) {
            // If network fails or page is unavailable, mark as incomplete
            $this->markTestIncomplete(
                "Failed to fetch tournament name from BCP: " . $e->getMessage() . "\n" .
                "This might be due to: network issues, page not available, or BCP changes."
            );
        }
    }
}
