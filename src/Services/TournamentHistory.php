<?php

declare(strict_types=1);

namespace KTTables\Services;

use KTTables\Database\Connection;

/**
 * Service for querying tournament history (player table/terrain usage).
 *
 * Reference: specs/001-table-allocation/data-model.md#query-patterns
 */
class TournamentHistory
{
    /** @var int */
    private $tournamentId;

    /** @var int */
    private $currentRound;

    /** @var array */
    private $playerTableHistoryCache = [];

    /** @var array */
    private $playerTerrainHistoryCache = [];

    public function __construct(int $tournamentId, int $currentRound)
    {
        $this->tournamentId = $tournamentId;
        $this->currentRound = $currentRound;
    }

    /**
     * Get tournament ID.
     */
    public function getTournamentId(): int
    {
        return $this->tournamentId;
    }

    /**
     * Get current round number.
     */
    public function getCurrentRound(): int
    {
        return $this->currentRound;
    }

    /**
     * Check if player has used a specific table in previous rounds.
     *
     * Reference: FR-007.2
     *
     * @param int|string $playerId Player ID (int for DB ID, string for BCP ID)
     * @param int $tableNumber Table number to check
     */
    public function hasPlayerUsedTable($playerId, int $tableNumber): bool
    {
        $history = $this->getPlayerTableHistory($playerId);

        foreach ($history as $record) {
            if ((int) $record['table_number'] === $tableNumber) {
                return true;
            }
        }

        return false;
    }

    /**
     * Check if player has experienced a specific terrain type.
     *
     * Reference: FR-007.3
     *
     * @param int|string $playerId Player ID
     * @param int|null $terrainTypeId Terrain type ID to check
     */
    public function hasPlayerExperiencedTerrain($playerId, ?int $terrainTypeId): bool
    {
        if ($terrainTypeId === null) {
            return false;
        }

        $history = $this->getPlayerTerrainHistory($playerId);

        foreach ($history as $record) {
            if ((int) $record['id'] === $terrainTypeId) {
                return true;
            }
        }

        return false;
    }

    /**
     * Get player's table history (tables used in previous rounds).
     *
     * Reference: specs/001-table-allocation/data-model.md#get-player-table-history
     */
    public function getPlayerTableHistory($playerId): array
    {
        $cacheKey = (string) $playerId;

        if (!isset($this->playerTableHistoryCache[$cacheKey])) {
            $this->playerTableHistoryCache[$cacheKey] = $this->queryPlayerTableHistory($playerId);
        }

        return $this->playerTableHistoryCache[$cacheKey];
    }

    /**
     * Get player's terrain history (terrain types experienced in previous rounds).
     *
     * Reference: specs/001-table-allocation/data-model.md#get-player-terrain-history
     */
    public function getPlayerTerrainHistory($playerId): array
    {
        $cacheKey = (string) $playerId;

        if (!isset($this->playerTerrainHistoryCache[$cacheKey])) {
            $this->playerTerrainHistoryCache[$cacheKey] = $this->queryPlayerTerrainHistory($playerId);
        }

        return $this->playerTerrainHistoryCache[$cacheKey];
    }

    /**
     * Query player table history from database.
     *
     * SQL per data-model.md#get-player-table-history
     */
    protected function queryPlayerTableHistory($playerId): array
    {
        // If round 1, there's no history
        if ($this->currentRound <= 1) {
            return [];
        }

        $sql = "
            SELECT t.table_number, tt.name as terrain_type, r.round_number
            FROM allocations a
            JOIN tables t ON a.table_id = t.id
            LEFT JOIN terrain_types tt ON t.terrain_type_id = tt.id
            JOIN rounds r ON a.round_id = r.id
            WHERE r.tournament_id = ?
              AND (a.player1_id = ? OR a.player2_id = ?)
              AND r.round_number < ?
            ORDER BY r.round_number
        ";

        return Connection::fetchAll($sql, [
            $this->tournamentId,
            $playerId,
            $playerId,
            $this->currentRound,
        ]);
    }

    /**
     * Query player terrain history from database.
     *
     * SQL per data-model.md#get-player-terrain-history
     */
    protected function queryPlayerTerrainHistory($playerId): array
    {
        // If round 1, there's no history
        if ($this->currentRound <= 1) {
            return [];
        }

        $sql = "
            SELECT DISTINCT tt.id, tt.name
            FROM allocations a
            JOIN tables t ON a.table_id = t.id
            JOIN terrain_types tt ON t.terrain_type_id = tt.id
            JOIN rounds r ON a.round_id = r.id
            WHERE r.tournament_id = ?
              AND (a.player1_id = ? OR a.player2_id = ?)
              AND r.round_number < ?
        ";

        return Connection::fetchAll($sql, [
            $this->tournamentId,
            $playerId,
            $playerId,
            $this->currentRound,
        ]);
    }

    /**
     * Clear all caches.
     */
    public function clearCache(): void
    {
        $this->playerTableHistoryCache = [];
        $this->playerTerrainHistoryCache = [];
    }
}
