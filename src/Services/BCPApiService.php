<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Service for fetching data from Best Coast Pairings (BCP) REST API.
 *
 * Reference: specs/001-table-allocation/research.md#2-bcp-rest-api
 *
 * BCP provides a public REST API that returns JSON data.
 */
class BCPApiService
{
    const BCP_API_BASE_URL = 'https://newprod-api.bestcoastpairings.com/v1/events';

    /** @var int */
    private $maxRetries = 3;

    /** @var int */
    private $baseDelayMs = 1000;

    /** @var float */
    private $backoffMultiplier = 2.0;

    /** @var string|null Mock base URL for API calls (testing) */
    private $mockApiBaseUrl;

    public function __construct()
    {
        // Check for mock BCP URL in test environment
        $this->mockApiBaseUrl = getenv('BCP_MOCK_API_URL') ?: null;
    }

    /**
     * Fetch tournament name from BCP API.
     *
     * @param string $bcpUrl BCP event URL
     * @return string Tournament name
     * @throws \RuntimeException If API fetch fails or name not found
     */
    public function fetchTournamentName(string $bcpUrl): string
    {
        $eventId = $this->extractEventId($bcpUrl);
        $eventData = $this->fetchEventDetails($eventId);

        if (!isset($eventData['name']) || empty($eventData['name'])) {
            throw new \RuntimeException("Tournament name not found in BCP API response.");
        }

        $name = trim($eventData['name']);
        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        // Truncate if too long (database VARCHAR 255 limit)
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '...';
        }

        return $name;
    }

    /**
     * Fetch event details from BCP API.
     *
     * @param string $eventId BCP event ID
     * @return array Event data including name
     * @throws \RuntimeException If API request fails
     */
    public function fetchEventDetails(string $eventId): array
    {
        $url = $this->buildEventUrl($eventId);
        return $this->fetchJsonWithRetry($url);
    }

    /**
     * Build the API URL for fetching event details.
     *
     * @param string $eventId BCP event ID
     * @return string API URL
     */
    public function buildEventUrl(string $eventId): string
    {
        return $this->buildUrl("/{$eventId}");
    }

    /**
     * Fetch pairings from BCP for a given event and round.
     *
     * @param string $eventId BCP event ID
     * @param int $round Round number
     * @return Pairing[]
     * @throws \RuntimeException If API request fails after retries
     */
    public function fetchPairings(string $eventId, int $round): array
    {
        $url = $this->buildPairingsUrl($eventId, $round);
        $data = $this->fetchJsonWithRetry($url);
        return $this->parseApiResponse($data);
    }

    /**
     * Build the API URL for fetching pairings.
     *
     * In test mode (BCP_MOCK_API_URL set), redirects to mock endpoint.
     */
    public function buildPairingsUrl(string $eventId, int $round): string
    {
        if ($this->mockApiBaseUrl !== null) {
            // Use mock API endpoint for testing (simpler query string)
            return $this->buildUrl("/{$eventId}/pairings?round={$round}");
        }
        return $this->buildUrl("/{$eventId}/pairings?eventId={$eventId}&round={$round}&pairingType=Pairing");
    }

    /**
     * Extract event ID from a BCP URL.
     *
     * Delegates to BcpUrlValidator for centralized URL parsing.
     *
     * @throws \InvalidArgumentException If URL is not a valid BCP event URL
     */
    public function extractEventId(string $url): string
    {
        $eventId = BcpUrlValidator::extractEventId($url);

        if ($eventId === null) {
            throw new \InvalidArgumentException('Invalid BCP event URL: ' . $url);
        }

        return $eventId;
    }

    /**
     * Fetch JSON from URL with retry and exponential backoff.
     *
     * Reference: specs/001-table-allocation/research.md#best-practices
     *
     * @return array Decoded JSON response
     * @throws \RuntimeException If all retries fail
     */
    private function fetchJsonWithRetry(string $url): array
    {
        $lastException = null;
        $delay = $this->baseDelayMs;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->fetchJson($url);
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    usleep($delay * 1000); // Convert ms to microseconds
                    $delay = (int) ($delay * $this->backoffMultiplier);
                }
            }
        }

        throw new \RuntimeException(
            "Failed to fetch BCP data after {$this->maxRetries} attempts: " .
            ($lastException ? $lastException->getMessage() : 'Unknown error'),
            0,
            $lastException
        );
    }

    /**
     * Fetch JSON from BCP API using native PHP HTTP client.
     *
     * @return array Decoded JSON response
     * @throws \RuntimeException If request fails or returns invalid JSON
     */
    private function fetchJson(string $url): array
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: application/json',
                    'client-id: web-app',
                    'env: bcp',
                    'content-type: application/json'
                ]),
                'timeout' => 10
            ]
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException("Failed to fetch URL: {$url}");
        }

        // Check HTTP status code
        if (isset($http_response_header[0])) {
            if (!preg_match('/HTTP\/\d\.\d\s+2\d{2}/', $http_response_header[0])) {
                throw new \RuntimeException("HTTP error: {$http_response_header[0]}");
            }
        }

        $data = json_decode($response, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("Invalid JSON response: " . json_last_error_msg());
        }

        return $data;
    }

    /**
     * Parse pairings from BCP API response.
     *
     * Reference: specs/001-table-allocation/research.md#api-response-structure
     *
     * @param array $data Decoded JSON from BCP API
     * @return Pairing[]
     */
    public function parseApiResponse(array $data): array
    {
        if (!isset($data['active']) || !is_array($data['active'])) {
            return [];
        }

        $pairings = [];

        foreach ($data['active'] as $item) {
            $pairing = $this->parsePairingItem($item);
            if ($pairing !== null) {
                $pairings[] = $pairing;
            }
        }

        // Sort by table number (ascending)
        usort($pairings, function (Pairing $a, Pairing $b) {
            if ($a->bcpTableNumber === null && $b->bcpTableNumber === null) {
                return 0;
            }
            if ($a->bcpTableNumber === null) {
                return 1;
            }
            if ($b->bcpTableNumber === null) {
                return -1;
            }
            return $a->bcpTableNumber <=> $b->bcpTableNumber;
        });

        return $pairings;
    }

    /**
     * Parse a single pairing item from API response.
     *
     * @param array $item Single pairing data from API
     * @return Pairing|null Returns null if required fields are missing
     */
    private function parsePairingItem(array $item): ?Pairing
    {
        // Validate required fields
        if (!isset($item['player1'], $item['player2'], $item['player1Game'], $item['player2Game'])) {
            return null;
        }

        // Extract player data
        $player1BcpId = $item['player1']['id'] ?? '';
        $player1Name = $this->formatPlayerName($item['player1']['user'] ?? []);
        $player1Score = (int)($item['player1Game']['points'] ?? 0);

        $player2BcpId = $item['player2']['id'] ?? '';
        $player2Name = $this->formatPlayerName($item['player2']['user'] ?? []);
        $player2Score = (int)($item['player2Game']['points'] ?? 0);

        // Table number (nullable)
        $bcpTableNumber = isset($item['table']) ? (int)$item['table'] : null;

        // Skip if missing player IDs
        if (empty($player1BcpId) || empty($player2BcpId)) {
            return null;
        }

        return new Pairing(
            $player1BcpId,
            $player1Name,
            $player1Score,
            $player2BcpId,
            $player2Name,
            $player2Score,
            $bcpTableNumber
        );
    }

    /**
     * Format player name from user data.
     *
     * @param array $user User data containing firstName and lastName
     * @return string Formatted name (firstName lastName)
     */
    private function formatPlayerName(array $user): string
    {
        $firstName = $user['firstName'] ?? '';
        $lastName = $user['lastName'] ?? '';
        return trim("{$firstName} {$lastName}");
    }

    /**
     * Build URL for fetching player placings.
     *
     * @param string $eventId BCP event ID
     * @return string API URL
     */
    public function buildPlacingsUrl(string $eventId): string
    {
        return $this->buildUrl("/{$eventId}/players?placings=true");
    }

    /**
     * Build a URL using either mock or production base URL.
     *
     * @param string $path Path to append (should start with /)
     * @return string Complete URL
     */
    private function buildUrl(string $path): string
    {
        $baseUrl = $this->mockApiBaseUrl !== null
            ? rtrim($this->mockApiBaseUrl, '/')
            : self::BCP_API_BASE_URL;

        return $baseUrl . $path;
    }

    /**
     * Fetch player placings (total scores) from BCP API.
     *
     * @param string $eventId BCP event ID
     * @return array<string, int> Map of BCP player ID to total score
     * @throws \RuntimeException If API request fails
     */
    public function fetchPlayerTotalScores(string $eventId): array
    {
        $url = $this->buildPlacingsUrl($eventId);
        $data = $this->fetchJsonWithRetry($url);

        return $this->parsePlacingsResponse($data);
    }

    /**
     * Parse placings response to extract total scores.
     *
     * Response structure per player:
     * {
     *   "id": "FX29RXY6GP",
     *   "overall_metrics": [
     *     {"name": "Overall Score", "value": 57}
     *   ]
     * }
     *
     * @param array $data API response data
     * @return array<string, int> Map of BCP player ID to total score
     */
    private function parsePlacingsResponse(array $data): array
    {
        $scores = [];

        // The API returns players in 'active' array
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

    // Getters for retry configuration (used in tests)

    public function getMaxRetries(): int
    {
        return $this->maxRetries;
    }

    public function getBaseDelayMs(): int
    {
        return $this->baseDelayMs;
    }

    public function getBackoffMultiplier(): float
    {
        return $this->backoffMultiplier;
    }
}
