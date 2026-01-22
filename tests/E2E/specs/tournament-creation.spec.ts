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
    await page.goto('/admin/tournament/create');

    // Verify we're on the creation page
    await expect(page.locator('h1, h2').first()).toBeVisible();

    const tournamentData = generateUniqueTournament('Create');

    // Fill in the tournament form (name is auto-imported from BCP mock)
    await page.locator('input[name="bcpUrl"]').fill(tournamentData.bcpUrl);

    // Submit the form
    await page.locator('button[type="submit"]').click();

    // Wait for automatic redirect to dashboard
    await page.waitForURL(/\/admin\/tournament\/\d+/, { timeout: 10000 });

    // Extract tournament ID from URL
    const url = page.url();
    const tournamentIdMatch = url.match(/\/admin\/tournament\/(\d+)/);
    expect(tournamentIdMatch).toBeTruthy();

    const tournamentId = parseInt(tournamentIdMatch![1], 10);

    // Verify success message is displayed on dashboard
    await expect(page.getByText('Tournament Created Successfully!')).toBeVisible();

    // Verify admin token is displayed
    await expect(page.locator('#admin-token-display')).toBeVisible();

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

    // Verify Round 1 was automatically imported - check "Rounds" section
    await expect(page.locator('h2').filter({ hasText: 'Rounds' })).toBeVisible();

    // Verify Round 1 row exists in the rounds table with expected content
    const roundsTable = page.locator('section').filter({ has: page.locator('h2', { hasText: 'Rounds' }) }).locator('table');
    await expect(roundsTable).toBeVisible();

    // Check Round 1 row is displayed with Draft status
    // Round 1 should be the first row in tbody (only round at this point)
    const round1Row = roundsTable.locator('tbody tr').first();
    await expect(round1Row).toBeVisible();
    await expect(round1Row.locator('td').first()).toHaveText('1');
    await expect(round1Row).toContainText('Draft');

    // Navigate to home page
    await page.goto('/admin');

    // Verify tournament is listed on home page
    await expect(page.locator('h1')).toContainText('My Tournaments');
    await expect(page.locator('body')).toContainText(tournamentData.expectedName);

    // Verify tournament appears in the table with correct metadata
    const tournamentRow = page.locator('tr').filter({ hasText: tournamentData.expectedName });
    await expect(tournamentRow).toBeVisible();

    // Verify table count is displayed (auto-imported from BCP)
    await expect(tournamentRow).toContainText(/\d+/);

    // Verify tournament name link (styled as button) works
    await tournamentRow.getByRole('button').click();
    await page.waitForURL(/\/admin\/tournament\/\d+/);
    expect(page.url()).toContain(`/admin/tournament/${tournamentId}`);
  });

  /**
   * Test: Import Round from Dashboard - Auto-Redirect to Manage Page
   *
   * Verifies UX Improvement #5: After importing a round from the dashboard,
   * the user should be immediately redirected to the manage page with a
   * success message displayed.
   *
   * Reference: docs/ui-ux-improvements.md - Improvement #5
   */
  test('should redirect to manage page after importing round from dashboard', async ({
    page,
  }) => {
    // Step 1: Create a tournament (which auto-imports Round 1)
    await page.goto('/admin/tournament/create');
    const tournamentData = generateUniqueTournament('RoundImport');
    await page.locator('input[name="bcpUrl"]').fill(tournamentData.bcpUrl);
    await page.locator('button[type="submit"]').click();

    // Wait for redirect to dashboard
    await page.waitForURL(/\/admin\/tournament\/\d+/, { timeout: 10000 });

    // Extract tournament ID from URL and register for cleanup
    const url = page.url();
    const tournamentIdMatch = url.match(/\/admin\/tournament\/(\d+)/);
    expect(tournamentIdMatch).toBeTruthy();
    const tournamentId = parseInt(tournamentIdMatch![1], 10);

    const cookies = await page.context().cookies();
    const adminCookie = cookies.find((c) => c.name === 'admin_token');
    expect(adminCookie).toBeTruthy();
    const decodedCookie = decodeURIComponent(adminCookie!.value);
    const cookieData = JSON.parse(decodedCookie);
    const actualAdminToken = cookieData.tournaments[tournamentId].token;
    registerTournament(cleanupContext, tournamentId, actualAdminToken);

    // Step 2: Click "Import Round 2" button on dashboard
    const importButton = page.locator('#import-round-button');
    await expect(importButton).toBeVisible();
    await expect(importButton).toContainText('Import Round 2');
    await importButton.click();

    // Step 3: Verify redirect to manage page (with query parameters)
    await page.waitForURL(/\/tournament\/\d+\/round\/2/, { timeout: 10000 });
    expect(page.url()).toMatch(/\/tournament\/\d+\/round\/2\?imported=1/);

    // Step 4: Verify success message is displayed on manage page
    const successMessage = page.locator('#import-success-message');
    await expect(successMessage).toBeVisible();
    await expect(successMessage).toContainText('Round 2 imported successfully');
    await expect(successMessage).toContainText('pairings loaded from BCP');

    // Verify we're on the correct round's manage page
    await expect(page.locator('h1')).toContainText('Round 2');
  });
});
