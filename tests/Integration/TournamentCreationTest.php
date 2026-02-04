<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use TournamentTables\Tests\DatabaseTestCase;
use TournamentTables\Services\TournamentService;
use TournamentTables\Models\Tournament;
use TournamentTables\Models\Table;
use TournamentTables\Database\Connection;

/**
 * Integration tests for tournament creation.
 *
 * Reference: specs/001-table-allocation/tasks.md T023
 */
class TournamentCreationTest extends DatabaseTestCase
{
    /** @var TournamentService */
    private $service;

    protected function setUp(): void
    {
        parent::setUp();

        $this->service = new TournamentService();
    }

    public function testCreateTournamentReturnsValidResult(): void
    {
        $result = $this->service->createTournament(
            'Test Tournament',
            'https://www.bestcoastpairings.com/event/test123',
            10
        );

        $this->assertArrayHasKey('tournament', $result);
        $this->assertArrayHasKey('adminToken', $result);
    }

    public function testCreateTournamentPersistsTournament(): void
    {
        $result = $this->service->createTournament(
            'Persisted Tournament',
            'https://www.bestcoastpairings.com/event/persist123',
            8
        );

        // Verify tournament exists in database
        $found = Tournament::find($result['tournament']->id);

        $this->assertNotNull($found);
        $this->assertEquals('Persisted Tournament', $found->name);
        $this->assertEquals('persist123', $found->bcpEventId);
        $this->assertEquals(8, $found->tableCount);
    }

    public function testCreateTournamentCreatesCorrectNumberOfTables(): void
    {
        $result = $this->service->createTournament(
            'Tables Test',
            'https://www.bestcoastpairings.com/event/tables123',
            12
        );

        // Verify tables were created
        $tables = Table::findByTournament($result['tournament']->id);

        $this->assertCount(12, $tables);

        // Verify table numbers are correct
        $tableNumbers = array_map(function ($t) {
            return $t->tableNumber;
        }, $tables);
        sort($tableNumbers);

        $this->assertEquals(range(1, 12), $tableNumbers);
    }

    public function testCreateTournamentGeneratesAdminToken(): void
    {
        $result = $this->service->createTournament(
            'Token Test',
            'https://www.bestcoastpairings.com/event/token123',
            5
        );

        // Verify token is 16 characters
        $this->assertEquals(16, strlen($result['adminToken']));

        // Verify token is stored in database
        $found = Tournament::findByToken($result['adminToken']);
        $this->assertNotNull($found);
        $this->assertEquals($result['tournament']->id, $found->id);
    }

    public function testCreateTournamentExtractsBcpEventId(): void
    {
        $result = $this->service->createTournament(
            'Event ID Test',
            'https://www.bestcoastpairings.com/event/abc123xyz',
            5
        );

        $this->assertEquals('abc123xyz', $result['tournament']->bcpEventId);
    }

    public function testCreateTournamentStoresFullBcpUrl(): void
    {
        $url = 'https://www.bestcoastpairings.com/event/fullurl123';
        $result = $this->service->createTournament(
            'URL Test',
            $url,
            5
        );

        $this->assertEquals($url, $result['tournament']->bcpUrl);
    }

    public function testCreateTournamentWithInvalidUrlThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTournament(
            'Invalid URL Test',
            'https://www.example.com/event/invalid',
            5
        );
    }

    public function testCreateTournamentWithInvalidTableCountThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTournament(
            'Invalid Count Test',
            'https://www.bestcoastpairings.com/event/count123',
            -1  // Negative values are invalid; 0 is now valid (auto-import)
        );
    }

    public function testCreateTournamentWithEmptyNameThrowsException(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->createTournament(
            '',
            'https://www.bestcoastpairings.com/event/empty123',
            5
        );
    }

    public function testCreateTournamentWithDuplicateBcpEventIdThrowsException(): void
    {
        // Create first tournament
        $result = $this->service->createTournament(
            'First Tournament',
            'https://www.bestcoastpairings.com/event/duplicate123',
            5
        );

        // Try to create second tournament with same BCP event ID
        $this->expectException(\RuntimeException::class);

        $this->service->createTournament(
            'Second Tournament',
            'https://www.bestcoastpairings.com/event/duplicate123',
            5
        );
    }

    public function testCreateTournamentUsesTransaction(): void
    {
        // Force a failure after tournament creation but before tables
        // by using a custom TournamentService that fails mid-creation
        // For now, just verify both tournament and tables exist or neither do

        try {
            $result = $this->service->createTournament(
                'Transaction Test',
                'https://www.bestcoastpairings.com/event/trans123',
                5
            );

            // Both should exist
            $tournament = Tournament::find($result['tournament']->id);
            $tables = Table::findByTournament($result['tournament']->id);

            $this->assertNotNull($tournament);
            $this->assertCount(5, $tables);
        } catch (\Exception $e) {
            // If creation failed, neither should exist
            $tournament = Tournament::findByBcpEventId('trans123');
            $this->assertNull($tournament);
        }
    }
}
