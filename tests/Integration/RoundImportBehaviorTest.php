<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use TournamentTables\Controllers\RoundController;
use TournamentTables\Models\Allocation;
use TournamentTables\Models\Player;
use TournamentTables\Models\Round;
use TournamentTables\Models\Table;
use TournamentTables\Models\Tournament;
use TournamentTables\Tests\DatabaseTestCase;

/**
 * Integration tests for round import behavior helpers.
 */
class RoundImportBehaviorTest extends DatabaseTestCase
{
    public function testParseGenerateAllocationsFlagDefaultsToTrue(): void
    {
        $controller = new RoundController();

        $this->assertTrue($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [null]));
        $this->assertTrue($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [[]]));
        $this->assertTrue($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [['foo' => 'bar']]));
        $this->assertTrue($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [['generateAllocations' => true]]));
    }

    public function testParseGenerateAllocationsFlagParsesStringAndBooleanFalse(): void
    {
        $controller = new RoundController();

        $this->assertFalse($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [['generateAllocations' => false]]));
        $this->assertFalse($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [['generateAllocations' => 'false']]));
        $this->assertFalse($this->invokePrivate($controller, 'parseGenerateAllocationsFlag', [['generateAllocations' => '0']]));
    }

    public function testBuildExistingTableAssignmentLookupUsesOrderInsensitivePairingKey(): void
    {
        $controller = new RoundController();
        $tournament = $this->createTournament();
        $round = Round::findOrCreate($tournament->id, 2);

        $table1 = new Table(null, $tournament->id, 1, null);
        $table1->save();
        $table2 = new Table(null, $tournament->id, 2, null);
        $table2->save();

        $player1 = Player::findOrCreate($tournament->id, 'bcp_p1', 'Player 1');
        $player2 = Player::findOrCreate($tournament->id, 'bcp_p2', 'Player 2');
        $player3 = Player::findOrCreate($tournament->id, 'bcp_p3', 'Player 3');

        $allocationA = new Allocation(
            null,
            $round->id,
            $table1->id,
            $player1->id,
            $player2->id,
            10,
            8,
            null,
            1
        );
        $allocationA->save();

        // Same matchup in reverse order - lookup should keep the first assignment.
        $allocationB = new Allocation(
            null,
            $round->id,
            $table2->id,
            $player2->id,
            $player1->id,
            8,
            10,
            null,
            2
        );
        $allocationB->save();

        // Bye allocation should be ignored.
        $bye = new Allocation(
            null,
            $round->id,
            null,
            $player3->id,
            null,
            12,
            0,
            null,
            null
        );
        $bye->save();

        /** @var Allocation[] $allocations */
        $allocations = Allocation::findByRound($round->id);

        $lookup = $this->invokePrivate($controller, 'buildExistingTableAssignmentLookup', [$allocations]);
        $key = $this->invokePrivate($controller, 'buildPairingKey', [$player1->bcpPlayerId, $player2->bcpPlayerId]);

        $this->assertCount(1, $lookup);
        $this->assertArrayHasKey($key, $lookup);
        $this->assertSame($table1->id, $lookup[$key]);
    }

    private function createTournament(): Tournament
    {
        $tournament = new Tournament(
            null,
            'Round Import Behavior Test',
            'TEST_' . uniqid(),
            'https://www.bestcoastpairings.com/event/TESTROUNDIMPORT',
            2,
            bin2hex(random_bytes(8))
        );
        $tournament->save();

        return $tournament;
    }

    /**
     * @param array<int, mixed> $arguments
     * @return mixed
     */
    private function invokePrivate(object $object, string $methodName, array $arguments = [])
    {
        $method = new \ReflectionMethod($object, $methodName);
        return $method->invokeArgs($object, $arguments);
    }
}
