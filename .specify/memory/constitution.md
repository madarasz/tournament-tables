<!--
=============================================================================
SYNC IMPACT REPORT
=============================================================================
Version Change: 1.0.0 → 1.1.0

Modified Principles: None

Added Sections:
  - Technical Standards > JavaScript Standards (new subsection)

Removed Sections: None

Templates Requiring Updates:
  - .specify/templates/plan-template.md: ✅ Compatible (no changes needed)
  - .specify/templates/spec-template.md: ✅ Compatible (no changes needed)
  - .specify/templates/tasks-template.md: ✅ Compatible (no changes needed)

Follow-up TODOs: None
=============================================================================
-->

# Tournament Tables Constitution

## Core Principles

### I. Single Purpose Focus

The application MUST solve exactly one problem: generating table allocations that ensure
tournament players experience different tables each round. All features MUST directly
support this core function. Scope creep into tournament management, scoring, or pairing
(handled by BCP) is explicitly prohibited.

**Rationale**: Best Coast Pairings already handles tournament logistics. This app fills
a specific gap without duplicating existing functionality.

### II. Test-Driven Development (NON-NEGOTIABLE)

All feature implementation MUST follow the TDD cycle:
1. Write failing test(s) that define expected behavior
2. Obtain user approval of test cases
3. Verify tests fail (red phase)
4. Implement minimum code to pass tests (green phase)
5. Refactor while keeping tests passing

No production code may be written without a corresponding failing test first. Test
coverage MUST include unit tests for allocation logic and integration tests for BCP
data import.

**Rationale**: Table allocation algorithms have deterministic expected outputs. TDD
ensures correctness and prevents regression when optimizing allocation strategies.

### III. Data Integrity First

The system MUST preserve tournament data integrity at all times:
- All BCP-imported data MUST be validated before storage
- Table allocation operations MUST be atomic (complete or rollback)
- Historical allocations MUST be immutable once a round begins
- Database operations MUST use transactions for multi-step changes

**Rationale**: Tournament organizers rely on accurate data. A corrupted allocation
mid-tournament would disrupt the event with no recovery path.

### IV. Simplicity Over Flexibility

Implementation MUST prefer simple, direct solutions:
- No configuration options unless explicitly required by a user story
- No abstraction layers until proven necessary by code duplication
- Plain PHP with minimal framework overhead (compatible with PHP 7.1)
- MySQL queries should be straightforward; avoid ORM complexity

**Rationale**: This is a focused tool, not a platform. Over-engineering increases
maintenance burden without proportional benefit for a single-purpose application.

### V. Transparent Algorithm Behavior

The table allocation algorithm MUST be explainable and auditable:
- Allocation decisions MUST be reproducible given the same inputs
- The system MUST log why each player was assigned to each table
- When perfect allocation is impossible, the system MUST report conflicts clearly
- Organizers MUST be able to manually override allocations with audit trail

**Rationale**: Tournament organizers need to justify table assignments to players
if questions arise. Black-box allocation erodes trust.

## Technical Standards

**Runtime Environment**:
- PHP 7.1 (MUST maintain compatibility; no features from PHP 7.4+)
- MySQL database for persistent storage
- Web-based interface accessible from tournament venue devices

**Code Standards**:
- PSR-12 coding style for PHP
- All database queries MUST use prepared statements (no SQL injection vectors)
- Input validation MUST occur at system boundaries (form submissions, API imports)
- Error messages MUST be user-friendly; technical details logged server-side only

**JavaScript Standards**:
- Client-side code MUST escape all dynamic content before DOM insertion to prevent XSS
  (use `escapeHtml()` for HTML context, `encodeURIComponent()` for URL parameters)
- Shared JavaScript utilities MUST be placed in `public/js/utils.js` and loaded via layout
- Inline `<script>` blocks in views MUST NOT duplicate functions available in shared utilities
- ES5 syntax MUST be used for broad browser compatibility (no arrow functions, const/let, etc.)

**Testing Requirements**:
- PHPUnit for unit and integration tests
- Test database MUST be isolated from production data
- CI pipeline MUST pass before any merge to main branch

## Development Workflow

**Feature Development**:
1. Specification created via `/speckit.specify`
2. Plan created via `/speckit.plan` with constitution compliance check
3. Tasks generated via `/speckit.tasks`
4. Implementation follows TDD cycle per task
5. Code review verifies constitution compliance

**Code Review Checklist**:
- Does the change stay within single-purpose scope?
- Are there failing tests that preceded the implementation?
- Is data integrity maintained (transactions, validation)?
- Is the solution as simple as possible for the requirement?
- Is algorithm behavior documented and reproducible?

**Branching Strategy**:
- `main` branch MUST always be deployable
- Feature branches named `###-feature-name` (issue number prefix)
- All changes via pull request with passing tests

## Governance

This constitution supersedes all other development practices for the Tournament Tables
project. Compliance is mandatory for all contributions.

**Amendment Process**:
1. Propose amendment with rationale via pull request to constitution
2. Document impact on existing code and templates
3. Obtain project owner approval
4. Update version number per semantic versioning:
   - MAJOR: Principle removal or fundamental redefinition
   - MINOR: New principle or significant guidance expansion
   - PATCH: Clarification or wording improvements
5. Propagate changes to affected templates and documentation

**Compliance Review**:
- Every pull request MUST include constitution compliance statement
- Violations MUST be documented in Complexity Tracking if justified
- Unjustified violations block merge

**Version**: 1.1.0 | **Ratified**: 2026-01-13 | **Last Amended**: 2026-01-14
