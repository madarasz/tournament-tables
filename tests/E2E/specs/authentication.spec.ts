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

    // Verify cookie is set
    const cookie = await getAdminTokenFromCookies(page.context(), baseURL!);
    expect(cookie).toBe(adminToken);
  });
});
