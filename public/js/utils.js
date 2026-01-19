/**
 * Tournament Tables shared JavaScript utilities.
 *
 * Included globally via layout.php to prevent code duplication
 * and ensure consistent behavior across all views.
 */

/**
 * Escape HTML entities to prevent XSS when inserting dynamic content into DOM.
 *
 * @param {string} text - The text to escape
 * @returns {string} HTML-escaped text
 */
function escapeHtml(text) {
    var div = document.createElement('div');
    div.textContent = text;
    return div.innerHTML;
}

/**
 * Get a cookie value by name.
 *
 * @param {string} name - The cookie name
 * @returns {string} Cookie value or empty string if not found
 */
function getCookie(name) {
    var value = '; ' + document.cookie;
    var parts = value.split('; ' + name + '=');
    if (parts.length === 2) return parts.pop().split(';').shift();
    return '';
}

/**
 * Get the admin token for a specific tournament from the multi-token cookie.
 *
 * The admin_token cookie stores a JSON object with the structure:
 * {"tournaments": {"1": {"token": "abc123...", "name": "...", "lastAccessed": 123}}}
 *
 * @param {number|string} tournamentId - The tournament ID to get the token for
 * @returns {string|null} The admin token or null if not found
 */
function getAdminToken(tournamentId) {
    var cookieValue = getCookie('admin_token');
    if (!cookieValue) {
        return null;
    }

    try {
        var decoded = JSON.parse(cookieValue);
        if (decoded && decoded.tournaments && decoded.tournaments[tournamentId]) {
            return decoded.tournaments[tournamentId].token;
        }
    } catch (e) {
        // Invalid JSON - cookie might be in old format or corrupted
        console.error('Failed to parse admin_token cookie:', e);
    }

    return null;
}
