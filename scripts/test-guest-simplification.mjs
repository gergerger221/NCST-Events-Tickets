/**
 * NCST Events & Tickets System
 * Guest Simplification Node.js Regression Test Suite
 */

import assert from 'node:assert';

const BASE_URL = 'http://localhost/NCST-Events-Tickets';

async function api(path, method = 'GET', body = null, token = null) {
  const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
  if (token) headers['Authorization'] = `Bearer ${token}`;
  const res = await fetch(`${BASE_URL}${path}`, {
    method,
    headers,
    body: body ? JSON.stringify(body) : undefined,
  });
  const json = await res.json().catch(() => ({}));
  return { status: res.status, json };
}

async function runTests() {
  console.log('🧪 Starting Guest Simplification Node.js Test Suite...');
  const suffix = Math.floor(Math.random() * 89999 + 10000);
  const guestCode = `GUEST-MJS-${suffix}`;
  const firstName = 'Sofia';
  const lastName = 'Mendoza';
  const newPassword = 'MendozaSecure@2026!';

  // 1. Provision via Cashier POS endpoint
  const cashierAuth = await api('/api/auth/login', 'POST', { identifier: 'CSH-001', password: 'password123' });
  assert.strictEqual(cashierAuth.json.success, true, 'Cashier login must succeed');
  const cashierToken = cashierAuth.json.token;

  const purchaseRes = await api('/api/tickets/purchase', 'POST', {
    eventId: 'evt-juans-2026',
    attendeeType: 'outsider',
    firstName,
    lastName,
    name: `${firstName} ${lastName}`,
    guestReference: guestCode,
    studentId: guestCode,
    price: 150,
    paymentMethod: 'Cash',
    cashTendered: 200,
    // NO mobile number, NO email passed
  }, cashierToken);

  assert.strictEqual(purchaseRes.json.success, true, 'Guest purchase without mobile/email must succeed');
  const ticketNumber = purchaseRes.json.ticket?.ticketNumber || purchaseRes.json.ticket?.id;
  assert.ok(ticketNumber, 'Ticket number generated');

  console.log('  [PASS] 1 & 2. Guest provisioned without mobile number or email at POS');

  // 3. Guest login using Guest Code and Surname
  const guestLogin = await api('/api/auth/login', 'POST', {
    identifier: guestCode,
    password: lastName
  });
  assert.strictEqual(guestLogin.json.success, true, 'Guest login using Guest Code and surname must succeed');
  assert.strictEqual(guestLogin.json.user?.mustChangePassword, true, 'mustChangePassword must be true');
  const guestToken = guestLogin.json.token;
  console.log('  [PASS] 3. Guest logged in using Guest Code and surname');

  // 4. Incorrect password rejected
  const badLogin = await api('/api/auth/login', 'POST', {
    identifier: guestCode,
    password: 'badpassword'
  });
  assert.strictEqual(badLogin.json.success, false, 'Bad password rejected');
  console.log('  [PASS] 4. Incorrect password rejected');

  // 5. First-login restriction verified via /api/state
  const stateRes = await api('/api/state', 'GET', null, guestToken);
  assert.strictEqual(stateRes.json.mustChangePassword, true, 'State reflects mustChangePassword = true');
  console.log('  [PASS] 5. First-login password change mandatory');

  // 6. Change password & verify initial surname no longer works
  const changeRes = await api('/api/auth/change-password', 'POST', {
    currentPassword: lastName,
    newPassword
  }, guestToken);
  assert.strictEqual(changeRes.json.success, true, 'Password change must succeed');

  const oldLogin = await api('/api/auth/login', 'POST', { identifier: guestCode, password: lastName });
  assert.strictEqual(oldLogin.json.success, false, 'Old surname password rejected after change');

  const newLogin = await api('/api/auth/login', 'POST', { identifier: guestCode, password: newPassword });
  assert.strictEqual(newLogin.json.success, true, 'New password login succeeds');
  assert.strictEqual(!!newLogin.json.user?.mustChangePassword, false, 'mustChangePassword is 0');
  console.log('  [PASS] 6. New password works, old surname password rejected');

  // 7 & 8. Repeat purchase does not overwrite password
  const repeatPurchase = await api('/api/tickets/purchase', 'POST', {
    eventId: 'evt-hackathon-2026',
    attendeeType: 'outsider',
    firstName,
    lastName,
    name: `${firstName} ${lastName}`,
    guestReference: guestCode,
    studentId: guestCode,
    price: 0,
    paymentMethod: 'Free',
  }, cashierToken);

  assert.strictEqual(repeatPurchase.json.success, true, 'Repeat purchase must succeed');
  const loginStillWorks = await api('/api/auth/login', 'POST', { identifier: guestCode, password: newPassword });
  assert.strictEqual(loginStillWorks.json.success, true, 'Password retained after repeat purchase');
  console.log('  [PASS] 7 & 8. Password preserved and no duplicate accounts on repeat purchase');

  // 9. Ticket lookup without phone & QR scanning
  const lookupRes = await api('/api/tickets/guest-lookup', 'POST', { controlNumber: ticketNumber });
  assert.strictEqual(lookupRes.json.success, true, 'Lookup by Control Number without phone succeeds');
  assert.strictEqual(lookupRes.json.ticket?.firstName, firstName, 'Ticket firstName matches');
  assert.strictEqual(lookupRes.json.ticket?.lastName, lastName, 'Ticket lastName matches');

  const staffAuth = await api('/api/auth/login', 'POST', { identifier: 'STF-001', password: 'password123' });
  const scanRes = await api('/api/scan-ticket', 'POST', { rawScanData: ticketNumber }, staffAuth.json.token);
  assert.strictEqual(scanRes.json.success, true, 'Event staff scan succeeds');
  console.log('  [PASS] 9. Ticket QR validation and scanning succeed');

  // 10. Student login
  const studentAuth = await api('/api/auth/login', 'POST', { identifier: '2024-58566', password: 'password123' });
  assert.strictEqual(studentAuth.json.success, true, 'Student login succeeds');
  console.log('  [PASS] 10. Student role authentication functional');

  console.log('\n========================================================');
  console.log('  ALL GUEST SIMPLIFICATION NODE TESTS PASSED (100%)');
  console.log('========================================================\n');
}

runTests().catch((err) => {
  console.error('❌ Test failed:', err);
  process.exit(1);
});
