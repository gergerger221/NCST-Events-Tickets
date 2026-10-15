import assert from 'assert';
import { INITIAL_USERS } from '../src/utils/seedData.ts';

console.log('================================================================');
console.log('NCST USER DELETION PERSISTENCE & FRONTEND STATE INTEGRITY TEST');
console.log('================================================================\n');

let passCount = 0;
async function test(name, fn) {
  try {
    await fn();
    console.log(`  [PASS] ${name}`);
    passCount++;
  } catch (err) {
    console.error(`  [FAIL] ${name}: ${err.message}`);
    process.exit(1);
  }
}

// Mock store state
let currentUser = { id: 'usr-admin-1', role: 'admin', name: 'System Administrator' };
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let deletedUserIds = new Set();

const recordDeletedUserId = (id) => {
  if (id) deletedUserIds.add(id);
};

// Mock deleteUser matching useAppStore.ts logic
const deleteUser = async (id, mockServerResponse) => {
  if (!currentUser || currentUser.role !== 'admin') {
    return { success: false, message: '403 Forbidden: Only Administrators can delete user accounts.' };
  }

  if (currentUser.id === id) {
    return { success: false, message: 'Action rejected: You cannot delete your own active administrator account.' };
  }

  // Simulate server call
  const serverRes = mockServerResponse ? mockServerResponse(id) : { ok: true, data: { success: true, mode: 'permanent', message: 'User account successfully deleted.' } };
  if (!serverRes.ok || !serverRes.data.success) {
    return { success: false, message: serverRes.data.error || serverRes.data.message || 'Server deletion failed.' };
  }

  // Record tombstone
  recordDeletedUserId(id);

  // Remove from users ONLY after server confirms success
  users = users.filter(u => u.id !== id);

  if (currentUser && currentUser.id === id) {
    currentUser = null;
  }

  return { success: true, message: serverRes.data.message, mode: serverRes.data.mode };
};

// Mock ensureSeedUsers matching useAppStore.ts logic
const ensureSeedUsers = (userList) => {
  if (!Array.isArray(userList) || userList.length === 0) return [...INITIAL_USERS];
  const merged = userList.map(u => ({ ...u }));

  INITIAL_USERS.forEach(seedUser => {
    if (deletedUserIds.has(seedUser.id)) return;
    if (seedUser.employeeId && deletedUserIds.has(seedUser.employeeId.toLowerCase())) return;
    if (seedUser.studentId && deletedUserIds.has(seedUser.studentId.toLowerCase())) return;

    const seedId = (seedUser.id || '').toLowerCase();
    const existingIndex = merged.findIndex(u => (u.id || '').toLowerCase() === seedId);
    if (existingIndex === -1) {
      merged.push({ ...seedUser });
    }
  });

  return merged;
};

// -------------------------------------------------------------
// TEST 1: Role-based access control
// -------------------------------------------------------------
await test('1. Non-admin users cannot trigger user deletion', async () => {
  const targetId = 'usr-staff-2';
  const originalLength = users.length;

  // Student attempt
  currentUser = { id: 'usr-student-01', role: 'student', name: 'Student One' };
  const res1 = await deleteUser(targetId);
  assert.strictEqual(res1.success, false);
  assert.strictEqual(users.length, originalLength, 'User must not be deleted by student');

  // Teacher attempt
  currentUser = { id: 'usr-teacher-01', role: 'teacher', name: 'Teacher One' };
  const res2 = await deleteUser(targetId);
  assert.strictEqual(res2.success, false);
  assert.strictEqual(users.length, originalLength, 'User must not be deleted by teacher');

  // Cashier attempt
  currentUser = { id: 'usr-cashier-01', role: 'cashier', name: 'Cashier One' };
  const res3 = await deleteUser(targetId);
  assert.strictEqual(res3.success, false);
  assert.strictEqual(users.length, originalLength, 'User must not be deleted by cashier');
});

// -------------------------------------------------------------
// TEST 2: Self-deletion attempt by admin is rejected
// -------------------------------------------------------------
await test('2. Administrator cannot delete their own active account', async () => {
  currentUser = { id: 'usr-admin-1', role: 'admin', name: 'System Administrator' };
  const res = await deleteUser('usr-admin-1');
  assert.strictEqual(res.success, false);
  assert.ok(res.message.includes('cannot delete your own active administrator account'));
  assert.ok(users.some(u => u.id === 'usr-admin-1'));
});

// -------------------------------------------------------------
// TEST 3: Failed backend deletion does not remove user
// -------------------------------------------------------------
await test('3. Server failure displays error and keeps user visible in state', async () => {
  currentUser = { id: 'usr-admin-1', role: 'admin', name: 'System Administrator' };
  const targetId = 'usr-staff-2';
  const originalLength = users.length;

  // Simulate server 500 failure
  const res = await deleteUser(targetId, () => ({
    ok: false,
    data: { success: false, error: 'Database transaction lock timeout.' }
  }));

  assert.strictEqual(res.success, false);
  assert.ok(res.message.includes('lock timeout'));
  assert.strictEqual(users.length, originalLength, 'User must remain visible when deletion fails');
  assert.ok(users.some(u => u.id === targetId), 'Target user must still exist in users array');
});

// -------------------------------------------------------------
// TEST 4: Successful deletion removes user and tombstones it
// -------------------------------------------------------------
await test('4. Successful deletion removes user only after backend confirmation', async () => {
  currentUser = { id: 'usr-admin-1', role: 'admin', name: 'System Administrator' };
  const targetId = 'usr-staff-2';

  const res = await deleteUser(targetId, (id) => ({
    ok: true,
    data: { success: true, mode: 'permanent', message: `User ${id} permanently deleted.` }
  }));

  assert.strictEqual(res.success, true);
  assert.strictEqual(users.some(u => u.id === targetId), false, 'Deleted user must be removed from users array');
  assert.ok(deletedUserIds.has(targetId), 'Deleted user ID must be stored in tombstone set');
});

// -------------------------------------------------------------
// TEST 5: State synchronization / ensureSeedUsers does NOT resurrect deleted user
// -------------------------------------------------------------
await test('5. State synchronization and seed hydration do NOT resurrect deleted user', async () => {
  const targetId = 'usr-staff-2';

  // Simulate incoming server state or seed hydration
  const hydrated = ensureSeedUsers(users);
  assert.strictEqual(hydrated.some(u => u.id === targetId), false, 'Deleted user must NOT be resurrected by seed data');
});

console.log('\n================================================================');
console.log(`ALL ${passCount} FRONTEND USER DELETION TESTS PASSED`);
console.log('================================================================\n');
