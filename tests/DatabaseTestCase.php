<?php

declare(strict_types=1);

namespace TournamentTables\Tests;

use PHPUnit\Framework\TestCase;
use TournamentTables\Database\Connection;

/**
 * Base test case that wraps each test in a database transaction.
 *
 * This provides automatic test isolation by rolling back all database
 * changes after each test, eliminating the need for manual cleanup.
 *
 * Performance benefit: Rollback is much faster than DELETE queries.
 */
abstract class DatabaseTestCase extends TestCase
{
    /**
     * @var bool Track if we started a transaction for this test
     */
    private $transactionStarted = false;

    /**
     * Start a transaction before each test.
     */
    protected function setUp(): void
    {
        parent::setUp();

        if (!$this->isDatabaseAvailable()) {
            $this->markTestSkipped('Database not available');
        }

        // Start transaction for test isolation
        if (!Connection::inTransaction()) {
            Connection::beginTransaction();
            $this->transactionStarted = true;
        }
    }

    /**
     * Rollback the transaction after each test.
     */
    protected function tearDown(): void
    {
        // Only rollback if we started the transaction
        if ($this->transactionStarted && Connection::inTransaction()) {
            Connection::rollBack();
            $this->transactionStarted = false;
        }

        parent::tearDown();
    }

    /**
     * Check if database is available.
     */
    protected function isDatabaseAvailable(): bool
    {
        try {
            Connection::getInstance();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Execute a callback within a "savepoint" if already in transaction,
     * or a real transaction if not.
     *
     * This allows helper methods that need transactional behavior to work
     * correctly whether wrapped in a test transaction or not.
     *
     * @param callable $callback
     * @return mixed
     */
    protected function executeInTestTransaction(callable $callback)
    {
        // If we're already in a transaction (test isolation), just run the callback
        // The outer transaction will handle rollback on failure
        if (Connection::inTransaction()) {
            return $callback();
        }

        // Otherwise, use a real transaction
        return Connection::executeInTransaction($callback);
    }
}
