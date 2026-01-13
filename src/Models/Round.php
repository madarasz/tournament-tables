<?php

declare(strict_types=1);

namespace KTTables\Models;

use KTTables\Database\Connection;

/**
 * Round entity.
 *
 * Represents a tournament round containing pairings and allocations.
 * Reference: specs/001-table-allocation/data-model.md#round
 */
class Round
{
    /** @var int|null */
    public $id;

    /** @var int */
    public $tournamentId;

    /** @var int */
    public $roundNumber;

    /** @var bool */
    public $isPublished;

    public function __construct(
        ?int $id = null,
        int $tournamentId = 0,
        int $roundNumber = 0,
        bool $isPublished = false
    ) {
        $this->id = $id;
        $this->tournamentId = $tournamentId;
        $this->roundNumber = $roundNumber;
        $this->isPublished = $isPublished;
    }

    /**
     * Create instance from database row.
     */
    public static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['tournament_id'],
            (int) $row['round_number'],
            (bool) $row['is_published']
        );
    }

    /**
     * Find round by ID.
     */
    public static function find(int $id): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM rounds WHERE id = ?',
            [$id]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find all rounds for a tournament.
     *
     * @return Round[]
     */
    public static function findByTournament(int $tournamentId): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM rounds WHERE tournament_id = ? ORDER BY round_number ASC',
            [$tournamentId]
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Find published rounds for a tournament.
     *
     * @return Round[]
     */
    public static function findPublishedByTournament(int $tournamentId): array
    {
        $rows = Connection::fetchAll(
            'SELECT * FROM rounds WHERE tournament_id = ? AND is_published = TRUE ORDER BY round_number ASC',
            [$tournamentId]
        );

        return array_map([self::class, 'fromRow'], $rows);
    }

    /**
     * Find round by tournament and round number.
     */
    public static function findByTournamentAndNumber(int $tournamentId, int $roundNumber): ?self
    {
        $row = Connection::fetchOne(
            'SELECT * FROM rounds WHERE tournament_id = ? AND round_number = ?',
            [$tournamentId, $roundNumber]
        );

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Find or create a round.
     */
    public static function findOrCreate(int $tournamentId, int $roundNumber): self
    {
        $round = self::findByTournamentAndNumber($tournamentId, $roundNumber);
        if ($round !== null) {
            return $round;
        }

        $round = new self(null, $tournamentId, $roundNumber, false);
        $round->save();
        return $round;
    }

    /**
     * Save the round (insert or update).
     */
    public function save(): bool
    {
        if ($this->id === null) {
            return $this->insert();
        }
        return $this->update();
    }

    /**
     * Insert a new round.
     */
    private function insert(): bool
    {
        Connection::execute(
            'INSERT INTO rounds (tournament_id, round_number, is_published)
             VALUES (?, ?, ?)',
            [
                $this->tournamentId,
                $this->roundNumber,
                $this->isPublished ? 1 : 0,
            ]
        );

        $this->id = Connection::lastInsertId();
        return true;
    }

    /**
     * Update an existing round.
     */
    private function update(): bool
    {
        Connection::execute(
            'UPDATE rounds SET is_published = ? WHERE id = ?',
            [
                $this->isPublished ? 1 : 0,
                $this->id,
            ]
        );

        return true;
    }

    /**
     * Publish the round.
     */
    public function publish(): bool
    {
        $this->isPublished = true;
        return $this->save();
    }

    /**
     * Get all allocations for this round.
     *
     * @return Allocation[]
     */
    public function getAllocations(): array
    {
        return Allocation::findByRound($this->id);
    }

    /**
     * Get allocation count for this round.
     */
    public function getAllocationCount(): int
    {
        $count = Connection::fetchColumn(
            'SELECT COUNT(*) FROM allocations WHERE round_id = ?',
            [$this->id]
        );
        return (int) $count;
    }

    /**
     * Delete all allocations for this round.
     */
    public function clearAllocations(): void
    {
        Connection::execute('DELETE FROM allocations WHERE round_id = ?', [$this->id]);
    }

    /**
     * Convert to array for JSON serialization.
     */
    public function toArray(): array
    {
        return [
            'roundNumber' => $this->roundNumber,
            'isPublished' => $this->isPublished,
            'allocationCount' => $this->getAllocationCount(),
        ];
    }
}
