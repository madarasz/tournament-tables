# Development Guide

## Creating a New Controller
1. Create file in `src/Controllers/`
2. Add routes in `public/index.php`
3. Apply middleware (AdminAuth, CSRF) as needed
4. Delegate business logic to services
5. Return JSON responses for API routes
6. Use views for HTML routes

## Creating a New Service
1. Create file in `src/Services/`
2. Use constructor injection for dependencies (Connection, other services)
3. Keep methods stateless where possible
4. Add unit tests in `tests/Unit/Services/`
5. Add integration tests if DB interaction needed

## Adding Database Migrations
1. Edit `bin/migrate.php`
2. Add new tables or ALTER statements
3. Test locally: `php bin/migrate.php`
4. Test in Docker: `docker-compose exec php php bin/migrate.php`
5. Update data model documentation if schema changes

## Writing E2E Tests
1. Create spec file in `tests/E2E/specs/`
2. Follow guidelines in `docs/e2e-testing-guidelines.md`
3. Use test helpers from `tests/E2E/helpers/`
4. Focus on critical user flows (test pyramid)
5. Use data-testid selectors where possible

## Local Development (without Docker)

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

**Note**: The `public/index.php` argument is required for URL routing.
