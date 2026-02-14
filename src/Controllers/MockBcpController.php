<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

/**
 * Mock BCP controller for E2E testing.
 *
 * Returns mock API responses that mimic BCP REST API.
 * Only available in test environment (APP_ENV=testing).
 */
class MockBcpController extends BaseController
{
    /**
     * GET /mock-bcp-api/{eventId} - Return mock BCP event details API response.
     *
     * Returns JSON mimicking the BCP events API structure with tournament name.
     */
    public function eventDetails(array $params, ?array $body): void
    {
        $eventId = $params['id'] ?? 'unknown';

        header('Content-Type: application/json');

        echo json_encode([
            'id' => $eventId,
            'name' => "Test Tournament {$eventId}",
            'city' => 'Test City',
            'country' => 'Test Country',
            'eventDate' => '2026-01-01T00:00:00.000Z',
            'numberOfRounds' => 3,
            'totalPlayers' => 8,
            'active' => true,
            'ended' => false
        ]);
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
            ['id' => 'mock_player_1', 'firstName' => 'Alice', 'lastName' => 'Smith', 'faction' => 'Corsair Voidscarred'],
            ['id' => 'mock_player_2', 'firstName' => 'Bob', 'lastName' => 'Jones', 'faction' => 'Nemesis Claw'],
            ['id' => 'mock_player_3', 'firstName' => 'Charlie', 'lastName' => 'Brown', 'faction' => 'Blades of Khaine'],
            ['id' => 'mock_player_4', 'firstName' => 'Diana', 'lastName' => 'Prince', 'faction' => 'Warpcoven'],
            ['id' => 'mock_player_5', 'firstName' => 'Eve', 'lastName' => 'Wilson', 'faction' => 'Pathfinders'],
            ['id' => 'mock_player_6', 'firstName' => 'Frank', 'lastName' => 'Miller', 'faction' => 'Legionaries'],
            ['id' => 'mock_player_7', 'firstName' => 'Grace', 'lastName' => 'Lee', 'faction' => 'Kommandos'],
            ['id' => 'mock_player_8', 'firstName' => 'Henry', 'lastName' => 'Ford', 'faction' => 'Intercession Squad'],
        ];

        $pairings = [];
        $tableNumber = 1;

        // Create 4 pairings (8 players)
        for ($i = 0; $i < 8; $i += 2) {
            $player1 = $players[$i];
            $player2 = $players[$i + 1];

            // Simulate scores based on round (higher rounds = accumulated points)
            $baseScore = ($round - 1);

            // Simulate realistic per-round scores
            // Player on lower table numbers tend to score higher
            $p1RoundScore = max(0, 20 - ($tableNumber * 2) + $round);
            $p2RoundScore = max(0, 15 - ($tableNumber * 2) + $round);

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
                    'faction' => $player1['faction'],
                ],
                'player2' => [
                    'id' => $player2['id'],
                    'user' => [
                        'firstName' => $player2['firstName'],
                        'lastName' => $player2['lastName'],
                    ],
                    'faction' => $player2['faction'],
                ],
                'player1Game' => ['points' => $p1RoundScore, 'result' => 2],
                'player2Game' => ['points' => $p2RoundScore, 'result' => 0],
            ];

            $tableNumber++;
        }

        return $pairings;
    }
}
