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
        <p>Enter your BCP event URL and we'll automatically import the tournament name and setup.</p>
    </header>

    <form id="createTournamentForm" method="POST" action="/api/tournaments">
        <label for="bcpUrl">
            BCP Event URL
            <input type="url" id="bcpUrl" name="bcpUrl" placeholder="https://www.bestcoastpairings.com/event/..." required>
            <small>The full URL from Best Coast Pairings for your event. The tournament name will be imported automatically from BCP. Tables will be created from Round 1 pairings.</small>
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
