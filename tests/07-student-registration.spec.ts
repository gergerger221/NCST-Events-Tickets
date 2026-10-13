import { test, expect } from '@playwright/test';

test.describe('Student Registration & Account Lookup Workflows', () => {
  test.beforeEach(async ({ page }) => {
    await page.goto('/');
    // Switch to Student Registration tab
    await page.getByRole('button', { name: 'Student Registration', exact: true }).click();
  });

  test('should detect existing student accounts and offer quick sign in', async ({ page }) => {
    // 2026-00125 is seeded with an existing user account
    const studentIdInput = page.getByPlaceholder('e.g. 2024-58566');
    await studentIdInput.fill('2026-00125');

    // Trigger lookup (either by clicking Search or pressing Enter)
    const searchBtn = page.getByRole('button', { name: 'Search' });
    if (await searchBtn.isVisible()) {
      await searchBtn.click();
    }

    // Expect Account Already Exists banner
    await expect(page.getByText('Account Already Exists', { exact: true })).toBeVisible({ timeout: 5000 });

    // Clicking Sign In switches back to login tab with the ID filled
    await page.getByRole('button', { name: 'Sign In', exact: true }).last().click();
    await expect(page.locator('form').getByRole('button', { name: /Sign In/i })).toBeVisible();
    await expect(page.getByPlaceholder(/Enter your ID|2026-/i)).toHaveValue('2026-00125');
  });

  test('should lookup official unregistered student record and display verified details', async ({ page }) => {
    // 2026-00129 is seeded as an unregistered student record (Christian Bautista)
    const studentIdInput = page.getByPlaceholder('e.g. 2024-58566');
    await studentIdInput.fill('2026-00129');

    const searchBtn = page.getByRole('button', { name: 'Search' });
    if (await searchBtn.isVisible()) {
      await searchBtn.click({ timeout: 1500 }).catch(() => {});
    }

    // Expect verified student record banner
    await expect(page.getByText('Official NCST Student Record Found')).toBeVisible({ timeout: 5000 });
    await expect(page.locator('input[value="Christian Bautista"]')).toBeVisible();
    await expect(page.locator('input[value="BSIT"]')).toBeVisible();

    // Verify password confirmation mismatch validation
    await page.getByPlaceholder('Enter password').fill('secret123');
    await page.getByPlaceholder('Confirm password').fill('mismatch456');

    await page.getByRole('button', { name: 'Create Student Account' }).click();
    await expect(page.getByText('Passwords do not match. Please re-enter.')).toBeVisible();
  });

  test('should offer manual registration when student ID is not found in registry', async ({ page }) => {
    // Unknown ID
    const studentIdInput = page.getByPlaceholder('e.g. 2024-58566');
    await studentIdInput.fill('9999-77777');

    const searchBtn = page.getByRole('button', { name: 'Search' });
    if (await searchBtn.isVisible()) {
      await searchBtn.click();
    }

    // Expect Not Found warning with fallback button
    await expect(page.getByText('Student Record Not Found')).toBeVisible({ timeout: 5000 });
    const manualBtn = page.getByRole('button', { name: 'Continue with Manual Student Registration' });
    await expect(manualBtn).toBeVisible();

    // Click manual registration
    await manualBtn.click();

    // Check manual registration form appears
    await expect(page.getByText('Manual Registration — Pending Verification')).toBeVisible();
    await expect(page.getByPlaceholder('e.g. Juan Dela Cruz')).toBeVisible();
    await expect(page.getByRole('button', { name: /Create Student Account \(Pending Verification\)/i })).toBeVisible();
  });

  test('should open and validate Forgot Password dialog', async ({ page }) => {
    // Switch to login tab
    await page.getByRole('button', { name: 'Sign In', exact: true }).first().click();

    // Click Forgot password? link
    await page.getByRole('button', { name: 'Forgot password?' }).click();

    // Modal should be visible
    await expect(page.getByText('Reset Your Password')).toBeVisible();
    await expect(page.getByRole('button', { name: 'Verify & Reset' })).toBeVisible();

    // Test Cancel closes modal
    await page.getByRole('button', { name: 'Cancel' }).click();
    await expect(page.getByText('Reset Your Password')).not.toBeVisible();
  });
});
