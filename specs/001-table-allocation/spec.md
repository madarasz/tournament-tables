# Feature Specification: Tournament Tables - Tournament Table Allocation

**Feature Branch**: `001-table-allocation`
**Created**: 2026-01-13
**Status**: Draft
**Input**: Tournament table allocation system that generates table assignments ensuring players experience different tables each round, integrated with Best Coast Pairings (BCP) for pairing data.

## Clarifications

### Session 2026-01-13

- Q: How is BCP data extracted (API, server-rendered HTML, JS-rendered, manual)? → A: BCP provides a public REST API at `newprod-api.bestcoastpairings.com` for pairing data extraction.
- Q: What is the tournament data retention policy? → A: Keep indefinitely; no automatic deletion.
- Q: How are BCP data changes handled after initial import? → A: Manual refresh button overwrites existing pairing data for that round.
- Q: Can published allocations be edited? → A: Yes, editable after publish with no special tracking required.

## User Scenarios & Testing *(mandatory)*

### User Story 1 - Generate Table Allocation for Round (Priority: P1)

As a tournament organizer, I want to generate table allocations for a tournament round based on BCP pairings, so that players are assigned to tables they haven't played on before.

**Why this priority**: This is the core value proposition. Without table allocation generation, the app provides no value. An organizer needs to get table assignments before any editing or publishing can happen.

**Independent Test**: Can be fully tested by creating a tournament, importing pairings for round 2+, and verifying that generated allocations avoid previously-used tables for each player.

**Acceptance Scenarios**:

1. **Given** a tournament with 8 tables and round 1 completed, **When** the organizer generates allocations for round 2 with 8 pairings from BCP, **Then** each pairing receives a table number where neither player has played before.

2. **Given** a tournament where perfect allocation is impossible (all tables used by some player), **When** the organizer generates allocations, **Then** the system assigns the best possible allocation and clearly indicates which players have conflicts.

3. **Given** round 1 of a tournament, **When** the organizer generates allocations, **Then** the system uses BCP's original table assignments as-is.

4. **Given** a tournament with terrain types assigned to tables, **When** the organizer generates allocations, **Then** players are preferentially assigned to terrain types they haven't experienced.

5. **Given** pairings with player scores, **When** the organizer generates allocations, **Then** higher-scoring players are assigned to lower table numbers (table 1 = top players).

---

### User Story 2 - Create and Configure Tournament (Priority: P2)

As a tournament organizer, I want to create a tournament with BCP integration and table configuration, so that the system knows where to fetch pairings and how many tables are available.

**Why this priority**: Tournament creation is required before any allocation can happen, but it's a one-time setup activity. Once created, the organizer spends most time on allocation activities (P1).

**Independent Test**: Can be fully tested by creating a tournament with a name, BCP URL, and table count, then verifying the tournament persists and displays correctly.

**Acceptance Scenarios**:

1. **Given** the organizer is on the home page, **When** they submit a new tournament with a name, BCP event URL (format: `https://www.bestcoastpairings.com/event/{eventId}`), and table count, **Then** the tournament is created and a 16-character admin token is displayed.

2. **Given** a newly created tournament, **When** the admin token is displayed, **Then** the token is also stored as a browser cookie with 30-day retention.

3. **Given** an organizer creating a tournament, **When** they optionally assign terrain types to tables, **Then** each table's terrain type is saved for use in allocation priority.

4. **Given** an invalid BCP URL (not matching `bestcoastpairings.com/event/{id}` pattern), **When** the organizer attempts to create a tournament, **Then** a clear error message explains the URL must be a valid BCP event page.

---

### User Story 3 - Edit and Publish Table Allocation (Priority: P3)

As a tournament organizer, I want to manually adjust table allocations and publish them for players to view, so that I can handle special cases and make assignments visible.

**Why this priority**: Editing is needed when automatic allocation requires adjustment. Publishing makes assignments useful to players. Both depend on having allocations generated first (P1).

**Independent Test**: Can be fully tested by generating an allocation, swapping two tables, observing conflict highlighting, and publishing to verify public visibility.

**Acceptance Scenarios**:

1. **Given** a generated allocation, **When** the organizer changes a pairing's table number, **Then** the change is saved and conflicts are immediately highlighted.

2. **Given** two pairings at tables 3 and 7, **When** the organizer uses the swap function, **Then** pairing A moves to table 7 and pairing B moves to table 3.

3. **Given** an allocation where a player is assigned to a previously-used table, **When** viewing the allocation, **Then** the conflict is visually highlighted with explanation.

4. **Given** a finalized allocation, **When** the organizer clicks publish, **Then** the round's allocation becomes visible to public users.

5. **Given** a published allocation, **When** the organizer makes further edits, **Then** a warning indicates changes will update the public view.

---

### User Story 4 - View Published Allocations (Priority: P4)

As a tournament player (public user), I want to view published table allocations without logging in, so that I know where to play my next game.

**Why this priority**: Player-facing view depends on organizers having created, generated, and published allocations. It's the final step in the value chain.

**Independent Test**: Can be fully tested by publishing an allocation and accessing the public URL without authentication, verifying table assignments display correctly.

**Acceptance Scenarios**:

1. **Given** a published allocation for round 3, **When** a player visits the tournament's public page, **Then** they see all table assignments for round 3.

2. **Given** a tournament with rounds 1-3 published, **When** a player views the page, **Then** they can see allocations for all published rounds.

3. **Given** round 4 exists but is not published, **When** a player views the page, **Then** round 4 allocations are not visible.

---

### User Story 5 - Admin Authentication (Priority: P5)

As an organizer returning to manage my tournament, I want to authenticate using my admin token, so that I can continue managing the tournament.

**Why this priority**: Authentication is needed for returning organizers but is secondary to core functionality. Initial access is handled by cookie retention from tournament creation.

**Independent Test**: Can be fully tested by clearing cookies, entering a valid admin token, and verifying access to tournament management.

**Acceptance Scenarios**:

1. **Given** an organizer whose admin cookie has expired, **When** they enter their 16-character admin token, **Then** they regain access to tournament management.

2. **Given** an invalid admin token, **When** the organizer attempts to authenticate, **Then** access is denied with a clear error message.

3. **Given** successful authentication, **When** access is granted, **Then** a new 30-day cookie is set.

---

### Edge Cases

- What happens when BCP is unreachable during pairing import?
  - System displays an error indicating BCP is unavailable and suggests retry.

- What happens when the number of pairings exceeds available tables?
  - System allocates all available tables and reports which pairings could not be assigned.

- What happens when a tournament has no previous rounds (fresh start)?
  - System uses BCP's table assignments for round 1 as the baseline.

- What happens when terrain types are not configured for a tournament?
  - Terrain type priority (requirement #3) is skipped; only table history and scoring priorities apply.

- What happens when two players have identical scores during allocation?
  - Either player may receive the lower table number; order is not significant for tied scores.

- What happens when the BCP URL round parameter is missing or invalid?
  - System prompts the organizer to select the round number manually.

## Requirements *(mandatory)*

### Functional Requirements

- **FR-001**: System MUST allow organizers to create tournaments with a name, BCP event URL (format: `https://www.bestcoastpairings.com/event/{eventId}`), and table count.
- **FR-002**: System MUST generate a 16-character base64 admin token when a tournament is created.
- **FR-003**: System MUST store the admin token as a browser cookie with 30-day retention.
- **FR-004**: System MUST allow organizers to authenticate by manually entering their admin token.
- **FR-005**: System MUST allow organizers to optionally assign terrain types to each table from a predefined list stored in the database.
- **FR-006**: System MUST fetch pairing data from the BCP event URL for a selected round (using URL format: `https://www.bestcoastpairings.com/event/{eventId}?active_tab=pairings&round={roundNumber}`).
- **FR-007**: System MUST generate table allocations following this priority order:
  1. Round 1 uses BCP's original table assignments
  2. Players should not play on tables they've used in previous rounds
  3. Players should play on terrain types they haven't experienced
  4. Higher-scoring players should play on lower table numbers
- **FR-008**: System MUST allow organizers to manually edit table assignments for any pairing.
- **FR-009**: System MUST allow organizers to swap tables between two pairings.
- **FR-010**: System MUST visually highlight conflicts when:
  - A table is assigned to multiple pairings in the same round
  - A player is assigned to a table they've used in a previous round
  - A player is assigned to a terrain type they've already experienced
- **FR-011**: System MUST allow organizers to publish a round's allocation, making it visible to public users.
- **FR-012**: System MUST display published allocations to public users without requiring authentication.
- **FR-013**: System MUST preserve allocation history for all rounds; published allocations remain editable by organizers.
- **FR-014**: System MUST log allocation decisions to enable auditability of why each assignment was made.
- **FR-015**: System MUST provide a manual refresh button to re-fetch pairing data from BCP, overwriting existing pairing data for that round.

### Key Entities

- **Tournament**: Represents a Kill Team event; has BCP event ID, table count, admin token, name, and collection of rounds.
- **Table**: Represents a physical table at the event; has number (1-N) and optional terrain type reference.
- **TerrainType**: Predefined terrain configuration name stored in the database (e.g., "Volkus", "Tomb World", "Octarius").
- **Round**: A tournament round; has round number, collection of allocations, published status.
- **Allocation**: Assignment of a player pairing to a table for a round; includes both player references and allocation reasoning.
- **Player**: A tournament participant; identified by BCP player ID, has name and current score from BCP.

### Assumptions

- BCP event URLs follow the format `https://www.bestcoastpairings.com/event/{eventId}` with optional query parameters for round selection.
- BCP provides pairing data that includes player IDs, names, scores, and (for round 1) table assignments.
- BCP provides a public REST API at `https://newprod-api.bestcoastpairings.com/v1/events/{eventId}/pairings` that returns JSON pairing data.
- Terrain types are pre-populated in the database and managed separately from this feature.
- Tournament organizers will have internet access at the venue to fetch BCP data.
- A typical tournament has 4-6 rounds with 8-20 tables.
- Tournament data is retained indefinitely; no automatic deletion or archival.

## Success Criteria *(mandatory)*

### Measurable Outcomes

- **SC-001**: Organizers can create a tournament and generate first allocation in under 5 minutes.
- **SC-002**: Table allocation generation completes in under 10 seconds for tournaments with up to 40 players.
- **SC-003**: 95% of generated allocations require no manual edits (automatic allocation is optimal).
- **SC-004**: Players can view their table assignment within 3 seconds of page load.
- **SC-005**: Organizers successfully authenticate using their admin token on first attempt 99% of the time.
- **SC-006**: Zero incidents of allocation conflicts going undetected (all conflicts are visually flagged).
- **SC-007**: System correctly applies all four allocation priority rules in the documented order.
