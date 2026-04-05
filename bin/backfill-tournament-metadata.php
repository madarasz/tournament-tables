#!/usr/bin/env php
<?php

declare(strict_types=1);

use TournamentTables\Services\TournamentMetadataBackfillService;

require_once __DIR__ . '/../vendor/autoload.php';

$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: config/database.php not found.\n");
    fwrite(STDERR, "Please copy config/database.example.php to config/database.php and configure.\n");
    exit(1);
}

$options = getopt('', ['tournament-id:', 'dry-run']);
if ($options === false) {
    fwrite(STDERR, "Error: Failed to parse options.\n");
    exit(1);
}

$tournamentId = null;
if (array_key_exists('tournament-id', $options)) {
    $rawTournamentId = $options['tournament-id'];

    if (!is_string($rawTournamentId) || !ctype_digit($rawTournamentId) || (int) $rawTournamentId <= 0) {
        fwrite(STDERR, "Error: --tournament-id must be a positive integer.\n");
        exit(1);
    }

    $tournamentId = (int) $rawTournamentId;
}

$dryRun = array_key_exists('dry-run', $options);

echo "Starting tournament metadata backfill...\n";
echo 'Mode: ' . ($dryRun ? 'dry-run' : 'write') . "\n";
if ($tournamentId !== null) {
    echo "Scope: tournament ID {$tournamentId}\n";
}

$service = new TournamentMetadataBackfillService();

try {
    $summary = $service->backfill(
        $tournamentId,
        $dryRun,
        function (array $progress) use ($dryRun): void {
            $id = $progress['tournamentId'];
            $eventId = $progress['bcpEventId'];
            $status = $progress['status'];

            if ($status === 'updated') {
                $fields = implode(', ', array_keys($progress['fields']));
                $verb = $dryRun ? 'Would update' : 'Updated';
                echo "{$verb} tournament {$id} ({$eventId}) fields: {$fields}\n";
                return;
            }

            if ($status === 'skipped_no_missing') {
                echo "Skipped tournament {$id} ({$eventId}): no missing target fields.\n";
                return;
            }

            echo "Skipped tournament {$id} ({$eventId}): BCP returned no fillable values.\n";
        }
    );

    echo "\nBackfill summary:\n";
    echo "  scanned: {$summary['scanned']}\n";
    echo "  updated: {$summary['updated']}\n";
    echo "  skipped: {$summary['skipped']}\n";
    echo "  field_updates: {$summary['field_updates']}\n";
    echo "Backfill completed successfully.\n";
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "Backfill failed: {$e->getMessage()}\n");
    exit(1);
}
