import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs, logout } from './helpers/auth';

test.describe('Authentication & Session Management', () => {
  test.beforeEach(async ({ page }) => {
    // Clear localStorage to ensure fresh unauthenticated state
    await page.goto('/');
    await page.evaluate(() => localStorage.clear());
    await page.reload();
  });

  test('should render the login portal with institutional branding and tabs', async ({ page }) => {
    await expect(page).toHaveTitle(/NCST/i);

    // Verify institutional logo and school title
    const logo = page.locator('img[alt="NCST Seal"]');
    await expect(logo).toBeVisible();

    const titleHeading = page.getByRole('heading', { name: /NCST Events & Tickets/i });
    await expect(titleHeading).toBeVisible();

    await expect(page.getByText('National College of Science & Technology')).toBeVisible();

    // Verify tabs
    const signInTab = page.getByRole('button', { name: 'Sign In', exact: true }).first();
    const regTab = page.getByRole('button', { name: 'Student Registration', exact: true });
    await expect(signInTab).toBeVisible();
    await expect(regTab).toBeVisible();

    // Verify login form inputs
    const identifierInput = page.getByPlaceholder(/Enter your ID|2026-/i);
    const passwordInput = page.locator('input[type="password"]');
    const submitBtn = page.locator('form').getByRole('button', { name: /Sign In/i });

    await expect(identifierInput).toBeVisible();
    await expect(passwordInput).toBeVisible();
    await expect(submitBtn).toBeVisible();
  });

  test('should display validation feedback on invalid credentials', async ({ page }) => {
    const identifierInput = page.getByPlaceholder(/Enter your ID|2026-/i);
    const passwordInput = page.locator('input[type="password"]');
    const submitBtn = page.locator('form').getByRole('button', { name: /Sign In/i });

    await identifierInput.fill('INVALID-ID-9999');
    await passwordInput.fill('incorrectpassword');
    await submitBtn.click();

    // Expect an error message alert container
    const alertMessage = page.locator('.bg-rose-50, .dark\\:bg-rose-950\\/70, [class*="text-rose"]');
    await expect(alertMessage.first()).toBeVisible({ timeout: 8000 });
    await expect(alertMessage.first()).toContainText(/(invalid|no account found)/i);
  });

  test('should expand demo accounts accordion and auto-fill credentials', async ({ page }) => {
    const demoAccordionBtn = page.getByRole('button', { name: /Available Demo Accounts/i });
    await expect(demoAccordionBtn).toBeVisible();
    await demoAccordionBtn.click();

    // Verify demo cards appear
    await expect(page.getByText(DEMO_USERS.admin.name)).toBeVisible();
    await expect(page.getByText(DEMO_USERS.student.name)).toBeVisible();

    // Click on Admin demo account to auto-fill
    const adminCard = page.locator('div', { hasText: DEMO_USERS.admin.name }).filter({ hasText: /Use/i }).last();
    await adminCard.click();

    const identifierInput = page.getByPlaceholder(/Enter your ID|2026-/i);
    await expect(identifierInput).toHaveValue(DEMO_USERS.admin.identifier);
  });

  test('should successfully sign in as Student and then log out', async ({ page }) => {
    await loginAs(page, DEMO_USERS.student.identifier, DEMO_USERS.student.password);

    // Verify Student header displays
    await expect(page.locator('header')).toBeVisible();
    await expect(page.getByText(/Official Student Account/i)).toBeVisible();
    await expect(page.getByRole('heading', { name: DEMO_USERS.student.name, level: 1 })).toBeVisible();

    // Logout
    await logout(page);
  });

  test('should successfully sign in as Administrator', async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);

    // Verify Admin session elements
    await expect(page.locator('header')).toBeVisible();
    await expect(page.getByTitle('View Account Profile')).toContainText(DEMO_USERS.admin.name);
    // Verify admin navigation sidebar items
    await expect(page.getByRole('button', { name: /Dashboard/i }).first()).toBeVisible();
    await expect(page.getByRole('button', { name: /Events/i }).first()).toBeVisible();
  });
});
