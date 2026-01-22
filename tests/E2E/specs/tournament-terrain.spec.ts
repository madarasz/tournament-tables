import { test, expect } from '@playwright/test';
import {
  createCleanupContext,
  cleanupTournaments,
  createAndRegisterTournament,
} from '../helpers/cleanup';
import { setAdminTokenCookie } from '../helpers/auth';

/**
 * E2E Tests for User Story 2: Table Terrain Type Configuration
 *
 * Tests critical terrain type assignment user flow:
 * - Accessing terrain configuration section on tournament dashboard
 * - Using "Set All Tables" quick setup to bulk assign terrain types
 * - Assigning terrain types to individual tables via dropdown selectors
 * - Saving terrain configuration
 * - Persistence of terrain assignments after page reload
 *
 * Reference: specs/001-table-allocation/tasks.md T109
 * Spec: specs/001-table-allocation/spec.md - User Story 2, Acceptance Scenario 3
 * FR: FR-005 - System MUST allow organizers to optionally assign terrain types
 */

test.describe('Terrain Type Configuration (US2)', () => {
  const cleanupContext = createCleanupContext();

  test.afterEach(async ({ request, baseURL }) => {
    await cleanupTournaments(request, cleanupContext, baseURL!);
  });

  test('should assign terrain types to tables and persist selections', async ({
    page,
    request,
    baseURL,
  }) => {
    // Create a tournament with 5 tables for testing
    // Note: Tournament name is auto-imported from BCP mock as "Test Tournament {eventId}"
    const { tournamentId, adminToken } = await createAndRegisterTournament(
      request,
      cleanupContext,
      baseURL!,
      {
        tableCount: 5,
      }
    );

    // Set admin token cookie for authentication
    await setAdminTokenCookie(
      page.context(),
      adminToken,
      baseURL!,
      tournamentId,
      'Test Tournament' // Cookie display name
    );

    // Navigate to tournament dashboard
    await page.goto(`/admin/tournament/${tournamentId}`);

    // Verify we're on the dashboard (tournament name from BCP mock starts with "Test Tournament")
    await expect(page.locator('h1')).toContainText('Test Tournament');

    // Verify Table Configuration section is present
    await expect(
      page.getByRole('heading', { name: 'Table Configuration' })
    ).toBeVisible();

    // Verify explanation text is present
    await expect(page.locator('body')).toContainText(
      'Assign terrain types to tables'
    );

    // Verify all 5 tables have terrain selectors
    const terrainSelects = page.locator('select[data-table-number]');
    await expect(terrainSelects).toHaveCount(5);

    // Verify first select has terrain type options
    const firstSelect = terrainSelects.first();
    await expect(firstSelect).toBeVisible();

    // Get all options from the first select
    const options = await firstSelect.locator('option').allTextContents();

    // Should have "No terrain assigned" option plus multiple terrain types
    expect(options.length).toBeGreaterThanOrEqual(5);
    expect(options[0]).toContain('No terrain assigned');

    // Verify some expected terrain types are present in options
    expect(options.some((opt) => opt.includes('Volkus'))).toBeTruthy();
    expect(options.some((opt) => opt.includes('Tomb World'))).toBeTruthy();
    expect(options.some((opt) => opt.includes('Octarius'))).toBeTruthy();

    // --- Test "Set All Tables" Quick Setup functionality ---
    // Verify Quick Setup section is present
    await expect(page.locator('.set-all-container')).toBeVisible();
    await expect(page.locator('.set-all-label')).toContainText(
      'Quick Setup: Set All Tables'
    );

    // Use "Set All Tables" to set all tables to Octarius (ID: 4)
    const setAllDropdown = page.locator('#set-all-terrain');
    const applyAllButton = page.locator('#apply-all-button');
    await setAllDropdown.selectOption('4'); // Octarius
    await applyAllButton.click();

    // Verify feedback message appears
    await expect(page.locator('#terrain-result')).toContainText(
      'Applied terrain to 5 tables'
    );

    // Verify all tables now have Octarius selected
    for (let i = 1; i <= 5; i++) {
      await expect(
        page.locator(`select[data-table-number="${i}"]`)
      ).toHaveValue('4'); // Octarius ID
    }

    // --- Now customize individual tables (overriding the bulk setting) ---
    // Table 1 -> Volkus (ID: 1)
    // Table 2 -> Tomb World (ID: 2)
    // Table 3 -> Into the Dark (ID: 3)
    // Table 4 -> Octarius (ID: 4) - keep the "Set All" value
    // Table 5 -> Bheta-Decima (ID: 5)

    const table1Select = page.locator('select[data-table-number="1"]');
    await table1Select.selectOption('1'); // Volkus

    const table2Select = page.locator('select[data-table-number="2"]');
    await table2Select.selectOption('2'); // Tomb World

    const table3Select = page.locator('select[data-table-number="3"]');
    await table3Select.selectOption('3'); // Into the Dark

    // Table 4 keeps Octarius from "Set All"

    const table5Select = page.locator('select[data-table-number="5"]');
    await table5Select.selectOption('5'); // Bheta-Decima

    // Verify selections are set correctly before saving
    await expect(table1Select).toHaveValue('1'); // Volkus ID
    await expect(table2Select).toHaveValue('2'); // Tomb World ID
    await expect(table3Select).toHaveValue('3'); // Into the Dark ID
    await expect(page.locator('select[data-table-number="4"]')).toHaveValue(
      '4'
    ); // Octarius (from Set All)
    await expect(table5Select).toHaveValue('5'); // Bheta-Decima ID

    // Click save button
    const saveButton = page.locator('button[type="submit"]', {
      has: page.locator('text=Save Terrain Configuration'),
    });
    await expect(saveButton).toBeVisible();
    await saveButton.click();

    // Wait for success message
    await expect(
      page.locator('.alert-success', {
        hasText: 'Terrain configuration saved successfully',
      })
    ).toBeVisible({ timeout: 5000 });

    // Reload the page to verify persistence
    await page.reload();

    // Wait for page to load
    await expect(page.locator('h1')).toContainText('Test Tournament');

    // Verify terrain type selections persisted after reload
    await expect(page.locator('select[data-table-number="1"]')).toHaveValue(
      '1'
    ); // Volkus
    await expect(page.locator('select[data-table-number="2"]')).toHaveValue(
      '2'
    ); // Tomb World
    await expect(page.locator('select[data-table-number="3"]')).toHaveValue(
      '3'
    ); // Into the Dark
    await expect(page.locator('select[data-table-number="4"]')).toHaveValue(
      '4'
    ); // Octarius (from Set All)
    await expect(page.locator('select[data-table-number="5"]')).toHaveValue(
      '5'
    ); // Bheta-Decima

    // Verify we can update terrain types (change Table 1 from Volkus to Volkus+Tyranid)
    const table1SelectAfterReload = page.locator(
      'select[data-table-number="1"]'
    );
    await table1SelectAfterReload.selectOption('6'); // Volkus+Tyranid
    await expect(table1SelectAfterReload).toHaveValue('6'); // Volkus+Tyranid ID

    // Save updated configuration
    await saveButton.click();

    // Wait for success message
    await expect(
      page.locator('.alert-success', {
        hasText: 'Terrain configuration saved successfully',
      })
    ).toBeVisible({ timeout: 5000 });

    // Reload again to verify update persisted
    await page.reload();
    await expect(page.locator('h1')).toContainText('Test Tournament');

    // Verify updated terrain type persisted
    await expect(page.locator('select[data-table-number="1"]')).toHaveValue(
      '6'
    ); // Volkus+Tyranid (updated)
    await expect(page.locator('select[data-table-number="2"]')).toHaveValue(
      '2'
    ); // Tomb World (unchanged)
  });
});
