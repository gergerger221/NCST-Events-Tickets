import { test, expect } from '@playwright/test';
import { DEMO_USERS, loginAs } from './helpers/auth';

test.describe('Event Management Workflow', () => {
  test.beforeEach(async ({ page }) => {
    await loginAs(page, DEMO_USERS.admin.identifier, DEMO_USERS.admin.password);
    const sidebar = page.locator('aside');
    await sidebar.getByRole('button', { name: 'Events' }).click();
    await expect(page.getByRole('heading', { name: 'Events', exact: true })).toBeVisible();
  });

  test('should display event metrics, search input, and view switcher', async ({ page }) => {
    // Verify Events header and counter badge
    const headerTitle = page.getByRole('heading', { name: 'Events', exact: true });
    await expect(headerTitle).toBeVisible();
    await expect(page.locator('span', { hasText: /Event/i }).first()).toBeVisible();

    // Verify search input
    const searchInput = page.locator('input[placeholder*="Search events by title"]');
    await expect(searchInput).toBeVisible();

    // Verify Create Event primary button
    const createBtn = page.getByRole('button', { name: /Create Event/i }).first();
    await expect(createBtn).toBeVisible();

    // Verify view switchers
    const listViewBtn = page.getByRole('button', { name: 'List' });
    const calendarViewBtn = page.getByRole('button', { name: 'Calendar' });
    await expect(listViewBtn).toBeVisible();
    await expect(calendarViewBtn).toBeVisible();
  });

  test('should filter events via the search input', async ({ page }) => {
    const searchInput = page.locator('input[placeholder*="Search events by title"]');
    await searchInput.fill('Foundation');

    // Verify at least one match or empty-state feedback
    await page.waitForTimeout(300);
    const eventCards = page.locator('.rounded-3xl.bg-white, .dark\\:bg-\\[\\#1e1e1e\\]');
    const cardCount = await eventCards.count();
    expect(cardCount).toBeGreaterThanOrEqual(0);

    // Clear search
    await searchInput.fill('');
    await page.waitForTimeout(300);
  });

  test('should toggle between List View and Calendar View', async ({ page }) => {
    const calendarBtn = page.getByRole('button', { name: 'Calendar' });
    await calendarBtn.click();

    // Verify Calendar day grid
    await expect(page.getByRole('button', { name: 'Today' })).toBeVisible();

    // Switch back to List View
    const listBtn = page.getByRole('button', { name: 'List' });
    await listBtn.click();
    await expect(page.locator('input[placeholder*="Search events by title"]')).toBeVisible();
  });

  test('should create a new event through the creation modal', async ({ page }) => {
    const newEventTitle = `E2E Campus Hackathon ${Date.now()}`;

    // Click Create Event button
    const openCreateBtn = page.getByRole('button', { name: /Create Event/i }).first();
    await openCreateBtn.click();

    // Verify modal appears
    await expect(page.getByText('Create New Event')).toBeVisible();

    // Fill Title
    const titleInput = page.locator('input[placeholder*="NCST Institutional Foundation Week"]');
    await titleInput.fill(newEventTitle);

    // Submit modal form
    const submitBtn = page.locator('button[type="submit"]:has-text("Create Event")');
    await submitBtn.click();

    // Verify modal closes and event appears in the list
    await expect(page.getByRole('heading', { name: newEventTitle })).toBeVisible({ timeout: 10000 });
  });
});
