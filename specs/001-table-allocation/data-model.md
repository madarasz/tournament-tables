# Data Model: Tournament Tables

**Generated**: 2026-01-13 | **Branch**: `001-table-allocation`

## Entity Relationship Diagram

```
┌──────────────────┐       ┌──────────────────┐
│   TerrainType    │       │    Tournament    │
├──────────────────┤       ├──────────────────┤
│ id (PK)          │       │ id (PK)          │
│ name             │       │ name             │
│ description      │       │ bcp_event_id     │
│ sort_order       │       │ bcp_url          │
└────────┬─────────┘       │ table_count      │
         │                 │ admin_token      │
         │                 └────────┬─────────┘
         │                          │
         │    ┌─────────────────────┼─────────────────────┐
         │    │                     │                     │
         │    ▼                     ▼                     ▼
         │ ┌──────────────────┐ ┌──────────────────┐ ┌──────────────────┐
         │ │      Table       │ │      Round       │ │      Player      │
         │ ├──────────────────┤ ├──────────────────┤ ├──────────────────┤
         │ │ id (PK)          │ │ id (PK)          │ │ id (PK)          │
         │ │ tournament_id(FK)│ │ tournament_id(FK)│ │ tournament_id(FK)│
         └─│ terrain_type_id  │ │ round_number     │ │ bcp_player_id    │
           │ table_number     │ │ is_published     │ │ name             │
           └────────┬─────────┘ └────────┬─────────┘ └────────┬─────────┘
                    │                    │                    │
                    │                    │                    │
                    ▼                    ▼                    │
           ┌─────────────────────────────────────────────────┐│
           │                   Allocation                    ││
           ├─────────────────────────────────────────────────┤│
           │ id (PK)                                         ││
           │ round_id (FK)                                   │◀┘
           │ table_id (FK)                                   │
           │ player1_id (FK)                                 │
           │ player2_id (FK)                                 │
           │ player1_score                                   │
           │ player2_score                                   │
           │ allocation_reason (JSON)                        │
           └─────────────────────────────────────────────────┘
```

## Entities

### Tournament

Represents a Kill Team event managed through BCP.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| name | VARCHAR(255) | NOT NULL | Tournament display name |
| bcp_event_id | VARCHAR(50) | NOT NULL, UNIQUE | BCP event identifier (e.g., "t6OOun8POR60") |
| bcp_url | VARCHAR(500) | NOT NULL | Full BCP event URL |
| table_count | INT | NOT NULL, CHECK > 0 | Number of physical tables at venue |
| admin_token | CHAR(16) | NOT NULL, UNIQUE | Base64 admin authentication token |

**Validation Rules**:
- `bcp_url` must match pattern: `https://www.bestcoastpairings.com/event/{eventId}`
- `table_count` must be between 1 and 100
- `admin_token` is generated as 16 characters of base64

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (bcp_event_id)
- UNIQUE INDEX (admin_token)

---

### TerrainType

Predefined terrain configurations available at the venue.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| name | VARCHAR(100) | NOT NULL, UNIQUE | Terrain type name (e.g., "Volkus", "Tomb World") |
| description | TEXT | NULL | Optional description of the terrain |
| sort_order | INT | NOT NULL, DEFAULT 0 | Display order in UI (lower values first) |

**Validation Rules**:
- `name` must be unique and non-empty

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (name)
- INDEX (sort_order)

**Notes**:
- Pre-populated in the database
- Managed separately from this feature (per spec assumption)
- UI displays terrain types ordered by `sort_order` ascending

---

### Table

Physical table at the tournament venue.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| tournament_id | INT | FK → Tournament.id, NOT NULL | Parent tournament |
| table_number | INT | NOT NULL | Table number (1-N) |
| terrain_type_id | INT | FK → TerrainType.id, NULL | Optional terrain type |

**Validation Rules**:
- `table_number` must be unique within a tournament
- `table_number` must be between 1 and `tournament.table_count`

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (tournament_id, table_number)
- INDEX (terrain_type_id)

---

### Round

A tournament round containing pairings and allocations.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| tournament_id | INT | FK → Tournament.id, NOT NULL | Parent tournament |
| round_number | INT | NOT NULL | Round number (1-N) |
| is_published | BOOLEAN | NOT NULL, DEFAULT FALSE | Whether allocations are public |

**Validation Rules**:
- `round_number` must be unique within a tournament
- `round_number` must be positive

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (tournament_id, round_number)

**State Transitions**:
- Created (is_published = false) → Published (is_published = true)
- Published rounds remain editable per spec (FR-013)

---

### Player

A tournament participant imported from BCP.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| tournament_id | INT | FK → Tournament.id, NOT NULL | Parent tournament |
| bcp_player_id | VARCHAR(50) | NOT NULL | BCP unique identifier |
| name | VARCHAR(255) | NOT NULL | Player display name |

**Validation Rules**:
- `bcp_player_id` must be unique within a tournament
- `name` must be non-empty

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (tournament_id, bcp_player_id)

**Notes**:
- Player scores are stored per-allocation, not on the player entity (scores change per round)

---

### Allocation

Assignment of a player pairing to a table for a specific round.

| Field | Type | Constraints | Description |
|-------|------|-------------|-------------|
| id | INT | PK, AUTO_INCREMENT | Internal identifier |
| round_id | INT | FK → Round.id, NOT NULL | Parent round |
| table_id | INT | FK → Table.id, NOT NULL | Assigned table |
| player1_id | INT | FK → Player.id, NOT NULL | First player in pairing |
| player2_id | INT | FK → Player.id, NOT NULL | Second player in pairing |
| player1_score | INT | NOT NULL, DEFAULT 0 | Player 1 score at time of allocation |
| player2_score | INT | NOT NULL, DEFAULT 0 | Player 2 score at time of allocation |
| allocation_reason | JSON | NULL | Audit trail (see structure below) |

**Validation Rules**:
- `table_id` must belong to the same tournament as `round_id`
- `player1_id` and `player2_id` must belong to the same tournament
- `player1_id` != `player2_id`
- Each table can only be assigned once per round
- Each player can only appear in one allocation per round

**Indexes**:
- PRIMARY KEY (id)
- UNIQUE INDEX (round_id, table_id)
- UNIQUE INDEX (round_id, player1_id)
- UNIQUE INDEX (round_id, player2_id)
- INDEX (table_id)

**allocation_reason JSON Structure** (FR-014):
```json
{
  "timestamp": "2026-01-13T14:30:00Z",
  "totalCost": 3,
  "costBreakdown": {
    "tableReuse": 0,
    "terrainReuse": 0,
    "tableNumber": 3
  },
  "reasons": [],
  "alternativesConsidered": {
    "4": 100004,
    "5": 5,
    "6": 6
  },
  "isRound1": false,
  "conflicts": []
}
```

---

## Database Schema (MySQL)

```sql
-- Terrain types (pre-populated)
CREATE TABLE terrain_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tournaments
CREATE TABLE tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bcp_event_id VARCHAR(50) NOT NULL UNIQUE,
    bcp_url VARCHAR(500) NOT NULL,
    table_count INT NOT NULL CHECK (table_count > 0 AND table_count <= 100),
    admin_token CHAR(16) NOT NULL UNIQUE,
    INDEX idx_admin_token (admin_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Tables per tournament
CREATE TABLE tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    table_number INT NOT NULL,
    terrain_type_id INT,
    UNIQUE INDEX idx_tournament_table (tournament_id, table_number),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (terrain_type_id) REFERENCES terrain_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Rounds per tournament
CREATE TABLE rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    round_number INT NOT NULL,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE INDEX idx_tournament_round (tournament_id, round_number),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Players per tournament
CREATE TABLE players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    bcp_player_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    UNIQUE INDEX idx_tournament_bcp_player (tournament_id, bcp_player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Allocations (pairings assigned to tables)
CREATE TABLE allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    table_id INT NOT NULL,
    player1_id INT NOT NULL,
    player2_id INT NOT NULL,
    player1_score INT NOT NULL DEFAULT 0,
    player2_score INT NOT NULL DEFAULT 0,
    allocation_reason JSON,
    UNIQUE INDEX idx_round_table (round_id, table_id),
    UNIQUE INDEX idx_round_player1 (round_id, player1_id),
    UNIQUE INDEX idx_round_player2 (round_id, player2_id),
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id),
    FOREIGN KEY (player1_id) REFERENCES players(id),
    FOREIGN KEY (player2_id) REFERENCES players(id),
    CHECK (player1_id != player2_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
```

---

## Initial Data

### Terrain Types (Pre-populated)

```sql
INSERT INTO terrain_types (name, description, sort_order) VALUES
    ('Volkus', 'Forge world industrial terrain', 1),
    ('Tomb World', 'Necron tomb complex', 2),
    ('Octarius', 'War-torn Ork-infested ruins', 3),
    ('Gallowdark', 'Space hulk interior corridors', 4),
    ('Bheta-Decima', 'Imperial hive city ruins', 5),
    ('Into the Dark', 'Generic space hulk terrain', 6),
    ('Sector Mechanicus', 'Imperial factory terrain', 7),
    ('Sector Imperialis', 'Imperial city ruins', 8);
```

---

## Data Integrity Constraints (Constitution Principle III)

### Transaction Boundaries

| Operation | Transaction Scope |
|-----------|-------------------|
| Create Tournament | Single transaction: tournament + tables |
| Import Pairings | Single transaction: players + allocations |
| Generate Allocations | Single transaction: all allocations for round |
| Swap Tables | Single transaction: update both allocations |
| Publish Round | Single transaction: update is_published |

### Validation at System Boundaries

| Boundary | Validations |
|----------|-------------|
| Tournament Creation | BCP URL format, table_count range |
| BCP Import | Player name non-empty, valid round number |
| Manual Edit | Table exists, players exist, no duplicate assignments |
| Authentication | Token length = 16, token exists in database |

### Immutability Rules

- `allocation_reason` is append-only (original reason preserved even after manual edits)
- `bcp_event_id` cannot be changed after tournament creation
- `admin_token` cannot be changed after tournament creation

---

## Query Patterns

### Get Player Table History

Used by allocation algorithm to check previous table usage (FR-007.2):

```sql
SELECT t.table_number, tt.name as terrain_type
FROM allocations a
JOIN tables t ON a.table_id = t.id
LEFT JOIN terrain_types tt ON t.terrain_type_id = tt.id
JOIN rounds r ON a.round_id = r.id
WHERE r.tournament_id = ?
  AND (a.player1_id = ? OR a.player2_id = ?)
  AND r.round_number < ?
ORDER BY r.round_number;
```

### Get Player Terrain History

Used by allocation algorithm to check previous terrain usage (FR-007.3):

```sql
SELECT DISTINCT tt.id, tt.name
FROM allocations a
JOIN tables t ON a.table_id = t.id
JOIN terrain_types tt ON t.terrain_type_id = tt.id
JOIN rounds r ON a.round_id = r.id
WHERE r.tournament_id = ?
  AND (a.player1_id = ? OR a.player2_id = ?)
  AND r.round_number < ?;
```

### Get Published Allocations for Public View

Used for public display (FR-012):

```sql
SELECT
    t.table_number,
    tt.name as terrain_type,
    p1.name as player1_name,
    a.player1_score,
    p2.name as player2_name,
    a.player2_score
FROM allocations a
JOIN tables t ON a.table_id = t.id
LEFT JOIN terrain_types tt ON t.terrain_type_id = tt.id
JOIN players p1 ON a.player1_id = p1.id
JOIN players p2 ON a.player2_id = p2.id
JOIN rounds r ON a.round_id = r.id
WHERE r.tournament_id = ?
  AND r.round_number = ?
  AND r.is_published = TRUE
ORDER BY t.table_number;
```
