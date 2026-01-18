<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

/**
 * Mock BCP controller for E2E testing.
 *
 * Returns mock HTML and API responses that mimic BCP.
 * Only available in test environment (APP_ENV=testing).
 */
class MockBcpController extends BaseController
{
    /**
     * GET /mock-bcp/event/{eventId} - Return mock BCP event page HTML.
     *
     * Returns HTML with an h3 element containing a tournament name
     * derived from the event ID.
     *
     * Note: This endpoint is only useful for testing. In production,
     * BCP_MOCK_BASE_URL is not set, so requests go to real BCP.
     */
    public function event(array $params, ?array $body): void
    {
        $eventId = $params['id'] ?? 'unknown';

        // Return mock BCP HTML with tournament name in h3
        header('Content-Type: text/html; charset=UTF-8');
        echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Best Coast Pairings - Mock</title>
</head>
<body>
    <div class="container">
        <h3>Test Tournament {$eventId}</h3>
        <div class="event-details">
            <p>This is a mock BCP event page for testing.</p>
        </div>
    </div>
</body>
</html>
HTML;
    }

    /**
     * GET /mock-bcp-api/{eventId}/pairings - Return mock BCP pairings API response.
     *
     * Returns JSON mimicking the BCP pairings API structure.
     * Generates mock pairings based on the round parameter.
     */
    public function pairings(array $params, ?array $body): void
    {
        $eventId = $params['id'] ?? 'unknown';
        $round = (int) ($_GET['round'] ?? 1);

        header('Content-Type: application/json');

        // Generate mock pairings (4 tables worth of players)
        $pairings = $this->generateMockPairings($round);

        echo json_encode([
            'active' => $pairings,
            'deleted' => [],
        ]);
    }

    /**
     * Generate mock pairing data for a given round.
     *
     * @param int $round Round number
     * @return array Mock pairings in BCP API format
     */
    private function generateMockPairings(int $round): array
    {
        $players = [
            ['id' => 'mock_player_1', 'firstName' => 'Alice', 'lastName' => 'Smith'],
            ['id' => 'mock_player_2', 'firstName' => 'Bob', 'lastName' => 'Jones'],
            ['id' => 'mock_player_3', 'firstName' => 'Charlie', 'lastName' => 'Brown'],
            ['id' => 'mock_player_4', 'firstName' => 'Diana', 'lastName' => 'Prince'],
            ['id' => 'mock_player_5', 'firstName' => 'Eve', 'lastName' => 'Wilson'],
            ['id' => 'mock_player_6', 'firstName' => 'Frank', 'lastName' => 'Miller'],
            ['id' => 'mock_player_7', 'firstName' => 'Grace', 'lastName' => 'Lee'],
            ['id' => 'mock_player_8', 'firstName' => 'Henry', 'lastName' => 'Ford'],
        ];

        $pairings = [];
        $tableNumber = 1;

        // Create 4 pairings (8 players)
        for ($i = 0; $i < 8; $i += 2) {
            $player1 = $players[$i];
            $player2 = $players[$i + 1];

            // Simulate scores based on round (higher rounds = accumulated points)
            $baseScore = ($round - 1);

            $pairings[] = [
                'id' => 'pairing_' . $tableNumber . '_round_' . $round,
                'table' => $tableNumber,
                'round' => $round,
                'player1' => [
                    'id' => $player1['id'],
                    'user' => [
                        'firstName' => $player1['firstName'],
                        'lastName' => $player1['lastName'],
                    ],
                ],
                'player2' => [
                    'id' => $player2['id'],
                    'user' => [
                        'firstName' => $player2['firstName'],
                        'lastName' => $player2['lastName'],
                    ],
                ],
                'player1Game' => ['points' => $baseScore],
                'player2Game' => ['points' => $baseScore],
            ];

            $tableNumber++;
        }

        return $pairings;
    }
}
