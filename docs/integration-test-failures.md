# Integration Test Failures Analysis

This document describes the integration test failures discovered when running the test suite with `--process-isolation`. Without process isolation, these tests appear as "Database not available" skips due to Connection singleton state pollution between tests.

## Fix Status (Updated: 2026-01-14)

| Test Suite | Original Issue | Status | Fix Applied |
|------------|----------------|--------|-------------|
| PublicViewTest | Validation logic order | **FIXED** | Changed round number from 99 to 15 |
| PublishTest | Model property naming mismatch | **FIXED** | Updated to camelCase + correct methods |
| AllocationPerformanceTest | Column size constraint + STDERR | **FIXED** | Fixed token length + null terrain + VERBOSE_PERF conditional |
| PageLoadPerformanceTest | Foreign key + duplicate token + STDERR | **FIXED** | Unique tokens + VERBOSE_PERF conditional |
| TournamentWorkflowTest (E2E) | Test data + URL validation + missing players | **FIXED** | Unique IDs + no underscores + createRound1WithPairings |

## Final Test Results (2026-01-14)

**Running with `--process-isolation`:**

- **Unit Tests:** ✅ 82/82 pass (100%)
- **Integration Tests:** ✅ 78/79 pass (1 intentional skip)
- **Performance Tests:** ✅ 9/9 pass (100%)
- **E2E Tests:** ⚠️ 2/3 pass (1 logic failure - see Phase 5)

**Overall: 171/173 tests passing (98.8%)**

### Remaining Issue

- `testWorkflowEdgeCases`: Conflict detection logic needs investigation (Phase 5 in plan below)

---

## Why Tests Are Skipped

### Primary Root Cause: MySQL Not Accessible

The database configuration (`config/database.php`) points to hostname `mysql`:

```php
return [
    'host' => 'mysql',  // Docker service name
    'database' => 'tournament_tables',
    'username' => 'root',
    'password' => 'root',
];
```

When running tests **outside Docker**, the hostname `mysql` cannot be resolved, causing:

```
PDOException: SQLSTATE[HY000] [2002] php_network_getaddresses:
getaddrinfo for mysql failed: nodename nor servname provided
```

### Connection Singleton Behavior

The `Connection` class (`src/Database/Connection.php`) uses a singleton pattern:

```php
class Connection {
    private static $instance = null;

    public static function getInstance(): PDO {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }
}
```

**Issue:** Once a connection attempt fails, the exception is thrown but no instance is cached. Each test retries the connection, fails, and skips.

### Defensive Skip Pattern

Integration tests use `isDatabaseAvailable()` to skip gracefully:

```php
protected function setUp(): void {
    if (!$this->isDatabaseAvailable()) {
        $this->markTestSkipped('Database not available');
    }
}

private function isDatabaseAvailable(): bool {
    try {
        Connection::getInstance();
        return true;
    } catch (\Exception $e) {
        return false;
    }
}
```

This pattern exists in:
- `AllocationGenerationTest.php`
- `TournamentCreationTest.php`
- `AuthenticationTest.php`
- `AllocationEditTest.php`
- `PublicViewTest.php`
- `PublishTest.php`
- And all Performance/E2E tests

---

## How to Run Integration Tests

### Option 1: Docker (Recommended)

```bash
# Start MySQL and PHP services
docker-compose up -d

# Wait for MySQL to initialize
sleep 10

# Run database migrations
docker-compose exec php php bin/migrate.php

# Run all tests
docker-compose exec php vendor/bin/phpunit

# Run specific test suites
docker-compose exec php vendor/bin/phpunit --testsuite integration
docker-compose exec php vendor/bin/phpunit --testsuite performance
docker-compose exec php vendor/bin/phpunit --testsuite e2e

# Run with process isolation (recommended for integration tests)
docker-compose exec php vendor/bin/phpunit --testsuite integration --process-isolation
```

### Option 2: Local MySQL

1. **Install MySQL locally** (via Homebrew, MAMP, etc.)

2. **Create database:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE tournament_tables CHARACTER SET utf8mb4;"
   ```

3. **Update config/database.php:**
   ```php
   return [
       'host' => '127.0.0.1',  // or 'localhost'
       'database' => 'tournament_tables',
       'username' => 'root',
       'password' => 'your_password',
       'charset' => 'utf8mb4',
   ];
   ```

4. **Run migrations:**
   ```bash
   php bin/migrate.php
   ```

5. **Run tests:**
   ```bash
   vendor/bin/phpunit --testsuite integration
   ```

### Option 3: Environment-Based Configuration

Create a test-specific config by checking environment:

```php
// config/database.php
$host = getenv('DB_HOST') ?: 'mysql';
$database = getenv('DB_DATABASE') ?: 'tournament_tables';
$username = getenv('DB_USERNAME') ?: 'root';
$password = getenv('DB_PASSWORD') ?: 'root';

return [
    'host' => $host,
    'database' => $database,
    'username' => $username,
    'password' => $password,
    'charset' => 'utf8mb4',
];
```

Then run with environment variables:
```bash
DB_HOST=127.0.0.1 DB_PASSWORD=mypassword vendor/bin/phpunit --testsuite integration
```

---

## Plan to Solve Remaining Issues

### Phase 1: Environment Setup (Required First)

1. **Start Docker environment:**
   ```bash
   docker-compose up -d
   docker-compose exec php php bin/migrate.php
   ```

2. **Seed terrain types** (if not already in migrations):
   ```sql
   INSERT INTO terrain_types (id, name, sort_order) VALUES
   (1, 'Desert', 1),
   (2, 'Forest', 2),
   (3, 'Urban', 3),
   (4, 'Wasteland', 4),
   (5, 'Ice', 5),
   (6, 'Jungle', 6),
   (7, 'Mountain', 7),
   (8, 'Swamp', 8);
   ```

### Phase 2: Run and Verify Fixed Tests

```bash
# Run integration tests with process isolation
docker-compose exec php vendor/bin/phpunit --testsuite integration --process-isolation

# Expected results after fixes:
# - PublicViewTest: All pass
# - PublishTest: All pass
# - AllocationEditTest: All pass
# - AllocationGenerationTest: All pass
# - AuthenticationTest: All pass
# - DatabaseConnectionTest: All pass
# - TournamentCreationTest: All pass
```

### Phase 3: Run Performance Tests

```bash
docker-compose exec php vendor/bin/phpunit --testsuite performance --process-isolation

# Expected results:
# - AllocationPerformanceTest: All pass (token length fixed, null terrain)
# - PageLoadPerformanceTest: All pass (null terrain_type_id)
```

### Phase 4: Run E2E Tests

```bash
docker-compose exec php vendor/bin/phpunit --testsuite e2e --process-isolation

# Expected results:
# - TournamentWorkflowTest::testCompleteWorkflowUnderFiveMinutes: Pass
# - TournamentWorkflowTest::testMultiRoundWorkflow: Pass
# - TournamentWorkflowTest::testWorkflowEdgeCases: May need investigation
```

### Phase 5: Investigate testWorkflowEdgeCases (If Still Failing)

The `testWorkflowEdgeCases` test expects conflicts when:
- 4 tables available
- 8 players (4 pairings per round)
- Round 2 should have unavoidable table reuse

**Investigation steps:**

1. Check `AllocationService::generateAllocations()` conflict detection
2. Verify `AllocationResult::$conflicts` is populated
3. Check if TournamentHistory correctly tracks player-table assignments
4. May need to adjust test to create a truly unavoidable conflict scenario

---

## Architecture Improvements (Future)

### 1. Base Test Case Class

Create `tests/Integration/IntegrationTestCase.php`:

```php
abstract class IntegrationTestCase extends TestCase
{
    protected function setUp(): void
    {
        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }
        $this->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->rollbackTransaction();
    }

    protected function isDatabaseAvailable(): bool { /* ... */ }
    protected function beginTransaction(): void { /* ... */ }
    protected function rollbackTransaction(): void { /* ... */ }
}
```

### 2. Transaction-Based Isolation

Use database transactions instead of DELETE cleanup:

```php
protected function setUp(): void
{
    Connection::beginTransaction();
}

protected function tearDown(): void
{
    Connection::rollBack();
}
```

### 3. PHPUnit Configuration Updates

Add to `phpunit.xml`:

```xml
<php>
    <env name="APP_ENV" value="testing"/>
    <env name="DB_HOST" value="mysql"/>
    <env name="DB_DATABASE" value="tournament_tables_test"/>
</php>
```

### 4. CI Integration Test Job

Add to `.github/workflows/tests.yml`:

```yaml
integration-tests:
  runs-on: ubuntu-latest
  services:
    mysql:
      image: mysql:8.0
      env:
        MYSQL_ROOT_PASSWORD: root
        MYSQL_DATABASE: tournament_tables
      ports:
        - 3306:3306
      options: --health-cmd="mysqladmin ping" --health-interval=10s
  steps:
    - uses: actions/checkout@v4
    - name: Setup PHP
      uses: shivammathur/setup-php@v2
      with:
        php-version: '7.4'
    - run: composer install
    - run: php bin/migrate.php
      env:
        DB_HOST: 127.0.0.1
    - run: vendor/bin/phpunit --testsuite integration
      env:
        DB_HOST: 127.0.0.1
```

---

## Original Analysis (Historical Reference)

<details>
<summary>Click to expand original failure analysis</summary>

### 1. PublicViewTest Failures

#### 1.1 testNonExistentRoundReturns404

**File:** `tests/Integration/PublicViewTest.php:243`

**Error:**
```
Failed asserting that two strings are equal.
Expected: 'not_found'
Actual: 'validation_error'
```

**Root Cause:** Test used round number `99` which fails validation (1-20 range) before existence check.

**Fix Applied:** Changed to round number `15`.

---

### 2. PublishTest Failures

#### 2.1 All Tests - Duplicate Entry Error

**Error:**
```
PDOException: SQLSTATE[23000]: Integrity constraint violation:
1062 Duplicate entry '' for key 'tournaments.bcp_event_id'
```

**Root Cause:** Test used snake_case properties (`bcp_event_id`) but model uses camelCase (`bcpEventId`). PHP silently creates new properties, leaving actual properties empty.

**Fix Applied:** Updated to use camelCase properties and model constructors. Also fixed:
- `Round::publish($id)` → `$round->publish()`
- `Round::getById($id)` → `Round::find($id)`
- `Table::getByTournament()` → `Table::findByTournament()`
- `Player::getByTournament()` → `Player::findByTournament()`
- `Allocation::getByRound()` → `Allocation::findByRound()`

---

### 3. AllocationPerformanceTest Failures

#### 3.1 All Tests - Data Too Long

**Error:**
```
PDOException: SQLSTATE[22001]: String data, right truncated:
1406 Data too long for column 'admin_token' at row 1
```

**Root Cause:** Token was `'PerfToken' + 8 chars = 17 chars`, exceeding 16-char column limit.

**Fix Applied:** Changed to `bin2hex(random_bytes(8))` (16 chars). Also set `terrain_type_id` to null.

---

### 4. PageLoadPerformanceTest Failures

#### 4.1 All Tests - Foreign Key Constraint

**Error:**
```
PDOException: SQLSTATE[23000]: Integrity constraint violation:
1452 Cannot add or update a child row: a foreign key constraint fails
```

**Root Cause:** Test assigned `terrain_type_id` values 1-8 that don't exist in `terrain_types` table.

**Fix Applied:** Set `terrain_type_id` to null for all test tables.

---

### 5. TournamentWorkflowTest (E2E) Failures

#### 5.1 testCompleteWorkflowUnderFiveMinutes - Unexpected Output

**Root Cause:** `fwrite(STDERR, ...)` output captured by PHPUnit with process isolation.

**Fix Applied:** Wrapped in `if (getenv('VERBOSE_E2E'))` conditional.

#### 5.2 testMultiRoundWorkflow - Duplicate Tournament

**Root Cause:** BCP event ID used `time()` which could duplicate within same second.

**Fix Applied:** Changed to `microtime(true) + random_bytes(4)` for unique IDs.

#### 5.3 testWorkflowEdgeCases - Empty Conflicts

**Status:** May still need investigation. Conflict detection depends on allocation algorithm correctly identifying unavoidable table reuse.

</details>
