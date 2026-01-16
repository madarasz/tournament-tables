# Tournament Tables - Development Guidelines

**Last Updated**: 2026-01-16
**Project**: Kill Team Tournament Table Allocation System
**Main Branch**: `master` | **Current Branch**: `e2e-tests`

## Project Overview

A web application for Kill Team tournament organizers that generates intelligent table allocations, ensuring players experience different tables and terrain types each round. Integrates with Best Coast Pairings (BCP) for pairing data.

**Key Features**:
- Smart table allocation algorithm (priority-weighted greedy)
- BCP API integration for pairing imports
- Terrain type tracking and variety optimization
- Conflict detection and manual editing
- Public viewing (unauthenticated)
- Admin authentication via token-based system

## Technology Stack

| Component | Technology | Version | Notes |
|-----------|-----------|---------|-------|
| **Backend** | PHP | 7.4.33 | Strict compatibility required |
| **Database** | MySQL | 8.0 | InnoDB, utf8mb4 collation |
| **Frontend** | Pico CSS | Latest | Lightweight, classless CSS |
| **AJAX/DOM** | HTMX | 1.9.10 | Partial page updates |
| **Testing** | PHPUnit | 9.5 | Unit/Integration/Performance |
| **E2E Testing** | Playwright | 1.57.0 | Browser automation (Chromium) |
| **Container** | Docker Compose | - | Dev and test environments |
| **CI/CD** | GitHub Actions | - | Auto-run on PR/push |

**IMPORTANT**: PHP 7.4.33 compatibility is a strict requirement. Do not use features from PHP 8.x.

## Directory Structure

```
kt-tables/
├── src/                          # Application source (5,189 lines PHP)
│   ├── Controllers/              # HTTP request handlers (8 files)
│   │   ├── TournamentController.php
│   │   ├── RoundController.php
│   │   ├── AllocationController.php
│   │   ├── AuthController.php
│   │   └── PublicController.php
│   ├── Models/                   # Entity models (6 files)
│   │   ├── Tournament.php
│   │   ├── Round.php
│   │   ├── Table.php
│   │   ├── Player.php
│   │   ├── Allocation.php
│   │   └── TerrainType.php
│   ├── Services/                 # Business logic
│   │   ├── AllocationService.php       # Core allocation algorithm
│   │   ├── CostCalculator.php          # Priority-weighted cost function
│   │   ├── BCPScraperService.php       # BCP API integration
│   │   ├── TournamentHistory.php       # Player table/terrain history
│   │   ├── AllocationEditService.php   # Manual edits & swaps
│   │   ├── AuthService.php             # Token validation
│   │   ├── TokenGenerator.php          # Admin token creation
│   │   ├── CsrfService.php             # CSRF token management
│   │   └── TournamentService.php       # Tournament lifecycle
│   ├── Middleware/               # Cross-cutting concerns
│   │   ├── AdminAuthMiddleware.php     # Token authentication
│   │   └── CsrfMiddleware.php          # CSRF protection
│   ├── Database/
│   │   └── Connection.php              # PDO singleton with prepared statements
│   └── Views/                    # PHP templates (8 files)
│       ├── layout.php                  # Base layout (Pico CSS + HTMX)
│       ├── home.php, auth/login.php
│       ├── tournament/create.php, tournament/dashboard.php
│       ├── round/manage.php
│       └── public/tournament.php, public/round.php
│
├── tests/                        # Comprehensive test suites
│   ├── Unit/                     # Logic/algorithm tests (9 files)
│   ├── Integration/              # API/DB tests (9 files)
│   ├── Performance/              # Benchmarks (2 files)
│   └── E2E/                      # Browser tests (Playwright)
│       ├── specs/                      # Test specifications (TypeScript)
│       ├── helpers/                    # Test utilities (api, auth, cleanup)
│       ├── fixtures/                   # Test data
│       ├── package.json, tsconfig.json
│       └── playwright.config.ts
│
├── config/
│   ├── database.example.php      # DB config template
│   └── apache.conf               # Apache virtual host
│
├── bin/                          # CLI scripts
│   ├── migrate.php               # Database schema creation
│   ├── seed-terrain-types.php    # Populate terrain types
│   └── fix-allocations-fk.php    # Migration helper
│
├── public/
│   ├── index.php                 # Front controller & router
│   └── .htaccess                 # Apache rewrite rules
│
├── docs/
│   └── e2e-testing-guidelines.md # E2E test best practices
│
├── specs/001-table-allocation/   # Feature specifications
│   ├── spec.md, data-model.md, plan.md
│   └── contracts/api.yaml        # OpenAPI specification
│
├── docker-compose.yml            # Development environment
├── docker-compose.test.yml       # Test environment overlay
├── phpunit.xml                   # PHPUnit configuration
└── composer.json                 # Dependencies
```

## Core Architecture

### Front Controller Pattern
**File**: `/public/index.php`
- Custom regex-based router (no framework dependency)
- 23 routes defined inline with HTTP method and path pattern
- Supports route parameters: `{id}`, `{n}` (round number)
- Middleware applied per-route (AdminAuth, CSRF)

### Database Layer
**File**: `src/Database/Connection.php`
- Singleton PDO wrapper
- Prepared statement helpers: `fetchAll()`, `fetchOne()`, `fetchColumn()`, `execute()`
- All queries use parameterized statements (no string concatenation)
- Transaction support for complex operations

### Service Layer Pattern
Business logic isolated in service classes:
- **AllocationService**: Priority-weighted greedy allocation algorithm
- **CostCalculator**: Cost function with weighted priorities (P1: 100k, P2: 10k, P3: 1)
- **BCPScraperService**: BCP REST API integration with exponential backoff retry
- **TournamentHistory**: Tracks player table/terrain usage across rounds

### Authentication System
- Token-based authentication (16-char base64)
- Tokens generated at tournament creation
- Stored as cookie (30-day retention) or X-Admin-Token header
- **AdminAuthMiddleware**: Validates tokens for protected routes
- **CsrfMiddleware**: CSRF protection for form submissions (exempts /api/)

## Data Model

### Entity Relationship
```
Tournament (1) ──┬─── (N) Round
                 ├─── (N) Table (with optional terrain_type_id)
                 └─── (N) Player

Round (1) ──── (N) Allocation (FK: round_id, table_id, player1_id, player2_id)

TerrainType (1) ──── (N) Table
```

### Core Tables
- **tournaments**: id, name, bcp_event_id, bcp_url, table_count, admin_token
- **tables**: id, tournament_id, table_number, terrain_type_id
- **rounds**: id, tournament_id, round_number, is_published
- **players**: id, tournament_id, bcp_player_id, name
- **allocations**: id, round_id, table_id, player1_id, player2_id, scores, allocation_reason (JSON)
- **terrain_types**: id, name, description, sort_order

## API Endpoints

### Tournament Management
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/tournaments` | None | Create tournament (returns admin token) |
| GET | `/api/tournaments/{id}` | Admin | Get tournament details |
| DELETE | `/api/tournaments/{id}` | Admin | Delete tournament |
| PUT | `/api/tournaments/{id}/tables` | Admin | Update table terrain types |

### Round Management
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| POST | `/api/tournaments/{id}/rounds/{n}/import` | Admin | Import BCP pairings |
| POST | `/api/tournaments/{id}/rounds/{n}/generate` | Admin | Generate allocations |
| POST | `/api/tournaments/{id}/rounds/{n}/publish` | Admin | Publish allocations |
| GET | `/api/tournaments/{id}/rounds/{n}` | Admin | Get round details |

### Allocation Editing
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| PATCH | `/api/allocations/{id}` | Admin | Edit single allocation |
| POST | `/api/allocations/swap` | Admin | Swap two allocations |

### Public Views
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/api/public/tournaments/{id}` | None | Public tournament view |
| GET | `/api/public/tournaments/{id}/rounds/{n}` | None | Public round view |

### Utility
| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/api/terrain-types` | None | List all terrain types |
| POST | `/api/auth` | None | Login with admin token |

## Commands

### Docker Development Environment

```bash
# Start development environment
docker-compose up -d

# Run database migrations
docker-compose exec php php bin/migrate.php

# Seed terrain types
docker-compose exec php php bin/seed-terrain-types.php

# Stop environment
docker-compose down

# View logs
docker-compose logs -f php
```

### Testing Commands

#### PHPUnit Tests (Docker)
```bash
# Run all test suites
docker-compose exec -w /var/www/app php ./vendor/bin/phpunit --testsuite unit,integration,performance,e2e --process-isolation

# Run specific test suite
docker-compose exec php ./vendor/bin/phpunit --testsuite unit
docker-compose exec php ./vendor/bin/phpunit --testsuite integration
docker-compose exec php ./vendor/bin/phpunit --testsuite performance

# Run specific test file
docker-compose exec php ./vendor/bin/phpunit tests/Unit/Services/AllocationServiceTest.php
```

#### Playwright E2E Tests (Docker)
```bash
# Start test environment (layered config)
docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d

# Run migrations for test DB
docker-compose -f docker-compose.yml -f docker-compose.test.yml run --rm migrate

# Install Playwright dependencies (first time only)
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npm install

# Run E2E tests
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test

# Run specific test file
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test tournament-creation.spec.ts

# View test report
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright show-report

# Stop test environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml down
```

#### Local Testing (without Docker)
```bash
# PHPUnit tests
./vendor/bin/phpunit
./vendor/bin/phpunit --testsuite unit

# E2E tests (from tests/E2E directory)
cd tests/E2E
npm install
npx playwright test
npx playwright test --headed
```

### Local Development (without Docker)

```bash
# Install dependencies
composer install

# Configure database (copy and edit)
cp config/database.example.php config/database.php

# Create database
mysql -u root -p -e "CREATE DATABASE tournament_tables CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php bin/migrate.php

# Seed terrain types
php bin/seed-terrain-types.php

# Start development server
php -S localhost:8080 -t public public/index.php
```

**Note**: The `public/index.php` argument is required for URL routing. Without it, clean URLs like `/tournament/create` will return 404.

## Code Style

### PHP Standards
- Follow PSR-12 coding standard
- Strict type declarations in all files: `declare(strict_types=1);`
- All database queries use prepared statements (no string concatenation)
- PHP 7.4.33 compatibility (no PHP 8.x features like match expressions, named arguments, etc.)

### Code Conventions
- **Models**: Static methods for CRUD operations, `fromRow()` for hydration
- **Services**: Business logic, stateless where possible
- **Controllers**: Thin controllers, delegate to services
- **Views**: PHP templates, use layout.php for base structure
- **Middleware**: Implement callable interface `(Request, Response, callable) => Response`

### Security Requirements
- Always use prepared statements for SQL queries
- Validate and sanitize user input
- Use CSRF tokens for form submissions
- Admin routes require token authentication
- Never expose admin tokens in logs or error messages

### Naming Conventions
- Classes: PascalCase (e.g., `AllocationService`)
- Methods/Functions: camelCase (e.g., `calculateCost()`)
- Database tables: snake_case, plural (e.g., `terrain_types`)
- Database columns: snake_case (e.g., `table_number`)

## Allocation Algorithm

### Priority-Weighted Greedy Algorithm
**File**: `src/Services/AllocationService.php`

**Steps**:
1. **Round 1**: Use BCP's original table assignments
2. **Round 2+**:
   - Sort pairings by combined player scores (descending)
   - For each pairing, calculate cost for all available tables
   - Assign pairing to lowest-cost table (tie-break by table number)

### Cost Function
**File**: `src/Services/CostCalculator.php`

**Priority Weights**:
- **P1 (100,000)**: Table reuse avoidance - players shouldn't use same table twice
- **P2 (10,000)**: Terrain reuse avoidance - prefer new terrain types
- **P3 (1)**: Table number preference - higher scores get lower table numbers

**Cost Calculation**:
```
cost = (P1 × table_reuse_penalty) + (P2 × terrain_reuse_penalty) + (P3 × table_number)
```

## Important Design Decisions

1. **PHP 7.4.33 Strict Compatibility**: No PHP 8.x features allowed
2. **No Heavy JavaScript Framework**: Uses HTMX for lightweight interactivity
3. **Simple Token Authentication**: No OAuth or complex auth systems
4. **Greedy Algorithm**: Trades optimality for performance (O(n²) vs exponential)
5. **Layered Docker Compose**: Test config layers on base for environment isolation
6. **Browser-Based E2E Only**: API testing covered by integration tests
7. **Public Views Unauthenticated**: Players can view without login
8. **Post-Publish Editing Allowed**: Admins can edit after publishing

## Docker Configuration

### Development Environment (`docker-compose.yml`)
**Services**:
- **php**: PHP 7.4.33-apache, port 8080, extensions: pdo, pdo_mysql, rewrite
- **mysql**: MySQL 8.0, port 3306, persistent volume `mysql_data`

### Test Environment (`docker-compose.test.yml`)
**Layered Configuration**: Applied on top of base config
```bash
docker-compose -f docker-compose.yml -f docker-compose.test.yml <command>
```

**Additional Services**:
- **playwright**: Node 20, Playwright v1.57.0, Chromium browser
- **migrate**: Profile-based utility service for running migrations

**Overrides**:
- Uses separate test database: `mysql_test_data` volume
- Sets `APP_ENV: testing` environment variable

## Security Architecture

### Authentication
- **Admin Tokens**: 16-character base64 strings generated at tournament creation
- **Storage**: HTTP cookie (30-day retention) or X-Admin-Token header
- **Validation**: AdminAuthMiddleware checks token against database
- **Scope**: Per-tournament (each tournament has unique token)

### CSRF Protection
- **CsrfService**: Generates and validates CSRF tokens
- **CsrfMiddleware**: Validates tokens for POST/PUT/PATCH/DELETE requests
- **Exemptions**: /api/ routes (use token auth instead)
- **Token Storage**: Session-based

### Database Security
- **Prepared Statements**: All queries use parameterized statements via PDO
- **Foreign Key Constraints**: Enabled with cascading deletes
- **Input Validation**: Controller-level validation before DB operations
- **Collation**: utf8mb4_unicode_ci for Unicode support

## BCP Integration

### REST API Integration
**Service**: `src/Services/BCPScraperService.php`
**Endpoint**: `https://newprod-api.bestcoastpairings.com/v1/events/{eventId}/pairings`

**Features**:
- Extracts event ID from BCP URL patterns
- Exponential backoff retry logic (3 retries, 1s base delay, 2x multiplier)
- Validates response structure
- Maps BCP player IDs to local database

**BCP URL Format**:
- Pattern: `https://www.bestcoastpairings.com/event/{eventId}`
- Example: `https://www.bestcoastpairings.com/event/t6OOun8POR60`

## CI/CD Pipeline

### GitHub Actions Workflow
**File**: `.github/workflows/tests.yml`

**Jobs**:
1. **PHPUnit Tests**:
   - PHP 7.4 with pdo, pdo_mysql extensions
   - MySQL 8.0 service
   - Runs all test suites (unit, integration, performance)
   - 15-minute timeout

2. **Playwright E2E Tests**:
   - Node 20, Playwright v1.57
   - Chromium browser
   - Uploads HTML reports (30-day retention)
   - Uploads failure artifacts (7-day retention)
   - 30-minute timeout

**Triggers**: Pull requests and pushes to master/main branches

## Common Development Tasks

### Creating a New Controller
1. Create file in `src/Controllers/`
2. Add routes in `public/index.php`
3. Apply middleware (AdminAuth, CSRF) as needed
4. Delegate business logic to services
5. Return JSON responses for API routes
6. Use views for HTML routes

### Creating a New Service
1. Create file in `src/Services/`
2. Use constructor injection for dependencies (Connection, other services)
3. Keep methods stateless where possible
4. Add unit tests in `tests/Unit/Services/`
5. Add integration tests if DB interaction needed

### Adding Database Migrations
1. Edit `bin/migrate.php`
2. Add new tables or ALTER statements
3. Test locally: `php bin/migrate.php`
4. Test in Docker: `docker-compose exec php php bin/migrate.php`
5. Update data model documentation if schema changes

### Writing E2E Tests
1. Create spec file in `tests/E2E/specs/`
2. Follow guidelines in `docs/e2e-testing-guidelines.md`
3. Use test helpers from `tests/E2E/helpers/`
4. Focus on critical user flows (test pyramid)
5. Use data-testid selectors where possible

## Troubleshooting

### BCP Import Fails
- Check BCP URL format is correct
- Verify BCP event ID is valid
- Check network connectivity to BCP API
- Review exponential backoff retry logs

### Database Connection Issues
- Verify MySQL is running: `docker-compose ps`
- Check credentials in `config/database.php`
- Ensure database exists
- Check port 3306 is not already in use

### E2E Tests Fail
- Ensure test environment is running: `docker-compose -f docker-compose.yml -f docker-compose.test.yml ps`
- Run migrations for test DB: `docker-compose -f docker-compose.yml -f docker-compose.test.yml run --rm migrate`
- Check Playwright browser installation: `npx playwright install`
- Review test artifacts in `playwright-report/`

### Permission Issues (Docker)
- Files created by Docker are owned by root
- Fix with: `sudo chown -R $USER:$USER .`

## Recent Changes

- **2026-01-16**: Comprehensive CLAUDE.md documentation update
- **2026-01-13**: E2E testing guidelines added
- **001-table-allocation**: Initial feature implementation (PHP 7.4.33)

<!-- MANUAL ADDITIONS START -->
## Testing Guidelines

When generating E2E tests, follow the guidelines in [docs/e2e-testing-guidelines.md](docs/e2e-testing-guidelines.md):
- Focus on critical user flows only (test pyramid principle)
- Do not test technical requirements, constraints, or error scenarios
- Consolidate related validations into compound tests
- E2E tests must be browser-based, not API-based
<!-- MANUAL ADDITIONS END -->
