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

// Detect table collisions (multiple allocations with same table)
$tableUsage = [];
$tableCollisions = [];
foreach ($allocations as $allocation) {
    $tableId = $allocation->tableId;
    if (!isset($tableUsage[$tableId])) {
        $tableUsage[$tableId] = [];
    }
    $tableUsage[$tableId][] = $allocation->id;
}
foreach ($tableUsage as $tableId => $allocationIds) {
    if (count($allocationIds) > 1) {
        foreach ($allocationIds as $allocId) {
            $tableCollisions[$allocId] = true;
        }
    }
}
$hasTableCollisions = !empty($tableCollisions);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script>
        // Helper to get cookie value - must be defined before HTMX buttons
        function getCookie(name) {
            var value = '; ' + document.cookie;
            var parts = value.split('; ' + name + '=');
            if (parts.length === 2) return parts.pop().split(';').shift();
            return '';
        }

        // Add admin token to all HTMX requests
        document.addEventListener('htmx:configRequest', function(event) {
            var token = getCookie('admin_token');
            if (token) {
                event.detail.headers['X-Admin-Token'] = token;
            }
        });
    </script>
    <style>
        .conflict-table-collision,
        .conflict-table-collision:nth-child(odd),
        .conflict-table-collision:nth-child(even) {
            background-color: hsla(0, 77%, 61%, 1.00) !important;
            border-left: 4px solid #c62828;
        }
        .conflict-table-reuse,
        .conflict-table-reuse:nth-child(odd),
        .conflict-table-reuse:nth-child(even) {
            background-color: #ffcdd2 !important;
            border-left: 4px solid #f44336;
        }
        .conflict-terrain-reuse,
        .conflict-terrain-reuse:nth-child(odd),
        .conflict-terrain-reuse:nth-child(even) {
            background-color: #ffe0b2 !important;
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
                <li><strong>Tournament Tables</strong></li>
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
                <?php if ($hasTableCollisions): ?>
                    <span class="conflict-badge">Table Collision!</span>
                <?php elseif ($hasConflicts): ?>
                    <span class="conflict-badge"><?= count($conflicts) ?> Conflict(s)</span>
                <?php endif; ?>
            </h1>
            <p><?= htmlspecialchars($tournament->name) ?></p>
        </header>

        <?php if ($isPublished): ?>
        <!-- Warning when editing published round (FR-013, T076) -->
        <article style="background-color: #fff3e0; border-left: 4px solid #ff9800;">
            <h4>⚠️ Published Round</h4>
            <p>This round has been published and is visible to players. Any changes you make will be immediately visible to all players viewing the allocations.</p>
        </article>
        <?php endif; ?>

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
                <!-- Publish button (disabled if table collisions exist) -->
                <button
                    hx-post="/api/tournaments/<?= $tournament->id ?>/rounds/<?= $round->roundNumber ?>/publish"
                    hx-target=".publish-status"
                    hx-swap="innerHTML"
                    hx-confirm="Publish allocations? Players will be able to see their table assignments."
                    class="contrast"
                    id="publish-button"
                    <?= $hasTableCollisions ? 'disabled title="Cannot publish while table collisions exist"' : '' ?>
                >
                    Publish Allocations
                </button>
                <?php if ($hasTableCollisions): ?>
                <small style="color: #d32f2f; line-height: 2.5;">Fix table collisions before publishing</small>
                <?php endif; ?>
                <?php else: ?>
                <span class="publish-status published-badge">Already Published</span>
                <?php endif; ?>

                <span class="publish-status"></span>
            </div>

            <!-- Swap controls (T074) -->
            <?php if (!empty($allocations)): ?>
            <div class="action-buttons" style="margin-top: 1em;">
                <button
                    onclick="swapSelectedTables()"
                    class="secondary"
                    id="swap-button"
                    disabled
                >
                    Swap Selected Tables
                </button>
                <small id="swap-status" style="line-height: 2.5;">Select two allocations to swap</small>
            </div>
            <?php endif; ?>
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
                        <th style="width: 50px;">Select</th>
                        <th>Table</th>
                        <th>Terrain</th>
                        <th>Player 1</th>
                        <th>Score</th>
                        <th>Player 2</th>
                        <th>Score</th>
                        <th>Status</th>
                        <th style="width: 150px;">Change Table</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Get all tables for dropdown
                    $allTables = \TournamentTables\Models\Table::findByTournament($tournament->id);

                    foreach ($allocations as $allocation):
                        $allocationConflicts = $allocation->getConflicts();
                        $hasTableReuse = false;
                        $hasTerrainReuse = false;
                        $hasTableCollision = isset($tableCollisions[$allocation->id]);
                        foreach ($allocationConflicts as $c) {
                            if ($c['type'] === 'TABLE_REUSE') $hasTableReuse = true;
                            if ($c['type'] === 'TERRAIN_REUSE') $hasTerrainReuse = true;
                        }
                        $rowClass = '';
                        if ($hasTableCollision) $rowClass = 'conflict-table-collision';
                        elseif ($hasTableReuse) $rowClass = 'conflict-table-reuse';
                        elseif ($hasTerrainReuse) $rowClass = 'conflict-terrain-reuse';

                        $table = $allocation->getTable();
                        $player1 = $allocation->getPlayer1();
                        $player2 = $allocation->getPlayer2();
                        $terrainType = $table ? $table->getTerrainType() : null;
                    ?>
                    <tr class="<?= $rowClass ?>">
                        <!-- Checkbox for swap selection (T074) -->
                        <td>
                            <input
                                type="checkbox"
                                class="swap-checkbox"
                                data-allocation-id="<?= $allocation->id ?>"
                                onchange="updateSwapButton()"
                            />
                        </td>

                        <td><strong><?= $table ? $table->tableNumber : 'N/A' ?></strong></td>
                        <td class="terrain-type"><?= $terrainType ? htmlspecialchars($terrainType->name) : '-' ?></td>
                        <td><?= $player1 ? htmlspecialchars($player1->name) : 'Unknown' ?></td>
                        <td class="score"><?= $allocation->player1Score ?></td>
                        <td><?= $player2 ? htmlspecialchars($player2->name) : 'Unknown' ?></td>
                        <td class="score"><?= $allocation->player2Score ?></td>
                        <td>
                            <?php if ($hasTableCollision): ?>
                                <span title="Multiple pairings assigned to same table" style="color: #d32f2f; font-weight: bold;">&#9888; TABLE COLLISION</span>
                            <?php elseif ($hasTableReuse): ?>
                                <span title="Table reuse conflict">&#9888; Table Reuse</span>
                            <?php elseif ($hasTerrainReuse): ?>
                                <span title="Terrain reuse">&#9888; Terrain Reuse</span>
                            <?php else: ?>
                                &#10003; OK
                            <?php endif; ?>
                        </td>

                        <!-- Inline table edit dropdown (T073) -->
                        <td>
                            <select
                                onchange="changeTableAssignment(<?= $allocation->id ?>, this.value)"
                                style="width: 100%; font-size: 0.875em;"
                            >
                                <?php foreach ($allTables as $t): ?>
                                    <option
                                        value="<?= $t->id ?>"
                                        <?= ($table && $t->id === $table->id) ? 'selected' : '' ?>
                                    >
                                        Table <?= $t->tableNumber ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <footer>
            <small>
                Tournament Tables - Tournament Table Allocation
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

        // Update swap button state based on checkbox selection (T074)
        function updateSwapButton() {
            var checkboxes = document.querySelectorAll('.swap-checkbox:checked');
            var button = document.getElementById('swap-button');
            var status = document.getElementById('swap-status');

            if (checkboxes.length === 2) {
                button.disabled = false;
                status.textContent = 'Ready to swap ' + checkboxes.length + ' allocations';
            } else if (checkboxes.length === 1) {
                button.disabled = true;
                status.textContent = 'Select one more allocation to swap';
            } else {
                button.disabled = true;
                status.textContent = 'Select two allocations to swap';
            }
        }

        // Swap selected tables (T074)
        function swapSelectedTables() {
            var checkboxes = document.querySelectorAll('.swap-checkbox:checked');

            if (checkboxes.length !== 2) {
                alert('Please select exactly two allocations to swap');
                return;
            }

            var allocationId1 = parseInt(checkboxes[0].dataset.allocationId);
            var allocationId2 = parseInt(checkboxes[1].dataset.allocationId);

            if (confirm('Swap tables for these two pairings?')) {
                fetch('/api/allocations/swap', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Admin-Token': getCookie('admin_token')
                    },
                    body: JSON.stringify({
                        allocationId1: allocationId1,
                        allocationId2: allocationId2
                    })
                })
                .then(response => response.json())
                .then(data => {
                    if (data.error) {
                        alert('Error: ' + data.message);
                    } else {
                        location.reload();
                    }
                })
                .catch(error => {
                    alert('Failed to swap tables: ' + error.message);
                });
            }
        }

        // Change table assignment (T073)
        function changeTableAssignment(allocationId, newTableId) {
            fetch('/api/allocations/' + allocationId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Token': getCookie('admin_token')
                },
                body: JSON.stringify({
                    tableId: parseInt(newTableId)
                })
            })
            .then(response => response.json())
            .then(data => {
                if (data.error) {
                    alert('Error: ' + data.message);
                    location.reload(); // Reload to reset dropdown
                } else {
                    // Reload page - conflicts will be shown in the UI
                    location.reload();
                }
            })
            .catch(error => {
                alert('Failed to change table assignment: ' + error.message);
                location.reload();
            });
        }
    </script>
</body>
</html>
