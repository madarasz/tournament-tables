<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use PDO;
use RuntimeException;
use InvalidArgumentException;

/**
 * Service for editing table allocations.
 *
 * Provides methods for manually adjusting table assignments and swapping tables.
 * Recalculates conflicts after edits per FR-010.
 *
 * Reference: specs/001-table-allocation/tasks.md#T068
 */
class AllocationEditService
{
    use DatabaseQueryHelper;

    /** @var PDO */
    private $db;

    /** @var CostCalculator */
    private $costCalculator;

    public function __construct(PDO $db, CostCalculator $costCalculator)
    {
        $this->db = $db;
        $this->costCalculator = $costCalculator;
    }

    /**
     * Edit table assignment for an allocation.
     *
     * @param int $allocationId Allocation ID to edit
     * @param int $newTableId New table ID to assign
     * @return array Result with success flag and conflicts
     * @throws RuntimeException If allocation not found or validation fails
     */
    public function editTableAssignment(int $allocationId, int $newTableId): array
    {
        // Get allocation
        $allocation = $this->getAllocation($allocationId);
        if (!$allocation) {
            throw new RuntimeException('Allocation not found');
        }

        // Verify new table exists and belongs to same tournament
        $newTable = $this->getTable($newTableId);
        if (!$newTable) {
            throw new RuntimeException('Table not found');
        }

        // Get round info
        $round = $this->getRound($allocation['round_id']);
        if (!$round) {
            throw new RuntimeException('Round not found');
        }

        // Verify table belongs to same tournament
        if ($newTable['tournament_id'] !== $round['tournament_id']) {
            throw new RuntimeException('Table does not belong to this tournament');
        }

        // Check if table is already used in this round (by a different allocation)
        $existingAllocation = $this->getAllocationByRoundAndTable($allocation['round_id'], $newTableId);

        // Recalculate conflicts for this allocation
        $conflicts = $this->calculateConflicts(
            $allocation['player1_id'],
            $allocation['player2_id'],
            $newTableId,
            $round['tournament_id'],
            $round['round_number']
        );

        // Add table collision conflict if another allocation uses this table
        if ($existingAllocation && $existingAllocation['id'] !== $allocationId) {
            $conflicts[] = [
                'type' => 'TABLE_COLLISION',
                'message' => 'Table ' . $newTable['table_number'] . ' is also assigned to another pairing in this round',
                'otherAllocationId' => $existingAllocation['id'],
            ];
        }

        // Update allocation with new table and conflicts
        $stmt = $this->db->prepare(
            'UPDATE allocations SET table_id = ?, allocation_reason = ? WHERE id = ?'
        );
        $stmt->execute([$newTableId, $this->encodeAllocationReason($conflicts), $allocationId]);

        return [
            'success' => true,
            'allocationId' => $allocationId,
            'newTableId' => $newTableId,
            'conflicts' => $conflicts,
        ];
    }

    /**
     * Swap table assignments between two allocations.
     *
     * @param int $allocationId1 First allocation ID
     * @param int $allocationId2 Second allocation ID
     * @return array Result with success flag and updated allocations
     * @throws RuntimeException If allocations not found or validation fails
     */
    public function swapTables(int $allocationId1, int $allocationId2): array
    {
        // Validate different allocations
        if ($allocationId1 === $allocationId2) {
            throw new InvalidArgumentException('Cannot swap an allocation with itself');
        }

        // Get both allocations
        $allocation1 = $this->getAllocation($allocationId1);
        $allocation2 = $this->getAllocation($allocationId2);

        if (!$allocation1 || !$allocation2) {
            throw new RuntimeException('One or both allocations not found');
        }

        // Verify both are in the same round
        if ($allocation1['round_id'] !== $allocation2['round_id']) {
            throw new RuntimeException('Both allocations must be in the same round');
        }

        // Get round info for conflict calculation
        $round = $this->getRound($allocation1['round_id']);
        if (!$round) {
            throw new RuntimeException('Round not found');
        }

        // Perform swap in transaction
        $this->db->beginTransaction();

        try {
            $table1 = $allocation1['table_id'];
            $table2 = $allocation2['table_id'];

            // Calculate conflicts for both allocations
            $conflicts1 = $this->calculateConflicts(
                $allocation1['player1_id'],
                $allocation1['player2_id'],
                $table2,
                $round['tournament_id'],
                $round['round_number']
            );

            $conflicts2 = $this->calculateConflicts(
                $allocation2['player1_id'],
                $allocation2['player2_id'],
                $table1,
                $round['tournament_id'],
                $round['round_number']
            );

            // Swap tables and update conflicts
            $stmt = $this->db->prepare('UPDATE allocations SET table_id = ?, allocation_reason = ? WHERE id = ?');
            $stmt->execute([$table2, $this->encodeAllocationReason($conflicts1), $allocationId1]);
            $stmt->execute([$table1, $this->encodeAllocationReason($conflicts2), $allocationId2]);

            $this->db->commit();

            return [
                'success' => true,
                'allocation1' => [
                    'id' => $allocationId1,
                    'newTableId' => $table2,
                    'conflicts' => $conflicts1,
                ],
                'allocation2' => [
                    'id' => $allocationId2,
                    'newTableId' => $table1,
                    'conflicts' => $conflicts2,
                ],
            ];
        } catch (\Exception $e) {
            $this->db->rollBack();
            throw new RuntimeException('Failed to swap tables: ' . $e->getMessage());
        }
    }

    /**
     * Calculate conflicts for an allocation.
     *
     * Checks if players have used the table or terrain before.
     * Delegates to TournamentHistory for history queries.
     *
     * @param int $player1Id Player 1 ID
     * @param int $player2Id Player 2 ID
     * @param int $tableId Table ID
     * @param int $tournamentId Tournament ID
     * @param int $currentRound Current round number
     * @return array List of conflicts
     */
    private function calculateConflicts(
        int $player1Id,
        int $player2Id,
        int $tableId,
        int $tournamentId,
        int $currentRound
    ): array {
        $conflicts = [];

        // Get table info
        $table = $this->getTable($tableId);

        // Use TournamentHistory for history checks (avoid duplicate query logic)
        $history = $this->getTournamentHistory($tournamentId, $currentRound);

        // Check table reuse for each player
        foreach ([$player1Id, $player2Id] as $playerId) {
            if ($history->hasPlayerUsedTable($playerId, (int) $table['table_number'])) {
                $player = $this->getPlayer($playerId);
                $conflicts[] = [
                    'type' => 'TABLE_REUSE',
                    'message' => $player['name'] . ' previously played on table ' . $table['table_number'],
                    'playerId' => $playerId,
                ];
            }
        }

        // Check terrain reuse if table has terrain type
        if ($table['terrain_type_id'] !== null) {
            $terrainTypeId = (int) $table['terrain_type_id'];
            foreach ([$player1Id, $player2Id] as $playerId) {
                if ($history->hasPlayerExperiencedTerrain($playerId, $terrainTypeId)) {
                    $player = $this->getPlayer($playerId);
                    $terrainType = $this->getTerrainType($terrainTypeId);
                    $conflicts[] = [
                        'type' => 'TERRAIN_REUSE',
                        'message' => $player['name'] . ' previously experienced ' . $terrainType['name'],
                        'playerId' => $playerId,
                    ];
                }
            }
        }

        return $conflicts;
    }

    /**
     * Get TournamentHistory instance for conflict checking.
     *
     * Delegates to TournamentHistory to avoid duplicate query logic.
     * Passes the injected PDO to enable unit testing.
     */
    private function getTournamentHistory(int $tournamentId, int $currentRound): TournamentHistory
    {
        return new TournamentHistory($tournamentId, $currentRound, $this->db);
    }

    /**
     * Encode conflicts as JSON allocation reason.
     *
     * @param array $conflicts List of conflict arrays
     * @return string JSON string for allocation_reason column
     */
    private function encodeAllocationReason(array $conflicts): string
    {
        return json_encode(['conflicts' => $conflicts]);
    }

    // Helper methods using DatabaseQueryHelper trait

    private function getAllocation(int $allocationId): ?array
    {
        return $this->fetchById('allocations', $allocationId);
    }

    private function getAllocationByRoundAndTable(int $roundId, int $tableId): ?array
    {
        return $this->fetchOneWhere('allocations', [
            'round_id' => $roundId,
            'table_id' => $tableId,
        ]);
    }

    private function getTable(int $tableId): ?array
    {
        return $this->fetchById('tables', $tableId);
    }

    private function getRound(int $roundId): ?array
    {
        // This needs a join, so we use a custom query
        $stmt = $this->db->prepare(
            'SELECT r.*, t.id as tournament_id FROM rounds r JOIN tournaments t ON r.tournament_id = t.id WHERE r.id = ?'
        );
        $stmt->execute([$roundId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getPlayer(int $playerId): ?array
    {
        return $this->fetchById('players', $playerId);
    }

    private function getTerrainType(int $terrainTypeId): ?array
    {
        return $this->fetchById('terrain_types', $terrainTypeId);
    }
}
