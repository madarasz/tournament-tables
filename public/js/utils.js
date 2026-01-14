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
