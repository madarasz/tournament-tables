<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\BCPApiService;

/**
 * Unit tests for BCPApiService parsing logic.
 *
 * These tests use fixture data instead of live API calls for:
 * - No network dependency
 * - Fast execution
 * - Deterministic results
 * - No load on external APIs
 */
class BCPApiServiceParsingTest extends TestCase
{
    /** @var BCPApiService */
    private $apiService;

    /** @var array Cached pairings fixture data */
    private static $pairingsFixture;

    /** @var array Cached placings fixture data */
    private static $placingsFixture;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();

        self::$pairingsFixture = self::loadFixture('bcp_api_response.json');
        self::$placingsFixture = self::loadFixture('bcp_placings_response.json');
    }

    protected function setUp(): void
    {
        $this->apiService = new BCPApiService();
    }

    /**
     * Load a JSON fixture file.
     *
     * @param string $filename Fixture filename (relative to fixtures directory)
     * @return array Decoded JSON data
     */
    private static function loadFixture(string $filename): array
    {
        $path = __DIR__ . '/../fixtures/' . $filename;

        if (!file_exists($path)) {
            throw new \RuntimeException("Fixture file not found: $filename");
        }

        $content = file_get_contents($path);
        $data = json_decode($content, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Failed to parse fixture: $filename - " . json_last_error_msg());
        }

        return $data;
    }

    // -------------------------------------------------------------------------
    // Pairings Parsing Tests
    // -------------------------------------------------------------------------

    /**
     * Test that each parsed pairing has valid player 1 data.
     */
    public function testParsedPairingsHaveValidPlayer1Data(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsFixture);

        $this->assertNotEmpty($pairings, 'Should parse at least one pairing from fixture');

        foreach ($pairings as $index => $pairing) {
            $this->assertNotEmpty(
                $pairing->player1BcpId,
                "Pairing $index should have player1 BCP ID"
            );
            $this->assertNotEmpty(
                $pairing->player1Name,
                "Pairing $index should have player1 name"
            );
            $this->assertTrue(
                is_int($pairing->player1Score),
                "Pairing $index should have player1 score as int"
            );
        }
    }

    /**
     * Test that each parsed pairing has valid player 2 data.
     */
    public function testParsedPairingsHaveValidPlayer2Data(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsFixture);

        $this->assertNotEmpty($pairings, 'Should parse at least one pairing from fixture');

        foreach ($pairings as $index => $pairing) {
            $this->assertNotEmpty(
                $pairing->player2BcpId,
                "Pairing $index should have player2 BCP ID"
            );
            $this->assertNotEmpty(
                $pairing->player2Name,
                "Pairing $index should have player2 name"
            );
            $this->assertTrue(
                is_int($pairing->player2Score),
                "Pairing $index should have player2 score as int"
            );
        }
    }

    /**
     * Test that pairings have faction data when available.
     */
    public function testParsedPairingsHaveFactionData(): void
    {
        $pairings = $this->apiService->parseApiResponse(self::$pairingsFixture);

        $this->assertNotEmpty($pairings, 'Should parse at least one pairing from fixture');

        // At least one pairing should have faction data (fixture includes factions)
        $hasFaction = false;
        foreach ($pairings as $pairing) {
            if ($pairing->player1Faction !== null || $pairing->player2Faction !== null) {
                $hasFaction = true;
                break;
            }
        }

        $this->assertTrue(
            $hasFaction,
            'At least one pairing should have faction data from fixture'
        );

        // Verify faction is string or null for all pairings
        foreach ($pairings as $index => $pairing) {
            $this->assertTrue(
                $pairing->player1Faction === null || is_string($pairing->player1Faction),
                "Pairing $index player1Faction should be string or null"
            );
            $this->assertTrue(
                $pairing->player2Faction === null || is_string($pairing->player2Faction),
                "Pairing $index player2Faction should be string or null"
            );
        }
    }

    // -------------------------------------------------------------------------
    // Placings/Scores Parsing Tests
    // -------------------------------------------------------------------------

    /**
     * Test that we can parse total scores from the placings fixture.
     */
    public function testCanParseTotalScores(): void
    {
        // Use reflection to call private parsePlacingsResponse method
        $scores = $this->invokeParsePlacingsResponse(self::$placingsFixture);

        $this->assertTrue(is_array($scores), 'Parsed scores should be an array');
        $this->assertNotEmpty($scores, 'Should have at least one player score');
    }

    /**
     * Test that parsed total scores are non-negative integers.
     */
    public function testParsedTotalScoresAreValid(): void
    {
        $scores = $this->invokeParsePlacingsResponse(self::$placingsFixture);

        foreach ($scores as $playerId => $score) {
            $this->assertTrue(is_string($playerId), 'Player ID key should be string');
            $this->assertTrue(is_int($score), 'Score should be integer');
            $this->assertGreaterThanOrEqual(0, $score, 'Score should be non-negative');
        }
    }

    /**
     * Invoke the private parsePlacingsResponse method via reflection.
     *
     * @param array $data Placings API response data
     * @return array<string, int> Map of BCP player ID to total score
     */
    private function invokeParsePlacingsResponse(array $data): array
    {
        $reflection = new \ReflectionClass($this->apiService);
        $method = $reflection->getMethod('parsePlacingsResponse');
        $method->setAccessible(true);

        return $method->invoke($this->apiService, $data);
    }
}
