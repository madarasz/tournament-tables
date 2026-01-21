# Development Guide

## Creating a New Controller
1. Create file in `src/Controllers/`
2. Add routes in `public/index.php`
3. Apply middleware (AdminAuth, CSRF) as needed
4. Delegate business logic to services
5. Return JSON responses for API routes
6. Use views for HTML routes

## Creating a New Service
1. Create file in `src/Services/`
2. Use constructor injection for dependencies (Connection, other services)
3. Keep methods stateless where possible
4. Add unit tests in `tests/Unit/Services/`
5. Add integration tests if DB interaction needed

## Adding Database Migrations
1. Edit `bin/migrate.php`
2. Add new tables or ALTER statements
3. Test locally: `php bin/migrate.php`
4. Test in Docker: `docker-compose exec php php bin/migrate.php`
5. Update data model documentation if schema changes

## Writing E2E Tests
1. Create spec file in `tests/E2E/specs/`
2. Follow guidelines in `docs/e2e-testing-guidelines.md`
3. Use test helpers from `tests/E2E/helpers/`
4. Focus on critical user flows (test pyramid)
5. Use data-testid selectors where possible

## Using Shared Utilities

The project includes shared CSS and JavaScript utilities to reduce code duplication.
Both files are automatically included via `layout.php`.

### CSS Classes (`public/css/app.css`)

| Category | Classes |
|----------|---------|
| Status labels | `.status-success`, `.status-warning`, `.status-draft`, `.status-published` |
| Badges | `.badge`, `.badge-success`, `.badge-warning`, `.badge-error`, `.badge-info` |
| Alerts | `.alert-success`, `.alert-error`, `.alert-warning`, `.alert-info-primary`, `.alert-info-secondary` |
| Articles | `.warning-article`, `.info-article` |
| Conflict | `.conflict-badge`, `.collision-badge`, `.published-badge` |
| Text | `.text-muted`, `.text-small`, `.text-small-muted`, `.text-center` |
| Spacing | `.mt-1`, `.mt-2`, `.mb-1`, `.mb-2` |
| Visibility | `.hidden`, `.hide-mobile`, `.show-mobile` |

### JavaScript Utilities (`public/js/form-utils.js`)

| Function | Purpose |
|----------|---------|
| `setButtonLoading(button, indicator, text, isLoading)` | Toggle button loading state |
| `showAlert(container, type, message, autoHideMs)` | Display alert in container |
| `createAlertElement(type, message)` | Create alert DOM element |
| `highlightRow(row, colorClass, durationMs)` | Highlight table row temporarily |
| `createAlertArticle(className, title)` | Create article with header |
| `submitWithLoading(options)` | Form submission with loading state |

Example button with loading state:
```html
<button id="submit-btn">
    <span id="submit-indicator" style="display: none;">⏳</span>
    <span id="submit-text">Submit</span>
</button>
<script>
setButtonLoading('submit-btn', 'submit-indicator', 'submit-text', true);
</script>
```

## Best Practices

### Avoiding Code Duplication
- Use CSS classes from `app.css` instead of inline styles
- Use `form-utils.js` for button loading states and alert messages
- Check existing utilities before writing new helper functions

### Views
- Keep views thin: render data, don't compute it
- Use shared CSS classes for consistent styling
- Delegate complex JavaScript to `form-utils.js` utilities

### Controllers
- Keep controllers thin: HTTP handling only
- Delegate business logic to services
- Use middleware for cross-cutting concerns (auth, CSRF)

### Services
- Keep methods stateless where possible
- Use constructor injection for dependencies
- Transaction management belongs in services, not controllers

### Verify by tests
- After finishing development based on a plan file, verify development by running unit and e2e tests
