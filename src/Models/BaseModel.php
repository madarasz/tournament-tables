<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Base model class providing common CRUD operations.
 *
 * Child classes must define:
 * - protected static string $tableName (the database table name)
 * - public static function fromRow(array $row)
 * - protected function insert(): bool
 * - protected function update(): bool
 */
abstract class BaseModel
{
    /** @var int|null */
    public $id;

    /**
     * Get the table name for this model.
     * Must be overridden in child classes.
     */
    abstract protected static function getTableName(): string;

    /**
     * Create instance from database row.
     *
     * @param array $row Database row
     * @return static
     */
    abstract public static function fromRow(array $row);

    /**
     * Insert a new record.
     */
    abstract protected function insert(): bool;

    /**
     * Update an existing record.
     */
    abstract protected function update(): bool;

    /**
     * Find entity by ID.
     */
    public static function find(int $id): ?self
    {
        $tableName = static::getTableName();
        $row = Connection::fetchOne(
            "SELECT * FROM {$tableName} WHERE id = ?",
            [$id]
        );

        return $row ? static::fromRow($row) : null;
    }

    /**
     * Save the entity (insert or update).
     */
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Delete the entity.
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        $tableName = static::getTableName();
        Connection::execute("DELETE FROM {$tableName} WHERE id = ?", [$this->id]);
        return true;
    }

    /**
     * Find all entities belonging to a tournament.
     *
     * @return static[]
     */
    protected static function findByTournamentId(int $tournamentId, string $orderBy = 'id ASC'): array
    {
        $tableName = static::getTableName();
        $rows = Connection::fetchAll(
            "SELECT * FROM {$tableName} WHERE tournament_id = ? ORDER BY {$orderBy}",
            [$tournamentId]
        );

        return array_map([static::class, 'fromRow'], $rows);
    }
}
