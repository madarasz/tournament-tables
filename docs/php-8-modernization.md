# PHP 7.1 to 8.5 Modernization Plan

## Summary
This migration is mainly blocked by runtime and tooling constraints, not core app syntax.

Resolved blockers (completed):
1. `composer.json` now targets PHP `^8.5` and no longer uses `config.platform.php`.
2. Docker now uses `php:8.5-apache` and `php:8.5-cli`.
3. PHPUnit upgraded from 7.5 to 12.x.
4. `phpunit.xml` migrated to modern schema/source configuration.

## Official Deprecation Review by Version Jump
All deprecation checks below are based on official PHP migration docs.

| Jump | Key deprecations to consider | Repo impact (current scan) |
|---|---|---|
| 7.1 -> 7.2 | `__autoload()` deprecation | Not found |
| 7.2 -> 7.3 | Case-insensitive constants via `define()` deprecation | Not found |
| 7.3 -> 7.4 | Unparenthesized nested ternary, `{}` offset syntax deprecations | Not found |
| 7.4 -> 8.0 | Required-after-optional params, `${}` interpolation forms, static-call behavior tightening | No direct hits found |
| 8.0 -> 8.1 | Passing `null` to non-nullable internal function parameters deprecated | Potential runtime-risk spots; harden |
| 8.1 -> 8.2 | Dynamic properties, partially supported callables, `utf8_encode`/`utf8_decode` deprecations | No direct hits found |
| 8.2 -> 8.3 | `get_class()`/`get_parent_class()` with no args, assert-related INI deprecations | No direct hits found |
| 8.3 -> 8.4 | Implicitly nullable params, optional-before-required signatures, `trigger_error(E_USER_ERROR)` deprecations | No direct hits found in app code |
| 8.4 -> 8.5 | Core/Date/OpenSSL/MySQLi deprecations | No direct hits for used APIs |

## Migration Strategy
Selected strategy:
1. Phased hardening
2. Targeted modernization

### Phase 1: Runtime and dependency baseline
1. Update Composer minimum PHP to `^8.5`, remove `config.platform.php` override.
2. Upgrade PHPUnit to a version compatible with PHP 8.5.
3. Update `phpunit.xml` to current schema and coverage syntax.
4. Update Docker images/services to PHP 8.5 (`apache` and `cli`).
5. Ensure required extensions are explicit in Composer and Docker (including `mbstring`).

### Phase 2: Compatibility hardening
1. Strengthen JSON handling with exception-safe patterns where practical (`JSON_THROW_ON_ERROR`).
2. Add null guards before internal functions that no longer accept null safely.
3. Run migration checks with `E_ALL` and fail on app-level deprecations.

### Phase 3: Targeted modernization (positive code changes)
1. Add typed properties to stable model/value classes where low-risk.
2. Use constructor property promotion and `readonly` where data is immutable.
3. Replace legacy cookie-header compatibility logic (kept for PHP 7.x) with modern cookie options.
4. Apply modern built-ins selectively (`str_contains`, nullsafe usage) where behavior is unchanged.

### Phase 4: Docs and workflow updates
1. Update docs that currently state PHP 7.1-only constraints.
2. Update CI/dev commands to use PHP 8.5.
3. Keep public API contracts and endpoint behavior unchanged during migration.

## Validation and Acceptance Criteria
1. `composer validate` passes.
2. `composer check-platform-reqs` passes on PHP 8.5.
3. Unit + integration + contract tests pass on PHP 8.5.
4. E2E tests pass on updated Docker stack.
5. Full PHP lint passes on PHP 8.5.
6. CI blocks new deprecation warnings from app code.

## Assumptions
1. PHP 7.1 backward compatibility is intentionally dropped.
2. This is a stability-first migration, not a full architectural rewrite.
3. External API behavior remains stable.

## Iteration Results (2026-03-14)

### Phase 1: Runtime and dependency baseline
1. Step 1 complete: Composer runtime bumped to `^8.5`; `platform.php` override removed.
2. Step 2 complete: PHPUnit upgraded to `^12.0` and lockfile refreshed.
3. Step 3 complete: `phpunit.xml` migrated to PHPUnit 12 schema and modern `<source>` config.
4. Step 4 complete: Docker PHP images upgraded to `php:8.5-*`.
5. Step 5 complete: `ext-mbstring` added to Composer and Docker bootstrap commands.

Findings and fixes:
- Initial PHP 8.5 Docker startup failed while compiling `mbstring`; fixed by installing `libonig-dev` before `docker-php-ext-install`.
- PHPUnit 12 migration required test updates (`assertRegExp` -> `assertMatchesRegularExpression`, `setMethods()` -> `onlyMethods()`).

### Phase 2: Compatibility hardening
1. Step 1 complete: JSON handling hardened with `JSON_THROW_ON_ERROR` in API parsing/cookie handling/allocation serialization/front controller request parsing.
2. Step 2 complete: Added null/type guards in request/header/cookie parsing paths.
3. Step 3 complete: Migration checks run with `E_ALL` and app-level deprecations fail via PHPUnit config.

Findings and fixes:
- PHP 8.5 deprecation for locally scoped `$http_response_header` detected; replaced with `http_get_last_response_headers()`.

### Phase 3: Targeted modernization
1. Step 1 complete: Typed properties introduced in stable value objects (`Pairing`, `CostResult`, `AllocationResult`).
2. Step 2 complete: Constructor property promotion + `readonly` applied where immutable.
3. Step 3 complete: Legacy manual cookie-header assembly replaced with modern `setcookie([...])` options.
4. Step 4 complete: Selective built-in modernization (`str_starts_with`, `str_contains`) in routing/validation/conflict parsing paths.

### Phase 4: Docs and workflow updates
1. Step 1 complete: Docs updated away from PHP 7.1-only guidance (`README`, `AGENTS.md`, compatibility notes, planning docs).
2. Step 2 complete: CI/dev workflow updated to PHP 8.5 (`.github/workflows/tests.yml` + Composer scripts).
3. Step 3 complete: API contract behavior preserved; only runtime/tooling and internal hardening changed.

## Validation Results
Acceptance checks executed on migrated stack:
1. `composer validate`: pass.
2. `composer check-platform-reqs` on PHP 8.5 container: pass.
3. Unit + integration + contract tests on PHP 8.5: pass.
4. Playwright E2E tests on updated layered Docker stack: pass (`7/7`).
5. Full PHP lint on PHP 8.5 runtime: pass.
6. CI deprecation gate: configured via `failOnDeprecation="true"` + `error_reporting=-1` in PHPUnit config, enforced in updated GitHub Actions workflow.

## Official Sources
- https://www.php.net/downloads.php
- https://www.php.net/manual/en/migration72.deprecated.php
- https://www.php.net/manual/en/migration73.deprecated.php
- https://www.php.net/manual/en/migration74.deprecated.php
- https://www.php.net/manual/en/migration80.deprecated.php
- https://www.php.net/manual/en/migration81.deprecated.php
- https://www.php.net/manual/en/migration82.deprecated.php
- https://www.php.net/manual/en/migration83.deprecated.php
- https://www.php.net/manual/en/migration84.deprecated.php
- https://www.php.net/manual/en/migration85.deprecated.php
- https://phpunit.de/supported-versions.html
