<?php
/**
 * Public tournaments list view.
 *
 * Shows all tournaments with at least one published round.
 *
 * Expected variables:
 * - $tournaments: Array of tournament rows with player_count
 */

$hasTournaments = !empty($tournaments);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tournaments - Tournament Tables</title>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">

    <style>
        /*
         * Public view styling for venue readability
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
            text-align: center;
        }

        .public-header h1 {
            color: white;
            margin: 0;
            font-size: 2.5rem;
            font-weight: 700;
        }

        .public-header .subtitle {
            margin: 0.5rem 0 0 0;
            font-size: 1.25rem;
            opacity: 0.9;
        }

        /* Tournament list */
        .tournament-list {
            display: grid;
            gap: 1rem;
        }

        .tournament-card {
            display: block;
            background: #f5f5f5;
            border-radius: 8px;
            padding: 1.5rem 2rem;
            text-decoration: none;
            color: inherit;
            transition: transform 0.1s, box-shadow 0.2s;
        }

        .tournament-card:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
            background: #eef7ff;
        }

        .tournament-card h2 {
            margin: 0;
            font-size: 1.5rem;
            color: var(--primary);
        }

        .tournament-meta {
            margin-top: 0.5rem;
            color: #666;
            font-size: 1.1rem;
        }

        /* No tournaments message */
        .no-tournaments {
            background: #fff3cd;
            border: 1px solid #ffeeba;
            border-radius: 8px;
            padding: 2rem;
            text-align: center;
            font-size: 1.25rem;
            color: #856404;
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

        /* Responsive adjustments */
        @media (max-width: 768px) {
            .public-header h1 {
                font-size: 1.75rem;
            }

            .tournament-card {
                padding: 1rem 1.5rem;
            }

            .tournament-card h2 {
                font-size: 1.25rem;
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

            .tournament-card h2 {
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header class="public-header">
        <h1>Tournaments</h1>
        <p class="subtitle">Table Allocations</p>
    </header>

    <main class="container">
        <?php if ($hasTournaments): ?>
        <div class="tournament-list">
            <?php foreach ($tournaments as $tournament): ?>
            <a href="/public/<?= (int) $tournament['id'] ?>" class="tournament-card">
                <h2><?= htmlspecialchars($tournament['name']) ?></h2>
                <div class="tournament-meta">
                    <?= (int) $tournament['player_count'] ?> players
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else: ?>
        <div class="no-tournaments">
            <p>No tournaments available.</p>
            <p>Please check back later.</p>
        </div>
        <?php endif; ?>
    </main>

    <footer class="public-footer">
        Tournament Tables - Tournament Table Allocation System
    </footer>
</body>
</html>
