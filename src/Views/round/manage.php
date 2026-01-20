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
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>
    <script src="/js/utils.js"></script>
    <script>
        // Current tournament ID for token retrieval
        var currentTournamentId = <?= $tournament->id ?>;

        // Add admin token to all HTMX requests
        document.addEventListener('htmx:configRequest', function(event) {
            var token = getAdminToken(currentTournamentId);
            if (token) {
                event.detail.headers['X-Admin-Token'] = token;
            }
        });
    </script>
    <style>
        /* Conflict highlighting */
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

        /* Badges */
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

        /* Base table styles (mobile-first) */
        .allocation-table {
            width: 100%;
            font-size: 14px;
        }
        .allocation-table th,
        .allocation-table td {
            padding: 8px 4px;
            text-align: left;
            vertical-align: middle;
        }
        .allocation-table th {
            font-size: 12px;
            white-space: nowrap;
        }

        /* Score styling - muted, inline with player name */
        .player-score {
            color: #1976d2;
            font-weight: 600;
            margin-left: 4px;
        }

        /* Terrain in table column */
        .terrain-suffix {
            font-style: italic;
            color: #666;
            font-size: 0.85em;
        }

        /* BCP table difference indicator */
        .bcp-diff {
            display: block;
            color: #666;
            font-size: 0.75em;
            font-weight: normal;
            margin-top: 2px;
        }

        /* VS separator column */
        .vs-cell {
            text-align: center;
            color: #888;
            font-size: 12px;
            padding: 8px 2px !important;
        }

        /* Player name styling */
        .player-name {
            max-width: 150px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .player-cell {
            display: flex;
            align-items: center;
            gap: 4px;
        }

        /* Checkbox - touch-friendly on mobile */
        .select-cell {
            width: 44px;
            text-align: center;
        }
        .swap-checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }

        /* Table number column */
        .table-cell {
            white-space: nowrap;
            font-weight: 600;
        }

        /* Change table - dropdown on desktop, button on mobile */
        .change-cell {
            width: 120px;
        }
        .change-table-dropdown {
            width: 100%;
            font-size: 0.875em;
            padding: 6px 8px;
        }
        .change-table-btn {
            display: none;
            width: 44px;
            height: 44px;
            padding: 0;
            background: var(--secondary);
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 18px;
            line-height: 44px;
        }
        .change-table-btn:hover {
            background: var(--secondary-hover);
        }

        /* Header abbreviations */
        .header-full { display: inline; }
        .header-short { display: none; }

        /* Action buttons */
        .action-buttons {
            display: flex;
            flex-wrap: wrap;
            gap: 0.5em;
            margin-bottom: 1em;
        }
        .loading {
            opacity: 0.5;
            pointer-events: none;
        }

        /* Conflict list */
        .conflict-list {
            margin-top: 1em;
            padding: 1em;
            color: #666;
            background-color: #fff3e0;
            border-radius: 4px;
        }
        .conflict-list h3 {
            margin: 0;
            color: #666;
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

        /* HTMX indicators */
        .htmx-indicator {
            display: none;
        }
        .htmx-request .htmx-indicator {
            display: inline-block;
        }
        .htmx-request.htmx-indicator {
            display: inline-block;
        }

        /* Swap button container - sticky on mobile */
        .swap-controls {
            display: flex;
            align-items: center;
            gap: 0.5em;
            margin-top: 1em;
            padding: 1em;
            background: var(--background-color, #fff);
            border-top: 1px solid var(--muted-border-color, #ddd);
        }
        .swap-status {
            font-size: 0.875em;
            color: #666;
        }

        /* Modal for mobile table editing */
        .modal-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.5);
            z-index: 1000;
            justify-content: center;
            align-items: flex-end;
        }
        .modal-overlay.active {
            display: flex;
        }
        .modal-content {
            background: var(--background-color, #fff);
            border-radius: 12px 12px 0 0;
            padding: 1.5em;
            width: 100%;
            max-width: 500px;
            max-height: 70vh;
            overflow-y: auto;
        }
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1em;
        }
        .modal-header h3 {
            margin: 0;
            font-size: 1.1em;
        }
        .modal-close {
            background: none;
            border: none;
            font-size: 1.5em;
            cursor: pointer;
            padding: 0;
            line-height: 1;
        }
        .modal-description {
            color: #666;
            margin-bottom: 1em;
            font-size: 0.9em;
        }
        .table-options {
            display: flex;
            flex-direction: column;
            gap: 8px;
        }
        .table-option-btn {
            width: 100%;
            padding: 14px 16px;
            text-align: left;
            background: var(--secondary-focus, #f0f0f0);
            border: 2px solid transparent;
            border-radius: 8px;
            cursor: pointer;
            font-size: 1em;
            transition: border-color 0.2s;
        }
        .table-option-btn:hover,
        .table-option-btn:focus {
            border-color: var(--primary);
        }
        .table-option-btn.selected {
            border-color: var(--primary);
            background: var(--primary-focus, #e3f2fd);
        }
        .table-option-terrain {
            font-style: italic;
            color: #666;
            margin-left: 8px;
        }

        /* Mobile styles (< 768px) */
        @media (max-width: 767px) {
            .allocation-table {
                font-size: 13px;
            }
            .allocation-table th,
            .allocation-table td {
                padding: 10px 4px;
            }

            /* Larger touch targets */
            .swap-checkbox {
                width: 24px;
                height: 24px;
            }
            .select-cell {
                width: 40px;
            }

            /* Abbreviate headers */
            .header-full { display: none; }
            .header-short { display: inline; }

            /* Show mobile edit button, hide dropdown */
            .change-table-dropdown { display: none; }
            .change-table-btn { display: inline-block; }
            .change-cell { width: 48px; }

            /* Player names - show abbreviated version */
            .player-name-full { display: none; }
            .player-name-short { display: inline; }
            .player-name {
                max-width: 100px;
            }

            /* Sticky swap controls at bottom */
            .swap-controls {
                position: fixed;
                bottom: 0;
                left: 0;
                right: 0;
                margin: 0;
                z-index: 100;
                box-shadow: 0 -2px 10px rgba(0,0,0,0.1);
            }

            /* Add padding at bottom for sticky controls */
            #allocation-results {
                padding-bottom: 80px;
            }

            /* Modal slides up from bottom */
            .modal-content {
                animation: slideUp 0.2s ease-out;
            }
            @keyframes slideUp {
                from { transform: translateY(100%); }
                to { transform: translateY(0); }
            }
        }

        /* Desktop styles (>= 768px) */
        @media (min-width: 768px) {
            .allocation-table {
                font-size: 15px;
            }
            .allocation-table th,
            .allocation-table td {
                padding: 12px 8px;
            }
            .player-name {
                max-width: 200px;
            }
            .player-name-full { display: inline; }
            .player-name-short { display: none; }
            .change-cell {
                width: 140px;
            }

            /* Modal centered on desktop */
            .modal-overlay {
                align-items: center;
            }
            .modal-content {
                border-radius: 12px;
                max-height: 80vh;
            }
        }
    </style>
</head>
<body>
    <main class="container">
        <nav>
            <ul>
                <li><strong><a href="/" style="text-decoration: none;">Tournament Tables</a></strong></li>
            </ul>
            <ul>
                <li><a href="/tournament/<?= $tournament->id ?>"><?= htmlspecialchars($tournament->name) ?></a></li>
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

        <!-- Round navigation -->
        <nav style="display: flex; justify-content: space-between; margin-bottom: 1em;">
            <div>
                <?php if ($prevRound): ?>
                <a href="/tournament/<?= $tournament->id ?>/round/<?= $prevRound->roundNumber ?>">&laquo; Round <?= $prevRound->roundNumber ?></a>
                <?php endif; ?>
            </div>
            <div>
                <?php if ($nextRound): ?>
                <a href="/tournament/<?= $tournament->id ?>/round/<?= $nextRound->roundNumber ?>">Round <?= $nextRound->roundNumber ?> &raquo;</a>
                <?php endif; ?>
            </div>
        </nav>

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
                        // Note: terrain reuse no longer highlights rows - shown via emoji instead

                        $table = $allocation->getTable();
                        $player1 = $allocation->getPlayer1();
                        $player2 = $allocation->getPlayer2();
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
                        <!-- Checkbox for swap selection (T074) -->
                        <td class="select-cell">
                            <input
                                type="checkbox"
                                class="swap-checkbox"
                                data-allocation-id="<?= $allocation->id ?>"
                                onchange="updateSwapButton()"
                                aria-label="Select for swap"
                            />
                        </td>

                        <!-- Table number with terrain -->
                        <?php $bcpDiff = formatBcpDifference($allocation); ?>
                        <td class="table-cell" title="<?= $terrainName ? "Table {$table->tableNumber} ({$terrainName})" : "Table " . ($table ? $table->tableNumber : 'N/A') ?>">
                            <?php if ($table): ?>
                                <span><?= $bcpDiff['emoji'] ?> Table <?= $table->tableNumber ?><?= $terrainEmoji ? ' ' . $terrainEmoji : '' ?></span>
                                <?php if ($terrainName): ?>
                                    <span class="terrain-suffix header-full">(<?= $terrainName ?>)</span>
                                <?php endif; ?>
                                <?= $bcpDiff['detail'] ?>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>

                        <!-- Player 1 with total score -->
                        <?php $p1TerrainReuse = playerHasTerrainReuse($player1Name, $allocationConflicts); ?>
                        <td title="<?= $player1Name ?> (<?= $player1 ? $player1->totalScore : 0 ?>)<?= $p1TerrainReuse ? ' - Already experienced this terrain' : '' ?>">
                            <span class="player-name">
                                <span class="player-name-full"><?= $player1Name ?><?= $p1TerrainReuse ? ' <span title="Already experienced this terrain">😑</span>' : '' ?></span>
                                <span class="player-name-short"><?= $player1Short ?><?= $p1TerrainReuse ? ' 😑' : '' ?></span>
                            </span>
                            <span class="player-score">(<?= $player1 ? $player1->totalScore : 0 ?>)</span>
                        </td>
                        <!-- Player 2 with total score -->
                        <?php $p2TerrainReuse = playerHasTerrainReuse($player2Name, $allocationConflicts); ?>
                        <td title="<?= $player2Name ?> (<?= $player2 ? $player2->totalScore : 0 ?>)<?= $p2TerrainReuse ? ' - Already experienced this terrain' : '' ?>">
                            <span class="player-name">
                                <span class="player-name-full"><?= $player2Name ?><?= $p2TerrainReuse ? ' <span title="Already experienced this terrain">😑</span>' : '' ?></span>
                                <span class="player-name-short"><?= $player2Short ?><?= $p2TerrainReuse ? ' 😑' : '' ?></span>
                            </span>
                            <span class="player-score">(<?= $player2 ? $player2->totalScore : 0 ?>)</span>
                        </td>

                        <!-- Change table - dropdown on desktop, button on mobile -->
                        <td class="change-cell">
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
                        'X-Admin-Token': getAdminToken(currentTournamentId)
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
    </script>
</body>
</html>
