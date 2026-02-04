<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Contract;

use JsonSchema\Validator;
use PHPUnit\Framework\TestCase;

/**
 * Contract tests for BCP API integration.
 *
 * These tests verify that the BCP REST API remains compatible
 * with our API service by validating responses against JSON schemas
 * that define the minimal fields we depend on.
 *
 * IMPORTANT: These tests require network access to BCP servers.
 * They should be run sparingly to avoid excessive load on BCP.
 *
 * Parsing logic is tested separately in Unit/BCPApiServiceParsingTest
 * using fixture data for fast, deterministic, network-independent tests.
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
    // Helper Methods
    // -------------------------------------------------------------------------

    /**
     * Load a JSON schema file.
     *
     * @param string $filename Schema filename (relative to schemas directory)
     * @return \stdClass Decoded schema object
     */
    private function loadSchema(string $filename)
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
}
