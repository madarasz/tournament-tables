<?php

declare(strict_types=1);

namespace KTTables\Services;

/**
 * Value object representing the cost calculation result.
 *
 * Reference: specs/001-table-allocation/research.md#cost-function
 */
class CostResult
{
    /** @var int */
    public $totalCost;

    /** @var array */
    public $costBreakdown;

    /** @var array */
    public $reasons;

    public function __construct(int $totalCost, array $costBreakdown, array $reasons)
    {
        $this->totalCost = $totalCost;
        $this->costBreakdown = $costBreakdown;
        $this->reasons = $reasons;
    }
}
