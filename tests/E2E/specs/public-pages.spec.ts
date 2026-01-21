import { test, expect } from '@playwright/test';
import { seedTestData, cleanupTestData } from '../helpers/database';

const TEST_TOURNAMENT_ID = 1001;

test.describe('Public Pages', () => {
  test.beforeAll(async () => {
    await seedTestData('public-pages');
  });

  test.afterAll(async () => {
    await cleanupTestData(TEST_TOURNAMENT_ID);
  });

  test('Navigate public pages: list -> tournament -> round -> back', async ({ page }) => {
    // Step 1: Visit main public page
    await page.goto('/public');
    await page.waitForLoadState('networkidle');

    // Verify tournaments list is visible
    await expect(page.getByTestId('tournaments-heading')).toHaveText('Tournaments');

    // Verify our test tournament is listed with player count
    const tournamentLink = page.getByTestId('tournament-link-Public Page Test');
    await expect(tournamentLink).toBeVisible();
    await expect(page.getByTestId('player-count-Public Page Test')).toBeVisible();

    // Step 2: Click tournament to go to tournament page
    await tournamentLink.click();
    await page.waitForLoadState('networkidle');

    // Verify we're on tournament page
    await expect(page.locator('h1')).toContainText('Public Page Test');

    // Verify table count is displayed correctly
    await expect(page.getByTestId('tables-count')).toContainText('8 Tables');

    // Verify "All Tournaments" back link exists
    const backToList = page.getByTestId('back-to-list');
    await expect(backToList).toBeVisible();

    // Verify Round 1 button is visible (published)
    const round1Button = page.getByTestId('round-button-1');
    await expect(round1Button).toBeVisible();

    // Verify Round 2 is NOT visible (not published)
    const round2Button = page.getByTestId('round-button-2');
    await expect(round2Button).not.toBeVisible();

    // Step 3: Click Round 1 to view allocations
    await round1Button.click();
    await page.waitForLoadState('networkidle');

    // Verify we're on round page
    await expect(page.locator('h1')).toContainText('Public Page Test');
    await expect(page.locator('.public-round-current')).toContainText('Round 1');

    // Verify allocations table is visible with 8 rows
    const allocationRows = page.locator('table tbody tr');
    await expect(allocationRows).toHaveCount(8);

    // Verify "All Tournaments" back link exists on round page
    const backFromRound = page.getByTestId('back-to-list');
    await expect(backFromRound).toBeVisible();

    // Step 4: Navigate back to tournaments list
    await backFromRound.click();
    await page.waitForLoadState('networkidle');

    // Verify we're back on the main public list
    await expect(page.getByTestId('tournaments-heading')).toHaveText('Tournaments');
  });
});
