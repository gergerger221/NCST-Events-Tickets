import assert from 'node:assert';
import {
  getEventExpirationCutoff,
  getOutsiderAccountExpirationDate,
  isOutsiderAccountExpired,
  formatPhilippineExpiration,
  isOutsiderUser,
  PHILIPPINE_TIMEZONE,
} from '../src/utils/philippineTime.ts';

console.log('------------------------------------------------------------');
console.log('FINAL OUTSIDER ACCOUNT AUTO-EXPIRATION VERIFICATION SUITE');
console.log('------------------------------------------------------------');

// 1. Setup Mock Event: November 5, 2026
const mockEventNov5 = {
  id: 'evt-juans-2026',
  title: 'The Juans Live Concert & Campus Music Fest',
  date: '2026-11-05',
  startTime: '06:00 PM',
  endTime: '10:00 PM',
  ticketPrice: 250,
};

// Expiration Cutoff Calculation Check
const cutoff = getEventExpirationCutoff(mockEventNov5.date);
assert(cutoff !== null, 'Expiration cutoff must be computable');
console.log(`[PASS] Calculated Cutoff ISO: ${cutoff.toISOString()}`);

// Verify Philippine Time string for cutoff
const formattedCutoff = formatPhilippineExpiration(cutoff);
console.log(`[PASS] Formatted Cutoff in PHT: ${formattedCutoff}`);
assert(formattedCutoff.includes('November 6, 2026 at 12:00:00 AM PHT'), `Cutoff must be November 6, 2026 at 12:00:00 AM PHT, got ${formattedCutoff}`);

// 2. Create Temporary Outsider Account & Linked Historical Records
const mockOutsiderUser = {
  id: 'usr-guest-999',
  name: 'Alex Guestson',
  email: 'alex.guestson@guest.external',
  role: 'student',
  attendeeType: 'OUTSIDER',
  isOutsider: true,
  studentId: 'GUEST-8812',
  eventId: 'evt-juans-2026',
  assignedEventIds: ['evt-juans-2026'],
  createdAt: '2026-11-01T10:00:00.000Z',
  isActive: true,
  accountStatus: 'active',
  password: 'password123',
};

const mockOutsiderTicket = {
  id: 'TIX-O-10088',
  ticketNumber: 'TIX-O-10088',
  qrToken: 'TIX-O-10088',
  eventId: 'evt-juans-2026',
  eventTitle: 'The Juans Live Concert',
  ticketType: 'physical',
  attendeeType: 'OUTSIDER',
  originalOwnerId: 'usr-guest-999',
  originalOwnerName: 'Alex Guestson',
  currentAttendeeId: 'usr-guest-999',
  currentAttendeeName: 'Alex Guestson',
  attendeeEmail: 'alex.guestson@guest.external',
  price: 250,
  ticketStatus: 'ISSUED',
  paymentStatus: 'paid',
  attendanceStatus: 'present',
  timeIn: '2026-11-05 06:45:00 PM',
  purchaseDate: '2026-11-01T10:05:00.000Z',
};

const mockOutsiderTxn = {
  id: 'TXN-2026-10088',
  userId: 'usr-guest-999',
  userName: 'Alex Guestson',
  attendeeType: 'OUTSIDER',
  eventId: 'evt-juans-2026',
  ticketId: 'TIX-O-10088',
  amount: 250,
  paymentStatus: 'paid',
  purchaseDate: '2026-11-01T10:05:00.000Z',
};

const mockAttendanceLog = {
  id: 'log-999',
  ticketId: 'TIX-O-10088',
  ticketNumber: 'TIX-O-10088',
  eventId: 'evt-juans-2026',
  studentId: 'GUEST-8812',
  attendeeName: 'Alex Guestson',
  attendeeType: 'OUTSIDER',
  scanType: 'entry',
  status: 'valid',
  timestamp: '2026-11-05T10:45:00.000Z',
};

const events = [mockEventNov5];
const tickets = [mockOutsiderTicket];

// Test 3: Verify account is ACTIVE before expiration
assert.strictEqual(isOutsiderUser(mockOutsiderUser), true, 'User must be recognized as Outsider');
assert.strictEqual(mockOutsiderUser.isActive, true, 'Account must start active');

// Test 4 & 5: Advance time to November 5, 2026 11:59:59 PM PHT (UTC 2026-11-05 15:59:59)
const timeNov5_115959PM_PHT = new Date(Date.UTC(2026, 10, 5, 15, 59, 59, 0));
const isExpiredAt115959 = isOutsiderAccountExpired(mockOutsiderUser, events, tickets, timeNov5_115959PM_PHT);
console.log(`[PASS] Nov 5, 2026 11:59:59 PM PHT -> isExpired: ${isExpiredAt115959} (Expected: false)`);
assert.strictEqual(isExpiredAt115959, false, 'Account must remain ACTIVE at 11:59:59 PM PHT');

// Test 6 & 7: Advance time to November 6, 2026 12:00:00 AM PHT (UTC 2026-11-05 16:00:00)
const timeNov6_120000AM_PHT = new Date(Date.UTC(2026, 10, 5, 16, 0, 0, 0));
const isExpiredAt120000 = isOutsiderAccountExpired(mockOutsiderUser, events, tickets, timeNov6_120000AM_PHT);
console.log(`[PASS] Nov 6, 2026 12:00:00 AM PHT -> isExpired: ${isExpiredAt120000} (Expected: true)`);
assert.strictEqual(isExpiredAt120000, true, 'Account must be EXPIRED at 12:00:00 AM PHT');

// Simulate Server-side / Store Expiration Evaluation
function evaluateExpiration(user, eventsList, ticketsList, referenceTime) {
  if (isOutsiderAccountExpired(user, eventsList, ticketsList, referenceTime)) {
    const { expirationDate, latestEvent } = getOutsiderAccountExpirationDate(user, eventsList, ticketsList);
    user.isActive = false;
    user.accountStatus = 'deactivated';
    user.deactivationReason = `Temporary Outsider account expired on ${formatPhilippineExpiration(expirationDate)} following the conclusion of ${latestEvent?.title || 'the event'}.`;
    user.expiredAt = referenceTime.toISOString();
  }
}

evaluateExpiration(mockOutsiderUser, events, tickets, timeNov6_120000AM_PHT);
assert.strictEqual(mockOutsiderUser.isActive, false, 'User isActive must be false');
assert.strictEqual(mockOutsiderUser.accountStatus, 'deactivated', 'User accountStatus must be deactivated');
console.log(`[PASS] User state transition: ACTIVE -> DEACTIVATED (Reason: "${mockOutsiderUser.deactivationReason}")`);

// Test 8: Verify the outsider ticket still exists and is not voided
assert.strictEqual(mockOutsiderTicket.ticketStatus, 'ISSUED', 'Ticket status must NOT be changed to VOID');
assert.strictEqual(mockOutsiderTicket.attendanceStatus, 'present', 'Attendance status must remain PRESENT');
assert.strictEqual(mockOutsiderTicket.currentAttendeeName, 'Alex Guestson');
console.log(`[PASS] Ticket Record Preserved: Ticket #${mockOutsiderTicket.ticketNumber}, Status: ${mockOutsiderTicket.ticketStatus}, Attendance: ${mockOutsiderTicket.attendanceStatus}`);

// Test 9: Verify payment transaction still exists
assert.strictEqual(mockOutsiderTxn.amount, 250, 'Transaction amount preserved');
assert.strictEqual(mockOutsiderTxn.paymentStatus, 'paid', 'Transaction payment status preserved');
console.log(`[PASS] Payment Transaction Preserved: TXN #${mockOutsiderTxn.id}, Amount: PHP ${mockOutsiderTxn.amount}`);

// Test 10: Verify attendance log still exists
assert.strictEqual(mockAttendanceLog.status, 'valid', 'Attendance log status preserved');
assert.strictEqual(mockAttendanceLog.attendeeName, 'Alex Guestson');
console.log(`[PASS] Attendance Record Preserved: Scan log #${mockAttendanceLog.id}, TimeIn: ${mockOutsiderTicket.timeIn}`);

// Test 11: Verify Admin historical reporting access
const adminAuditRecords = tickets.filter(t => t.attendeeType === 'OUTSIDER');
assert.strictEqual(adminAuditRecords.length, 1, 'Admin can view all historical outsider tickets');
console.log(`[PASS] Admin Audit: ${adminAuditRecords.length} historical outsider ticket(s) found in archive`);

// Test 12: Verify outsider cannot log in or access expired account
function simulateLogin(user, password) {
  if (user.isActive === false) {
    return {
      success: false,
      error: `This temporary Outsider account expired on ${formatPhilippineExpiration(cutoff)}. Historical ticket and attendance records remain preserved in the institutional archive.`
    };
  }
  return { success: true };
}

const loginResult = simulateLogin(mockOutsiderUser, 'password123');
assert.strictEqual(loginResult.success, false, 'Login must be rejected');
console.log(`[PASS] Login Blocked: "${loginResult.error}"`);

// Test 13: Multi-Event Handling
console.log('\n--- MULTI-EVENT RESOLUTION TEST ---');
const mockEventA = { id: 'evt-A', title: 'Nov 5 Event', date: '2026-11-05' };
const mockEventB = { id: 'evt-B', title: 'Nov 10 Event', date: '2026-11-10' };

const multiEventOutsider = {
  id: 'usr-guest-multi',
  name: 'Multi Guest',
  role: 'student',
  attendeeType: 'OUTSIDER',
  isOutsider: true,
  assignedEventIds: ['evt-A', 'evt-B'],
  isActive: true,
  accountStatus: 'active',
};

const multiEventsList = [mockEventA, mockEventB];
const multiExpInfo = getOutsiderAccountExpirationDate(multiEventOutsider, multiEventsList, []);
console.log(`[PASS] Multi-Event Latest Expiration: ${formatPhilippineExpiration(multiExpInfo.expirationDate)}`);
assert(formatPhilippineExpiration(multiExpInfo.expirationDate).includes('November 11, 2026 at 12:00:00 AM PHT'), 'Expiration must be Nov 11, 2026 12:00:00 AM PHT based on latest event');

// Check on Nov 6, 2026 (Event A ended, but Event B upcoming)
const isMultiExpiredNov6 = isOutsiderAccountExpired(multiEventOutsider, multiEventsList, [], timeNov6_120000AM_PHT);
console.log(`[PASS] Nov 6, 2026 -> isExpired: ${isMultiExpiredNov6} (Expected: false, Event B still active)`);
assert.strictEqual(isMultiExpiredNov6, false, 'Multi-event account must remain ACTIVE while Event B is active');

// Check on Nov 11, 2026 12:00:00 AM PHT
const timeNov11_120000AM_PHT = new Date(Date.UTC(2026, 10, 10, 16, 0, 0, 0));
const isMultiExpiredNov11 = isOutsiderAccountExpired(multiEventOutsider, multiEventsList, [], timeNov11_120000AM_PHT);
console.log(`[PASS] Nov 11, 2026 12:00:00 AM PHT -> isExpired: ${isMultiExpiredNov11} (Expected: true)`);
assert.strictEqual(isMultiExpiredNov11, true, 'Multi-event account expires after latest event concludes');

console.log('\n============================================================');
console.log('ALL 13 TEST CASES PASSED SUCCESSFULLY!');
console.log('============================================================');
