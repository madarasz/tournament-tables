import { test, expect } from '@playwright/test';
import {
  createCleanupContext,
  cleanupTournaments,
  registerTournament,
} from '../helpers/cleanup';
import { generateUniqueTournament } from '../fixtures/test-data';

/**
 * E2E Tests for User Story 2: Create and Configure Tournament
 *
 * Tests critical tournament creation user flow:
 * - Creating tournament with name, BCP URL, and table count via browser UI
 * - Redirect to dashboard after creation
 * - Tournament displayed on dashboard
 * - Admin token cookie set for authentication
 *
 * Reference: specs/001-table-allocation/tasks.md T108
 * Spec: specs/001-table-allocation/spec.md - User Story 2
 */

test.describe('Tournament Creation (US2)', () => {
  const cleanupContext = createCleanupContext();

  test.afterEach(async ({ request, baseURL }) => {
    await cleanupTournaments(request, cleanupContext, baseURL!);
  });

  test('should create tournament and redirect to dashboard', async ({
    page,
  }) => {
    // Navigate to tournament creation page
    await page.goto('/tournament/create');

    // Verify we're on the creation page
    await expect(page.locator('h1, h2').first()).toBeVisible();

    const tournamentData = generateUniqueTournament('Create');

    // Fill in the tournament form
    await page.locator('input[name="name"]').fill(tournamentData.name);
    await page.locator('input[name="bcpUrl"]').fill(tournamentData.bcpUrl);
    await page
      .locator('input[name="tableCount"]')
      .fill(String(tournamentData.tableCount));

    // Submit the form
    await page.locator('button[type="submit"]').click();

    // Wait for success message
    await expect(page.getByText('Tournament Created!')).toBeVisible();

    // Click "Go to Tournament" button
    await page.getByRole('button', { name: 'Go to Tournament' }).click();

    // Wait for navigation to dashboard
    await page.waitForURL(/\/tournament\/\d+/, { timeout: 10000 });

    // Extract tournament ID from URL
    const url = page.url();
    const tournamentIdMatch = url.match(/\/tournament\/(\d+)/);
    expect(tournamentIdMatch).toBeTruthy();

    const tournamentId = parseInt(tournamentIdMatch![1], 10);

    // Get admin token from cookie to register for cleanup
    const cookies = await page.context().cookies();
    const adminCookie = cookies.find((c) => c.name === 'admin_token');
    expect(adminCookie).toBeTruthy();

    registerTournament(cleanupContext, tournamentId, adminCookie!.value);

    // Verify tournament name is displayed on dashboard
    await expect(page.locator('body')).toContainText(tournamentData.name);
  });
});
