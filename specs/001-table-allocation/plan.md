# Implementation Plan: BCP Tables - Tournament Table Allocation

**Branch**: `001-table-allocation` | **Date**: 2026-01-13 | **Spec**: [spec.md](./spec.md)
**Input**: Feature specification from `/specs/001-table-allocation/spec.md`

## Summary

Build a web application that generates table allocations for BCP tournaments, ensuring players experience different tables each round. The system integrates with Best Coast Pairings (BCP) for pairing data via REST API, implements a priority-based allocation algorithm, and provides both organizer management and public viewing interfaces.

## Technical Context

**Language/Version**: PHP 7.1 (strict compatibility required per constitution)
**Primary Dependencies**:
- Frontend: HTMX 1.9.x + Pico CSS 1.5.x (no build step, server-rendered)
- Testing: PHPUnit 9.5
**Storage**: MySQL 5.7+
**Testing**: PHPUnit 9.5 (per constitution)
**Target Platform**: Web-based, accessible from tournament venue devices
**Project Type**: Web application (frontend + backend)
**Performance Goals**:
- Table allocation generation < 10 seconds for 40 players (SC-002)
- Page load < 3 seconds (SC-004)
- First allocation < 5 minutes from tournament creation (SC-001)
**Constraints**:
- PHP 7.1 compatibility (no PHP 7.4+ features)
- Tournament data retained indefinitely
- BCP API access requires internet connectivity
**Scale/Scope**:
- 4-6 rounds per tournament
- 8-20 tables typical
- Up to 40 players per tournament
**Algorithm**: Priority-weighted greedy assignment (see [research.md](./research.md))

## Constitution Check

*GATE: Must pass before Phase 0 research. Re-check after Phase 1 design.*

### Initial Check (Pre-Research)

| Principle | Status | Notes |
|-----------|--------|-------|
| I. Single Purpose Focus | ✅ PASS | Feature is strictly table allocation; no tournament management/scoring duplication |
| II. Test-Driven Development | ⏳ PENDING | TDD cycle must be followed during implementation |
| III. Data Integrity First | ⏳ PENDING | Design must include transactions, validation, atomic operations |
| IV. Simplicity Over Flexibility | ⏳ PENDING | Must verify minimal abstraction in design phase |
| V. Transparent Algorithm Behavior | ⏳ PENDING | Allocation logging required (FR-014) |

**Initial Gate Status**: ✅ PASS - No blockers for Phase 0 research

### Post-Design Check (Phase 1 Complete)

| Principle | Status | Evidence |
|-----------|--------|----------|
| I. Single Purpose Focus | ✅ PASS | Design strictly handles table allocation; no scoring, pairing, or tournament management logic |
| II. Test-Driven Development | ⏳ IMPLEMENTATION | PHPUnit configured; test structure defined in quickstart.md |
| III. Data Integrity First | ✅ PASS | Transaction boundaries documented in data-model.md; foreign keys with CASCADE; validation at boundaries |
| IV. Simplicity Over Flexibility | ✅ PASS | No ORM; minimal MVC; greedy algorithm (not over-engineered Hungarian); HTMX over SPA frameworks |
| V. Transparent Algorithm Behavior | ✅ PASS | allocation_reason JSON field captures decision rationale; cost breakdown stored per allocation |

**Post-Design Gate Status**: ✅ PASS - Ready for Phase 2 task generation

## Project Structure

### Documentation (this feature)

```text
specs/001-table-allocation/
├── plan.md              # This file
├── research.md          # Phase 0 output
├── data-model.md        # Phase 1 output
├── quickstart.md        # Phase 1 output
├── contracts/           # Phase 1 output
└── tasks.md             # Phase 2 output (/speckit.tasks command)
```

### Source Code (repository root)

```text
public/
├── index.php            # Front controller
├── css/                 # Stylesheets
└── js/                  # Client-side JavaScript

src/
├── Models/              # Entity classes (Tournament, Round, Allocation, etc.)
├── Services/            # Business logic (AllocationService, BCPService)
├── Controllers/         # HTTP request handlers
├── Database/            # Database connection and queries
└── Views/               # PHP templates

tests/
├── Unit/                # Unit tests for allocation algorithm
├── Integration/         # BCP import and database tests
└── fixtures/            # Test data (mock BCP responses)

config/
└── database.php         # Database configuration

vendor/                  # Composer dependencies
```

**Structure Decision**: Web application pattern selected. Minimal MVC structure without heavy framework overhead, aligned with constitution principle IV (Simplicity Over Flexibility). Single deployable unit with clear separation between public assets, source code, and tests.

## Implementation Roadmap

This section defines the implementation sequence with cross-references to detail documents.

### Phase 1: Project Foundation

| Step | Task | Reference Documents |
|------|------|---------------------|
| 1.1 | Create `composer.json` with dependencies | [research.md#updated-dependencies](./research.md#updated-dependencies) |
| 1.2 | Create directory structure | [quickstart.md#directory-structure](./quickstart.md#directory-structure) |
| 1.3 | Create database config files | [quickstart.md#2-configure-database](./quickstart.md#2-configure-database) |
| 1.4 | Create Chrome config file | [quickstart.md#5-configure-chrome-path](./quickstart.md#5-configure-chrome-path-if-needed) |
| 1.5 | Set up PHPUnit configuration | Constitution Principle II (TDD) |

**Output**: Empty project skeleton with dependencies installed, configs in place

---

### Phase 2: Database Layer

| Step | Task | Reference Documents |
|------|------|---------------------|
| 2.1 | Create migration script (`bin/migrate.php`) | [data-model.md#database-schema](./data-model.md#database-schema-mysql) |
| 2.2 | Create terrain seed script (`bin/seed-terrain-types.php`) | [data-model.md#initial-data](./data-model.md#initial-data) |
| 2.3 | Create `Database/Connection.php` | Use PDO with prepared statements per Constitution |
| 2.4 | Write integration test for database connection | Constitution Principle II (TDD) |

**Output**: Working database with schema and seed data

---

### Phase 3: Entity Models

| Step | Task | Reference Documents |
|------|------|---------------------|
| 3.1 | Create `Models/TerrainType.php` | [data-model.md#terraintype](./data-model.md#terraintype) |
| 3.2 | Create `Models/Tournament.php` | [data-model.md#tournament](./data-model.md#tournament) |
| 3.3 | Create `Models/Table.php` | [data-model.md#table](./data-model.md#table) |
| 3.4 | Create `Models/Round.php` | [data-model.md#round](./data-model.md#round) |
| 3.5 | Create `Models/Player.php` | [data-model.md#player](./data-model.md#player) |
| 3.6 | Create `Models/Allocation.php` | [data-model.md#allocation](./data-model.md#allocation) |

**Output**: Entity classes matching data model, no ORM (per Constitution Principle IV)

---

### Phase 4: Core Services - Tournament Management

| Step | Task | Reference Documents |
|------|------|---------------------|
| 4.1 | Create `Services/TokenGenerator.php` | Generate 16-char base64 tokens (FR-002) |
| 4.2 | Create `Services/TournamentService.php` | [contracts/api.yaml#CreateTournamentRequest](./contracts/api.yaml) |
| 4.3 | Write unit tests for tournament creation | Validate BCP URL format, table count range |
| 4.4 | Create `Services/AuthService.php` | Token validation, cookie management (FR-003, FR-004) |

**Validation Rules**: [data-model.md#validation-at-system-boundaries](./data-model.md#validation-at-system-boundaries)
**Transaction Scope**: [data-model.md#transaction-boundaries](./data-model.md#transaction-boundaries)

**Output**: Tournament CRUD with authentication

---

### Phase 5: BCP Integration Service

| Step | Task | Reference Documents |
|------|------|---------------------|
| 5.1 | Update `Services/BCPScraperService.php` to use REST API | [research.md#2-bcp-rest-api](./research.md#2-bcp-rest-api) |
| 5.2 | Implement `fetchPairings(eventId, round)` using API endpoint | API: `newprod-api.bestcoastpairings.com/v1/events/{id}/pairings` |
| 5.3 | Create JSON parser for BCP API response | [research.md#expected-data-fields](./research.md#expected-data-fields) |
| 5.4 | Write integration tests with mock JSON | Store fixtures in `tests/fixtures/` |
| 5.5 | Implement retry with exponential backoff | [research.md#best-practices](./research.md#best-practices-for-bcp-scraping) |

**Output**: Working BCP import that extracts player names, IDs, scores, table assignments

---

### Phase 6: Allocation Algorithm

| Step | Task | Reference Documents |
|------|------|---------------------|
| 6.1 | Create `Services/TournamentHistory.php` | [data-model.md#query-patterns](./data-model.md#query-patterns) |
| 6.2 | Implement `hasPlayerUsedTable()` | [data-model.md#get-player-table-history](./data-model.md#get-player-table-history) |
| 6.3 | Implement `hasPlayerExperiencedTerrain()` | [data-model.md#get-player-terrain-history](./data-model.md#get-player-terrain-history) |
| 6.4 | Create `Services/CostCalculator.php` | [research.md#cost-function](./research.md#cost-function) |
| 6.5 | Create `Services/AllocationService.php` | [research.md#algorithm-overview](./research.md#algorithm-overview) |
| 6.6 | Implement stable sort wrapper | [research.md#determinism-requirements](./research.md#determinism-requirements) |
| 6.7 | Implement `AllocationReason` audit trail | [research.md#audit-trail-structure](./research.md#audit-trail-structure-fr-014) |
| 6.8 | Write unit tests for allocation algorithm | Test all priority rules (FR-007.1 through FR-007.4) |

**Cost Constants**:
- `COST_TABLE_REUSE = 100000` (P1: highest priority)
- `COST_TERRAIN_REUSE = 10000` (P2: medium priority)
- `COST_TABLE_NUMBER = 1` (P3: tiebreaker)

**Output**: Allocation algorithm with full audit trail per FR-014

---

### Phase 7: API Controllers

| Step | Task | Reference Documents |
|------|------|---------------------|
| 7.1 | Create `public/index.php` front controller | Route requests to controllers |
| 7.2 | Create `Controllers/TournamentController.php` | [contracts/api.yaml#/tournaments](./contracts/api.yaml) |
| 7.3 | Create `Controllers/RoundController.php` | [contracts/api.yaml#/rounds](./contracts/api.yaml) |
| 7.4 | Create `Controllers/AllocationController.php` | [contracts/api.yaml#/allocations](./contracts/api.yaml) |
| 7.5 | Create `Controllers/PublicController.php` | [contracts/api.yaml#/public](./contracts/api.yaml) |
| 7.6 | Create `Controllers/AuthController.php` | [contracts/api.yaml#/auth](./contracts/api.yaml) |
| 7.7 | Implement admin token middleware | Check `X-Admin-Token` header |

**API Contract**: [contracts/api.yaml](./contracts/api.yaml) defines all request/response schemas

**Output**: Working REST API matching OpenAPI spec

---

### Phase 8: Views (Admin UI)

| Step | Task | Reference Documents |
|------|------|---------------------|
| 8.1 | Create base layout with Pico CSS + HTMX | [research.md#implementation-notes](./research.md#implementation-notes) |
| 8.2 | Create tournament creation form | User Story 2 |
| 8.3 | Create round management view | Show pairings, allocations, conflicts |
| 8.4 | Implement table swap UI | [research.md#htmx-patterns](./research.md#implementation-notes) (swap example) |
| 8.5 | Implement publish button with confirmation | [research.md#htmx-patterns](./research.md#implementation-notes) (publish example) |
| 8.6 | Create conflict highlighting | FR-010: visual indicators for violations |

**Output**: Admin interface for tournament management

---

### Phase 9: Views (Public UI)

| Step | Task | Reference Documents |
|------|------|---------------------|
| 9.1 | Create public tournament view | User Story 4 |
| 9.2 | Create round selector | Show only published rounds |
| 9.3 | Create allocation table display | [data-model.md#get-published-allocations](./data-model.md#get-published-allocations-for-public-view) |

**Output**: Public-facing allocation display

---

### Phase 10: Integration Testing & Refinement

| Step | Task | Reference Documents |
|------|------|---------------------|
| 10.1 | End-to-end test: tournament creation flow | SC-001: < 5 minutes to first allocation |
| 10.2 | Performance test: allocation generation | SC-002: < 10 seconds for 40 players |
| 10.3 | Performance test: page load | SC-004: < 3 seconds |
| 10.4 | Test conflict detection | SC-006: 100% conflict detection |
| 10.5 | Test allocation priority rules | SC-007: correct rule ordering |

**Success Criteria**: [spec.md#success-criteria](./spec.md#success-criteria-mandatory)

**Output**: Verified working system meeting all success criteria

---

## Implementation Notes

### TDD Workflow (Constitution Principle II)

For each service/feature:
1. Write failing test defining expected behavior
2. Get user approval of test cases
3. Verify test fails (red)
4. Implement minimum code to pass (green)
5. Refactor while keeping tests passing

### Key Files to Reference During Implementation

| Topic | Document | Section |
|-------|----------|---------|
| Entity fields & types | data-model.md | Entities |
| SQL schema | data-model.md | Database Schema |
| Validation rules | data-model.md | Validation Rules per entity |
| Transaction boundaries | data-model.md | Transaction Boundaries |
| API endpoints | contracts/api.yaml | paths |
| Request/response schemas | contracts/api.yaml | components/schemas |
| Algorithm pseudocode | research.md | Algorithm Overview |
| Cost function code | research.md | Cost Function |
| BCP scraper code | research.md | Implementation Approach |
| HTMX patterns | research.md | Implementation Notes |
| Setup commands | quickstart.md | Setup |

---

## Complexity Tracking

> No constitution violations requiring justification at this stage.

| Violation | Why Needed | Simpler Alternative Rejected Because |
|-----------|------------|-------------------------------------|
| — | — | — |
