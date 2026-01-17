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
 * - Creating tournament with BCP URL via browser UI (name auto-imported from BCP)
 * - Automatic import of Round 1 and table creation
 * - Automatic redirect to dashboard after creation
 * - Success message and admin token displayed on dashboard
 * - Tournament displayed on dashboard
 * - Admin token cookie set for authentication
 *
 * Note: In the test environment, BCP requests are redirected to a mock endpoint
 * via the BCP_MOCK_BASE_URL environment variable. The mock returns HTML with
 * the tournament name "Test Tournament {eventId}".
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

    // Fill in the tournament form (name is auto-imported from BCP mock)
    await page.locator('input[name="bcpUrl"]').fill(tournamentData.bcpUrl);

    // Submit the form
    await page.locator('button[type="submit"]').click();

    // Wait for automatic redirect to dashboard
    await page.waitForURL(/\/tournament\/\d+/, { timeout: 10000 });

    // Extract tournament ID from URL
    const url = page.url();
    const tournamentIdMatch = url.match(/\/tournament\/(\d+)/);
    expect(tournamentIdMatch).toBeTruthy();

    const tournamentId = parseInt(tournamentIdMatch![1], 10);

    // Verify success message is displayed on dashboard
    await expect(page.getByText('Tournament Created Successfully!')).toBeVisible();

    // Verify admin token is displayed
    await expect(page.locator('#admin-token-display')).toBeVisible();

    // Verify copy button is present
    await expect(page.locator('#copy-token-btn')).toBeVisible();

    // Get admin token from cookie to register for cleanup
    const cookies = await page.context().cookies();
    const adminCookie = cookies.find((c) => c.name === 'admin_token');
    expect(adminCookie).toBeTruthy();

    // Parse JSON cookie to extract the actual admin token
    // Cookie values are URL-encoded by browsers, so decode first
    const decodedCookie = decodeURIComponent(adminCookie!.value);
    const cookieData = JSON.parse(decodedCookie);
    const actualAdminToken = cookieData.tournaments[tournamentId].token;

    registerTournament(cleanupContext, tournamentId, actualAdminToken);

    // Verify tournament name (auto-imported from BCP) is displayed on dashboard
    await expect(page.locator('body')).toContainText(tournamentData.expectedName);

    // Navigate to home page
    await page.goto('/');

    // Verify tournament is listed on home page
    await expect(page.locator('h1')).toContainText('My Tournaments');
    await expect(page.locator('body')).toContainText(tournamentData.expectedName);

    // Verify tournament appears in the table with correct metadata
    const tournamentRow = page.locator('tr').filter({ hasText: tournamentData.expectedName });
    await expect(tournamentRow).toBeVisible();

    // Verify table count is displayed (auto-imported from BCP)
    await expect(tournamentRow).toContainText(/\d+/);

    // Verify "View Dashboard" button works
    await tournamentRow.locator('a[role="button"]', { hasText: 'View Dashboard' }).click();
    await page.waitForURL(/\/tournament\/\d+/);
    expect(page.url()).toContain(`/tournament/${tournamentId}`);
  });
});
