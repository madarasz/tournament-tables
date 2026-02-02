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

    <form id="createTournamentForm" hx-post="/api/tournaments" hx-target="#result" hx-swap="innerHTML">
        <label for="bcpUrl">
            BCP Event URL
            <input type="url" id="bcpUrl" name="bcpUrl" placeholder="https://www.bestcoastpairings.com/event/..." required>
            <small>The full URL from Best Coast Pairings for your event. The tournament name will be imported automatically from BCP. Tables will be created from Round 1 pairings.</small>
        </label>

        <button type="submit" id="submit-btn">
            <span id="submit-indicator" style="display: none;">Creating...</span>
            <span id="submit-text">Create Tournament</span>
        </button>
    </form>

    <div id="result"></div>
</article>

<script>
// Show loading state when form is submitted
document.getElementById('createTournamentForm').addEventListener('submit', function(e) {
    setButtonLoading('submit-btn', 'submit-indicator', 'submit-text', true);
});

// Handle successful tournament creation (server returns 3xx redirect)
document.body.addEventListener('htmx:beforeOnLoad', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/tournaments') {
        // Check if we got a redirect response (HTMX follows redirects and returns the final page)
        // Success case: server redirects to dashboard, HTMX returns HTML
        const xhr = event.detail.xhr;
        const contentType = xhr.getResponseHeader('Content-Type') || '';

        // If we got HTML back, the server redirected us - follow it
        if (contentType.indexOf('text/html') !== -1) {
            // Get the final URL from the redirect chain
            const finalUrl = xhr.responseURL;
            if (finalUrl && finalUrl !== window.location.href) {
                window.location.href = finalUrl;
                event.preventDefault();
                return;
            }
        }
    }
});

// Handle creation errors
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/tournaments' && !event.detail.successful) {
        const resultEl = document.getElementById('result');
        resultEl.innerHTML = '';
        setButtonLoading('submit-btn', 'submit-indicator', 'submit-text', false);

        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            const article = createAlertArticle('alert-error', 'Tournament Creation Failed');

            if (response.fields && typeof response.fields === 'object') {
                const ul = document.createElement('ul');
                for (const [field, errors] of Object.entries(response.fields)) {
                    if (Array.isArray(errors)) {
                        errors.forEach(function(error) {
                            const li = document.createElement('li');
                            li.textContent = String(error);
                            ul.appendChild(li);
                        });
                    }
                }
                article.appendChild(ul);
            } else if (response.message) {
                const p = document.createElement('p');
                p.textContent = String(response.message);
                article.appendChild(p);
            } else {
                const p = document.createElement('p');
                p.textContent = 'An error occurred. Please try again.';
                article.appendChild(p);
            }

            resultEl.appendChild(article);
        } catch (e) {
            const article = createAlertArticle('alert-error', 'Error');
            const p = document.createElement('p');
            p.textContent = 'An unexpected error occurred. Please try again.';
            article.appendChild(p);
            resultEl.appendChild(article);
        }
    }
});

// Auto-focus the URL input
document.getElementById('bcpUrl').focus();
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layout.php';
