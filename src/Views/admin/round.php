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
declare(strict_types=1);

use TournamentTables\Services\CsrfService;

$pageTitle = "{$tournament->name} - Round {$round->roundNumber}";
$isPublished = $round->isPublished;

// Separate terrain reuse (warnings) from actual conflicts
$warnings = [];
$actualConflicts = [];
foreach ($conflicts as $conflict) {
    if ($conflict['type'] === 'TERRAIN_REUSE') {
        $warnings[] = $conflict;
    } else {
        $actualConflicts[] = $conflict;
    }
}
$conflicts = $actualConflicts;
$hasConflicts = !empty($conflicts);
$hasWarnings = !empty($warnings);

// Calculate prev/next rounds for navigation
$prevRound = null;
$nextRound = null;
foreach ($rounds as $r) {
    if ($r->roundNumber === $round->roundNumber - 1) {
        $prevRound = $r;
    }
    if ($r->roundNumber === $round->roundNumber + 1) {
        $nextRound = $r;
    }
}

// Check for flash message from import redirect
$justImported = isset($_GET['imported']) && $_GET['imported'] === '1';
$importedPairings = $justImported && isset($_GET['pairings']) ? (int)$_GET['pairings'] : 0;

/**
 * Abbreviate player name for mobile display.
 * "Tamas Horvath" -> "T. Horvath"
 * "John" -> "John" (single name, no change)
 */
function abbreviateName($fullName) {
    $parts = explode(' ', trim($fullName));
    if (count($parts) < 2) {
        return $fullName;
    }
    // First initial + last name(s)
    $firstInitial = mb_substr($parts[0], 0, 1) . '.';
    array_shift($parts);
    return $firstInitial . ' ' . implode(' ', $parts);
}

/**
 * Check if a specific player has terrain reuse conflict.
 * Returns true if the player name appears in a TERRAIN_REUSE conflict message.
 */
function playerHasTerrainReuse($playerName, $conflicts) {
    foreach ($conflicts as $c) {
        if ($c['type'] === 'TERRAIN_REUSE' && strpos($c['message'], $playerName . ' ') === 0) {
            return true;
        }
    }
    return false;
}

/**
 * Format BCP table difference indicator.
 * Returns array with 'emoji' and 'detail' keys.
 */
function formatBcpDifference($allocation) {
    if (!$allocation->hasBcpTableDifference()) {
        return ['emoji' => '', 'detail' => ''];
    }
    return [
        'emoji' => ' 🆕',
        'detail' => '<small class="bcp-diff">• BCP: Table ' . $allocation->bcpTableNumber . '</small>'
    ];
}

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
    <?= CsrfService::getMetaTag() ?>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <link rel="stylesheet" href="/css/app.css">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="/js/utils.js"></script>
    <script src="/js/form-utils.js"></script>
    <script>
        // Current tournament ID for token retrieval
        var currentTournamentId = <?= $tournament->id ?>;

        // Add admin token to all HTMX requests
        document.addEventListener('htmx:configRequest', function(event) {
            var token = getAdminToken(currentTournamentId);
            if (token) {
                event.detail.headers['X-Admin-Token'] = token;
            }
            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            if (csrfToken) {
                event.detail.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
            }
        });
    </script>
</head>
<body>
    <nav>
        <div class="container">
            <ul>
                <li><a href="/admin" class="brand">Tournament Tables</a></li>
                <li class="nav-right">
                    <a href="/admin/tournament/create">New Tournament</a>
                    <a href="/admin/login">Login</a>
                </li>
            </ul>
            <a href="/admin/tournament/<?= $tournament->id ?>" class="back-link">&laquo; <?= htmlspecialchars($tournament->name) ?></a>
        </div>
    </nav>

    <div class="nav-page-name full-bleed">
        <h1>
            Round <?= $round->roundNumber ?>
            <?php if ($isPublished): ?>
                <span class="round-published-badge">Published</span>
            <?php endif; ?>
            <?php if ($hasTableCollisions): ?>
                <span class="round-conflict-badge">Table Collision!</span>
            <?php elseif ($hasConflicts): ?>
                <span class="round-conflict-badge"><?= count($conflicts) ?> Conflict(s)</span>
            <?php endif; ?>
        </h1>
    </div>

    <main class="container">

        <?php if ($justImported): ?>
        <!-- Success message for round import (FR-015, UX Improvement #5) -->
        <article class="success-article" id="import-success-message">
            <p><strong>Round <?= $round->roundNumber ?> imported successfully</strong> — <?= $importedPairings ?> pairing<?= $importedPairings !== 1 ? 's' : '' ?> loaded from BCP.</p>
        </article>
        <?php endif; ?>

        <?php if ($isPublished): ?>
        <!-- Warning when editing published round (FR-013, T076) -->
        <article class="warning-article">
            <h4 style="color: #666;">⚠️ Published Round</h4>
            <p style="color: #666;">This round has been published and is visible to players. Any changes you make will be immediately visible to all players viewing the allocations.</p>
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
                <?php endif; ?>

                <span class="publish-status"></span>
            </div>
        </section>

        <?php if ($hasConflicts): ?>
        <section class="conflict-list">
            <h3>Conflicts</h3>
            <?php foreach ($conflicts as $conflict): ?>
            <div class="conflict-item">
                <span class="conflict-type"><?= htmlspecialchars($conflict['type']) ?>:</span>
                <?= htmlspecialchars($conflict['message']) ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <?php if ($hasWarnings): ?>
        <section class="warning-list" style="padding: 0.5em; background-color: #f5f5f5b7; border-radius: 4px; border-left: 4px solid #9e9e9e;">
            <h5 style="color: #666; margin: 0;">Warnings <em>- the allocation is still valid</em></h5>
            <?php foreach ($warnings as $warning): ?>
            <div style="padding: 0.5em 0; color: #666; font-size: 0.7em;">
                <?= htmlspecialchars($warning['message']) ?>
            </div>
            <?php endforeach; ?>
        </section>
        <?php endif; ?>

        <!-- Round navigation (UX Improvement #7: side-by-side on mobile) -->
        <?php $nextRoundNumber = $round->roundNumber + 1; ?>
        <nav class="round-navigation">
            <?php if ($prevRound): ?>
            <a href="/admin/tournament/<?= $tournament->id ?>/round/<?= $prevRound->roundNumber ?>" class="round-nav-btn round-nav-prev">&laquo; Round <?= $prevRound->roundNumber ?></a>
            <?php else: ?>
            <span class="round-nav-spacer"></span>
            <?php endif; ?>
            <?php if ($nextRound): ?>
            <a href="/admin/tournament/<?= $tournament->id ?>/round/<?= $nextRound->roundNumber ?>" class="round-nav-btn round-nav-next">Round <?= $nextRound->roundNumber ?> &raquo;</a>
            <?php else: ?>
            <!-- Import Next button when on last round -->
            <button
                type="button"
                id="import-next-button"
                class="import-next-btn"
                data-round-number="<?= $nextRoundNumber ?>"
                data-tournament-id="<?= $tournament->id ?>"
            >
                <span id="import-next-indicator" style="display: none;">Importing...</span>
                <span id="import-next-text">Import Round <?= $nextRoundNumber ?> &raquo;</span>
            </button>
            <?php endif; ?>
        </nav>
        <!-- Error container for import next button -->
        <div id="import-next-result" class="import-next-result"></div>

        <section id="allocation-results">
            <?php if (empty($allocations)): ?>
            <article>
                <p>No allocations yet. Click "Generate Allocations" to create table assignments.</p>
            </article>
            <?php else: ?>
            <table class="allocation-table" role="grid">
                <thead>
                    <tr>
                        <th class="select-cell">
                            <span class="header-full">Select</span>
                            <span class="header-short" aria-label="Select"></span>
                        </th>
                        <th class="table-cell">
                            <span>Table</span>
                        </th>
                        <th>
                            <span>Player 1</span>
                        </th>
                        <th>
                            <span>Player 2</span>
                        </th>
                        <th class="change-cell">
                            <span class="header-full">Change</span>
                            <span class="header-short" aria-label="Change"></span>
                        </th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    // Get all tables for dropdown
                    $allTables = \TournamentTables\Models\Table::findByTournament($tournament->id);

                    foreach ($allocations as $allocation):
                        $isBye = $allocation->isBye();
                        $allocationConflicts = $allocation->getConflicts();
                        $hasTableReuse = false;
                        $hasTerrainReuse = false;
                        $hasTableCollision = !$isBye && isset($tableCollisions[$allocation->id]);
                        foreach ($allocationConflicts as $c) {
                            if ($c['type'] === 'TABLE_REUSE') $hasTableReuse = true;
                            if ($c['type'] === 'TERRAIN_REUSE') $hasTerrainReuse = true;
                        }
                        $rowClass = '';
                        if ($isBye) $rowClass = 'bye-row';
                        elseif ($hasTableCollision) $rowClass = 'conflict-table-collision';
                        elseif ($hasTableReuse) $rowClass = 'conflict-table-reuse';
                        // Note: terrain reuse no longer highlights rows - shown via emoji instead

                        $table = $allocation->getTable();
                        $player1 = $allocation->getPlayer1();
                        $player2 = $isBye ? null : $allocation->getPlayer2();
                        $terrainType = $table ? $table->getTerrainType() : null;
                        $terrainEmoji = $terrainType ? $terrainType->emoji : null;
                    ?>
                    <?php
                        // Prepare player name abbreviations (FirstName L.)
                        $player1Name = $player1 ? htmlspecialchars($player1->name) : 'Unknown';
                        $player2Name = $player2 ? htmlspecialchars($player2->name) : 'Unknown';
                        $player1Short = abbreviateName($player1Name);
                        $player2Short = abbreviateName($player2Name);
                        $terrainName = $terrainType ? htmlspecialchars($terrainType->name) : null;
                    ?>
                    <tr class="<?= $rowClass ?>" data-allocation-id="<?= $allocation->id ?>">
                        <!-- Checkbox for swap selection (T074) - disabled for bye -->
                        <td class="select-cell">
                            <?php if ($isBye): ?>
                            <input
                                type="checkbox"
                                class="swap-checkbox"
                                disabled
                                title="Cannot swap bye allocations"
                                aria-label="Cannot swap bye allocations"
                            />
                            <?php else: ?>
                            <input
                                type="checkbox"
                                class="swap-checkbox"
                                data-allocation-id="<?= $allocation->id ?>"
                                onchange="updateSwapButton()"
                                aria-label="Select for swap"
                            />
                            <?php endif; ?>
                        </td>

                        <!-- Table number with terrain (or "No Table" for bye) -->
                        <?php $bcpDiff = $isBye ? ['emoji' => '', 'detail' => ''] : formatBcpDifference($allocation); ?>
                        <td class="table-cell" title="<?= $isBye ? 'Bye - no table assigned' : ($terrainName ? "Table {$table->tableNumber} ({$terrainName})" : "Table " . ($table ? $table->tableNumber : 'N/A')) ?>">
                            <?php if ($isBye): ?>
                                <span class="bye-indicator">BYE</span>
                            <?php elseif ($table): ?>
                                <span><?= $bcpDiff['emoji'] ?> Table <?= $table->tableNumber ?><?= $terrainEmoji ? ' ' . $terrainEmoji : '' ?></span>
                                <?php if ($terrainName): ?>
                                    <span class="terrain-suffix header-full">(<?= $terrainName ?>)</span>
                                <?php endif; ?>
                                <?= $bcpDiff['detail'] ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>

                        <!-- Player 1 with total score and faction -->
                        <?php $p1TerrainReuse = playerHasTerrainReuse($player1Name, $allocationConflicts); ?>
                        <td title="<?= $player1Name ?><?= $player1 && $player1->faction ? ' (' . htmlspecialchars($player1->faction) . ')' : '' ?> - Score: <?= $player1 ? $player1->totalScore : 0 ?><?= $p1TerrainReuse ? ' - Already experienced this terrain' : '' ?>">
                            <span class="player-name">
                                <span class="player-name-full"><?= $player1Name ?><?= $p1TerrainReuse ? ' <span title="Already experienced this terrain">😑</span>' : '' ?></span>
                                <span class="player-name-short"><?= $player1Short ?><?= $p1TerrainReuse ? ' 😑' : '' ?></span>
                            </span>
                            <span class="player-score">(<?= $player1 ? $player1->totalScore : 0 ?>)</span>
                            <?php if ($player1 && $player1->faction): ?>
                            <span class="player-faction"><?= htmlspecialchars($player1->faction) ?></span>
                            <?php endif; ?>
                        </td>
                        <!-- Player 2 with total score and faction (or empty for bye) -->
                        <?php if ($isBye): ?>
                        <td class="bye-no-table" title="Bye - no opponent">
                            <span style="color: #9e9e9e; font-style: italic;">No opponent</span>
                        </td>
                        <?php else: ?>
                        <?php $p2TerrainReuse = playerHasTerrainReuse($player2Name, $allocationConflicts); ?>
                        <td title="<?= $player2Name ?><?= $player2 && $player2->faction ? ' (' . htmlspecialchars($player2->faction) . ')' : '' ?> - Score: <?= $player2 ? $player2->totalScore : 0 ?><?= $p2TerrainReuse ? ' - Already experienced this terrain' : '' ?>">
                            <span class="player-name">
                                <span class="player-name-full"><?= $player2Name ?><?= $p2TerrainReuse ? ' <span title="Already experienced this terrain">😑</span>' : '' ?></span>
                                <span class="player-name-short"><?= $player2Short ?><?= $p2TerrainReuse ? ' 😑' : '' ?></span>
                            </span>
                            <span class="player-score">(<?= $player2 ? $player2->totalScore : 0 ?>)</span>
                            <?php if ($player2 && $player2->faction): ?>
                            <span class="player-faction"><?= htmlspecialchars($player2->faction) ?></span>
                            <?php endif; ?>
                        </td>
                        <?php endif; ?>

                        <!-- Change table - dropdown on desktop, button on mobile (disabled for bye) -->
                        <td class="change-cell">
                            <?php if ($isBye): ?>
                            <span class="bye-no-table" title="Bye - no table to change">—</span>
                            <?php else: ?>
                            <!-- Desktop dropdown -->
                            <select
                                class="change-table-dropdown"
                                onchange="changeTableAssignment(<?= $allocation->id ?>, this.value)"
                                aria-label="Change table assignment"
                            >
                                <?php foreach ($allTables as $t):
                                    $tTerrain = $t->getTerrainType();
                                    $tEmoji = $tTerrain ? $tTerrain->emoji : null;
                                ?>
                                    <option
                                        value="<?= $t->id ?>"
                                        <?= ($table && $t->id === $table->id) ? 'selected' : '' ?>
                                    >
                                        T<?= $t->tableNumber ?><?= $tEmoji ? ' ' . $tEmoji : '' ?><?= $tTerrain ? ' (' . htmlspecialchars($tTerrain->name) . ')' : '' ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <!-- Mobile edit button -->
                            <button
                                type="button"
                                class="change-table-btn"
                                onclick="openTableModal(<?= $allocation->id ?>, <?= $table ? $table->id : 'null' ?>, <?= htmlspecialchars(json_encode($player1 ? $player1->name : 'Unknown'), ENT_QUOTES) ?>, <?= htmlspecialchars(json_encode($player2 ? $player2->name : 'Unknown'), ENT_QUOTES) ?>)"
                                aria-label="Change table"
                                title="Change table"
                            >&#9998;</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
        </section>

        <!-- Swap controls (T074) - sticky on mobile -->
        <?php if (!empty($allocations)): ?>
        <div class="swap-controls" id="swap-controls">
            <button
                onclick="swapSelectedTables()"
                class="secondary"
                id="swap-button"
                disabled
            >
                Swap Selected
            </button>
            <span class="swap-status" id="swap-status">Select 2 to swap</span>
        </div>
        <?php endif; ?>

        <!-- Modal for mobile table editing -->
        <div class="modal-overlay" id="table-modal">
            <div class="modal-content">
                <div class="modal-header">
                    <h3>Change Table</h3>
                    <button type="button" class="modal-close" onclick="closeTableModal()" aria-label="Close">&times;</button>
                </div>
                <p class="modal-description" id="modal-matchup"></p>
                <div class="table-options" id="modal-table-options">
                    <!-- Options populated by JavaScript -->
                </div>
            </div>
        </div>

        <footer>
            <small>
                Tournament Tables - Tournament Table Allocation
                | Round <?= $round->roundNumber ?>
                | <?= count($allocations) ?> allocation(s)
            </small>
        </footer>
    </main>

    <script>
        // Table data for modal (populated from PHP)
        // Use JSON_HEX_* flags to prevent XSS when embedding in script context
        var tableData = <?= json_encode(array_map(function($t) {
            $terrain = $t->getTerrainType();
            return [
                'id' => $t->id,
                'number' => $t->tableNumber,
                'terrain' => $terrain ? $terrain->name : null,
                'emoji' => $terrain ? $terrain->emoji : null
            ];
        }, \TournamentTables\Models\Table::findByTournament($tournament->id)), JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

        // Current modal state
        var currentModalAllocationId = null;
        var currentModalTableId = null;

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

            if (!button || !status) return;

            if (checkboxes.length === 2) {
                button.disabled = false;
                status.textContent = 'Swap ' + checkboxes.length + ' selected';
            } else if (checkboxes.length === 1) {
                button.disabled = true;
                status.textContent = 'Select 1 more';
            } else if (checkboxes.length > 2) {
                button.disabled = true;
                status.textContent = 'Select only 2';
            } else {
                button.disabled = true;
                status.textContent = 'Select 2 to swap';
            }
        }

        // Swap selected tables (T074)
        // Note: No confirmation dialog - swapping is reversible (UX Improvement #6)
        function swapSelectedTables() {
            var checkboxes = document.querySelectorAll('.swap-checkbox:checked');

            if (checkboxes.length !== 2) {
                alert('Please select exactly two allocations to swap');
                return;
            }

            var allocationId1 = parseInt(checkboxes[0].dataset.allocationId);
            var allocationId2 = parseInt(checkboxes[1].dataset.allocationId);

            // Execute swap immediately - action is reversible (swap again to undo)
            var csrfToken = document.querySelector('meta[name="csrf-token"]');
            fetch('/api/allocations/swap', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Token': getAdminToken(currentTournamentId),
                    'X-CSRF-Token': csrfToken ? csrfToken.getAttribute('content') : ''
                },
                body: JSON.stringify({
                    allocationId1: allocationId1,
                    allocationId2: allocationId2
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.error) {
                    alert('Error: ' + data.message);
                } else {
                    location.reload();
                }
            })
            .catch(function(error) {
                alert('Failed to swap tables: ' + error.message);
            });
        }

        // Change table assignment (T073)
        function changeTableAssignment(allocationId, newTableId) {
            fetch('/api/allocations/' + allocationId, {
                method: 'PATCH',
                headers: {
                    'Content-Type': 'application/json',
                    'X-Admin-Token': getAdminToken(currentTournamentId)
                },
                body: JSON.stringify({
                    tableId: parseInt(newTableId)
                })
            })
            .then(function(response) { return response.json(); })
            .then(function(data) {
                if (data.error) {
                    alert('Error: ' + data.message);
                    location.reload(); // Reload to reset dropdown
                } else {
                    // Reload page - conflicts will be shown in the UI
                    location.reload();
                }
            })
            .catch(function(error) {
                alert('Failed to change table assignment: ' + error.message);
                location.reload();
            });
        }

        // Open table change modal (mobile)
        function openTableModal(allocationId, currentTableId, player1Name, player2Name) {
            currentModalAllocationId = allocationId;
            currentModalTableId = currentTableId;

            // Set matchup description
            document.getElementById('modal-matchup').textContent = player1Name + ' vs ' + player2Name;

            // Build table options
            var optionsContainer = document.getElementById('modal-table-options');
            // Clear existing options safely (remove all children)
            while (optionsContainer.firstChild) {
                optionsContainer.removeChild(optionsContainer.firstChild);
            }

            tableData.forEach(function(table) {
                var btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'table-option-btn';
                if (table.id === currentTableId) {
                    btn.className += ' selected';
                }

                // Build button content using text nodes to prevent XSS
                btn.appendChild(document.createTextNode('Table ' + table.number));
                if (table.emoji) {
                    btn.appendChild(document.createTextNode(' ' + table.emoji));
                }
                if (table.terrain) {
                    btn.appendChild(document.createTextNode(' '));
                    var terrainSpan = document.createElement('span');
                    terrainSpan.className = 'table-option-terrain';
                    terrainSpan.textContent = '(' + table.terrain + ')';
                    btn.appendChild(terrainSpan);
                }

                btn.onclick = function() {
                    selectTableOption(table.id);
                };

                optionsContainer.appendChild(btn);
            });

            // Show modal
            document.getElementById('table-modal').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        // Select table option in modal
        function selectTableOption(tableId) {
            if (tableId !== currentModalTableId) {
                changeTableAssignment(currentModalAllocationId, tableId);
            }
            closeTableModal();
        }

        // Close table change modal
        function closeTableModal() {
            document.getElementById('table-modal').classList.remove('active');
            document.body.style.overflow = '';
            currentModalAllocationId = null;
            currentModalTableId = null;
        }

        // Close modal on backdrop click
        document.getElementById('table-modal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeTableModal();
            }
        });

        // Close modal on Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeTableModal();
            }
        });

        // Import Next button handler (when on last round)
        var importNextBtn = document.getElementById('import-next-button');
        if (importNextBtn) {
            importNextBtn.addEventListener('click', function() {
                var button = this;
                var roundNumber = button.getAttribute('data-round-number');
                var tournamentId = button.getAttribute('data-tournament-id');
                var token = getAdminToken(currentTournamentId);

                // Show loading state
                setButtonLoading('import-next-button', 'import-next-indicator', 'import-next-text', true);
                document.getElementById('import-next-result').innerHTML = '';

                var headers = { 'Content-Type': 'application/json' };
                if (token) {
                    headers['X-Admin-Token'] = token;
                }

                fetch('/api/tournaments/' + tournamentId + '/rounds/' + roundNumber + '/import', {
                    method: 'POST',
                    headers: headers
                })
                .then(function(response) {
                    return response.json().then(function(data) {
                        return { status: response.status, data: data };
                    });
                })
                .then(function(response) {
                    setButtonLoading('import-next-button', 'import-next-indicator', 'import-next-text', false);

                    if (response.status >= 200 && response.status < 300) {
                        // Redirect to new round page with success info
                        var pairingsCount = response.data.pairingsImported || 0;
                        window.location.href = '/admin/tournament/' + tournamentId + '/round/' + roundNumber +
                            '?imported=1&pairings=' + encodeURIComponent(pairingsCount);
                    } else {
                        showAlert('import-next-result', 'error',
                            'Error: ' + escapeHtml(response.data.message || 'Failed to import round')
                        );
                    }
                })
                .catch(function(error) {
                    setButtonLoading('import-next-button', 'import-next-indicator', 'import-next-text', false);
                    showAlert('import-next-result', 'error', 'Network error: ' + escapeHtml(error.message));
                });
            });
        }
    </script>
</body>
</html>
