     │ Plan: Rename kt-tables to tournament-tables                                                                                                                 │
     │                                                                                                                                                             │
     │ Summary                                                                                                                                                     │
     │                                                                                                                                                             │
     │ Rename the project from "kt-tables" (Kill Team specific) to "tournament-tables" (generic for any BCP tournament) while keeping the database name unchanged. │
     │                                                                                                                                                             │
     │ Changes Overview                                                                                                                                            │
     │ ┌──────────────────┬─────────────────────┬─────────────────────────────────────┐                                                                            │
     │ │    Component     │       Current       │                 New                 │                                                                            │
     │ ├──────────────────┼─────────────────────┼─────────────────────────────────────┤                                                                            │
     │ │ PHP Namespace    │ KTTables\           │ TournamentTables\                   │                                                                            │
     │ ├──────────────────┼─────────────────────┼─────────────────────────────────────┤                                                                            │
     │ │ Composer package │ kt-tables/kt-tables │ tournament-tables/tournament-tables │                                                                            │
     │ ├──────────────────┼─────────────────────┼─────────────────────────────────────┤                                                                            │
     │ │ User-facing name │ "Kill Team Tables"  │ "Tournament Tables"                 │                                                                            │
     │ ├──────────────────┼─────────────────────┼─────────────────────────────────────┤                                                                            │
     │ │ Database name    │ kt_tables           │ kt_tables (unchanged)               │                                                                            │
     │ └──────────────────┴─────────────────────┴─────────────────────────────────────┘                                                                            │
     │ ---                                                                                                                                                         │
     │ Implementation Steps                                                                                                                                        │
     │                                                                                                                                                             │
     │ Step 1: Update composer.json                                                                                                                                │
     │                                                                                                                                                             │
     │ File: composer.json                                                                                                                                         │
     │                                                                                                                                                             │
     │ - Change "name": "kt-tables/kt-tables" → "tournament-tables/tournament-tables"                                                                              │
     │ - Change description from "Kill Team tournament..." → "Tournament table allocation system"                                                                  │
     │ - Update namespace autoload mapping: "KTTables\\" → "TournamentTables\\"                                                                                    │
     │ - Update test namespace: "KTTables\\Tests\\" → "TournamentTables\\Tests\\"                                                                                  │
     │                                                                                                                                                             │
     │ Step 2: Update PHP namespaces in source files (35 files)                                                                                                    │
     │                                                                                                                                                             │
     │ Controllers (9 files):                                                                                                                                      │
     │ - src/Controllers/AllocationController.php                                                                                                                  │
     │ - src/Controllers/AuthController.php                                                                                                                        │
     │ - src/Controllers/BaseController.php                                                                                                                        │
     │ - src/Controllers/PublicController.php                                                                                                                      │
     │ - src/Controllers/RoundController.php                                                                                                                       │
     │ - src/Controllers/TerrainTypeController.php                                                                                                                 │
     │ - src/Controllers/TournamentController.php                                                                                                                  │
     │ - src/Controllers/ViewController.php                                                                                                                        │
     │ - public/index.php                                                                                                                                          │
     │                                                                                                                                                             │
     │ Models (6 files):                                                                                                                                           │
     │ - src/Models/Allocation.php                                                                                                                                 │
     │ - src/Models/Player.php                                                                                                                                     │
     │ - src/Models/Round.php                                                                                                                                      │
     │ - src/Models/Table.php                                                                                                                                      │
     │ - src/Models/TerrainType.php                                                                                                                                │
     │ - src/Models/Tournament.php                                                                                                                                 │
     │                                                                                                                                                             │
     │ Services (12 files):                                                                                                                                        │
     │ - src/Services/AllocationEditService.php                                                                                                                    │
     │ - src/Services/AllocationResult.php                                                                                                                         │
     │ - src/Services/AllocationService.php                                                                                                                        │
     │ - src/Services/AuthService.php                                                                                                                              │
     │ - src/Services/BCPScraperService.php                                                                                                                        │
     │ - src/Services/CostCalculator.php                                                                                                                           │
     │ - src/Services/CostResult.php                                                                                                                               │
     │ - src/Services/CsrfService.php                                                                                                                              │
     │ - src/Services/Pairing.php                                                                                                                                  │
     │ - src/Services/TokenGenerator.php                                                                                                                           │
     │ - src/Services/TournamentHistory.php                                                                                                                        │
     │ - src/Services/TournamentService.php                                                                                                                        │
     │                                                                                                                                                             │
     │ Middleware (2 files):                                                                                                                                       │
     │ - src/Middleware/AdminAuthMiddleware.php                                                                                                                    │
     │ - src/Middleware/CsrfMiddleware.php                                                                                                                         │
     │                                                                                                                                                             │
     │ Database (1 file):                                                                                                                                          │
     │ - src/Database/Connection.php                                                                                                                               │
     │                                                                                                                                                             │
     │ Views (2 files with namespace references):                                                                                                                  │
     │ - src/Views/layout.php                                                                                                                                      │
     │ - src/Views/round/manage.php                                                                                                                                │
     │                                                                                                                                                             │
     │ Binary scripts (2 files):                                                                                                                                   │
     │ - bin/migrate.php                                                                                                                                           │
     │ - bin/seed-terrain-types.php                                                                                                                                │
     │                                                                                                                                                             │
     │ Step 3: Update PHP namespaces in test files (16 files)                                                                                                      │
     │                                                                                                                                                             │
     │ All files under tests/ directory:                                                                                                                           │
     │ - Change namespace KTTables\Tests\... → namespace TournamentTables\Tests\...                                                                                │
     │ - Change use KTTables\... → use TournamentTables\...                                                                                                        │
     │                                                                                                                                                             │
     │ Step 4: Update user-facing strings in views                                                                                                                 │
     │                                                                                                                                                             │
     │ Files to update:                                                                                                                                            │
     │ - src/Views/layout.php - title, brand links, footer                                                                                                         │
     │ - src/Views/home.php - headings, descriptions, title                                                                                                        │
     │ - src/Views/public/tournament.php - title, footer                                                                                                           │
     │ - src/Views/public/round.php - title, footer                                                                                                                │
     │ - src/Views/round/manage.php - breadcrumb, footer                                                                                                           │
     │ - src/Views/tournament/create.php - description, placeholder text                                                                                           │
     │ - src/Controllers/ViewController.php - 404 page title                                                                                                       │
     │                                                                                                                                                             │
     │ String replacements:                                                                                                                                        │
     │ - Kill Team Tables → Tournament Tables                                                                                                                      │
     │ - Kill Team tournament → tournament (where appropriate)                                                                                                     │
     │ - Kill Team GT January 2026 → My Tournament January 2026 (example placeholder)                                                                              │
     │                                                                                                                                                             │
     │ Step 5: Update documentation files                                                                                                                          │
     │                                                                                                                                                             │
     │ Files to update:                                                                                                                                            │
     │ - CLAUDE.md - update header                                                                                                                                 │
     │ - initial_specification.md - project name references                                                                                                        │
     │ - specs/001-table-allocation/spec.md - feature spec title, domain references                                                                                │
     │ - specs/001-table-allocation/plan.md - implementation plan title                                                                                            │
     │ - specs/001-table-allocation/data-model.md - data model title                                                                                               │
     │ - specs/001-table-allocation/research.md - research title                                                                                                   │
     │ - specs/001-table-allocation/quickstart.md - quickstart title, example names                                                                                │
     │ - specs/001-table-allocation/tasks.md - tasks title                                                                                                         │
     │ - specs/001-table-allocation/checklists/requirements.md - checklist title                                                                                   │
     │ - specs/001-table-allocation/contracts/api.yaml - API title, example values                                                                                 │
     │ - docs/routes.md - routes documentation title                                                                                                               │
     │ - .specify/memory/constitution.md - constitution header                                                                                                     │
     │                                                                                                                                                             │
     │ Step 6: Update comments in code                                                                                                                             │
     │                                                                                                                                                             │
     │ Files with "Kill Team" in comments:                                                                                                                         │
     │ - src/Models/Tournament.php - class docblock                                                                                                                │
     │ - bin/migrate.php - script header comment                                                                                                                   │
     │ - bin/seed-terrain-types.php - script header comment                                                                                                        │
     │ - public/index.php - front controller comment                                                                                                               │
     │                                                                                                                                                             │
     │ ---                                                                                                                                                         │
     │ Change database name, databe will be recreated                                                                                                                                   │
     │                                                                                                                                                             │
     │ - config/database.example.php - update kt_tables database name                                                                                               │
     │ - docker-compose.yml - update MYSQL_DATABASE: kt_tables                                                                                                      │                                                                                   │
     │                                                                                                                                                             │
     │ ---                                                                                                                                                         │
     │ Verification                                                                                                                                                │
     │                                                                                                                                                             │
     │ 1. Run composer dump-autoload - verify namespace autoloading works                                                                                          │
     │ 2. Run tests - php vendor/bin/phpunit tests/ - ensure all tests pass                                                                                        │
     │ 3. Start the application - verify pages load without namespace errors                                                                                       │
     │ 4. Check views - confirm "Tournament Tables" appears in UI instead of "Kill Team Tables"                                                                    │
     │ 5. Search for remnants - grep -r "Kill Team" src/ should return no results                                                                                  │
     │ 6. Search for old namespace - grep -r "KTTables" src/ tests/ should return no results                                                                       │
     │                                                                                                                                                             │
     │ ---                                                                                                                                                         │
     │ Order of Operations                                                                                                                                         │
     │                                                                                                                                                             │
     │ Execute in this order to minimize intermediate broken state:                                                                                                │
     │                                                                                                                                                             │
     │ 1. Update composer.json (namespace mappings)                                                                                                                │
     │ 2. Update all PHP files (namespace declarations and use statements)                                                                                         │
     │ 3. Run composer dump-autoload                                                                                                                               │
     │ 4. Update view strings and documentation                                                                                                                    │
     │ 5. Run tests to verify