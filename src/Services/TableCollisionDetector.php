<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use TournamentTables\Database\Connection;

/**
 * Service for detecting table allocation collisions.
 *
 * A collision occurs when the same table is assigned to multiple allocations
 * within the same round. This typically happens during manual allocation edits.
 */
class TableCollisionDetector
{
    /**
     * Check if a round has any table collisions.
     */
    public function hasCollisions(int $roundId): bool
    {
        $count = Connection::fetchColumn(
            'SELECT COUNT(*) FROM (
                SELECT table_id FROM allocations
                WHERE round_id = ?
                GROUP BY table_id
                HAVING COUNT(*) > 1
            ) AS duplicates',
            [$roundId]
        );
        return (int) $count > 0;
    }

    /**
     * Get all table collisions for a round.
     *
     * @return array<array{tableId: int, tableNumber: int, allocationIds: int[]}>
     */
    public function getCollisions(int $roundId): array
    {
        $rows = Connection::fetchAll(
            'SELECT a.table_id, t.table_number, GROUP_CONCAT(a.id) as allocation_ids
             FROM allocations a
             JOIN tables t ON a.table_id = t.id
             WHERE a.round_id = ?
             GROUP BY a.table_id, t.table_number
             HAVING COUNT(*) > 1',
            [$roundId]
        );

        $collisions = [];
        foreach ($rows as $row) {
            $collisions[] = [
                'tableId' => (int) $row['table_id'],
                'tableNumber' => (int) $row['table_number'],
                'allocationIds' => array_map('intval', explode(',', $row['allocation_ids'])),
            ];
        }
        return $collisions;
    }

    /**
     * Get collision count for a round.
     */
    public function getCollisionCount(int $roundId): int
    {
        $count = Connection::fetchColumn(
            'SELECT COUNT(*) FROM (
                SELECT table_id FROM allocations
                WHERE round_id = ?
                GROUP BY table_id
                HAVING COUNT(*) > 1
            ) AS duplicates',
            [$roundId]
        );
        return (int) $count;
    }

    /**
     * Check if a specific table has a collision in a round.
     */
    public function hasTableCollision(int $roundId, int $tableId): bool
    {
        $count = Connection::fetchColumn(
            'SELECT COUNT(*) FROM allocations WHERE round_id = ? AND table_id = ?',
            [$roundId, $tableId]
        );
        return (int) $count > 1;
    }
}
