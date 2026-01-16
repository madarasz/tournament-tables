/**
 * Mock BCP API responses for E2E tests.
 *
 * These fixtures simulate what the BCP scraper would return,
 * allowing E2E tests to run without actual BCP connectivity.
 *
 * Reference: specs/001-table-allocation/tasks.md T105
 */

/**
 * Mock pairing data for round 1.
 */
export const round1Pairings = {
  roundNumber: 1,
  pairings: [
    {
      tableNumber: 1,
      player1: {
        bcpPlayerId: 'abc123',
        name: 'Alice Smith',
        score: 0,
      },
      player2: {
        bcpPlayerId: 'def456',
        name: 'Bob Jones',
        score: 0,
      },
    },
    {
      tableNumber: 2,
      player1: {
        bcpPlayerId: 'ghi789',
        name: 'Charlie Brown',
        score: 0,
      },
      player2: {
        bcpPlayerId: 'jkl012',
        name: 'Diana Prince',
        score: 0,
      },
    },
    {
      tableNumber: 3,
      player1: {
        bcpPlayerId: 'mno345',
        name: 'Eve Wilson',
        score: 0,
      },
      player2: {
        bcpPlayerId: 'pqr678',
        name: 'Frank Miller',
        score: 0,
      },
    },
    {
      tableNumber: 4,
      player1: {
        bcpPlayerId: 'stu901',
        name: 'Grace Lee',
        score: 0,
      },
      player2: {
        bcpPlayerId: 'vwx234',
        name: 'Henry Ford',
        score: 0,
      },
    },
  ],
};

/**
 * Mock pairing data for round 2 (after round 1 results).
 */
export const round2Pairings = {
  roundNumber: 2,
  pairings: [
    {
      tableNumber: 1,
      player1: {
        bcpPlayerId: 'abc123',
        name: 'Alice Smith',
        score: 1, // Won round 1
      },
      player2: {
        bcpPlayerId: 'ghi789',
        name: 'Charlie Brown',
        score: 1, // Won round 1
      },
    },
    {
      tableNumber: 2,
      player1: {
        bcpPlayerId: 'mno345',
        name: 'Eve Wilson',
        score: 1, // Won round 1
      },
      player2: {
        bcpPlayerId: 'stu901',
        name: 'Grace Lee',
        score: 1, // Won round 1
      },
    },
    {
      tableNumber: 3,
      player1: {
        bcpPlayerId: 'def456',
        name: 'Bob Jones',
        score: 0, // Lost round 1
      },
      player2: {
        bcpPlayerId: 'jkl012',
        name: 'Diana Prince',
        score: 0, // Lost round 1
      },
    },
    {
      tableNumber: 4,
      player1: {
        bcpPlayerId: 'pqr678',
        name: 'Frank Miller',
        score: 0, // Lost round 1
      },
      player2: {
        bcpPlayerId: 'vwx234',
        name: 'Henry Ford',
        score: 0, // Lost round 1
      },
    },
  ],
};

/**
 * Mock pairing data for round 3.
 */
export const round3Pairings = {
  roundNumber: 3,
  pairings: [
    {
      tableNumber: 1,
      player1: {
        bcpPlayerId: 'abc123',
        name: 'Alice Smith',
        score: 2, // 2-0
      },
      player2: {
        bcpPlayerId: 'mno345',
        name: 'Eve Wilson',
        score: 2, // 2-0
      },
    },
    {
      tableNumber: 2,
      player1: {
        bcpPlayerId: 'ghi789',
        name: 'Charlie Brown',
        score: 1, // 1-1
      },
      player2: {
        bcpPlayerId: 'def456',
        name: 'Bob Jones',
        score: 1, // 1-1
      },
    },
    {
      tableNumber: 3,
      player1: {
        bcpPlayerId: 'stu901',
        name: 'Grace Lee',
        score: 1, // 1-1
      },
      player2: {
        bcpPlayerId: 'pqr678',
        name: 'Frank Miller',
        score: 1, // 1-1
      },
    },
    {
      tableNumber: 4,
      player1: {
        bcpPlayerId: 'jkl012',
        name: 'Diana Prince',
        score: 0, // 0-2
      },
      player2: {
        bcpPlayerId: 'vwx234',
        name: 'Henry Ford',
        score: 0, // 0-2
      },
    },
  ],
};

/**
 * Large tournament pairing data (20 players).
 */
export const largeTournamentPairings = {
  roundNumber: 1,
  pairings: Array.from({ length: 10 }, (_, i) => ({
    tableNumber: i + 1,
    player1: {
      bcpPlayerId: `player${i * 2 + 1}`,
      name: `Player ${i * 2 + 1}`,
      score: 0,
    },
    player2: {
      bcpPlayerId: `player${i * 2 + 2}`,
      name: `Player ${i * 2 + 2}`,
      score: 0,
    },
  })),
};

/**
 * Pairing with BYE (odd number of players).
 */
export const pairingsWithBye = {
  roundNumber: 1,
  pairings: [
    {
      tableNumber: 1,
      player1: {
        bcpPlayerId: 'abc123',
        name: 'Alice Smith',
        score: 0,
      },
      player2: {
        bcpPlayerId: 'def456',
        name: 'Bob Jones',
        score: 0,
      },
    },
    {
      tableNumber: 2,
      player1: {
        bcpPlayerId: 'ghi789',
        name: 'Charlie Brown',
        score: 0,
      },
      player2: null, // BYE
    },
  ],
};

/**
 * Empty pairings (round not yet posted).
 */
export const emptyPairings = {
  roundNumber: 1,
  pairings: [],
};

/**
 * Get mock pairings for a specific round.
 */
export function getMockPairings(roundNumber: number): typeof round1Pairings {
  switch (roundNumber) {
    case 1:
      return round1Pairings;
    case 2:
      return round2Pairings;
    case 3:
      return round3Pairings;
    default:
      // Generate generic pairings for rounds > 3
      return {
        roundNumber,
        pairings: round1Pairings.pairings.map((p, i) => ({
          ...p,
          player1: { ...p.player1, score: roundNumber - 1 },
          player2: { ...p.player2, score: roundNumber - 1 },
        })),
      };
  }
}
