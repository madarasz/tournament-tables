<?php

declare(strict_types=1);

namespace TournamentTables\Services;

/**
 * Service for generating table allocations.
 *
 * Implements priority-weighted greedy assignment algorithm per research.md.
 *
 * Algorithm overview:
 * 1. Round 1: Use BCP's original table assignments (FR-007.1)
 * 2. Round 2+: Sort pairings by combined score (descending)
 * 3. For each pairing, calculate cost for each available table
 * 4. Select lowest-cost table (tie-break by original BCP table)
 * 5. Record allocation with audit trail (FR-014)
 */
class AllocationService
{
    /** @var CostCalculator */
    private $costCalculator;

    public function __construct(CostCalculator $costCalculator)
    {
        $this->costCalculator = $costCalculator;
    }

    /**
     * Generate allocations for a round.
     *
     * @param Pairing[] $pairings List of pairings to allocate
     * @param array $tables Available tables [['tableNumber' => int, 'terrainTypeId' => ?int, 'terrainTypeName' => ?string], ...]
     * @param int $roundNumber Round number
     * @param TournamentHistory $history Tournament history service
     * @return AllocationResult
     */
    public function generateAllocations(
        array $pairings,
        array $tables,
        int $roundNumber,
        TournamentHistory $history
    ): AllocationResult {
        $allocations = [];
        $conflicts = [];
        $isRound1 = ($roundNumber === 1);

        // Separate bye pairings from regular pairings
        $regularPairings = [];
        $byePairings = [];
        foreach ($pairings as $pairing) {
            if ($pairing->isBye()) {
                $byePairings[] = $pairing;
            } else {
                $regularPairings[] = $pairing;
            }
        }

        // Round 1: Use BCP's original table assignments (FR-007.1)
        if ($isRound1) {
            $result = $this->generateRound1Allocations($regularPairings, $tables);
            // Append bye allocations
            foreach ($byePairings as $byePairing) {
                $result->allocations[] = $this->createByeAllocation($byePairing, true);
            }
            return $result;
        }

        // Sort regular pairings by combined score (descending), then by BCP ID (ascending) for stability
        $sortedPairings = $this->stableSort($regularPairings);

        // Track which tables are used
        $usedTables = [];

        // Process each regular pairing in order
        foreach ($sortedPairings as $pairing) {
            $result = $this->allocatePairing($pairing, $tables, $usedTables, $history);

            $allocations[] = $result['allocation'];
            $usedTables[] = $result['allocation']['tableNumber'];

            // Collect conflicts
            foreach ($result['allocation']['reason']['conflicts'] as $conflict) {
                $conflicts[] = $conflict;
            }
        }

        // Append bye allocations (no table assignment needed)
        foreach ($byePairings as $byePairing) {
            $allocations[] = $this->createByeAllocation($byePairing, false);
        }

        // Generate summary
        $summary = $this->generateSummary($conflicts);

        return new AllocationResult($allocations, $conflicts, $summary);
    }

    /**
     * Create a bye allocation (no table, no opponent).
     *
     * @param Pairing $pairing Bye pairing
     * @param bool $isRound1 Whether this is round 1
     * @return array Allocation data
     */
    private function createByeAllocation(Pairing $pairing, bool $isRound1): array
    {
        return [
            'tableNumber' => null,
            'terrainType' => null,
            'player1' => [
                'bcpId' => $pairing->player1BcpId,
                'name' => $pairing->player1Name,
                'score' => $pairing->player1Score,
            ],
            'player2' => null,
            'reason' => [
                'timestamp' => date('c'),
                'totalCost' => 0,
                'costBreakdown' => [
                    'tableReuse' => 0,
                    'terrainReuse' => 0,
                    'bcpTableMismatch' => 0,
                ],
                'reasons' => ['Bye - no opponent this round'],
                'alternativesConsidered' => [],
                'isRound1' => $isRound1,
                'isBye' => true,
                'conflicts' => [],
            ],
        ];
    }

    /**
     * Generate Round 1 allocations using BCP's original assignments.
     *
     * FR-007.1: For round 1, use BCP's table assignments.
     */
    private function generateRound1Allocations(array $pairings, array $tables): AllocationResult
    {
        $allocations = [];
        $conflicts = [];
        $timestamp = date('c');

        // Build set of available table numbers
        $availableTableNumbers = [];
        foreach ($tables as $table) {
            $tableNumber = is_array($table) ? $table['tableNumber'] : $table->tableNumber;
            $availableTableNumbers[$tableNumber] = true;
        }

        // Track assigned table numbers to prevent collisions
        $assignedTableNumbers = [];

        foreach ($pairings as $pairing) {
            $tableNumber = $pairing->bcpTableNumber;
            $reason = 'Round 1 - using BCP original assignment';
            $pairingConflicts = [];

            // Validate table number: must be in available tables and not already assigned
            $needsReassignment = false;
            if ($tableNumber === null) {
                $needsReassignment = true;
                $reason = 'Round 1 - BCP table number missing, assigned next available';
            } elseif (!isset($availableTableNumbers[$tableNumber])) {
                $needsReassignment = true;
                $reason = "Round 1 - BCP table {$tableNumber} not in tournament tables, assigned next available";
            } elseif (isset($assignedTableNumbers[$tableNumber])) {
                $needsReassignment = true;
                $reason = "Round 1 - BCP table {$tableNumber} already assigned, assigned next available";
            }

            if ($needsReassignment) {
                // Find next available table
                $tableNumber = null;
                foreach ($availableTableNumbers as $num => $available) {
                    if (!isset($assignedTableNumbers[$num])) {
                        $tableNumber = $num;
                        break;
                    }
                }

                if ($tableNumber === null) {
                    // No tables available - add conflict but continue with placeholder
                    $pairingConflicts[] = [
                        'type' => 'NO_TABLE_AVAILABLE',
                        'message' => "No available tables for pairing {$pairing->player1Name} vs {$pairing->player2Name}",
                    ];
                    $conflicts[] = $pairingConflicts[0];
                    // Use 0 as placeholder for unassigned
                    $tableNumber = 0;
                }
            }

            // Mark table as assigned (if valid)
            if ($tableNumber > 0) {
                $assignedTableNumbers[$tableNumber] = true;
            }

            $allocations[] = [
                'tableNumber' => $tableNumber,
                'player1' => [
                    'bcpId' => $pairing->player1BcpId,
                    'name' => $pairing->player1Name,
                    'score' => $pairing->player1Score,
                ],
                'player2' => [
                    'bcpId' => $pairing->player2BcpId,
                    'name' => $pairing->player2Name,
                    'score' => $pairing->player2Score,
                ],
                'reason' => [
                    'timestamp' => $timestamp,
                    'totalCost' => 0,
                    'costBreakdown' => [
                        'tableReuse' => 0,
                        'terrainReuse' => 0,
                        'bcpTableMismatch' => 0,
                    ],
                    'reasons' => [$reason],
                    'alternativesConsidered' => [],
                    'isRound1' => true,
                    'conflicts' => $pairingConflicts,
                ],
            ];
        }

        $summary = count($conflicts) > 0
            ? 'Round 1 allocations generated with ' . count($conflicts) . ' conflict(s).'
            : 'Round 1 allocations use BCP original table assignments.';

        return new AllocationResult($allocations, $conflicts, $summary);
    }

    /**
     * Allocate a single pairing to the best available table.
     */
    private function allocatePairing(
        Pairing $pairing,
        array $tables,
        array $usedTables,
        TournamentHistory $history
    ): array {
        $timestamp = date('c');
        $bestTable = null;
        $bestCost = null;
        $alternatives = [];

        // Calculate cost for each available table
        foreach ($tables as $table) {
            $tableNumber = $table['tableNumber'];

            // Skip already-used tables
            if (in_array($tableNumber, $usedTables, true)) {
                continue;
            }

            $costResult = $this->costCalculator->calculateForPairing($pairing, $table, $history);

            // Track as alternative (will remove selected table later)
            $alternatives[$tableNumber] = $costResult->totalCost;

            // Select best table (lowest cost, tie-break by original BCP table match)
            if ($bestCost === null || $costResult->totalCost < $bestCost ||
                ($costResult->totalCost === $bestCost && $tableNumber === $pairing->bcpTableNumber)) {
                $bestCost = $costResult->totalCost;
                $bestTable = $table;
            }
        }

        // Should not happen if tables > pairings, but handle gracefully
        if ($bestTable === null) {
            throw new \RuntimeException('No available tables for allocation');
        }

        // Calculate final cost for selected table
        $finalCost = $this->costCalculator->calculateForPairing($pairing, $bestTable, $history);

        // Remove selected table from alternatives
        unset($alternatives[$bestTable['tableNumber']]);

        // Detect conflicts
        $conflicts = $this->detectConflicts($finalCost);

        return [
            'allocation' => [
                'tableNumber' => $bestTable['tableNumber'],
                'terrainType' => $bestTable['terrainTypeName'] ?? null,
                'player1' => [
                    'bcpId' => $pairing->player1BcpId,
                    'name' => $pairing->player1Name,
                    'score' => $pairing->player1Score,
                ],
                'player2' => [
                    'bcpId' => $pairing->player2BcpId,
                    'name' => $pairing->player2Name,
                    'score' => $pairing->player2Score,
                ],
                'reason' => [
                    'timestamp' => $timestamp,
                    'totalCost' => $finalCost->totalCost,
                    'costBreakdown' => $finalCost->costBreakdown,
                    'reasons' => $finalCost->reasons,
                    'alternativesConsidered' => $alternatives,
                    'isRound1' => false,
                    'conflicts' => $conflicts,
                ],
            ],
        ];
    }

    /**
     * Stable sort pairings by combined total score (descending), then BCP ID (ascending).
     *
     * Reference: research.md#determinism-requirements
     *
     * PHP's usort is not stable, so we need to preserve original order for ties.
     *
     * @param Pairing[] $pairings
     * @return Pairing[]
     */
    private function stableSort(array $pairings): array
    {
        // Add original index for stability
        $indexed = [];
        foreach ($pairings as $index => $pairing) {
            $indexed[] = [
                'pairing' => $pairing,
                'index' => $index,
                'score' => $pairing->getCombinedTotalScore(),
                'bcpId' => $pairing->getMinBcpId(),
            ];
        }

        // Sort by score descending, then by BCP ID ascending, then by original index
        usort($indexed, function ($a, $b) {
            // Primary: score descending
            if ($a['score'] !== $b['score']) {
                return $b['score'] <=> $a['score'];
            }
            // Secondary: BCP ID ascending (deterministic)
            if ($a['bcpId'] !== $b['bcpId']) {
                return $a['bcpId'] <=> $b['bcpId'];
            }
            // Tertiary: original index (stability)
            return $a['index'] <=> $b['index'];
        });

        // Extract sorted pairings
        return array_map(function ($item) {
            return $item['pairing'];
        }, $indexed);
    }

    /**
     * Detect conflicts from cost result.
     *
     * FR-010: Flag constraint violations.
     */
    private function detectConflicts(CostResult $costResult): array
    {
        $conflicts = [];

        // Table reuse conflict
        if ($costResult->costBreakdown['tableReuse'] > 0) {
            foreach ($costResult->reasons as $reason) {
                if (strpos($reason, 'previously played on table') !== false) {
                    $conflicts[] = [
                        'type' => 'TABLE_REUSE',
                        'message' => $reason,
                    ];
                }
            }
        }

        // Terrain reuse conflict (note: this is a soft constraint, less severe)
        if ($costResult->costBreakdown['terrainReuse'] > 0) {
            foreach ($costResult->reasons as $reason) {
                if (strpos($reason, 'previously experienced') !== false) {
                    $conflicts[] = [
                        'type' => 'TERRAIN_REUSE',
                        'message' => $reason,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Generate allocation summary.
     */
    private function generateSummary(array $conflicts): string
    {
        if (empty($conflicts)) {
            return 'All allocations optimal - no constraint violations.';
        }

        $tableReuseCount = 0;
        $terrainReuseCount = 0;

        foreach ($conflicts as $conflict) {
            if ($conflict['type'] === 'TABLE_REUSE') {
                $tableReuseCount++;
            } elseif ($conflict['type'] === 'TERRAIN_REUSE') {
                $terrainReuseCount++;
            }
        }

        $parts = [];
        if ($tableReuseCount > 0) {
            $parts[] = "{$tableReuseCount} table reuse conflict(s)";
        }
        if ($terrainReuseCount > 0) {
            $parts[] = "{$terrainReuseCount} terrain reuse conflict(s)";
        }

        return 'Best effort allocation with ' . implode(', ', $parts) . '.';
    }
}
