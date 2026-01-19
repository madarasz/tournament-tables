import { test, expect } from '@playwright/test';
import {
  createCleanupContext,
  cleanupTournaments,
  createAndRegisterTournament,
} from '../helpers/cleanup';
import { getAdminTokenFromCookies, clearAdminTokenCookie } from '../helpers/auth';

/**
 * E2E Tests for User Story 5: Admin Authentication
 *
 * Tests critical authentication user flow:
 * - Login with valid admin token via browser UI
 * - Redirect to tournament dashboard
 * - Cookie-based authentication
 *
 * Reference: specs/001-table-allocation/tasks.md T110
 * Spec: specs/001-table-allocation/spec.md - User Story 5
 */

test.describe('Admin Authentication (US5)', () => {
  const cleanupContext = createCleanupContext();

  test.afterEach(async ({ request, baseURL }) => {
    await cleanupTournaments(request, cleanupContext, baseURL!);
  });

  test('should login with admin token and access tournament dashboard', async ({
    page,
    request,
    baseURL,
  }) => {
    // Create a tournament to get a valid admin token
    const { tournamentId, adminToken } = await createAndRegisterTournament(
      request,
      cleanupContext,
      baseURL!
    );

    // Clear any existing cookies
    await clearAdminTokenCookie(page.context(), baseURL!);

    // Navigate to login page
    await page.goto('/login');

    // Fill in the token
    await page.locator('input[name="token"]').fill(adminToken);

    // Submit the form
    await page.locator('button[type="submit"]').click();

    // Wait for success message
    await expect(page.getByText('Login Successful')).toBeVisible();

    // Click "Go to Tournament" button
    await page.getByRole('button', { name: 'Go to Tournament' }).click();

    // Should navigate to tournament dashboard
    await page.waitForURL(/\/tournament\/\d+/, { timeout: 10000 });

    // Verify we're on the correct tournament
    expect(page.url()).toContain(`/tournament/${tournamentId}`);

    // Verify cookie is set (JSON format with tournament tokens)
    const cookieValue = await getAdminTokenFromCookies(page.context(), baseURL!);
    expect(cookieValue).toBeTruthy();

    // Parse JSON cookie and verify tournament token is present
    // Cookie values are URL-encoded by browsers, so decode first
    const decodedCookie = decodeURIComponent(cookieValue!);
    const cookieData = JSON.parse(decodedCookie);
    expect(cookieData.tournaments).toBeDefined();
    expect(cookieData.tournaments[tournamentId]).toBeDefined();
    expect(cookieData.tournaments[tournamentId].token).toBe(adminToken);

    // Verify tournament appears on home page
    await page.goto('/');
    await expect(page.locator('h1')).toContainText('My Tournaments');

    // Find the tournament in the list (table row containing tournament name)
    // Tournament name is auto-imported from BCP mock: "Test Tournament {eventId}"
    const tournamentRow = page.locator('tr').filter({ hasText: /Test Tournament/ });
    await expect(tournamentRow).toBeVisible();

    // Verify tournament name link (styled as button) is present
    await expect(tournamentRow.getByRole('button')).toBeVisible();
  });
});
