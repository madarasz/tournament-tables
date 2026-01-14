# Tournament Tables

A web app for tournament organizers to generate table allocations that ensure players experience different tables each round. Integrates with Best Coast Pairings (BCP) for pairing data.

## Features

- **Smart Table Allocation**: Automatically assigns tables so players don't repeat tables from previous rounds
- **Terrain Type Tracking**: Prioritizes terrain variety across rounds
- **BCP Integration**: Fetches pairings directly from Best Coast Pairings
- **Conflict Detection**: Highlights when allocation rules are violated
- **Public View**: Players can view published allocations without login

## Requirements

- PHP 7.4.33
- MySQL 5.7+
- Composer

## Quick Setup

```bash
# Install dependencies
composer install

# Configure database
cp config/database.example.php config/database.php
# Edit config/database.php with your credentials

# Run migrations and seed data
php bin/migrate.php
php bin/seed-terrain-types.php

# Start server
php -S localhost:8080 -t public public/index.php
```

Visit `http://localhost:8080`

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

```bash
./vendor/bin/phpunit              # All tests
./vendor/bin/phpunit --testsuite unit        # Unit only
./vendor/bin/phpunit --testsuite integration # Integration only
```

## License

MIT
