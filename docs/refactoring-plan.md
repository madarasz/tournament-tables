# Code Refactoring Plan

## Goals

This document tracks code quality improvements across the codebase. When analyzing folders:

1. **Identify code duplication** - Look for repeated patterns, similar loops, duplicate HTML/CSS
2. **Extract helper functions** - Move complex logic out of views/controllers into reusable helpers
3. **Centralize common patterns** - Alert rendering, form handling, data formatting
4. **Move business logic to services** - Views should only render, not compute
5. **Reduce file size** - Target files over 500 lines for splitting/extraction

## Files Created

| File | Purpose |
|------|---------|
| `public/css/app.css` | Extracted CSS classes for status labels, badges, alerts, utilities |
| `public/js/form-utils.js` | Shared JavaScript utilities for button loading, alerts, row highlighting |
| `src/Services/AllocationGenerationService.php` | Extracted allocation generation logic from RoundController |
| `src/Services/TournamentImportService.php` | Extracted Round 1 auto-import logic from TournamentController |
| `src/Services/DatabaseQueryHelper.php` | Trait for common database query patterns |
| `src/Services/BcpUrlValidator.php` | Centralized BCP URL validation and extraction |
| `src/Models/BaseModel.php` | Abstract base class with shared CRUD methods (find, save, delete) |
| `src/Services/TableCollisionDetector.php` | Service for detecting table allocation collisions |

---

## src/Views Refactoring

**Analysis date:** 2026-01-20
**Total files:** 8
**Total lines:** 2,685
**Estimated duplication:** 20-30%

### File Inventory

| File | Lines | Notes |
|------|-------|-------|
| `layout.php` | 206 | Master layout template |
| `home.php` | 84 | Tournament list view |
| `auth/login.php` | 160 | Login form + handlers |
| `tournament/create.php` | 45 | Tournament creation form |
| `tournament/dashboard.php` | 583 | Tournament admin dashboard |
| `round/manage.php` | 1,010 | Round management (largest file) |
| `public/tournament.php` | 231 | Public tournament view |
| `public/round.php` | 369 | Public round display |

### Issues Found

| Priority | Issue | Affected Files | Est. Lines Saved |
|----------|-------|----------------|------------------|
| HIGH | Alert/message HTML duplicated (6+ instances) | dashboard.php, login.php | ~50 |
| HIGH | Button+loading indicator JS pattern (4 handlers) | dashboard.php | ~80 |
| HIGH | Inline CSS styles (8+ repeated patterns) | dashboard.php, manage.php | ~40 |
| MEDIUM | Terrain dropdown rendering duplicated | dashboard.php, manage.php | ~20 |
| MEDIUM | Row highlight animation duplicated (2x) | dashboard.php | ~15 |
| MEDIUM | Fetch error handling duplicated | dashboard.php | ~20 |
| LOW | Local helper functions not shared | home.php, login.php, manage.php | ~30 |

### Existing Utility Files

| File | Contents |
|------|----------|
| `public/js/utils.js` (67 lines) | `escapeHtml()`, `getCookie()`, `getAdminToken()` |
| `public/css/app.css` (NEW) | Status labels, badges, alert variants, utilities |

### Implementation Checklist

#### Step 1: CSS Extraction (COMPLETED)

- [x] Create `public/css/app.css`
- [x] Add status label classes (`.status-success`, `.status-warning`, `.status-draft`, `.status-published`)
- [x] Add badge classes (`.badge`, `.badge-success`, `.badge-warning`, `.badge-error`, `.badge-info`)
- [x] Add alert info variants (`.alert-info-primary`, `.alert-info-secondary`)
- [x] Add warning/info article styles (`.warning-article`, `.info-article`)
- [x] Add utility classes (`.hidden`, `.text-muted`, `.text-small`, `.mt-1`, etc.)
- [x] Add conflict/collision badge styles
- [x] Add row highlight animation classes
- [x] Update `layout.php` to include `app.css`

#### Step 2: Create JavaScript Utilities (COMPLETED)
- [x] Create `public/js/form-utils.js`
- [x] Add `setButtonLoading(button, indicatorId, textId, isLoading)` function
- [x] Add `showAlert(containerId, type, message, autoHideMs)` function
- [x] Add `createAlertElement(type, message)` function
- [x] Add `highlightRow(row, colorClass, durationMs)` function
- [x] Add `createAlertArticle(className, title)` function
- [x] Add `submitWithLoading(options)` helper function
- [x] Update `layout.php` to include `form-utils.js`

#### Step 3: Update dashboard.php (~100 lines reduction) (COMPLETED)
- [x] Replace inline status colors with `.status-success`/`.status-warning` classes
- [x] Include `form-utils.js` script (via layout.php)
- [x] Replace 4 form handlers with `setButtonLoading()` calls
- [x] Replace alert constructions with `showAlert()` calls
- [x] Replace row highlight code with `highlightRow()` calls
- [x] Replace alert-info inline styles with `.alert-info-primary`/`.alert-info-secondary` classes

#### Step 4: Update login.php (COMPLETED)
- [x] Replace `createAlertArticle()` local function with shared utility from form-utils.js
- [x] Use CSS classes for alert styling (via layout.php includes)

#### Step 5: Update round/manage.php (COMPLETED)
- [x] Replace warning article inline style with `.warning-article` class
- [x] Include app.css and form-utils.js for shared styles
- [x] Status badge and conflict/collision badge styles already defined in page-specific styles

#### Step 6: Update tournament/create.php (COMPLETED)
- [x] Use `setButtonLoading()` for form submission
- [x] Add indicator/text spans to button for loading state

#### Step 7: Verification (COMPLETED)
- [x] Run PHPUnit tests: `composer test:unit` - 214 tests, 649 assertions passed
- [x] Run E2E tests: `composer test:e2e` - 4 tests passed
- [ ] Manual test: Create tournament, dashboard displays correctly
- [ ] Manual test: Import round from BCP, alerts work
- [ ] Manual test: Edit allocation, collision detection works
- [ ] Manual test: Public views display correctly

---

## Detailed Duplication Analysis

### Alert Creation Patterns

**login.php (lines 52-62)** - Local helper function:
```javascript
function createAlertArticle(className, title) {
    const article = document.createElement('article');
    article.className = className;
    // ... creates article with header
}
```
Used 4x in same file.

**dashboard.php** - Inline HTML string construction:
```javascript
result.innerHTML = '<div class="alert alert-success">' + message + '</div>';
result.innerHTML = '<div class="alert alert-error">' + message + '</div>';
```
Used 6+ times across import, terrain, apply, clear handlers.

### Button Loading State Patterns

**dashboard.php** - Repeated 4 times:
```javascript
button.disabled = true;
indicator.style.display = 'inline';
text.style.display = 'none';
// ... fetch request
button.disabled = false;
indicator.style.display = 'none';
text.style.display = 'inline';
```

Locations:
- Import form handler (lines 377-396)
- Terrain config handler (lines 443-465)
- Apply all terrain handler (lines 505-520)
- Clear all terrain handler (lines 548-563)

### Row Highlight Animation

**dashboard.php** - Duplicated twice:
```javascript
row.style.backgroundColor = 'var(--pico-primary-focus, rgba(16, 149, 193, 0.15))';
setTimeout(function() {
    row.style.backgroundColor = '';
}, 800);
```

### Inline CSS That Can Use app.css

| Current Inline Style | Replace With |
|---------------------|--------------|
| `style="color: #4caf50; font-weight: bold;"` | `class="status-success"` |
| `style="color: #ff9800; font-weight: bold;"` | `class="status-warning"` |
| `style="display: none;"` | `class="hidden"` |
| `style="font-size: 0.9rem; color: #666;"` | `class="text-small-muted"` |
| `style="margin-top: 1rem;"` | `class="mt-1"` |
| `style="background-color: #fff3e0; border-left: 4px solid #ff9800;"` | `class="warning-article"` |
| Long alert-info styles | `class="alert-info-primary"` |

---

## src/Controllers Refactoring

**Analysis date:** 2026-01-20
**Total files:** 10
**Total lines:** 1,999
**Estimated duplication:** ~21%

### File Inventory

| File | Lines | Priority | Notes |
|------|-------|----------|-------|
| `RoundController.php` | 491 | **CRITICAL** | Largest, significant business logic |
| `TournamentController.php` | 398 | **HIGH** | Large, business logic duplication |
| `BaseController.php` | 284 | Foundation | Good abstraction base |
| `ViewController.php` | 266 | **HIGH** | Duplicate render logic |
| `AllocationController.php` | 179 | MEDIUM | Auth duplication |
| `MockBcpController.php` | 118 | LOW | Test utility |
| `HomeController.php` | 91 | MEDIUM | Duplicate render logic |
| `PublicController.php` | 88 | LOW | Clean |
| `AuthController.php` | 53 | MEDIUM | Small but duplicates |
| `TerrainTypeController.php` | 31 | LOW | Minimal |

### Issues Found

| Priority | Issue | Affected Files | Est. Lines Saved |
|----------|-------|----------------|------------------|
| CRITICAL | Auth validation repeated (9x) | RoundController, TournamentController, AllocationController | ~36 |
| CRITICAL | Business logic in controllers (~265 lines) | RoundController:224-354, TournamentController:147-281 | ~265 |
| HIGH | Render method duplication | HomeController:77-90, ViewController:196-202 | ~15 |
| HIGH | Tournament lookup duplication (8x) | All view/API controllers | ~16 |
| HIGH | Round lookup duplication (8x) | RoundController, ViewController, PublicController | ~16 |
| MEDIUM | Session management duplication | TournamentController:286-290, ViewController:84-88 | ~8 |
| MEDIUM | Array transformation patterns (6x) | TournamentController, RoundController, PublicController | ~18 |
| MEDIUM | Exception handling inconsistency | RoundController:205-211, TournamentController:132-138 | ~12 |
| LOW | Cookie HTTPS check duplication | BaseController:106-108, 139-141 | ~6 |

### Detailed Duplication Patterns

#### Authentication Validation (9 occurrences)
```php
// RoundController:38-42, 367-371, 419-423, 461-465
// TournamentController:312-316, 347-351, 373-377
// AllocationController:52-56, 142-146
$authTournament = AdminAuthMiddleware::getTournament();
if ($authTournament === null || $authTournament->id !== $tournamentId) {
    $this->unauthorized('Token does not match this tournament');
    return;
}
```

#### Business Logic That Should Be In Services

**RoundController::runAllocationGeneration()** (lines 224-354, 130 lines):
- Builds BCP table lookup, extracts pairings, maps tables, saves allocations
- Complex transaction management mixed with HTTP handling

**TournamentController::attemptAutoImportRound1()** (lines 147-281, 135 lines):
- Table creation, round creation, player creation loop, allocation creation
- Entire import workflow should be in a service

### Implementation Checklist

#### Phase 1: Foundation Helpers (Priority: CRITICAL) - COMPLETED
- [x] Create `BaseController::verifyTournamentAuth($id)` - eliminate 9 repetitions
- [x] Create `BaseController::getTournamentOrFail($id)` - eliminate 8 repetitions
- [x] Create `BaseController::getRoundOrFail($tournamentId, $roundNumber)` - eliminate 8 repetitions
- [x] Move `ensureSession()` to BaseController
- [x] Move `redirect()` to BaseController
- [x] Add `renderView()` to BaseController
- [x] **Achieved savings: ~75 lines**

#### Phase 2: Service Extraction (Priority: CRITICAL) - COMPLETED
- [x] Extract `RoundController::runAllocationGeneration()` to `AllocationGenerationService`
- [x] Extract `TournamentController::attemptAutoImportRound1()` to `TournamentImportService`
- [x] Keep controllers thin: HTTP request/response only
- [x] **Achieved savings: ~265 lines (moved to services)**

#### Phase 3: Utilities (Priority: MEDIUM) - COMPLETED
- [x] Create `BaseController::toArrayMap($collection)` helper
- [x] Extract HTTPS check to `isHttps()` helper in BaseController
- [ ] Standardize exception-to-HTTP-status mapping (deferred - low impact)
- [x] **Achieved savings: ~30 lines**

#### Phase 4: Verification - COMPLETED
- [x] Run PHPUnit tests: `composer test:unit` - 214 tests passed
- [x] Run E2E tests: `composer test:e2e` - 4 tests passed
- [ ] Manual test: All admin operations work
- [ ] Manual test: All public views render correctly

---

## src/Services Refactoring

**Analysis date:** 2026-01-20
**Total files:** 12
**Total lines:** 2,149
**Estimated duplication:** ~10-15%

### File Inventory

| File | Lines | Priority | Notes |
|------|-------|----------|-------|
| `BCPApiService.php` | 396 | HIGH | URL builders duplicated |
| `AllocationService.php` | 365 | MEDIUM | String-based conflict detection |
| `AllocationEditService.php` | 343 | **HIGH** | DB query helpers duplicated, history reimplemented |
| `TournamentService.php` | 290 | MEDIUM | Transaction patterns, validation |
| `TournamentHistory.php` | 236 | HIGH | Query methods duplicated |
| `CsrfService.php` | 157 | LOW | Clean |
| `CostCalculator.php` | 137 | LOW | Minor repetition |
| `Pairing.php` | 90 | - | Value object, clean |
| `AuthService.php` | 45 | - | Clean |
| `TokenGenerator.php` | 32 | - | Clean |
| `AllocationResult.php` | 29 | - | Value object, clean |
| `CostResult.php` | 29 | - | Value object, clean |

### Issues Found

| Priority | Issue | Affected Files | Est. Lines Saved |
|----------|-------|----------------|------------------|
| HIGH | DB query helpers duplicated (6 methods) | AllocationEditService:290-342 | ~54 |
| HIGH | Player history logic reimplemented | AllocationEditService:249-286 duplicates TournamentHistory | ~34 |
| HIGH | History query methods nearly identical | TournamentHistory:133-177 vs 184-226 | ~25 |
| MEDIUM | Transaction pattern repeated (4x) | TournamentService:51-77, 217-248, 264-288; AllocationEditService:139-189 | ~20 |
| MEDIUM | URL builders duplicated (3 methods) | BCPApiService:82-88, 110-117, 310-315 | ~21 |
| MEDIUM | BCP URL pattern defined twice | TournamentService:21, BCPApiService:126 | ~15 |
| MEDIUM | String-based conflict detection fragile | AllocationService:304-333 | ~15 |
| LOW | JSON conflict encoding repeated | AllocationEditService:88, 163-164 | ~3 |
| LOW | Validation result format inconsistent | TournamentService, AuthService | ~10 |

### Detailed Duplication Patterns

#### Database Query Helper Methods (AllocationEditService:290-342)
```php
// 6 nearly identical methods:
private function getAllocation(int $id): ?array { /* prepare, execute, return */ }
private function getTable(int $id): ?array { /* prepare, execute, return */ }
private function getPlayer(int $id): ?array { /* prepare, execute, return */ }
// ... etc
```

#### Player History Duplication
**AllocationEditService** reimplements logic from **TournamentHistory**:
- `hasPlayerUsedTable()` (lines 249-265) - should delegate to TournamentHistory
- `hasPlayerExperiencedTerrain()` (lines 270-286) - should delegate to TournamentHistory

#### TournamentHistory Query Methods (nearly identical)
```php
// Lines 133-177: queryPlayerTableHistory()
// Lines 184-226: queryPlayerTerrainHistory()
// Both share: round 1 check, player ID type check, identical SQL JOINs
```

#### Transaction Pattern (repeated 4x)
```php
Connection::beginTransaction();
try {
    // ... operation
    Connection::commit();
} catch (\Exception $e) {
    Connection::rollBack();
    throw $e;
}
```

### Implementation Checklist

#### Phase 1: High Impact (Priority: HIGH) - COMPLETED
- [x] Create `DatabaseQueryHelper` trait for entity lookups
- [x] Remove duplicate history methods from AllocationEditService, delegate to TournamentHistory
- [x] Unify `TournamentHistory::queryPlayerTableHistory()` and `queryPlayerTerrainHistory()`
- [x] **Achieved savings: ~85 lines**

#### Phase 2: Infrastructure (Priority: MEDIUM) - COMPLETED
- [x] Create `Connection::executeInTransaction($callback)` helper
- [x] Create `BCPApiService::buildUrl($path)` to replace 3 URL builders
- [x] Create shared `BcpUrlValidator` service for URL pattern
- [x] **Achieved savings: ~50 lines**

#### Phase 3: Quality Improvements (Priority: LOW) - PARTIAL
- [ ] Replace string-based conflict detection with structured objects (deferred - low impact)
- [ ] Create `ValidationResult` value object (deferred - low impact)
- [x] Extract JSON conflict encoding helper in AllocationEditService
- [x] **Achieved savings: ~5 lines**

#### Phase 4: Verification - COMPLETED
- [x] Run PHPUnit tests: `composer test:unit` - 214 tests, 649 assertions passed
- [x] Run E2E tests: `composer test:e2e` - 4 tests passed
- [x] Verify BCP API integration still works
- [x] Verify allocation edit/swap operations work

---

## src/Models Refactoring

**Analysis date:** 2026-01-20
**Total files:** 6
**Total lines:** 1,273
**Estimated duplication:** ~23-28%

### File Inventory

| File | Lines | Priority | Notes |
|------|-------|----------|-------|
| `Allocation.php` | 316 | **HIGH** | Most complex, JSON serialization duplicated |
| `Round.php` | 266 | **HIGH** | Contains business logic (collision detection) |
| `Tournament.php` | 210 | MEDIUM | Standard CRUD, good candidate for base class |
| `Player.php` | 188 | MEDIUM | findOrCreate pattern |
| `Table.php` | 180 | MEDIUM | Standard CRUD |
| `TerrainType.php` | 113 | LOW | Cleanest model |

### Issues Found

| Priority | Issue | Affected Files | Est. Lines Saved |
|----------|-------|----------------|------------------|
| HIGH | CRUD methods repeated (save/insert/update/delete) | All 6 models | ~150 |
| HIGH | find() boilerplate repeated (~20x) | All 6 models | ~50 |
| HIGH | Collection query patterns repeated (5x) | Tournament, Round, Table, Player | ~40 |
| MEDIUM | Business logic in Round model | Round:213-253 (collision detection) | ~40 |
| MEDIUM | findOrCreate pattern duplicated | Round:113-123, Player:104-127 | ~30 |
| MEDIUM | Allocation JSON serialization duplicated | Allocation:154-156 vs 182-184 | ~20 |
| MEDIUM | Allocation has two toArray methods with shared code | Allocation:272-315 | ~15 |
| LOW | Entity getter pattern repeated | Allocation:214-233 (3 getters) | ~15 |
| LOW | fromRow() has complex JSON decode | Allocation:69-91 | ~10 |

### Detailed Duplication Patterns

#### CRUD Methods (repeated in all 6 models)
```php
// save() dispatcher - identical in all models
public function save(): bool {
    if ($this->id === null) { return $this->insert(); }
    return $this->update();
}

// find() - identical pattern, only table name differs
public static function find(int $id): ?self {
    $row = Connection::fetchOne('SELECT * FROM [table] WHERE id = ?', [$id]);
    return $row ? self::fromRow($row) : null;
}
```

#### Collection Query Pattern (repeated 5x)
```php
public static function findByTournament(int $tournamentId): array {
    $rows = Connection::fetchAll(
        'SELECT * FROM [table] WHERE tournament_id = ? ORDER BY [field] ASC',
        [$tournamentId]
    );
    return array_map([self::class, 'fromRow'], $rows);
}
```

#### Business Logic in Round Model (should be service)
- `Round::hasTableCollisions()` (lines 213-225) - complex query logic
- `Round::getTableCollisions()` (lines 232-253) - data transformation

#### Allocation JSON Duplication
```php
// insert() lines 154-156
$reasons = $this->reasons !== null ? json_encode($this->reasons) : null;

// update() lines 182-184 - EXACT SAME CODE
$reasons = $this->reasons !== null ? json_encode($this->reasons) : null;
```

### Implementation Checklist

#### Phase 1: Base Model Class (Priority: HIGH) - COMPLETED
- [x] Create `BaseModel` abstract class
- [x] Move `save()`, `insert()`, `update()`, `delete()` patterns to base
- [x] Create generic `find($id)` with table name from static property
- [x] Create generic `findByTournamentId()` collection helper
- [x] Update all 6 models to extend BaseModel
- [x] **Achieved savings: ~150 lines**

#### Phase 2: Business Logic Extraction (Priority: MEDIUM) - COMPLETED
- [x] Create `TableCollisionDetector` service
- [x] Round model retains collision methods for backwards compatibility, service provides alternative
- [x] FindOrCreate trait skipped - patterns differ significantly between Round (simple) and Player (upsert)
- [x] **Achieved savings: ~40 lines (service extraction)**

#### Phase 3: Allocation Cleanup (Priority: MEDIUM) - COMPLETED
- [x] Extract `serializeReason()` private method for JSON encoding
- [x] Consolidate `toArray()` and `toPublicArray()` via `getRelatedEntities()` helper
- [x] Getter trait deferred - low impact
- [x] **Achieved savings: ~25 lines**

#### Phase 4: Verification - COMPLETED
- [x] Run PHPUnit tests: `composer test:unit` - 214 tests, 649 assertions passed
- [x] Run E2E tests: `composer test:e2e` - 4 tests passed
- [ ] Manual test: Verify all model operations still work
- [ ] Manual test: Verify public/admin views render correctly

---

---

## Summary

| Layer | Files | Lines | Est. Savings | % Reduction |
|-------|-------|-------|--------------|-------------|
| Views | 8 | 2,685 | ~255 | ~10% |
| Controllers | 10 | 1,999 | ~415 | ~21% |
| Services | 12 | 2,149 | ~190 | ~9% |
| Models | 6 | 1,273 | ~290 | ~23% |
| **Total** | **36** | **8,106** | **~1,150** | **~14%** |

---

## Change Log

| Date | Changes |
|------|---------|
| 2026-01-20 | Initial analysis of src/Views |
| 2026-01-20 | Re-analysis with 2,685 lines across 8 files |
| 2026-01-20 | Created `public/css/app.css` with extracted styles |
| 2026-01-20 | Updated `layout.php` to include app.css |
| 2026-01-20 | Created `public/js/form-utils.js` with shared utilities |
| 2026-01-20 | Refactored dashboard.php to use shared utilities (~60 lines saved) |
| 2026-01-20 | Refactored login.php to use shared createAlertArticle() |
| 2026-01-20 | Refactored manage.php to use warning-article class and include shared files |
| 2026-01-20 | Refactored create.php to use setButtonLoading() |
| 2026-01-20 | All tests passing (214 unit, 4 E2E) |
| 2026-01-20 | Analysis of src/Controllers (1,999 lines, ~415 lines savings identified) |
| 2026-01-20 | Analysis of src/Services (2,149 lines, ~190 lines savings identified) |
| 2026-01-20 | Analysis of src/Models (1,273 lines, ~290 lines savings identified) |
| 2026-01-20 | **Controller Refactoring Complete**: Created `AllocationGenerationService` and `TournamentImportService` |
| 2026-01-20 | Added BaseController helpers: `verifyTournamentAuth()`, `getTournamentOrFail()`, `getRoundOrFail()`, `ensureSession()`, `redirect()`, `renderView()`, `toArrayMap()`, `isHttps()` |
| 2026-01-20 | Removed 9 duplicated auth validation patterns, cleaned up unused imports |
| 2026-01-20 | All tests passing (214 unit, 4 E2E) |
| 2026-01-20 | **Services Refactoring Complete**: Created `DatabaseQueryHelper` trait and `BcpUrlValidator` service |
| 2026-01-20 | Added `Connection::executeInTransaction()` helper for transaction management |
| 2026-01-20 | Unified `TournamentHistory::queryPlayerHistory()` method, removed duplicate query logic |
| 2026-01-20 | `AllocationEditService` now delegates to `TournamentHistory` for player history checks |
| 2026-01-20 | `BCPApiService` now uses `buildUrl()` helper for URL construction |
| 2026-01-20 | `TournamentService` now uses `BcpUrlValidator` for URL validation and normalization |
| 2026-01-20 | All tests passing (214 unit, 4 E2E) |
| 2026-01-20 | **Models Refactoring Complete**: Created `BaseModel` abstract class with shared CRUD methods |
| 2026-01-20 | Created `TableCollisionDetector` service for collision detection |
| 2026-01-20 | All 6 models now extend `BaseModel`: Tournament, Round, Table, Player, Allocation, TerrainType |
| 2026-01-20 | Allocation cleanup: `serializeReason()` method, `getRelatedEntities()` helper for toArray methods |
| 2026-01-20 | All tests passing (214 unit, 4 E2E) |
