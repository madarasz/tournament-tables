<?php
/**
 * Public tournament view.
 *
 * Shows tournament info and round selector for published rounds.
 * Reference: specs/001-table-allocation/tasks.md#T080, T082, T083
 *
 * Expected variables:
 * - $tournament: Tournament model
 * - $publishedRounds: Array of Round models (only published)
 */

$pageTitle = htmlspecialchars($tournament->name);
$hasPublishedRounds = !empty($publishedRounds);
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= $pageTitle ?> - Tournament Tables</title>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">

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

        /* Tournament info card */
        .tournament-info {
            background: #f5f5f5;
            border-radius: 8px;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
            font-size: 1.1rem;
        }

        .tournament-info-item {
            display: inline-block;
            margin-right: 2rem;
        }

        .tournament-info-label {
            font-weight: 600;
            color: #666;
        }

        /* Round selector - large touch-friendly buttons (T082) */
        .round-selector {
            margin: 2rem 0;
        }

        .round-selector h2 {
            font-size: 1.75rem;
            margin-bottom: 1rem;
        }

        .round-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 1rem;
        }

        .round-button {
            display: inline-block;
            padding: 1.5rem 3rem;
            font-size: 1.5rem;
            font-weight: 600;
            text-decoration: none;
            color: white;
            background: var(--primary);
            border-radius: 8px;
            text-align: center;
            transition: transform 0.1s, background 0.2s;
            min-width: 150px;
        }

        .round-button:hover {
            background: var(--primary-hover);
            transform: scale(1.02);
            color: white;
        }

        .round-button:active {
            transform: scale(0.98);
        }

        /* No rounds message */
        .no-rounds {
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

            .round-button {
                padding: 1rem 2rem;
                font-size: 1.25rem;
                flex: 1 1 calc(50% - 0.5rem);
            }

            .tournament-info-item {
                display: block;
                margin-bottom: 0.5rem;
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

            .round-button {
                padding: 2rem 4rem;
                font-size: 1.75rem;
            }
        }
    </style>
</head>
<body>
    <header class="public-header">
        <h1><?= $pageTitle ?></h1>
        <p class="subtitle">Table Allocations</p>
    </header>

    <main class="container">
        <div class="tournament-info">
            <span class="tournament-info-item">
                <span class="tournament-info-label">Tables:</span> <?= $tournament->tableCount ?>
            </span>
            <?php if ($hasPublishedRounds): ?>
            <span class="tournament-info-item">
                <span class="tournament-info-label">Published Rounds:</span> <?= count($publishedRounds) ?>
            </span>
            <?php endif; ?>
        </div>

        <section class="round-selector">
            <h2>Select Round</h2>

            <?php if ($hasPublishedRounds): ?>
            <div class="round-buttons">
                <?php foreach ($publishedRounds as $round): ?>
                <a href="/public/<?= $tournament->id ?>/round/<?= $round->roundNumber ?>" class="round-button">
                    Round <?= $round->roundNumber ?>
                </a>
                <?php endforeach; ?>
            </div>
            <?php else: ?>
            <div class="no-rounds">
                <p>No rounds have been published yet.</p>
                <p>Please check back later for table allocations.</p>
            </div>
            <?php endif; ?>
        </section>
    </main>

    <footer class="public-footer">
        Tournament Tables - Tournament Table Allocation System
    </footer>
</body>
</html>
