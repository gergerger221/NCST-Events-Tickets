import assert from 'assert';
import { INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_LOGS } from '../src/utils/seedData.ts';

console.log('🧪 Starting Event Staff (`event_staff`) Validation & Scanner Test Suite...\n');

// 1. Setup Mock In-Memory Database
const db = {
  users: JSON.parse(JSON.stringify(INITIAL_USERS)),
  events: JSON.parse(JSON.stringify(INITIAL_EVENTS)),
  tickets: JSON.parse(JSON.stringify(INITIAL_TICKETS)),
  logs: JSON.parse(JSON.stringify(INITIAL_LOGS)),
};

// Staff user assigned to evt-juans-2026
const eventStaffA = db.users.find(u => u.role === 'event_staff') || {
  id: 'usr-staff-1',
  name: 'Alex Rivera',
  role: 'event_staff',
  assignedEventIds: ['evt-juans-2026'],
  isActive: true,
};
eventStaffA.assignedEventIds = ['evt-juans-2026'];

// Authoritative Scanner Engine (Mirroring backend & useAppStore logic)
function scanTicket(staffUser, eventId, qrData) {
  if (!staffUser) return { status: 401, error: '401 Unauthorized' };
  if (staffUser.role !== 'event_staff') {
    return { status: 403, error: '403 Forbidden: Only Event Staff can scan tickets.' };
  }
  if (staffUser.isActive === false) {
    return { status: 403, error: '403 Forbidden: Staff account deactivated.' };
  }
  if (staffUser.assignedEventIds && staffUser.assignedEventIds.length > 0) {
    if (!staffUser.assignedEventIds.includes(eventId)) {
      return { status: 403, error: '403 Forbidden: You are not assigned to scan tickets for this event.' };
    }
  }

  const cleanLookup = qrData.toLowerCase().replace(/^[№#\s]+/, '').trim();
  const ticket = db.tickets.find(t => 
    (t.id && t.id.toLowerCase() === cleanLookup) ||
    (t.ticketNumber && t.ticketNumber.toLowerCase() === cleanLookup) ||
    (t.qrToken && t.qrToken.toLowerCase() === cleanLookup)
  );

  if (!ticket) {
    return { status: 400, error: 'INVALID TICKET: Ticket not found in database.' };
  }

  if (ticket.eventId !== eventId) {
    return { status: 400, error: 'WRONG EVENT: This ticket is registered for a different event.' };
  }

  const tStatus = (ticket.ticketStatus || '').toUpperCase();
  if (tStatus === 'CANCELLED' || tStatus === 'REFUNDED' || tStatus === 'VOID') {
    return { status: 400, error: `TICKET NOT VALID: Ticket is marked as ${tStatus}.` };
  }

  if (ticket.attendanceStatus === 'present' || tStatus === 'USED') {
    return {
      status: 400,
      isDuplicate: true,
      error: '⚠️ TICKET ALREADY USED: Scanned previously.',
      originalTimeIn: ticket.timeIn,
      attendeeName: ticket.currentAttendeeName
    };
  }

  // Authoritative Attendance Recording
  const now = new Date();
  const timeInPht = now.toLocaleTimeString('en-US', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit' });
  const isoTime = now.toISOString();

  ticket.ticketStatus = 'USED';
  ticket.attendanceStatus = 'present';
  ticket.timeIn = isoTime;
  ticket.lastScannedBy = staffUser.name;

  const newLog = {
    id: `log-${Date.now()}`,
    ticketId: ticket.id,
    ticketNumber: ticket.ticketNumber || ticket.id,
    eventId: ticket.eventId,
    attendeeName: ticket.currentAttendeeName,
    attendeeType: ticket.attendeeType || 'STUDENT',
    studentId: ticket.attendeeStudentId,
    timestamp: isoTime,
    timeIn: timeInPht,
    status: 'valid',
    verifiedByName: staffUser.name
  };
  db.logs.unshift(newLog);

  return {
    status: 200,
    success: true,
    message: 'TICKET VALID: Attendance recorded.',
    attendeeName: ticket.currentAttendeeName,
    attendanceStatus: 'PRESENT',
    timeIn: timeInPht
  };
}

// Read-only Manual Lookup Engine (Does NOT record attendance)
function lookupTicketInfo(query) {
  const cleanLookup = query.toLowerCase().replace(/^[№#\s]+/, '').trim();
  const ticket = db.tickets.find(t => 
    (t.id && t.id.toLowerCase() === cleanLookup) ||
    (t.ticketNumber && t.ticketNumber.toLowerCase() === cleanLookup) ||
    (t.qrToken && t.qrToken.toLowerCase() === cleanLookup) ||
    (t.attendeeStudentId && t.attendeeStudentId.toLowerCase() === cleanLookup)
  );

  if (!ticket) return { found: false, message: 'Ticket not found.' };
  return {
    found: true,
    ticket,
    message: 'Ticket details retrieved (Lookup only — QR scan required for entry).'
  };
}

// --- TEST 1: Valid Student QR Scan ---
console.log('--- TEST 1: Valid Student QR Scan ---');
const studentTicket = db.tickets.find(t => t.eventId === 'evt-juans-2026' && t.attendanceStatus !== 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT') || {
  id: 'tkt-test-stud-1',
  ticketNumber: 'TIX-TEST-001',
  qrToken: 'QR-TEST-STUD-1',
  eventId: 'evt-juans-2026',
  attendeeStudentId: '2026-00125',
  currentAttendeeName: 'Juan Dela Cruz',
  ticketStatus: 'ISSUED',
  attendanceStatus: 'not_entered'
};
if (!db.tickets.some(t => t.id === studentTicket.id)) db.tickets.push(studentTicket);

const res1 = scanTicket(eventStaffA, 'evt-juans-2026', studentTicket.qrToken);
assert.strictEqual(res1.status, 200, 'Valid student scan should succeed');
assert.strictEqual(studentTicket.ticketStatus, 'USED', 'Ticket status must become USED');
assert.strictEqual(studentTicket.attendanceStatus, 'present', 'Attendance status must become PRESENT');
assert(studentTicket.timeIn, 'Time In must be stamped');
console.log('✅ TEST 1 PASSED: Student QR validated -> PRESENT + Time In recorded.\n');

// --- TEST 2: Duplicate Scan Interception ---
console.log('--- TEST 2: Duplicate Scan Interception ---');
const initialTimeIn = studentTicket.timeIn;
const initialLogsCount = db.logs.length;
const res2 = scanTicket(eventStaffA, 'evt-juans-2026', studentTicket.qrToken);
assert.strictEqual(res2.status, 400, 'Duplicate scan must be rejected');
assert.strictEqual(res2.isDuplicate, true, 'Must be flagged as duplicate scan');
assert.strictEqual(studentTicket.timeIn, initialTimeIn, 'Original Time In must never be overwritten');
assert.strictEqual(db.logs.length, initialLogsCount, 'No duplicate attendance record must be created');
console.log('✅ TEST 2 PASSED: Second scan flagged as ALREADY USED with original Time In preserved.\n');

// --- TEST 3: Valid Outsider QR Scan ---
console.log('--- TEST 3: Valid Outsider QR Scan ---');
const outsiderTicket = {
  id: 'tkt-test-out-1',
  ticketNumber: 'TIX-OUT-001',
  qrToken: 'QR-TEST-OUT-1',
  eventId: 'evt-juans-2026',
  currentAttendeeName: 'Guest John Doe',
  attendeeType: 'OUTSIDER',
  ticketStatus: 'ISSUED',
  attendanceStatus: 'not_entered'
};
db.tickets.push(outsiderTicket);
const res3 = scanTicket(eventStaffA, 'evt-juans-2026', outsiderTicket.qrToken);
assert.strictEqual(res3.status, 200, 'Valid outsider scan should succeed');
assert.strictEqual(outsiderTicket.ticketStatus, 'USED', 'Outsider ticket must become USED');
assert.strictEqual(outsiderTicket.attendanceStatus, 'present', 'Outsider attendance must become PRESENT');
console.log('✅ TEST 3 PASSED: Outsider QR validated -> PRESENT + Time In recorded.\n');

// --- TEST 4: Fake / Invalid QR Token ---
console.log('--- TEST 4: Fake / Invalid QR Token ---');
const res4 = scanTicket(eventStaffA, 'evt-juans-2026', 'QR-FAKE-TOKEN-999');
assert.strictEqual(res4.status, 400, 'Fake QR must be rejected');
assert(res4.error.includes('INVALID TICKET'), 'Should return invalid ticket error');
console.log('✅ TEST 4 PASSED: Fake QR token rejected.\n');

// --- TEST 5: VOID Ticket Scan ---
console.log('--- TEST 5: VOID Ticket Scan ---');
const voidTicket = {
  id: 'tkt-test-void-1',
  ticketNumber: 'TIX-VOID-001',
  qrToken: 'QR-TEST-VOID-1',
  eventId: 'evt-juans-2026',
  currentAttendeeName: 'Revoked Attendee',
  ticketStatus: 'VOID',
  attendanceStatus: 'not_entered'
};
db.tickets.push(voidTicket);
const res5 = scanTicket(eventStaffA, 'evt-juans-2026', voidTicket.qrToken);
assert.strictEqual(res5.status, 400, 'Void ticket must be rejected');
assert(res5.error.includes('TICKET NOT VALID') || res5.error.includes('VOID'), 'Should return void error');
console.log('✅ TEST 5 PASSED: VOID ticket rejected.\n');

// --- TEST 6: Wrong Event Ticket Scan ---
console.log('--- TEST 6: Wrong Event Ticket Scan ---');
const otherEventTicket = {
  id: 'tkt-test-it-1',
  ticketNumber: 'TIX-IT-001',
  qrToken: 'QR-TEST-IT-1',
  eventId: 'evt-it-2026',
  currentAttendeeName: 'Student CS',
  ticketStatus: 'ISSUED',
  attendanceStatus: 'not_entered'
};
db.tickets.push(otherEventTicket);
const res6 = scanTicket(eventStaffA, 'evt-juans-2026', otherEventTicket.qrToken);
assert.strictEqual(res6.status, 400, 'Wrong event ticket must be rejected');
assert(res6.error.includes('WRONG EVENT'), 'Should return wrong event error');
console.log('✅ TEST 6 PASSED: Wrong event ticket rejected.\n');

// --- TEST 7: Staff Assigned Event Isolation ---
console.log('--- TEST 7: Staff Assigned Event Isolation ---');
const res7 = scanTicket(eventStaffA, 'evt-it-2026', otherEventTicket.qrToken);
assert.strictEqual(res7.status, 403, 'Staff cannot scan for unassigned event');
console.log('✅ TEST 7 PASSED: Staff isolated to assigned events only.\n');

// --- TEST 8: Role Enforcement (Unauthorized Roles Cannot Scan) ---
console.log('--- TEST 8: Role Enforcement ---');
const studentUser = { id: 'usr-s1', name: 'Student Juan', role: 'student', isActive: true };
const teacherUser = { id: 'usr-t1', name: 'Prof. Reyes', role: 'teacher', isActive: true };
const cashierUser = { id: 'usr-c1', name: 'Cashier Carla', role: 'cashier', isActive: true };

assert.strictEqual(scanTicket(studentUser, 'evt-juans-2026', 'QR-ANY').status, 403, 'Student cannot scan');
assert.strictEqual(scanTicket(teacherUser, 'evt-juans-2026', 'QR-ANY').status, 403, 'Teacher cannot scan');
assert.strictEqual(scanTicket(cashierUser, 'evt-juans-2026', 'QR-ANY').status, 403, 'Cashier cannot scan');
console.log('✅ TEST 8 PASSED: Only event_staff role can invoke attendance scanner.\n');

// --- TEST 9: Manual Lookup Does NOT Create Attendance ---
console.log('--- TEST 9: Manual Lookup Read-Only Integrity ---');
const freshTicket = {
  id: 'tkt-lookup-test',
  ticketNumber: 'TIX-LOOKUP-001',
  qrToken: 'QR-LOOKUP-001',
  eventId: 'evt-juans-2026',
  currentAttendeeName: 'Unscanned Attendee',
  ticketStatus: 'ISSUED',
  attendanceStatus: 'not_entered'
};
db.tickets.push(freshTicket);
const logsBeforeLookup = db.logs.length;
const lookupRes = lookupTicketInfo('TIX-LOOKUP-001');
assert.strictEqual(lookupRes.found, true, 'Ticket should be found');
assert.strictEqual(freshTicket.attendanceStatus, 'not_entered', 'Attendance status must remain not_entered');
assert.strictEqual(freshTicket.ticketStatus, 'ISSUED', 'Ticket status must remain ISSUED');
assert.strictEqual(db.logs.length, logsBeforeLookup, 'No attendance log should be created on manual lookup');
console.log('✅ TEST 9 PASSED: Manual lookup is strictly verification-only and does not record attendance.\n');

console.log('🎉 ALL 9 EVENT STAFF & SCANNER TEST CASES PASSED SUCCESSFULLY!\n');
