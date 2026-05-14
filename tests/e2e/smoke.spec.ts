import { expect, test } from '@playwright/test';

test.describe('application smoke test', () => {
  test('home page loads', async ({ page }) => {
    await page.goto('/');

    await expect(page).toHaveTitle(/.+/);
    await expect(page.locator('body')).toBeVisible();
  });
});
