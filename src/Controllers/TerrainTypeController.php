<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\TerrainType;

/**
 * Terrain type controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/terrain-types
 */
class TerrainTypeController extends BaseController
{
    /**
     * GET /api/terrain-types - List all terrain types.
     *
     * Reference: FR-005
     */
    public function index(array $params, ?array $body): void
    {
        $terrainTypes = TerrainType::all();

        $this->success([
            'terrainTypes' => $this->toArrayMap($terrainTypes),
        ]);
    }
}
