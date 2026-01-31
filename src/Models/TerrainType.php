<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Terrain type entity.
 *
 * Represents predefined terrain configurations available at the venue.
 * Reference: specs/001-table-allocation/data-model.md#terraintype
 */
class TerrainType extends BaseModel
{
    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var string|null */
    public $emoji;

    /** @var int */
    public $sortOrder;

    public function __construct(
        ?int $id = null,
        string $name = '',
        ?string $description = null,
        ?string $emoji = null,
        int $sortOrder = 0
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->emoji = $emoji;
        $this->sortOrder = $sortOrder;
    }

    protected static function getTableName(): string
    {
        return 'terrain_types';
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row)
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['description'],
            $row['emoji'] ?? null,
            (int) $row['sort_order']
        );
    }

    /**
     * Find terrain type by name.
     */
    public static function findByName(string $name): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM terrain_types WHERE name = ?',
            [$name]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Get all terrain types ordered by sort_order.
     *
     * @return TerrainType[]
     */
    public static function all(): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM terrain_types ORDER BY sort_order ASC'
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Insert a new terrain type.
     */
    protected function insert(): bool
    {
        Connection::execute(
            'INSERT INTO terrain_types (name, description, emoji, sort_order)
             VALUES (?, ?, ?, ?)',
            [
                $this->name,
                $this->description,
                $this->emoji,
                $this->sortOrder,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing terrain type.
     */
    protected function update(): bool
    {
        Connection::execute(
            'UPDATE terrain_types SET name = ?, description = ?, emoji = ?, sort_order = ? WHERE id = ?',
            [
                $this->name,
                $this->description,
                $this->emoji,
                $this->sortOrder,
                $this->id,
            ]
        );

        return true;
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'emoji' => $this->emoji,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
