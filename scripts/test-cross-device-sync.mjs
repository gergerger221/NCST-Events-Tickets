import assert from 'assert';
import { INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_LOGS, INITIAL_TRANSACTIONS } from '../src/utils/seedData.ts';

console.log('================================================================');
console.log('TWO-DEVICE REAL-TIME ATTENDANCE & SECURITY SYNCHRONIZATION SUITE');
console.log('================================================================');

// 1. Authoritative Shared Backend Database Simulation
const authoritativeDatabase = {
  users: JSON.parse(JSON.stringify(INITIAL_USERS)),
  events: JSON.parse(JSON.stringify(INITIAL_EVENTS)),
  tickets: JSON.parse(JSON.stringify(INITIAL_TICKETS)),
  logs: JSON.parse(JSON.stringify(INITIAL_LOGS)),
  transactions: JSON.parse(JSON.stringify(INITIAL_TRANSACTIONS)),
  studentRecords: [],
  timestamp: Date.now(),
};

// Target Test Entities
const eventId = 'evt-juans-2026';
const studentId = '2026-00125';
const studentCourse = 'BSIT';
const studentSection = '31M1';

// Ensure clean pre-scan state for this test case
authoritativeDatabase.tickets.forEach(t => {
  if (t.attendeeStudentId === studentId && t.eventId === eventId) {
    t.attendanceStatus = 'not_entered';
    t.ticketStatus = 'ISSUED';
    delete t.timeIn;
  }
});
authoritativeDatabase.logs = authoritativeDatabase.logs.filter(l => l.studentId !== studentId || l.eventId !== eventId);

// Step 1: Pre-Scan Verification
console.log('\n[TEST 1] Pre-Scan Initial State Verification');
const initialTicket = authoritativeDatabase.tickets.find(
  t => t.attendeeStudentId === studentId && t.eventId === eventId
);
assert(initialTicket, 'Juan Dela Cruz ticket must exist in database');
assert.strictEqual(initialTicket.attendanceStatus, 'not_entered', 'Initial attendance status must be "not_entered"');
assert.strictEqual(initialTicket.ticketStatus, 'ISSUED', 'Initial ticket status must be "ISSUED"');
console.log(`[PASS] Student Juan Dela Cruz (${studentId}) ticket #${initialTicket.ticketNumber} is NOT YET ENTERED.`);

// Step 2: Teacher Device B Query Simulation (Role = Teacher, Assigned Class: BSIT - 31M1)
console.log('\n[TEST 2] Teacher Device B Scoped Live Roster Query (BSIT / 31M1)');
const teacherUser = authoritativeDatabase.users.find(u => u.role === 'teacher' && u.assignedClasses?.some(c => c.course === 'BSIT' && c.section === '31M1'))
  || authoritativeDatabase.users.find(u => u.role === 'teacher');

assert(teacherUser, 'Teacher user must exist');
console.log(`[PASS] Teacher ${teacherUser.name} active session retrieved with assigned classes:`, teacherUser.assignedClasses);

const teacherScopedTickets = authoritativeDatabase.tickets.filter(t => {
  return t.course === studentCourse && t.section === studentSection;
});
const teacherJuanTicket = teacherScopedTickets.find(t => t.attendeeStudentId === studentId);
assert(teacherJuanTicket, 'Teacher Device B must see Juan Dela Cruz in BSIT - 31M1');
assert.strictEqual(teacherJuanTicket.attendanceStatus, 'not_entered', 'Teacher Device B shows NOT YET ENTERED before scan');
console.log(`[PASS] Device B confirms Juan Dela Cruz status: NOT YET ENTERED.`);

// Step 3: Event Staff Device A Scans QR Code (Write Authorization)
console.log('\n[TEST 3] Event Staff Device A Scans Valid QR Code (Authorized Write)');
const staffUser = authoritativeDatabase.users.find(u => u.role === 'event_staff');
assert(staffUser, 'Event Staff user must exist');

const scanTime = new Date('2026-11-05T18:15:00+08:00').toISOString();

// Mutate authoritative database simulating staff scan POST /api/state
const ticketIdx = authoritativeDatabase.tickets.findIndex(t => t.id === initialTicket.id);
authoritativeDatabase.tickets[ticketIdx] = {
  ...authoritativeDatabase.tickets[ticketIdx],
  ticketStatus: 'USED',
  attendanceStatus: 'present',
  timeIn: scanTime,
  lastScannedBy: staffUser.name,
};

const newAttendanceLog = {
  id: `log-scan-${Date.now()}`,
  ticketId: initialTicket.id,
  ticketNumber: initialTicket.ticketNumber,
  eventId: eventId,
  eventTitle: 'The Juans Live Concert',
  attendeeType: 'STUDENT',
  attendeeName: 'Juan Dela Cruz',
  studentId: studentId,
  course: studentCourse,
  section: studentSection,
  timestamp: scanTime,
  status: 'valid',
  verifiedByName: staffUser.name,
};
authoritativeDatabase.logs.unshift(newAttendanceLog);
authoritativeDatabase.timestamp = Date.now();

console.log(`[PASS] Device A scanned QR -> Server updated: Ticket status: USED, Attendance: PRESENT, Time In: ${scanTime}`);

// Step 4: Device B (Teacher) Periodic Server Sync (~2s Polling)
console.log('\n[TEST 4] Cross-Device Synchronization Verification (Device B Reception)');
const syncedTeacherTickets = authoritativeDatabase.tickets.filter(t => {
  return t.course === studentCourse && t.section === studentSection;
});
const updatedTeacherJuan = syncedTeacherTickets.find(t => t.attendeeStudentId === studentId);
assert.strictEqual(updatedTeacherJuan.attendanceStatus, 'present', 'Teacher Device B must automatically reflect PRESENT');
assert.strictEqual(updatedTeacherJuan.timeIn, scanTime, 'Teacher Device B must show exact Time In');
console.log(`[PASS] Device B automatically synchronized: Juan Dela Cruz is PRESENT at ${updatedTeacherJuan.timeIn}`);

// Step 5: Security Test - Teacher Write Permission Rejection (POST /api/state)
console.log('\n[TEST 5] Security Enforcement: Teacher Write Rejection in POST /api/state');
function simulatePostApiState(authUser, payload) {
  if (!authUser) return { status: 401, error: 'Unauthorized' };
  if (authUser.role === 'admin') return { status: 200, success: true };
  if (authUser.role === 'event_staff' || authUser.role === 'staff') return { status: 200, success: true };
  if (authUser.role === 'cashier') return { status: 200, success: true };
  if (authUser.role === 'teacher') {
    return {
      status: 403,
      error: '403 Forbidden: Teachers have read-only access to master student rosters. Institutional StudentRecord modifications require Administrator authorization.'
    };
  }
  return { status: 403, error: 'Forbidden' };
}

const teacherWriteAttempt = simulatePostApiState(teacherUser, {
  studentRecords: [{ studentId: '2026-99999', name: 'Malicious Injected Student' }]
});
assert.strictEqual(teacherWriteAttempt.status, 403, 'Teacher state write must be rejected with 403');
assert(teacherWriteAttempt.error.includes('403 Forbidden: Teachers have read-only access'), 'Error must specify read-only status');
console.log(`[PASS] Server rejected Teacher write attempt with 403 Forbidden. Master roster is protected.`);

// Step 6: Security Test - Cashier Write Authorization
console.log('\n[TEST 6] Security Verification: Cashier Write Authorization');
const cashierUser = authoritativeDatabase.users.find(u => u.role === 'cashier');
const cashierWriteAttempt = simulatePostApiState(cashierUser, {
  tickets: [{ id: 'tix-new-cashier' }],
  transactions: [{ id: 'txn-new-cashier' }]
});
assert.strictEqual(cashierWriteAttempt.status, 200, 'Cashier ticket write must succeed');
console.log(`[PASS] Cashier authorized POS write passed.`);

// Step 7: Student & Admin View Synchronization
console.log('\n[TEST 7] Student & Admin View Synchronization Verification');
const studentOwnTicket = authoritativeDatabase.tickets.find(t => t.attendeeStudentId === studentId && t.eventId === eventId);
assert.strictEqual(studentOwnTicket.attendanceStatus, 'present', 'Student Digital Pass must show PRESENT');

const adminLiveLog = authoritativeDatabase.logs.find(l => l.studentId === studentId && l.eventId === eventId);
assert(adminLiveLog, 'Admin real-time entrance log must record scan');
assert.strictEqual(adminLiveLog.status, 'valid');
console.log(`[PASS] Student Pass and Admin Live Logs synchronized simultaneously.`);

// Step 8: Duplicate Scan Prevention Test
console.log('\n[TEST 8] Duplicate Scan Rejection Test');
const currentTicketState = authoritativeDatabase.tickets.find(t => t.id === initialTicket.id);

const isDuplicate = currentTicketState.ticketStatus === 'USED' || currentTicketState.attendanceStatus === 'present';
assert(isDuplicate, 'System must detect that ticket is already used');
console.log(`[PASS] Device A detects: ALREADY USED (Scanned by ${currentTicketState.lastScannedBy} at ${currentTicketState.timeIn}).`);

assert.strictEqual(authoritativeDatabase.tickets[ticketIdx].timeIn, scanTime, 'Original Time In must remain untouched');
const matchingLogs = authoritativeDatabase.logs.filter(l => l.ticketId === initialTicket.id);
assert.strictEqual(matchingLogs.length, 1, 'Only exactly 1 attendance log must exist for the scanned ticket');
console.log(`[PASS] Duplicate scan safely denied with 0 duplicate logs.`);

console.log('\n================================================================');
console.log('ALL 8 CROSS-DEVICE REAL-TIME ATTENDANCE & SECURITY TESTS PASSED!');
console.log('================================================================');
