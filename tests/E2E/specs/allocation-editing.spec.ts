import { test, expect } from '@playwright/test';
import { seedTestData, cleanupTestData } from '../helpers/database';
import { setAdminTokenCookie } from '../helpers/auth';

/**
 * E2E Tests for Table Allocation Editing
 *
 * Tests the critical allocation editing workflow including:
 * - Editing individual table assignments via dropdown
 * - Creating table collisions by assigning same table to multiple pairings
 * - Swapping tables between pairings (no confirmation - UX #6)
 * - Creating TABLE_REUSE conflicts via swap
 * - Regenerating allocations to resolve conflicts
 *
 * Uses direct MySQL data import for deterministic test data.
 */

const TEST_TOURNAMENT_ID = 1000;
const ADMIN_TOKEN = 'testEditToken123';

test.describe('Allocation Editing', () => {
  test.beforeAll(async () => {
    await seedTestData('allocation-editing');
  });

  test.afterAll(async () => {
    await cleanupTestData(TEST_TOURNAMENT_ID);
  });

  test('Edit allocations, create conflicts, and regenerate to resolve', async ({
    page,
    baseURL,
  }) => {
    // Set up authentication via cookie
    await setAdminTokenCookie(
      page.context(),
      ADMIN_TOKEN,
      baseURL!,
      TEST_TOURNAMENT_ID,
      'Allocation Edit Test'
    );

    // Navigate directly to round 2 management page
    await page.goto(`/admin/tournament/${TEST_TOURNAMENT_ID}/round/2`);
    await page.waitForLoadState('networkidle');

    // STEP 1: Verify initial state - 8 tables, no conflicts, terrain warnings exist
    await expect(page.locator('h1')).toContainText('Round 2');

    // Verify all 8 allocations are displayed
    const allocationRows = page.locator('table.allocation-table tbody tr');
    await expect(allocationRows).toHaveCount(8);

    // Verify player factions are displayed in allocation rows
    await expect(page.locator('.player-faction').first()).toBeVisible();
    await expect(page.getByText('Blades of Khaine')).toBeVisible();
    await expect(page.getByText('Warpcoven')).toBeVisible();

    // Verify no conflict badges initially (table collisions or table reuse)
    await expect(page.locator('.round-conflict-badge')).not.toBeVisible();

    // Verify no conflict row highlighting
    await expect(page.locator('tr.conflict-table-collision')).toHaveCount(0);
    await expect(page.locator('tr.conflict-table-reuse')).toHaveCount(0);

    // Verify warnings section exists (terrain reuse)
    await expect(page.locator('.warning-list')).toBeVisible();

    // STEP 2: Edit table assignment to create TABLE COLLISION
    // Change Table 2 allocation to Table 1 (creating a collision)
    // Find the row for Table 2 and change its dropdown
    const table2Row = allocationRows.filter({ hasText: 'Table 2' }).first();
    const table2Dropdown = table2Row.locator('select.change-table-dropdown');

    // Select Table 1 (value=1001) - this creates a collision with the existing Table 1 allocation
    // The onchange handler makes a PATCH request and then reloads the page
    const [response] = await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/api/allocations/') && resp.request().method() === 'PATCH'
      ),
      table2Dropdown.selectOption('1001'),
    ]);

    // Verify the PATCH succeeded
    expect(response.ok()).toBeTruthy();

    // Wait for page to reload after the JS location.reload()
    await page.waitForLoadState('load');
    await page.waitForLoadState('networkidle');

    // Verify Table Collision badge appears
    await expect(page.locator('.round-conflict-badge')).toBeVisible({ timeout: 10000 });
    await expect(page.locator('.round-conflict-badge')).toContainText(
      'Table Collision'
    );

    // Verify two rows are highlighted with collision styling
    await expect(page.locator('tr.conflict-table-collision')).toHaveCount(2);

    // STEP 3: Fix the collision by changing back to Table 2
    const collisionRow = page
      .locator('tr.conflict-table-collision')
      .filter({ hasText: 'Edward' })
      .first();
    const collisionDropdown = collisionRow.locator(
      'select.change-table-dropdown'
    );

    // Wait for PATCH response and page reload
    await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/api/allocations/') && resp.request().method() === 'PATCH'
      ),
      collisionDropdown.selectOption('1002'), // Table 2
    ]);
    await page.waitForLoadState('load');
    await page.waitForLoadState('networkidle');

    // Verify collision is resolved
    await expect(page.locator('tr.conflict-table-collision')).toHaveCount(0);
    await expect(
      page.locator('.round-conflict-badge', { hasText: 'Table Collision' })
    ).not.toBeVisible();

    // STEP 4: Swap tables to create TABLE_REUSE conflict
    // Swap Table 4 (p13/p14) with Table 7 (p7/p8)
    // This creates TABLE_REUSE because:
    // - p13/p14 were on Table 7 in Round 1, moving them back creates reuse
    // - p7/p8 were on Table 4 in Round 1, moving them back creates reuse

    // Select first allocation for swap (Table 4 - Mike/Nancy)
    const table4Row = allocationRows.filter({ hasText: 'Table 4' }).first();
    const table4Checkbox = table4Row.locator('input.swap-checkbox');
    await table4Checkbox.check();

    // Select second allocation for swap (Table 7 - George/Hannah)
    const table7Row = allocationRows.filter({ hasText: 'Table 7' }).first();
    const table7Checkbox = table7Row.locator('input.swap-checkbox');
    await table7Checkbox.check();

    // Verify swap button is enabled
    const swapButton = page.getByRole('button', { name: 'Swap Selected' });
    await expect(swapButton).toBeEnabled();

    // Click swap - no confirmation dialog needed (UX Improvement #6: reversible action)
    // Wait for swap API response and page reload
    await Promise.all([
      page.waitForResponse(
        (resp) =>
          resp.url().includes('/api/allocations/swap') && resp.request().method() === 'POST'
      ),
      swapButton.click(),
    ]);
    await page.waitForLoadState('load');
    await page.waitForLoadState('networkidle');

    // Verify TABLE_REUSE conflict is shown
    // After swap, rows should be highlighted with table-reuse styling
    await expect(page.locator('tr.conflict-table-reuse')).toHaveCount(2);

    // Verify conflict badge shows conflicts
    await expect(
      page.locator('.round-conflict-badge', { hasText: 'Conflict' })
    ).toBeVisible();

    // STEP 5: Regenerate allocations to resolve all conflicts
    const generateButton = page.getByRole('button', {
      name: 'Generate Allocations',
    });

    // Wait for generate API response and page reload
    await Promise.all([
      page.waitForResponse(
        (resp) => resp.url().includes('/generate') && resp.request().method() === 'POST'
      ),
      generateButton.click(),
    ]);
    await page.waitForLoadState('load');
    await page.waitForLoadState('networkidle');

    // Verify all conflicts are resolved
    await expect(page.locator('tr.conflict-table-collision')).toHaveCount(0);
    await expect(page.locator('tr.conflict-table-reuse')).toHaveCount(0);
    await expect(
      page.locator('.round-conflict-badge', { hasText: 'Collision' })
    ).not.toBeVisible();
    await expect(
      page.locator('.round-conflict-badge', { hasText: 'Conflict' })
    ).not.toBeVisible();

    // Warnings may still exist (terrain reuse is acceptable)
    // The allocations table should still show 8 allocations
    await expect(allocationRows).toHaveCount(8);
  });
});
