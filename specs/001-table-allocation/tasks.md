# Tasks: Tournament Tables - Tournament Table Allocation

**Input**: Design documents from `/specs/001-table-allocation/`
**Prerequisites**: plan.md, spec.md, research.md, data-model.md, contracts/api.yaml

**Tests**: TDD approach required per Constitution Principle II. Tests are included for all user stories.

**Organization**: Tasks are grouped by user story to enable independent implementation and testing of each story.

## Format: `[ID] [P?] [Story] Description`

- **[P]**: Can run in parallel (different files, no dependencies)
- **[Story]**: Which user story this task belongs to (e.g., US1, US2, US3)
- Include exact file paths in descriptions

## Path Conventions

Based on plan.md project structure:
- Source code: `src/` at repository root
- Tests: `tests/` at repository root
- Public assets: `public/` at repository root
- Config: `config/` at repository root
- CLI scripts: `bin/` at repository root

---

## Phase 1: Setup (Shared Infrastructure)

**Purpose**: Project initialization and basic structure

**Reference**: [plan.md#phase-1-project-foundation](./plan.md#phase-1-project-foundation)

- [X] T001 Create `composer.json` with dependencies per [research.md#updated-dependencies](./research.md#updated-dependencies)
- [X] T002 Create directory structure: `src/Models/`, `src/Services/`, `src/Controllers/`, `src/Database/`, `src/Views/`, `tests/Unit/`, `tests/Integration/`, `tests/fixtures/`, `public/css/`, `public/js/`, `config/`, `bin/`
- [X] T003 [P] Create `config/database.example.php` with placeholder credentials per [quickstart.md](./quickstart.md#2-configure-database)
- [X] T004 [P] Create `config/chrome.example.php` with Chrome path per [quickstart.md](./quickstart.md#5-configure-chrome-path-if-needed)
- [X] T005 [P] Create `phpunit.xml` with test suites for Unit and Integration tests
- [X] T006 Run `composer install` to verify dependencies

**Checkpoint**: Empty project skeleton with dependencies installed

---

## Phase 2: Foundational (Blocking Prerequisites)

**Purpose**: Core infrastructure that MUST be complete before ANY user story can be implemented

**Reference**: [plan.md#phase-2-database-layer](./plan.md#phase-2-database-layer), [plan.md#phase-3-entity-models](./plan.md#phase-3-entity-models)

### Database Infrastructure

- [X] T007 Create `bin/migrate.php` script using SQL from [data-model.md#database-schema](./data-model.md#database-schema-mysql)
- [X] T008 Create `bin/seed-terrain-types.php` using INSERT from [data-model.md#initial-data](./data-model.md#initial-data)
- [X] T009 Create `src/Database/Connection.php` with PDO singleton and prepared statement helpers
- [X] T010 Write `tests/Integration/DatabaseConnectionTest.php` to verify database connectivity

### Entity Models (all stories depend on these)

- [X] T011 [P] Create `src/Models/TerrainType.php` per [data-model.md#terraintype](./data-model.md#terraintype)
- [X] T012 [P] Create `src/Models/Tournament.php` per [data-model.md#tournament](./data-model.md#tournament)
- [X] T013 [P] Create `src/Models/Table.php` per [data-model.md#table](./data-model.md#table)
- [X] T014 [P] Create `src/Models/Round.php` per [data-model.md#round](./data-model.md#round)
- [X] T015 [P] Create `src/Models/Player.php` per [data-model.md#player](./data-model.md#player)
- [X] T016 [P] Create `src/Models/Allocation.php` per [data-model.md#allocation](./data-model.md#allocation)

### Request Handling Infrastructure

- [X] T017 Create `public/index.php` front controller with basic routing
- [X] T018 Create `src/Controllers/BaseController.php` with JSON response helpers
- [X] T019 Create `src/Middleware/AdminAuthMiddleware.php` checking X-Admin-Token header per [contracts/api.yaml#securitySchemes](./contracts/api.yaml)
- [X] T020 Create base layout template `src/Views/layout.php` with Pico CSS + HTMX per [research.md#implementation-notes](./research.md#implementation-notes)

**Checkpoint**: Foundation ready - user story implementation can now begin

---

## Phase 3: User Story 2 - Create and Configure Tournament (Priority: P2)

**Note**: US2 implemented before US1 because tournament creation is a prerequisite for allocation generation.

**Goal**: Organizer can create a tournament with BCP URL and table configuration, receive admin token

**Independent Test**: Create tournament with name, BCP URL, table count → verify tournament persists and admin token is returned

**Reference**: [spec.md#user-story-2](./spec.md#user-story-2---create-and-configure-tournament-priority-p2)

### Tests for User Story 2

- [X] T021 [P] [US2] Write `tests/Unit/Services/TokenGeneratorTest.php` - verify 16-char base64 output
- [X] T022 [P] [US2] Write `tests/Unit/Services/TournamentServiceTest.php` - validate BCP URL format, table count range
- [X] T023 [P] [US2] Write `tests/Integration/TournamentCreationTest.php` - end-to-end tournament creation

### Implementation for User Story 2

- [X] T024 [US2] Create `src/Services/TokenGenerator.php` - generate 16-char base64 tokens (FR-002)
- [X] T025 [US2] Create `src/Services/TournamentService.php` with `createTournament()` method per [contracts/api.yaml#CreateTournamentRequest](./contracts/api.yaml)
- [X] T026 [US2] Implement BCP URL validation (pattern: `https://www.bestcoastpairings.com/event/{eventId}`) per [data-model.md#tournament](./data-model.md#tournament)
- [X] T027 [US2] Implement table creation in transaction with tournament per [data-model.md#transaction-boundaries](./data-model.md#transaction-boundaries)
- [X] T028 [US2] Create `src/Controllers/TournamentController.php` with POST /api/tournaments endpoint
- [X] T029 [US2] Implement admin token cookie setting (30-day retention) per FR-003
- [X] T030 [US2] Create `src/Controllers/TerrainTypeController.php` with GET /api/terrain-types endpoint
- [X] T031 [US2] Create `src/Views/tournament/create.php` form view with HTMX
- [X] T031b [US2] Create `src/Views/tournament/dashboard.php` admin view with rounds list and import controls
- [X] T032 [US2] Implement PUT /api/tournaments/{id}/tables for terrain type assignment per FR-005

**Checkpoint**: Tournament creation works independently - can create tournament and receive admin token

---

## Phase 4: User Story 5 - Admin Authentication (Priority: P5)

**Note**: US5 implemented early because other admin features depend on authentication.

**Goal**: Organizer can authenticate using admin token to access tournament management

**Independent Test**: Clear cookies, enter valid admin token → verify access granted and new cookie set

**Reference**: [spec.md#user-story-5](./spec.md#user-story-5---admin-authentication-priority-p5)

### Tests for User Story 5

- [X] T033 [P] [US5] Write `tests/Unit/Services/AuthServiceTest.php` - token validation, invalid token rejection
- [X] T034 [P] [US5] Write `tests/Integration/AuthenticationTest.php` - cookie flow, middleware blocking

### Implementation for User Story 5

- [X] T035 [US5] Create `src/Services/AuthService.php` with `validateToken()` and `setAuthCookie()` methods
- [X] T036 [US5] Create `src/Controllers/AuthController.php` with POST /api/auth endpoint per [contracts/api.yaml#/auth](./contracts/api.yaml)
- [X] T037 [US5] Create `src/Views/auth/login.php` token entry form
- [X] T038 [US5] Update `src/Middleware/AdminAuthMiddleware.php` to check both cookie and header

**Checkpoint**: Authentication works independently - can login with token and access protected routes

---

## Phase 5: User Story 1 - Generate Table Allocation for Round (Priority: P1) MVP

**Goal**: Generate table allocations following priority rules: round 1 uses BCP assignments, avoid used tables, prefer new terrain, score-based ordering

**Independent Test**: Create tournament, import round 2 pairings, generate allocations → verify no player assigned to previously-used table

**Reference**: [spec.md#user-story-1](./spec.md#user-story-1---generate-table-allocation-for-round-priority-p1), [research.md#4-table-allocation-algorithm](./research.md#4-table-allocation-algorithm)

### Tests for User Story 1

- [X] T039 [P] [US1] Write `tests/Unit/Services/CostCalculatorTest.php` - verify cost weights per [research.md#cost-function](./research.md#cost-function)
- [X] T040 [P] [US1] Write `tests/Unit/Services/AllocationServiceTest.php` - test all 4 priority rules (FR-007.1-4)
- [X] T041 [P] [US1] Write `tests/Unit/Services/TournamentHistoryTest.php` - player table/terrain history queries
- [X] T042 [P] [US1] Write `tests/Integration/AllocationGenerationTest.php` - end-to-end allocation with conflicts
- [X] T043 [P] [US1] Write `tests/Integration/BCPScraperTest.php` using fixtures in `tests/fixtures/bcp_pairings.html`

### Implementation for User Story 1

#### BCP Integration

- [X] T044 [US1] Create `tests/fixtures/bcp_pairings.html` with sample BCP DOM structure
- [X] T045 [US1] Create `src/Services/BCPScraperService.php` per [research.md#implementation-approach](./research.md#implementation-approach)
- [X] T046 [US1] Implement `fetchPairings(eventId, round)` with Chrome PHP headless browser
- [X] T047 [US1] Implement HTML parser extracting player names, IDs, scores, tables per [research.md#expected-data-fields](./research.md#expected-data-fields)
- [X] T048 [US1] Implement retry with exponential backoff per [research.md#best-practices](./research.md#best-practices-for-bcp-scraping)

#### Allocation Algorithm

- [X] T049 [US1] Create `src/Services/TournamentHistory.php` with player history queries per [data-model.md#query-patterns](./data-model.md#query-patterns)
- [X] T050 [US1] Implement `hasPlayerUsedTable()` per [data-model.md#get-player-table-history](./data-model.md#get-player-table-history)
- [X] T051 [US1] Implement `hasPlayerExperiencedTerrain()` per [data-model.md#get-player-terrain-history](./data-model.md#get-player-terrain-history)
- [X] T052 [US1] Create `src/Services/CostCalculator.php` with cost constants: TABLE_REUSE=100000, TERRAIN_REUSE=10000, TABLE_NUMBER=1
- [X] T053 [US1] Create `src/Services/AllocationService.php` with `generateAllocations()` method
- [X] T054 [US1] Implement stable sort wrapper per [research.md#determinism-requirements](./research.md#determinism-requirements)
- [X] T055 [US1] Implement priority-weighted greedy assignment per [research.md#algorithm-overview](./research.md#algorithm-overview)
- [X] T056 [US1] Implement `AllocationReason` JSON structure per [data-model.md#allocation_reason-json-structure](./data-model.md#allocation_reason-json-structure-fr-014)
- [X] T057 [US1] Implement conflict detection and flagging per FR-010

#### API Endpoints

- [X] T058 [US1] Create `src/Controllers/RoundController.php` with POST /api/tournaments/{id}/rounds/{n}/import
- [X] T059 [US1] Implement POST /api/tournaments/{id}/rounds/{n}/generate per [contracts/api.yaml#/generate](./contracts/api.yaml)
- [X] T060 [US1] Implement GET /api/tournaments/{id}/rounds/{n} (admin view with conflicts)

#### Admin UI

- [X] T061 [US1] Create `src/Views/round/manage.php` showing pairings and allocations
- [X] T062 [US1] Add refresh button with HTMX to re-import from BCP per FR-015
- [X] T063 [US1] Add generate button with HTMX to trigger allocation
- [X] T064 [US1] Display allocation results with conflict highlighting per FR-010

**Checkpoint**: Core allocation generation works - can import pairings and generate optimized table assignments

---

## Phase 6: User Story 3 - Edit and Publish Table Allocation (Priority: P3)

**Goal**: Organizer can manually adjust allocations, swap tables, and publish for players to view

**Independent Test**: Generate allocation, swap tables 3 and 7, observe conflict update, publish → verify public visibility

**Reference**: [spec.md#user-story-3](./spec.md#user-story-3---edit-and-publish-table-allocation-priority-p3)

### Tests for User Story 3

- [X] T065 [P] [US3] Write `tests/Unit/Services/AllocationEditServiceTest.php` - table assignment change, swap logic
- [X] T066 [P] [US3] Write `tests/Integration/AllocationEditTest.php` - edit persistence, conflict recalculation
- [X] T067 [P] [US3] Write `tests/Integration/PublishTest.php` - publish state change, public visibility

### Implementation for User Story 3

- [X] T068 [US3] Create `src/Services/AllocationEditService.php` with `editTableAssignment()` and `swapTables()` methods
- [X] T069 [US3] Implement conflict recalculation after manual edit per FR-010
- [X] T070 [US3] Create `src/Controllers/AllocationController.php` with PATCH /api/allocations/{id} per [contracts/api.yaml#/allocations](./contracts/api.yaml)
- [X] T071 [US3] Implement POST /api/allocations/swap per FR-009
- [X] T072 [US3] Implement POST /api/tournaments/{id}/rounds/{n}/publish per FR-011
- [X] T073 [US3] Update `src/Views/round/manage.php` with inline edit controls using HTMX
- [X] T074 [US3] Add swap UI with drag-drop or selection per [research.md#htmx-patterns](./research.md#implementation-notes)
- [X] T075 [US3] Add publish button with confirmation dialog per [research.md#htmx-patterns](./research.md#implementation-notes)
- [X] T076 [US3] Display warning when editing published round per acceptance scenario 5

**Checkpoint**: Editing and publishing work - can adjust allocations and make visible to players

---

## Phase 7: User Story 4 - View Published Allocations (Priority: P4)

**Goal**: Tournament players can view published table allocations without authentication

**Independent Test**: Publish allocation, access public URL without auth → verify table assignments display

**Reference**: [spec.md#user-story-4](./spec.md#user-story-4---view-published-allocations-priority-p4)

### Tests for User Story 4

- [X] T077 [P] [US4] Write `tests/Integration/PublicViewTest.php` - unauthenticated access, round visibility

### Implementation for User Story 4

- [X] T078 [US4] Create `src/Controllers/PublicController.php` with GET /api/public/tournaments/{id} per [contracts/api.yaml#/public](./contracts/api.yaml)
- [X] T079 [US4] Implement GET /api/public/tournaments/{id}/rounds/{n} per [data-model.md#get-published-allocations](./data-model.md#get-published-allocations-for-public-view)
- [X] T080 [US4] Create `src/Views/public/tournament.php` showing tournament info and round selector
- [X] T081 [US4] Create `src/Views/public/round.php` displaying allocation table per FR-012
- [X] T082 [US4] Implement round selector showing only published rounds per acceptance scenario 3
- [X] T083 [US4] Style public view for readability on venue devices (large fonts, high contrast)

**Checkpoint**: Public view works - players can see their table assignments

---

## Phase 8: Polish & Cross-Cutting Concerns

**Purpose**: Performance validation, security hardening, final integration

**Reference**: [plan.md#phase-10-integration-testing--refinement](./plan.md#phase-10-integration-testing--refinement)

### Performance Validation

- [X] T084 Write performance test: allocation generation < 10 seconds for 40 players per SC-002
- [X] T085 Write performance test: page load < 3 seconds per SC-004
- [X] T086 Optimize database queries with proper indexing if needed

### Security Hardening

- [X] T087 Audit all SQL queries use prepared statements per Constitution Principle III
- [X] T088 Validate all user input at system boundaries per [data-model.md#validation-at-system-boundaries](./data-model.md#validation-at-system-boundaries)
- [X] T089 Implement CSRF protection for form submissions
- [X] T090 Set secure cookie flags (HttpOnly, SameSite)

### Final Integration

- [X] T091 End-to-end test: tournament creation to first allocation < 5 minutes per SC-001
- [X] T092 Test conflict detection rate = 100% per SC-006
- [X] T093 Test allocation priority rules applied correctly per SC-007
- [X] T094 Run full test suite and verify all tests pass
- [X] T095 Validate quickstart.md instructions work on fresh setup

---

## Phase 9: E2E Browser Tests with Playwright

**Purpose**: End-to-end browser testing to validate complete user workflows across the application

**Reference**: Playwright for PHP 7.4 application testing with Docker support for local and CI environments

### Infrastructure Setup

- [X] T096 [P] Create `docker-compose.test.yml` with Playwright container alongside existing PHP + MySQL services
- [X] T097 [P] Create `playwright.config.ts` with base URL pointing to PHP container (http://php:80 for Docker, localhost:8080 for local)
- [X] T098 Create `package.json` in `tests/e2e/` with Playwright dependencies (@playwright/test)
- [X] T099 Create `.github/workflows/e2e-tests.yml` workflow extending tests.yml pattern with Playwright execution
- [X] T100 Update `docker-compose.yml` to add healthcheck for PHP service readiness

### Test Data Management (API-only, not exposed in UI)

- [X] T101 Implement DELETE /api/tournaments/{id} endpoint in `src/Controllers/TournamentController.php` - cascades delete to tables, rounds, players, allocations (admin auth required)
- [X] T102 Write `tests/Integration/TournamentDeleteTest.php` to verify cascade deletion and auth protection
- [X] T103 [P] Create `tests/e2e/helpers/cleanup.ts` with helper to delete test tournaments via API after each test

### Test Fixtures and Helpers

- [X] T104 [P] Create `tests/e2e/fixtures/test-data.ts` with tournament, player, and allocation seed data
- [X] T105 [P] Create `tests/e2e/fixtures/bcp-mock.ts` with mock BCP API responses for pairing data
- [X] T106 [P] Create `tests/e2e/helpers/api.ts` with helper functions to seed database via API calls
- [X] T107 [P] Create `tests/e2e/helpers/auth.ts` with helper functions to authenticate as admin via token

### E2E Tests for User Story 2 (Tournament Creation)

- [X] T108 [P] [US2] Write `tests/e2e/tournament-creation.spec.ts` - create tournament with name, BCP URL, table count
- [X] T109 [P] [US2] Write `tests/e2e/tournament-terrain.spec.ts` - assign terrain types to tables

### E2E Tests for User Story 5 (Authentication)

- [X] T110 [P] [US5] Write `tests/e2e/authentication.spec.ts` - login with admin token, cookie persistence, invalid token rejection

### E2E Tests for User Story 1 (Allocation Generation)

- [ ] T111 [P] [US1] Write `tests/e2e/allocation-generation.spec.ts` - import pairings, generate allocations, verify conflict display
- [ ] T112 [P] [US1] Write `tests/e2e/round-management.spec.ts` - round navigation, refresh from BCP, regenerate allocations

### E2E Tests for User Story 3 (Edit and Publish)

- [ ] T113 [P] [US3] Write `tests/e2e/allocation-editing.spec.ts` - change table assignment, swap tables, conflict highlighting
- [ ] T114 [P] [US3] Write `tests/e2e/allocation-publish.spec.ts` - publish allocation, warning on edit after publish

### E2E Tests for User Story 4 (Public View)

- [ ] T115 [P] [US4] Write `tests/e2e/public-view.spec.ts` - view published allocations without auth, round selector, hidden unpublished rounds

### Cross-Browser and Visual Tests

- [ ] T116 [P] Write `tests/e2e/cross-browser.spec.ts` - verify critical flows work in Chrome, Firefox, Safari (webkit)
- [ ] T117 [P] Write `tests/e2e/responsive.spec.ts` - verify UI renders correctly on mobile viewport sizes

### CI/CD Integration

- [ ] T118 Update `.github/workflows/tests.yml` to include Playwright e2e test execution after PHP tests pass
- [ ] T119 Configure Playwright to save screenshots and traces on failure for CI debugging
- [ ] T120 Add npm script in `tests/e2e/package.json` for running tests locally: `npm run test:e2e`

**Checkpoint**: E2E tests validate complete user workflows - can run locally via docker-compose and in GitHub Actions CI

---

## Dependencies & Execution Order

### Phase Dependencies

```
Phase 1: Setup
    ↓
Phase 2: Foundational (BLOCKS all user stories)
    ↓
    ├── Phase 3: US2 - Tournament Creation
    │       ↓
    │   Phase 4: US5 - Authentication
    │       ↓
    │   Phase 5: US1 - Allocation Generation (MVP)
    │       ↓
    │   Phase 6: US3 - Edit & Publish
    │       ↓
    │   Phase 7: US4 - Public View
    ↓
Phase 8: Polish
    ↓
Phase 9: E2E Browser Tests (Playwright)
```

### User Story Dependencies

| Story | Can Start After | Notes |
|-------|-----------------|-------|
| US2 (Tournament) | Phase 2 | First user story - creates prerequisite data |
| US5 (Auth) | US2 | Needs tournaments to exist for token validation |
| US1 (Allocation) | US5 | Core feature - needs auth to protect endpoints |
| US3 (Edit/Publish) | US1 | Needs allocations to exist |
| US4 (Public View) | US3 | Needs published allocations |
| E2E Tests (Phase 9) | Phase 8 | Needs all features implemented before browser testing |

### Parallel Opportunities Within Phases

**Phase 2 (Foundational)**:
- T011-T016 (all models) can run in parallel

**Phase 3 (US2)**:
- T021-T023 (all tests) can run in parallel

**Phase 5 (US1)**:
- T039-T043 (all tests) can run in parallel
- T049-T051 (history service methods) can run in parallel after T049

**Phase 9 (E2E Tests)**:
- T096-T097 (infrastructure setup) can run in parallel
- T104-T107 (test fixtures and helpers) can run in parallel after T098
- T108-T117 (all spec files) can run in parallel after infrastructure and test data management setup

---

## Parallel Example: User Story 1 Tests

```bash
# Launch all US1 tests in parallel:
Task: "Write tests/Unit/Services/CostCalculatorTest.php"
Task: "Write tests/Unit/Services/AllocationServiceTest.php"
Task: "Write tests/Unit/Services/TournamentHistoryTest.php"
Task: "Write tests/Integration/AllocationGenerationTest.php"
Task: "Write tests/Integration/BCPScraperTest.php"
```

---

## Parallel Example: Phase 9 E2E Tests

```bash
# Launch test fixtures and helpers in parallel (after package.json created):
Task: "Create tests/e2e/fixtures/test-data.ts"
Task: "Create tests/e2e/fixtures/bcp-mock.ts"
Task: "Create tests/e2e/helpers/api.ts"
Task: "Create tests/e2e/helpers/auth.ts"

# Launch all E2E spec files in parallel (after infrastructure + test data management ready):
Task: "Write tests/e2e/tournament-creation.spec.ts"
Task: "Write tests/e2e/tournament-terrain.spec.ts"
Task: "Write tests/e2e/authentication.spec.ts"
Task: "Write tests/e2e/allocation-generation.spec.ts"
Task: "Write tests/e2e/round-management.spec.ts"
Task: "Write tests/e2e/allocation-editing.spec.ts"
Task: "Write tests/e2e/allocation-publish.spec.ts"
Task: "Write tests/e2e/public-view.spec.ts"
Task: "Write tests/e2e/cross-browser.spec.ts"
Task: "Write tests/e2e/responsive.spec.ts"
```

---

## Implementation Strategy

### MVP First (User Stories 2, 5, 1)

1. Complete Phase 1: Setup
2. Complete Phase 2: Foundational
3. Complete Phase 3: US2 - Tournament Creation
4. Complete Phase 4: US5 - Authentication
5. Complete Phase 5: US1 - Allocation Generation
6. **STOP and VALIDATE**: Test core allocation flow end-to-end
7. Deploy/demo MVP

**MVP Scope**: Organizer can create tournament, import pairings, generate optimized table allocations.

### Incremental Delivery

1. Setup + Foundational → Foundation ready
2. Add US2 (Tournament) → Test → Can create tournaments
3. Add US5 (Auth) → Test → Can authenticate
4. Add US1 (Allocation) → Test → **MVP Complete!**
5. Add US3 (Edit/Publish) → Test → Can edit and publish
6. Add US4 (Public) → Test → Players can view assignments
7. Polish → Performance and security verified
8. E2E Tests → Full browser automation coverage, CI/CD integrated

---

## Notes

- [P] tasks = different files, no dependencies
- [Story] label maps task to specific user story for traceability
- Each user story should be independently completable and testable
- TDD: Write tests FIRST, verify they FAIL before implementing
- Commit after each task or logical group
- Stop at any checkpoint to validate story independently
- Reference docs in task descriptions point to exact section for implementation details
