import { expect, test  } from '@playwright/test';
import type {Cookie} from '@playwright/test';

// ---------------------------------------------------------------------------
// Test data
// ---------------------------------------------------------------------------

const CREDS = {
    email: 'nikkocausapin@gmail.com',
    password: 'password',
};

const LOGIN_TIMEOUT = 30_000;

// ---------------------------------------------------------------------------
// Auth & access control (no shared login — each test is independent)
// ---------------------------------------------------------------------------

test.describe('Phase 1 — Auth & access control', () => {
    test('guest is redirected to login when accessing /datasets', async ({
        page,
    }) => {
        await page.goto('/datasets');
        await page.waitForURL(/\/login/);
        await expect(
            page.getByRole('heading', { name: /log in/i }),
        ).toBeVisible();
    });

    test('dashboard shows Phase 1 feature cards after login', async ({
        page,
    }) => {
        await page.goto('/login');
        await page.getByLabel('Email address').fill(CREDS.email);
        await page.locator('input[name="password"]').fill(CREDS.password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard/, { timeout: LOGIN_TIMEOUT });

        await expect(page.locator('h1')).toContainText('Turn messy');

        await expect(
            page.getByText('Upload datasets', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Profile quality', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Visualize insights', { exact: true }),
        ).toBeVisible();
    });
});

// ---------------------------------------------------------------------------
// Dataset show page — core Phase 1 features (serial: single login shared)
// ---------------------------------------------------------------------------

test.describe.serial('Phase 1 — Dataset show page', () => {
    let sharedCookies: Cookie[];

    test.beforeAll(async ({ browser }) => {
        // Login once and share cookies across all tests in this group.
        const page = await browser.newPage();
        await page.goto('/login');
        await page.getByLabel('Email address').fill(CREDS.email);
        await page.locator('input[name="password"]').fill(CREDS.password);
        await page.getByRole('button', { name: 'Log in' }).click();
        await page.waitForURL(/\/dashboard/, { timeout: LOGIN_TIMEOUT });
        sharedCookies = await page.context().cookies();
        await page.close();
    });

    test.beforeEach(async ({ context }) => {
        await context.addCookies(sharedCookies);
    });

    test('summary cards: rows, columns, type, size, uploaded', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        await expect(
            page.getByText('Rows', { exact: true }).first(),
        ).toBeVisible({ timeout: 15_000 });
        await expect(
            page.getByText('Columns', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Type', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Size', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Uploaded', { exact: true }),
        ).toBeVisible();
    });

    test('workflow steps bar shows Upload → Profile → Clean → Visualize', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        await expect(
            page.getByText('Upload', { exact: true }).first(),
        ).toBeVisible({ timeout: 10_000 });
        await expect(
            page.getByText('Profile', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Clean', { exact: true }).first(),
        ).toBeVisible();
        await expect(
            page.getByText('Visualize', { exact: true }).first(),
        ).toBeVisible();
    });

    test('preview table renders with data rows', async ({ page }) => {
        await page.goto('/datasets/1');

        const table = page.locator('table').first();
        await expect(table).toBeVisible({ timeout: 15_000 });

        const rows = table.locator('tbody tr');
        await expect(rows.first()).toBeVisible({ timeout: 5_000 });
    });

    test('profiling — attribute panel lists dataset columns', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        // The "Columns" card heading
        await expect(
            page.getByText('Columns', { exact: true }).first(),
        ).toBeVisible({ timeout: 10_000 });

        // Columns from the actual Messy_Employee_dataset (underscore naming)
        for (const col of [
            'Employee_ID',
            'First_Name',
            'Last_Name',
            'Age',
            'Department_Region',
            'Status',
            'Join_Date',
            'Salary',
            'Email',
            'Phone',
            'Performance_Score',
            'Remote_Work',
            'Department',
            'Region',
        ]) {
            // Column names appear in multiple places (buttons, table, charts, etc.)
            // .first() avoids strict-mode violations.
            await expect(
                page.getByText(col, { exact: true }).first(),
            ).toBeVisible({ timeout: 3_000 });
        }
    });

    test('profiling — clicking a column reveals type & missing info', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        await page.getByText('Salary', { exact: true }).first().click();

        // The right-hand profile panel should show type/missing data
        await expect(
            page.getByText(/type/i).first(),
        ).toBeVisible({ timeout: 5_000 });
        await expect(
            page.getByText(/missing/i).first(),
        ).toBeVisible();
    });

    test('manual cleaning — Remove Duplicates, Fill Missing Values, Convert Data Type', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        await expect(
            page.getByText('Remove Duplicates', { exact: true }),
        ).toBeVisible({ timeout: 10_000 });
        await expect(
            page.getByText('Fill Missing Values', { exact: true }),
        ).toBeVisible();
        await expect(
            page.getByText('Convert Data Type', { exact: true }),
        ).toBeVisible();
    });

    test('chart panel — Visualize heading and recharts SVG', async ({
        page,
    }) => {
        await page.goto('/datasets/1');

        // CardTitle renders as <div>, not <h*>. Use the card slot selector.
        await expect(
            page.locator('[data-slot="card-title"]').filter({ hasText: 'Visualize' }),
        ).toBeVisible({ timeout: 10_000 });

        await expect(
            page.locator('.recharts-responsive-container'),
        ).toBeVisible({ timeout: 5_000 });
    });

    test('quality score card — "Before Cleaning"', async ({ page }) => {
        await page.goto('/datasets/1');

        await expect(
            page.getByText('Before Cleaning'),
        ).toBeVisible({ timeout: 10_000 });
    });

    test('undo toolbar — "Undo Last" button', async ({ page }) => {
        await page.goto('/datasets/1');

        await expect(
            page.getByRole('button', { name: 'Undo Last' }),
        ).toBeVisible({ timeout: 10_000 });
    });
});
