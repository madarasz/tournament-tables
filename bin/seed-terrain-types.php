#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Seed terrain types
 *
 * Populates the terrain_types table with predefined terrain types.
 * Reference: specs/001-table-allocation/data-model.md#initial-data
 */

require_once __DIR__ . '/../vendor/autoload.php';

$configPath = __DIR__ . '/../config/database.php';
if (!file_exists($configPath)) {
    fwrite(STDERR, "Error: config/database.php not found.\n");
    fwrite(STDERR, "Please copy config/database.example.php to config/database.php and configure.\n");
    exit(1);
}

$config = require $configPath;

$dsn = sprintf(
    'mysql:host=%s;port=%s;dbname=%s;charset=%s',
    $config['host'],
    $config['port'] ?? 3306,
    $config['database'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Connected to database.\n";

    // Terrain types from data-model.md#initial-data
    $terrainTypes = [
        ['Volkus', 'Forge world industrial terrain', '🏢', 1],
        ['Tomb World', 'Necron tomb complex', '🪦', 2],
        ['Into the Dark', 'Generic space hulk terrain', '🚀', 3],
        ['Octarius', 'War-torn Ork-infested ruins', '🛖', 4],
        ['Bheta-Decima', 'Imperial hive city ruins', '🏗️', 5],
        ['Volkus+Tyranid', 'Volkus with Tyranid infestation', '👾', 6]
    ];

    // Use INSERT IGNORE to avoid duplicates on re-run
    $stmt = $pdo->prepare(
        'INSERT IGNORE INTO terrain_types (name, description, emoji, sort_order) VALUES (?, ?, ?, ?)'
    );

    $inserted = 0;
    foreach ($terrainTypes as $terrain) {
        $stmt->execute($terrain);
        if ($stmt->rowCount() > 0) {
            $inserted++;
            echo "  Added: {$terrain[0]}\n";
        }
    }

    if ($inserted === 0) {
        echo "No new terrain types added (already seeded).\n";
    } else {
        echo "Seeded {$inserted} terrain types.\n";
    }

} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
