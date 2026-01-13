<?php
/**
 * Round management view.
 *
 * Displays pairings and allocations with HTMX controls for:
 * - Refresh from BCP (FR-015)
 * - Generate allocations (FR-007)
 * - Conflict highlighting (FR-010)
 *
 * Reference: specs/001-table-allocation/research.md#implementation-notes
 *
 * Expected variables:
 * - $tournament: Tournament model
 * - $round: Round model
 * - $allocations: Array of Allocation models
 * - $conflicts: Array of conflict objects
 */

$pageTitle = "{$tournament->name} - Round {$round->roundNumber}";
$hasConflicts = !empty($conflicts);
$isPublished = $round->isPublished;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <style>
        .conflict-table-reuse {
            background-color: #ffebee;
            border-left: 4px solid #f44336;
        }
        .conflict-terrain-reuse {
            background-color: #fff3e0;
            border-left: 4px solid #ff9800;
        }
        .published-badge {
            display: inline-block;
            padding: 0.25em 0.5em;
            background-color: #4caf50;
            color: white;
            border-radius: 4px;
            font-size: 0.875em;
            margin-left: 0.5em;
        }
        .conflict-badge {
            display: inline-block;
            padding: 0.25em 0.5em;
            background-color: #f44336;
            color: white;
            border-radius: 4px;
            font-size: 0.875em;
            margin-left: 0.5em;
        }
        .allocation-table {
            width: 100%;
        }
        .allocation-table th,
        .allocation-table td {
            padding: 0.75em;
            text-align: left;
        }
        .score {
            font-weight: bold;
            color: #1976d2;
        }
        .terrain-type {
            font-style: italic;
            color: #666;
        }
        .action-buttons {
            display: flex;
            gap: 0.5em;
            margin-bottom: 1em;
        }
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }
        .conflict-list {
            margin-top: 1em;
            padding: 1em;
            background-color: #fff3e0;
            border-radius: 4px;
        }
        .conflict-item {
            padding: 0.5em 0;
            border-bottom: 1px solid #ffe0b2;
        }
        .conflict-item:last-child {
            border-bottom: none;
        }
        .conflict-type {
            font-weight: bold;
            color: #e65100;
        }
        #allocation-results {
            min-height: 200px;
        }
        .htmx-indicator {
            display: none;
        }
        .htmx-request .htmx-indicator {
            display: inline-block;
        }
        .htmx-request.htmx-indicator {
            display: inline-block;
        }
    </style>
</head>
<body>
    <main class="container">
        <nav>
            <ul>
                <li><strong>Kill Team Tables</strong></li>
            </ul>
            <ul>
                <li><a href="/tournament/<?= $tournament->id ?>">Tournament Dashboard</a></li>
            </ul>
        </nav>

        <header>
            <h1>
                Round <?= $round->roundNumber ?>
                <?php if ($isPublished): ?>
                    <span class="published-badge">Published</span>
                <?php endif; ?>
                <?php if ($hasConflicts): ?>
                    <span class="conflict-badge"><?= count($conflicts) ?> Conflict(s)</span>
                <?php endif; ?>
            </h1>
            <p><?= htmlspecialchars($tournament->name) ?></p>
        </header>

        <section>
            <div class="action-buttons">
                <!-- Refresh from BCP button -->
                <button
                    hx-post="/api/tournaments/<?= $tournament->id ?>/rounds/<?= $round->roundNumber ?>/import"
                    hx-target="#allocation-results"
                    hx-swap="innerHTML"
                    hx-indicator="#refresh-indicator"
                    hx-confirm="This will re-import pairings from BCP. Existing allocations will be cleared. Continue?"
                    class="secondary"
                >
                    <span id="refresh-indicator" class="htmx-indicator">Loading...</span>
                    <span>Refresh from BCP</span>
                </button>

                <!-- Generate allocations button -->
                <button
                    hx-post="/api/tournaments/<?= $tournament->id ?>/rounds/<?= $round->roundNumber ?>/generate"
                    hx-target="#allocation-results"
                    hx-swap="innerHTML"
                    hx-indicator="#generate-indicator"
                    class="primary"
                >
                    <span id="generate-indicator" class="htmx-indicator">Generating...</span>
                    <span>Generate Allocations</span>
                </button>

                <?php if (!$isPublished): ?>
                <!-- Publish button -->
                <button
                    hx-post="/api/tournaments/<?= $tournament->id ?>/rounds/<?= $round->roundNumber ?>/publish"
                    hx-target="#publish-status"
                    hx-swap="innerHTML"
                    hx-confirm="Publish allocations? Players will be able to see their table assignments."
                    class="contrast"
                >
                    Publish Allocations
                </button>
                <?php else: ?>
                <span id="publish-status" class="published-badge">Already Published</span>
                <?php endif; ?>

                <span id="publish-status"></span>
            </div>
        </section>

        <?php if ($hasConflicts): ?>
        <section class="conflict-list">
            <h3>Conflicts</h3>
            <p>The following constraint violations were detected. These allocations represent the best available options.</p>
            <?php foreach ($conflicts as $conflict): ?>
            <div class="conflict-item">
                <span class="conflict-type"><?= htmlspecialchars($conflict['type']) ?>:</span>
                <?= htmlspecialchars($conflict['message']) ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <section id="allocation-results">
            <?php if (empty($allocations)): ?>
            <article>
                <p>No allocations yet. Click "Generate Allocations" to create table assignments.</p>
            </article>
            <?php else: ?>
            <table class="allocation-table" role="grid">
                <thead>
                    <tr>
                        <th>Table</th>
                        <th>Terrain</th>
                        <th>Player 1</th>
                        <th>Score</th>
                        <th>Player 2</th>
                        <th>Score</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($allocations as $allocation):
                        $allocationConflicts = $allocation->getConflicts();
                        $hasTableReuse = false;
                        $hasTerrainReuse = false;
                        foreach ($allocationConflicts as $c) {
                            if ($c['type'] === 'TABLE_REUSE') $hasTableReuse = true;
                            if ($c['type'] === 'TERRAIN_REUSE') $hasTerrainReuse = true;
                        }
                        $rowClass = '';
                        if ($hasTableReuse) $rowClass = 'conflict-table-reuse';
                        elseif ($hasTerrainReuse) $rowClass = 'conflict-terrain-reuse';

                        $table = $allocation->getTable();
                        $player1 = $allocation->getPlayer1();
                        $player2 = $allocation->getPlayer2();
                        $terrainType = $table ? $table->getTerrainType() : null;
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <td><strong><?= $table ? $table->tableNumber : 'N/A' ?></strong></td>
                        <td class="terrain-type"><?= $terrainType ? htmlspecialchars($terrainType->name) : '-' ?></td>
                        <td><?= $player1 ? htmlspecialchars($player1->name) : 'Unknown' ?></td>
                        <td class="score"><?= $allocation->player1Score ?></td>
                        <td><?= $player2 ? htmlspecialchars($player2->name) : 'Unknown' ?></td>
                        <td class="score"><?= $allocation->player2Score ?></td>
                        <td>
                            <?php if ($hasTableReuse): ?>
                                <span title="Table reuse conflict">&#9888; Table Reuse</span>
                            <?php elseif ($hasTerrainReuse): ?>
                                <span title="Terrain reuse">&#9888; Terrain Reuse</span>
                            <?php else: ?>
                                &#10003; OK
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <footer>
            <small>
                Kill Team Tables - Tournament Table Allocation
                | Round <?= $round->roundNumber ?>
                | <?= count($allocations) ?> allocation(s)
            </small>
        </footer>
    </main>

    <script>
        // Handle HTMX responses
        document.body.addEventListener('htmx:afterRequest', function(event) {
            if (event.detail.successful) {
                // Reload page to show updated allocations
                if (event.detail.xhr.responseURL.includes('/generate') ||
                    event.detail.xhr.responseURL.includes('/import') ||
                    event.detail.xhr.responseURL.includes('/publish')) {
                    location.reload();
                }
            } else {
                // Show error
                var response = JSON.parse(event.detail.xhr.responseText || '{}');
                alert('Error: ' + (response.message || 'Unknown error'));
            }
        });
    </script>
</body>
</html>
