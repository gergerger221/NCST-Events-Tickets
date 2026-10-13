# Playwright End-to-End (E2E) Test Automation Guide

This document describes the Playwright end-to-end testing suite for the **NCST Events & Tickets System**, including installation, browser setup, test execution modes, covered user journeys, architectural assumptions, and reporting.

---

## 1. Overview & Architecture

The E2E test suite validates real browser interactions against the application's single-page architecture (Vue 3 + TypeScript + Tailwind CSS v4).

### Key Architectural Characteristics
- **Zero External Backend Requirement**: The application embeds an in-memory dev state and REST API mock service (`scripts/devStateSyncPlugin.ts`) directly into the Vite development server. Playwright's `webServer` configuration automatically spins up `npm run dev` at `http://localhost:5173` if it is not already running.
- **Isolated Test Data**: Tests execute against seeded institutional users, events, and rosters (`src/utils/seedData.ts`) without modifying persistent production databases.
- **Resilient Locators**: Tests prioritize accessible roles (`getByRole`), labels (`getByLabel`), placeholders, and test IDs, avoiding brittle CSS hierarchy selectors.

---

## 2. Prerequisites & Installation

### Step 1: Install Node.js Dependencies
If dependencies are not yet installed:
```bash
npm install
```

### Step 2: Install Playwright Browsers
Playwright requires browser binaries to run:
```bash
# Install all supported browser binaries (Chromium, Firefox, WebKit)
npx playwright install

# Or install Chromium only (faster)
npx playwright install chromium
```

---

## 3. Running the E2E Tests

Convenient npm scripts are integrated into `package.json`:

| Command | Description |
| :--- | :--- |
| `npm run test:e2e` | Runs all E2E test suites in headless mode across configured browsers. |
| `npm run test:e2e:headed` | Runs tests with visible browser windows (great for visual inspection). |
| `npm run test:e2e:ui` | Launches Playwright's interactive visual UI Mode with time-travel debugging. |
| `npm run test:e2e:report` | Opens the interactive HTML test report in your default browser. |

### Targeted Execution Examples
```bash
# Run tests only on Chromium
npx playwright test --project=chromium

# Run a specific test suite
npx playwright test tests/01-auth.spec.ts --project=chromium

# Run tests matching a grep filter
npx playwright test -g "mobile" --project=chromium

# Run with trace enabled
npx playwright test --trace=on
```

---

## 4. Implemented Test Suites & Scenarios

The suite consists of **8 test modules** covering **31 end-to-end user journeys**:

### Suite 01: Authentication & Session Management ([`tests/01-auth.spec.ts`](../tests/01-auth.spec.ts))
1. **Branding & Layout**: Validates institutional logo, heading, and default login tab.
2. **Input Validation**: Verifies error alerts for invalid IDs and passwords.
3. **Demo Quick-Fill**: Tests expanding the demo accounts accordion and auto-populating credentials.
4. **Student Session**: Validates student login, dashboard rendering, and navbar sign out.
5. **Admin Session**: Validates administrator login and authenticated dashboard access.

### Suite 02: Navigation, Sidebar & Layout ([`tests/02-navigation-and-layout.spec.ts`](../tests/02-navigation-and-layout.spec.ts))
1. **Navbar Branding & Clock**: Confirms NCST branding and live Philippine Standard Time (PHT) clock.
2. **Sidebar Navigation**: Navigates between Admin Dashboard, Event Manager, User Accounts, and Attendance.
3. **Desktop Rail Collapse**: Tests toggling the sidebar between full-width (256px) and collapsed icon-rail mode (72px).
4. **Theme Switcher**: Tests light/dark mode toggling and HTML class updates (`class="dark"`).

### Suite 03: Event Management Workflow ([`tests/03-event-management.spec.ts`](../tests/03-event-management.spec.ts))
1. **Event Metrics & Header**: Validates active events counter, search input, and view toggles.
2. **Search Filtering**: Verifies dynamic filtering by event title and category.
3. **View Switcher**: Switches between standard List View and interactive Calendar View.
4. **Event Creation**: Opens the "+ Create New Event" modal, fills title, venue, price, and capacity, and verifies newly created event rendering.

### Suite 04: User Management Workflow ([`tests/04-user-management.spec.ts`](../tests/04-user-management.spec.ts))
1. **Table Structure**: Validates table headers (User / ID, Role, Department / Info, Status, Actions).
2. **Role Filter Chips**: Filters between All, Students, Teachers, Staff, Cashiers, and Admins.
3. **Search Filtering**: Searches by student ID (`2026-00125`) and employee name.
4. **User Details Drawer**: Clicks a user row to open the inspection drawer and closes it.

### Suite 05: Cashier POS & Ticketing Workflow ([`tests/05-cashier-pos.spec.ts`](../tests/05-cashier-pos.spec.ts))
1. **Operator Context**: Validates Cashier POS header, active cashier identity, and step indicators.
2. **Attendee Type Switcher**: Toggles between Enrolled Student and Outsider / Guest modes.
3. **Payment Validation**: Validates ticket price calculation and quick cash payment presets (Exact, ₱500, ₱1,000).
4. **Print Queue Transition**: Navigates to the QR Print Queue and returns back to POS.

### Suite 06: Role-Based Access Control (RBAC) ([`tests/06-role-access-control.spec.ts`](../tests/06-role-access-control.spec.ts))
1. **Teacher / Faculty Portal**: Verifies teacher sees Faculty Dashboard and Assigned Attendance, with admin controls excluded.
2. **Event Staff Scanner**: Verifies event staff station, camera permission trigger, and manual lookup tabs.
3. **Student Portal**: Verifies student sees personal QR passes and events without administrative operations.

### Suite 07: Student Registration & Record Lookup ([`tests/07-student-registration.spec.ts`](../tests/07-student-registration.spec.ts))
1. **Existing Account Lookup**: Detects existing accounts and offers a one-click redirect to Sign In.
2. **Unregistered Official Record**: Resolves official unregistered student roster entries (`2026-00129`) and pre-populates read-only academic fields.
3. **Password Mismatch Validation**: Asserts validation error when password confirmation does not match.
4. **Manual Registration Fallback**: Handles unregistered IDs (`9999-77777`) by opening the manual self-registration form.
5. **Forgot Password Dialog**: Validates modal opening, input fields, and dismissal.

### Suite 08: Mobile Viewport & Responsive Drawer ([`tests/08-mobile-responsive.spec.ts`](../tests/08-mobile-responsive.spec.ts))
1. **Mobile Login**: Validates login card scaling and credential auto-fill on mobile touch viewports (390×844).
2. **Mobile Drawer Navigation**: Opens off-canvas sidebar using the hamburger button, switches views, and confirms auto-collapse.
3. **Backdrop Dismissal**: Taps outside the drawer on the semi-transparent backdrop to dismiss navigation.

---

## 5. Environment Variables & Backend Setup

- **Default Test URL**: `http://localhost:5173` (configured in `playwright.config.ts`).
- **Web Server Config**: `playwright.config.ts` automatically runs `npm run dev` and waits until port 5173 responds.
- **Port Conflict Handling**: `reuseExistingServer: !process.env.CI` allows tests to reuse a manually running `npm run dev` server during local development.
- **Database Dependency**: For end-to-end automation, no MySQL instance is required because Vite provides the dev state sync plugin. In production environments with PHP/MySQL active, the same tests can target `http://localhost/Integ 2` by setting `PLAYWRIGHT_BASE_URL`.

---

## 6. Known Limitations & Notes

1. **Hardware Camera Emulation**: In headless environments, physical webcam streaming is not present; tests verify the scanner UI structure, manual ticket lookup, and fallback keyboard inputs rather than physical camera feeds.
2. **PHT Clock Accuracy**: The live navbar clock displays Philippine Standard Time (UTC+8). Tests verify format regex rather than exact local machine time.
3. **Auto-Triggered Modals**: Certain views (such as Student Dashboard) automatically trigger event reminder modals on mount. The shared test helper [`tests/helpers/auth.ts`](../tests/helpers/auth.ts) automatically dismisses these dialogs before proceeding.
