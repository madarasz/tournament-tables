<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use RuntimeException;
use TournamentTables\Database\Connection;
use TournamentTables\Services\BCPApiService;
use TournamentTables\Services\TournamentMetadataBackfillService;
use TournamentTables\Tests\DatabaseTestCase;
use TournamentTables\Tests\TestUtilityTrait;

/**
 * Integration tests for tournament metadata backfill service.
 */
class TournamentMetadataBackfillServiceTest extends DatabaseTestCase
{
    use TestUtilityTrait;

    public function testBackfillFillsAllTargetFieldsWhenMissing(): void
    {
        $tournament = $this->createTournament([
            'photo_url' => null,
            'event_date' => null,
            'event_end_date' => null,
            'location_name' => null,
        ]);

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->once())
            ->method('fetchTournamentMetadata')
            ->with($tournament['bcpUrl'])
            ->willReturn([
                'name' => 'Ignored Name',
                'photoUrl' => 'https://example.com/event.png',
                'eventDate' => '2026-11-15T08:00:00.000Z',
                'eventEndDate' => '2026-11-15T20:00:00.000Z',
                'locationName' => 'Main Arena',
            ]);

        $service = new TournamentMetadataBackfillService($bcpService);
        $summary = $service->backfill($tournament['id']);

        $row = $this->fetchTournamentRow($tournament['id']);
        $this->assertSame('https://example.com/event.png', $row['photo_url']);
        $this->assertSame('2026-11-15T08:00:00.000Z', $row['event_date']);
        $this->assertSame('2026-11-15T20:00:00.000Z', $row['event_end_date']);
        $this->assertSame('Main Arena', $row['location_name']);

        $this->assertSame([
            'scanned' => 1,
            'updated' => 1,
            'skipped' => 0,
            'field_updates' => 4,
        ], $summary);
    }

    public function testBackfillOnlyUpdatesMissingFieldsWithoutOverwritingExistingValues(): void
    {
        $tournament = $this->createTournament([
            'photo_url' => 'https://example.com/existing-photo.png',
            'event_date' => null,
            'event_end_date' => '   ',
            'location_name' => 'Existing Hall',
        ]);

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->once())
            ->method('fetchTournamentMetadata')
            ->with($tournament['bcpUrl'])
            ->willReturn([
                'name' => 'Ignored Name',
                'photoUrl' => 'https://example.com/new-photo.png',
                'eventDate' => '2026-12-01T08:00:00.000Z',
                'eventEndDate' => '2026-12-01T18:00:00.000Z',
                'locationName' => 'New Hall',
            ]);

        $service = new TournamentMetadataBackfillService($bcpService);
        $summary = $service->backfill($tournament['id']);

        $row = $this->fetchTournamentRow($tournament['id']);
        $this->assertSame('https://example.com/existing-photo.png', $row['photo_url']);
        $this->assertSame('Existing Hall', $row['location_name']);
        $this->assertSame('2026-12-01T08:00:00.000Z', $row['event_date']);
        $this->assertSame('2026-12-01T18:00:00.000Z', $row['event_end_date']);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(2, $summary['field_updates']);
    }

    public function testBackfillSkipsTournamentWhenNoTargetFieldsAreMissing(): void
    {
        $tournament = $this->createTournament([
            'photo_url' => 'https://example.com/existing.png',
            'event_date' => '2026-10-10T08:00:00.000Z',
            'event_end_date' => '2026-10-10T18:00:00.000Z',
            'location_name' => 'Existing Venue',
        ]);

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->never())
            ->method('fetchTournamentMetadata');

        $service = new TournamentMetadataBackfillService($bcpService);
        $summary = $service->backfill($tournament['id']);

        $this->assertSame([
            'scanned' => 1,
            'updated' => 0,
            'skipped' => 1,
            'field_updates' => 0,
        ], $summary);
    }

    public function testBackfillFailsFastOnFetchErrorAndKeepsAlreadyAppliedUpdates(): void
    {
        $this->suppressExistingMissingTournaments();

        $first = $this->createTournament([
            'photo_url' => null,
            'event_date' => null,
            'event_end_date' => null,
            'location_name' => null,
        ]);

        $second = $this->createTournament([
            'photo_url' => null,
            'event_date' => null,
            'event_end_date' => null,
            'location_name' => null,
        ]);

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->exactly(2))
            ->method('fetchTournamentMetadata')
            ->willReturnCallback(function (string $bcpUrl) use ($first): array {
                if ($bcpUrl === $first['bcpUrl']) {
                    return [
                        'name' => 'Ignored Name',
                        'photoUrl' => 'https://example.com/first.png',
                        'eventDate' => '2026-11-01T09:00:00.000Z',
                        'eventEndDate' => '2026-11-01T19:00:00.000Z',
                        'locationName' => 'First Venue',
                    ];
                }

                throw new RuntimeException('Simulated BCP failure');
            });

        $service = new TournamentMetadataBackfillService($bcpService);

        try {
            $service->backfill();
            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('Backfill failed for tournament ID ' . $second['id'], $e->getMessage());
        }

        $firstRow = $this->fetchTournamentRow($first['id']);
        $secondRow = $this->fetchTournamentRow($second['id']);

        $this->assertSame('https://example.com/first.png', $firstRow['photo_url']);
        $this->assertNull($secondRow['photo_url']);
    }

    public function testBackfillDryRunDoesNotWriteDatabaseChanges(): void
    {
        $tournament = $this->createTournament([
            'photo_url' => null,
            'event_date' => null,
            'event_end_date' => null,
            'location_name' => null,
        ]);

        $bcpService = $this->createMock(BCPApiService::class);
        $bcpService
            ->expects($this->once())
            ->method('fetchTournamentMetadata')
            ->with($tournament['bcpUrl'])
            ->willReturn([
                'name' => 'Ignored Name',
                'photoUrl' => 'https://example.com/dry-run.png',
                'eventDate' => '2027-01-10T08:00:00.000Z',
                'eventEndDate' => '2027-01-10T18:00:00.000Z',
                'locationName' => 'Dry Run Venue',
            ]);

        $service = new TournamentMetadataBackfillService($bcpService);
        $summary = $service->backfill($tournament['id'], true);

        $row = $this->fetchTournamentRow($tournament['id']);
        $this->assertNull($row['photo_url']);
        $this->assertNull($row['event_date']);
        $this->assertNull($row['event_end_date']);
        $this->assertNull($row['location_name']);

        $this->assertSame(1, $summary['updated']);
        $this->assertSame(4, $summary['field_updates']);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array{id: int, bcpUrl: string}
     */
    private function createTournament(array $overrides = []): array
    {
        $eventId = $overrides['bcp_event_id'] ?? ('evt' . $this->createUniqueId());
        $defaultValues = [
            'name' => 'Backfill Test ' . $this->createUniqueId(),
            'bcp_event_id' => $eventId,
            'bcp_url' => 'https://www.bestcoastpairings.com/event/' . $eventId,
            'photo_url' => null,
            'location_name' => null,
            'event_date' => null,
            'event_end_date' => null,
            'table_count' => 0,
            'last_updated' => null,
            'admin_token' => substr(bin2hex(random_bytes(8)), 0, 16),
        ];

        $values = array_merge($defaultValues, $overrides);

        Connection::execute(
            'INSERT INTO tournaments (
                name,
                bcp_event_id,
                bcp_url,
                photo_url,
                location_name,
                event_date,
                event_end_date,
                table_count,
                last_updated,
                admin_token
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $values['name'],
                $values['bcp_event_id'],
                $values['bcp_url'],
                $values['photo_url'],
                $values['location_name'],
                $values['event_date'],
                $values['event_end_date'],
                $values['table_count'],
                $values['last_updated'],
                $values['admin_token'],
            ]
        );

        return [
            'id' => Connection::lastInsertId(),
            'bcpUrl' => (string) $values['bcp_url'],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function fetchTournamentRow(int $tournamentId): array
    {
        $row = Connection::fetchOne('SELECT * FROM tournaments WHERE id = ?', [$tournamentId]);
        $this->assertNotNull($row);

        return $row;
    }

    /**
     * Neutralize pre-existing rows so global backfill scans only target test data.
     */
    private function suppressExistingMissingTournaments(): void
    {
        Connection::execute(
            'UPDATE tournaments
             SET
                photo_url = CASE
                    WHEN photo_url IS NULL OR TRIM(photo_url) = "" THEN "https://example.com/existing.png"
                    ELSE photo_url
                END,
                event_date = CASE
                    WHEN event_date IS NULL OR TRIM(event_date) = "" THEN "2000-01-01T00:00:00.000Z"
                    ELSE event_date
                END,
                event_end_date = CASE
                    WHEN event_end_date IS NULL OR TRIM(event_end_date) = "" THEN "2000-01-01T01:00:00.000Z"
                    ELSE event_end_date
                END,
                location_name = CASE
                    WHEN location_name IS NULL OR TRIM(location_name) = "" THEN "Existing Venue"
                    ELSE location_name
                END'
        );
    }
}
