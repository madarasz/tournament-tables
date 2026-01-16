import { APIRequestContext } from '@playwright/test';

/**
 * Helper functions for cleaning up test data after E2E tests.
 *
 * Reference: specs/001-table-allocation/tasks.md T103
 */

interface CleanupContext {
  tournamentIds: number[];
  adminTokens: Map<number, string>;
}

/**
 * Creates a new cleanup context to track created test data.
 */
export function createCleanupContext(): CleanupContext {
  return {
    tournamentIds: [],
    adminTokens: new Map(),
  };
}

/**
 * Registers a tournament for cleanup.
 */
export function registerTournament(
  context: CleanupContext,
  tournamentId: number,
  adminToken: string
): void {
  context.tournamentIds.push(tournamentId);
  context.adminTokens.set(tournamentId, adminToken);
}

/**
 * Deletes all registered tournaments via the API.
 * Call this in afterEach or afterAll hooks.
 */
export async function cleanupTournaments(
  request: APIRequestContext,
  context: CleanupContext,
  baseURL: string
): Promise<void> {
  for (const tournamentId of context.tournamentIds) {
    const adminToken = context.adminTokens.get(tournamentId);
    if (!adminToken) {
      console.warn(`No admin token found for tournament ${tournamentId}`);
      continue;
    }

    try {
      const response = await request.delete(
        `${baseURL}/api/tournaments/${tournamentId}`,
        {
          headers: {
            'X-Admin-Token': adminToken,
          },
        }
      );

      if (!response.ok() && response.status() !== 404) {
        console.warn(
          `Failed to delete tournament ${tournamentId}: ${response.status()}`
        );
      }
    } catch (error) {
      console.warn(`Error deleting tournament ${tournamentId}:`, error);
    }
  }

  // Clear the context
  context.tournamentIds = [];
  context.adminTokens.clear();
}

/**
 * Creates a test tournament and registers it for cleanup.
 * Returns the tournament ID and admin token.
 */
export async function createAndRegisterTournament(
  request: APIRequestContext,
  context: CleanupContext,
  baseURL: string,
  options: {
    name?: string;
    bcpUrl?: string;
    tableCount?: number;
  } = {}
): Promise<{ tournamentId: number; adminToken: string }> {
  const name = options.name || `E2E Test Tournament ${Date.now()}`;
  const bcpUrl =
    options.bcpUrl ||
    `https://www.bestcoastpairings.com/event/e2etest${Date.now()}`;
  const tableCount = options.tableCount || 10;

  const response = await request.post(`${baseURL}/api/tournaments`, {
    data: {
      name,
      bcpUrl,
      tableCount,
    },
  });

  if (!response.ok()) {
    throw new Error(`Failed to create tournament: ${await response.text()}`);
  }

  const data = await response.json();
  const tournamentId = data.tournament.id;
  const adminToken = data.adminToken;

  registerTournament(context, tournamentId, adminToken);

  return { tournamentId, adminToken };
}
