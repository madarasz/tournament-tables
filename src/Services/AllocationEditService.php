<?php

declare(strict_types=1);

namespace KTTables\Services;

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
        if ($existingAllocation && $existingAllocation['id'] !== $allocationId) {
            throw new RuntimeException('Table ' . $newTable['table_number'] . ' is already assigned in this round');
        }

        // Update allocation
        $stmt = $this->db->prepare(
            'UPDATE allocations SET table_id = ? WHERE id = ?'
        );
        $stmt->execute([$newTableId, $allocationId]);

        // Recalculate conflicts for this allocation
        $conflicts = $this->calculateConflicts(
            $allocation['player1_id'],
            $allocation['player2_id'],
            $newTableId,
            $round['tournament_id'],
            $round['round_number']
        );

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

        // Perform swap in transaction
        $this->db->beginTransaction();

        try {
            $table1 = $allocation1['table_id'];
            $table2 = $allocation2['table_id'];

            // Swap tables
            $stmt = $this->db->prepare('UPDATE allocations SET table_id = ? WHERE id = ?');
            $stmt->execute([$table2, $allocationId1]);
            $stmt->execute([$table1, $allocationId2]);

            $this->db->commit();

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

        // Check table reuse for each player
        foreach ([$player1Id, $player2Id] as $playerId) {
            if ($this->hasPlayerUsedTable($playerId, $table['table_number'], $tournamentId, $currentRound)) {
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
            foreach ([$player1Id, $player2Id] as $playerId) {
                if ($this->hasPlayerExperiencedTerrain($playerId, $table['terrain_type_id'], $tournamentId, $currentRound)) {
                    $player = $this->getPlayer($playerId);
                    $terrainType = $this->getTerrainType($table['terrain_type_id']);
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
     * Check if player has used a table before.
     */
    private function hasPlayerUsedTable(int $playerId, int $tableNumber, int $tournamentId, int $currentRound): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as count
            FROM allocations a
            JOIN rounds r ON a.round_id = r.id
            JOIN tables t ON a.table_id = t.id
            WHERE r.tournament_id = ?
              AND (a.player1_id = ? OR a.player2_id = ?)
              AND t.table_number = ?
              AND r.round_number < ?
        ');
        $stmt->execute([$tournamentId, $playerId, $playerId, $tableNumber, $currentRound]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    /**
     * Check if player has experienced a terrain type before.
     */
    private function hasPlayerExperiencedTerrain(int $playerId, int $terrainTypeId, int $tournamentId, int $currentRound): bool
    {
        $stmt = $this->db->prepare('
            SELECT COUNT(*) as count
            FROM allocations a
            JOIN rounds r ON a.round_id = r.id
            JOIN tables t ON a.table_id = t.id
            WHERE r.tournament_id = ?
              AND (a.player1_id = ? OR a.player2_id = ?)
              AND t.terrain_type_id = ?
              AND r.round_number < ?
        ');
        $stmt->execute([$tournamentId, $playerId, $playerId, $terrainTypeId, $currentRound]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result['count'] > 0;
    }

    // Helper methods

    private function getAllocation(int $allocationId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM allocations WHERE id = ?');
        $stmt->execute([$allocationId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getAllocationByRoundAndTable(int $roundId, int $tableId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM allocations WHERE round_id = ? AND table_id = ?');
        $stmt->execute([$roundId, $tableId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getTable(int $tableId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM tables WHERE id = ?');
        $stmt->execute([$tableId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getRound(int $roundId): ?array
    {
        $stmt = $this->db->prepare('SELECT r.*, t.id as tournament_id FROM rounds r JOIN tournaments t ON r.tournament_id = t.id WHERE r.id = ?');
        $stmt->execute([$roundId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getPlayer(int $playerId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM players WHERE id = ?');
        $stmt->execute([$playerId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }

    private function getTerrainType(int $terrainTypeId): ?array
    {
        $stmt = $this->db->prepare('SELECT * FROM terrain_types WHERE id = ?');
        $stmt->execute([$terrainTypeId]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        return $result ?: null;
    }
}
