<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Tournament entity.
 *
 * Represents a tournament event managed through BCP.
 * Reference: specs/001-table-allocation/data-model.md#tournament
 */
class Tournament extends BaseModel
{
    /** @var string */
    public $name;

    /** @var string */
    public $bcpEventId;

    /** @var string */
    public $bcpUrl;

    /** @var string|null */
    public $photoUrl;

    /** @var string|null */
    public $locationName;

    /** @var string|null */
    public $eventDate;

    /** @var string|null */
    public $eventEndDate;

    /** @var int */
    public $tableCount;

    /** @var string|null */
    public $lastUpdated;

    /** @var string */
    public $adminToken;

    public function __construct(
        ?int $id = null,
        string $name = '',
        string $bcpEventId = '',
        string $bcpUrl = '',
        int $tableCount = 0,
        string $adminToken = '',
        ?string $lastUpdated = null,
        ?string $photoUrl = null,
        ?string $eventDate = null,
        ?string $eventEndDate = null,
        ?string $locationName = null
    ) {
        $this->id = $id;
        $this->name = $name;
        $this->bcpEventId = $bcpEventId;
        $this->bcpUrl = $bcpUrl;
        $this->photoUrl = $photoUrl;
        $this->locationName = $locationName;
        $this->eventDate = $eventDate;
        $this->eventEndDate = $eventEndDate;
        $this->tableCount = $tableCount;
        $this->adminToken = $adminToken;
        $this->lastUpdated = $lastUpdated;
    }

    protected static function getTableName(): string
    {
        return 'tournaments';
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row)
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            $row['bcp_event_id'],
            $row['bcp_url'],
            (int) $row['table_count'],
            $row['admin_token'],
            $row['last_updated'] ?? null,
            $row['photo_url'] ?? null,
            $row['event_date'] ?? null,
            $row['event_end_date'] ?? null,
            $row['location_name'] ?? null
        );
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
     * Insert a new tournament.
     */
    protected function insert(): bool
    {
        Connection::execute(
            'INSERT INTO tournaments (name, bcp_event_id, bcp_url, photo_url, location_name, event_date, event_end_date, table_count, admin_token, last_updated)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $this->name,
                $this->bcpEventId,
                $this->bcpUrl,
                $this->photoUrl,
                $this->locationName,
                $this->eventDate,
                $this->eventEndDate,
                $this->tableCount,
                $this->adminToken,
                $this->lastUpdated,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing tournament.
     */
    protected function update(): bool
    {
        Connection::execute(
            'UPDATE tournaments SET name = ?, table_count = ?, last_updated = ? WHERE id = ?',
            [
                $this->name,
                $this->tableCount,
                $this->lastUpdated,
                $this->id,
            ]
        );

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
     * Update BCP refresh timestamp.
     */
    public function touchLastUpdated(): bool
    {
        $this->lastUpdated = date('Y-m-d H:i:s');
        return $this->save();
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
            'photoUrl' => $this->photoUrl,
            'locationName' => $this->locationName,
            'eventDate' => $this->eventDate,
            'eventEndDate' => $this->eventEndDate,
            'tableCount' => $this->tableCount,
            'lastUpdated' => $this->lastUpdated,
        ];
    }
}
