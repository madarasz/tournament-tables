<?php

declare(strict_types=1);

namespace KTTables\Models;

use KTTables\Database\Connection;

/**
 * Table entity.
 *
 * Represents a physical table at the tournament venue.
 * Reference: specs/001-table-allocation/data-model.md#table
 */
class Table
{
    /** @var int|null */
    public $id;

    /** @var int */
    public $tournamentId;

    /** @var int */
    public $tableNumber;

    /** @var int|null */
    public $terrainTypeId;

    public function __construct(
        ?int $id = null,
        int $tournamentId = 0,
        int $tableNumber = 0,
        ?int $terrainTypeId = null
    ) {
        $this->id = $id;
        $this->tournamentId = $tournamentId;
        $this->tableNumber = $tableNumber;
        $this->terrainTypeId = $terrainTypeId;
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tournament_id'],
            (int) $row['table_number'],
            isset($row['terrain_type_id']) ? (int) $row['terrain_type_id'] : null
        );
    }

    /**
     * Find table by ID.
     */
    public static function find(int $id): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tables WHERE id = ?',
            [$id]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all tables for a tournament.
     *
     * @return Table[]
     */
    public static function findByTournament(int $tournamentId): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM tables WHERE tournament_id = ? ORDER BY table_number ASC',
            [$tournamentId]
        );

        return array_map([self::class, 'fromRow'], $rows);
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
     * Save the table (insert or update).
     */
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Insert a new table.
     */
    private function insert(): bool
    {
        Connection::execute(
            'INSERT INTO tables (tournament_id, table_number, terrain_type_id)
             VALUES (?, ?, ?)',
            [
                $this->tournamentId,
                $this->tableNumber,
                $this->terrainTypeId,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing table.
     */
    private function update(): bool
    {
        Connection::execute(
            'UPDATE tables SET terrain_type_id = ? WHERE id = ?',
            [
                $this->terrainTypeId,
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
        ];
    }
}
