<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Service for fetching pairing data from Best Coast Pairings (BCP) REST API.
 *
 * Reference: specs/001-table-allocation/research.md#2-bcp-rest-api
 *
 * BCP provides a public REST API that returns JSON data.
 */
class BCPScraperService
{
    const BCP_API_BASE_URL = 'https://newprod-api.bestcoastpairings.com/v1/events';
    const BCP_WEB_BASE_URL = 'https://www.bestcoastpairings.com';

    /** @var int */
    private $maxRetries = 3;

    /** @var int */
    private $baseDelayMs = 1000;

    /** @var float */
    private $backoffMultiplier = 2.0;

    /** @var string|null Mock base URL for testing */
    private $mockBaseUrl;

    public function __construct()
    {
        // Check for mock BCP URL in test environment
        $this->mockBaseUrl = getenv('BCP_MOCK_BASE_URL') ?: null;
    }

    /**
     * Fetch tournament name from BCP event page.
     *
     * @param string $bcpUrl BCP event URL
     * @return string Tournament name
     * @throws \RuntimeException If HTML fetch fails or name cannot be parsed
     */
    public function fetchTournamentName(string $bcpUrl): string
    {
        // In test mode, redirect BCP URLs to mock server
        $fetchUrl = $this->resolveFetchUrl($bcpUrl);
        $html = $this->fetchHtmlWithRetry($fetchUrl);
        return $this->parseHtmlForTournamentName($html);
    }

    /**
     * Resolve the actual URL to fetch, handling mock redirects for testing.
     *
     * @param string $bcpUrl Original BCP URL
     * @return string URL to fetch (mock or real)
     */
    private function resolveFetchUrl(string $bcpUrl): string
    {
        $eventId = $this->extractEventId($bcpUrl);
        $base = $this->mockBaseUrl ?? self::BCP_WEB_BASE_URL;
        return rtrim($base, '/') . '/event/' . $eventId;
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
     */
    public function buildPairingsUrl(string $eventId, int $round): string
    {
        return self::BCP_API_BASE_URL . "/{$eventId}/pairings?eventId={$eventId}&round={$round}&pairingType=Pairing";
    }

    /**
     * Extract event ID from a BCP URL.
     *
     * @throws \InvalidArgumentException If URL is not a valid BCP event URL
     */
    public function extractEventId(string $url): string
    {
        $pattern = '#^https://www\.bestcoastpairings\.com/event/([A-Za-z0-9]+)(?:[/?]|$)#';

        if (preg_match($pattern, $url, $matches)) {
            return $matches[1];
        }

        throw new \InvalidArgumentException('Invalid BCP event URL: ' . $url);
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
            "Failed to fetch BCP pairings after {$this->maxRetries} attempts: " .
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
     * Fetch HTML from URL with retry and exponential backoff.
     *
     * @param string $url URL to fetch
     * @return string Raw HTML content
     * @throws \RuntimeException If all retries fail
     */
    private function fetchHtmlWithRetry(string $url): string
    {
        $lastException = null;
        $delay = $this->baseDelayMs;

        for ($attempt = 1; $attempt <= $this->maxRetries; $attempt++) {
            try {
                return $this->fetchHtml($url);
            } catch (\Exception $e) {
                $lastException = $e;

                if ($attempt < $this->maxRetries) {
                    usleep($delay * 1000); // Convert ms to microseconds
                    $delay = (int) ($delay * $this->backoffMultiplier);
                }
            }
        }

        throw new \RuntimeException(
            "Unable to connect to BCP. Please try again later.",
            0,
            $lastException
        );
    }

    /**
     * Fetch HTML from BCP page using native PHP HTTP client.
     *
     * @param string $url URL to fetch
     * @return string HTML content
     * @throws \RuntimeException If request fails
     */
    private function fetchHtml(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => implode("\r\n", [
                    'Accept: text/html,application/xhtml+xml',
                    'User-Agent: Mozilla/5.0 (compatible; TournamentTables/1.0)'
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

        return $response;
    }

    /**
     * Parse tournament name from BCP HTML page.
     *
     * The tournament name is in the first <h3> element on the page.
     *
     * @param string $html HTML content from BCP page
     * @return string Tournament name
     * @throws \RuntimeException If name cannot be parsed
     */
    public function parseHtmlForTournamentName(string $html): string
    {
        // Suppress DOM parsing warnings for potentially malformed HTML
        $previousErrorState = libxml_use_internal_errors(true);

        $doc = new \DOMDocument();
        $doc->loadHTML($html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Restore error handling
        libxml_clear_errors();
        libxml_use_internal_errors($previousErrorState);

        // Find the first h3 element
        $h3Elements = $doc->getElementsByTagName('h3');
        if ($h3Elements->length === 0) {
            throw new \RuntimeException("Tournament name not found on BCP page. Please check URL.");
        }

        $h3 = $h3Elements->item(0);
        $name = trim($h3->textContent);

        // Validate name is not empty
        if ($name === '') {
            throw new \RuntimeException("Tournament name not found on BCP page. Please check URL.");
        }

        $name = htmlspecialchars($name, ENT_QUOTES, 'UTF-8');

        // Truncate if too long (database VARCHAR 255 limit)
        if (strlen($name) > 255) {
            $name = substr($name, 0, 252) . '...';
        }

        // Sanitize to prevent XSS
        return $name;
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
