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
    public function __construct(
        public readonly string $player1BcpId,
        public readonly string $player1Name,
        public readonly int $player1Score,
        public readonly ?string $player2BcpId,
        public readonly ?string $player2Name,
        public readonly int $player2Score,
        public readonly ?int $bcpTableNumber,
        public readonly int $player1TotalScore = 0,
        public readonly int $player2TotalScore = 0,
        public readonly ?string $player1Faction = null,
        public readonly ?string $player2Faction = null
    ) {}

    /**
     * Check if this pairing is a bye (no opponent).
     */
    public function isBye(): bool
    {
        return $this->player2BcpId === null;
    }

    /**
     * Get combined round score for both players.
     *
     * Used for storing in allocations.
     */
    public function getCombinedScore(): int
    {
        return $this->player1Score + $this->player2Score;
    }

    /**
     * Get combined total tournament score for both players.
     *
     * Used for sorting pairings (higher scores get lower table numbers).
     * For bye pairings, returns only player1's score.
     */
    public function getCombinedTotalScore(): int
    {
        if ($this->isBye()) {
            return $this->player1TotalScore;
        }
        return $this->player1TotalScore + $this->player2TotalScore;
    }

    /**
     * Get the lower BCP ID for deterministic tie-breaking.
     * For bye pairings, returns player1's BCP ID.
     */
    public function getMinBcpId(): string
    {
        if ($this->isBye()) {
            return $this->player1BcpId;
        }
        return min($this->player1BcpId, $this->player2BcpId);
    }
}
