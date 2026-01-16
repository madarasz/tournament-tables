# E2E Testing Guidelines

This document defines the principles for writing End-to-End (E2E) tests in this project. Follow these guidelines when generating or reviewing E2E tests.

## Core Philosophy

E2E tests sit at the top of the test pyramid and should be:
- **Few in number** - Only test the most critical user flows
- **High value** - Validate complete user journeys, not technical details
- **Browser-based** - Simulate real user interactions through the UI

## What E2E Tests SHOULD Cover

### Primary User Flow examples
Test the happy path of core features that users perform regularly:
- Creating a tournament (the most common entry point)
- Generating table allocations for a round
- Publishing allocations for players to view
- Viewing published allocations as a player

### Compound Validations
A single E2E test can and should validate multiple requirements as part of one flow. For example, a "Successful Tournament Creation" test can verify:
- Form submission works
- Redirect to dashboard occurs
- Tournament appears in the list
- Admin token is displayed

Do not create separate atomic tests for each of these behaviors.

## What E2E Tests Should NOT Cover

### Technical Requirements
Do not write E2E tests for implementation details:
- Cookie format or retention periods
- Token generation algorithms or uniqueness
- API response formats
- Database state verification
- Internal data structures

### Constraint Validation
Do not write E2E tests for boundary conditions:
- Minimum/maximum values (e.g., table count limits)
- Field length constraints
- Data type validations

These belong in unit or integration tests.

### Error Scenarios
Do not write E2E tests for error handling unless explicitly required:
- Form validation errors
- Invalid input messages
- Network error handling
- Edge case error states

### API-Based Tests
E2E tests must be browser-based user simulations:
- Do not make direct API calls to verify behavior
- Do not bypass the UI to set up test state (unless absolutely necessary for fixtures)
- Test what users see and do, not what the backend returns

## Test Structure Guidelines

### Consolidate Related Behaviors
Instead of:
```
- 'Successful Tournament Creation'
- 'should redirect to dashboard after creation'
- 'should display admin token'
```

Write:
```
- 'Successful Tournament Creation' (includes redirect and token display verification)
```

### Name Tests After User Goals
Good: "Create tournament and generate round 1 allocations"
Bad: "should set cookie with correct expiration"

### Keep Test Count Minimal
A typical feature should have 2-5 E2E tests covering:
1. The primary happy path
2. One or two critical alternative flows
3. Any explicitly required error scenarios

## Examples

### Good E2E Test
```typescript
test('Organizer creates tournament and generates allocations', async () => {
  // Navigate to home page
  // Fill tournament creation form
  // Submit and verify redirect to dashboard
  // Verify admin token is displayed
  // Import pairings for round 1
  // Generate allocations
  // Verify allocations are displayed
});
```

### Tests to Avoid
```typescript
// Too technical - validates implementation detail
test('should generate 16-character base64 admin token', ...)

// Constraint validation - belongs in unit tests
test('should show error for table count exceeding 100', ...)

// Error scenario - not a primary user flow
test('should show error for invalid BCP URL', ...)

// API-based - not a browser simulation
test('should verify API returns correct tournament data', ...)

// Overly lenient - passes even when feature is missing
test('should display terrain options', async () => {
  if (await terrainSelect.isVisible()) {
    // verify options
  }
  // Passes if feature doesn't exist!
});
```

## Running E2E Tests

### Docker Environment Setup

```bash
# Start test environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d

# Run all tests
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test

# Run a single test file
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test specs/authentication.spec.ts

# Run a specific test by name pattern
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test -g "should authenticate with valid admin token"

# Stop environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml down
```

### Test Artifacts

After running tests:
- **HTML Report**: `tests/E2E/playwright-report/index.html`
  - View with: `cd tests/E2E && npx playwright show-report`
- **Test Results**: `tests/E2E/test-results/`
  - Contains per-test directories with failure artifacts

## Debugging Failing Tests

### Failure Artifacts

When tests fail, Playwright generates artifacts in `tests/E2E/test-results/<test-name>/`:

| File | Description |
|------|-------------|
| `test-failed-1.png` | Screenshot at the moment of failure |
| `error-context.md` | Accessibility tree snapshot showing page structure |
| `trace.zip` | Step-by-step trace (when retries enabled) |

### Using error-context.md

The `error-context.md` file contains a YAML-formatted accessibility tree that shows:
- All visible elements with their roles and text
- Interactive element states (buttons, inputs)
- Current values in form fields
- Element references for debugging selectors

Example:
```yaml
- button "Login" [active] [ref=e20] [cursor=pointer]
- article [ref=e22]:
  - heading "Login Successful" [level=3]
```

### Enable Traces for Deep Debugging

Temporarily modify `playwright.config.ts`:
```typescript
trace: 'on',  // Instead of 'on-first-retry'
retries: 1,   // Enable retries to capture traces
```

Then view traces in the HTML report or with:
```bash
npx playwright show-trace tests/E2E/test-results/<test-name>/trace.zip
```

### Interactive Debugging Commands

```bash
# Run with Playwright Inspector (step through test)
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test --debug

# Run with UI mode (interactive test explorer)
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test --ui

# Generate test code by recording browser actions
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright codegen http://php:80
```

## Developing E2E Tests

Use `chrome-devtools` or `playwright` MCP to inspect pages and verify locators.

**A test is only complete after it has been run and verified passing.**

### Selector Best Practices

Prefer selectors in this order (most to least reliable):

1. **data-testid attributes**: `[data-testid="admin-token"]`
2. **Role-based**: `page.getByRole('button', { name: 'Login' })`
3. **Text-based**: `page.getByText('Login Successful')`
4. **CSS as last resort**: `.admin-token`

### Assertion Strategies

**CRITICAL: Tests must fail when features are missing.** Avoid conditional logic that allows tests to pass silently when the feature isn't implemented.

```typescript
// BAD: Test passes even if feature doesn't exist
if (await terrainSelect.isVisible()) {
  // Verify terrain types are present
}
// If terrainSelect doesn't exist, test passes without testing anything!

// BAD: Overly broad OR condition
expect(hasTerrainSelects || hasConfigLinks || hasSection).toBeTruthy();
// Passes if ANY element exists, not necessarily the right one

// GOOD: Direct assertion that fails if element doesn't exist
const terrainSelect = page.locator('select[name*="terrain"]').first();
await expect(terrainSelect).toBeVisible();
const options = await terrainSelect.locator('option').allTextContents();
expect(options).toContain('Desert');

// GOOD: If flexibility needed, fail explicitly in else branch
const terrainSelect = page.locator('select[name*="terrain"]').first();
if (await terrainSelect.count() > 0) {
  await expect(terrainSelect).toBeVisible();
  // ... assertions
} else {
  throw new Error('Terrain configuration UI not found - feature may not be implemented');
}
```

**Why this matters**: Tests that silently pass when features are missing provide false confidence. They defeat the purpose of E2E testing by not detecting when critical user flows are broken or incomplete.

### Wait Strategies

```typescript
// GOOD: Wait for specific element/state
await expect(page.getByText('Login Successful')).toBeVisible();
await page.waitForURL(/\/tournament\/\d+/);

// BAD: Arbitrary timeouts (flaky, slow)
await page.waitForTimeout(500);
```

### Handling HTMX/Dynamic Content

The app uses HTMX for partial page updates. Wait for content changes explicitly:

```typescript
// Wait for HTMX response to complete
await page.waitForResponse(resp => resp.url().includes('/api/'));

// Or wait for specific content to appear
await expect(page.locator('.success-message')).toBeVisible();

// Wait for element to be removed (after HTMX swap)
await expect(page.locator('.loading')).toBeHidden();
```

### Test Data & Cleanup

**Fixtures location**: `tests/E2E/fixtures/`
- `test-data.ts` - Test data generators
- `bcp-mock.ts` - BCP API mocks

**Always use cleanup context** to ensure test isolation:
```typescript
const cleanupContext = createCleanupContext();

test.afterEach(async ({ request, baseURL }) => {
  await cleanupTournaments(request, cleanupContext, baseURL!);
});
```

### Common Failure Patterns

| Symptom | Likely Cause | Solution |
|---------|--------------|----------|
| Timeout waiting for URL | App shows message instead of redirect | Wait for success element, then click navigation |
| Element not found | HTMX hasn't updated DOM yet | Add explicit wait for element |
| Stale element | DOM replaced by HTMX | Re-query element after wait |
| `null` vs `undefined` | API contract mismatch | Check API response format, update assertion |

## Relationship to Other Test Types

| Test Type | Purpose | Count |
|-----------|---------|-------|
| **Unit** | Technical correctness, edge cases, algorithms | Many |
| **Integration** | Component interactions, API contracts, database | Moderate |
| **E2E** | Critical user journeys through the UI | Few |

E2E tests should assume that unit and integration tests already cover:
- Input validation logic
- Error handling
- Boundary conditions
- API correctness
- Business rule enforcement

E2E tests verify that the complete system works together for real user scenarios.
