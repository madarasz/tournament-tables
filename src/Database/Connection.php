<?php

declare(strict_types=1);

namespace TournamentTables\Database;

use PDO;
use PDOStatement;
use PDOException;

/**
 * Database connection singleton with prepared statement helpers.
 *
 * Constitution Principle III: All SQL queries use prepared statements.
 */
class Connection
{
    /** @var PDO|null */
    private static $instance = null;

    /** @var array */
    private static $config = [];

    private function __construct()
    {
        // Private constructor for singleton
    }

    /**
     * Get the PDO instance (singleton).
     */
    public static function getInstance(): PDO
    {
        if (self::$instance === null) {
            self::$instance = self::createConnection();
        }
        return self::$instance;
    }

    /**
     * Set configuration (for testing or custom config).
     */
    public static function setConfig(array $config): void
    {
        self::$config = $config;
        self::$instance = null; // Reset connection when config changes
    }

    /**
     * Reset the connection (for testing).
     */
    public static function reset(): void
    {
        self::$instance = null;
    }

    /**
     * Create a new PDO connection.
     */
    private static function createConnection(): PDO
    {
        $config = self::loadConfig();

        $dsn = sprintf(
            'mysql:host=%s;port=%s;dbname=%s;charset=%s',
            $config['host'],
            $config['port'] ?? 3306,
            $config['database'],
            $config['charset']
        );

        $pdo = new PDO($dsn, $config['username'], $config['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]);

        return $pdo;
    }

    /**
     * Load configuration from file or static config.
     */
    private static function loadConfig(): array
    {
        if (!empty(self::$config)) {
            return self::$config;
        }

        $configPath = dirname(__DIR__, 2) . '/config/database.php';
        if (!file_exists($configPath)) {
            throw new PDOException('Database configuration file not found: config/database.php');
        }

        return require $configPath;
    }

    /**
     * Execute a prepared statement and return all results.
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array Result rows
     */
    public static function fetchAll(string $sql, array $params = []): array
    {
        $stmt = self::execute($sql, $params);
        return $stmt->fetchAll();
    }

    /**
     * Execute a prepared statement and return one row.
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return array|null Single row or null
     */
    public static function fetchOne(string $sql, array $params = []): ?array
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetch();
        return $result !== false ? $result : null;
    }

    /**
     * Execute a prepared statement and return a single column value.
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @param int $column Column index (default 0)
     * @return mixed|null Column value or null
     */
    public static function fetchColumn(string $sql, array $params = [], int $column = 0)
    {
        $stmt = self::execute($sql, $params);
        $result = $stmt->fetchColumn($column);
        return $result !== false ? $result : null;
    }

    /**
     * Execute a prepared statement (INSERT, UPDATE, DELETE).
     *
     * @param string $sql SQL query with placeholders
     * @param array $params Parameters to bind
     * @return PDOStatement Executed statement
     */
    public static function execute(string $sql, array $params = []): PDOStatement
    {
        $pdo = self::getInstance();
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }

    /**
     * Get the last inserted ID.
     */
    public static function lastInsertId(): int
    {
        return (int) self::getInstance()->lastInsertId();
    }

    /**
     * Begin a transaction.
     */
    public static function beginTransaction(): bool
    {
        return self::getInstance()->beginTransaction();
    }

    /**
     * Commit a transaction.
     */
    public static function commit(): bool
    {
        return self::getInstance()->commit();
    }

    /**
     * Rollback a transaction.
     */
    public static function rollBack(): bool
    {
        return self::getInstance()->rollBack();
    }

    /**
     * Check if inside a transaction.
     */
    public static function inTransaction(): bool
    {
        return self::getInstance()->inTransaction();
    }
}
