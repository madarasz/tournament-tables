<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Allocation entity.
 *
 * Represents assignment of a player pairing to a table for a specific round.
 * Reference: specs/001-table-allocation/data-model.md#allocation
 */
class Allocation extends BaseModel
{
    /** @var int */
    public $roundId;

    /** @var int */
    public $tableId;

    /** @var int */
    public $player1Id;

    /** @var int */
    public $player2Id;

    /** @var int */
    public $player1Score;

    /** @var int */
    public $player2Score;

    /** @var array|null */
    public $allocationReason;

    /** @var int|null Original BCP table assignment */
    public $bcpTableNumber;

    public function __construct(
        ?int $id = null,
        int $roundId = 0,
        int $tableId = 0,
        int $player1Id = 0,
        int $player2Id = 0,
        int $player1Score = 0,
        int $player2Score = 0,
        ?array $allocationReason = null,
        ?int $bcpTableNumber = null
    ) {
        $this->id = $id;
        $this->roundId = $roundId;
        $this->tableId = $tableId;
        $this->player1Id = $player1Id;
        $this->player2Id = $player2Id;
        $this->player1Score = $player1Score;
        $this->player2Score = $player2Score;
        $this->allocationReason = $allocationReason;
        $this->bcpTableNumber = $bcpTableNumber;
    }

    protected static function getTableName(): string
    {
        return 'allocations';
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row)
    {
        $reason = null;
        if (!empty($row['allocation_reason'])) {
            $reason = json_decode($row['allocation_reason'], true);
        }

        $bcpTableNumber = isset($row['bcp_table_number']) && $row['bcp_table_number'] !== null
            ? (int) $row['bcp_table_number']
            : null;

        return new self(
            (int) $row['id'],
            (int) $row['round_id'],
            (int) $row['table_id'],
            (int) $row['player1_id'],
            (int) $row['player2_id'],
            (int) $row['player1_score'],
            (int) $row['player2_score'],
            $reason,
            $bcpTableNumber
        );
    }

    /**
     * Find all allocations for a round.
     *
     * @return Allocation[]
     */
    public static function findByRound(int $roundId): array
    {
        $rows = Connection::fetchAll(
            'SELECT a.*, t.table_number
             FROM allocations a
             JOIN tables t ON a.table_id = t.id
             WHERE a.round_id = ?
             ORDER BY t.table_number ASC',
            [$roundId]
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Find allocation by round and table.
     */
    public static function findByRoundAndTable(int $roundId, int $tableId): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM allocations WHERE round_id = ? AND table_id = ?',
            [$roundId, $tableId]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Insert a new allocation.
     */
    protected function insert(): bool
    {
        Connection::execute(
            'INSERT INTO allocations (round_id, table_id, player1_id, player2_id, player1_score, player2_score, allocation_reason, bcp_table_number)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->roundId,
                $this->tableId,
                $this->player1Id,
                $this->player2Id,
                $this->player1Score,
                $this->player2Score,
                $this->serializeReason(),
                $this->bcpTableNumber,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing allocation.
     */
    protected function update(): bool
    {
        Connection::execute(
            'UPDATE allocations SET table_id = ?, allocation_reason = ? WHERE id = ?',
            [
                $this->tableId,
                $this->serializeReason(),
                $this->id,
            ]
        );

        return true;
    }

    /**
     * Serialize allocation reason to JSON.
     */
    private function serializeReason(): ?string
    {
        return $this->allocationReason !== null
            ? json_encode($this->allocationReason)
            : null;
    }

    /**
     * Get player 1.
     */
    public function getPlayer1(): ?Player
    {
        return Player::find($this->player1Id);
    }

    /**
     * Get player 2.
     */
    public function getPlayer2(): ?Player
    {
        return Player::find($this->player2Id);
    }

    /**
     * Get the table.
     */
    public function getTable(): ?Table
    {
        return Table::find($this->tableId);
    }

    /**
     * Get conflicts from allocation reason.
     *
     * @return array
     */
    public function getConflicts(): array
    {
        if ($this->allocationReason === null) {
            return [];
        }
        return $this->allocationReason['conflicts'] ?? [];
    }

    /**
     * Check if allocation has conflicts.
     */
    public function hasConflicts(): bool
    {
        return count($this->getConflicts()) > 0;
    }

    /**
     * Check if current table differs from original BCP assignment.
     * Returns false if BCP table number is not available.
     */
    public function hasBcpTableDifference(): bool
    {
        if ($this->bcpTableNumber === null) {
            return false;
        }
        $table = $this->getTable();
        return $table && $table->tableNumber !== $this->bcpTableNumber;
    }

    /**
     * Get common array data for both toArray and toPublicArray.
     *
     * @return array{table: Table|null, player1: Player|null, player2: Player|null, terrainType: TerrainType|null}
     */
    private function getRelatedEntities(): array
    {
        $table = $this->getTable();
        return [
            'table' => $table,
            'player1' => $this->getPlayer1(),
            'player2' => $this->getPlayer2(),
            'terrainType' => $table ? $table->getTerrainType() : null,
        ];
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        $entities = $this->getRelatedEntities();
        $table = $entities['table'];
        $player1 = $entities['player1'];
        $player2 = $entities['player2'];
        $terrainType = $entities['terrainType'];

        return [
            'id' => $this->id,
            'tableNumber' => $table ? $table->tableNumber : null,
            'terrainType' => $terrainType ? $terrainType->name : null,
            'player1' => [
                'id' => $player1 ? $player1->id : null,
                'name' => $player1 ? $player1->name : null,
                'score' => $this->player1Score,
            ],
            'player2' => [
                'id' => $player2 ? $player2->id : null,
                'name' => $player2 ? $player2->name : null,
                'score' => $this->player2Score,
            ],
            'conflicts' => $this->getConflicts(),
        ];
    }

    /**
     * Convert to public array (no conflict details).
     */
    public function toPublicArray(): array
    {
        $entities = $this->getRelatedEntities();
        $table = $entities['table'];
        $player1 = $entities['player1'];
        $player2 = $entities['player2'];
        $terrainType = $entities['terrainType'];

        return [
            'tableNumber' => $table ? $table->tableNumber : null,
            'terrainType' => $terrainType ? $terrainType->name : null,
            'player1Name' => $player1 ? $player1->name : null,
            'player1Score' => $this->player1Score,
            'player2Name' => $player2 ? $player2->name : null,
            'player2Score' => $this->player2Score,
        ];
    }
}
