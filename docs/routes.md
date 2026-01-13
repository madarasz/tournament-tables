# Kill Team Tables - Routes Documentation

**Generated**: 2026-01-13 | **Branch**: `001-table-allocation`

## Overview

This document describes all available web pages and API endpoints in the Kill Team Tables application.

---

## HTML Pages (Views)

### Public Pages

| Route | Description |
|-------|-------------|
| `GET /` | Home page - landing page with feature overview |
| `GET /tournament/create` | Tournament creation form |
| `GET /login` | Admin login page |
| `GET /public/{id}` | Public tournament view with round selector |
| `GET /public/{id}/round/{n}` | Public round allocations display |

### Admin Pages (Authentication Required)

| Route | Description |
|-------|-------------|
| `GET /tournament/{id}` | Tournament dashboard |
| `GET /tournament/{id}/round/{n}` | Round management view |

---

## API Endpoints

Base path: `/api`

### Authentication

All admin endpoints require authentication via one of:
- **Header**: `X-Admin-Token: <16-character-token>`
- **Cookie**: `admin_token=<16-character-token>`

### Tournament Management

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `POST` | `/api/tournaments` | Create a new tournament | No |
| `GET` | `/api/tournaments/{id}` | Get tournament details | Yes |
| `PUT` | `/api/tournaments/{id}/tables` | Update table terrain types | Yes |

### Round Management

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `GET` | `/api/tournaments/{id}/rounds/{n}` | Get round allocations (admin view) | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/import` | Import pairings from BCP | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/generate` | Generate table allocations | Yes |
| `POST` | `/api/tournaments/{id}/rounds/{n}/publish` | Publish round to public | Yes |

### Allocation Management

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `PATCH` | `/api/allocations/{id}` | Edit table assignment | Yes |
| `POST` | `/api/allocations/swap` | Swap tables between two pairings | Yes |

### Authentication

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `POST` | `/api/auth` | Authenticate with admin token | No |

### Reference Data

| Method | Route | Description | Auth |
|--------|-------|-------------|------|
| `GET` | `/api/terrain-types` | List all terrain types | No |

### Public Endpoints

These endpoints require no authentication and are intended for player-facing displays.

| Method | Route | Description |
|--------|-------|-------------|
| `GET` | `/api/public/tournaments/{id}` | Get public tournament info and published rounds list |
| `GET` | `/api/public/tournaments/{id}/rounds/{n}` | Get published round allocations |

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
