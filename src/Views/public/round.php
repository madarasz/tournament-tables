<?php
/**
 * Public round view.
 *
 * Displays allocation table for a published round (FR-012).
 * Styled for readability on venue devices (T083).
 *
 * Reference: specs/001-table-allocation/tasks.md#T081, T083
 *
 * Expected variables:
 * - $tournament: Tournament model
 * - $round: Round model
 * - $allocations: Array of Allocation models
 * - $publishedRounds: Array of Round models (for round navigation)
 */

$pageTitle = htmlspecialchars($tournament->name) . " - Round {$round->roundNumber}";
$hasAllocations = !empty($allocations);

// Calculate prev/next rounds for navigation
$prevRound = null;
$nextRound = null;
foreach ($publishedRounds as $r) {
    if ($r->roundNumber === $round->roundNumber - 1) {
        $prevRound = $r;
    }
    if ($r->roundNumber === $round->roundNumber + 1) {
        $nextRound = $r;
    }
}

/**
 * Abbreviate player name for mobile display (UX Improvement #8).
 * "Tamas Horvath" -> "T. Horvath"
 * "John" -> "John" (single name, no change)
 */
function abbreviatePlayerName($fullName) {
    $parts = explode(' ', trim($fullName));
    if (count($parts) < 2) {
        return $fullName;
    }
    // First initial + last name(s)
    $firstInitial = mb_substr($parts[0], 0, 1) . '.';
    array_shift($parts);
    return $firstInitial . ' ' . implode(' ', $parts);
}
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Tournament Tables</title>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <!-- App CSS -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

    <style>
        /*
         * Public view styling for venue readability (T083)
         * Large fonts, high contrast for tournament venue displays
         */

        :root {
            --primary: #1095c1;
            --primary-hover: #0d7ea8;
            --font-size-base: 1.25rem;
        }

        body {
            font-size: var(--font-size-base);
            line-height: 1.6;
        }

        .container {
            max-width: 1400px;
            margin: 0 auto;
            padding: 0 2rem;
        }

        /* Header styling */
        .public-header {
            background: linear-gradient(135deg, var(--primary) 0%, #0d7ea8 100%);
            color: white;
            padding: 2rem;
            margin-bottom: 2rem;
        }

        .public-header h1 {
            color: white;
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
            text-align: center;
        }

        .public-header .subtitle {
            margin: 0.5rem 0 0 0;
            font-size: 1.25rem;
            opacity: 0.9;
            text-align: center;
        }

        /* Round navigation */
        .round-nav {
            display: flex;
            justify-content: space-between;
            align-items: center;
            flex-wrap: wrap;
            gap: 1rem;
            margin-top: 1rem;
        }

        .round-nav-buttons {
            display: flex;
            gap: 0.5rem;
            flex-wrap: wrap;
        }

        .round-nav a {
            display: inline-block;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            text-decoration: none;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            transition: background 0.2s;
        }

        .round-nav a:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        .round-nav a.active {
            background: white;
            color: var(--primary);
            font-weight: 600;
        }

        .back-link {
            display: inline-block;
            padding: 0.5rem 1rem;
            font-size: 1rem;
            text-decoration: none;
            color: white;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
        }

        .back-link:hover {
            background: rgba(255, 255, 255, 0.3);
            color: white;
        }

        /* Allocation table - optimized for readability */
        .allocation-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 1.25rem;
            margin: 1rem 0;
        }

        .allocation-table thead {
            background: #333;
            color: white;
        }

        .allocation-table th {
            padding: 1rem 1.5rem;
            text-align: left;
            font-weight: 600;
            white-space: nowrap;
        }

        .allocation-table td {
            padding: 1rem 1.5rem;
            border-bottom: 1px solid #e0e0e0;
            vertical-align: middle;
        }

        .allocation-table tbody tr:nth-child(even) {
            background: #f9f9f9;
        }

        .allocation-table tbody tr:hover {
            background: #eef7ff;
        }

        /* Table number - large and bold */
        .table-number {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--primary);
            text-align: center;
            width: 100px;
        }

        /* Terrain type */
        .terrain-type {
            font-style: italic;
            color: #666;
        }

        /* Player names - prominent */
        .player-name {
            font-weight: 500;
        }

        /* Score - highlighted */
        .player-score {
            font-weight: 700;
            font-size: 1.4rem;
            color: #1976d2;
            text-align: center;
            width: 80px;
        }

        /* VS separator */
        .vs-separator {
            text-align: center;
            color: #999;
            font-weight: bold;
            width: 60px;
        }

        /* Player faction styling */
        .player-faction {
            display: block;
            font-size: 0.75em;
            color: #888;
            font-style: italic;
            margin-top: 2px;
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        /* No allocations message */
        .no-allocations {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            font-size: 1.25rem;
            color: #856404;
        }

        /* Bye row styling */
        .bye-row {
            background-color: #f5f5f5 !important;
        }
        .bye-row:hover {
            background-color: #eeeeee !important;
        }
        .bye-indicator {
            color: #9e9e9e;
            font-style: italic;
            font-size: 0.9em;
        }
        .bye-no-table {
            color: #9e9e9e;
            font-style: italic;
        }

        /* Footer */
        .public-footer {
            margin-top: 3rem;
            padding: 1.5rem;
            text-align: center;
            background: #f5f5f5;
            font-size: 1rem;
            color: #666;
        }

        /* Mobile name display (UX Improvement #8) */
        .player-name-full { display: inline; }
        .player-name-short { display: none; }

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .public-header h1 {
                font-size: 1.5rem;
            }

            .allocation-table {
                font-size: 1rem;
            }

            .allocation-table th,
            .allocation-table td {
                padding: 0.75rem 0.5rem;
            }

            .table-number {
                font-size: 1.25rem;
                width: 60px;
            }

            .player-score {
                font-size: 1.1rem;
                width: 50px;
            }

            /* Hide terrain, vs separator, and score columns on mobile */
            .allocation-table .terrain-type,
            .allocation-table .vs-separator,
            .allocation-table .player-score {
                display: none;
            }
            .allocation-table th.terrain-type,
            .allocation-table th.vs-separator,
            .allocation-table th.player-score {
                display: none;
            }

            /* Show abbreviated names on mobile (UX Improvement #8) */
            .player-name-full { display: none; }
            .player-name-short { display: inline; }

            /* Compact player cells on mobile */
            .player-name {
                font-size: 0.95rem;
            }
        }

        /* Large display mode - for TV/monitor at venue */
        @media (min-width: 1600px) {
            :root {
                --font-size-base: 1.5rem;
            }

            .public-header h1 {
                font-size: 3rem;
            }

            .allocation-table {
                font-size: 1.5rem;
            }

            .allocation-table th,
            .allocation-table td {
                padding: 1.25rem 2rem;
            }

            .table-number {
                font-size: 2.25rem;
                width: 120px;
            }

            .player-score {
                font-size: 1.75rem;
                width: 100px;
            }
        }

        /* Auto-refresh indicator */
        .refresh-info {
            text-align: center;
            color: #666;
            font-size: 0.9rem;
            margin-top: 1rem;
        }
    </style>
</head>
<body>
    <header class="public-header">
        <nav class="public-back-nav">
            <a href="/" data-testid="back-to-list">&larr; All Tournaments</a>
        </nav>
        <h1><?= htmlspecialchars($tournament->name) ?></h1>
        <p class="subtitle">Table Allocations</p>
    </header>

    <main class="container">
        <!-- Round navigation -->
        <nav class="public-round-navigation">
            <?php if ($prevRound): ?>
            <a href="/<?= $tournament->id ?>/round/<?= $prevRound->roundNumber ?>" class="public-round-nav-btn">&laquo; Round <?= $prevRound->roundNumber ?></a>
            <?php else: ?>
            <span class="public-round-nav-spacer"></span>
            <?php endif; ?>
            <span class="public-round-current">Round <?= $round->roundNumber ?></span>
            <?php if ($nextRound): ?>
            <a href="/<?= $tournament->id ?>/round/<?= $nextRound->roundNumber ?>" class="public-round-nav-btn">Round <?= $nextRound->roundNumber ?> &raquo;</a>
            <?php else: ?>
            <span class="public-round-nav-spacer"></span>
            <?php endif; ?>
        </nav>

        <?php if ($hasAllocations): ?>
        <table class="allocation-table" role="grid">
            <thead>
                <tr>
                    <th class="table-number">Table</th>
                    <th class="terrain-type">Terrain</th>
                    <th class="player-name">Player 1</th>
                    <th class="player-score">Score</th>
                    <th class="vs-separator"></th>
                    <th class="player-score">Score</th>
                    <th class="player-name">Player 2</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($allocations as $allocation):
                    $isBye = $allocation->isBye();
                    $table = $allocation->getTable();
                    $player1 = $allocation->getPlayer1();
                    $player2 = $isBye ? null : $allocation->getPlayer2();
                    $terrainType = $table ? $table->getTerrainType() : null;
                    $emoji = $terrainType ? $terrainType->emoji : '';

                    // Prepare names (UX Improvement #8)
                    $p1Name = $player1 ? htmlspecialchars($player1->name) : 'Unknown';
                    $p2Name = $isBye ? '' : ($player2 ? htmlspecialchars($player2->name) : 'Unknown');
                    $p1Short = abbreviatePlayerName($p1Name);
                    $p2Short = $isBye ? '' : abbreviatePlayerName($p2Name);
                ?>
                <tr class="<?= $isBye ? 'bye-row' : '' ?>">
                    <?php if ($isBye): ?>
                    <td class="table-number bye-no-table">—</td>
                    <td class="terrain-type bye-no-table">—</td>
                    <td class="player-name">
                        <span class="player-name-full"><?= $p1Name ?></span>
                        <span class="player-name-short"><?= $p1Short ?> (<?= $allocation->player1Score ?>)</span>
                        <?php if ($player1 && $player1->faction): ?>
                        <span class="player-faction"><?= htmlspecialchars($player1->faction) ?></span>
                        <?php endif; ?>
                        <span class="bye-indicator">(Bye - no game this round)</span>
                    </td>
                    <td class="player-score"><?= $allocation->player1Score ?></td>
                    <td class="vs-separator"></td>
                    <td class="player-score"></td>
                    <td class="player-name bye-no-table">—</td>
                    <?php else: ?>
                    <td class="table-number"><?= $table ? $table->tableNumber : 'N/A' ?><?= $emoji ? ' ' . $emoji : '' ?></td>
                    <td class="terrain-type"><?= $terrainType ? htmlspecialchars($terrainType->name) : '-' ?></td>
                    <td class="player-name">
                        <span class="player-name-full"><?= $p1Name ?></span>
                        <span class="player-name-short"><?= $p1Short ?> (<?= $allocation->player1Score ?>)</span>
                        <?php if ($player1 && $player1->faction): ?>
                        <span class="player-faction"><?= htmlspecialchars($player1->faction) ?></span>
                        <?php endif; ?>
                    </td>
                    <td class="player-score"><?= $allocation->player1Score ?></td>
                    <td class="vs-separator">vs</td>
                    <td class="player-score"><?= $allocation->player2Score ?></td>
                    <td class="player-name">
                        <span class="player-name-full"><?= $p2Name ?></span>
                        <span class="player-name-short"><?= $p2Short ?> (<?= $allocation->player2Score ?>)</span>
                        <?php if ($player2 && $player2->faction): ?>
                        <span class="player-faction"><?= htmlspecialchars($player2->faction) ?></span>
                        <?php endif; ?>
                    </td>
                    <?php endif; ?>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <p class="refresh-info">
            <?= count($allocations) ?> game(s) &bull;
            Last updated: <?= date('g:i A') ?>
        </p>
        <?php else: ?>
        <div class="no-allocations">
            <p>No table allocations available for this round.</p>
            <p>Please check back later.</p>
        </div>
        <?php endif; ?>
    </main>

    <footer class="public-footer">
        Tournament Tables - Tournament Table Allocation System
    </footer>

    <script>
        // Optional: Auto-refresh every 60 seconds for live updates
        // Uncomment to enable auto-refresh
        // setTimeout(function() { location.reload(); }, 60000);
    </script>
</body>
</html>
