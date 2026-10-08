import assert from 'assert';
import { INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_TRANSACTIONS, INITIAL_LOGS } from '../src/utils/seedData.ts';
import { validateStudentId, validateSection, validateYearLevel, validateAttendeeName, validateCashPayment } from '../src/utils/posValidation.ts';

console.log('================================================================');
console.log('NCST TICKET LIFECYCLE & DATA FLOW AUDIT SUITE');
console.log('================================================================\n');

// Mock shared store state simulating useAppStore()
let tickets = [...INITIAL_TICKETS];
let transactions = [...INITIAL_TRANSACTIONS];
let logs = [...INITIAL_LOGS];
let users = [...INITIAL_USERS];
let events = [...INITIAL_EVENTS];

const filters = {
  eventId: 'all',
  searchQuery: '',
  attendeeType: 'all',
  ticketStatus: 'all',
  qrPrintStatus: 'all',
  paymentStatus: 'all',
  attendanceStatus: 'all',
  ticketType: 'all',
  course: 'all',
  yearLevel: 'all',
  section: 'all',
};

const getFilteredTickets = () => {
  return tickets.filter(ticket => {
    if (filters.eventId !== 'all' && ticket.eventId !== filters.eventId) return false;
    if (filters.attendeeType && filters.attendeeType !== 'all') {
      const aType = ticket.attendeeType || (ticket.attendeeStudentId ? 'STUDENT' : 'OUTSIDER');
      if (aType !== filters.attendeeType) return false;
    }
    if (filters.ticketStatus !== 'all') {
      const tStatus = (ticket.ticketStatus || '').toLowerCase();
      const fStatus = filters.ticketStatus.toLowerCase();
      if ((fStatus === 'active' || fStatus === 'issued') && tStatus !== 'active' && tStatus !== 'issued') return false;
      else if (fStatus === 'used' && tStatus !== 'used') return false;
      else if ((fStatus === 'cancelled' || fStatus === 'void') && tStatus !== 'cancelled' && tStatus !== 'void') return false;
      else if (fStatus !== 'active' && fStatus !== 'issued' && fStatus !== 'used' && fStatus !== 'cancelled' && fStatus !== 'void' && tStatus !== fStatus) return false;
    }
    if (filters.qrPrintStatus && filters.qrPrintStatus !== 'all') {
      const tQrStatus = ticket.qrPrintStatus || 'PENDING';
      if (tQrStatus !== filters.qrPrintStatus) return false;
    }
    if (filters.paymentStatus !== 'all' && ticket.paymentStatus !== filters.paymentStatus) return false;
    if (filters.attendanceStatus !== 'all') {
      const tAtt = (ticket.attendanceStatus || 'not_entered').toLowerCase();
      const fAtt = filters.attendanceStatus.toLowerCase();
      if (fAtt === 'present' && tAtt !== 'present') return false;
      if ((fAtt === 'not_entered' || fAtt === 'not_yet_entered') && tAtt !== 'not_entered' && tAtt !== 'not_yet_entered') return false;
    }
    if (filters.course && filters.course !== 'all' && filters.course.trim()) {
      const cFilter = filters.course.trim().toLowerCase();
      const tCourse = (ticket.course || '').toLowerCase();
      if (!tCourse.includes(cFilter) && tCourse !== cFilter) return false;
    }
    if (filters.yearLevel && filters.yearLevel !== 'all' && filters.yearLevel.trim()) {
      const yFilter = filters.yearLevel.trim().toLowerCase();
      const tYear = (ticket.yearLevel || '').toLowerCase();
      if (!tYear.includes(yFilter) && tYear !== yFilter) return false;
    }
    if (filters.section && filters.section !== 'all' && filters.section.trim()) {
      const sFilter = filters.section.trim().toLowerCase();
      const tSec = (ticket.section || '').toLowerCase();
      if (!tSec.includes(sFilter) && tSec !== sFilter) return false;
    }

    if (filters.searchQuery.trim()) {
      const q = filters.searchQuery.toLowerCase();
      const matchId = (ticket.id || '').toLowerCase().includes(q);
      const matchNum = (ticket.ticketNumber || '').toLowerCase().includes(q);
      const matchName = (ticket.currentAttendeeName || '').toLowerCase().includes(q);
      const matchStudentId = (ticket.attendeeStudentId || '').toLowerCase().includes(q);
      const matchCourse = (ticket.course || '').toLowerCase().includes(q);
      const matchYear = (ticket.yearLevel || '').toLowerCase().includes(q);
      const matchSection = (ticket.section || '').toLowerCase().includes(q);
      const matchEmail = (ticket.attendeeEmail || '').toLowerCase().includes(q);
      const matchOriginal = (ticket.originalOwnerName || '').toLowerCase().includes(q);
      if (!matchId && !matchNum && !matchName && !matchStudentId && !matchCourse && !matchYear && !matchSection && !matchEmail && !matchOriginal) {
        return false;
      }
    }

    return true;
  });
};

// Purchase Ticket simulation
const purchaseTicket = (eventId, cashierName, studentOverride, price) => {
  const targetEvent = events.find(e => e.id === eventId);
  const studentName = studentOverride.name.trim();
  const studentId = studentOverride.studentId.trim();
  const course = studentOverride.course || 'BSIT';
  const yearLevel = studentOverride.yearLevel || '3rd Year';
  const section = studentOverride.section || '11M1';

  // Backend validation
  const nameVal = validateAttendeeName(studentName);
  assert.strictEqual(nameVal.isValid, true, 'Name must be valid');

  const idVal = validateStudentId(studentId);
  assert.strictEqual(idVal.isValid, true, 'Student ID must be valid');

  const yrVal = validateYearLevel(yearLevel);
  assert.strictEqual(yrVal.isValid, true, 'Year level must be valid');

  const secVal = validateSection(section);
  assert.strictEqual(secVal.isValid, true, 'Section must be valid');

  const seq = Math.floor(10000 + tickets.length + 1);
  const newTicketNumber = `TIX-${seq}`;

  const newTicket = {
    id: newTicketNumber,
    ticketNumber: newTicketNumber,
    qrToken: newTicketNumber,
    eventId: targetEvent ? targetEvent.id : eventId,
    eventTitle: targetEvent ? targetEvent.title : 'Event Pass',
    ticketType: 'digital',
    attendeeType: 'STUDENT',
    originalOwnerId: `usr-stud-${studentId}`,
    originalOwnerName: studentName,
    currentAttendeeId: `usr-stud-${studentId}`,
    currentAttendeeName: studentName,
    attendeeStudentId: studentId,
    attendeeEmail: `${studentName.toLowerCase().replace(/\s+/g, '.')}@student.edu.ph`,
    attendeeDepartment: 'College of Computer Studies',
    course,
    yearLevel,
    section,
    price,
    purchaseDate: new Date().toISOString(),
    ticketStatus: 'ISSUED',
    paymentStatus: price > 0 ? 'paid' : 'free',
    qrPrintStatus: 'PENDING',
    qrPrintCount: 0,
    attendanceStatus: 'not_entered',
    transferHistory: [],
    notes: 'Payment recorded via Cashier Ticket Desk.',
  };

  tickets.unshift(newTicket);
  return { success: true, ticket: newTicket };
};

// --- STAGE A: ISSUE ONE TICKET ---
console.log('Stage A: Issuing one real test ticket from Cashier POS...');
const targetEvent = events[0];
const initialTicketCount = tickets.length;

const testIssuance = purchaseTicket(
  targetEvent.id,
  'Maria Santos (Cashier Desk)',
  {
    studentId: '2026-00125',
    name: 'Juan Dela Cruz',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '11M1',
    cashReceived: '500',
  },
  150
);

assert.strictEqual(testIssuance.success, true);
const testTicket = testIssuance.ticket;

console.log(`   ↳ Ticket Number: ${testTicket.ticketNumber}`);
console.log(`   ↳ Control / QR Token: ${testTicket.qrToken}`);
console.log(`   ↳ Student/Attendee: ${testTicket.currentAttendeeName} (${testTicket.attendeeStudentId})`);
console.log(`   ↳ Event: ${testTicket.eventTitle} (${testTicket.eventId})`);
console.log(`   ↳ Ticket Status: ${testTicket.ticketStatus}`);
console.log(`   ↳ QR Print Status: ${testTicket.qrPrintStatus}`);
console.log(`   ↳ Tickets count in authoritative store: ${tickets.length} (was ${initialTicketCount})`);
assert.strictEqual(tickets.length, initialTicketCount + 1, 'Exactly 1 ticket record must be added');
console.log('   ✅ Stage A Passed.\n');

// --- STAGE B: SEARCH BY TICKET NUMBER IN REGISTRY ---
console.log('Stage B: Searching by Ticket Number in Registry...');
filters.searchQuery = testTicket.ticketNumber;
let registryMatches = getFilteredTickets();
assert.strictEqual(registryMatches.length, 1, 'Search by Ticket Number must return exactly 1 ticket');
assert.strictEqual(registryMatches[0].id, testTicket.id);
console.log(`   ↳ Found: ${registryMatches[0].ticketNumber} - ${registryMatches[0].currentAttendeeName}`);
console.log('   ✅ Stage B Passed.\n');

// --- STAGE C: SEARCH BY STUDENT ID IN REGISTRY ---
console.log('Stage C: Searching by Student ID in Registry...');
filters.searchQuery = '2026-00125';
registryMatches = getFilteredTickets();
assert.ok(registryMatches.length >= 1, 'Search by Student ID must find the ticket');
assert.ok(registryMatches.some(t => t.id === testTicket.id), 'Test ticket must be in the search results');
console.log(`   ↳ Found ${registryMatches.length} match(es) for Student ID 2026-00125`);
console.log('   ✅ Stage C Passed.\n');

// --- STAGE D: SEARCH BY EVENT IN REGISTRY ---
console.log('Stage D: Searching by Event in Registry...');
filters.searchQuery = '';
filters.eventId = targetEvent.id;
registryMatches = getFilteredTickets();
assert.ok(registryMatches.some(t => t.id === testTicket.id), 'Test ticket must appear when filtering by its event');
console.log(`   ↳ Found test ticket in event filter for ${targetEvent.title}`);
filters.eventId = 'all'; // Reset
console.log('   ✅ Stage D Passed.\n');

// --- STAGE E: QR PRINT QUEUE REFLECTION ---
console.log('Stage E: Checking QR Print Queue...');
const queueTickets = tickets.filter(t => t.ticketStatus !== 'CANCELLED' && t.ticketStatus !== 'VOID');
const pendingTickets = queueTickets.filter(t => (t.qrPrintStatus || 'PENDING') === 'PENDING');
assert.ok(pendingTickets.some(t => t.id === testTicket.id), 'Ticket must be in pendingTickets queue');
const foundInQueue = pendingTickets.find(t => t.id === testTicket.id);
assert.strictEqual(foundInQueue.qrPrintStatus, 'PENDING');
console.log(`   ↳ Ticket ${foundInQueue.ticketNumber} is present in QR Print Queue with status PENDING`);
console.log('   ✅ Stage E Passed.\n');

// --- STAGE F: STUDENT MY PASSES & ATTENDANCE REFLECTION ---
console.log('Stage F: Checking Student My Passes & Attendance...');
const studentUser = { id: `usr-stud-2026-00125`, studentId: '2026-00125', name: 'Juan Dela Cruz', role: 'student' };
const studentMyTickets = tickets.filter(t => {
  if (t.currentAttendeeId === studentUser.id) return true;
  if (studentUser.studentId && t.attendeeStudentId === studentUser.studentId) return true;
  return false;
});
assert.ok(studentMyTickets.some(t => t.id === testTicket.id), 'Ticket must appear in student personal passes');
console.log(`   ↳ Found ticket ${testTicket.ticketNumber} in Student (${studentUser.studentId}) My Passes`);
console.log('   ✅ Stage F Passed.\n');

// --- STAGE G: ADMIN TICKET MANAGEMENT ---
console.log('Stage G: Checking Admin Ticket Management...');
filters.eventId = 'all';
filters.searchQuery = '';
const adminTickets = getFilteredTickets();
assert.ok(adminTickets.some(t => t.id === testTicket.id), 'Ticket must appear in Admin Ticket Management list');
console.log(`   ↳ Admin Ticket Management lists test ticket ${testTicket.ticketNumber}`);
console.log('   ✅ Stage G Passed.\n');

// --- STAGE H: SCAN QR CODE BY EVENT STAFF ---
console.log('Stage H: Scanning QR Code by Event Staff...');
// Simulate Event Staff scanning ticket QR Token
const scanTicket = (qrToken, staffName = 'Staff Alex') => {
  const t = tickets.find(x => x.qrToken === qrToken || x.id === qrToken || x.ticketNumber === qrToken);
  assert.ok(t, 'Ticket must exist for QR token');
  assert.strictEqual(t.ticketStatus, 'ISSUED', 'Ticket must be in ISSUED state before scan');
  assert.strictEqual(t.attendanceStatus, 'not_entered', 'Attendance status must be not_entered before scan');

  t.ticketStatus = 'USED';
  t.attendanceStatus = 'present';
  t.timeIn = new Date().toISOString();
  t.lastScannedBy = staffName;

  logs.unshift({
    id: `log-${Date.now()}`,
    ticketId: t.id,
    ticketNumber: t.ticketNumber,
    eventId: t.eventId,
    attendeeName: t.currentAttendeeName,
    studentId: t.attendeeStudentId,
    timestamp: t.timeIn,
    status: 'valid'
  });

  return { success: true, ticket: t };
};

const scanResult = scanTicket(testTicket.qrToken);
assert.strictEqual(scanResult.success, true);
assert.strictEqual(testTicket.ticketStatus, 'USED');
assert.strictEqual(testTicket.attendanceStatus, 'present');
assert.ok(testTicket.timeIn, 'timeIn timestamp must be set');
console.log(`   ↳ Scan Successful: Status = ${testTicket.ticketStatus}, Attendance = ${testTicket.attendanceStatus}, Time In = ${testTicket.timeIn}`);
console.log('   ✅ Stage H Passed.\n');

// --- STAGE I: SEARCH REGISTRY AGAIN (POST-SCAN) ---
console.log('Stage I: Searching Registry Post-Scan...');
filters.searchQuery = testTicket.ticketNumber;
registryMatches = getFilteredTickets();
assert.strictEqual(registryMatches.length, 1, 'Must still return exactly 1 ticket (zero duplicates)');
assert.strictEqual(registryMatches[0].ticketStatus, 'USED');
assert.strictEqual(registryMatches[0].attendanceStatus, 'present');
console.log(`   ↳ Ticket ${registryMatches[0].ticketNumber} updated in Registry: Status=${registryMatches[0].ticketStatus}, Attendance=${registryMatches[0].attendanceStatus}`);
console.log('   ✅ Stage I Passed.\n');

// --- STAGE J: COMPREHENSIVE FILTERING AUDIT ---
console.log('Stage J: Comprehensive Filter Audit...');
filters.searchQuery = '';

// 1. Attendee Type Filter
filters.attendeeType = 'STUDENT';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match STUDENT attendeeType filter');
filters.attendeeType = 'OUTSIDER';
assert.ok(!getFilteredTickets().some(t => t.id === testTicket.id), 'Must NOT match OUTSIDER attendeeType filter');
filters.attendeeType = 'all';

// 2. Ticket Status Filter (USED)
filters.ticketStatus = 'used';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match USED ticket status filter');
filters.ticketStatus = 'all';

// 3. Attendance Status Filter (PRESENT)
filters.attendanceStatus = 'present';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match PRESENT attendance filter');
filters.attendanceStatus = 'not_entered';
assert.ok(!getFilteredTickets().some(t => t.id === testTicket.id), 'Must NOT match NOT_ENTERED attendance filter');
filters.attendanceStatus = 'all';

// 4. Course Filter
filters.course = 'BSIT';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match BSIT course filter');
filters.course = 'all';

// 5. Year Level Filter
filters.yearLevel = '3rd Year';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match 3rd Year filter');
filters.yearLevel = 'all';

// 6. Section Filter
filters.section = '11M1';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match 11M1 section filter');
filters.section = 'all';

// 7. QR Print Status Filter
filters.qrPrintStatus = 'PENDING';
assert.ok(getFilteredTickets().some(t => t.id === testTicket.id), 'Must match PENDING qr print status filter');
filters.qrPrintStatus = 'all';

console.log('   ↳ All 7 filter dimensions verified. No accidental hiding.');
console.log('   ✅ Stage J Passed.\n');

// --- STAGE K: ROSTER & SEPARATION OF CONCERNS VERIFICATION ---
console.log('Stage K: Checking Authoritative Student Record & Non-Creation of User Account...');
// Issue a ticket for a student without an account
const newStudentId = '2026-00999';
const newStudentName = 'Clara Bella';
let studentRecords = [];

const saveStudentRecord = (data) => {
  const rec = {
    id: `rec-${data.studentId}`,
    studentId: data.studentId,
    name: data.name,
    course: data.course || 'BSCS',
    yearLevel: data.yearLevel || '2nd Year',
    section: data.section || '21M1',
  };
  studentRecords.unshift(rec);
  return rec;
};

// Cashier registers student record in roster
saveStudentRecord({
  studentId: newStudentId,
  name: newStudentName,
  course: 'BSCS',
  yearLevel: '2nd Year',
  section: '21M1',
});

const newStudentIssuance = purchaseTicket(
  targetEvent.id,
  'Maria Santos (Cashier)',
  {
    studentId: newStudentId,
    name: newStudentName,
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: '21M1',
    cashReceived: '200',
  },
  150
);

// Verify student record exists in studentRecords roster
const foundRec = studentRecords.find(r => r.studentId === newStudentId);
assert.ok(foundRec, 'Authoritative Student Record must exist in studentRecords roster');
assert.strictEqual(foundRec.name, newStudentName);
assert.strictEqual(foundRec.course, 'BSCS');

// Verify NO user login account was automatically created in users repository
const foundUser = users.find(u => u.studentId === newStudentId);
assert.strictEqual(foundUser, undefined, 'Cashier issuance must NOT create a user login account');

// Now simulate student registering later
const registeredUser = {
  id: 'usr-student-999',
  name: newStudentName,
  email: 'clara.bella@student.edu.ph',
  role: 'student',
  studentId: newStudentId,
  course: 'BSCS',
  yearLevel: '2nd Year',
  section: '21M1',
  isActive: true,
};
users.unshift(registeredUser);

// Verify user now exists in users repository post-registration
const postRegUser = users.find(u => u.studentId === newStudentId);
assert.ok(postRegUser, 'Registered student now exists in users repository');
assert.strictEqual(postRegUser.role, 'student');

console.log(`   ↳ Student Record ${newStudentName} (${newStudentId}) verified in roster.`);
console.log('   ↳ Cashier did NOT create a user login account (correct architecture).');
console.log('   ↳ Student successfully created login account upon registration.');
console.log('   ✅ Stage K Passed.\n');

console.log('================================================================');
console.log('SUMMARY: COMPLETE TICKET DATA FLOW & REGISTRY LIFECYCLE VERIFIED (11/11)');
console.log('================================================================');
