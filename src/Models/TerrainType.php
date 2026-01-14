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
class TerrainType
{
    /** @var int|null */
    public $id;

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var int */
    public $sortOrder;

    public function __construct(
        ?int $id = null,
        string $name = '',
        ?string $description = null,
        int $sortOrder = 0
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->description = $description;
        $this->sortOrder = $sortOrder;
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['description'],
            (int) $row['sort_order']
        );
    }

    /**
     * Find terrain type by ID.
     */
    public static function find(int $id): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM terrain_types WHERE id = ?',
            [$id]
        );

        return $row ? self::fromRow($row) : null;
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
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sortOrder' => $this->sortOrder,
        ];
    }
}
