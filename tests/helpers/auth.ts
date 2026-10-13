import { Page, expect } from '@playwright/test';

/**
 * Standard test user credentials corresponding to seedData.ts.
 */
export const DEMO_USERS = {
  admin: {
    name: 'Engr. Mark Santos',
    identifier: 'EMP-00101',
    password: 'password123',
    role: 'admin',
  },
  cashier: {
    name: 'Carla Gomez',
    identifier: 'EMP-00102',
    password: 'password123',
    role: 'cashier',
  },
  eventStaff: {
    name: 'Alex Rivera',
    identifier: 'EMP-00103',
    password: 'password123',
    role: 'event_staff',
  },
  staff: {
    name: 'Alex Rivera',
    identifier: 'EMP-00103',
    password: 'password123',
    role: 'event_staff',
  },
  teacher: {
    name: 'Prof. Ramon Reyes',
    identifier: 'EMP-00125',
    password: 'password123',
    role: 'teacher',
  },
  student: {
    name: 'Juan Dela Cruz',
    identifier: '2026-00125',
    password: 'password123',
    role: 'student',
  },
};

/**
 * Dismiss any top-level dialogs that auto-open upon logging in (such as UpcomingEventReminderModal).
 */
export async function dismissModalsIfAny(page: Page): Promise<void> {
  try {
    const closeBtn = page.locator('div.fixed.inset-0 button').filter({ hasText: /Close|Dismiss/i }).first();
    if (await closeBtn.isVisible({ timeout: 2500 }).catch(() => false)) {
      await closeBtn.click({ force: true }).catch(() => {});
      await page.waitForTimeout(300);
    }
  } catch {
    // No modal present, proceed
  }
}

/**
 * Perform a clean login via the UI and wait for the authenticated navbar.
 */
export async function loginAs(page: Page, identifier: string, password = 'password123'): Promise<void> {
  await page.goto('/');

  // If already authenticated with a navbar, verify or logout
  const navbar = page.locator('header');
  if (await navbar.isVisible()) {
    await dismissModalsIfAny(page);
    return;
  }

  // Ensure login tab is active
  const signInTab = page.getByRole('button', { name: 'Sign In', exact: true }).first();
  if (await signInTab.isVisible()) {
    await signInTab.click();
  }

  const idInput = page.getByPlaceholder(/Enter your ID|2026-/i);
  await idInput.waitFor({ state: 'visible' });
  await idInput.fill(identifier);

  const passInput = page.locator('input[type="password"]');
  await passInput.fill(password);

  const submitBtn = page.locator('form').getByRole('button', { name: /Sign In/i });
  await submitBtn.click();

  // Wait for the authenticated header to render
  await expect(page.locator('header')).toBeVisible({ timeout: 10000 });

  // Dismiss any auto-opened modals (e.g. upcoming event reminder for student)
  await dismissModalsIfAny(page);
}

/**
 * Log out the currently authenticated user through the User Profile modal.
 */
export async function logout(page: Page): Promise<void> {
  await dismissModalsIfAny(page);

  // Click on the user profile chip in the top navbar (contains user's name or avatar)
  const profileButton = page.locator('header button').filter({ hasText: /Santos|Gomez|Rivera|Reyes|Cruz/i }).first();

  try {
    await profileButton.click({ timeout: 3000 });
  } catch {
    // If blocked by an overlay, click any modal close button and retry
    const modalClose = page.locator('div.fixed.inset-0 button').filter({ hasText: /Close|Dismiss/i }).first();
    if (await modalClose.isVisible({ timeout: 1000 }).catch(() => false)) {
      await modalClose.click({ force: true }).catch(() => {});
      await page.waitForTimeout(300);
    }
    await profileButton.click({ force: true });
  }

  // In User Profile Modal, click "Sign Out"
  const signOutBtn = page.getByRole('button', { name: /Sign Out|Logout/i });
  await signOutBtn.waitFor({ state: 'visible' });
  await signOutBtn.click();

  // Wait for the login screen to return
  await expect(page.getByRole('button', { name: 'Sign In', exact: true }).first()).toBeVisible({ timeout: 8000 });
}
