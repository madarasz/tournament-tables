<?php

declare(strict_types=1);

/**
 * Public tournaments list view.
 *
 * Shows all tournaments with at least one published round.
 *
 * Expected variables:
 * - $tournaments: Array of tournament rows with player_count
 */

$hasTournaments = !empty($tournaments);
$title = 'Tournaments';
$isPublic = true;

// Start capturing content for layout
ob_start();
?>
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

<header class="public-header">
    <h1 data-testid="tournaments-heading">Tournaments</h1>
    <p class="subtitle">Table Allocations</p>
</header>

<?php if ($hasTournaments): ?>
<div class="tournament-list">
    <?php foreach ($tournaments as $tournament): ?>
    <a href="/<?= (int) $tournament['id'] ?>" class="tournament-card" data-testid="tournament-link-<?= htmlspecialchars($tournament['name']) ?>">
        <h2><?= htmlspecialchars($tournament['name']) ?></h2>
        <div class="tournament-meta" data-testid="player-count-<?= htmlspecialchars($tournament['name']) ?>">
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
<?php
$content = ob_get_clean();

// Include the layout template
include __DIR__ . '/../layout.php';
