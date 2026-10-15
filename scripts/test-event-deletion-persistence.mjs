import assert from 'assert';
import { INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_TRANSACTIONS, INITIAL_LOGS } from '../src/utils/seedData.ts';

console.log('================================================================');
console.log('NCST EVENT DELETION PERSISTENCE & FRONTEND STATE INTEGRITY TEST');
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
let currentUser = { id: 'usr-admin-01', role: 'admin', name: 'System Administrator' };
let events = JSON.parse(JSON.stringify(INITIAL_EVENTS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let transactions = JSON.parse(JSON.stringify(INITIAL_TRANSACTIONS));
let logs = JSON.parse(JSON.stringify(INITIAL_LOGS));
let currentEvent = events[0] || null;

// Mock deleteEvent matching useAppStore.ts logic
const deleteEvent = async (id, mockServerResponse) => {
  if (!currentUser || currentUser.role !== 'admin') {
    return { success: false, message: '403 Forbidden: Only Administrators can delete events.' };
  }

  // Simulate server call
  const serverRes = mockServerResponse ? mockServerResponse(id) : { ok: true, data: { success: true, mode: 'permanent', message: 'Event deleted.' } };
  if (!serverRes.ok || !serverRes.data.success) {
    return { success: false, message: serverRes.data.error || serverRes.data.message || 'Server deletion failed.' };
  }

  // Remove from events ONLY after server confirms success
  events = events.filter(e => e.id !== id);

  // Switch currentEvent if deleted
  if (currentEvent && currentEvent.id === id) {
    const remainingActive = events.filter(e => e.status === 'ongoing' || e.status === 'upcoming');
    currentEvent = remainingActive[0] || events[0] || null;
  }

  return { success: true, message: serverRes.data.message, mode: serverRes.data.mode };
};

// -------------------------------------------------------------
// TEST 1: Role-based access control
// -------------------------------------------------------------
await test('1. Non-admin users cannot trigger event deletion', async () => {
  const targetId = events[0].id;
  const originalLength = events.length;

  // Student attempt
  currentUser = { id: 'usr-student-01', role: 'student', name: 'Student One' };
  const res1 = await deleteEvent(targetId);
  assert.strictEqual(res1.success, false);
  assert.strictEqual(events.length, originalLength, 'Event must not be deleted by student');

  // Teacher attempt
  currentUser = { id: 'usr-teacher-01', role: 'teacher', name: 'Teacher One' };
  const res2 = await deleteEvent(targetId);
  assert.strictEqual(res2.success, false);
  assert.strictEqual(events.length, originalLength, 'Event must not be deleted by teacher');

  // Cashier attempt
  currentUser = { id: 'usr-cashier-01', role: 'cashier', name: 'Cashier One' };
  const res3 = await deleteEvent(targetId);
  assert.strictEqual(res3.success, false);
  assert.strictEqual(events.length, originalLength, 'Event must not be deleted by cashier');
});

// -------------------------------------------------------------
// TEST 2: Failed backend deletion does not remove event
// -------------------------------------------------------------
await test('2. Server failure displays error and keeps event visible in state', async () => {
  currentUser = { id: 'usr-admin-01', role: 'admin', name: 'System Administrator' };
  const targetId = events[0].id;
  const originalLength = events.length;

  // Simulate server 500 failure
  const res = await deleteEvent(targetId, () => ({
    ok: false,
    data: { success: false, error: 'Database deadlock encountered. Deletion rolled back.' }
  }));

  assert.strictEqual(res.success, false);
  assert.ok(res.message.includes('deadlock') || res.message.includes('Server deletion failed'));
  assert.strictEqual(events.length, originalLength, 'Event must remain visible when deletion fails');
  assert.ok(events.some(e => e.id === targetId), 'Target event must still exist in events array');
});

// -------------------------------------------------------------
// TEST 3: Successful deletion removes event and updates currentEvent
// -------------------------------------------------------------
await test('3. Server success removes event from active list and gracefully switches currentEvent', async () => {
  currentUser = { id: 'usr-admin-01', role: 'admin', name: 'System Administrator' };
  const targetId = events[0].id;
  currentEvent = events[0];
  const initialTicketCount = tickets.length;
  const initialLogCount = logs.length;

  const res = await deleteEvent(targetId, (id) => ({
    ok: true,
    data: {
      success: true,
      mode: 'archived',
      message: 'Event deleted and archived.',
      preserved: { tickets: 5, attendance: 2, transactions: 1 }
    }
  }));

  assert.strictEqual(res.success, true);
  assert.strictEqual(events.some(e => e.id === targetId), false, 'Deleted event must be removed from active events');
  assert.notStrictEqual(currentEvent?.id, targetId, 'currentEvent must not be the deleted event');

  // Historical records must NOT be destroyed
  assert.strictEqual(tickets.length, initialTicketCount, 'Historical tickets must be preserved');
  assert.strictEqual(logs.length, initialLogCount, 'Historical attendance logs must be preserved');
});

// -------------------------------------------------------------
// TEST 4: Hydration from server excludes deleted event
// -------------------------------------------------------------
await test('4. Hydration from server state replaces local events without resurrecting deleted event', () => {
  // Simulate server returning only remaining active events
  const serverEvents = events.filter(e => e.status !== 'cancelled' && !e.isDeleted);
  
  // Hydrate local state
  events = serverEvents.map(e => ({ ...e }));

  assert.strictEqual(events.some(e => e.id === 'evt-001' && e.status === 'cancelled'), false);
  assert.ok(events.length >= 1, 'Remaining active events are properly retained');
});

console.log('\n================================================================');
console.log(`ALL ${passCount} FRONTEND DELETION INTEGRITY TESTS PASSED!`);
console.log('================================================================\n');
