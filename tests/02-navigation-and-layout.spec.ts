import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers/auth';

test.describe('Navigation, Sidebar & Layout', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);
  });

  test('should render navbar with branding, live clock, and user profile chip', async ({ page }) => {
    const navbar = page.locator('header');
    await expect(navbar).toBeVisible();

    // Verify institutional logo and title in navbar
    await expect(navbar.locator('img[alt="NCST Seal"]')).toBeVisible();
    await expect(navbar.getByText(/NCST/i).first()).toBeVisible();

    // Verify live clock indicator with PHT badge
    await expect(navbar.getByText('PHT')).toBeVisible();

    // Verify admin profile chip
    const profileChip = navbar.locator('button[title="View Account Profile"]');
    await expect(profileChip).toBeVisible();
    await expect(profileChip).toContainText(DEMO_USERS.admin.name);
  });

  test('should navigate across admin modules using the sidebar', async ({ page }) => {
    const sidebar = page.locator('aside');
    await expect(sidebar).toBeVisible();

    // 1. Navigate to Events
    await sidebar.getByRole('button', { name: 'Events' }).click();
    await expect(page.getByRole('heading', { name: 'Events', exact: true })).toBeVisible();

    // 2. Navigate to Attendance
    await sidebar.getByRole('button', { name: 'All Attendance' }).click();
    await expect(page.getByRole('button', { name: /Active Attendance/i })).toBeVisible();

    // 3. Navigate to Ticket Registry
    await sidebar.getByRole('button', { name: 'Ticket Registry' }).click();
    await expect(page.getByRole('heading', { name: /Issued Tickets Registry/i })).toBeVisible();

    // 4. Navigate to User Accounts
    await sidebar.getByRole('button', { name: 'User Accounts' }).click();
    await expect(page.getByRole('heading', { name: 'User Accounts' })).toBeVisible();

    // 5. Navigate to Reports & Excel Export
    await sidebar.getByRole('button', { name: 'Reports & Excel Export' }).click();
    await expect(page.getByRole('heading', { name: /Reports Center/i })).toBeVisible();

    // 6. Return to Admin Dashboard
    await sidebar.getByRole('button', { name: 'Admin Dashboard' }).click();
    await expect(page.getByRole('button', { name: 'Admin Dashboard' })).toHaveClass(/bg-\[#1E3A5F\]|bg-blue-600/);
  });

  test('should toggle desktop sidebar collapse to rail mode and back', async ({ page }) => {
    const sidebar = page.locator('aside');

    // Click collapse button
    const collapseBtn = sidebar.locator('button[title*="Collapse"]');
    await expect(collapseBtn).toBeVisible();
    await collapseBtn.click();

    // Verify rail mode
    const expandBtn = sidebar.locator('button[title*="Expand"]');
    await expect(expandBtn).toBeVisible();

    // Expand sidebar back
    await expandBtn.click();
    await expect(sidebar.getByText('Administrator Portal')).toBeVisible();
  });

  test('should toggle between dark and light themes', async ({ page }) => {
    const themeBtn = page.locator('header').locator('button[aria-label*="Mode"], button[title*="Mode"]').first();
    await expect(themeBtn).toBeVisible();

    const initialIsDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));

    // Toggle theme
    await themeBtn.click();

    const afterToggleIsDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(afterToggleIsDark).toBe(!initialIsDark);

    // Toggle back
    await themeBtn.click();
    const finalIsDark = await page.evaluate(() => document.documentElement.classList.contains('dark'));
    expect(finalIsDark).toBe(initialIsDark);
  });
});
