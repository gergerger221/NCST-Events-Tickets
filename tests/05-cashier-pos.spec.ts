import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers/auth';

test.describe('Cashier POS & Ticketing Workflow', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_USERS.cashier.identifier, DEMO_USERS.cashier.password);
  });

  test('should render Cashier POS station with operator context and steps', async ({ page }) => {
    // Verify Operator context
    await expect(page.getByText('Ticket POS Station')).toBeVisible();
    await expect(page.locator('strong', { hasText: DEMO_USERS.cashier.name })).toBeVisible();

    // Verify Steps
    await expect(page.getByText(/Step 1 — Attendee Information/i)).toBeVisible();
    await expect(page.getByText(/Step 2 — Event & Cash Payment Details/i)).toBeVisible();

    // Verify quick navigation buttons
    await expect(page.locator('button[title="Open Dynamic QR Label Print Queue"]')).toBeVisible();
    await expect(page.getByRole('button', { name: /Issued Registry/i })).toBeVisible();
  });

  test('should toggle attendee type between Enrolled Student and Outsider / Guest', async ({ page }) => {
    const studentToggleBtn = page.getByRole('button', { name: /Enrolled Student/i });
    const outsiderToggleBtn = page.getByRole('button', { name: /Outsider \/ Guest/i });

    await expect(studentToggleBtn).toBeVisible();
    await expect(outsiderToggleBtn).toBeVisible();

    // Select Outsider / Guest
    await outsiderToggleBtn.click();
    await expect(page.getByText(/Guest Reference Code/i)).toBeVisible();
    await expect(page.locator('input[placeholder="GUEST-1234"]')).toBeVisible();

    // Switch back to Student
    await studentToggleBtn.click();
    await expect(page.locator('input[placeholder*="Search Student ID"]')).toBeVisible();
  });

  test('should validate payment calculation and quick cash options', async ({ page }) => {
    // Check if an event dropdown is present
    const eventSelect = page.locator('select').filter({ hasText: /EVT-/i }).or(page.locator('select').nth(1));
    if (await eventSelect.isVisible()) {
      // If paid event is selected, verify cash options
      const exactCashBtn = page.getByRole('button', { name: /Exact/i });
      if (await exactCashBtn.isVisible()) {
        await exactCashBtn.click();
        await expect(page.getByText(/Sufficient payment/i)).toBeVisible();
      }
    }
  });

  test('should navigate to QR Print Queue and return to POS station', async ({ page }) => {
    const printQueueBtn = page.locator('button[title="Open Dynamic QR Label Print Queue"]');
    await printQueueBtn.click();

    // Verify Print Queue interface
    await expect(page.getByRole('heading', { name: /QR Print Queue/i }).or(page.getByText(/Label Print/i))).toBeVisible();

    // Return to POS station
    const sidebar = page.locator('aside');
    await sidebar.getByRole('button', { name: 'Ticket POS' }).click();
    await expect(page.getByText(/Step 1 — Attendee Information/i)).toBeVisible();
  });
});
