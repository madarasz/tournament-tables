# Testing Architecture Analysis

**Date**: 2026-01-18
**Status**: Analysis & Recommendations

## Current State Summary

| Layer | Technology | Mocking Approach | Coverage |
|-------|------------|------------------|----------|
| **Unit** | PHPUnit | `$this->createMock()` | Business logic |
| **Integration** | PHPUnit | JSON fixtures + real DB | API parsing |
| **E2E** | Playwright | Server-side mock controller | Partial |
| **Contract** | PHPUnit | Real network calls | BCP API structure |

---

## Critical Issues Identified

### 1. Orphaned Mock Fixtures

`tests/E2E/fixtures/bcp-mock.ts` defines comprehensive mock data:

```typescript
export const round1Pairings = { ... }
export const round2Pairings = { ... }
export function getMockPairings(roundNumber: number) { ... }
```

**But this file is never imported anywhere.** The E2E tests don't actually use these fixtures.

### 2. Incomplete BCP Mocking

The `BCPScraperService` has two external dependencies:

| Method | External Call | Mocked in E2E? |
|--------|--------------|----------------|
| `fetchTournamentName()` | HTML page scrape | Yes (`MockBcpController`) |
| `fetchPairings()` | JSON REST API | **No** |

The service's JSON API URL is hardcoded:

```php
const BCP_API_BASE_URL = 'https://newprod-api.bestcoastpairings.com/v1/events';
```

This means E2E tests cannot test round import flows without making real BCP API calls.

### 3. No HTTP Client Abstraction

Both `fetchJson()` and `fetchHtml()` use raw `file_get_contents()`:

```php
$response = @file_get_contents($url, false, $context);
```

This makes it impossible to:
- Unit test HTTP layer behavior
- Mock responses at the service level
- Test retry/backoff logic without real network delays

### 4. Inconsistent Mock Strategy

| Test Type | Mock Location | Pros | Cons |
|-----------|--------------|------|------|
| Unit | PHPUnit mocks | Fast, isolated | Can't test integration |
| Integration | JSON fixtures | Reproducible | Static, limited scenarios |
| E2E | Server-side controller | Real PHP execution | Can't control from test code |

E2E tests should ideally use Playwright's `page.route()` for network mocking, giving test code control over responses.

### 5. Mock Controller Too Simplistic

`MockBcpController.php` always returns the same pattern:

```php
<h3>Test Tournament {$eventId}</h3>
```

Cannot test:
- Empty/missing tournament names
- Very long names (truncation)
- HTML parsing edge cases
- Error scenarios (BCP down)

### 6. Contract Tests in CI Pipeline Risk

Contract tests make real network calls to BCP. If BCP is slow/down:
- CI builds fail unpredictably
- Tests become flaky

Current mitigation (skip on failure) is good but could still cause CI delays.

---

## Improvement Recommendations

### High Priority

#### 1. Add API Mocking to BCPScraperService

Enable mocking the JSON API URL similar to HTML:

```php
// BCPScraperService.php
private function resolveApiUrl(string $eventId, int $round): string
{
    // Allow mock override for testing
    $mockApiUrl = getenv('BCP_MOCK_API_URL');
    if ($mockApiUrl) {
        return rtrim($mockApiUrl, '/') . "/{$eventId}/pairings?round={$round}";
    }
    return self::BCP_API_BASE_URL . "/{$eventId}/pairings?eventId={$eventId}&round={$round}&pairingType=Pairing";
}
```

Then add a mock API endpoint:

```php
// MockBcpController.php
public function pairings(array $params, ?array $body): void
{
    $eventId = $params['id'] ?? 'unknown';
    $round = (int)($_GET['round'] ?? 1);

    header('Content-Type: application/json');
    echo json_encode($this->getMockPairingsData($round));
}
```

#### 2. Use Playwright Route Interception for E2E

Instead of server-side mocks, intercept at the browser level:

```typescript
// tests/E2E/helpers/bcp-mock.ts
import { Page } from '@playwright/test';
import { getMockPairings } from '../fixtures/bcp-mock';

export async function mockBcpApi(page: Page): Promise<void> {
  // Mock pairings API
  await page.route('**/newprod-api.bestcoastpairings.com/**', async (route) => {
    const url = new URL(route.request().url());
    const round = parseInt(url.searchParams.get('round') || '1');

    await route.fulfill({
      status: 200,
      contentType: 'application/json',
      body: JSON.stringify(transformToApiFormat(getMockPairings(round))),
    });
  });

  // Mock tournament name page
  await page.route('**/bestcoastpairings.com/event/*', async (route) => {
    const eventId = route.request().url().split('/event/')[1];
    await route.fulfill({
      status: 200,
      contentType: 'text/html',
      body: `<html><body><h3>Test Tournament ${eventId}</h3></body></html>`,
    });
  });
}
```

This gives test code full control over mock responses.

#### 3. Introduce HTTP Client Abstraction

Create an injectable HTTP client interface:

```php
// src/Http/HttpClientInterface.php
interface HttpClientInterface
{
    public function get(string $url, array $headers = []): HttpResponse;
}

// src/Http/HttpResponse.php
class HttpResponse
{
    public string $body;
    public int $statusCode;
    public array $headers;
}

// src/Http/NativeHttpClient.php (production)
class NativeHttpClient implements HttpClientInterface
{
    public function get(string $url, array $headers = []): HttpResponse
    {
        // Current file_get_contents implementation
    }
}

// tests/Mocks/MockHttpClient.php
class MockHttpClient implements HttpClientInterface
{
    private array $responses = [];

    public function addResponse(string $urlPattern, HttpResponse $response): void
    {
        $this->responses[$urlPattern] = $response;
    }

    public function get(string $url, array $headers = []): HttpResponse
    {
        foreach ($this->responses as $pattern => $response) {
            if (preg_match($pattern, $url)) {
                return $response;
            }
        }
        throw new \RuntimeException("No mock response for: $url");
    }
}
```

Then inject into `BCPScraperService`:

```php
public function __construct(?HttpClientInterface $httpClient = null)
{
    $this->httpClient = $httpClient ?? new NativeHttpClient();
}
```

### Medium Priority

#### 4. Enhance MockBcpController for Scenarios

Support query parameters for different test scenarios:

```php
public function event(array $params, ?array $body): void
{
    $eventId = $params['id'] ?? 'unknown';
    $scenario = $_GET['scenario'] ?? 'normal';

    switch ($scenario) {
        case 'empty_name':
            $name = '';
            break;
        case 'long_name':
            $name = str_repeat('Long ', 100) . 'Tournament';
            break;
        case 'special_chars':
            $name = 'Tournament & Special <Characters>';
            break;
        case 'error':
            http_response_code(500);
            return;
        default:
            $name = "Test Tournament {$eventId}";
    }

    header('Content-Type: text/html; charset=UTF-8');
    echo "<html><body><h3>{$name}</h3></body></html>";
}
```

#### 5. Separate Contract Tests from CI

Move contract tests to a separate workflow that runs:
- On schedule (daily/weekly)
- On demand
- Not on every PR

```yaml
# .github/workflows/contract-tests.yml
name: Contract Tests
on:
  schedule:
    - cron: '0 6 * * *'  # Daily at 6 AM
  workflow_dispatch:  # Manual trigger

jobs:
  contract:
    runs-on: ubuntu-latest
    steps:
      - uses: actions/checkout@v4
      - name: Run BCP Contract Tests
        run: vendor/bin/phpunit --testsuite contract
```

#### 6. Add Test Database Transactions

Wrap each test in a transaction for true isolation:

```php
// tests/TestCase.php
abstract class DatabaseTestCase extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        Connection::beginTransaction();
    }

    protected function tearDown(): void
    {
        Connection::rollback();
        parent::tearDown();
    }
}
```

### Lower Priority

#### 7. Create Unified Mock Data Factory

Share mock data between PHP and TypeScript:

```php
// tests/Fixtures/MockDataFactory.php
class MockDataFactory
{
    public static function createPairings(int $round, int $count = 4): array
    {
        // Generate consistent mock data
    }

    public static function exportAsJson(string $outputPath): void
    {
        // Export to JSON for TypeScript consumption
    }
}
```

#### 8. Add E2E Test for Full Round Import Flow

Once API mocking is implemented:

```typescript
test('should import round and generate allocations', async ({ page }) => {
  await mockBcpApi(page);  // Now this works!

  const { tournamentId } = await createTournamentAndAuthenticate(page, {...});

  // Import round
  await page.click('[data-testid="import-round-1"]');
  await expect(page.locator('[data-testid="import-success"]')).toBeVisible();

  // Generate allocations
  await page.click('[data-testid="generate-allocations"]');
  await expect(page.locator('[data-testid="allocation-table"]')).toBeVisible();
});
```

---

## Summary Matrix

| Issue | Severity | Effort | Recommendation |
|-------|----------|--------|----------------|
| Orphaned mock fixtures | High | Low | Wire up or remove |
| No API mocking in E2E | High | Medium | Add env var + mock endpoint |
| No HTTP abstraction | Medium | Medium | Introduce interface + DI |
| Simplistic mock controller | Medium | Low | Add scenario support |
| Contract tests in CI | Medium | Low | Move to separate workflow |
| Test DB isolation | Low | Medium | Transaction-based isolation |

The most impactful change would be **enabling BCP API mocking** so E2E tests can cover the full tournament workflow including round imports and allocation generation.

---

## Architecture Diagram

```
┌─────────────────────────────────────────────────────────────────┐
│                        Test Pyramid                              │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│     ┌─────────────┐                                             │
│     │   E2E       │  Playwright + Docker                        │
│     │   Tests     │  Browser-based, full stack                  │
│     └──────┬──────┘                                             │
│            │                                                     │
│     ┌──────▼──────┐                                             │
│     │ Integration │  PHPUnit + MySQL                            │
│     │   Tests     │  Real DB, JSON fixtures                     │
│     └──────┬──────┘                                             │
│            │                                                     │
│     ┌──────▼──────┐                                             │
│     │   Unit      │  PHPUnit + Mocks                            │
│     │   Tests     │  Isolated, fast                             │
│     └─────────────┘                                             │
│                                                                  │
├─────────────────────────────────────────────────────────────────┤
│                     External Dependencies                        │
├─────────────────────────────────────────────────────────────────┤
│                                                                  │
│  ┌─────────────────┐    ┌─────────────────┐                     │
│  │  BCP HTML Page  │    │  BCP REST API   │                     │
│  │  (Tournament    │    │  (Pairings)     │                     │
│  │   Name)         │    │                 │                     │
│  └────────┬────────┘    └────────┬────────┘                     │
│           │                      │                               │
│           │ Mocked via           │ NOT MOCKED                   │
│           │ MockBcpController    │ (Issue #2)                   │
│           │                      │                               │
│  ┌────────▼──────────────────────▼────────┐                     │
│  │         BCPScraperService              │                     │
│  │  - fetchTournamentName() ✓ mockable   │                     │
│  │  - fetchPairings() ✗ hardcoded URL    │                     │
│  └────────────────────────────────────────┘                     │
│                                                                  │
└─────────────────────────────────────────────────────────────────┘
```

## Files Analyzed

- `docker-compose.yml` - Development environment
- `docker-compose.test.yml` - Test environment overlay
- `src/Services/BCPScraperService.php` - BCP integration service
- `src/Controllers/MockBcpController.php` - Mock endpoint for testing
- `public/index.php` - Router with conditional mock routes
- `tests/Unit/Services/AllocationServiceTest.php` - Unit test example
- `tests/Unit/Services/BCPScraperServiceTest.php` - HTML parsing tests
- `tests/Integration/BCPScraperTest.php` - Integration tests with fixtures
- `tests/Integration/TournamentCreationTest.php` - DB integration tests
- `tests/Contract/BCPContractTest.php` - Live BCP contract tests
- `tests/E2E/specs/tournament-creation.spec.ts` - E2E test example
- `tests/E2E/fixtures/bcp-mock.ts` - Unused mock fixtures
- `tests/E2E/fixtures/test-data.ts` - Test data generators
- `tests/E2E/helpers/auth.ts` - Authentication helpers
- `tests/fixtures/bcp_api_response.json` - JSON fixture for integration tests
