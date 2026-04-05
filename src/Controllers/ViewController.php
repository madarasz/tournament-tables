<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Tournament;
use TournamentTables\Models\Round;
use TournamentTables\Models\Allocation;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\TerrainType;
use TournamentTables\Database\Connection;
use TournamentTables\Services\AuthService;

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
        include __DIR__ . '/../Views/admin/create.php';
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
        $tables = Table::findVisibleByTournament($tournamentId);
        $terrainTypes = TerrainType::all();

        // Calculate minimum table count for UI
        // floor because odd player count = 1 bye (no table needed)
        $playerCount = count(Player::findByTournament($tournamentId));
        $minimumTables = $playerCount > 0 ? (int) floor($playerCount / 2) : 0;

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

        include __DIR__ . '/../Views/admin/tournament.php';
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
        include __DIR__ . '/../Views/admin/round.php';
    }

    /**
     * GET /{id} - Public tournament display (unauthenticated).
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

        // Get only published rounds.
        $publishedRounds = Round::findPublishedByTournament($tournamentId);

        $requestedView = strtolower(trim((string) ($_GET['view'] ?? '')));
        $isLeaderboardView = $requestedView === 'leaderboard';
        $roundQuery = (int) ($_GET['round'] ?? 0);

        $round = null;
        $allocations = [];

        if (!empty($publishedRounds)) {
            $latestPublishedRound = $publishedRounds[count($publishedRounds) - 1];

            if ($isLeaderboardView) {
                $round = $latestPublishedRound;
            } else {
                $selectedRound = null;

                if ($roundQuery > 0) {
                    foreach ($publishedRounds as $publishedRound) {
                        if ($publishedRound->roundNumber === $roundQuery) {
                            $selectedRound = $publishedRound;
                            break;
                        }
                    }
                }

                $round = $selectedRound ?? $latestPublishedRound;
                $allocations = $round->getAllocations();
            }
        }

        // Get players sorted by persisted BCP placing for leaderboard (unknown placing last).
        $players = $tournament->getPlayers();
        usort($players, function ($a, $b) {
            $aPlacing = $a->placing;
            $bPlacing = $b->placing;

            if ($aPlacing === null && $bPlacing === null) {
                $scoreCompare = $b->totalScore <=> $a->totalScore;
                if ($scoreCompare !== 0) {
                    return $scoreCompare;
                }
                return strcasecmp($a->name, $b->name);
            }

            if ($aPlacing === null) {
                return 1;
            }

            if ($bPlacing === null) {
                return -1;
            }

            if ($aPlacing !== $bPlacing) {
                return $aPlacing <=> $bPlacing;
            }

            $scoreCompare = $b->totalScore <=> $a->totalScore;
            if ($scoreCompare !== 0) {
                return $scoreCompare;
            }

            return strcasecmp($a->name, $b->name);
        });

        $roundScoresByPlayer = $this->buildLeaderboardRoundScores($publishedRounds);

        $rankedPlayers = array_map(function ($player) use ($roundScoresByPlayer) {
            return [
                'rank' => $player->placing,
                'player' => $player,
                'roundScores' => $roundScoresByPlayer[$player->id] ?? [],
            ];
        }, $players);

        include __DIR__ . '/../Views/public/round.php';
    }

    /**
     * Build leaderboard round score breakdown by player.
     *
     * Each score item includes a CSS class aligned with round-table coloring:
     * score-win (green), score-loss (red), score-tie (grey).
     *
     * @param Round[] $publishedRounds
     * @return array<int, array<int, array{score: int, class: string}>>
     */
    private function buildLeaderboardRoundScores(array $publishedRounds): array
    {
        $roundScoresByPlayer = [];

        foreach ($publishedRounds as $publishedRound) {
            $roundAllocations = $publishedRound->getAllocations();

            foreach ($roundAllocations as $allocation) {
                $player1Id = (int) $allocation->player1Id;
                $player2Id = $allocation->player2Id !== null ? (int) $allocation->player2Id : null;
                $player1Score = (int) $allocation->player1Score;
                $player2Score = (int) $allocation->player2Score;

                $player1Class = $player2Id === null ? 'score-win' : 'score-tie';
                if ($player2Id !== null) {
                    if ($player1Score > $player2Score) {
                        $player1Class = 'score-win';
                    } elseif ($player1Score < $player2Score) {
                        $player1Class = 'score-loss';
                    }
                }

                $roundScoresByPlayer[$player1Id][] = [
                    'score' => $player1Score,
                    'class' => $player1Class,
                ];

                if ($player2Id !== null) {
                    $player2Class = 'score-tie';
                    if ($player2Score > $player1Score) {
                        $player2Class = 'score-win';
                    } elseif ($player2Score < $player1Score) {
                        $player2Class = 'score-loss';
                    }

                    $roundScoresByPlayer[$player2Id][] = [
                        'score' => $player2Score,
                        'class' => $player2Class,
                    ];
                }
            }
        }

        return $roundScoresByPlayer;
    }

    /**
     * GET / - Public tournaments list (unauthenticated).
     */
    public function publicIndex(array $params, ?array $body): void
    {
        // Query all tournaments (public-safe columns only, exclude admin_token).
        // Aggregate related display metadata for tactical public cards.
        $sql = "SELECT
                    t.id,
                    t.name,
                    t.bcp_event_id,
                    t.bcp_url,
                    t.photo_url,
                    t.location_name,
                    t.event_date,
                    t.event_end_date,
                    t.table_count,
                    t.last_updated,
                    COALESCE(p.player_count, 0) AS player_count,
                    COALESCE(r.round_count, 0) AS round_count,
                    COALESCE(te.terrain_emojis, '') AS terrain_emojis
                FROM tournaments t
                LEFT JOIN (
                    SELECT tournament_id, COUNT(*) AS player_count
                    FROM players
                    GROUP BY tournament_id
                ) p ON p.tournament_id = t.id
                LEFT JOIN (
                    SELECT tournament_id, COUNT(*) AS round_count
                    FROM rounds
                    GROUP BY tournament_id
                ) r ON r.tournament_id = t.id
                LEFT JOIN (
                    SELECT
                        terrain_rows.tournament_id,
                        GROUP_CONCAT(terrain_rows.emoji ORDER BY terrain_rows.sort_order SEPARATOR '||') AS terrain_emojis
                    FROM (
                        -- De-dup by terrain type ID (not emoji text) to avoid collation-dependent emoji DISTINCT behavior.
                        SELECT DISTINCT
                            tb.tournament_id,
                            tb.terrain_type_id,
                            tt.emoji,
                            tt.sort_order
                        FROM tables tb
                        INNER JOIN terrain_types tt ON tt.id = tb.terrain_type_id
                        WHERE tb.is_hidden = FALSE
                          AND tt.emoji IS NOT NULL
                          AND tt.emoji <> ''
                    ) terrain_rows
                    GROUP BY terrain_rows.tournament_id
                ) te ON te.tournament_id = t.id
                ORDER BY
                    CASE
                        WHEN t.event_date IS NULL OR TRIM(t.event_date) = '' THEN 1
                        ELSE 0
                    END ASC,
                    t.event_date DESC,
                    t.id DESC";

        $tournaments = Connection::fetchAll($sql);

        include __DIR__ . '/../Views/public/index.php';
    }

    /**
     * GET /login - Login page.
     */
    public function login(array $params, ?array $body): void
    {
        $autoLoginError = null;
        $token = isset($_GET['token']) ? trim((string) $_GET['token']) : '';

        if ($token !== '') {
            $authService = new AuthService();
            $result = $authService->validateToken($token);

            if ($result['valid']) {
                $tournament = $result['tournament'];
                $this->addTournamentToken($tournament->id, $token, $tournament->name);
                header('Location: /admin/tournament/' . $tournament->id . '?loginSuccess=1');
                return;
            }

            $autoLoginError = $result['error'] ?? 'Invalid token';
        }

        include __DIR__ . '/../Views/admin/login.php';
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
