<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Value object representing a pairing from BCP.
 *
 * Reference: specs/001-table-allocation/research.md#expected-data-fields
 */
class Pairing
{
    /** @var string */
    public $player1BcpId;

    /** @var string */
    public $player1Name;

    /** @var int */
    public $player1Score;

    /** @var string */
    public $player2BcpId;

    /** @var string */
    public $player2Name;

    /** @var int */
    public $player2Score;

    /** @var int|null */
    public $bcpTableNumber;

    public function __construct(
        string $player1BcpId,
        string $player1Name,
        int $player1Score,
        string $player2BcpId,
        string $player2Name,
        int $player2Score,
        ?int $bcpTableNumber
    ) {
        $this->player1BcpId = $player1BcpId;
        $this->player1Name = $player1Name;
        $this->player1Score = $player1Score;
        $this->player2BcpId = $player2BcpId;
        $this->player2Name = $player2Name;
        $this->player2Score = $player2Score;
        $this->bcpTableNumber = $bcpTableNumber;
    }

    /**
     * Get combined score for both players.
     *
     * Used for sorting pairings (higher scores get lower table numbers).
     */
    public function getCombinedScore(): int
    {
        return $this->player1Score + $this->player2Score;
    }

    /**
     * Get the lower BCP ID for deterministic tie-breaking.
     */
    public function getMinBcpId(): string
    {
        return min($this->player1BcpId, $this->player2BcpId);
    }
}
