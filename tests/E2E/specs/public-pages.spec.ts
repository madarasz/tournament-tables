import { test, expect } from '@playwright/test';
import { seedTestData, cleanupTestData } from '../helpers/database';

const TEST_TOURNAMENT_IDS = [1001, 1002, 1003, 1004];

test.describe('Public Pages', () => {
  test.beforeAll(async () => {
    await seedTestData('public-pages');
  });

  test.afterAll(async () => {
    for (const tournamentId of TEST_TOURNAMENT_IDS) {
      await cleanupTestData(tournamentId);
    }
  });

  test('Public list page renders all tournament cards with tactical metadata', async ({ page }) => {
    await page.goto('/');
    await page.waitForLoadState('networkidle');

    await expect(page.getByTestId('tournaments-heading')).toHaveText('Tournaments');

    await expect(page.getByTestId('tournament-link-Public Page Test')).toBeVisible();
    await expect(page.getByTestId('tournament-link-Upcoming Open Test')).toBeVisible();
    await expect(page.getByTestId('tournament-link-Finished Event Test')).toBeVisible();
    await expect(page.getByTestId('tournament-link-Fallback Data Test')).toBeVisible();

    await expect(page.getByTestId('status-Public Page Test')).toHaveText(/LIVE/);
    await expect(page.getByTestId('status-Upcoming Open Test')).toHaveText(/UPCOMING/);
    await expect(page.getByTestId('status-Finished Event Test')).toHaveText(/FINISHED/);
    await expect(page.getByTestId('status-Fallback Data Test')).toHaveText(/UPCOMING/);

    await expect(page.getByTestId('event-date-Fallback Data Test')).toHaveText(/Date TBD/i);
    await expect(page.getByTestId('player-count-Fallback Data Test')).toHaveCount(0);

    const cardOrder = await page.locator('[data-testid^="tournament-link-"]').evaluateAll((elements) =>
      elements.map((element) => element.getAttribute('data-testid'))
    );
    const expectedCardOrder = [
      'tournament-link-Upcoming Open Test',
      'tournament-link-Public Page Test',
      'tournament-link-Finished Event Test',
      'tournament-link-Fallback Data Test',
    ];
    const seededCardOrder = cardOrder.filter(
      (cardId): cardId is string =>
        cardId !== null && expectedCardOrder.includes(cardId)
    );

    expect(seededCardOrder).toEqual(expectedCardOrder);
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
    await expect(page.getByTestId('leaderboard-row').first().locator('.tc-lb-rank')).toHaveText('1');
    await expect(page.getByTestId('leaderboard-row').first().locator('.tc-player-name')).toHaveText(
      'Bob Test'
    );
    await expect(page.getByTestId('leaderboard-row').nth(1).locator('.tc-lb-rank')).toHaveText('2');
    await expect(page.getByTestId('leaderboard-row').nth(1).locator('.tc-player-name')).toHaveText(
      'Alice Test'
    );

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

  test('Finished tournament defaults to leaderboard view', async ({ page }) => {
    await page.goto('/1003');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('body')).toHaveClass(/leaderboard-active/);
    await expect(page.getByTestId('leaderboard-section')).toBeVisible();
    await expect(page.getByTestId('leaderboard-row').first().locator('.tc-player-name')).toHaveText(
      'Yara Past'
    );
  });

  test('Old public round route returns 404', async ({ page }) => {
    await page.goto('/1001/round/1');
    await page.waitForLoadState('networkidle');

    await expect(page.locator('h1')).toHaveText('404 Not Found');
  });
});
