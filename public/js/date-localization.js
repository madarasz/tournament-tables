/**
 * Tournament Tables - Browser locale date formatting helpers.
 *
 * Replaces server-rendered fallback date strings with browser-locale output.
 */
(function () {
    'use strict';

    /**
     * Parse an input value into a Date object.
     *
     * Supports date-only values (YYYY-MM-DD) as local calendar dates to avoid
     * timezone shift issues in browsers that parse date-only strings as UTC.
     *
     * @param {string|null} value
     * @returns {Date|null}
     */
    function parseDateValue(value) {
        if (!value) {
            return null;
        }

        var raw = String(value).trim();
        if (raw === '') {
            return null;
        }

        var dateOnlyMatch = raw.match(/^(\d{4})-(\d{2})-(\d{2})$/);
        if (dateOnlyMatch) {
            var year = parseInt(dateOnlyMatch[1], 10);
            var month = parseInt(dateOnlyMatch[2], 10) - 1;
            var day = parseInt(dateOnlyMatch[3], 10);
            return new Date(year, month, day);
        }

        var parsed = new Date(raw);
        if (Number.isNaN(parsed.getTime())) {
            return null;
        }

        return parsed;
    }

    /**
     * Format a start/end date pair using browser locale.
     *
     * @param {Date|null} start
     * @param {Date|null} end
     * @returns {string|null}
     */
    function formatDateRange(start, end) {
        if (!start && !end) {
            return null;
        }

        if (typeof Intl === 'undefined' || typeof Intl.DateTimeFormat !== 'function') {
            return null;
        }

        var formatter = new Intl.DateTimeFormat(undefined, { dateStyle: 'medium' });

        if (start && end) {
            if (typeof formatter.formatRange === 'function') {
                return formatter.formatRange(start, end);
            }

            return formatter.format(start) + ' - ' + formatter.format(end);
        }

        return formatter.format(start || end);
    }

    /**
     * Localize all elements with data-local-date-range attributes.
     */
    function localizeDateRanges() {
        var elements = document.querySelectorAll('[data-local-date-range]');

        elements.forEach(function (element) {
            var startRaw = element.getAttribute('data-local-date-start');
            var endRaw = element.getAttribute('data-local-date-end');

            var start = parseDateValue(startRaw);
            var end = parseDateValue(endRaw);
            var localized = formatDateRange(start, end);

            if (localized) {
                element.textContent = localized;
            }
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', localizeDateRanges);
        return;
    }

    localizeDateRanges();
})();
