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
 */

$title = $tournament->name;
$tableCount = $tournament->tableCount;
$hasRounds = !empty($rounds);
?>

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
                <td><strong>Round <?= $round->roundNumber ?></strong></td>
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
        <p>
            This tournament has <strong><?= $tableCount ?></strong> tables configured.
            <a href="/api/tournaments/<?= $tournament->id ?>/tables" target="_blank">View table details (JSON)</a>
        </p>
    </article>
</section>

<script>
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
                'Successfully imported ' + response.data.pairingsImported + ' pairings for Round ' + roundNumber + '. ' +
                '<a href="/tournament/<?= $tournament->id ?>/round/' + roundNumber + '">Manage Round ' + roundNumber + '</a>' +
                '</div>';
            // Reload after a short delay to show updated rounds list
            setTimeout(function() { location.reload(); }, 2000);
        } else {
            result.innerHTML = '<div class="alert alert-error">' +
                'Error: ' + (response.data.message || 'Failed to import round') +
                '</div>';
        }
    })
    .catch(function(error) {
        button.disabled = false;
        indicator.style.display = 'none';
        text.style.display = 'inline';
        result.innerHTML = '<div class="alert alert-error">Network error: ' + error.message + '</div>';
    });
});
</script>
