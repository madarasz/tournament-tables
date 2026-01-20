# Tournament Tables - Development Guidelines

**Project**: Tournament Table Allocation System
**Main Branch**: `master`

## Project Overview

Web application for tournament organizers that generates intelligent table allocations, ensuring players experience different tables and terrain types each round. Integrates with Best Coast Pairings (BCP) for pairing data.

**Key Features**: Smart allocation algorithm, BCP API integration, terrain tracking, conflict detection, public viewing, token-based admin auth.

## Technology Stack

| Component | Technology | Notes |
|-----------|-----------|-------|
| **Backend** | PHP 7.4.33 | **Strict compatibility required** |
| **Database** | MySQL 8.0 | InnoDB, utf8mb4 collation |
| **Frontend** | Pico CSS + HTMX | Lightweight, no heavy JS framework |
| **Testing** | PHPUnit 9.5 + Playwright 1.57 | Unit/Integration/E2E |
| **Container** | Docker Compose | Dev and test environments |

## Directory Structure

```
kt-tables/
├── src/                 # PHP source (Controllers, Models, Services, Views, Middleware)
├── tests/               # PHPUnit (Unit, Integration, Performance, Contract) + E2E (Playwright)
├── config/              # Database config, Apache config
├── bin/                 # CLI scripts (migrate.php, seed-terrain-types.php)
├── public/              # Front controller (index.php) + .htaccess
├── docs/                # Guidelines (e2e-testing, troubleshooting, development-guide)
└── specs/               # Feature specs + OpenAPI (contracts/api.yaml)
```

## Core Architecture

### Front Controller (`public/index.php`)
Custom regex-based router with 23 routes. Supports route parameters (`{id}`, `{n}`). Middleware applied per-route.

### Database Layer (`src/Database/Connection.php`)
Singleton PDO wrapper with prepared statement helpers. All queries use parameterized statements.

### Service Layer
- **AllocationService**: Priority-weighted greedy allocation algorithm
- **CostCalculator**: Cost function (P1: 100k table reuse, P2: 10k terrain reuse, P3: 1 BCP table mismatch)
- **BCPApiService**: BCP REST API integration with exponential backoff retry
- **TournamentHistory**: Tracks player table/terrain usage across rounds

### Authentication & Security
- **Token-based auth**: 16-char base64 tokens generated at tournament creation
- **Storage**: HTTP cookie (30-day) or X-Admin-Token header
- **AdminAuthMiddleware**: Validates tokens for protected routes
- **CsrfMiddleware**: CSRF protection for form submissions (exempts /api/)
- **Prepared statements**: All DB queries use parameterized statements

## Data Model

```
Tournament (1) ──┬─── (N) Round
                 ├─── (N) Table (with terrain_type_id)
                 └─── (N) Player

Round (1) ──── (N) Allocation (round_id, table_id, player1_id, player2_id)
```

**Core Tables**: tournaments, tables, rounds, players, allocations, terrain_types

## API Endpoints

Full API specification: `specs/001-table-allocation/contracts/api.yaml`

**Key Routes**:
- `POST /api/tournaments` - Create tournament (returns admin token)
- `POST /api/tournaments/{id}/rounds/{n}/import` - Import BCP pairings
- `POST /api/tournaments/{id}/rounds/{n}/generate` - Generate allocations
- `PATCH /api/allocations/{id}` - Edit allocation
- `GET /api/public/tournaments/{id}/rounds/{n}` - Public round view

## Commands

```bash
# Development
docker-compose up -d              # Start environment
composer migrate                  # Run migrations
composer seed                     # Seed terrain types

# Testing
composer test:unit                # PHPUnit tests (all suites)
composer test:e2e                 # Playwright E2E tests
```

For manual Docker commands or local development without Docker, see `docs/development-guide.md`.

## Code Style

- **PSR-12** coding standard
- **Strict types**: `declare(strict_types=1);` in all files
- **PHP 7.4.33 only**: No PHP 8.x features (match, named arguments, etc.)
- **Models**: Static CRUD methods, `fromRow()` for hydration
- **Services**: Stateless business logic
- **Controllers**: Thin, delegate to services

### Naming Conventions
- Classes: PascalCase (`AllocationService`)
- Methods: camelCase (`calculateCost()`)
- DB tables: snake_case, plural (`terrain_types`)
- DB columns: snake_case (`table_number`)

## Allocation Algorithm

**File**: `src/Services/AllocationService.php`

1. **Round 1**: Use BCP's original table assignments
2. **Round 2+**: Sort pairings by score (desc), assign to lowest-cost available table

**Cost Function** (`src/Services/CostCalculator.php`):
```
cost = (100,000 × table_reuse) + (10,000 × terrain_reuse) + bcp_table_mismatch
```

Where `bcp_table_mismatch` = 1 if table differs from original BCP assignment, 0 otherwise.

## BCP Integration

**Service**: `src/Services/BCPApiService.php`

- Event API: `https://newprod-api.bestcoastpairings.com/v1/events/{eventId}`
- Pairings API: `https://newprod-api.bestcoastpairings.com/v1/events/{eventId}/pairings`
- URL Pattern: `https://www.bestcoastpairings.com/event/{eventId}`

## Testing Guidelines

- **E2E tests**: Follow `docs/e2e-testing-guidelines.md` - critical user flows only
- **Contract tests**: Verify BCP API compatibility (`tests/Contract/`)
- **Test pyramid**: Unit > Integration > E2E

## Important Design Decisions

1. **PHP 7.4.33 strict**: No PHP 8.x features
2. **Greedy algorithm**: O(n²) for performance over optimality
3. **Public views unauthenticated**: Players view without login
4. **Post-publish editing**: Admins can edit after publishing

## Additional Documentation

- [Troubleshooting](docs/troubleshooting.md)
- [Development Guide](docs/development-guide.md)
- [E2E Testing Guidelines](docs/e2e-testing-guidelines.md)
