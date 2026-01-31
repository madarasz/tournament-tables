# Research: Tournament Tables - Tournament Table Allocation

**Generated**: 2026-01-13 | **Branch**: `001-table-allocation`

## Research Questions

This document resolves all NEEDS CLARIFICATION items from the Technical Context.

---

## 1. Frontend Framework Selection

### Decision: HTMX + Pico CSS

### Rationale

For a PHP 7.1 web application with minimal interactivity requirements, server-rendered HTML with progressive enhancement is the optimal approach:

- **HTMX** (v1.9.x): Enables dynamic updates without writing JavaScript. Perfect for table swaps, refresh buttons, and form submissions. Works naturally with PHP server-rendered responses.
- **Pico CSS** (v1.5.x): Classless CSS framework providing clean styling with minimal markup. No build step required.

### Alternatives Considered

| Option | Rejected Because |
|--------|------------------|
| React/Vue SPA | Over-engineering for this use case; adds build complexity; violates constitution principle IV (Simplicity) |
| Alpine.js | Requires writing JavaScript; HTMX handles our use cases declaratively |
| Bootstrap | Heavy; requires many CSS classes; dated appearance |
| Vanilla JS only | More code to write; HTMX provides better DX for our patterns |
| Tailwind CSS | Requires build step; verbose markup; overkill for simple UI |

### Implementation Notes

```html
<!-- No build step required -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@picocss/pico@1/css/pico.min.css">
<script src="https://unpkg.com/htmx.org@1.9.10"></script>
```

HTMX patterns for our features:
- **Table refresh**: `<button hx-post="/round/3/refresh" hx-target="#pairings">Refresh from BCP</button>`
- **Table swap**: `<button hx-post="/allocation/swap" hx-vals='{"from":3,"to":7}'>Swap</button>`
- **Publish**: `<button hx-post="/round/3/publish" hx-confirm="Publish allocations?">Publish</button>`

---

## 2. Headless Browser for BCP Scraping

### Decision: Chrome PHP (`chrome-php/chrome`)

### Rationale

BCP is a JavaScript-rendered SPA (confirmed by testing the provided URL `https://www.bestcoastpairings.com/event/t6OOun8POR60?active_tab=pairings&round=1` - page shows "You need to enable JavaScript to run this app"). Chrome PHP provides:

- PHP 7.1 compatibility
- Direct Chrome DevTools Protocol integration
- Page rendering and DOM access after JavaScript execution
- Network request interception (may reveal API endpoints)

### Alternatives Considered

| Option | Rejected Because |
|--------|------------------|
| Symfony Panther | Requires Symfony components; heavier dependency |
| Selenium + WebDriver | Requires separate Selenium server; operational complexity |
| Spatie Browsershot | Optimized for screenshots/PDFs, not DOM scraping |
| Node.js microservice | Adds second runtime; deployment complexity |

### Implementation Approach

```php
use HeadlessChromium\BrowserFactory;

class BCPScraperService {
    public function fetchPairings(string $eventId, int $round): array {
        $browserFactory = new BrowserFactory();
        $browser = $browserFactory->createBrowser([
            'headless' => true,
            'noSandbox' => true,
        ]);

        try {
            $page = $browser->createPage();
            $url = "https://www.bestcoastpairings.com/event/{$eventId}?active_tab=pairings&round={$round}";
            $page->navigate($url)->waitForNavigation();

            // Wait for JS rendering
            $page->evaluate('document.readyState')->waitUntilResponse();
            sleep(2); // Allow React hydration

            // Extract pairing data from rendered DOM
            $html = $page->evaluate('document.body.innerHTML')->getReturnValue();

            return $this->parsePairingsHtml($html);
        } finally {
            $browser->close();
        }
    }
}
```

### Dependencies

```json
{
    "require": {
        "chrome-php/chrome": "^1.8"
    }
}
```

**Server Requirement**: Chrome/Chromium must be installed on the server.

### Best Practices for BCP Scraping

- Add 2-3 second delays between page loads
- Use realistic User-Agent header
- Implement retry with exponential backoff
- Cache responses where appropriate (pairings don't change frequently)
- Consider storing raw HTML for debugging

---

## 3. BCP Website Data Structure

### Confirmed URL Format

- Event page: `https://www.bestcoastpairings.com/event/{eventId}`
- Pairings tab: `https://www.bestcoastpairings.com/event/{eventId}?active_tab=pairings&round={roundNumber}`
- Real example: `https://www.bestcoastpairings.com/event/t6OOun8POR60?active_tab=pairings&round=1`

### Site Characteristics

1. **Rendering Method**: Client-side JavaScript rendering (SPA)
2. **Framework**: Likely React-based
3. **Static HTML Content**: Displays only "You need to enable JavaScript to run this app"
4. **Event ID Format**: Alphanumeric strings (e.g., `t6OOun8POR60`)

### Expected Data Fields

Based on typical tournament management systems, expect to extract:

| Field | Description | Example |
|-------|-------------|---------|
| Table Number | Physical table assignment | 1, 2, 3... |
| Player 1 Name | First player in pairing | "John Smith" |
| Player 1 ID | BCP unique identifier | "abc123xyz" |
| Player 1 Score | Current tournament points | 2 (wins) |
| Player 2 Name | Second player in pairing | "Jane Doe" |
| Player 2 ID | BCP unique identifier | "def456uvw" |
| Player 2 Score | Current tournament points | 1 (wins) |

### Scraping Strategy

1. **Primary**: Parse rendered DOM after JavaScript execution using Chrome PHP
2. **Alternative**: Monitor Network tab for API calls (may provide cleaner JSON data)
3. **Fallback**: Manual CSV import if automated extraction proves unreliable

### Implementation Recommendation

Create a proof-of-concept that:
1. Navigates to the provided BCP event URL with Chrome PHP
2. Waits for JavaScript rendering
3. Extracts and logs the full HTML
4. Identifies actual CSS selectors for pairing data
5. Documents the real DOM structure

---

## 4. Table Allocation Algorithm

### Decision: Priority-Weighted Greedy Assignment

### Algorithm Overview

```
1. If Round 1: return BCP assignments directly (FR-007.1)
2. Load tournament history (player → tables used, player → terrains experienced)
3. Sort pairings by combined score (descending), stable tie-breaking by BCP ID
4. For each pairing (in score order):
   a. Calculate cost for each available table:
      - +100000 if either player used this table before (FR-007.2)
      - +10000 if either player experienced this terrain type (FR-007.3)
      - +tableNumber for natural ordering (FR-007.4)
   b. Select lowest-cost table (tie-break by table number)
   c. Record allocation with full audit trail (FR-014)
   d. Mark table as used for this round
5. Return allocations with any conflicts flagged
```

### Rationale

For the problem scale (max 20 pairings, 20 tables, 6 rounds):

| Criterion | Greedy | Hungarian | Backtracking |
|-----------|--------|-----------|--------------|
| Implementation complexity | Low | Medium | High |
| PHP 7.1 compatibility | Native | Needs port | Native |
| Optimality | Near-optimal | Optimal | Optimal |
| Auditability | Excellent | Requires wrapper | Good |
| Performance | O(P×T) = 400 ops | O(n³) = 8000 ops | O(T^P) worst |

The greedy approach wins on simplicity while providing near-optimal results for this problem size.

### Alternatives Considered

| Option | Rejected Because |
|--------|------------------|
| Hungarian Algorithm | Requires porting to PHP; marginal benefit at our scale |
| Backtracking/CSP | Overkill; our constraints are soft, not hard |
| Stable Matching | Not applicable; tables don't have preferences |
| Random with retry | Non-deterministic; violates constitution principle V |

### Cost Function

```php
class CostCalculator {
    const COST_TABLE_REUSE = 100000;    // P1: Very bad
    const COST_TERRAIN_REUSE = 10000;   // P2: Bad but acceptable
    const COST_TABLE_NUMBER = 1;         // P3: Minor preference

    public function calculate(Pairing $pairing, Table $table, TournamentHistory $history): CostResult {
        $cost = 0;
        $reasons = [];

        // P1: Table reuse check
        foreach ([$pairing->player1, $pairing->player2] as $player) {
            if ($history->hasPlayerUsedTable($player, $table)) {
                $cost += self::COST_TABLE_REUSE;
                $reasons[] = "{$player->name} previously played on table {$table->number}";
            }
        }

        // P2: Terrain reuse check
        if ($table->terrainType !== null) {
            foreach ([$pairing->player1, $pairing->player2] as $player) {
                if ($history->hasPlayerExperiencedTerrain($player, $table->terrainType)) {
                    $cost += self::COST_TERRAIN_REUSE;
                    $reasons[] = "{$player->name} previously experienced {$table->terrainType}";
                }
            }
        }

        // P3: Table number preference
        $cost += $table->number * self::COST_TABLE_NUMBER;

        return new CostResult($cost, $reasons);
    }
}
```

### Handling Impossible Perfect Allocation

By round 5-6, some players will inevitably repeat tables. The algorithm handles this gracefully:

1. Violations add to cost but don't block assignment
2. Conflicts are flagged in the result for UI highlighting (FR-010)
3. Summary reports "best effort" status

### Determinism Requirements

To ensure reproducible results (Constitution Principle V):

1. **Stable sorting**: PHP's `usort` is not stable; implement stable sort wrapper
2. **Consistent tie-breaking**: When costs equal, use table number; when scores equal, use BCP player ID
3. **No random elements**: No `rand()`, `shuffle()`, or timestamp-based decisions

### Audit Trail Structure (FR-014)

```php
class AllocationReason {
    public string $pairingId;
    public int $tableNumber;
    public int $roundNumber;
    public DateTime $timestamp;
    public int $totalCost;
    public array $costBreakdown;      // ['tableReuse' => 0, 'terrainReuse' => 0, 'tableNumber' => 3]
    public array $reasons;            // Human-readable explanations
    public array $alternativesConsidered; // [tableNum => cost]
}
```

Storage: JSON column in allocations table or separate audit table.

---

## Technical Context Resolution

| Item | Resolution |
|------|------------|
| Frontend framework | HTMX + Pico CSS (no build step) |
| Headless browser | chrome-php/chrome |
| Algorithm | Priority-weighted greedy assignment |

## Updated Dependencies

```json
{
    "require": {
        "php": "^7.1",
        "chrome-php/chrome": "^1.8",
        "ext-pdo": "*",
        "ext-json": "*"
    },
    "require-dev": {
        "phpunit/phpunit": "^7.5"
    }
}
```

## Server Requirements

- PHP 7.1
- MySQL 5.7+
- Chrome/Chromium browser installed (for headless scraping)
- Composer for dependency management
