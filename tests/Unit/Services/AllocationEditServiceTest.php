<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use TournamentTables\Services\AllocationEditService;
use TournamentTables\Services\CostCalculator;
use TournamentTables\Services\TournamentHistory;
use TournamentTables\Database\Connection;
use PDO;
use PDOStatement;

/**
 * Unit tests for AllocationEditService.
 *
 * Reference: specs/001-table-allocation/tasks.md#phase-6
 * Tests table assignment editing and swap logic
 */
class AllocationEditServiceTest extends TestCase
{
    /**
     * @var AllocationEditService
     */
    private $service;

    /**
     * @var PDO|\PHPUnit\Framework\MockObject\MockObject
     */
    private $mockDb;

    protected function setUp(): void
    {
        $this->mockDb = $this->createMock(PDO::class);
        $costCalculator = new CostCalculator();
        $this->service = new AllocationEditService($this->mockDb, $costCalculator);
    }

    /**
     * Test successful table assignment change.
     */
    public function testEditTableAssignmentSuccess(): void
    {
        $allocationId = 1;
        $newTableId = 5;

        // Create statement mock for different calls
        $stmt = $this->createMock(PDOStatement::class);

        $tableRow = [
            'id' => $newTableId,
            'tournament_id' => 1,
            'table_number' => 5,
            'terrain_type_id' => null,
        ];

        // fetch() is used by fetchById(), fetchOneWhere(), and getRound()
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            // 1. getAllocation
            [
                'id' => $allocationId,
                'round_id' => 1,
                'table_id' => 3,
                'player1_id' => 10,
                'player2_id' => 11,
            ],
            // 2. getTable
            $tableRow,
            // 3. getRound
            [
                'id' => 1,
                'tournament_id' => 1,
                'round_number' => 2,
            ],
            // 4. getAllocationByRoundAndTable - null (no existing allocation)
            false,
            // 5. getTable again for calculateConflicts
            $tableRow
        );

        // fetchAll() is used by TournamentHistory::queryPlayerHistory()
        // Returns empty arrays (no previous table usage) for each player
        $stmt->method('fetchAll')->willReturn([]);

        $this->mockDb->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        $result = $this->service->editTableAssignment($allocationId, $newTableId);

        $this->assertTrue($result['success']);
        $this->assertEquals($allocationId, $result['allocationId']);
        $this->assertEquals($newTableId, $result['newTableId']);
    }

    /**
     * Test editing allocation with invalid allocation ID.
     */
    public function testEditTableAssignmentInvalidAllocation(): void
    {
        $allocationId = 999;
        $newTableId = 5;

        // Mock allocation not found
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn(false);
        $this->mockDb->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Allocation not found');

        $this->service->editTableAssignment($allocationId, $newTableId);
    }

    /**
     * Test editing with duplicate table assignment (table already used in round).
     * Collisions are now warnings (TABLE_COLLISION conflict) instead of blocking errors.
     */
    public function testEditTableAssignmentDuplicateTable(): void
    {
        $allocationId = 1;
        $newTableId = 5;

        // Create statement mock for different calls
        $stmt = $this->createMock(PDOStatement::class);

        $tableRow = [
            'id' => $newTableId,
            'tournament_id' => 1,
            'table_number' => 5,
            'terrain_type_id' => null,
        ];

        // fetch() is used by fetchById(), fetchOneWhere(), and getRound()
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            // 1. getAllocation
            [
                'id' => $allocationId,
                'round_id' => 1,
                'table_id' => 3,
                'player1_id' => 10,
                'player2_id' => 11,
            ],
            // 2. getTable
            $tableRow,
            // 3. getRound
            [
                'id' => 1,
                'tournament_id' => 1,
                'round_number' => 2,
            ],
            // 4. getAllocationByRoundAndTable - existing allocation found
            [
                'id' => 2, // Different allocation already using table 5
                'round_id' => 1,
                'table_id' => $newTableId,
            ],
            // 5. getTable for calculateConflicts
            $tableRow
        );

        // fetchAll() is used by TournamentHistory::queryPlayerHistory()
        // Returns empty arrays (no previous table usage) for each player
        $stmt->method('fetchAll')->willReturn([]);

        $this->mockDb->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        // Now returns success with TABLE_COLLISION conflict instead of throwing
        $result = $this->service->editTableAssignment($allocationId, $newTableId);

        $this->assertTrue($result['success']);
        $this->assertEquals($allocationId, $result['allocationId']);
        $this->assertEquals($newTableId, $result['newTableId']);

        // Should have TABLE_COLLISION conflict
        $hasCollisionConflict = false;
        foreach ($result['conflicts'] as $conflict) {
            if ($conflict['type'] === 'TABLE_COLLISION') {
                $hasCollisionConflict = true;
                $this->assertEquals(2, $conflict['otherAllocationId']);
                break;
            }
        }
        $this->assertTrue($hasCollisionConflict, 'Expected TABLE_COLLISION conflict');
    }

    /**
     * Test successful table swap.
     */
    public function testSwapTablesSuccess(): void
    {
        $allocationId1 = 1;
        $allocationId2 = 2;

        // Mock both allocations exist and are in same round
        $stmt = $this->createMock(PDOStatement::class);

        // fetch() is used by fetchById() and getRound()
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            // 1. getAllocation - allocation 1
            [
                'id' => $allocationId1,
                'round_id' => 1,
                'table_id' => 3,
                'player1_id' => 10,
                'player2_id' => 11,
            ],
            // 2. getAllocation - allocation 2
            [
                'id' => $allocationId2,
                'round_id' => 1,
                'table_id' => 5,
                'player1_id' => 12,
                'player2_id' => 13,
            ],
            // 3. getRound
            [
                'id' => 1,
                'tournament_id' => 1,
                'round_number' => 2,
            ],
            // 4. getTable(5) for first calculateConflicts
            ['id' => 5, 'tournament_id' => 1, 'table_number' => 5, 'terrain_type_id' => null],
            // 5. getTable(3) for second calculateConflicts
            ['id' => 3, 'tournament_id' => 1, 'table_number' => 3, 'terrain_type_id' => null]
        );

        // fetchAll() is used by TournamentHistory::queryPlayerHistory()
        // Returns empty arrays (no previous table usage) for each player
        $stmt->method('fetchAll')->willReturn([]);

        $this->mockDb->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);
        $this->mockDb->method('beginTransaction')->willReturn(true);
        $this->mockDb->method('commit')->willReturn(true);

        $result = $this->service->swapTables($allocationId1, $allocationId2);

        $this->assertTrue($result['success']);
        $this->assertEquals($allocationId1, $result['allocation1']['id']);
        $this->assertEquals($allocationId2, $result['allocation2']['id']);
    }

    /**
     * Test swap with allocations in different rounds.
     */
    public function testSwapTablesDifferentRounds(): void
    {
        $allocationId1 = 1;
        $allocationId2 = 2;

        // Mock allocations in different rounds
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => $allocationId1,
                'round_id' => 1,
                'table_id' => 3,
            ],
            [
                'id' => $allocationId2,
                'round_id' => 2, // Different round
                'table_id' => 5,
            ]
        );

        $this->mockDb->method('prepare')->willReturn($stmt);
        $stmt->method('execute')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Both allocations must be in the same round');

        $this->service->swapTables($allocationId1, $allocationId2);
    }

    /**
     * Test swap with same allocation ID.
     */
    public function testSwapTablesSameAllocation(): void
    {
        $allocationId = 1;

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Cannot swap an allocation with itself');

        $this->service->swapTables($allocationId, $allocationId);
    }
}
