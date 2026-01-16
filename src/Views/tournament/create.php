<?php
/**
 * Tournament creation form.
 *
 * Reference: specs/001-table-allocation/spec.md User Story 2
 */

$title = 'Create Tournament';
ob_start();
?>

<article>
    <header>
        <h1>Create New Tournament</h1>
        <p>Set up a new tournament with BCP integration.</p>
    </header>

    <form id="createTournamentForm" method="POST" action="/api/tournaments">
        <label for="name">
            Tournament Name
            <input type="text" id="name" name="name" placeholder="My Tournament January 2026" required>
        </label>

        <label for="bcpUrl">
            BCP Event URL
            <input type="url" id="bcpUrl" name="bcpUrl" placeholder="https://www.bestcoastpairings.com/event/..." required>
            <small>The full URL from Best Coast Pairings for your event.</small>
        </label>

        <label for="tableCount">
            Number of Tables
            <input type="number" id="tableCount" name="tableCount" min="1" max="100" value="8" required>
            <small>The number of physical tables available at the venue (1-100).</small>
        </label>

        <button type="submit" id="submit-btn">
            Create Tournament
        </button>
    </form>

    <div id="result"></div>
</article>

<script>
// Show loading state when form is submitted
document.getElementById('createTournamentForm').addEventListener('submit', function(e) {
    var button = document.getElementById('submit-btn');
    button.disabled = true;
    button.textContent = 'Creating...';
});
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layout.php';
