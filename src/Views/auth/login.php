<?php
/**
 * Admin token login form.
 *
 * Reference: specs/001-table-allocation/spec.md User Story 5
 */

$title = 'Login';
ob_start();
?>

<article>
    <header>
        <h1>Tournament Admin Login</h1>
        <p>Enter your 16-character admin token to access tournament management.</p>
    </header>

    <form id="loginForm" hx-post="/api/auth" hx-target="#result" hx-swap="innerHTML">
        <label for="token">
            Admin Token
            <input
                type="text"
                id="token"
                name="token"
                placeholder="Enter your 16-character token"
                minlength="16"
                maxlength="16"
                pattern="[A-Za-z0-9_-]{16}"
                required
                autocomplete="off"
                spellcheck="false"
            >
            <small>The admin token you received when creating the tournament.</small>
        </label>

        <button type="submit">
            <span class="htmx-indicator">Authenticating...</span>
            <span>Login</span>
        </button>
    </form>

    <div id="result"></div>

    <details>
        <summary>Don't have a token?</summary>
        <p>Admin tokens are generated when you create a new tournament. If you've lost your token, there is currently no way to recover it.</p>
        <p><a href="/admin/tournament/create">Create a new tournament</a></p>
    </details>
</article>

<script>
// Handle successful authentication
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/auth' && event.detail.successful) {
        const resultEl = document.getElementById('result');
        resultEl.innerHTML = '';

        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            if (response.tournamentId) {
                // Show brief redirecting message
                const article = createAlertArticle('alert-success', 'Login Successful');
                const p = document.createElement('p');
                p.textContent = 'Redirecting to tournament...';
                article.appendChild(p);
                resultEl.appendChild(article);

                // Validate tournamentId is numeric to prevent URL injection
                const tournamentId = parseInt(response.tournamentId, 10);
                if (!isNaN(tournamentId) && tournamentId > 0) {
                    window.location.href = '/admin/tournament/' + tournamentId + '?loginSuccess=1';
                } else {
                    window.location.href = '/admin';
                }
            }
        } catch (e) {
            // JSON parse error, redirect to admin home
            window.location.href = '/admin';
        }
    }
});

// Handle authentication errors
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/auth' && !event.detail.successful) {
        const resultEl = document.getElementById('result');
        resultEl.innerHTML = '';

        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            const article = createAlertArticle('alert-error', 'Authentication Failed');

            if (response.fields && typeof response.fields === 'object') {
                const ul = document.createElement('ul');
                for (const [field, errors] of Object.entries(response.fields)) {
                    if (Array.isArray(errors)) {
                        errors.forEach(error => {
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
                p.textContent = 'Invalid token. Please check and try again.';
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

// Auto-focus the token input
document.getElementById('token').focus();
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layout.php';
