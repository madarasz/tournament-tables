# Tournament Tables

A web app for tournament organizers to generate table allocations that ensure players experience different tables each round. Integrates with Best Coast Pairings (BCP) for pairing data.

## Features

- **Smart Table Allocation**: Automatically assigns tables so players don't repeat tables from previous rounds
- **Terrain Type Tracking**: Prioritizes terrain variety across rounds
- **BCP Integration**: Fetches pairings directly from Best Coast Pairings
- **Conflict Detection**: Highlights when allocation rules are violated
- **Public View**: Players can view published allocations without login

## Requirements

- Docker & Docker Compose
- (Optional for local dev) PHP 7.4.33, MySQL 5.7+, Composer

## Quick Setup with Docker

```bash
# Start development environment
docker-compose up -d

# Run database migrations and seed data
composer migrate
composer seed
```

Visit `http://localhost:8080`

### Database Management

PHPMyAdmin is available for database management:
- **URL**: `http://localhost:8081`
- **Server**: `mysql`
- **Username**: `root`
- **Password**: `root`
- **Database**: `tournament_tables`

## Local Setup (without Docker)

```bash
# Install dependencies
composer install

# Configure database
cp config/database.example.php config/database.php
# Edit config/database.php with your credentials

# Run migrations and seed data
php bin/migrate.php
php bin/seed-terrain-types.php

# Start PHP built-in server
php -S localhost:8080 -t public
```

## Usage

1. **Create Tournament** - Enter name, BCP event URL, and table count
2. **Import Pairings** - Fetch pairings from BCP for each round
3. **Generate Allocations** - System assigns tables following priority rules
4. **Edit & Publish** - Make adjustments if needed, then publish for players

## Allocation Priority

1. Round 1 uses BCP's original table assignments
2. Players avoid tables they've used before
3. Players experience new terrain types
4. Higher scores get lower table numbers

## API Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/tournaments` | Create tournament |
| POST | `/api/tournaments/{id}/rounds/{n}/import` | Import BCP pairings |
| POST | `/api/tournaments/{id}/rounds/{n}/generate` | Generate allocations |
| POST | `/api/tournaments/{id}/rounds/{n}/publish` | Publish allocations |
| GET | `/api/public/tournaments/{id}/rounds/{n}` | Public view |

## Running Tests

### Composer Scripts (Recommended)

```bash
composer test:unit   # Run all PHPUnit test suites
composer test:e2e    # Start test environment and run Playwright E2E tests
composer migrate     # Run database migrations
composer seed        # Seed terrain types
```

### Unit & Integration Tests (Docker)

```bash
# Start development environment
docker-compose up -d

# Run all PHP tests (recommended)
composer test:unit

# Or manually run specific test suites
docker-compose exec php ./vendor/bin/phpunit --testsuite unit
docker-compose exec php ./vendor/bin/phpunit --testsuite integration
docker-compose exec php ./vendor/bin/phpunit --testsuite performance
```

### E2E Tests with Playwright (Docker)

E2E tests use a layered Docker Compose configuration that adds Playwright and uses isolated test data.

```bash
# Run E2E tests (recommended - starts environment automatically)
composer test:e2e

# Or manually:
# Start test environment (layered config)
docker-compose -f docker-compose.yml -f docker-compose.test.yml up -d

# Run database migrations for test environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml run --rm migrate

# Install Playwright dependencies (first time only)
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npm install

# Run E2E tests
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test

# Run specific test file
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test tournament-creation.spec.ts

# Run tests with UI (headed mode) - requires X11 forwarding
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright test --headed

# View test report
docker-compose -f docker-compose.yml -f docker-compose.test.yml exec playwright npx playwright show-report

# Stop test environment
docker-compose -f docker-compose.yml -f docker-compose.test.yml down
```

### E2E Tests Locally (without Docker)

```bash
# Start PHP server
php -S localhost:8080 -t public &

# Navigate to E2E test directory
cd tests/E2E

# Install dependencies
npm install

# Run tests
npx playwright test

# Run with browser visible
npx playwright test --headed
```

## Docker Compose Files

| File | Purpose |
|------|---------|
| `docker-compose.yml` | Base development environment (PHP + MySQL) |
| `docker-compose.test.yml` | Test overrides (adds Playwright, uses isolated test DB) |

The test configuration layers on top of the base, so you use both files together:
```bash
docker-compose -f docker-compose.yml -f docker-compose.test.yml <command>
```

## License

MIT
