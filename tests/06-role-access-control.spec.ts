import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs, logout } from './helpers/auth';

test.describe('Role-Based Access Control (RBAC) & Dedicated Dashboards', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
  });

  test('should display dedicated Faculty Portal for Teacher and enforce read-only scope', async ({ page }) => {
    await loginAs(page, DEMO_USERS.teacher.identifier, DEMO_USERS.teacher.password);

    // Verify Faculty Portal context
    await expect(page.locator('main').getByText('Faculty Portal')).toBeVisible();
    await expect(page.getByRole('heading', { name: new RegExp(DEMO_USERS.teacher.name) })).toBeVisible();
    await expect(page.getByText(/Assigned Classes:/i)).toBeVisible();

    // Verify dedicated Faculty Actions
    const openRosterBtn = page.getByRole('button', { name: /Open Students' Attendance Roster/i });
    await expect(openRosterBtn).toBeVisible();
    await openRosterBtn.click();

    // Verify attendance roster view rendered
    await expect(page.getByText(/Event.*Monitoring/i)).toBeVisible();

    // Verify Admin-only modules are NOT accessible in the sidebar
    const sidebar = page.locator('aside');
    await expect(sidebar.getByRole('button', { name: 'User Accounts' })).toHaveCount(0);

    await logout(page);
  });

  test('should display Entrance Scanner Station for Event Staff', async ({ page }) => {
    await loginAs(page, DEMO_USERS.staff.identifier, DEMO_USERS.staff.password);

    // Verify Event Staff context
    await expect(page.locator('main').getByText('Event Staff').first()).toBeVisible();

    // Verify primary optical scanner CTA
    const scanTicketBtn = page.getByRole('button', { name: /Scan Attendee Pass/i }).or(page.getByRole('button', { name: /Scan Ticket/i }).first());
    await expect(scanTicketBtn).toBeVisible();

    // Verify staff navigation items
    const sidebar = page.locator('aside');
    await sidebar.getByRole('button', { name: 'Recent Scans' }).click();
    await expect(page.getByRole('heading', { name: /Recent Entrance Scans/i })).toBeVisible();

    await sidebar.getByRole('button', { name: 'Ticket Lookup' }).click();
    await expect(page.getByRole('heading', { name: /Manual Ticket Verification/i })).toBeVisible();

    await logout(page);
  });

  test('should render student dashboard with personal tickets and exclude admin operations', async ({ page }) => {
    await loginAs(page, DEMO_USERS.student.identifier, DEMO_USERS.student.password);

    // Verify Student dashboard
    await expect(page.getByText('Official Student Account')).toBeVisible();
    await expect(page.getByRole('heading', { name: DEMO_USERS.student.name, level: 1 })).toBeVisible();
    await expect(page.getByText(/Issued Passes/i)).toBeVisible();

    // Verify student cannot see Administrative sidebar modules
    const sidebar = page.locator('aside');
    await expect(sidebar.getByRole('button', { name: 'User Accounts' })).toHaveCount(0);
    await expect(sidebar.getByRole('button', { name: 'Ticket POS' })).toHaveCount(0);

    await logout(page);
  });
});
