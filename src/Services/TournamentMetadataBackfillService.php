<?php

declare(strict_types=1);

namespace TournamentTables\Services;

use RuntimeException;
use TournamentTables\Database\Connection;
use Throwable;

/**
 * Backfills missing tournament metadata fields from BCP event details.
 */
class TournamentMetadataBackfillService
{
    /**
     * Map of DB column => BCP metadata key.
     *
     * @var array<string, string>
     */
    private const FIELD_MAP = [
        'photo_url' => 'photoUrl',
        'event_date' => 'eventDate',
        'event_end_date' => 'eventEndDate',
        'location_name' => 'locationName',
    ];

    /** @var BCPApiService */
    private $bcpService;

    public function __construct(?BCPApiService $bcpService = null)
    {
        $this->bcpService = $bcpService ?? new BCPApiService();
    }

    /**
     * Backfill missing metadata fields on tournaments.
     *
     * @param int|null $tournamentId Optional single-tournament scope
     * @param bool $dryRun Report planned updates without writing to DB
     * @param callable|null $progressCallback Optional per-tournament callback
     * @return array{scanned: int, updated: int, skipped: int, field_updates: int}
     *
     * Callback payload:
     * @param array{
     *   tournamentId: int,
     *   bcpEventId: string,
     *   status: string,
     *   fields: array<string, string>
     * } $progressCallbackPayload
     *
     * @throws RuntimeException Fail-fast on first API/update error
     */
    public function backfill(?int $tournamentId = null, bool $dryRun = false, ?callable $progressCallback = null): array
    {
        $tournaments = $this->findTournamentsToScan($tournamentId);

        $summary = [
            'scanned' => 0,
            'updated' => 0,
            'skipped' => 0,
            'field_updates' => 0,
        ];

        foreach ($tournaments as $tournament) {
            $summary['scanned']++;

            if (!$this->hasMissingTargetField($tournament)) {
                $summary['skipped']++;
                $this->notifyProgress($progressCallback, [
                    'tournamentId' => (int) $tournament['id'],
                    'bcpEventId' => (string) $tournament['bcp_event_id'],
                    'status' => 'skipped_no_missing',
                    'fields' => [],
                ]);
                continue;
            }

            try {
                $metadata = $this->bcpService->fetchTournamentMetadata((string) $tournament['bcp_url']);
                $updates = $this->buildFillableUpdateFields($tournament, $metadata);

                if (empty($updates)) {
                    $summary['skipped']++;
                    $this->notifyProgress($progressCallback, [
                        'tournamentId' => (int) $tournament['id'],
                        'bcpEventId' => (string) $tournament['bcp_event_id'],
                        'status' => 'skipped_no_fillable',
                        'fields' => [],
                    ]);
                    continue;
                }

                if (!$dryRun) {
                    $this->updateTournamentFields((int) $tournament['id'], $updates);
                }

                $summary['updated']++;
                $summary['field_updates'] += count($updates);

                $this->notifyProgress($progressCallback, [
                    'tournamentId' => (int) $tournament['id'],
                    'bcpEventId' => (string) $tournament['bcp_event_id'],
                    'status' => 'updated',
                    'fields' => $updates,
                ]);
            } catch (Throwable $e) {
                throw new RuntimeException(
                    sprintf(
                        'Backfill failed for tournament ID %d (BCP event %s): %s',
                        (int) $tournament['id'],
                        (string) $tournament['bcp_event_id'],
                        $e->getMessage()
                    ),
                    0,
                    $e
                );
            }
        }

        return $summary;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function findTournamentsToScan(?int $tournamentId): array
    {
        if ($tournamentId !== null) {
            return Connection::fetchAll(
                'SELECT id, bcp_event_id, bcp_url, photo_url, event_date, event_end_date, location_name
                 FROM tournaments
                 WHERE id = ?
                 ORDER BY id ASC',
                [$tournamentId]
            );
        }

        return Connection::fetchAll(
            'SELECT id, bcp_event_id, bcp_url, photo_url, event_date, event_end_date, location_name
             FROM tournaments
             WHERE
                photo_url IS NULL OR TRIM(photo_url) = "" OR
                event_date IS NULL OR TRIM(event_date) = "" OR
                event_end_date IS NULL OR TRIM(event_end_date) = "" OR
                location_name IS NULL OR TRIM(location_name) = ""
             ORDER BY id ASC'
        );
    }

    /**
     * @param array<string, mixed> $tournament
     */
    private function hasMissingTargetField(array $tournament): bool
    {
        foreach (array_keys(self::FIELD_MAP) as $field) {
            if ($this->isMissingValue($tournament[$field] ?? null)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $tournament
     * @param array<string, mixed> $metadata
     * @return array<string, string>
     */
    private function buildFillableUpdateFields(array $tournament, array $metadata): array
    {
        $updates = [];

        foreach (self::FIELD_MAP as $dbColumn => $metadataKey) {
            $existingValue = $tournament[$dbColumn] ?? null;
            $incomingValue = $metadata[$metadataKey] ?? null;

            if (!$this->isMissingValue($existingValue)) {
                continue;
            }

            if ($this->isMissingValue($incomingValue)) {
                continue;
            }

            $updates[$dbColumn] = trim((string) $incomingValue);
        }

        return $updates;
    }

    /**
     * @param array<string, string> $updates
     */
    private function updateTournamentFields(int $tournamentId, array $updates): void
    {
        if (empty($updates)) {
            return;
        }

        $setParts = [];
        $params = [];

        foreach ($updates as $column => $value) {
            $setParts[] = $column . ' = ?';
            $params[] = $value;
        }

        $params[] = $tournamentId;

        Connection::execute(
            'UPDATE tournaments SET ' . implode(', ', $setParts) . ' WHERE id = ?',
            $params
        );
    }

    /**
     * @param mixed $value
     */
    private function isMissingValue($value): bool
    {
        if ($value === null) {
            return true;
        }

        if (!is_string($value)) {
            return false;
        }

        return trim($value) === '';
    }

    /**
     * @param callable|null $progressCallback
     * @param array{
     *   tournamentId: int,
     *   bcpEventId: string,
     *   status: string,
     *   fields: array<string, string>
     * } $payload
     */
    private function notifyProgress(?callable $progressCallback, array $payload): void
    {
        if ($progressCallback !== null) {
            $progressCallback($payload);
        }
    }
}
