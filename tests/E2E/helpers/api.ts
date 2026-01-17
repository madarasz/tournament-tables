import { APIRequestContext, expect } from '@playwright/test';

/**
 * API helper functions for E2E tests.
 *
 * Provides type-safe wrappers around API calls for test setup and verification.
 *
 * Reference: specs/001-table-allocation/tasks.md T106
 */

/**
 * Tournament data returned from the API.
 */
export interface Tournament {
  id: number;
  name: string;
  bcpEventId: string;
  bcpUrl: string;
  tableCount: number;
}

/**
 * Table data returned from the API.
 */
export interface Table {
  id: number;
  tableNumber: number;
  terrainType?: {
    id: number;
    name: string;
  };
}

/**
 * Terrain type data.
 */
export interface TerrainType {
  id: number;
  name: string;
  sortOrder: number;
}

/**
 * Round data.
 */
export interface Round {
  roundNumber: number;
  isPublished: boolean;
  allocationCount: number;
}

/**
 * Allocation data.
 */
export interface Allocation {
  id: number;
  tableNumber: number;
  terrainType?: string;
  player1: {
    id: number;
    name: string;
    score: number;
  };
  player2: {
    id: number;
    name: string;
    score: number;
  };
  conflicts: Array<{
    type: string;
    message: string;
  }>;
}

/**
 * API client for E2E tests.
 */
export class ApiClient {
  constructor(
    private request: APIRequestContext,
    private baseURL: string
  ) {}

  /**
   * Creates a new tournament.
   * Table count is optional - if not provided, tables will be created from Round 1 pairings.
   */
  async createTournament(data: {
    name: string;
    bcpUrl: string;
    tableCount?: number;
  }): Promise<{ tournament: Tournament; adminToken: string }> {
    const response = await this.request.post(`${this.baseURL}/api/tournaments`, {
      data,
    });

    expect(response.ok(), `Create tournament failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Gets tournament details.
   */
  async getTournament(
    tournamentId: number,
    adminToken: string
  ): Promise<Tournament & { tables: Table[]; rounds: Round[] }> {
    const response = await this.request.get(
      `${this.baseURL}/api/tournaments/${tournamentId}`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Get tournament failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Deletes a tournament.
   */
  async deleteTournament(
    tournamentId: number,
    adminToken: string
  ): Promise<void> {
    const response = await this.request.delete(
      `${this.baseURL}/api/tournaments/${tournamentId}`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Delete tournament failed: ${await response.text()}`).toBeTruthy();
  }

  /**
   * Updates table terrain types.
   */
  async updateTables(
    tournamentId: number,
    adminToken: string,
    tables: Array<{ tableNumber: number; terrainTypeId: number | null }>
  ): Promise<{ tables: Table[] }> {
    const response = await this.request.put(
      `${this.baseURL}/api/tournaments/${tournamentId}/tables`,
      {
        headers: { 'X-Admin-Token': adminToken },
        data: { tables },
      }
    );

    expect(response.ok(), `Update tables failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Gets terrain types.
   */
  async getTerrainTypes(): Promise<{ terrainTypes: TerrainType[] }> {
    const response = await this.request.get(
      `${this.baseURL}/api/terrain-types`
    );

    expect(response.ok(), `Get terrain types failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Authenticates with admin token.
   */
  async authenticate(
    token: string
  ): Promise<{ tournamentId: number; tournamentName: string; message: string }> {
    const response = await this.request.post(`${this.baseURL}/api/auth`, {
      data: { token },
    });

    if (!response.ok()) {
      throw new Error(`Authentication failed: ${await response.text()}`);
    }

    return response.json();
  }

  /**
   * Imports pairings from BCP for a round.
   */
  async importPairings(
    tournamentId: number,
    roundNumber: number,
    adminToken: string
  ): Promise<{
    roundNumber: number;
    pairingsImported: number;
    playersImported: number;
    message: string;
  }> {
    const response = await this.request.post(
      `${this.baseURL}/api/tournaments/${tournamentId}/rounds/${roundNumber}/import`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Import pairings failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Generates allocations for a round.
   */
  async generateAllocations(
    tournamentId: number,
    roundNumber: number,
    adminToken: string
  ): Promise<{
    roundNumber: number;
    allocations: Allocation[];
    conflicts: Array<{ type: string; message: string }>;
    summary: string;
  }> {
    const response = await this.request.post(
      `${this.baseURL}/api/tournaments/${tournamentId}/rounds/${roundNumber}/generate`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Generate allocations failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Gets round allocations.
   */
  async getRound(
    tournamentId: number,
    roundNumber: number,
    adminToken: string
  ): Promise<{
    roundNumber: number;
    isPublished: boolean;
    allocations: Allocation[];
    conflicts: Array<{ type: string; message: string }>;
  }> {
    const response = await this.request.get(
      `${this.baseURL}/api/tournaments/${tournamentId}/rounds/${roundNumber}`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Get round failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Publishes a round.
   */
  async publishRound(
    tournamentId: number,
    roundNumber: number,
    adminToken: string
  ): Promise<{ roundNumber: number; message: string }> {
    const response = await this.request.post(
      `${this.baseURL}/api/tournaments/${tournamentId}/rounds/${roundNumber}/publish`,
      {
        headers: { 'X-Admin-Token': adminToken },
      }
    );

    expect(response.ok(), `Publish round failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Updates an allocation.
   */
  async updateAllocation(
    allocationId: number,
    tableId: number,
    adminToken: string
  ): Promise<Allocation> {
    const response = await this.request.patch(
      `${this.baseURL}/api/allocations/${allocationId}`,
      {
        headers: { 'X-Admin-Token': adminToken },
        data: { tableId },
      }
    );

    expect(response.ok(), `Update allocation failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Swaps two allocations.
   */
  async swapAllocations(
    allocationId1: number,
    allocationId2: number,
    adminToken: string
  ): Promise<{ allocation1: Allocation; allocation2: Allocation }> {
    const response = await this.request.post(
      `${this.baseURL}/api/allocations/swap`,
      {
        headers: { 'X-Admin-Token': adminToken },
        data: { allocationId1, allocationId2 },
      }
    );

    expect(response.ok(), `Swap allocations failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Gets public tournament info.
   */
  async getPublicTournament(
    tournamentId: number
  ): Promise<{
    id: number;
    name: string;
    tableCount: number;
    publishedRounds: number[];
  }> {
    const response = await this.request.get(
      `${this.baseURL}/api/public/tournaments/${tournamentId}`
    );

    expect(response.ok(), `Get public tournament failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }

  /**
   * Gets public round allocations.
   */
  async getPublicRound(
    tournamentId: number,
    roundNumber: number
  ): Promise<{
    tournamentName: string;
    roundNumber: number;
    allocations: Array<{
      tableNumber: number;
      terrainType?: string;
      player1Name: string;
      player1Score: number;
      player2Name: string;
      player2Score: number;
    }>;
  }> {
    const response = await this.request.get(
      `${this.baseURL}/api/public/tournaments/${tournamentId}/rounds/${roundNumber}`
    );

    expect(response.ok(), `Get public round failed: ${await response.text()}`).toBeTruthy();
    return response.json();
  }
}

/**
 * Creates an API client with the given request context.
 */
export function createApiClient(
  request: APIRequestContext,
  baseURL: string
): ApiClient {
  return new ApiClient(request, baseURL);
}
