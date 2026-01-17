<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

/**
 * Mock BCP controller for E2E testing.
 *
 * Returns mock HTML that mimics BCP event pages.
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
}
