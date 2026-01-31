<?php

declare(strict_types=1);

namespace TournamentTables\Controllers;

use TournamentTables\Models\Allocation;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Database\Connection;
use TournamentTables\Services\AllocationEditService;
use TournamentTables\Services\CostCalculator;

/**
 * Allocation editing controller.
 *
 * Reference: specs/001-table-allocation/contracts/api.yaml#/allocations
 */
class AllocationController extends BaseController
{
    /** @var AllocationEditService */
    private $editService;

    public function __construct()
    {
        $db = Connection::getInstance();
        $this->editService = new AllocationEditService($db, new CostCalculator());
    }
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
        if (!$this->verifyTournamentAuth($round->tournamentId)) {
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

        // Update the allocation using the edit service (includes conflict recalculation per FR-010)
        // Note: Duplicate table assignments are allowed but will be flagged as conflicts
        try {
            $result = $this->editService->editTableAssignment($allocation->id, $newTableId);

            // Reload allocation to get updated data
            $allocation = Allocation::find($allocationId);
            if ($allocation === null) {
                $this->error('internal_error', 'Allocation was deleted during update', 500);
                return;
            }

            $this->success([
                'id' => $allocation->id,
                'tableId' => $allocation->tableId,
                'conflicts' => $result['conflicts'],
            ]);
        } catch (\RuntimeException $e) {
            $this->error('conflict', $e->getMessage(), 400);
        }
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
            $this->error('internal_error', 'Allocation was deleted during swap', 500);
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
        if (!$this->verifyTournamentAuth($round->tournamentId)) {
            return;
        }

        // Swap tables using the edit service (includes conflict recalculation per FR-010)
        try {
            $result = $this->editService->swapTables($allocationId1, $allocationId2);

            // Reload allocations to get updated data
            $allocation1 = Allocation::find($allocationId1);
            $allocation2 = Allocation::find($allocationId2);

            if ($allocation1 === null || $allocation2 === null) {
                $this->error('internal_error', 'Allocation was deleted during swap', 500);
                return;
            }

            $this->success([
                'allocation1' => [
                    'id' => $allocation1->id,
                    'tableId' => $allocation1->tableId,
                    'conflicts' => $result['allocation1']['conflicts'],
                ],
                'allocation2' => [
                    'id' => $allocation2->id,
                    'tableId' => $allocation2->tableId,
                    'conflicts' => $result['allocation2']['conflicts'],
                ],
            ]);
        } catch (\RuntimeException $e) {
            $this->error('conflict', $e->getMessage(), 400);
        } catch (\Exception $e) {
            $this->error('internal_error', 'Failed to swap tables', 500);
        }
    }
}
