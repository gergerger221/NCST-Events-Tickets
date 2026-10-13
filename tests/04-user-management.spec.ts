import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers/auth';

test.describe('User Management Workflow', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);
    const sidebar = page.locator('aside');
    await sidebar.getByRole('button', { name: 'User Accounts' }).click();
    await expect(page.getByRole('heading', { name: 'User Accounts' })).toBeVisible();
  });

  test('should render user accounts table with standard headers and search bar', async ({ page }) => {
    // Check main title and subtitle
    await expect(page.getByRole('heading', { name: 'User Accounts' })).toBeVisible();
    await expect(page.getByRole('button', { name: /Add Account/i })).toBeVisible();

    // Check search input
    const searchInput = page.locator('input[placeholder*="Search by name, email, ID"]');
    await expect(searchInput).toBeVisible();

    // Verify table headers
    const table = page.locator('table');
    await expect(table).toBeVisible();
    await expect(table.getByText('Name', { exact: true })).toBeVisible();
    await expect(table.getByText('Role', { exact: true })).toBeVisible();
    await expect(table.getByText('ID / Email', { exact: true })).toBeVisible();
    await expect(table.getByText('Status', { exact: true })).toBeVisible();
  });

  test('should filter user table using role chips', async ({ page }) => {
    const roleChips = page.locator('div.overflow-x-auto button');

    // 1. Filter by Admin
    await roleChips.filter({ hasText: 'Admin' }).click();
    await expect(page.locator('table').getByText(DEMO_USERS.admin.name)).toBeVisible();

    // 2. Filter by Teacher
    await roleChips.filter({ hasText: 'Teacher' }).click();
    await expect(page.locator('table').getByText(DEMO_USERS.teacher.name)).toBeVisible();

    // 3. Filter by Cashier
    await roleChips.filter({ hasText: 'Cashier' }).click();
    await expect(page.locator('table').getByText(DEMO_USERS.cashier.name)).toBeVisible();

    // 4. Reset filter by clicking All
    await roleChips.filter({ hasText: 'All' }).click();
    await expect(page.locator('table').getByText(DEMO_USERS.admin.name)).toBeVisible();
  });

  test('should search users by name or ID query', async ({ page }) => {
    const searchInput = page.locator('input[placeholder*="Search by name, email, ID"]');
    await searchInput.fill('Carla Gomez');
    await page.waitForTimeout(300);

    const table = page.locator('table');
    await expect(table.getByText('Carla Gomez')).toBeVisible();

    // Search by ID
    await searchInput.fill('EMP-00101');
    await page.waitForTimeout(300);
    await expect(table.getByText(DEMO_USERS.admin.name)).toBeVisible();
  });

  test('should open User Details modal on row click', async ({ page }) => {
    const userRow = page.locator('tbody tr').filter({ hasText: DEMO_USERS.admin.name }).first();
    await userRow.click();

    // Verify modal drawer
    await expect(page.getByText('User Details')).toBeVisible();
    await expect(page.locator('div[role="dialog"], .bg-white, .dark\\:bg-\\[\\#242424\\]').getByText(DEMO_USERS.admin.name).first()).toBeVisible();

    // Close modal
    const closeBtn = page.getByRole('button', { name: 'Close' }).or(page.locator('button[title="Close"]')).first();
    await closeBtn.click();
  });
});
