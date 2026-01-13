<?php

declare(strict_types=1);

namespace KTTables\Models;

use KTTables\Database\Connection;

/**
 * Tournament entity.
 *
 * Represents a Kill Team event managed through BCP.
 * Reference: specs/001-table-allocation/data-model.md#tournament
 */
class Tournament
{
    /** @var int|null */
    public $id;

    /** @var string */
    public $name;

    /** @var string */
    public $bcpEventId;

    /** @var string */
    public $bcpUrl;

    /** @var int */
    public $tableCount;

    /** @var string */
    public $adminToken;

    public function __construct(
        ?int $id = null,
        string $name = '',
        string $bcpEventId = '',
        string $bcpUrl = '',
        int $tableCount = 0,
        string $adminToken = ''
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->bcpEventId = $bcpEventId;
        $this->bcpUrl = $bcpUrl;
        $this->tableCount = $tableCount;
        $this->adminToken = $adminToken;
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['bcp_event_id'],
            $row['bcp_url'],
            (int) $row['table_count'],
            $row['admin_token']
        );
    }

    /**
     * Find tournament by ID.
     */
    public static function find(int $id): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tournaments WHERE id = ?',
            [$id]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find tournament by admin token.
     */
    public static function findByToken(string $token): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tournaments WHERE admin_token = ?',
            [$token]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find tournament by BCP event ID.
     */
    public static function findByBcpEventId(string $bcpEventId): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM tournaments WHERE bcp_event_id = ?',
            [$bcpEventId]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Save the tournament (insert or update).
     */
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Insert a new tournament.
     */
    private function insert(): bool
    {
        Connection::execute(
            'INSERT INTO tournaments (name, bcp_event_id, bcp_url, table_count, admin_token)
             VALUES (?, ?, ?, ?, ?)',
            [
                $this->name,
                $this->bcpEventId,
                $this->bcpUrl,
                $this->tableCount,
                $this->adminToken,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing tournament.
     */
    private function update(): bool
    {
        Connection::execute(
            'UPDATE tournaments SET name = ?, table_count = ? WHERE id = ?',
            [
                $this->name,
                $this->tableCount,
                $this->id,
            ]
        );

        return true;
    }

    /**
     * Delete the tournament.
     */
    public function delete(): bool
    {
        if ($this->id === null) {
            return false;
        }

        Connection::execute('DELETE FROM tournaments WHERE id = ?', [$this->id]);
        return true;
    }

    /**
     * Get all tables for this tournament.
     *
     * @return Table[]
     */
    public function getTables(): array
    {
        return Table::findByTournament($this->id);
    }

    /**
     * Get all rounds for this tournament.
     *
     * @return Round[]
     */
    public function getRounds(): array
    {
        return Round::findByTournament($this->id);
    }

    /**
     * Get all players for this tournament.
     *
     * @return Player[]
     */
    public function getPlayers(): array
    {
        return Player::findByTournament($this->id);
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'bcpEventId' => $this->bcpEventId,
            'bcpUrl' => $this->bcpUrl,
            'tableCount' => $this->tableCount,
        ];
    }
}
