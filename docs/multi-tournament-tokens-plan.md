# Multi-Tournament Token Storage Implementation Plan

## Overview
Enable users to stay authenticated for multiple tournaments simultaneously by storing up to 20 tournament tokens in a single JSON-formatted cookie. Add a home page UI displaying all accessible tournaments.

## Current State
- Single `admin_token` cookie stores one 16-character token
- Creating/logging into a new tournament overwrites the cookie
- Users lose access to previous tournaments

## Proposed Solution
Store multiple tokens in a JSON-formatted cookie with tournament metadata:

```json
{
  "tournaments": {
    "123": {
      "token": "abc123token12345",
      "name": "Spring Championship",
      "lastAccessed": 1705449600
    },
    "456": {
      "token": "def456token67890",
      "name": "Summer League",
      "lastAccessed": 1705363200
    }
  }
}
```

**Token Limit**: Maximum 20 tournaments. When limit is reached, evict the least-recently-used (LRU) tournament.

## Architecture Decisions

### Why JSON Cookie vs Alternatives?
- **Maintains stateless design** - No session infrastructure needed
- **Cookie-based auth pattern** - Consistent with current implementation
- **Tournament-aware** - Can display tournament names in UI
- **Sufficient capacity** - ~100 tournaments possible within 4KB limit, 20-limit provides safety margin

### Key Constraints
- **PHP 7.1 compatibility** - No PHP 7.2+ features
- **Cookie size limit** - 4KB maximum (20 tournaments = ~2KB with metadata)
- **Security** - Maintain HttpOnly, SameSite=Lax, Secure flags
- **Token format** - Existing 16-character tokens remain unchanged
- **No backward compatibility** - Clean implementation without migration logic

## Critical Files to Modify

### 1. Cookie Management Layer
**File**: `src/Controllers/BaseController.php`

Add new methods for multi-token cookie management:
- `getMultiTokenCookie(): array` - Parse JSON cookie, return tournament map
- `setMultiTokenCookie(array $tournaments): void` - Serialize and set cookie
- `addTournamentToken(int $tournamentId, string $token, string $name): void` - Add/update tournament token with LRU eviction
- `updateLastAccessed(int $tournamentId): void` - Update timestamp for LRU tracking
- Private helper: `evictLRUTournament(array &$tournaments): void` - Remove oldest tournament

### 2. Authentication Middleware
**File**: `src/Middleware/AdminAuthMiddleware.php`

Modify token retrieval and validation:
- Update `getCookieToken()` to parse JSON cookie and extract tournament-specific token
- Extract tournament ID from request URL (e.g., `/api/tournaments/123/...`)
- Match tournament ID to token in cookie map
- Update last accessed timestamp on successful validation

### 3. Authentication Controller
**File**: `src/Controllers/AuthController.php`

Update `authenticate()` method:
- Replace single-token cookie with multi-token cookie
- Call `addTournamentToken()` instead of `setCookie()`

### 4. Tournament Controller
**File**: `src/Controllers/TournamentController.php`

Update `create()` method:
- Replace single-token cookie with multi-token cookie
- Call `addTournamentToken()` with new tournament details

### 5. Home Page Controller (NEW)
**File**: `src/Controllers/HomeController.php` (new file)

Create new controller:
- `index()` method to render home page
- Retrieve all accessible tournaments from cookie
- Fetch tournament details from database (name, table_count, round counts)
- Pass data to view

### 6. Home Page View (NEW)
**File**: `src/Views/home.php` (new file)

Display tournament list:
- Table/card layout showing tournament names
- Link to each tournament dashboard: `/tournaments/{id}`
- Display metadata: table count, number of rounds
- Sorted by last accessed (most recent first)
- Empty state: "No tournaments yet. Create one to get started."

### 7. Router
**File**: `public/index.php`

Add new route:
- `GET /` - HomeController::index() - No auth required (checks cookie client-side)

## Implementation Steps

### Step 1: Add Multi-Token Cookie Methods (BaseController)
Add to `src/Controllers/BaseController.php`:

**getMultiTokenCookie(): array**
```php
protected function getMultiTokenCookie(): array
{
    $cookieValue = $_COOKIE['admin_token'] ?? null;
    if ($cookieValue === null) {
        return [];
    }

    $decoded = json_decode($cookieValue, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('Failed to decode admin_token cookie: ' . json_last_error_msg());
        return [];
    }

    if (!is_array($decoded) || !isset($decoded['tournaments'])) {
        return [];
    }

    return $decoded['tournaments'];
}
```

**setMultiTokenCookie(array $tournaments): void**
```php
protected function setMultiTokenCookie(array $tournaments): void
{
    $cookieValue = json_encode(['tournaments' => $tournaments]);

    if (strlen($cookieValue) > 4096) {
        error_log('Cookie size exceeds 4KB limit. Evicting additional tournaments.');
        // Evict until under limit
        while (strlen($cookieValue) > 4096 && count($tournaments) > 1) {
            $this->evictLRUTournament($tournaments);
            $cookieValue = json_encode(['tournaments' => $tournaments]);
        }
    }

    $this->setCookie('admin_token', $cookieValue, 30 * 24 * 60 * 60);
}
```

**addTournamentToken(int $tournamentId, string $token, string $name): void**
```php
protected function addTournamentToken(int $tournamentId, string $token, string $name): void
{
    $tournaments = $this->getMultiTokenCookie();

    // LRU eviction if limit reached
    if (count($tournaments) >= 20 && !isset($tournaments[$tournamentId])) {
        $this->evictLRUTournament($tournaments);
    }

    $tournaments[$tournamentId] = [
        'token' => $token,
        'name' => $name,
        'lastAccessed' => time()
    ];

    $this->setMultiTokenCookie($tournaments);
}
```

**updateLastAccessed(int $tournamentId): void**
```php
protected function updateLastAccessed(int $tournamentId): void
{
    $tournaments = $this->getMultiTokenCookie();

    if (isset($tournaments[$tournamentId])) {
        $tournaments[$tournamentId]['lastAccessed'] = time();
        $this->setMultiTokenCookie($tournaments);
    }
}
```

**evictLRUTournament(array &$tournaments): void**
```php
private function evictLRUTournament(array &$tournaments): void
{
    if (empty($tournaments)) {
        return;
    }

    $oldestId = null;
    $oldestTime = PHP_INT_MAX;

    foreach ($tournaments as $id => $data) {
        if ($data['lastAccessed'] < $oldestTime) {
            $oldestTime = $data['lastAccessed'];
            $oldestId = $id;
        }
    }

    if ($oldestId !== null) {
        unset($tournaments[$oldestId]);
    }
}
```

### Step 2: Update Authentication Middleware
Modify `src/Middleware/AdminAuthMiddleware.php`:

**Add method to extract tournament ID from URI**
```php
private static function getTournamentIdFromUri(string $uri): ?int
{
    // Match patterns like /tournament/123 or /api/tournaments/123/...
    if (preg_match('#/tournaments?/(\d+)#', $uri, $matches)) {
        return (int)$matches[1];
    }
    return null;
}
```

**Update getCookieToken() method**
```php
private static function getCookieToken(): ?string
{
    $cookieValue = $_COOKIE['admin_token'] ?? null;
    if ($cookieValue === null || empty($cookieValue)) {
        return null;
    }

    // Try JSON format
    $decoded = json_decode($cookieValue, true);
    if (is_array($decoded) && isset($decoded['tournaments'])) {
        $tournaments = $decoded['tournaments'];

        // Extract tournament ID from request URI
        $uri = $_SERVER['REQUEST_URI'] ?? '';
        $tournamentId = self::getTournamentIdFromUri($uri);

        if ($tournamentId !== null && isset($tournaments[$tournamentId])) {
            return $tournaments[$tournamentId]['token'];
        }

        return null;
    }

    return null;
}
```

**Update check() method to update last accessed**
Add after successful authentication (around line 40):
```php
// Update last accessed timestamp
if ($tournament !== null) {
    $uri = $_SERVER['REQUEST_URI'] ?? '';
    $tournamentId = self::getTournamentIdFromUri($uri);
    if ($tournamentId !== null && $tournamentId === $tournament->id) {
        // Note: This requires BaseController methods to be static or extracted to utility
        // Alternative: Update timestamp on next page load in controller
    }
}
```

### Step 3: Update Login Flow (AuthController)
Modify `src/Controllers/AuthController.php` in `authenticate()` method:

Replace:
```php
$this->setCookie('admin_token', $body['token'], 30 * 24 * 60 * 60);
```

With:
```php
$this->addTournamentToken($tournament->id, $body['token'], $tournament->name);
```

### Step 4: Update Tournament Creation Flow (TournamentController)
Modify `src/Controllers/TournamentController.php` in `create()` method:

Replace:
```php
$this->setCookie('admin_token', $result['adminToken'], 30 * 24 * 60 * 60);
```

With:
```php
// Get tournament object to access name
$tournament = Tournament::findById($result['tournamentId']);
$this->addTournamentToken($result['tournamentId'], $result['adminToken'], $tournament->name);
```

### Step 5: Create Home Page Controller
Create `src/Controllers/HomeController.php`:

```php
<?php

declare(strict_types=1);

namespace Controllers;

use Models\Tournament;
use Models\Round;
use Database\Connection;

class HomeController extends BaseController
{
    public function index(): void
    {
        $tournaments = $this->getMultiTokenCookie();

        if (empty($tournaments)) {
            $this->render('home', [
                'tournaments' => [],
                'isEmpty' => true
            ]);
            return;
        }

        // Fetch full tournament details
        $tournamentData = [];
        foreach ($tournaments as $id => $data) {
            try {
                $tournament = Tournament::findById((int)$id);
                if ($tournament === null) {
                    continue; // Skip invalid tournaments
                }

                // Get round count
                $roundCount = Connection::fetchColumn(
                    'SELECT COUNT(*) FROM rounds WHERE tournament_id = ?',
                    [$id]
                );

                $tournamentData[] = [
                    'id' => $tournament->id,
                    'name' => $tournament->name,
                    'tableCount' => $tournament->tableCount,
                    'roundCount' => $roundCount,
                    'lastAccessed' => $data['lastAccessed']
                ];
            } catch (\Exception $e) {
                error_log("Failed to load tournament {$id}: " . $e->getMessage());
                continue;
            }
        }

        // Sort by last accessed (descending)
        usort($tournamentData, function ($a, $b) {
            return $b['lastAccessed'] - $a['lastAccessed'];
        });

        $this->render('home', [
            'tournaments' => $tournamentData,
            'isEmpty' => false
        ]);
    }
}
```

### Step 6: Create Home Page View
Create `src/Views/home.php`:

```php
<?php
$title = 'Tournament Tables';
require __DIR__ . '/layout.php';
?>

<h1>My Tournaments</h1>

<?php if ($isEmpty): ?>
    <article>
        <p>You haven't created or accessed any tournaments yet.</p>
        <a href="/tournament/create" role="button">Create New Tournament</a>
    </article>
<?php else: ?>
    <p><a href="/tournament/create" role="button">Create New Tournament</a></p>

    <table>
        <thead>
            <tr>
                <th>Tournament Name</th>
                <th>Tables</th>
                <th>Rounds</th>
                <th>Last Viewed</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach ($tournaments as $tournament): ?>
                <tr>
                    <td><strong><?= htmlspecialchars($tournament['name']) ?></strong></td>
                    <td><?= $tournament['tableCount'] ?></td>
                    <td><?= $tournament['roundCount'] ?></td>
                    <td><?= formatRelativeTime($tournament['lastAccessed']) ?></td>
                    <td>
                        <a href="/tournament/<?= $tournament['id'] ?>" role="button" class="secondary">
                            View Dashboard
                        </a>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
<?php endif; ?>

<?php
function formatRelativeTime(int $timestamp): string
{
    $diff = time() - $timestamp;

    if ($diff < 60) {
        return 'Just now';
    } elseif ($diff < 3600) {
        $mins = floor($diff / 60);
        return $mins . ' minute' . ($mins > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 86400) {
        $hours = floor($diff / 3600);
        return $hours . ' hour' . ($hours > 1 ? 's' : '') . ' ago';
    } elseif ($diff < 604800) {
        $days = floor($diff / 86400);
        return $days . ' day' . ($days > 1 ? 's' : '') . ' ago';
    } else {
        return date('M j, Y', $timestamp);
    }
}
?>
```

### Step 7: Add Route to Router
Modify `public/index.php`, add route at the beginning of the routes array:

```php
$routes = [
    'GET /' => ['HomeController', 'index', []],
    // ... existing routes
];
```

### Step 8: Add Unit Tests

**Create `tests/Unit/Controllers/BaseControllerTest.php`**:

Tests for multi-token cookie methods:
- Test `getMultiTokenCookie()` with valid JSON format
- Test `getMultiTokenCookie()` with invalid JSON (returns empty array)
- Test `getMultiTokenCookie()` with empty cookie
- Test `addTournamentToken()` adds new tournament
- Test `addTournamentToken()` updates existing tournament
- Test `addTournamentToken()` triggers LRU eviction at limit (20)
- Test `updateLastAccessed()` updates timestamp
- Test `evictLRUTournament()` removes oldest tournament
- Test cookie size validation (>4KB triggers additional eviction)

**Update `tests/Unit/Middleware/AdminAuthMiddlewareTest.php`**:

Add tests:
- Test `getTournamentIdFromUri()` extracts ID from various URL patterns
- Test `getCookieToken()` with multi-token cookie format
- Test token extraction for correct tournament ID
- Test returns null when tournament ID not in cookie

### Step 9: Add Integration Tests

**Create `tests/Integration/Controllers/HomeControllerTest.php`**:

Tests:
- Home page with empty cookie shows empty state
- Home page with multiple tournaments displays all
- Tournaments sorted by last accessed (descending)
- Invalid tournament IDs in cookie are skipped gracefully
- Database errors are handled gracefully

**Update `tests/Integration/AuthenticationFlowTest.php`** (or create if doesn't exist):

Tests:
- Login adds token to existing multi-token cookie
- Tournament creation adds token to existing multi-token cookie
- Creating 21 tournaments evicts oldest (LRU)
- Accessing tournament updates last accessed timestamp

### Step 10: Update E2E Tests

**Modify `tests/E2E/specs/authentication.spec.ts`**:

Add verification at the end of the test (after line 66):

```typescript
// Verify tournament appears on home page
await page.goto('/');
await expect(page.locator('body')).toContainText('My Tournaments');

// Find the tournament in the list (table row containing tournament name)
const tournamentRow = page.locator('tr').filter({ hasText: /Test Tournament/ });
await expect(tournamentRow).toBeVisible();

// Verify View Dashboard button is present
await expect(tournamentRow.locator('a[role="button"]')).toBeVisible();
```

**Modify `tests/E2E/specs/tournament-creation.spec.ts`**:

Add verification at the end of the test (after line 76):

```typescript
// Navigate to home page
await page.goto('/');

// Verify tournament is listed on home page
await expect(page.locator('h1')).toContainText('My Tournaments');
await expect(page.locator('body')).toContainText(tournamentData.name);

// Verify tournament appears in the table with correct metadata
const tournamentRow = page.locator('tr').filter({ hasText: tournamentData.name });
await expect(tournamentRow).toBeVisible();

// Verify table count is displayed (auto-imported from BCP)
await expect(tournamentRow).toContainText(/\d+ tables?/i);

// Verify "View Dashboard" button works
await tournamentRow.locator('a[role="button"]', { hasText: 'View Dashboard' }).click();
await page.waitForURL(/\/tournament\/\d+/);
expect(page.url()).toContain(`/tournament/${tournamentId}`);
```

## Security Considerations

### Maintain Current Security Properties
- **HttpOnly**: Prevent JavaScript access to tokens
- **SameSite=Lax**: CSRF protection
- **Secure**: HTTPS-only transmission (when available)
- **30-day expiration**: Unchanged from current implementation

### New Security Considerations
- **Cookie size validation**: Reject cookies >4KB before setting
- **JSON parsing**: Use `json_decode()` with error handling (return empty array on failure)
- **Token format validation**: Verify each token is exactly 16 characters before storage
- **Tournament ID validation**: Ensure tournament IDs are positive integers
- **No exposure of parsing errors**: Log errors server-side, don't expose to clients

### No Changes to Token Generation
- Token generation (`TokenGenerator`) remains unchanged
- Database schema (`admin_token` column) remains unchanged
- Token uniqueness constraint remains enforced

## Error Handling

### Cookie Size Exceeded (>4KB)
- Log warning
- Evict additional tournaments beyond LRU to fit within limit
- Fallback: Keep only most recently accessed 10 tournaments if still exceeding

### JSON Parsing Failures
- Return empty array (treat as no authentication)
- Log error for debugging
- Do not expose parsing errors to client

### Database Errors During Home Page Load
- Skip tournaments that fail to load
- Display partial list (no error banner to keep UX simple)
- Log errors for debugging

### Invalid Tournament IDs in Cookie
- Skip silently during home page rendering
- Clean up on next cookie write (optional future enhancement)

## Testing Strategy

### Unit Tests (New/Updated)
1. **BaseControllerTest** - Cookie serialization, LRU eviction, size validation
2. **AdminAuthMiddlewareTest** - Multi-token validation, tournament ID extraction
3. **HomeControllerTest** - Tournament list rendering, sorting, error handling

### Integration Tests (New/Updated)
1. **HomeControllerTest** - Full integration with database
2. **AuthenticationFlowTest** - Multi-tournament auth flow, LRU eviction
3. **Multi-tournament access flow** - Create 2+ tournaments, verify both accessible

### E2E Tests (Updated)
1. **authentication.spec.ts** - Add home page verification after login
2. **tournament-creation.spec.ts** - Add home page verification after creation

## Verification Steps

After implementation, verify:

1. **Create Multiple Tournaments**:
   - Create 3 tournaments via UI
   - Verify cookie contains all 3 tokens with metadata
   - Inspect cookie in browser DevTools (should be JSON format)

2. **Home Page Display**:
   - Navigate to `/` → verify tournament list displays
   - Verify tournaments sorted by last accessed
   - Verify metadata displayed (table count, round count)
   - Click "View Dashboard" → verify correct dashboard loads

3. **Access Different Tournaments**:
   - Navigate to tournament 1 dashboard → verify access granted
   - Navigate to tournament 2 dashboard → verify access granted
   - Check cookie: verify lastAccessed timestamps updated

4. **LRU Eviction**:
   - Create 21 tournaments programmatically (via API loop in test)
   - Verify cookie contains only 20 most recent
   - Verify oldest tournament evicted

5. **Cookie Size**:
   - Create 20 tournaments
   - Inspect cookie size (should be ~2KB)
   - Verify well under 4KB limit

6. **Run All Tests**:
   ```bash
   # PHPUnit tests
   docker-compose exec php ./vendor/bin/phpunit

   # E2E tests
   docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d
   docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test
   ```

## Implementation Phases

### Phase 1: Backend Implementation
- Add multi-token cookie methods to BaseController
- Update middleware for token extraction and tournament ID parsing
- Update login and tournament creation flows
- Add unit tests for new methods

### Phase 2: Home Page Implementation
- Create HomeController with tournament list logic
- Create home.php view with Pico CSS styling
- Add route to router
- Add integration tests for home page

### Phase 3: Testing & Validation
- Add E2E test verifications to existing tests
- Run all test suites (unit, integration, E2E)
- Manual testing with multiple tournaments
- Verify cookie size and LRU eviction

### Phase 4: Documentation
- Update CLAUDE.md with new authentication behavior
- Document home page route
- Add inline code comments for complex logic

## Risk Assessment

### Low Risk
- LRU eviction (well-tested algorithm, standard pattern)
- JSON encoding/decoding (standard PHP functions with error handling)
- Home page display (read-only, minimal logic)

### Medium Risk
- Cookie size limits (mitigated by 20-tournament cap, size checks, and additional eviction)
- Parsing errors (mitigated by error handling and graceful degradation)
- Tournament ID extraction from URLs (mitigated by comprehensive regex testing)

### No Risk
- Token generation unchanged
- Database schema unchanged
- No backward compatibility concerns (clean break)

## Estimated Complexity
**Medium** - Requires changes across 7 files with new logic, but well-scoped and straightforward implementation.

## Dependencies
None - all changes are self-contained within the existing codebase. No new libraries or external dependencies required.

## Open Questions
None - all design decisions have been finalized based on user feedback.
