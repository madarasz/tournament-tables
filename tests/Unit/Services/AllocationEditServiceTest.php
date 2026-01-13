<?php

declare(strict_types=1);

namespace KTTables\Tests\Unit\Services;

use PHPUnit\Framework\TestCase;
use KTTables\Services\AllocationEditService;
use KTTables\Services\CostCalculator;
use KTTables\Services\TournamentHistory;
use KTTables\Database\Connection;
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

        // Mock allocation exists
        $stmt = $this->createMock(PDOStatement::class);
        $stmt->method('fetch')->willReturn([
            'id' => $allocationId,
            'round_id' => 1,
            'table_id' => 3,
            'player1_id' => 10,
            'player2_id' => 11,
        ]);

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
     */
    public function testEditTableAssignmentDuplicateTable(): void
    {
        $allocationId = 1;
        $newTableId = 5;

        // Mock allocation exists
        $stmt1 = $this->createMock(PDOStatement::class);
        $stmt1->method('fetch')->willReturn([
            'id' => $allocationId,
            'round_id' => 1,
            'table_id' => 3,
            'player1_id' => 10,
            'player2_id' => 11,
        ]);

        // Mock table already used in this round
        $stmt2 = $this->createMock(PDOStatement::class);
        $stmt2->method('fetch')->willReturn([
            'id' => 2, // Different allocation already using table 5
        ]);

        $this->mockDb->method('prepare')
            ->willReturnOnConsecutiveCalls($stmt1, $stmt2);

        $stmt1->method('execute')->willReturn(true);
        $stmt2->method('execute')->willReturn(true);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Table 5 is already assigned in this round');

        $this->service->editTableAssignment($allocationId, $newTableId);
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
        $stmt->method('fetch')->willReturnOnConsecutiveCalls(
            [
                'id' => $allocationId1,
                'round_id' => 1,
                'table_id' => 3,
                'player1_id' => 10,
                'player2_id' => 11,
            ],
            [
                'id' => $allocationId2,
                'round_id' => 1,
                'table_id' => 5,
                'player1_id' => 12,
                'player2_id' => 13,
            ]
        );

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
