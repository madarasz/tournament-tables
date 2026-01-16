#!/usr/bin/env php
<?php

declare(strict_types=1);

/**
 * Database migration script
 *
 * Creates all required tables for the Tournament Tables application.
 * Reference: specs/001-table-allocation/data-model.md#database-schema-mysql
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
    'mysql:host=%s;charset=%s',
    $config['host'],
    $config['charset']
);

try {
    $pdo = new PDO($dsn, $config['username'], $config['password'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);

    echo "Connected to MySQL server.\n";

    // Create database if not exists
    $dbName = $config['database'];
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$dbName}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "Database '{$dbName}' ready.\n";

    // Use the database
    $pdo->exec("USE `{$dbName}`");

    // SQL schema from data-model.md
    $schema = <<<'SQL'
CREATE TABLE IF NOT EXISTS terrain_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE,
    description TEXT,
    sort_order INT NOT NULL DEFAULT 0,
    INDEX idx_sort_order (sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tournaments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    bcp_event_id VARCHAR(50) NOT NULL UNIQUE,
    bcp_url VARCHAR(500) NOT NULL,
    table_count INT NOT NULL,
    admin_token CHAR(16) NOT NULL UNIQUE,
    INDEX idx_admin_token (admin_token)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS tables (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    table_number INT NOT NULL,
    terrain_type_id INT,
    UNIQUE INDEX idx_tournament_table (tournament_id, table_number),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE,
    FOREIGN KEY (terrain_type_id) REFERENCES terrain_types(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS rounds (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    round_number INT NOT NULL,
    is_published BOOLEAN NOT NULL DEFAULT FALSE,
    UNIQUE INDEX idx_tournament_round (tournament_id, round_number),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS players (
    id INT AUTO_INCREMENT PRIMARY KEY,
    tournament_id INT NOT NULL,
    bcp_player_id VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    UNIQUE INDEX idx_tournament_bcp_player (tournament_id, bcp_player_id),
    FOREIGN KEY (tournament_id) REFERENCES tournaments(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS allocations (
    id INT AUTO_INCREMENT PRIMARY KEY,
    round_id INT NOT NULL,
    table_id INT NOT NULL,
    player1_id INT NOT NULL,
    player2_id INT NOT NULL,
    player1_score INT NOT NULL DEFAULT 0,
    player2_score INT NOT NULL DEFAULT 0,
    allocation_reason JSON,
    INDEX idx_round_table (round_id, table_id),
    UNIQUE INDEX idx_round_player1 (round_id, player1_id),
    UNIQUE INDEX idx_round_player2 (round_id, player2_id),
    INDEX idx_table_id (table_id),
    INDEX idx_player1_id (player1_id),
    INDEX idx_player2_id (player2_id),
    FOREIGN KEY (round_id) REFERENCES rounds(id) ON DELETE CASCADE,
    FOREIGN KEY (table_id) REFERENCES tables(id),
    FOREIGN KEY (player1_id) REFERENCES players(id) ON DELETE CASCADE,
    FOREIGN KEY (player2_id) REFERENCES players(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
SQL;

    // Execute each statement
    $statements = array_filter(
        array_map('trim', explode(';', $schema)),
        function ($stmt) {
            return !empty($stmt) && strpos($stmt, '--') !== 0;
        }
    );

    foreach ($statements as $statement) {
        if (!empty($statement)) {
            $pdo->exec($statement);
        }
    }

    echo "Migration complete. All tables created.\n";

} catch (PDOException $e) {
    fwrite(STDERR, "Database error: " . $e->getMessage() . "\n");
    exit(1);
}
