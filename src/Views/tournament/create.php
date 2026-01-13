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
        <p>Set up a new Kill Team tournament with BCP integration.</p>
    </header>

    <form id="createTournamentForm" hx-post="/api/tournaments" hx-target="#result" hx-swap="innerHTML">
        <label for="name">
            Tournament Name
            <input type="text" id="name" name="name" placeholder="Kill Team GT January 2026" required>
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

        <button type="submit">
            <span class="htmx-indicator">Creating...</span>
            <span>Create Tournament</span>
        </button>
    </form>

    <div id="result"></div>
</article>

<script>
// Handle successful tournament creation
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/tournaments' && event.detail.successful) {
        const response = JSON.parse(event.detail.xhr.responseText);
        if (response.adminToken) {
            document.getElementById('result').innerHTML = `
                <article class="alert-success">
                    <header>
                        <h3>Tournament Created!</h3>
                    </header>
                    <p><strong>Important:</strong> Save your admin token. You'll need it to manage this tournament.</p>
                    <p class="token-display">${response.adminToken}</p>
                    <p>
                        <a href="/tournament/${response.tournament.id}" role="button">Go to Tournament</a>
                    </p>
                </article>
            `;
        }
    }
});

// Handle validation errors
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/tournaments' && !event.detail.successful) {
        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            let errorHtml = '<article class="alert-error"><header><h3>Error</h3></header>';

            if (response.fields) {
                errorHtml += '<ul>';
                for (const [field, errors] of Object.entries(response.fields)) {
                    errors.forEach(error => {
                        errorHtml += `<li><strong>${field}:</strong> ${error}</li>`;
                    });
                }
                errorHtml += '</ul>';
            } else if (response.message) {
                errorHtml += `<p>${response.message}</p>`;
            }

            errorHtml += '</article>';
            document.getElementById('result').innerHTML = errorHtml;
        } catch (e) {
            document.getElementById('result').innerHTML = `
                <article class="alert-error">
                    <header><h3>Error</h3></header>
                    <p>An unexpected error occurred.</p>
                </article>
            `;
        }
    }
});
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layout.php';
