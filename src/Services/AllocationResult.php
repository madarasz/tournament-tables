<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Value object representing the result of allocation generation.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#GenerateResponse
 */
class AllocationResult
{
    /** @var array */
    public $allocations;

    /** @var array */
    public $conflicts;

    /** @var string */
    public $summary;

    public function __construct(array $allocations, array $conflicts, string $summary)
    {
        $this->allocations = $allocations;
        $this->conflicts = $conflicts;
        $this->summary = $summary;
    }
}
