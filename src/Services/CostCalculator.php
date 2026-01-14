<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Cost calculator for table allocation.
 *
 * Implements the priority-weighted cost function per research.md#cost-function.
 *
 * Cost weights (P1 > P2 > P3):
 * - P1: Table reuse = 100000 (avoid tables players have used)
 * - P2: Terrain reuse = 10000 (prefer new terrain types)
 * - P3: Table number = 1 (lower tables for higher scores)
 */
class CostCalculator
{
    /**
     * Cost for table reuse (P1 - highest priority).
     * FR-007.2: Avoid tables players have used before.
     */
    const COST_TABLE_REUSE = 100000;

    /**
     * Cost for terrain reuse (P2 - medium priority).
     * FR-007.3: Prefer terrain types not yet experienced.
     */
    const COST_TERRAIN_REUSE = 10000;

    /**
     * Cost multiplier for table number (P3 - lowest priority).
     * FR-007.4: Higher-scoring players get lower table numbers.
     */
    const COST_TABLE_NUMBER = 1;

    /**
     * Calculate the cost of assigning a pairing to a table.
     *
     * @param int|string $player1Id Player 1 ID
     * @param int $tableNumber Table number
     * @param int|null $terrainTypeId Terrain type ID (null if not assigned)
     * @param string|null $terrainTypeName Terrain type name for reasons
     * @param TournamentHistory $history Tournament history service
     * @param int|string|null $player2Id Player 2 ID (optional, for both-player checks)
     */
    public function calculate(
        $player1Id,
        int $tableNumber,
        ?int $terrainTypeId,
        ?string $terrainTypeName,
        TournamentHistory $history,
        $player2Id = null
    ): CostResult {
        $tableReuseCost = 0;
        $terrainReuseCost = 0;
        $reasons = [];

        // P1: Check table reuse for player 1
        if ($history->hasPlayerUsedTable($player1Id, $tableNumber)) {
            $tableReuseCost += self::COST_TABLE_REUSE;
            $reasons[] = "Player 1 previously played on table {$tableNumber}";
        }

        // P1: Check table reuse for player 2
        if ($player2Id !== null && $history->hasPlayerUsedTable($player2Id, $tableNumber)) {
            $tableReuseCost += self::COST_TABLE_REUSE;
            $reasons[] = "Player 2 previously played on table {$tableNumber}";
        }

        // P2: Check terrain reuse for player 1
        if ($terrainTypeId !== null) {
            if ($history->hasPlayerExperiencedTerrain($player1Id, $terrainTypeId)) {
                $terrainReuseCost += self::COST_TERRAIN_REUSE;
                $reasons[] = "Player 1 previously experienced {$terrainTypeName}";
            }

            // P2: Check terrain reuse for player 2
            if ($player2Id !== null && $history->hasPlayerExperiencedTerrain($player2Id, $terrainTypeId)) {
                $terrainReuseCost += self::COST_TERRAIN_REUSE;
                $reasons[] = "Player 2 previously experienced {$terrainTypeName}";
            }
        }

        // P3: Table number cost
        $tableNumberCost = $tableNumber * self::COST_TABLE_NUMBER;

        // Total cost
        $totalCost = $tableReuseCost + $terrainReuseCost + $tableNumberCost;

        return new CostResult(
            $totalCost,
            [
                'tableReuse' => $tableReuseCost,
                'terrainReuse' => $terrainReuseCost,
                'tableNumber' => $tableNumberCost,
            ],
            $reasons
        );
    }

    /**
     * Calculate cost for a pairing and table.
     *
     * Convenience method that takes a Pairing object.
     */
    public function calculateForPairing(
        Pairing $pairing,
        array $table,
        TournamentHistory $history
    ): CostResult {
        return $this->calculate(
            $pairing->player1BcpId,
            $table['tableNumber'],
            $table['terrainTypeId'] ?? null,
            $table['terrainTypeName'] ?? null,
            $history,
            $pairing->player2BcpId
        );
    }
}
