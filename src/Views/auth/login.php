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
        <p><a href="/tournament/create">Create a new tournament</a></p>
    </details>
</article>

<script>
// Handle successful authentication
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/auth' && event.detail.successful) {
        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            if (response.tournamentId) {
                document.getElementById('result').innerHTML = `
                    <article class="alert-success">
                        <header>
                            <h3>Login Successful</h3>
                        </header>
                        <p>Welcome back! You now have access to <strong>${response.tournamentName}</strong>.</p>
                        <p>
                            <a href="/tournament/${response.tournamentId}" role="button">Go to Tournament</a>
                        </p>
                    </article>
                `;
            }
        } catch (e) {
            // JSON parse error, show generic success
            document.getElementById('result').innerHTML = `
                <article class="alert-success">
                    <header><h3>Login Successful</h3></header>
                    <p>You are now authenticated.</p>
                </article>
            `;
        }
    }
});

// Handle authentication errors
document.body.addEventListener('htmx:afterRequest', function(event) {
    if (event.detail.pathInfo.requestPath === '/api/auth' && !event.detail.successful) {
        try {
            const response = JSON.parse(event.detail.xhr.responseText);
            let errorHtml = '<article class="alert-error"><header><h3>Authentication Failed</h3></header>';

            if (response.fields) {
                errorHtml += '<ul>';
                for (const [field, errors] of Object.entries(response.fields)) {
                    errors.forEach(error => {
                        errorHtml += `<li>${error}</li>`;
                    });
                }
                errorHtml += '</ul>';
            } else if (response.message) {
                errorHtml += `<p>${response.message}</p>`;
            } else {
                errorHtml += '<p>Invalid token. Please check and try again.</p>';
            }

            errorHtml += '</article>';
            document.getElementById('result').innerHTML = errorHtml;
        } catch (e) {
            document.getElementById('result').innerHTML = `
                <article class="alert-error">
                    <header><h3>Error</h3></header>
                    <p>An unexpected error occurred. Please try again.</p>
                </article>
            `;
        }
    }
});

// Auto-focus the token input
document.getElementById('token').focus();
</script>

<?php
$content = ob_get_clean();

include __DIR__ . '/../layout.php';
