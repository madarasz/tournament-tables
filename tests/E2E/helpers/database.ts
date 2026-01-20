/**
 * Database helper for E2E tests.
 *
 * Provides functions to seed and cleanup test data directly via MySQL.
 * This is used for tests that need deterministic data independent of the BCP import flow.
 */

import { createConnection, Connection } from 'mysql2/promise';
import * as fs from 'fs';
import * as path from 'path';

/**
 * Database connection configuration.
 * Uses environment variables with fallback to Docker defaults.
 */
const DB_CONFIG = {
  host: process.env.DB_HOST || 'mysql',
  port: parseInt(process.env.DB_PORT || '3306', 10),
  user: process.env.DB_USER || 'root',
  password: process.env.DB_PASSWORD || 'root',
  database: process.env.DB_NAME || 'tournament_tables',
  multipleStatements: true,
};

/**
 * Creates a database connection.
 */
async function getConnection(): Promise<Connection> {
  return createConnection(DB_CONFIG);
}

/**
 * Seeds test data from a SQL fixture file.
 *
 * @param fixtureName - Name of the fixture file (without .sql extension)
 */
export async function seedTestData(fixtureName: string): Promise<void> {
  const connection = await getConnection();

  try {
    const sqlPath = path.join(__dirname, '..', 'fixtures', `${fixtureName}.sql`);
    const sql = fs.readFileSync(sqlPath, 'utf8');
    await connection.query(sql);
  } finally {
    await connection.end();
  }
}

/**
 * Cleans up test data for a specific tournament.
 * Deletes all related data in the correct order due to foreign key constraints.
 *
 * @param tournamentId - ID of the tournament to clean up
 */
export async function cleanupTestData(tournamentId: number): Promise<void> {
  const connection = await getConnection();

  try {
    await connection.beginTransaction();
    // Delete in correct order due to foreign keys
    await connection.query(
      'DELETE FROM allocations WHERE round_id IN (SELECT id FROM rounds WHERE tournament_id = ?)',
      [tournamentId]
    );
    await connection.query('DELETE FROM rounds WHERE tournament_id = ?', [
      tournamentId,
    ]);
    await connection.query('DELETE FROM players WHERE tournament_id = ?', [
      tournamentId,
    ]);
    await connection.query('DELETE FROM tables WHERE tournament_id = ?', [
      tournamentId,
    ]);
    await connection.query('DELETE FROM tournaments WHERE id = ?', [
      tournamentId,
    ]);
    await connection.commit();
  } catch (err) {
    await connection.rollback();
    throw err;
  } finally {
    await connection.end();
  }
}

/**
 * Executes a raw SQL query.
 * Useful for custom data manipulation in tests.
 *
 * @param sql - SQL query to execute
 * @param params - Optional query parameters
 */
export async function executeQuery(
  sql: string,
  params?: unknown[]
): Promise<unknown> {
  const connection = await getConnection();

  try {
    const [result] = await connection.query(sql, params);
    return result;
  } finally {
    await connection.end();
  }
}

/**
 * Checks if a tournament exists in the database.
 *
 * @param tournamentId - ID of the tournament to check
 */
export async function tournamentExists(tournamentId: number): Promise<boolean> {
  const connection = await getConnection();

  try {
    const [rows] = await connection.query(
      'SELECT COUNT(*) as count FROM tournaments WHERE id = ?',
      [tournamentId]
    );
    const result = rows as Array<{ count: number }>;
    return result[0].count > 0;
  } finally {
    await connection.end();
  }
}
