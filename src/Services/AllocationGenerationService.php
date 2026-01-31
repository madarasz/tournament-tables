<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Player;
use TournamentTables\Models\Allocation;
use TournamentTables\Database\Connection;

/**
 * Service for generating table allocations for a round.
 *
 * Extracted from RoundController to separate business logic from HTTP handling.
 */
class AllocationGenerationService
{
    /** @var AllocationService */
    private $allocationService;

    public function __construct(AllocationService $allocationService = null)
    {
        $this->allocationService = $allocationService ?? new AllocationService(new CostCalculator());
    }

    /**
     * Generate optimized table allocations for a round.
     *
     * @param int $tournamentId Tournament ID
     * @param int $roundNumber Round number
     * @param Round $round Round model
     * @return array{allocations: array, conflicts: array, summary: string}
     */
    public function generate(int $tournamentId, int $roundNumber, Round $round): array
    {
        // Get existing allocations to extract pairings
        $existingAllocations = Allocation::findByRound($round->id);

        // Build BCP table lookup from existing allocations before deleting
        $bcpTableLookup = $this->buildBcpTableLookup($existingAllocations);

        // Build pairings from existing allocations
        $pairings = $this->reconstructPairings($existingAllocations, $bcpTableLookup);

        // Get tables and format for allocation service
        $tables = Table::findByTournament($tournamentId);
        $tablesArray = $this->formatTableData($tables);

        // Generate allocations using the allocation algorithm
        $history = new TournamentHistory($tournamentId, $roundNumber);
        $result = $this->allocationService->generateAllocations(
            $pairings,
            $tablesArray,
            $roundNumber,
            $history
        );

        // Save allocations in transaction
        return $this->saveAllocations($round, $tournamentId, $tables, $result, $bcpTableLookup);
    }

    /**
     * Build a lookup of BCP table numbers from existing allocations.
     *
     * @param Allocation[] $allocations Existing allocations
     * @return array<string, int> Map of "player1BcpId:player2BcpId" => bcpTableNumber
     */
    private function buildBcpTableLookup(array $allocations): array
    {
        $lookup = [];
        foreach ($allocations as $alloc) {
            if ($alloc->bcpTableNumber !== null) {
                $p1 = Player::find($alloc->player1Id);
                $p2 = Player::find($alloc->player2Id);
                if ($p1 && $p2) {
                    $key = $p1->bcpPlayerId . ':' . $p2->bcpPlayerId;
                    $lookup[$key] = $alloc->bcpTableNumber;
                }
            }
        }
        return $lookup;
    }

    /**
     * Reconstruct Pairing objects from existing allocations.
     *
     * @param Allocation[] $allocations Existing allocations
     * @param array<string, int> $bcpTableLookup BCP table number lookup
     * @return Pairing[] Array of pairing objects
     */
    private function reconstructPairings(array $allocations, array $bcpTableLookup): array
    {
        $pairings = [];
        foreach ($allocations as $allocation) {
            $player1 = Player::find($allocation->player1Id);

            if ($player1 === null) {
                continue;
            }

            // Handle bye allocations
            if ($allocation->isBye()) {
                $pairings[] = new Pairing(
                    $player1->bcpPlayerId,
                    $player1->name,
                    $allocation->player1Score,
                    null, // player2BcpId
                    null, // player2Name
                    0,    // player2Score
                    null, // bcpTableNumber
                    $player1->totalScore,
                    0     // player2TotalScore
                );
                continue;
            }

            // Regular pairing
            $player2 = Player::find($allocation->player2Id);
            if ($player2 === null) {
                continue;
            }

            // Look up BCP table number from preserved lookup
            $bcpTableKey = $player1->bcpPlayerId . ':' . $player2->bcpPlayerId;
            $bcpTableNumber = $bcpTableLookup[$bcpTableKey] ?? null;

            $pairings[] = new Pairing(
                $player1->bcpPlayerId,
                $player1->name,
                $allocation->player1Score,
                $player2->bcpPlayerId,
                $player2->name,
                $allocation->player2Score,
                $bcpTableNumber,
                $player1->totalScore,
                $player2->totalScore
            );
        }
        return $pairings;
    }

    /**
     * Format table data for the allocation service.
     *
     * @param Table[] $tables Table models
     * @return array[] Array of table data arrays
     */
    private function formatTableData(array $tables): array
    {
        return array_map(function ($t) {
            $terrain = $t->getTerrainType();
            return [
                'id' => $t->id,
                'tableNumber' => $t->tableNumber,
                'terrainTypeId' => $t->terrainTypeId,
                'terrainTypeName' => $terrain ? $terrain->name : null,
            ];
        }, $tables);
    }

    /**
     * Save new allocations to the database in a transaction.
     *
     * @param Round $round Round model
     * @param int $tournamentId Tournament ID
     * @param Table[] $tables Table models
     * @param AllocationResult $result Allocation result from service
     * @param array<string, int> $bcpTableLookup BCP table number lookup
     * @return array{allocations: array, conflicts: array, summary: string}
     */
    private function saveAllocations(
        Round $round,
        int $tournamentId,
        array $tables,
        AllocationResult $result,
        array $bcpTableLookup
    ): array {
        Connection::beginTransaction();

        try {
            // Clear existing allocations
            $round->clearAllocations();

            // Create player ID lookup
            $playerLookup = [];
            foreach (Player::findByTournament($tournamentId) as $player) {
                $playerLookup[$player->bcpPlayerId] = $player->id;
            }

            // Create table ID lookup
            $tableLookup = [];
            foreach ($tables as $table) {
                $tableLookup[$table->tableNumber] = $table->id;
            }

            // Save new allocations
            $savedAllocations = [];
            foreach ($result->allocations as $allocData) {
                $player1Id = $playerLookup[$allocData['player1']['bcpId']] ?? null;

                if ($player1Id === null) {
                    continue;
                }

                // Handle bye allocations (no player2, no table)
                $isBye = $allocData['player2'] === null || ($allocData['reason']['isBye'] ?? false);

                if ($isBye) {
                    $allocation = new Allocation(
                        null,
                        $round->id,
                        null, // No table for bye
                        $player1Id,
                        null, // No player2 for bye
                        $allocData['player1']['score'],
                        0,
                        $allocData['reason'],
                        null  // No BCP table for bye
                    );
                    $allocation->save();
                    $savedAllocations[] = $allocation->toArray();
                    continue;
                }

                // Regular allocation
                $player2Id = $playerLookup[$allocData['player2']['bcpId']] ?? null;
                $tableId = $tableLookup[$allocData['tableNumber']] ?? null;

                if ($player2Id === null || $tableId === null) {
                    continue;
                }

                // Retrieve preserved BCP table number from lookup
                $bcpTableKey = $allocData['player1']['bcpId'] . ':' . $allocData['player2']['bcpId'];
                $bcpTableNumber = $bcpTableLookup[$bcpTableKey] ?? null;

                $allocation = new Allocation(
                    null,
                    $round->id,
                    $tableId,
                    $player1Id,
                    $player2Id,
                    $allocData['player1']['score'],
                    $allocData['player2']['score'],
                    $allocData['reason'],
                    $bcpTableNumber
                );
                $allocation->save();

                $savedAllocations[] = $allocation->toArray();
            }

            Connection::commit();

            return [
                'allocations' => $savedAllocations,
                'conflicts' => $result->conflicts,
                'summary' => $result->summary,
            ];
        } catch (\Exception $e) {
            Connection::rollBack();
            throw $e;
        }
    }
}
