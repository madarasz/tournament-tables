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
    public function __construct(
        public readonly array $allocations,
        public readonly array $conflicts,
        public readonly string $summary
    ) {}
}
