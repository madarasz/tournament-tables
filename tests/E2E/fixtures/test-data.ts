/**
 * Test data fixtures for E2E tests.
 *
 * Reference: specs/001-table-allocation/tasks.md T104
 */

/**
 * Valid tournament creation data.
 */
export const validTournament = {
  name: 'Kill Team GT January 2026',
  bcpUrl: 'https://www.bestcoastpairings.com/event/testEvent123',
  tableCount: 12,
};

/**
 * Tournament with minimum tables.
 */
export const minimalTournament = {
  name: 'Small Event',
  bcpUrl: 'https://www.bestcoastpairings.com/event/smallEvent',
  tableCount: 1,
};

/**
 * Tournament with maximum tables.
 */
export const largeTournament = {
  name: 'Major Championship',
  bcpUrl: 'https://www.bestcoastpairings.com/event/majorChamp',
  tableCount: 100,
};

/**
 * Invalid tournament data for validation tests.
 */
export const invalidTournaments = {
  emptyName: {
    name: '',
    bcpUrl: 'https://www.bestcoastpairings.com/event/valid',
    tableCount: 10,
  },
  invalidUrl: {
    name: 'Invalid URL Tournament',
    bcpUrl: 'https://example.com/event/invalid',
    tableCount: 10,
  },
  zeroTables: {
    name: 'Zero Tables',
    bcpUrl: 'https://www.bestcoastpairings.com/event/zero',
    tableCount: 0,
  },
  tooManyTables: {
    name: 'Too Many Tables',
    bcpUrl: 'https://www.bestcoastpairings.com/event/toomany',
    tableCount: 101,
  },
  missingBcpUrl: {
    name: 'Missing URL',
    bcpUrl: '',
    tableCount: 10,
  },
};

/**
 * Terrain type configuration for tables.
 */
export const terrainConfigurations = {
  volkus: { tableNumber: 1, terrainTypeId: 1 },
  tombWorld: { tableNumber: 2, terrainTypeId: 2 },
  gallowdark: { tableNumber: 3, terrainTypeId: 3 },
  imperial: { tableNumber: 4, terrainTypeId: 4 },
  mechanicus: { tableNumber: 5, terrainTypeId: 5 },
};

/**
 * Sample player data for allocation tests.
 */
export const samplePlayers = [
  { bcpPlayerId: 'player1', name: 'Alice Smith', score: 3 },
  { bcpPlayerId: 'player2', name: 'Bob Jones', score: 3 },
  { bcpPlayerId: 'player3', name: 'Charlie Brown', score: 2 },
  { bcpPlayerId: 'player4', name: 'Diana Prince', score: 2 },
  { bcpPlayerId: 'player5', name: 'Eve Wilson', score: 1 },
  { bcpPlayerId: 'player6', name: 'Frank Miller', score: 1 },
  { bcpPlayerId: 'player7', name: 'Grace Lee', score: 0 },
  { bcpPlayerId: 'player8', name: 'Henry Ford', score: 0 },
];

/**
 * Sample pairings for round simulation.
 */
export const samplePairings = [
  {
    player1: samplePlayers[0],
    player2: samplePlayers[1],
    tableNumber: 1,
  },
  {
    player1: samplePlayers[2],
    player2: samplePlayers[3],
    tableNumber: 2,
  },
  {
    player1: samplePlayers[4],
    player2: samplePlayers[5],
    tableNumber: 3,
  },
  {
    player1: samplePlayers[6],
    player2: samplePlayers[7],
    tableNumber: 4,
  },
];

/**
 * Authentication test data.
 */
export const authTestData = {
  validTokenPattern: /^[A-Za-z0-9+/]{16}$/,
  invalidTokens: [
    '', // empty
    'short', // too short
    'this-token-is-way-too-long-for-our-system', // too long
    'invalid!@#$%^&*', // invalid characters
  ],
};

/**
 * Helper to generate unique tournament data.
 */
export function generateUniqueTournament(prefix = 'E2E'): {
  name: string;
  bcpUrl: string;
  tableCount: number;
} {
  const timestamp = Date.now();
  const random = Math.random().toString(36).substring(7);
  return {
    name: `${prefix} Test ${timestamp}`,
    bcpUrl: `https://www.bestcoastpairings.com/event/${prefix}${timestamp}${random}`,
    tableCount: 10,
  };
}

/**
 * Terrain types expected in the system (seeded data).
 */
export const expectedTerrainTypes = [
  { id: 1, name: 'Volkus' },
  { id: 2, name: 'Tomb World' },
  { id: 3, name: 'Gallowdark' },
  { id: 4, name: 'Imperial' },
  { id: 5, name: 'Mechanicus' },
  { id: 6, name: 'Alien' },
  { id: 7, name: 'Octarius' },
];
