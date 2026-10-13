import { test, expect } from '@playwright/test';
import { loginAs, DEMO_USERS } from './helpers/auth';

test.describe('Mobile Viewport & Responsive Drawer Interactions', () => {
  test.use({ viewport: { width: 390, height: 844 } }); // Mobile viewport (iPhone 12/13/14)

  test('should render responsive login card on mobile without overflow', async ({ page }) => {
    await page.goto('/');

    // Check main branding header is visible
    const brandHeading = page.getByRole('heading', { name: /NCST Events & Tickets/i });
    await expect(brandHeading).toBeVisible();

    // Check demo accounts accordion works on mobile touch viewport
    const accordionBtn = page.getByRole('button', { name: /Available Demo Accounts/i });
    await expect(accordionBtn).toBeVisible();
    await accordionBtn.click();

    // Verify demo card auto-fills credentials
    const studentCard = page.locator('div', { hasText: DEMO_USERS.student.name }).filter({ hasText: /Use/i }).last();
    await expect(studentCard).toBeVisible();
    await studentCard.click();

    const idInput = page.getByPlaceholder(/Enter your ID|2026-/i);
    await expect(idInput).toHaveValue(/2026-00125|juan\.delacruz/i);
  });

  test('should toggle mobile navigation drawer and navigate between modules', async ({ page }) => {
    // Authenticate as Admin
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);

    // Sidebar should NOT be visible on mobile before clicking hamburger
    const sidebar = page.locator('aside');
    await expect(sidebar).toBeHidden();

    // Hamburger button in header
    const hamburgerBtn = page.getByTitle('Toggle Navigation Menu (Sidebar)');
    await expect(hamburgerBtn).toBeVisible();
    await hamburgerBtn.click();

    // Drawer opens and becomes visible
    await expect(sidebar).toBeVisible();

    // Navigate to User Accounts via drawer
    const userAccountsNav = sidebar.getByRole('button', { name: /User Accounts/i });
    await expect(userAccountsNav).toBeVisible();
    await userAccountsNav.click();

    // Main workspace should update to User Accounts
    await expect(page.getByRole('heading', { name: 'User Accounts' })).toBeVisible();

    // Drawer should auto-close upon navigation on mobile
    await expect(sidebar).toBeHidden({ timeout: 5000 });
  });

  test('should close mobile drawer when clicking the backdrop', async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);

    const hamburgerBtn = page.getByTitle('Toggle Navigation Menu (Sidebar)');
    await hamburgerBtn.click();

    const sidebar = page.locator('aside');
    await expect(sidebar).toBeVisible();

    // Click outside the 288px drawer onto the backdrop (x=350, y=300 on a 390px viewport)
    await page.mouse.click(350, 300);

    // Drawer closes
    await expect(sidebar).toBeHidden();
  });
});
