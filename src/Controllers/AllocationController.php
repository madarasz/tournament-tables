<?php

declare(strict_types=1);

namespace KTTables\Controllers;

use KTTables\Models\Allocation;
use KTTables\Models\Round;
use KTTables\Models\Table;
use KTTables\Middleware\AdminAuthMiddleware;
use KTTables\Database\Connection;

/**
 * Allocation editing controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/allocations
 */
class AllocationController extends BaseController
{
    /**
     * PATCH /api/allocations/{id} - Edit table assignment.
     *
     * Reference: FR-008
     */
    public function update(array $params, ?array $body): void
    {
        $allocationId = (int) ($params['id'] ?? 0);

        $allocation = Allocation::find($allocationId);
        if ($allocation === null) {
            $this->notFound('Allocation');
            return;
        }

        // Get the round to verify tournament ownership
        $round = Round::find($allocation->roundId);
        if ($round === null) {
            $this->notFound('Round');
            return;
        }

        // Verify authenticated tournament matches the allocation's tournament
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $round->tournamentId) {
            $this->unauthorized('Token does not match this tournament');
            return;
        }

        if (!isset($body['tableId'])) {
            $this->validationError(['tableId' => ['Table ID is required']]);
            return;
        }

        $newTableId = (int) $body['tableId'];
        $newTable = Table::find($newTableId);

        if ($newTable === null) {
            $this->notFound('Table');
            return;
        }

        if ($newTable->tournamentId !== $round->tournamentId) {
            $this->error('validation_error', 'Table does not belong to this tournament', 400);
            return;
        }

        // Check if the new table is already assigned in this round
        $existingAllocation = Allocation::findByRoundAndTable($round->id, $newTableId);
        if ($existingAllocation !== null && $existingAllocation->id !== $allocation->id) {
            $this->error('conflict', 'Table is already assigned to another pairing in this round', 409);
            return;
        }

        // Update the allocation
        $allocation->tableId = $newTableId;

        // TODO: Recalculate conflicts after edit (FR-010) - Phase 6 (US3)
        $allocation->save();

        $this->success($allocation->toArray());
    }

    /**
     * POST /api/allocations/swap - Swap two tables.
     *
     * Reference: FR-009
     */
    public function swap(array $params, ?array $body): void
    {
        if (!isset($body['allocationId1']) || !isset($body['allocationId2'])) {
            $this->validationError([
                'allocationId1' => !isset($body['allocationId1']) ? ['Required'] : [],
                'allocationId2' => !isset($body['allocationId2']) ? ['Required'] : [],
            ]);
            return;
        }

        $allocationId1 = (int) $body['allocationId1'];
        $allocationId2 = (int) $body['allocationId2'];

        if ($allocationId1 === $allocationId2) {
            $this->error('validation_error', 'Cannot swap allocation with itself', 400);
            return;
        }

        $allocation1 = Allocation::find($allocationId1);
        $allocation2 = Allocation::find($allocationId2);

        if ($allocation1 === null || $allocation2 === null) {
            $this->notFound('Allocation');
            return;
        }

        // Verify both allocations are in the same round
        if ($allocation1->roundId !== $allocation2->roundId) {
            $this->error('validation_error', 'Allocations must be in the same round', 400);
            return;
        }

        // Get the round to verify tournament ownership
        $round = Round::find($allocation1->roundId);
        if ($round === null) {
            $this->notFound('Round');
            return;
        }

        // Verify authenticated tournament matches
        $authTournament = AdminAuthMiddleware::getTournament();
        if ($authTournament === null || $authTournament->id !== $round->tournamentId) {
            $this->unauthorized('Token does not match this tournament');
            return;
        }

        // Swap table IDs in a transaction
        Connection::beginTransaction();

        try {
            $tempTableId = $allocation1->tableId;
            $allocation1->tableId = $allocation2->tableId;
            $allocation2->tableId = $tempTableId;

            // TODO: Recalculate conflicts after swap (FR-010) - Phase 6 (US3)
            $allocation1->save();
            $allocation2->save();

            Connection::commit();

            $this->success([
                'allocation1' => $allocation1->toArray(),
                'allocation2' => $allocation2->toArray(),
            ]);
        } catch (\Exception $e) {
            Connection::rollBack();
            $this->error('internal_error', 'Failed to swap tables', 500);
        }
    }
}
