<?php
/**
 * Tournament dashboard view (admin).
 *
 * Displays tournament overview with rounds list and management controls.
 *
 * Reference: specs/001-table-allocation/spec.md#user-story-2
 *
 * Expected variables:
 * - $tournament: Tournament model
 * - $rounds: Array of Round models
 * - $tables: Array of Table models
 * - $terrainTypes: Array of TerrainType models
 * - $justCreated: bool (optional) - Whether tournament was just created
 * - $adminToken: string (optional) - Admin token if just created
 * - $autoImport: array (optional) - Auto-import result {success: bool, tableCount?: int, pairingsImported?: int, error?: string}
 */

$title = $tournament->name;
$tableCount = count($tables); // Use actual table count from database
$hasRounds = !empty($rounds);
$justCreated = $justCreated ?? false;
$adminToken = $adminToken ?? null;
$autoImport = $autoImport ?? null;
?>

<?php if ($justCreated && $adminToken): ?>
<article class="alert-success" id="creation-success">
    <header>
        <h3>Tournament Created Successfully!</h3>
    </header>

    <?php if ($autoImport && $autoImport['success']): ?>
        <p class="status-success">
            Round 1 imported automatically! Created <?= $autoImport['tableCount'] ?> tables from <?= $autoImport['pairingsImported'] ?> pairings.
        </p>
    <?php elseif ($autoImport && !$autoImport['success']): ?>
        <p class="status-warning">
            Note: Could not auto-import Round 1: <?= htmlspecialchars($autoImport['error']) ?>
        </p>
        <p>You can manually import Round 1 using the button in the Rounds table below.</p>
    <?php endif; ?>

    <p><strong>Important:</strong> Save your admin token. You'll need it to manage this tournament from other devices or browsers.</p>
    <div style="display: flex; align-items: center; gap: 1rem; margin: 1rem 0;">
        <div class="token-display" id="admin-token-display" style="flex: 1;">
            <?= htmlspecialchars($adminToken) ?>
        </div>
        <button type="button" id="copy-token-btn" onclick="copyAdminToken()" style="white-space: nowrap;">
            Copy Token
        </button>
    </div>
    <p class="text-small-muted">
        This token has been saved in your browser cookies for 30 days.
        You can dismiss this message - the token will remain accessible from your cookies.
    </p>
</article>
<?php endif; ?>

<header>
    <h1><?= htmlspecialchars($tournament->name) ?></h1>
    <p>
        <a href="<?= htmlspecialchars($tournament->bcpUrl) ?>" target="_blank" rel="noopener">
            View on Best Coast Pairings
        </a>
        |
        <a href="/<?= $tournament->id ?>">Public View</a>
    </p>
</header>

<section>
    <h2>Tournament Info</h2>
    <article>
        <div class="grid">
            <div>
                <strong>Tables:</strong> <?= $tableCount ?>
            </div>
            <div>
                <strong>Rounds Imported:</strong> <?= count($rounds) ?>
            </div>
            <div>
                <strong>BCP Event ID:</strong> <?= htmlspecialchars($tournament->bcpEventId) ?>
            </div>
        </div>
    </article>
</section>

<?php
// Calculate next round number (MAX + 1 or 1 if no rounds)
$nextRoundNumber = $hasRounds ? max(array_map(function($r) { return $r->roundNumber; }, $rounds)) + 1 : 1;
?>
<section>
    <h2>Rounds</h2>

    <table role="grid">
        <thead>
            <tr>
                <th class="text-center">Round</th>
                <th>Status</th>
                <th class="text-center">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if ($hasRounds): ?>
                <?php foreach ($rounds as $round): ?>
                <tr>
                    <td class="text-center"><strong><?= $round->roundNumber ?></strong></td>
                    <td>
                        <?php if ($round->isPublished): ?>
                            <span class="status-published">Published</span>
                        <?php else: ?>
                            <span class="status-draft">Draft</span>
                        <?php endif; ?>
                    </td>
                    <td class="text-center">
                        <a href="/admin/tournament/<?= $tournament->id ?>/round/<?= $round->roundNumber ?>" role="button" class="secondary">
                            Manage
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
            <!-- Import next round row -->
            <tr class="import-round-row">
                <td colspan="3" class="import-round-cell">
                    <button
                        type="button"
                        id="import-round-button"
                        class="outline"
                        data-round-number="<?= $nextRoundNumber ?>"
                        data-tournament-id="<?= $tournament->id ?>"
                    >
                        <span id="import-indicator" style="display: none;">Importing...</span>
                        <span id="import-text">+ Import Round <?= $nextRoundNumber ?></span>
                    </button>
                    <div id="import-result" class="import-result"></div>
                </td>
            </tr>
        </tbody>
    </table>
</section>

<section>
    <h2>Table Configuration</h2>
    <article>
        <p>Assign terrain types to tables. Players will preferentially be assigned to terrain types they haven't experienced.</p>

        <form id="terrain-form">
            <!-- Quick Setup: Set All Tables -->
            <div class="set-all-container">
                <label for="set-all-terrain" class="set-all-label">
                    Quick Setup: Set All Tables
                </label>
                <div class="set-all-controls">
                    <select id="set-all-terrain">
                        <option value="">-- Select terrain type --</option>
                        <?php foreach ($terrainTypes as $terrainType): ?>
                        <option value="<?= $terrainType->id ?>">
                            <?= $terrainType->emoji ? $terrainType->emoji . ' ' : '' ?><?= htmlspecialchars($terrainType->name) ?>
                        </option>
                        <?php endforeach; ?>
                    </select>
                    <button type="button" id="apply-all-button" class="secondary">
                        Apply to All
                    </button>
                    <button type="button" id="clear-all-button" class="outline secondary">
                        Clear All
                    </button>
                </div>
                <small class="set-all-hint">
                    Changes apply to form only. Click "Save Terrain Configuration" below to save.
                </small>
            </div>

            <table role="grid">
                <thead>
                    <tr>
                        <th style="width: 20%;">Table</th>
                        <th>Terrain Type</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tables as $table):
                        $currentTerrain = $table->getTerrainType();
                        $currentEmoji = $currentTerrain ? $currentTerrain->emoji : null;
                    ?>
                    <tr>
                        <td><strong>Table <?= $table->tableNumber ?><?= $currentEmoji ? ' ' . $currentEmoji : '' ?></strong></td>
                        <td>
                            <select
                                name="table_<?= $table->tableNumber ?>"
                                id="table-<?= $table->tableNumber ?>"
                                data-table-number="<?= $table->tableNumber ?>"
                            >
                                <option value="">-- No terrain assigned --</option>
                                <?php foreach ($terrainTypes as $terrainType): ?>
                                <option
                                    value="<?= $terrainType->id ?>"
                                    <?= ($table->terrainTypeId === $terrainType->id) ? 'selected' : '' ?>
                                >
                                    <?= $terrainType->emoji ? $terrainType->emoji . ' ' : '' ?><?= htmlspecialchars($terrainType->name) ?>
                                    <?php if ($terrainType->description): ?>
                                        - <?= htmlspecialchars($terrainType->description) ?>
                                    <?php endif; ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div style="margin-top: 1rem;">
                <button type="submit" id="save-terrain-button">
                    <span id="save-terrain-indicator" style="display: none;">Saving...</span>
                    <span id="save-terrain-text">Save Terrain Configuration</span>
                </button>
            </div>
        </form>

        <div id="terrain-result" style="margin-top: 1rem;"></div>
    </article>
</section>

<style>
/* Utility classes */
.text-center {
    text-align: center;
}

/* Import Round Row Styles */
.import-round-row {
    background: transparent;
}

.import-round-cell {
    text-align: center;
    padding: 1rem !important;
    border-top: 1px dashed var(--pico-muted-border-color, #e0e0e0);
}

.import-round-cell button {
    margin: 0 auto;
    min-width: 200px;
}

.import-result {
    margin-top: 0.75rem;
}

.import-result:empty {
    display: none;
}

/* Responsive styles for Table Configuration - Set All Tables */
.set-all-container {
    margin-bottom: 1.5rem;
    padding: 1rem;
    background: var(--pico-card-background-color, #f8f9fa);
    border-radius: var(--pico-border-radius, 0.25rem);
    border: 1px solid var(--pico-muted-border-color, #e0e0e0);
}

.set-all-label {
    display: block;
    margin-bottom: 0.5rem;
    font-weight: 600;
}

.set-all-controls {
    display: flex;
    gap: 0.75rem;
    flex-wrap: wrap;
    align-items: stretch;
}

.set-all-controls select {
    flex: 1;
    min-width: 200px;
    margin-bottom: 0;
}

.set-all-controls button {
    white-space: nowrap;
    margin-bottom: 0;
    min-height: 44px; /* Touch-friendly target per NNG guidelines */
    padding-left: 1rem;
    padding-right: 1rem;
}

.set-all-hint {
    display: block;
    margin-top: 0.5rem;
    color: var(--pico-muted-color, #666);
}

/* Mobile: Stack elements vertically */
@media (max-width: 576px) {
    .set-all-controls {
        flex-direction: column;
    }

    .set-all-controls select {
        min-width: 100%;
        width: 100%;
    }

    .set-all-controls button {
        width: 100%;
        justify-content: center;
    }
}

/* Tablet: Keep horizontal but allow wrapping */
@media (min-width: 577px) and (max-width: 768px) {
    .set-all-controls select {
        min-width: 180px;
    }
}

/* Row highlight animation for visual feedback */
@keyframes highlightFade {
    0% { background-color: var(--pico-primary-focus, rgba(16, 149, 193, 0.25)); }
    100% { background-color: transparent; }
}

.table-row-highlight {
    animation: highlightFade 0.8s ease-out;
}
</style>

<script>
// Copy admin token to clipboard
function copyAdminToken() {
    var tokenDisplay = document.getElementById('admin-token-display');
    var button = document.getElementById('copy-token-btn');
    var originalText = button.textContent;

    // Copy to clipboard
    navigator.clipboard.writeText(tokenDisplay.textContent.trim()).then(function() {
        // Show success feedback
        button.textContent = 'Copied!';
        button.style.background = '#4caf50';

        // Reset button after 2 seconds
        setTimeout(function() {
            button.textContent = originalText;
            button.style.background = '';
        }, 2000);
    }).catch(function(err) {
        // Fallback for older browsers
        var textArea = document.createElement('textarea');
        textArea.value = tokenDisplay.textContent.trim();
        textArea.style.position = 'fixed';
        textArea.style.left = '-999999px';
        document.body.appendChild(textArea);
        textArea.select();
        try {
            document.execCommand('copy');
            button.textContent = 'Copied!';
            button.style.background = '#4caf50';
            setTimeout(function() {
                button.textContent = originalText;
                button.style.background = '';
            }, 2000);
        } catch (err) {
            button.textContent = 'Failed to copy';
            setTimeout(function() {
                button.textContent = originalText;
            }, 2000);
        }
        document.body.removeChild(textArea);
    });
}

// Import round button handler
document.getElementById('import-round-button').addEventListener('click', function() {
    var button = this;
    var roundNumber = button.getAttribute('data-round-number');
    var tournamentId = button.getAttribute('data-tournament-id');

    // Show loading state
    setButtonLoading('import-round-button', 'import-indicator', 'import-text', true);
    document.getElementById('import-result').innerHTML = '';

    fetch('/api/tournaments/' + tournamentId + '/rounds/' + roundNumber + '/import', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json'
        }
    })
    .then(function(response) {
        return response.json().then(function(data) {
            return { status: response.status, data: data };
        });
    })
    .then(function(response) {
        setButtonLoading('import-round-button', 'import-indicator', 'import-text', false);

        if (response.status >= 200 && response.status < 300) {
            // Redirect immediately to manage page with success info in query params
            var pairingsCount = response.data.pairingsImported || 0;
            window.location.href = '/admin/tournament/' + tournamentId + '/round/' + roundNumber +
                '?imported=1&pairings=' + encodeURIComponent(pairingsCount);
        } else {
            showAlert('import-result', 'error',
                'Error: ' + escapeHtml(response.data.message || 'Failed to import round')
            );
        }
    })
    .catch(function(error) {
        setButtonLoading('import-round-button', 'import-indicator', 'import-text', false);
        showAlert('import-result', 'error', 'Network error: ' + escapeHtml(error.message));
    });
});

// Terrain type configuration form handler
document.getElementById('terrain-form').addEventListener('submit', function(e) {
    e.preventDefault();

    // Collect all table configurations
    var tables = [];
    var selects = document.querySelectorAll('select[data-table-number]');

    selects.forEach(function(select) {
        var tableNumber = parseInt(select.getAttribute('data-table-number'));
        var terrainTypeId = select.value ? parseInt(select.value) : null;

        tables.push({
            tableNumber: tableNumber,
            terrainTypeId: terrainTypeId
        });
    });

    // Show loading state
    setButtonLoading('save-terrain-button', 'save-terrain-indicator', 'save-terrain-text', true);
    document.getElementById('terrain-result').innerHTML = '';

    fetch('/api/tournaments/<?= $tournament->id ?>/tables', {
        method: 'PUT',
        headers: {
            'Content-Type': 'application/json'
        },
        body: JSON.stringify({ tables: tables })
    })
    .then(function(response) {
        return response.json().then(function(data) {
            return { status: response.status, data: data };
        });
    })
    .then(function(response) {
        setButtonLoading('save-terrain-button', 'save-terrain-indicator', 'save-terrain-text', false);

        if (response.status >= 200 && response.status < 300) {
            showAlert('terrain-result', 'success', 'Terrain configuration saved successfully!', 3000);
        } else {
            showAlert('terrain-result', 'error',
                'Error: ' + escapeHtml(response.data.message || 'Failed to save terrain configuration')
            );
        }
    })
    .catch(function(error) {
        setButtonLoading('save-terrain-button', 'save-terrain-indicator', 'save-terrain-text', false);
        showAlert('terrain-result', 'error', 'Network error: ' + escapeHtml(error.message));
    });
});

// Apply terrain type to all tables
document.getElementById('apply-all-button').addEventListener('click', function() {
    var selectedTerrain = document.getElementById('set-all-terrain').value;

    if (!selectedTerrain) {
        // Flash the dropdown to indicate selection needed
        var dropdown = document.getElementById('set-all-terrain');
        dropdown.focus();
        dropdown.style.outline = '2px solid var(--pico-primary, #1095c1)';
        setTimeout(function() {
            dropdown.style.outline = '';
        }, 1500);
        return;
    }

    var selects = document.querySelectorAll('select[data-table-number]');
    var changedCount = 0;

    selects.forEach(function(select) {
        if (select.value !== selectedTerrain) {
            select.value = selectedTerrain;
            changedCount++;
            // Visual feedback: briefly highlight changed rows
            var row = select.closest('tr');
            if (row) {
                highlightRow(row, 'primary', 800);
            }
        }
    });

    // Show feedback
    if (changedCount > 0) {
        showAlert('terrain-result', 'info-primary',
            'Applied terrain to ' + changedCount + ' table' + (changedCount !== 1 ? 's' : '') + '. Click "Save Terrain Configuration" to save changes.',
            4000
        );
    } else {
        showAlert('terrain-result', 'info-primary',
            'All tables already have this terrain type selected.',
            4000
        );
    }
});

// Clear all terrain types
document.getElementById('clear-all-button').addEventListener('click', function() {
    var selects = document.querySelectorAll('select[data-table-number]');
    var changedCount = 0;

    selects.forEach(function(select) {
        if (select.value !== '') {
            select.value = '';
            changedCount++;
            // Visual feedback: briefly highlight changed rows
            var row = select.closest('tr');
            if (row) {
                highlightRow(row, 'secondary', 800);
            }
        }
    });

    // Also reset the "set all" dropdown
    document.getElementById('set-all-terrain').value = '';

    // Show feedback
    if (changedCount > 0) {
        showAlert('terrain-result', 'info-secondary',
            'Cleared terrain from ' + changedCount + ' table' + (changedCount !== 1 ? 's' : '') + '. Click "Save Terrain Configuration" to save changes.',
            4000
        );
    } else {
        showAlert('terrain-result', 'info-secondary',
            'All tables already have no terrain assigned.',
            4000
        );
    }
});
</script>
