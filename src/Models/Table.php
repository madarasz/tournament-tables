<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Table entity.
 *
 * Represents a physical table at the tournament venue.
 * Reference: specs/001-table-allocation/data-model.md#table
 */
class Table extends BaseModel
{
    /** @var int */
    public $tournamentId;

    /** @var int */
    public $tableNumber;

    /** @var int|null */
    public $terrainTypeId;

    /** @var bool */
    public $isHidden;

    public function __construct(
        ?int $id = null,
        int $tournamentId = 0,
        int $tableNumber = 0,
        ?int $terrainTypeId = null,
        bool $isHidden = false
    ) {
        $this->id = $id;
        $this->tournamentId = $tournamentId;
        $this->tableNumber = $tableNumber;
        $this->terrainTypeId = $terrainTypeId;
        $this->isHidden = $isHidden;
    }

    protected static function getTableName(): string
    {
        return 'tables';
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row)
    {
        return new self(
            (int) $row['id'],
            (int) $row['tournament_id'],
            (int) $row['table_number'],
            isset($row['terrain_type_id']) ? (int) $row['terrain_type_id'] : null,
            !empty($row['is_hidden'])
        );
    }

    /**
     * Find all tables for a tournament (including hidden).
     *
     * @return Table[]
     */
    public static function findByTournament(int $tournamentId): array
    {
        return self::findByTournamentId($tournamentId, 'table_number ASC');
    }

    /**
     * Find visible (non-hidden) tables for a tournament.
     *
     * @return Table[]
     */
    public static function findVisibleByTournament(int $tournamentId): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM tables WHERE tournament_id = ? AND is_hidden = FALSE ORDER BY table_number ASC',
            [$tournamentId]
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Count visible (non-hidden) tables for a tournament.
     */
    public static function countVisibleByTournament(int $tournamentId): int
    {
        $row = Connection::fetchOne(
            'SELECT COUNT(*) as cnt FROM tables WHERE tournament_id = ? AND is_hidden = FALSE',
            [$tournamentId]
        );

        return $row ? (int) $row['cnt'] : 0;
    }

    /**
     * Get the next available table number for a tournament.
     */
    public static function getNextTableNumber(int $tournamentId): int
    {
        $row = Connection::fetchOne(
            'SELECT MAX(table_number) as max_num FROM tables WHERE tournament_id = ?',
            [$tournamentId]
        );

        return $row && $row['max_num'] !== null ? (int) $row['max_num'] + 1 : 1;
    }

    /**
     * Find the lowest-numbered hidden table for a tournament.
     */
    public static function findLowestHidden(int $tournamentId): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tables WHERE tournament_id = ? AND is_hidden = TRUE ORDER BY table_number ASC LIMIT 1',
            [$tournamentId]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find the highest-numbered visible table for a tournament.
     */
    public static function findHighestVisible(int $tournamentId): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tables WHERE tournament_id = ? AND is_hidden = FALSE ORDER BY table_number DESC LIMIT 1',
            [$tournamentId]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find table by tournament and table number.
     */
    public static function findByTournamentAndNumber(int $tournamentId, int $tableNumber): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tables WHERE tournament_id = ? AND table_number = ?',
            [$tournamentId, $tableNumber]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Create tables for a tournament.
     *
     * @return Table[]
     */
    public static function createForTournament(int $tournamentId, int $tableCount): array
    {
        $tables = [];
        for ($i = 1; $i <= $tableCount; $i++) {
            $table = new self(null, $tournamentId, $i, null);
            $table->save();
            $tables[] = $table;
        }
        return $tables;
    }

    /**
     * Insert a new table.
     */
    protected function insert(): bool
    {
        Connection::execute(
            'INSERT INTO tables (tournament_id, table_number, terrain_type_id, is_hidden)
             VALUES (?, ?, ?, ?)',
            [
                $this->tournamentId,
                $this->tableNumber,
                $this->terrainTypeId,
                $this->isHidden ? 1 : 0,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing table.
     */
    protected function update(): bool
    {
        Connection::execute(
            'UPDATE tables SET terrain_type_id = ?, is_hidden = ? WHERE id = ?',
            [
                $this->terrainTypeId,
                $this->isHidden ? 1 : 0,
                $this->id,
            ]
        );

        return true;
    }

    /**
     * Get the terrain type for this table.
     */
    public function getTerrainType(): ?TerrainType
    {
        if ($this->terrainTypeId === null) {
            return null;
        }
        return TerrainType::find($this->terrainTypeId);
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        $terrainType = $this->getTerrainType();
        return [
            'id' => $this->id,
            'tableNumber' => $this->tableNumber,
            'terrainType' => $terrainType ? $terrainType->toArray() : null,
            'isHidden' => $this->isHidden,
        ];
    }
}
