<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Allocation;
use TournamentTables\Models\Table;
use TournamentTables\Models\TerrainType;
use TournamentTables\Database\Connection;

/**
 * View controller for HTML page rendering.
 *
 * Handles rendering of both admin and public views.
 */
class ViewController extends BaseController
{
    /**
     * GET / - Home page.
     */
    public function home(array $params, ?array $body): void
    {
        echo $this->render('home');
    }

    /**
     * GET /tournament/create - Tournament creation form.
     */
    public function createTournament(array $params, ?array $body): void
    {
        echo $this->render('tournament/create');
    }

    /**
     * GET /tournament/{id} - Tournament dashboard (admin).
     */
    public function showTournament(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $tournament = Tournament::find($tournamentId);

        if ($tournament === null) {
            http_response_code(404);
            echo $this->render404('Tournament not found');
            return;
        }

        $rounds = Round::findByTournament($tournamentId);
        $tables = Table::findByTournament($tournamentId);
        $terrainTypes = TerrainType::all();

        // Check if this tournament was just created
        $this->ensureSession();
        $justCreated = false;
        $adminToken = null;
        $autoImport = null;
        if (isset($_SESSION['tournament_just_created'])) {
            $createdInfo = $_SESSION['tournament_just_created'];
            if ($createdInfo['id'] === $tournamentId) {
                $justCreated = true;
                $adminToken = $createdInfo['adminToken'];
                $autoImport = $createdInfo['autoImport'] ?? null;
                // Clear the session variable after use
                unset($_SESSION['tournament_just_created']);
            }
        }

        echo $this->renderWithLayout('tournament/dashboard', [
            'tournament' => $tournament,
            'rounds' => $rounds,
            'tables' => $tables,
            'terrainTypes' => $terrainTypes,
            'justCreated' => $justCreated,
            'adminToken' => $adminToken,
            'autoImport' => $autoImport,
        ]);
    }

    /**
     * GET /tournament/{id}/round/{n} - Round management (admin).
     */
    public function showRound(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            http_response_code(404);
            echo $this->render404('Tournament not found');
            return;
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            http_response_code(404);
            echo $this->render404('Round not found');
            return;
        }

        $allocations = $round->getAllocations();
        $conflicts = [];
        foreach ($allocations as $allocation) {
            $conflicts = array_merge($conflicts, $allocation->getConflicts());
        }

        // Get all rounds for navigation
        $rounds = Round::findByTournament($tournamentId);

        // Render the round management view
        include __DIR__ . '/../Views/round/manage.php';
    }

    /**
     * GET /public/{id} - Public tournament view (unauthenticated).
     */
    public function publicTournament(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            http_response_code(404);
            echo $this->render404('Tournament not found');
            return;
        }

        // Get only published rounds
        $publishedRounds = Round::findPublishedByTournament($tournamentId);

        // Get tables for table count display
        $tables = Table::findByTournament($tournamentId);

        // Render the public tournament view
        include __DIR__ . '/../Views/public/tournament.php';
    }

    /**
     * GET /public - Public tournaments list (unauthenticated).
     */
    public function publicIndex(array $params, ?array $body): void
    {
        // Query tournaments with at least one published round
        $sql = 'SELECT DISTINCT t.*,
                (SELECT COUNT(*) FROM players WHERE tournament_id = t.id) as player_count
                FROM tournaments t
                INNER JOIN rounds r ON r.tournament_id = t.id AND r.is_published = TRUE
                ORDER BY t.id DESC';

        $tournaments = Connection::fetchAll($sql);

        include __DIR__ . '/../Views/public/index.php';
    }

    /**
     * GET /public/{id}/round/{n} - Public round view (unauthenticated).
     */
    public function publicRound(array $params, ?array $body): void
    {
        $tournamentId = (int) ($params['id'] ?? 0);
        $roundNumber = (int) ($params['n'] ?? 0);

        $tournament = Tournament::find($tournamentId);
        if ($tournament === null) {
            http_response_code(404);
            echo $this->render404('Tournament not found');
            return;
        }

        $round = Round::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round === null) {
            http_response_code(404);
            echo $this->render404('Round not found');
            return;
        }

        // Only show published rounds publicly
        if (!$round->isPublished) {
            http_response_code(404);
            echo $this->render404('Round not published');
            return;
        }

        $allocations = $round->getAllocations();

        // Get all published rounds for navigation
        $publishedRounds = Round::findPublishedByTournament($tournamentId);

        // Render the public round view
        include __DIR__ . '/../Views/public/round.php';
    }

    /**
     * GET /login - Login page.
     */
    public function login(array $params, ?array $body): void
    {
        include __DIR__ . '/../Views/auth/login.php';
    }

    /**
     * Render a view file.
     */
    private function render(string $view, array $data = []): string
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../Views/' . $view . '.php';
        return ob_get_clean();
    }

    /**
     * Render a view within the main layout.
     */
    private function renderWithLayout(string $view, array $data = [], bool $isPublic = false): string
    {
        extract($data);
        ob_start();
        include __DIR__ . '/../Views/' . $view . '.php';
        $content = ob_get_clean();

        ob_start();
        include __DIR__ . '/../Views/layout.php';
        return ob_get_clean();
    }

    /**
     * Render a 404 error page.
     */
    private function render404(string $message = 'Page not found'): string
    {
        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>404 Not Found - Tournament Tables</title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <style>
        .error-container {
            max-width: 600px;
            margin: 4rem auto;
            text-align: center;
            padding: 2rem;
        }
        .error-code {
            font-size: 6rem;
            font-weight: bold;
            color: #999;
            margin: 0;
        }
        .error-message {
            font-size: 1.25rem;
            color: #666;
            margin: 1rem 0;
        }
        .back-link {
            display: inline-block;
            margin-top: 2rem;
        }
    </style>
</head>
<body>
    <main class="error-container">
        <p class="error-code">404</p>
        <p class="error-message">{$message}</p>
        <a href="/" class="back-link">Return to Home</a>
    </main>
</body>
</html>
HTML;
    }
}
