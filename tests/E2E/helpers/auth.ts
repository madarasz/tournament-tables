import { Page, BrowserContext } from '@playwright/test';

/**
 * Authentication helper functions for E2E tests.
 *
 * Reference: specs/001-table-allocation/tasks.md T107
 */

/**
 * Sets the admin token cookie for authentication.
 * The cookie format matches the multi-token structure used by the application:
 * { "tournaments": { [tournamentId]: { "token": "...", "name": "...", "lastAccessed": ... } } }
 */
export async function setAdminTokenCookie(
  context: BrowserContext,
  adminToken: string,
  baseURL: string,
  tournamentId?: number,
  tournamentName?: string
): Promise<void> {
  const url = new URL(baseURL);

  // Create the multi-token cookie structure
  let cookieValue: string;
  if (tournamentId !== undefined) {
    const cookieData = {
      tournaments: {
        [tournamentId]: {
          token: adminToken,
          name: tournamentName || `Tournament ${tournamentId}`,
          lastAccessed: Math.floor(Date.now() / 1000),
        },
      },
    };
    cookieValue = JSON.stringify(cookieData);
  } else {
    // Fallback for backward compatibility (though this shouldn't be used)
    cookieValue = adminToken;
  }

  await context.addCookies([
    {
      name: 'admin_token',
      value: cookieValue,
      domain: url.hostname,
      path: '/',
      httpOnly: false, // Must be false so JavaScript can read it for X-Admin-Token header
      sameSite: 'Lax',
      expires: Math.floor(Date.now() / 1000) + 30 * 24 * 60 * 60, // 30 days
    },
  ]);
}

/**
 * Clears the admin token cookie.
 */
export async function clearAdminTokenCookie(
  context: BrowserContext,
  baseURL: string
): Promise<void> {
  const cookies = await context.cookies(baseURL);
  const adminCookie = cookies.find((c) => c.name === 'admin_token');

  if (adminCookie) {
    await context.clearCookies();
  }
}

/**
 * Gets the current admin token from cookies.
 */
export async function getAdminTokenFromCookies(
  context: BrowserContext,
  baseURL: string
): Promise<string | null> {
  const cookies = await context.cookies(baseURL);
  const adminCookie = cookies.find((c) => c.name === 'admin_token');
  return adminCookie?.value ?? null;
}

/**
 * Logs in via the login page UI.
 */
export async function loginViaUI(
  page: Page,
  adminToken: string
): Promise<void> {
  await page.goto('/admin/login');

  // Fill in the token input
  await page.locator('input[name="token"]').fill(adminToken);

  // Click the login button
  await page.locator('button[type="submit"]').click();

  // Wait for redirect to dashboard
  await page.waitForURL(/\/admin\/tournament\/\d+/);
}

/**
 * Verifies the user is authenticated by checking for admin UI elements.
 */
export async function verifyAuthenticated(page: Page): Promise<boolean> {
  // Check if we're on a protected page (tournament dashboard)
  const url = page.url();
  if (url.includes('/admin/tournament/')) {
    return true;
  }

  // Check for admin-only elements
  const adminElements = await page.locator('[data-admin-only]').count();
  return adminElements > 0;
}

/**
 * Verifies the user is NOT authenticated.
 */
export async function verifyNotAuthenticated(page: Page): Promise<boolean> {
  // Check if redirected to login page
  const url = page.url();
  if (url.includes('/admin/login')) {
    return true;
  }

  // Check we're not on a protected page
  if (url.includes('/admin/tournament/')) {
    return false;
  }

  return true;
}

/**
 * Navigates to the tournament dashboard (requires authentication).
 */
export async function goToTournamentDashboard(
  page: Page,
  tournamentId: number
): Promise<void> {
  await page.goto(`/admin/tournament/${tournamentId}`);
  await page.waitForLoadState('networkidle');
}

/**
 * Navigates to a specific round management page (requires authentication).
 */
export async function goToRoundManagement(
  page: Page,
  tournamentId: number,
  roundNumber: number
): Promise<void> {
  await page.goto(`/admin/tournament/${tournamentId}/round/${roundNumber}`);
  await page.waitForLoadState('networkidle');
}

/**
 * Creates a tournament and returns authentication context.
 * Note: Tournament name is auto-imported from BCP, so only bcpUrl is required.
 * BCP mock should be set up before calling this function.
 */
export async function createTournamentAndAuthenticate(
  page: Page,
  tournamentData: {
    bcpUrl: string;
  }
): Promise<{ tournamentId: number; adminToken: string }> {
  // Navigate to create tournament page
  await page.goto('/admin/tournament/create');

  // Fill in the form (name is auto-imported from BCP)
  await page.locator('input[name="bcpUrl"]').fill(tournamentData.bcpUrl);

  // Submit the form
  await page.locator('button[type="submit"]').click();

  // Wait for redirect to dashboard
  await page.waitForURL(/\/admin\/tournament\/\d+/);

  // Extract tournament ID from URL
  const url = page.url();
  const match = url.match(/\/admin\/tournament\/(\d+)/);
  if (!match) {
    throw new Error('Failed to extract tournament ID from URL');
  }

  const tournamentId = parseInt(match[1], 10);

  // Get admin token from cookies
  const context = page.context();
  const cookies = await context.cookies();
  const adminCookie = cookies.find((c) => c.name === 'admin_token');

  if (!adminCookie) {
    throw new Error('Admin token cookie not found after tournament creation');
  }

  return {
    tournamentId,
    adminToken: adminCookie.value,
  };
}
