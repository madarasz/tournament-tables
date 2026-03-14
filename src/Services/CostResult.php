<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Value object representing the cost calculation result.
 *
 * Reference: specs/001-table-allocation/research.md#cost-function
 */
class CostResult
{
    public function __construct(
        public readonly int $totalCost,
        public readonly array $costBreakdown,
        public readonly array $reasons
    ) {}
}
