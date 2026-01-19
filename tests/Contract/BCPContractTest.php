<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Contract;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPApiService;

/**
 * Contract tests for BCP API integration.
 *
 * These tests verify that the BCP REST API remains compatible
 * with our API service. They make requests to BCP, cache the
 * responses, and run multiple assertions against that cached data.
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

    /** @var BCPApiService */
    private $apiService;

    /** @var bool Track if fetch was successful */
    private static $fetchSuccess = false;

    /** @var string|null Error message if fetch failed */
    private static $fetchError = null;

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

    protected function setUp(): void
    {
        if (!self::$fetchSuccess) {
            $this->markTestSkipped(
                'BCP data fetch failed: ' . (self::$fetchError ?? 'Unknown error') .
                '. This may be due to network issues or BCP availability.'
            );
        }

        $this->apiService = new BCPApiService();
    }

    // -------------------------------------------------------------------------
    // Event Details API Tests (Tournament Name)
    // -------------------------------------------------------------------------

    /**
     * Test that event details API returns a name field.
     *
     * This is the primary field we need for fetching tournament names.
     */
    public function testEventApiReturnsName(): void
    {
        $this->assertNotNull(self::$eventData, 'Event API data should be cached');
        $this->assertArrayHasKey('name', self::$eventData, 'Response should have "name" key');
        $this->assertNotEmpty(self::$eventData['name'], 'Tournament name should not be empty');
        $this->assertIsString(self::$eventData['name'], 'Tournament name should be a string');
    }

    /**
     * Test that event details API returns expected structure.
     */
    public function testEventApiReturnsExpectedFields(): void
    {
        $this->assertArrayHasKey('id', self::$eventData, 'Response should have "id" key');
        $this->assertArrayHasKey('name', self::$eventData, 'Response should have "name" key');
    }

    /**
     * Test that event ID in response matches requested ID.
     */
    public function testEventApiReturnsMatchingId(): void
    {
        $this->assertEquals(
            self::BCP_EVENT_ID,
            self::$eventData['id'],
            'Event ID in response should match requested ID'
        );
    }

    /**
     * Test that tournament name is a reasonable length.
     */
    public function testEventNameHasReasonableLength(): void
    {
        $name = self::$eventData['name'];

        // Tournament names should be between 1 and 255 characters
        $this->assertGreaterThan(0, strlen($name), 'Tournament name should not be empty');
        $this->assertLessThanOrEqual(
            255,
            strlen($name),
            'Tournament name should fit in database VARCHAR(255)'
        );
    }

    /**
     * Test that tournament name contains printable characters.
     */
    public function testEventNameContainsPrintableCharacters(): void
    {
        $name = self::$eventData['name'];

        // Name should contain at least some letters
        $this->assertMatchesRegularExpression(
            '/[a-zA-Z]/',
            $name,
            'Tournament name should contain letters'
        );
    }

    // -------------------------------------------------------------------------
    // Pairings API Structure Tests
    // -------------------------------------------------------------------------

    /**
     * Test that API response has the expected structure with 'active' array.
     */
    public function testApiResponseHasActiveArray(): void
    {
        $this->assertNotNull(self::$pairingsData, 'API data should be cached');
        $this->assertIsArray(self::$pairingsData, 'API response should be an array');
        $this->assertArrayHasKey('active', self::$pairingsData, 'Response should have "active" key');
        $this->assertIsArray(self::$pairingsData['active'], '"active" should be an array');
    }

    /**
     * Test that we can parse pairings from the API response.
     */
    public function testCanParsePairings(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        $this->assertIsArray($pairings, 'Parsed pairings should be an array');
        $this->assertNotEmpty($pairings, 'Should have at least one pairing');
    }

    // -------------------------------------------------------------------------
    // Table Numbers Tests
    // -------------------------------------------------------------------------

    /**
     * Test that pairings have table numbers.
     */
    public function testPairingsHaveTableNumbers(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        $tablesWithNumbers = 0;
        foreach ($pairings as $pairing) {
            if ($pairing->bcpTableNumber !== null) {
                $tablesWithNumbers++;
            }
        }

        $this->assertGreaterThan(
            0,
            $tablesWithNumbers,
            'At least one pairing should have a table number'
        );
    }

    /**
     * Test that table numbers are positive integers.
     */
    public function testTableNumbersArePositiveIntegers(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $pairing) {
            if ($pairing->bcpTableNumber !== null) {
                $this->assertIsInt(
                    $pairing->bcpTableNumber,
                    'Table number should be an integer'
                );
                $this->assertGreaterThan(
                    0,
                    $pairing->bcpTableNumber,
                    'Table number should be positive'
                );
            }
        }
    }

    /**
     * Test that table numbers are sorted in ascending order.
     */
    public function testPairingsAreSortedByTableNumber(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        $prevTableNumber = 0;
        foreach ($pairings as $pairing) {
            if ($pairing->bcpTableNumber !== null) {
                $this->assertGreaterThanOrEqual(
                    $prevTableNumber,
                    $pairing->bcpTableNumber,
                    'Pairings should be sorted by table number'
                );
                $prevTableNumber = $pairing->bcpTableNumber;
            }
        }
    }

    /**
     * Test that table numbers are unique within round 1.
     */
    public function testTableNumbersAreUnique(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        $tableNumbers = [];
        foreach ($pairings as $pairing) {
            if ($pairing->bcpTableNumber !== null) {
                $this->assertNotContains(
                    $pairing->bcpTableNumber,
                    $tableNumbers,
                    "Table number {$pairing->bcpTableNumber} appears more than once"
                );
                $tableNumbers[] = $pairing->bcpTableNumber;
            }
        }
    }

    // -------------------------------------------------------------------------
    // Round 1 Pairings Structure Tests
    // -------------------------------------------------------------------------

    /**
     * Test that each pairing has valid player 1 data.
     */
    public function testPairingsHaveValidPlayer1Data(): void
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
     * Test that each pairing has valid player 2 data.
     */
    public function testPairingsHaveValidPlayer2Data(): void
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
     * Test that player names are reasonable (not IDs or garbage).
     */
    public function testPlayerNamesAreReasonable(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $pairing) {
            // Names should contain letters (not just numbers/special chars)
            $this->assertMatchesRegularExpression(
                '/[a-zA-Z]/',
                $pairing->player1Name,
                "Player 1 name '{$pairing->player1Name}' should contain letters"
            );
            $this->assertMatchesRegularExpression(
                '/[a-zA-Z]/',
                $pairing->player2Name,
                "Player 2 name '{$pairing->player2Name}' should contain letters"
            );
        }
    }

    /**
     * Test that BCP player IDs have expected format.
     */
    public function testPlayerIdsHaveExpectedFormat(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $pairing) {
            // BCP IDs are typically alphanumeric strings
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9]+$/',
                $pairing->player1BcpId,
                "Player 1 ID '{$pairing->player1BcpId}' should be alphanumeric"
            );
            $this->assertMatchesRegularExpression(
                '/^[A-Za-z0-9]+$/',
                $pairing->player2BcpId,
                "Player 2 ID '{$pairing->player2BcpId}' should be alphanumeric"
            );
        }
    }

    /**
     * Test that player scores are non-negative integers.
     *
     * Note: Round 1 scores are NOT always zero - BCP returns cumulative scores
     * from the player's tournament history, even in round 1 pairings data.
     */
    public function testPlayerScoresAreNonNegativeIntegers(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsData);

        foreach ($pairings as $pairing) {
            $this->assertIsInt(
                $pairing->player1Score,
                'Player 1 score should be an integer'
            );
            $this->assertGreaterThanOrEqual(
                0,
                $pairing->player1Score,
                'Player 1 score should be non-negative'
            );
            $this->assertIsInt(
                $pairing->player2Score,
                'Player 2 score should be an integer'
            );
            $this->assertGreaterThanOrEqual(
                0,
                $pairing->player2Score,
                'Player 2 score should be non-negative'
            );
        }
    }

    // -------------------------------------------------------------------------
    // Raw API Response Structure Tests
    // -------------------------------------------------------------------------

    /**
     * Test that raw pairings have expected fields.
     */
    public function testRawPairingsHaveExpectedFields(): void
    {
        $this->assertNotEmpty(self::$pairingsData['active'], 'Should have active pairings');

        $firstPairing = self::$pairingsData['active'][0];

        // Check for required fields our parser expects
        $this->assertArrayHasKey('player1', $firstPairing, 'Should have player1 field');
        $this->assertArrayHasKey('player2', $firstPairing, 'Should have player2 field');
        $this->assertArrayHasKey('player1Game', $firstPairing, 'Should have player1Game field');
        $this->assertArrayHasKey('player2Game', $firstPairing, 'Should have player2Game field');
    }

    /**
     * Test that player objects have required nested structure.
     */
    public function testPlayerObjectsHaveExpectedStructure(): void
    {
        $firstPairing = self::$pairingsData['active'][0];

        // Player 1 structure
        $this->assertArrayHasKey('id', $firstPairing['player1'], 'player1 should have id');
        $this->assertArrayHasKey('user', $firstPairing['player1'], 'player1 should have user');
        $this->assertArrayHasKey(
            'firstName',
            $firstPairing['player1']['user'],
            'player1.user should have firstName'
        );
        $this->assertArrayHasKey(
            'lastName',
            $firstPairing['player1']['user'],
            'player1.user should have lastName'
        );

        // Player 2 structure
        $this->assertArrayHasKey('id', $firstPairing['player2'], 'player2 should have id');
        $this->assertArrayHasKey('user', $firstPairing['player2'], 'player2 should have user');
    }

    /**
     * Test that game objects have points field.
     */
    public function testGameObjectsHavePointsField(): void
    {
        $firstPairing = self::$pairingsData['active'][0];

        $this->assertArrayHasKey(
            'points',
            $firstPairing['player1Game'],
            'player1Game should have points'
        );
        $this->assertArrayHasKey(
            'points',
            $firstPairing['player2Game'],
            'player2Game should have points'
        );
    }
}
