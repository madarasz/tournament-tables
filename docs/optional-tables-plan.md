# Optional Tables Feature Plan

## Status
- Plan updated: March 6, 2026
- Implementation status: Not started in this plan document

## Objective
Introduce a new table property `optional` (boolean, default `false`) to support overflow/manual-only tables.

## Agreed Constraints
1. `optional` tables are **never** used by automatic assignment.
2. Automatic assignment should **not** fail with an error when assignment cannot be fully completed.
3. Admin can manually assign pairings to optional tables.
4. UI guard (Tables tab): optional toggle appears **only** for tables above the minimum required for current player count.
5. Backend does **not** enforce that UI guard (backend may accept optional on any table).
6. E2E coverage: **one** happy-path scenario only (no edge-case matrix).

## Functional Requirements

### Data Model
- Add `optional` column to `tables`:
  - Type: `BOOLEAN`
  - Default: `FALSE`
  - Non-nullable
- Include migration upgrade logic for existing DBs.

### API and Serialization
- Include `isOptional` in table API responses.
- Accept `optional` in table config update payloads (`PUT /api/tournaments/{id}/tables`).

### Tables Tab (Admin)
- Add Optional control per table row.
- Apply UI guard:
  - Let `minimumTables = floor(playerCount / 2)`.
  - Show optional control only when `tableNumber > minimumTables`.
  - For guarded rows, show non-interactive “Required” indicator instead of toggle.
- Save `terrainTypeId` and `optional` together via existing save action.

### Automatic Assignment Behavior
- In automatic flows (import placeholders + generate), use only non-hidden, non-optional tables.
- If eligible tables run out, keep pairings persisted as unassigned table allocations (manual follow-up possible), not hard failure.

### Manual Assignment Behavior
- Keep manual table change endpoints permissive so optional tables can be selected.
- Round edit UI should allow selecting optional tables as assignment targets.

## Test Plan

### E2E (Single Happy Path)
File target: `tests/E2E/specs/tournament-terrain.spec.ts`

Scenario:
1. Create tournament with 5 tables.
2. Confirm imported player count implies minimum 4 tables.
3. Open Tables tab.
4. Verify optional toggle exists only for table 5 (table 1-4 are required/no toggle).
5. Enable optional for table 5 and save.
6. Reload and verify persistence of the optional state.

Notes:
- Keep this as one focused scenario.
- Do not add edge-case permutations in this story.

## Implementation Tasks
1. Schema/migration updates for `tables.optional`.
2. Table model updates (`fromRow`, constructor, insert/update, serialization).
3. Tournament table update service accepts/saves `optional`.
4. Tables tab UI adds guarded optional toggle and payload wiring.
5. Auto-allocation/import paths filter out optional tables.
6. Preserve non-error behavior when auto-assignment capacity is insufficient.
7. Add one happy-path E2E scenario.

## Acceptance Criteria
- `optional` persisted and returned by API.
- Tables tab shows optional toggle only for tables exceeding minimum required.
- Automatic assignment never uses optional tables.
- No hard error solely because optional filtering reduces automatic capacity.
- Manual assignment to optional tables remains possible.
- One happy-path E2E test covers optional guard + persistence.

## Out of Scope
- Backend enforcement of UI guard rule.
- Additional edge-case E2E scenarios.
- UX redesign beyond the optional control and required indicator.
