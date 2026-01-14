<?php

declare(strict_types=1);

namespace TournamentTables\Models;

use TournamentTables\Database\Connection;

/**
 * Player entity.
 *
 * Represents a tournament participant imported from BCP.
 * Reference: specs/001-table-allocation/data-model.md#player
 */
class Player
{
    /** @var int|null */
    public $id;

    /** @var int */
    public $tournamentId;

    /** @var string */
    public $bcpPlayerId;

    /** @var string */
    public $name;

    public function __construct(
        ?int $id = null,
        int $tournamentId = 0,
        string $bcpPlayerId = '',
        string $name = ''
    ) {
        $this->id = $id;
        $this->tournamentId = $tournamentId;
        $this->bcpPlayerId = $bcpPlayerId;
        $this->name = $name;
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tournament_id'],
            $row['bcp_player_id'],
            $row['name']
        );
    }

    /**
     * Find player by ID.
     */
    public static function find(int $id): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM players WHERE id = ?',
            [$id]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all players for a tournament.
     *
     * @return Player[]
     */
    public static function findByTournament(int $tournamentId): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM players WHERE tournament_id = ? ORDER BY name ASC',
            [$tournamentId]
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Find player by tournament and BCP player ID.
     */
    public static function findByTournamentAndBcpId(int $tournamentId, string $bcpPlayerId): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM players WHERE tournament_id = ? AND bcp_player_id = ?',
            [$tournamentId, $bcpPlayerId]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find or create a player.
     */
    public static function findOrCreate(int $tournamentId, string $bcpPlayerId, string $name): self
    {
        $player = self::findByTournamentAndBcpId($tournamentId, $bcpPlayerId);
        if ($player !== null) {
            // Update name if it changed
            if ($player->name !== $name) {
                $player->name = $name;
                $player->save();
            }
            return $player;
        }

        $player = new self(null, $tournamentId, $bcpPlayerId, $name);
        $player->save();
        return $player;
    }

    /**
     * Save the player (insert or update).
     */
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Insert a new player.
     */
    private function insert(): bool
    {
        Connection::execute(
            'INSERT INTO players (tournament_id, bcp_player_id, name)
             VALUES (?, ?, ?)',
            [
                $this->tournamentId,
                $this->bcpPlayerId,
                $this->name,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing player.
     */
    private function update(): bool
    {
        Connection::execute(
            'UPDATE players SET name = ? WHERE id = ?',
            [
                $this->name,
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
        ];
    }
}
