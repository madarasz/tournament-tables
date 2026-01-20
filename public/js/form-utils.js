/**
 * Tournament Tables - Form Utility Functions
 *
 * Shared JavaScript utilities for form handling, alerts, and UI feedback.
 * Extracted from inline code to reduce duplication across views.
 * Reference: docs/refactoring-plan.md
 */

/**
 * Set loading state on a button with indicator/text spans.
 *
 * Usage:
 *   <button id="my-button">
 *     <span id="my-indicator" style="display: none;">Loading...</span>
 *     <span id="my-text">Submit</span>
 *   </button>
 *
 *   setButtonLoading('my-button', 'my-indicator', 'my-text', true);
 *
 * @param {string|HTMLElement} button - Button element or ID
 * @param {string|HTMLElement} indicator - Loading indicator element or ID
 * @param {string|HTMLElement} text - Button text element or ID
 * @param {boolean} isLoading - Whether to show loading state
 */
function setButtonLoading(button, indicator, text, isLoading) {
    var btn = typeof button === 'string' ? document.getElementById(button) : button;
    var ind = typeof indicator === 'string' ? document.getElementById(indicator) : indicator;
    var txt = typeof text === 'string' ? document.getElementById(text) : text;

    if (!btn) return;

    btn.disabled = isLoading;

    if (isLoading) {
        btn.classList.add('is-loading');
    } else {
        btn.classList.remove('is-loading');
    }
    
    if (ind) {
        ind.style.display = isLoading ? 'inline' : 'none';
    }

    if (txt) {
        txt.style.display = isLoading ? 'none' : 'inline';
    }
}

/**
 * Create an alert element with the specified type and message.
 *
 * @param {string} type - Alert type: 'success', 'error', 'warning', 'info', 'info-primary', 'info-secondary'
 * @param {string} message - HTML content for the alert (will be escaped if plain text needed)
 * @returns {HTMLElement} The created alert div element
 */
function createAlertElement(type, message) {
    var div = document.createElement('div');
    var className = 'alert ';

    switch (type) {
        case 'success':
            className += 'alert-success';
            break;
        case 'error':
            className += 'alert-error';
            break;
        case 'warning':
            className += 'alert-warning';
            break;
        case 'info-primary':
            className += 'alert-info alert-info-primary';
            break;
        case 'info-secondary':
            className += 'alert-info alert-info-secondary';
            break;
        case 'info':
        default:
            className += 'alert-info';
            break;
    }

    div.className = className;
    div.innerHTML = message;

    return div;
}

/**
 * Show an alert in a container element.
 *
 * @param {string|HTMLElement} container - Container element or ID
 * @param {string} type - Alert type: 'success', 'error', 'warning', 'info', 'info-primary', 'info-secondary'
 * @param {string} message - HTML content for the alert
 * @param {number} [autoHideMs=0] - Auto-hide after milliseconds (0 = don't auto-hide)
 * @returns {HTMLElement} The created alert element
 */
function showAlert(container, type, message, autoHideMs) {
    var el = typeof container === 'string' ? document.getElementById(container) : container;
    if (!el) return null;

    var alert = createAlertElement(type, message);
    el.innerHTML = '';
    el.appendChild(alert);

    if (autoHideMs && autoHideMs > 0) {
        setTimeout(function() {
            if (el.contains(alert)) {
                el.innerHTML = '';
            }
        }, autoHideMs);
    }

    return alert;
}

/**
 * Highlight a table row with a color class that fades out.
 *
 * @param {HTMLElement} row - The table row element
 * @param {string} [colorClass='primary'] - Color type: 'primary' or 'secondary'
 * @param {number} [durationMs=800] - Duration before the highlight fades
 */
function highlightRow(row, colorClass, durationMs) {
    if (!row) return;

    colorClass = colorClass || 'primary';
    durationMs = durationMs || 800;

    var bgColor;
    if (colorClass === 'secondary') {
        bgColor = 'var(--pico-secondary-focus, rgba(98, 119, 140, 0.15))';
    } else {
        bgColor = 'var(--pico-primary-focus, rgba(16, 149, 193, 0.15))';
    }

    row.style.backgroundColor = bgColor;

    setTimeout(function() {
        row.style.backgroundColor = '';
    }, durationMs);
}

/**
 * Create an alert article element with header (used in login.php pattern).
 *
 * @param {string} className - CSS class for the article
 * @param {string} title - Title for the header h3
 * @returns {HTMLElement} The created article element
 */
function createAlertArticle(className, title) {
    var article = document.createElement('article');
    article.className = className;

    var header = document.createElement('header');
    var h3 = document.createElement('h3');
    h3.textContent = title;
    header.appendChild(h3);
    article.appendChild(header);

    return article;
}

/**
 * Handle a standard fetch form submission with loading state and alert display.
 *
 * @param {Object} options - Configuration options
 * @param {string} options.url - The URL to fetch
 * @param {string} [options.method='POST'] - HTTP method
 * @param {Object} [options.body] - Request body (will be JSON stringified)
 * @param {string|HTMLElement} options.button - Button element or ID
 * @param {string|HTMLElement} options.indicator - Loading indicator element or ID
 * @param {string|HTMLElement} options.text - Button text element or ID
 * @param {string|HTMLElement} options.resultContainer - Result container element or ID
 * @param {function} [options.onSuccess] - Callback on success: function(data, response)
 * @param {function} [options.onError] - Callback on error: function(data, response)
 */
function submitWithLoading(options) {
    var url = options.url;
    var method = options.method || 'POST';
    var body = options.body;
    var button = options.button;
    var indicator = options.indicator;
    var text = options.text;
    var resultContainer = options.resultContainer;
    var onSuccess = options.onSuccess;
    var onError = options.onError;

    // Show loading state
    setButtonLoading(button, indicator, text, true);

    var container = typeof resultContainer === 'string'
        ? document.getElementById(resultContainer)
        : resultContainer;
    if (container) {
        container.innerHTML = '';
    }

    var fetchOptions = {
        method: method,
        headers: {
            'Content-Type': 'application/json'
        }
    };

    if (body) {
        fetchOptions.body = JSON.stringify(body);
    }

    fetch(url, fetchOptions)
        .then(function(response) {
            return response.json().then(function(data) {
                return { status: response.status, data: data, ok: response.ok };
            });
        })
        .then(function(response) {
            setButtonLoading(button, indicator, text, false);

            if (response.ok) {
                if (onSuccess) {
                    onSuccess(response.data, response);
                }
            } else {
                if (onError) {
                    onError(response.data, response);
                } else if (container) {
                    var message = response.data.message || 'An error occurred';
                    showAlert(container, 'error', 'Error: ' + escapeHtml(message));
                }
            }
        })
        .catch(function(error) {
            setButtonLoading(button, indicator, text, false);
            if (container) {
                showAlert(container, 'error', 'Network error: ' + escapeHtml(error.message));
            }
        });
}
