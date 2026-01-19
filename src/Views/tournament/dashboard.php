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
$tableCount = $tournament->tableCount;
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
        <p style="color: #4caf50; font-weight: bold;">
            Round 1 imported automatically! Created <?= $autoImport['tableCount'] ?> tables from <?= $autoImport['pairingsImported'] ?> pairings.
        </p>
    <?php elseif ($autoImport && !$autoImport['success']): ?>
        <p style="color: #ff9800; font-weight: bold;">
            Note: Could not auto-import Round 1: <?= htmlspecialchars($autoImport['error']) ?>
        </p>
        <p>You can manually import Round 1 using the form below.</p>
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
    <p style="font-size: 0.9rem; color: #666;">
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
        <a href="/public/<?= $tournament->id ?>">Public View</a>
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

<section>
    <h2>Rounds</h2>

    <?php if ($hasRounds): ?>
    <table role="grid">
        <thead>
            <tr>
                <th>Round</th>
                <th>Allocations</th>
                <th>Status</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($rounds as $round): ?>
            <tr>
                <td><strong><?= $round->roundNumber ?></strong></td>
                <td><?= $round->getAllocationCount() ?> pairings</td>
                <td>
                    <?php if ($round->isPublished): ?>
                        <span style="color: #4caf50;">Published</span>
                    <?php else: ?>
                        <span style="color: #ff9800;">Draft</span>
                    <?php endif; ?>
                </td>
                <td>
                    <a href="/tournament/<?= $tournament->id ?>/round/<?= $round->roundNumber ?>" role="button" class="secondary">
                        Manage
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <article>
        <p>No rounds imported yet. Use the form below to import a round from BCP.</p>
    </article>
    <?php endif; ?>
</section>

<section>
    <h2>Import New Round</h2>
    <article>
        <p>Import pairings from Best Coast Pairings for a specific round.</p>

        <form id="import-form">
            <div class="grid">
                <label>
                    Round Number
                    <input
                        type="number"
                        id="round-number"
                        name="roundNumber"
                        min="1"
                        max="10"
                        value="<?= count($rounds) + 1 ?>"
                        required
                    />
                </label>
                <div>
                    <label>&nbsp;</label>
                    <button type="submit" id="import-button">
                        <span id="import-indicator" style="display: none;">Importing...</span>
                        <span id="import-text">Import from BCP</span>
                    </button>
                </div>
            </div>
        </form>

        <div id="import-result" style="margin-top: 1rem;"></div>
    </article>
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

document.getElementById('import-form').addEventListener('submit', function(e) {
    e.preventDefault();

    var roundNumber = document.getElementById('round-number').value;
    var button = document.getElementById('import-button');
    var indicator = document.getElementById('import-indicator');
    var text = document.getElementById('import-text');
    var result = document.getElementById('import-result');

    // Show loading state
    button.disabled = true;
    indicator.style.display = 'inline';
    text.style.display = 'none';
    result.innerHTML = '';

    fetch('/api/tournaments/<?= $tournament->id ?>/rounds/' + roundNumber + '/import', {
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
        button.disabled = false;
        indicator.style.display = 'none';
        text.style.display = 'inline';

        if (response.status >= 200 && response.status < 300) {
            result.innerHTML = '<div class="alert alert-success">' +
                'Successfully imported ' + escapeHtml(String(response.data.pairingsImported)) + ' pairings for Round ' + escapeHtml(roundNumber) + '. ' +
                '<a href="/tournament/<?= $tournament->id ?>/round/' + roundNumber + '">Manage Round ' + roundNumber + '</a>' +
                '</div>';
            // Reload after a short delay to show updated rounds list
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            result.innerHTML = '<div class="alert alert-error">' +
                'Error: ' + escapeHtml(response.data.message || 'Failed to import round') +
                '</div>';
        }
    })
    .catch(function(error) {
        button.disabled = false;
        indicator.style.display = 'none';
        text.style.display = 'inline';
        result.innerHTML = '<div class="alert alert-error">Network error: ' + escapeHtml(error.message) + '</div>';
    });
});

// Terrain type configuration form handler
document.getElementById('terrain-form').addEventListener('submit', function(e) {
    e.preventDefault();

    var button = document.getElementById('save-terrain-button');
    var indicator = document.getElementById('save-terrain-indicator');
    var text = document.getElementById('save-terrain-text');
    var result = document.getElementById('terrain-result');

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
    button.disabled = true;
    indicator.style.display = 'inline';
    text.style.display = 'none';
    result.innerHTML = '';

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
        button.disabled = false;
        indicator.style.display = 'none';
        text.style.display = 'inline';

        if (response.status >= 200 && response.status < 300) {
            result.innerHTML = '<div class="alert alert-success">' +
                'Terrain configuration saved successfully!' +
                '</div>';

            // Clear success message after 3 seconds
            setTimeout(function() {
                result.innerHTML = '';
            }, 3000);
        } else {
            result.innerHTML = '<div class="alert alert-error">' +
                'Error: ' + escapeHtml(response.data.message || 'Failed to save terrain configuration') +
                '</div>';
        }
    })
    .catch(function(error) {
        button.disabled = false;
        indicator.style.display = 'none';
        text.style.display = 'inline';
        result.innerHTML = '<div class="alert alert-error">Network error: ' + escapeHtml(error.message) + '</div>';
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
                row.style.backgroundColor = 'var(--pico-primary-focus, rgba(16, 149, 193, 0.15))';
                setTimeout(function() {
                    row.style.backgroundColor = '';
                }, 800);
            }
        }
    });

    // Show feedback
    var result = document.getElementById('terrain-result');
    if (changedCount > 0) {
        result.innerHTML = '<div class="alert alert-info" style="background: var(--pico-primary-focus, rgba(16, 149, 193, 0.15)); border: 1px solid var(--pico-primary, #1095c1); padding: 0.75rem; border-radius: 0.25rem;">' +
            'Applied terrain to ' + changedCount + ' table' + (changedCount !== 1 ? 's' : '') + '. Click "Save Terrain Configuration" to save changes.' +
            '</div>';
    } else {
        result.innerHTML = '<div class="alert alert-info" style="background: var(--pico-primary-focus, rgba(16, 149, 193, 0.15)); border: 1px solid var(--pico-primary, #1095c1); padding: 0.75rem; border-radius: 0.25rem;">' +
            'All tables already have this terrain type selected.' +
            '</div>';
    }

    // Clear info message after 4 seconds
    setTimeout(function() {
        if (result.querySelector('.alert-info')) {
            result.innerHTML = '';
        }
    }, 4000);
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
                row.style.backgroundColor = 'var(--pico-secondary-focus, rgba(98, 119, 140, 0.15))';
                setTimeout(function() {
                    row.style.backgroundColor = '';
                }, 800);
            }
        }
    });

    // Also reset the "set all" dropdown
    document.getElementById('set-all-terrain').value = '';

    // Show feedback
    var result = document.getElementById('terrain-result');
    if (changedCount > 0) {
        result.innerHTML = '<div class="alert alert-info" style="background: var(--pico-secondary-focus, rgba(98, 119, 140, 0.15)); border: 1px solid var(--pico-secondary, #62778c); padding: 0.75rem; border-radius: 0.25rem;">' +
            'Cleared terrain from ' + changedCount + ' table' + (changedCount !== 1 ? 's' : '') + '. Click "Save Terrain Configuration" to save changes.' +
            '</div>';
    } else {
        result.innerHTML = '<div class="alert alert-info" style="background: var(--pico-secondary-focus, rgba(98, 119, 140, 0.15)); border: 1px solid var(--pico-secondary, #62778c); padding: 0.75rem; border-radius: 0.25rem;">' +
            'All tables already have no terrain assigned.' +
            '</div>';
    }

    // Clear info message after 4 seconds
    setTimeout(function() {
        if (result.querySelector('.alert-info')) {
            result.innerHTML = '';
        }
    }, 4000);
});
</script>
