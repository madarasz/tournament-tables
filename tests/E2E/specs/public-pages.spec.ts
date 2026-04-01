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

  test('Navigate public pages with query-based round and leaderboard views', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('tournaments-heading')).toHaveText('Tournaments');

    const tournamentLink = page.getByTestId('tournament-link-Public Page Test');
    await expect(tournamentLink).toBeVisible();
    await expect(page.getByTestId('player-count-Public Page Test')).toBeVisible();

    await tournamentLink.click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/1001$/);

    await expect(page.locator('.tc-tournament-name')).toContainText('Public Page Test');
    await expect(page.locator('#hero-round-title')).toContainText('Round 1');
    await expect(page.locator('body')).not.toHaveClass(/leaderboard-active/);

    await expect(page.locator('.tc-match-row')).toHaveCount(8);
    await expect(page.locator('.tc-match-list').getByText('Corsair Voidscarred')).toBeVisible();
    await expect(page.locator('.tc-match-list').getByText('Nemesis Claw')).toBeVisible();
    await expect(page.getByTestId('sidebar-round-link-1')).toBeVisible();
    await expect(page.getByTestId('sidebar-round-link-2')).toHaveCount(0);

    await page.locator('#sidebar-leaderboard-link').click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/1001\?view=leaderboard$/);
    await expect(page.locator('body')).toHaveClass(/leaderboard-active/);
    await expect(page.getByTestId('leaderboard-section')).toBeVisible();

    await page.goto('/1001?round=1&view=leaderboard');
    await page.waitForLoadState('networkidle');
    await expect(page.locator('body')).toHaveClass(/leaderboard-active/);
    await expect(page.getByTestId('leaderboard-section')).toBeVisible();

    await page.getByTestId('sidebar-round-link-1').click();
    await page.waitForLoadState('networkidle');
    await expect(page).toHaveURL(/\/1001\?round=1$/);
    await expect(page.locator('body')).not.toHaveClass(/leaderboard-active/);

    await page.getByTestId('back-to-list').click();
    await page.waitForLoadState('networkidle');
    await expect(page.getByTestId('tournaments-heading')).toHaveText('Tournaments');
  });

  test('Old public round route returns 404', async ({ page }) => {
    await page.goto('/1001/round/1');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toHaveText('404 Not Found');
  });
});
