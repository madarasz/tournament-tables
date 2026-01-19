<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Contract;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPApiService;

/**
 * Contract tests for BCP API integration.
 *
 * These tests verify that the BCP REST API remains compatible
 * with our API service by validating responses against JSON schemas
 * that define the minimal fields we depend on.
 *
 * IMPORTANT: These tests require network access to BCP servers.
 * They should be run sparingly to avoid excessive load on BCP.
 */
class BCPContractTest extends TestCase
{
    /** @var string Hardcoded BCP event URL for contract testing */
    const BCP_EVENT_URL = 'https://www.bestcoastpairings.com/event/NKsseGHSYuIw';

    /** @var string BCP event ID extracted from URL */
    const BCP_EVENT_ID = 'NKsseGHSYuIw';

    /** @var array|null Cached API response for round 1 pairings */
    private static $pairingsData = null;

    /** @var array|null Cached API response for event details */
    private static $eventData = null;

    /** @var array|null Cached API response for player placings (total scores) */
    private static $placingsData = null;

    /** @var BCPApiService */
    private $apiService;

    /** @var bool Track if fetch was successful */
    private static $fetchSuccess = false;

    /** @var string|null Error message if fetch failed */
    private static $fetchError = null;

    /** @var string|false Original mock URL (saved for restoration) */
    private $originalMockUrl;

    /**
     * Fetch BCP data once before all tests in this class.
     *
     * This minimizes network calls to BCP by caching responses.
     */
    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        try {
            self::fetchBcpPairingsApi();
            self::fetchBcpEventApi();
            self::fetchBcpPlacingsApi();
            self::$fetchSuccess = true;
        } catch (\Exception $e) {
            self::$fetchError = $e->getMessage();
            self::$fetchSuccess = false;
        }
    }

    /**
     * Fetch and cache the BCP pairings API response for round 1.
     */
    private static function fetchBcpPairingsApi(): void
    {
        $url = 'https://newprod-api.bestcoastpairings.com/v1/events/' .
               self::BCP_EVENT_ID . '/pairings?eventId=' . self::BCP_EVENT_ID .
               '&round=1&pairingType=Pairing';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'client-id: web-app',
                    'env: bcp',
                    'content-type: application/json'
                ]),
                'timeout' => 15
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Failed to fetch BCP pairings API');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from BCP API: ' . json_last_error_msg());
        }

        self::$pairingsData = $data;
    }

    /**
     * Fetch and cache the BCP event details API response.
     */
    private static function fetchBcpEventApi(): void
    {
        $url = 'https://newprod-api.bestcoastpairings.com/v1/events/' . self::BCP_EVENT_ID;

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'client-id: web-app',
                    'env: bcp',
                    'content-type: application/json'
                ]),
                'timeout' => 15
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Failed to fetch BCP event API');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from BCP event API: ' . json_last_error_msg());
        }

        self::$eventData = $data;
    }

    /**
     * Fetch and cache the BCP player placings API response.
     */
    private static function fetchBcpPlacingsApi(): void
    {
        $url = 'https://newprod-api.bestcoastpairings.com/v1/events/' .
               self::BCP_EVENT_ID . '/players?placings=true';

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'client-id: web-app',
                    'env: bcp',
                    'content-type: application/json'
                ]),
                'timeout' => 15
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Failed to fetch BCP placings API');
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid JSON from BCP placings API: ' . json_last_error_msg());
        }

        self::$placingsData = $data;
    }

    protected function setUp(): void
    {
        if (!self::$fetchSuccess) {
            $this->markTestSkipped(
                'BCP data fetch failed: ' . (self::$fetchError ?? 'Unknown error') .
                '. This may be due to network issues or BCP availability.'
            );
        }

        // Save and clear mock URL to ensure we test against real BCP API
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
    // Schema Validation Tests
    // -------------------------------------------------------------------------

    /**
     * Test that event API response matches our minimal schema.
     *
     * Schema defines only fields used by BCPApiService::fetchTournamentName().
     */
    public function testEventApiMatchesSchema(): void
    {
        $this->assertNotNull(self::$eventData, 'Event API data should be cached');

        $schema = $this->loadSchema('event.json');
        $data = json_decode(json_encode(self::$eventData));

        $validator = new Validator();
        $validator->validate($data, $schema);

        $this->assertTrue(
            $validator->isValid(),
            'Event API response does not match schema: ' . $this->formatValidationErrors($validator)
        );
    }

    /**
     * Test that pairings API response matches our minimal schema.
     *
     * Schema defines only fields used by BCPApiService::parseApiResponse().
     */
    public function testPairingsApiMatchesSchema(): void
    {
        $this->assertNotNull(self::$pairingsData, 'Pairings API data should be cached');

        $schema = $this->loadSchema('pairings.json');
        $data = json_decode(json_encode(self::$pairingsData));

        $validator = new Validator();
        $validator->validate($data, $schema);

        $this->assertTrue(
            $validator->isValid(),
            'Pairings API response does not match schema: ' . $this->formatValidationErrors($validator)
        );
    }

    /**
     * Test that placings API response matches our minimal schema.
     *
     * Schema defines only fields used by BCPApiService::parsePlacingsResponse().
     */
    public function testPlacingsApiMatchesSchema(): void
    {
        $this->assertNotNull(self::$placingsData, 'Placings API data should be cached');

        $schema = $this->loadSchema('placings.json');
        $data = json_decode(json_encode(self::$placingsData));

        $validator = new Validator();
        $validator->validate($data, $schema);

        $this->assertTrue(
            $validator->isValid(),
            'Placings API response does not match schema: ' . $this->formatValidationErrors($validator)
        );
    }

    // -------------------------------------------------------------------------
    // Functional Tests (verify our parsing logic works with real data)
    // -------------------------------------------------------------------------

    /**
     * Test that we can parse pairings from the API response.
     */
    public function testCanParsePairings(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        $this->assertIsArray($pairings, 'Parsed pairings should be an array');
        $this->assertNotEmpty($pairings, 'Should have at least one pairing');
    }

    /**
     * Test that each pairing has valid player 1 data after parsing.
     */
    public function testParsedPairingsHaveValidPlayer1Data(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $index => $pairing) {
            $this->assertNotEmpty(
                $pairing->player1BcpId,
                "Pairing $index should have player1 BCP ID"
            );
            $this->assertNotEmpty(
                $pairing->player1Name,
                "Pairing $index should have player1 name"
            );
            $this->assertIsInt(
                $pairing->player1Score,
                "Pairing $index should have player1 score as int"
            );
        }
    }

    /**
     * Test that each pairing has valid player 2 data after parsing.
     */
    public function testParsedPairingsHaveValidPlayer2Data(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $index => $pairing) {
            $this->assertNotEmpty(
                $pairing->player2BcpId,
                "Pairing $index should have player2 BCP ID"
            );
            $this->assertNotEmpty(
                $pairing->player2Name,
                "Pairing $index should have player2 name"
            );
            $this->assertIsInt(
                $pairing->player2Score,
                "Pairing $index should have player2 score as int"
            );
        }
    }

    /**
     * Test that we can parse total scores from the cached placings response.
     */
    public function testCanParseTotalScores(): void
    {
        $scores = $this->parsePlacingsData(self::$placingsData);

        $this->assertIsArray($scores, 'Parsed scores should be an array');
        $this->assertNotEmpty($scores, 'Should have at least one player score');
    }

    /**
     * Test that parsed total scores are non-negative integers.
     */
    public function testParsedTotalScoresAreValid(): void
    {
        $scores = $this->parsePlacingsData(self::$placingsData);

        foreach ($scores as $playerId => $score) {
            $this->assertIsString($playerId, 'Player ID key should be string');
            $this->assertIsInt($score, 'Score should be integer');
            $this->assertGreaterThanOrEqual(0, $score, 'Score should be non-negative');
        }
    }

    // -------------------------------------------------------------------------
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Load a JSON schema file.
     *
     * @param string $filename Schema filename (relative to schemas directory)
     * @return object Decoded schema object
     */
    private function loadSchema(string $filename): object
    {
        $path = __DIR__ . '/schemas/' . $filename;
        $this->assertFileExists($path, "Schema file not found: $filename");

        $content = file_get_contents($path);
        $schema = json_decode($content);

        $this->assertNotNull($schema, "Failed to parse schema: $filename");

        return $schema;
    }

    /**
     * Format validation errors into a readable string.
     *
     * @param Validator $validator The validator with errors
     * @return string Formatted error messages
     */
    private function formatValidationErrors(Validator $validator): string
    {
        $errors = [];
        foreach ($validator->getErrors() as $error) {
            $path = $error['property'] ? "[{$error['property']}] " : '';
            $errors[] = $path . $error['message'];
        }
        return implode('; ', $errors);
    }

    /**
     * Parse placings data to extract total scores.
     *
     * Mirrors BCPApiService::parsePlacingsResponse() to avoid additional API calls.
     *
     * @param array $data Cached placings API response
     * @return array<string, int> Map of BCP player ID to total score
     */
    private function parsePlacingsData(array $data): array
    {
        $scores = [];
        $players = $data['active'] ?? $data;

        if (!is_array($players)) {
            return $scores;
        }

        foreach ($players as $player) {
            $bcpPlayerId = $player['id'] ?? null;
            if ($bcpPlayerId === null) {
                continue;
            }

            $totalScore = 0;
            $overallMetrics = $player['overall_metrics'] ?? [];

            foreach ($overallMetrics as $metric) {
                if (isset($metric['name']) && $metric['name'] === 'Overall Score') {
                    $totalScore = (int) ($metric['value'] ?? 0);
                    break;
                }
            }

            $scores[$bcpPlayerId] = $totalScore;
        }

        return $scores;
    }
}
