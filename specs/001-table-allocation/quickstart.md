# Quickstart: Tournament Tables

**Generated**: 2026-01-13 | **Branch**: `001-table-allocation`

## Prerequisites

- PHP 7.4.33
- MySQL 5.7+
- Composer
- Chrome/Chromium browser (for BCP scraping)

## Setup

### 1. Clone and Install Dependencies

```bash
git clone <repository-url>
cd tournament-tables

# Install PHP dependencies
composer install
```

### 2. Configure Database

Copy the example config and edit with your database credentials:

```bash
cp config/database.example.php config/database.php
```

Edit `config/database.php`:
```php
<?php
return [
    'host' => 'localhost',
    'database' => 'tournament_tables',
    'username' => 'your_user',
    'password' => 'your_password',
    'charset' => 'utf8mb4',
];
```

### 3. Create Database Schema

```bash
# Create the database
mysql -u root -p -e "CREATE DATABASE tournament_tables CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"

# Run migrations
php bin/migrate.php
# For Docker:
docker-compose exec -w /var/www/app php php bin/migrate.php
```

### 4. Seed Terrain Types

```bash
php bin/seed-terrain-types.php
# For Docker:
docker-compose exec -w /var/www/app php php bin/seed-terrain-types.php
```

### 5. Configure Chrome Path (if needed)

If Chrome is not in the default location, set the path in `config/chrome.php`:

```php
<?php
return [
    'chromePath' => '/usr/bin/chromium-browser',
    // Or on macOS: '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome'
];
```

### 6. Start Development Server

```bash
php -S localhost:8080 -t public public/index.php
```

Visit `http://localhost:8080`

**Note**: The `public/index.php` argument is required to enable URL routing. Without it, the PHP built-in server will return 404 for clean URLs like `/tournament/create`.

## Directory Structure

```
tournament-tables/
├── public/                  # Web root
│   ├── index.php           # Front controller
│   ├── css/
│   └── js/
├── src/
│   ├── Controllers/        # HTTP handlers
│   ├── Models/             # Entity classes
│   ├── Services/           # Business logic
│   ├── Database/           # DB connection
│   └── Views/              # PHP templates
├── tests/
│   ├── Unit/
│   ├── Integration/
│   └── fixtures/
├── config/
│   ├── database.php
│   └── chrome.php
├── bin/                    # CLI scripts
│   ├── migrate.php
│   └── seed-terrain-types.php
├── composer.json
└── phpunit.xml
```

## Development Workflow

### Running Tests

```bash
# All tests
./vendor/bin/phpunit
# For Docker
docker-compose exec -w /var/www/app php ./vendor/bin/phpunit

# Unit tests only
./vendor/bin/phpunit --testsuite unit

# Integration tests only
./vendor/bin/phpunit --testsuite integration

# Performance tests
./vendor/bin/phpunit --testsuite performance

# End-to-end tests
./vendor/bin/phpunit --testsuite e2e

# Specific test file
./vendor/bin/phpunit tests/Unit/Services/AllocationServiceTest.php
```

### Code Style

Follow PSR-12. Check with:

```bash
./vendor/bin/phpcs --standard=PSR12 src/
```

## Key Endpoints

| Method | Path | Description |
|--------|------|-------------|
| POST | `/api/tournaments` | Create tournament |
| POST | `/api/auth` | Authenticate with token |
| POST | `/api/tournaments/{id}/rounds/{n}/import` | Import BCP pairings |
| POST | `/api/tournaments/{id}/rounds/{n}/generate` | Generate allocations |
| POST | `/api/tournaments/{id}/rounds/{n}/publish` | Publish allocations |
| GET | `/api/public/tournaments/{id}/rounds/{n}` | Public view |

See [contracts/api.yaml](./contracts/api.yaml) for full API documentation.

## Usage Example

### 1. Create a Tournament

```bash
curl -X POST http://localhost:8080/api/tournaments \
  -H "Content-Type: application/json" \
  -d '{
    "name": "Tournament GT January 2026",
    "bcpUrl": "https://www.bestcoastpairings.com/event/t6OOun8POR60",
    "tableCount": 12
  }'
```

Response:
```json
{
  "tournament": {
    "id": 1,
    "name": "Tournament GT January 2026",
    "bcpEventId": "t6OOun8POR60",
    "tableCount": 12
  },
  "adminToken": "Abc123XyzDef456G"
}
```

**Save the admin token!**

### 2. Import Pairings for Round 2

```bash
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/import \
  -H "X-Admin-Token: Abc123XyzDef456G"
```

### 3. Generate Allocations

```bash
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/generate \
  -H "X-Admin-Token: Abc123XyzDef456G"
```

### 4. Publish

```bash
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/publish \
  -H "X-Admin-Token: Abc123XyzDef456G"
```

### 5. View Public Allocations

```bash
curl http://localhost:8080/api/public/tournaments/1/rounds/2
```

## Troubleshooting

### BCP Import Fails

1. Check Chrome is installed: `which chromium-browser` or `which google-chrome`
2. Verify the BCP URL is accessible in a browser
3. Check `config/chrome.php` path
4. Try running with `--no-sandbox` flag if in Docker

### Database Connection Issues

1. Verify MySQL is running: `systemctl status mysql`
2. Check credentials in `config/database.php`
3. Ensure database exists: `mysql -e "SHOW DATABASES LIKE 'tournament_tables';"`

### Permission Issues

```bash
# Ensure web server can write to necessary directories
chmod -R 755 public/
```
