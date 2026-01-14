<?php

declare(strict_types=1);

namespace TournamentTables\Tests\Integration;

use PHPUnit\Framework\TestCase;
use TournamentTables\Database\Connection;
use PDO;
use PDOException;

/**
 * Integration tests for database connectivity.
 */
class DatabaseConnectionTest extends TestCase
{
    protected function setUp(): void
    {
        // Skip if no database config available
        $configPath = dirname(__DIR__, 2) . '/config/database.php';
        if (!file_exists($configPath)) {
            $this->markTestSkipped('Database configuration not found. Copy config/database.example.php to config/database.php');
        }
    }

    protected function tearDown(): void
    {
        Connection::reset();
    }

    public function testConnectionReturnsValidPdoInstance(): void
    {
        $pdo = Connection::getInstance();

        $this->assertInstanceOf(PDO::class, $pdo);
    }

    public function testConnectionIsSingleton(): void
    {
        $pdo1 = Connection::getInstance();
        $pdo2 = Connection::getInstance();

        $this->assertSame($pdo1, $pdo2);
    }

    public function testCanExecuteSimpleQuery(): void
    {
        $result = Connection::fetchColumn('SELECT 1');

        $this->assertEquals(1, $result);
    }

    public function testCanExecutePreparedStatement(): void
    {
        $result = Connection::fetchColumn('SELECT ? + ?', [2, 3]);

        $this->assertEquals(5, $result);
    }

    public function testTerrainTypesTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['terrain_types']
        );

        $this->assertNotEmpty($result, 'terrain_types table should exist. Run bin/migrate.php first.');
    }

    public function testTournamentsTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['tournaments']
        );

        $this->assertNotEmpty($result, 'tournaments table should exist. Run bin/migrate.php first.');
    }

    public function testTablesTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['tables']
        );

        $this->assertNotEmpty($result, 'tables table should exist. Run bin/migrate.php first.');
    }

    public function testRoundsTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['rounds']
        );

        $this->assertNotEmpty($result, 'rounds table should exist. Run bin/migrate.php first.');
    }

    public function testPlayersTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['players']
        );

        $this->assertNotEmpty($result, 'players table should exist. Run bin/migrate.php first.');
    }

    public function testAllocationsTableExists(): void
    {
        $result = Connection::fetchAll(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = ?",
            ['allocations']
        );

        $this->assertNotEmpty($result, 'allocations table should exist. Run bin/migrate.php first.');
    }

    public function testTransactionSupport(): void
    {
        $this->assertFalse(Connection::inTransaction());

        Connection::beginTransaction();
        $this->assertTrue(Connection::inTransaction());

        Connection::rollBack();
        $this->assertFalse(Connection::inTransaction());
    }

    public function testInvalidConfigThrowsException(): void
    {
        Connection::reset();
        Connection::setConfig([
            'host' => 'invalid_host_that_does_not_exist',
            'database' => 'invalid_db',
            'username' => 'invalid_user',
            'password' => 'invalid_pass',
            'charset' => 'utf8mb4',
        ]);

        $this->expectException(PDOException::class);
        Connection::getInstance();
    }
}
