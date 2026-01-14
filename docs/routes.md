# Kill Team Tables - Routes Documentation

**Generated**: 2026-01-13 | **Branch**: `001-table-allocation`

## Overview

This document describes all available web pages and API endpoints in the Kill Team Tables application.

---

## HTML Pages (Views)

### Public Pages

| Route | Description | Ready |
|-------|-------------|-------|
| `GET /` | Home page - landing page with feature overview | Yes |
| `GET /tournament/create` | Tournament creation form | Yes |
| `GET /login` | Admin login page | Yes |
| `GET /public/{id}` | Public tournament view with round selector | Yes |
| `GET /public/{id}/round/{n}` | Public round allocations display | Yes |

### Admin Pages (Authentication Required)

| Route | Description | Ready |
|-------|-------------|-------|
| `GET /tournament/{id}` | Tournament dashboard | No |
| `GET /tournament/{id}/round/{n}` | Round management view | Yes |

---

## API Endpoints

Base path: `/api`

### Authentication

All admin endpoints require authentication via one of:
- **Header**: `X-Admin-Token: <16-character-token>`
- **Cookie**: `admin_token=<16-character-token>`

### Tournament Management

| Method | Route | Description | Auth | Ready |
|--------|-------|-------------|------|-------|
| `POST` | `/api/tournaments` | Create a new tournament | No | Yes |
| `GET` | `/api/tournaments/{id}` | Get tournament details | Yes | Yes |
| `PUT` | `/api/tournaments/{id}/tables` | Update table terrain types | Yes | Yes |

### Round Management

| Method | Route | Description | Auth | Ready |
|--------|-------|-------------|------|-------|
| `GET` | `/api/tournaments/{id}/rounds/{n}` | Get round allocations (admin view) | Yes | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/import` | Import pairings from BCP | Yes | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/generate` | Generate table allocations | Yes | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/publish` | Publish round to public | Yes | Yes |

### Allocation Management

| Method | Route | Description | Auth | Ready |
|--------|-------|-------------|------|-------|
| `PATCH` | `/api/allocations/{id}` | Edit table assignment | Yes | Yes |
| `POST` | `/api/allocations/swap` | Swap tables between two pairings | Yes | Yes |

### Authentication

| Method | Route | Description | Auth | Ready |
|--------|-------|-------------|------|-------|
| `POST` | `/api/auth` | Authenticate with admin token | No | Yes |

### Reference Data

| Method | Route | Description | Auth | Ready |
|--------|-------|-------------|------|-------|
| `GET` | `/api/terrain-types` | List all terrain types | No | Yes |

### Public Endpoints

These endpoints require no authentication and are intended for player-facing displays.

| Method | Route | Description | Ready |
|--------|-------|-------------|-------|
| `GET` | `/api/public/tournaments/{id}` | Get public tournament info and published rounds list | Yes |
| `GET` | `/api/public/tournaments/{id}/rounds/{n}` | Get published round allocations | Yes |

---

## URL Parameters

| Parameter | Description | Example |
|-----------|-------------|---------|
| `{id}` | Tournament ID (integer) | `1`, `42` |
| `{n}` | Round number (1-based integer) | `1`, `2`, `3` |

---

## Usage Examples

### For Tournament Organizers

1. **Create a tournament**: Visit `/tournament/create`
2. **Manage rounds**: Navigate to `/tournament/{id}/round/{n}`
3. **Login with token**: Visit `/login` and enter your admin token

### For Players

1. **View tournament**: Navigate to `/public/{tournamentId}`
2. **View round allocations**: Click on a round or go to `/public/{tournamentId}/round/{roundNumber}`

### API Examples

```bash
# Create a tournament
curl -X POST http://localhost:8080/api/tournaments \
  -H "Content-Type: application/json" \
  -d '{"name": "My Tournament", "bcpUrl": "https://www.bestcoastpairings.com/event/abc123", "tableCount": 12}'

# Get public tournament info
curl http://localhost:8080/api/public/tournaments/1

# Get published round allocations
curl http://localhost:8080/api/public/tournaments/1/rounds/2

# Import pairings (authenticated)
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/import \
  -H "X-Admin-Token: your16chartoken"

# Generate allocations (authenticated)
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/generate \
  -H "X-Admin-Token: your16chartoken"

# Publish round (authenticated)
curl -X POST http://localhost:8080/api/tournaments/1/rounds/2/publish \
  -H "X-Admin-Token: your16chartoken"
```

---

## See Also

- [API Contract (OpenAPI)](../specs/001-table-allocation/contracts/api.yaml) - Full API specification
- [Quickstart Guide](../specs/001-table-allocation/quickstart.md) - Setup and usage instructions
