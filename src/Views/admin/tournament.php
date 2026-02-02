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

use TournamentTables\Services\CsrfService;

$title = $tournament->name;
$tableCount = count($tables); // Use actual table count from database
$hasRounds = !empty($rounds);
$justCreated = $justCreated ?? false;
$adminToken = $adminToken ?? null;
$autoImport = $autoImport ?? null;
?>
<!DOCTYPE html>
<html lang="en" data-theme="light">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= htmlspecialchars($title) ?> - Tournament Tables</title>
    <?= CsrfService::getMetaTag() ?>

    <!-- Pico CSS -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">

    <!-- App CSS -->
    <link rel="stylesheet" href="/css/app.css">

    <!-- HTMX -->
    <script src="https://unpkg.com/htmx.org@1.9.10"></script>

    <!-- App utilities -->
    <script src="/js/utils.js"></script>
    <script src="/js/form-utils.js"></script>
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
        </div>
    </nav>

<!-- Tournament name header (extends nav bar) -->
<div class="nav-tournament-name full-bleed">
    <h1><?= htmlspecialchars($tournament->name) ?></h1>
</div>

<!-- Tab Navigation (darker blue bar) -->
<nav class="dashboard-tabs" role="tablist" aria-label="Dashboard sections">
    <button
        role="tab"
        class="dashboard-tab active"
        id="tab-overview"
        data-tab="overview"
        aria-selected="true"
        aria-controls="panel-overview"
        tabindex="0"
    >Overview</button>
    <button
        role="tab"
        class="dashboard-tab"
        id="tab-tables"
        data-tab="tables"
        aria-selected="false"
        aria-controls="panel-tables"
        tabindex="-1"
    >Tables</button>
    <button
        role="tab"
        class="dashboard-tab"
        id="tab-manage"
        data-tab="manage"
        aria-selected="false"
        aria-controls="panel-manage"
        tabindex="-1"
    >Manage</button>
</nav>

<main class="container">

<?php if (isset($_GET['loginSuccess'])): ?>
<article class="alert-success" id="login-success">
    <p><strong>Login Successful!</strong> Welcome back to <?= htmlspecialchars($tournament->name) ?>.</p>
</article>
<script>
// Auto-dismiss login success message after 4 seconds
setTimeout(function() {
    var el = document.getElementById('login-success');
    if (el) {
        el.style.transition = 'opacity 0.5s';
        el.style.opacity = '0';
        setTimeout(function() { el.remove(); }, 500);
    }
}, 4000);
</script>
<?php endif; ?>

<?php if ($justCreated && $adminToken): ?>
<article class="alert-success" id="creation-success" style="margin-top: 1.5rem;">
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

<!-- Tab Panels -->

<?php
// Calculate next round number (MAX + 1 or 1 if no rounds)
$nextRoundNumber = $hasRounds ? max(array_map(function($r) { return $r->roundNumber; }, $rounds)) + 1 : 1;
?>

<!-- Overview Tab Panel -->
<section role="tabpanel" id="panel-overview" class="dashboard-tab-panel" aria-labelledby="tab-overview">
    <!-- General Info -->
    <article style="margin-bottom: 1.5rem;">
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
            <div>
                <a href="<?= htmlspecialchars($tournament->bcpUrl) ?>" target="_blank" rel="noopener">
                    View on Best Coast Pairings
                </a>
            </div>
            <div>
                <a href="/<?= $tournament->id ?>">Public View</a>
            </div>
        </div>
    </article>

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

<!-- Tables Tab Panel -->
<section role="tabpanel" id="panel-tables" class="dashboard-tab-panel" aria-labelledby="tab-tables" hidden>
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

<!-- Manage Tab Panel -->
<section role="tabpanel" id="panel-manage" class="dashboard-tab-panel" aria-labelledby="tab-manage" hidden>
    <h2>Tournament Management</h2>

    <section class="danger-zone">
        <h3 style="color: var(--pico-del-color, #c62828); margin-top: 0;">Delete Tournament</h3>
        <p>Deleting a tournament will permanently remove all rounds, tables, players, and allocations. This action cannot be undone.</p>

        <div style="margin-top: 1rem;">
            <label for="delete-confirm-input">
                Type "<strong><?= htmlspecialchars($tournament->name) ?></strong>" to confirm:
            </label>
            <input
                type="text"
                id="delete-confirm-input"
                placeholder="Enter tournament name"
                autocomplete="off"
            >
        </div>

        <div style="margin-top: 1rem;">
            <button
                type="button"
                id="delete-tournament-button"
                class="delete-button"
                disabled
            >
                <span id="delete-indicator" style="display: none;">Deleting...</span>
                <span id="delete-text">Delete Tournament</span>
            </button>
        </div>

        <div id="delete-result" style="margin-top: 1rem;"></div>
    </section>
</section>

</main>

<footer>
    <div class="container">
        Tournament Tables - Tournament Table Allocation System
    </div>
</footer>

<script>
// Configure HTMX to include CSRF token in requests
document.body.addEventListener('htmx:configRequest', function(event) {
    var csrfToken = document.querySelector('meta[name="csrf-token"]');
    if (csrfToken) {
        event.detail.headers['X-CSRF-Token'] = csrfToken.getAttribute('content');
    }
});

// Tab Controller
(function() {
    var tabs = document.querySelectorAll('.dashboard-tab');
    var panels = document.querySelectorAll('.dashboard-tab-panel');

    function activateTab(tabId) {
        // Update tabs
        tabs.forEach(function(tab) {
            var isActive = tab.getAttribute('data-tab') === tabId;
            tab.classList.toggle('active', isActive);
            tab.setAttribute('aria-selected', isActive ? 'true' : 'false');
            tab.setAttribute('tabindex', isActive ? '0' : '-1');
        });

        // Update panels
        panels.forEach(function(panel) {
            var panelId = panel.id.replace('panel-', '');
            var isActive = panelId === tabId;
            if (isActive) {
                panel.removeAttribute('hidden');
            } else {
                panel.setAttribute('hidden', '');
            }
        });

        // Update URL hash without scrolling
        if (history.replaceState) {
            history.replaceState(null, null, '#' + tabId);
        } else {
            location.hash = tabId;
        }
    }

    // Click handler
    tabs.forEach(function(tab) {
        tab.addEventListener('click', function() {
            activateTab(this.getAttribute('data-tab'));
        });
    });

    // Keyboard navigation
    document.querySelector('.dashboard-tabs').addEventListener('keydown', function(e) {
        var currentTab = document.activeElement;
        if (!currentTab.classList.contains('dashboard-tab')) return;

        var tabsArray = Array.prototype.slice.call(tabs);
        var currentIndex = tabsArray.indexOf(currentTab);
        var newIndex = currentIndex;

        switch (e.key) {
            case 'ArrowLeft':
                newIndex = currentIndex > 0 ? currentIndex - 1 : tabsArray.length - 1;
                break;
            case 'ArrowRight':
                newIndex = currentIndex < tabsArray.length - 1 ? currentIndex + 1 : 0;
                break;
            case 'Home':
                newIndex = 0;
                break;
            case 'End':
                newIndex = tabsArray.length - 1;
                break;
            default:
                return;
        }

        e.preventDefault();
        tabsArray[newIndex].focus();
        activateTab(tabsArray[newIndex].getAttribute('data-tab'));
    });

    // Handle URL hash on load
    function handleHash() {
        var hash = window.location.hash.replace('#', '');
        if (hash && ['overview', 'tables', 'manage'].indexOf(hash) !== -1) {
            activateTab(hash);
        }
    }

    // Initial load
    handleHash();

    // Handle browser back/forward
    window.addEventListener('hashchange', handleHash);
})();

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
    var roundNumber = this.getAttribute('data-round-number');
    var tournamentId = this.getAttribute('data-tournament-id');

    importRound({
        tournamentId: tournamentId,
        roundNumber: roundNumber,
        button: 'import-round-button',
        indicator: 'import-indicator',
        text: 'import-text',
        resultContainer: 'import-result'
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

// Delete tournament functionality
(function() {
    var tournamentName = <?= json_encode($tournament->name) ?>;
    var tournamentId = <?= $tournament->id ?>;
    var confirmInput = document.getElementById('delete-confirm-input');
    var deleteButton = document.getElementById('delete-tournament-button');

    // Enable/disable button based on input match
    confirmInput.addEventListener('input', function() {
        deleteButton.disabled = (this.value !== tournamentName);
    });

    // Handle delete
    deleteButton.addEventListener('click', function() {
        if (confirmInput.value !== tournamentName) return;

        setButtonLoading('delete-tournament-button', 'delete-indicator', 'delete-text', true);
        document.getElementById('delete-result').innerHTML = '';

        fetch('/api/tournaments/' + tournamentId, {
            method: 'DELETE',
            headers: { 'Content-Type': 'application/json' }
        })
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data, ok: response.ok };
            });
        })
        .then(function(response) {
            setButtonLoading('delete-tournament-button', 'delete-indicator', 'delete-text', false);

            if (response.ok) {
                showAlert('delete-result', 'success', 'Tournament deleted. Redirecting...', 0);
                setTimeout(function() {
                    window.location.href = '/admin/';
                }, 1500);
            } else {
                showAlert('delete-result', 'error',
                    'Error: ' + escapeHtml(response.data.message || 'Failed to delete tournament')
                );
            }
        })
        .catch(function(error) {
            setButtonLoading('delete-tournament-button', 'delete-indicator', 'delete-text', false);
            showAlert('delete-result', 'error', 'Network error: ' + escapeHtml(error.message));
        });
    });
})();
</script>
</body>
</html>
