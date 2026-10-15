import assert from 'assert';
import { INITIAL_USERS, INITIAL_STUDENT_RECORDS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_TRANSACTIONS, INITIAL_LOGS } from '../src/utils/seedData.ts';
import { normalizeId, checkUserIdentityConflict } from '../src/utils/userValidation.ts';
import { splitFullName } from '../src/utils/nameUtils.ts';

console.log('================================================================');
console.log('TEST SUITE: STUDENT WITHOUT ACCOUNT WORKFLOW & SECURITY AUDIT');
console.log('================================================================\n');

// -------------------------------------------------------------
// Setup in-memory store simulation matching useAppStore.ts logic
// -------------------------------------------------------------
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let studentRecords = JSON.parse(JSON.stringify(INITIAL_STUDENT_RECORDS));
let events = JSON.parse(JSON.stringify(INITIAL_EVENTS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let transactions = JSON.parse(JSON.stringify(INITIAL_TRANSACTIONS));
let logs = JSON.parse(JSON.stringify(INITIAL_LOGS));
let currentUser = null;

const saveStudentRecord = (data) => {
  const cleanStudentId = normalizeId(data.studentId);
  const existingIdx = studentRecords.findIndex(
    r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
  );
  const parsedName = splitFullName(data.name.trim());

  if (existingIdx !== -1) {
    const existing = studentRecords[existingIdx];
    const updated = {
      ...existing,
      name: data.name.trim() || existing.name,
      firstName: parsedName.firstName || existing.firstName,
      middleName: parsedName.middleName || existing.middleName,
      lastName: parsedName.lastName || existing.lastName,
      course: data.course?.trim() || existing.course,
      yearLevel: data.yearLevel?.trim() || existing.yearLevel,
      section: data.section?.trim() || existing.section,
      department: data.department?.trim() || existing.department,
      photoUrl: data.photoUrl || existing.photoUrl,
      userId: data.userId || existing.userId,
    };
    studentRecords[existingIdx] = updated;
    return updated;
  }

  const newRecord = {
    id: `rec-${cleanStudentId}`,
    studentId: cleanStudentId,
    name: data.name.trim(),
    firstName: parsedName.firstName,
    middleName: parsedName.middleName,
    lastName: parsedName.lastName,
    course: data.course?.trim() || 'BSIT',
    yearLevel: data.yearLevel?.trim() || '3rd Year',
    section: data.section?.trim() || 'M1',
    department: data.department?.trim() || `College of ${data.course?.trim() || 'Computer Studies'}`,
    photoUrl: data.photoUrl,
    createdAt: new Date().toISOString(),
    userId: data.userId,
  };
  studentRecords.unshift(newRecord);
  return newRecord;
};

const findUserByIdentifier = (identifier) => {
  const trimmed = identifier.trim().toLowerCase();
  if (!trimmed) return undefined;
  return users.find(u => {
    const studentIdMatch = u.role === 'student' && u.studentId && normalizeId(u.studentId).toLowerCase() === trimmed;
    const employeeIdMatch = u.role !== 'student' && u.employeeId && normalizeId(u.employeeId).toLowerCase() === trimmed;
    return studentIdMatch || employeeIdMatch;
  });
};

const login = (identifier, password) => {
  const trimmed = identifier.trim().toLowerCase();
  const cleanPass = password.trim();
  const foundUser = findUserByIdentifier(trimmed);
  if (!foundUser) {
    return { success: false, message: 'No account found with this Student/Employee ID.' };
  }
  if (foundUser.isActive === false) {
    return { success: false, message: 'This account has been deactivated.' };
  }
  const expectedPassword = (foundUser.password || 'password123').trim();
  if (cleanPass !== expectedPassword) {
    return { success: false, message: 'Invalid password.' };
  }
  currentUser = foundUser;
  return { success: true, message: `Welcome back, ${foundUser.name}!`, user: foundUser };
};

const purchaseTicket = (eventId, photoUrl, userId, cashierStaffName, studentOverride, customPrice) => {
  const targetEvent = events.find(e => e.id === eventId) || events[0];
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER' || (!studentOverride?.studentId && !userId);
  const calculatedAttendeeType = isOutsider ? 'OUTSIDER' : 'STUDENT';

  const studentName = (studentOverride?.name || (userId ? users.find(u => u.id === userId)?.name : '') || 'Registered Student').trim();
  const studentId = isOutsider ? undefined : (studentOverride?.studentId || (userId ? users.find(u => u.id === userId)?.studentId : undefined));
  const course = isOutsider ? undefined : (studentOverride?.course || 'BSIT');
  const yearLevel = isOutsider ? undefined : (studentOverride?.yearLevel || '3rd Year');
  const section = isOutsider ? undefined : (studentOverride?.section || 'M1');
  const department = isOutsider ? 'General Public / Guest' : (studentOverride?.department || 'College of Computer Studies');

  let studentRec = null;
  let existingUserAccount = null;

  if (!isOutsider && studentId) {
    const cleanStudentId = normalizeId(studentId);
    studentRec = saveStudentRecord({
      name: studentName,
      studentId: cleanStudentId,
      course: course || 'BSIT',
      yearLevel: yearLevel || '3rd Year',
      section: section || 'M1',
      department,
    });
    existingUserAccount = users.find(u => u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()) || null;
    if (existingUserAccount) {
      studentRec.userId = existingUserAccount.id;
    }
  } else if (userId) {
    existingUserAccount = users.find(u => u.id === userId) || null;
  }

  const ownerId = existingUserAccount ? existingUserAccount.id : (studentRec ? studentRec.id : (isOutsider ? (studentOverride?.studentId || `usr-guest-${Date.now()}`) : `rec-${Date.now()}`));
  const price = targetEvent ? (targetEvent.ticketPrice ?? 0) : 0;

  const parsedName = splitFullName(studentName);
  const ticketSeq = Math.floor(10000 + tickets.length + 1);
  const newTicketNumber = isOutsider ? `TIX-O-${ticketSeq}` : `TIX-${ticketSeq}`;

  const newTicket = {
    id: newTicketNumber,
    ticketNumber: newTicketNumber,
    qrToken: newTicketNumber,
    eventId: targetEvent ? targetEvent.id : eventId,
    eventTitle: targetEvent ? targetEvent.title : 'Event Pass',
    ticketType: isOutsider ? 'physical' : 'digital',
    attendeeType: calculatedAttendeeType,
    originalOwnerId: ownerId,
    originalOwnerName: studentName,
    currentAttendeeId: ownerId,
    currentAttendeeName: studentName,
    firstName: existingUserAccount?.firstName || studentRec?.firstName || parsedName.firstName,
    middleName: existingUserAccount?.middleName || studentRec?.middleName || parsedName.middleName,
    lastName: existingUserAccount?.lastName || studentRec?.lastName || parsedName.lastName,
    attendeeStudentId: isOutsider ? undefined : (studentId ? normalizeId(studentId) : undefined),
    attendeeDepartment: department,
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
  };

  tickets.unshift(newTicket);
  return { success: true, message: 'Ticket generated successfully', ticket: newTicket };
};

const register = (data) => {
  const cleanStudentId = normalizeId(data.studentId);
  const assignedRole = 'student';

  let matchedStudentRecord = studentRecords.find(
    r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
  );

  const parsedRegName = splitFullName(data.name.trim());
  const finalCourse = data.course?.trim() || matchedStudentRecord?.course || 'BSIT';
  const finalYear = data.yearLevel?.trim() || matchedStudentRecord?.yearLevel || '3rd Year';
  const finalSection = data.section?.trim() || matchedStudentRecord?.section || 'A';
  const finalDept = data.department?.trim() || matchedStudentRecord?.department || `College of ${finalCourse}`;

  const newUser = {
    id: `usr-student-${Date.now().toString().slice(-6)}`,
    name: data.name.trim(),
    firstName: parsedRegName.firstName || matchedStudentRecord?.firstName,
    middleName: parsedRegName.middleName || matchedStudentRecord?.middleName,
    lastName: parsedRegName.lastName || matchedStudentRecord?.lastName,
    role: 'student',
    password: data.password,
    studentId: cleanStudentId,
    department: finalDept,
    course: finalCourse,
    yearLevel: finalYear,
    section: finalSection,
    isActive: true,
    createdAt: new Date().toISOString(),
  };

  if (matchedStudentRecord) {
    matchedStudentRecord.userId = newUser.id;
  }

  // Bind existing tickets to this newly registered account
  tickets = tickets.map(t => {
    if (t.attendeeStudentId && normalizeId(t.attendeeStudentId).toLowerCase() === cleanStudentId.toLowerCase()) {
      return {
        ...t,
        currentAttendeeId: newUser.id,
        originalOwnerId: (t.originalOwnerId && (t.originalOwnerId.startsWith('rec-') || t.originalOwnerId.startsWith('usr-guest-') || t.originalOwnerId === t.attendeeStudentId))
          ? newUser.id
          : (t.originalOwnerId || newUser.id),
        firstName: newUser.firstName || t.firstName,
        lastName: newUser.lastName || t.lastName,
      };
    }
    return t;
  });

  users = [newUser, ...users];
  currentUser = newUser;
  return { success: true, message: 'Account registered', user: newUser };
};

const getStudentDashboardTickets = (authenticatedUser) => {
  if (!authenticatedUser || authenticatedUser.role !== 'student') return [];
  const currentId = authenticatedUser.id;
  const currentStudentId = authenticatedUser.studentId?.trim().toLowerCase();
  return tickets.filter(t => {
    if (t.currentAttendeeId === currentId || t.originalOwnerId === currentId) return true;
    if (currentStudentId && t.attendeeStudentId && t.attendeeStudentId.trim().toLowerCase() === currentStudentId) return true;
    return false;
  });
};

const scanTicket = (qrToken) => {
  const ticket = tickets.find(t => t.qrToken === qrToken || t.ticketNumber === qrToken || t.id === qrToken);
  if (!ticket) return { success: false, message: 'Ticket not found.' };
  if (ticket.ticketStatus !== 'ISSUED' && ticket.ticketStatus !== 'active') {
    return { success: false, message: `Ticket is ${ticket.ticketStatus}.` };
  }
  ticket.attendanceStatus = 'present';
  ticket.ticketStatus = 'USED';
  return { success: true, message: 'Valid ticket. Entry granted!', ticket };
};

// ====================================================================
// TEST A — BEFORE ACCOUNT EXISTS
// ====================================================================
console.log('▶ TEST A: Verify Student without login account buys ticket at Cashier');

// 1. Authoritative Student Record exists in NCST Roster
const studentRecord = studentRecords.find(r => r.studentId === '2026-00129');
assert.ok(studentRecord, 'StudentRecord 2026-00129 must exist in authoritative roster');
console.log(`  ✔ Found authoritative Student Record: ${studentRecord.name} (${studentRecord.studentId})`);

// 2. Verify NO user account exists for 2026-00129 in INITIAL_USERS / users
const existingAccount = users.find(u => u.studentId === '2026-00129');
assert.strictEqual(existingAccount, undefined, 'Must NOT have a pre-existing user account for 2026-00129');
console.log('  ✔ Confirmed: No user account exists for 2026-00129');

// 3. Verify login attempt before registration FAILS
const loginAttempt = login('2026-00129', 'password123');
assert.strictEqual(loginAttempt.success, false, 'Login must fail because student is unregistered');
console.log(`  ✔ Login rejected as expected: "${loginAttempt.message}"`);

// 4. Cashier issues ticket using Student ID
const initialUserCount = users.length;
const initialTicketCount = tickets.length;
const cashierResult = purchaseTicket('evt-juans-2026', undefined, undefined, 'Cashier Desk', {
  studentId: '2026-00129',
  name: studentRecord.name,
  course: studentRecord.course,
  yearLevel: studentRecord.yearLevel,
  section: studentRecord.section,
});

assert.strictEqual(cashierResult.success, true, 'Cashier issuance must succeed');
const issuedTicket = cashierResult.ticket;
console.log(`  ✔ Cashier issued ticket: #${issuedTicket.ticketNumber} (QR Token: ${issuedTicket.qrToken})`);

// 5. Verify NO new user account or password was created
assert.strictEqual(users.length, initialUserCount, 'User collection count must NOT change');
assert.strictEqual(users.find(u => u.studentId === '2026-00129'), undefined, 'No user account created in users table');
console.log('  ✔ Confirmed: Zero user accounts created during cashier issuance');

// 6. Verify Ticket details
assert.strictEqual(issuedTicket.attendeeStudentId, '2026-00129');
assert.strictEqual(issuedTicket.ticketStatus, 'ISSUED');
assert.ok(issuedTicket.qrToken && issuedTicket.qrToken.length > 0, 'QR Token must be generated');
assert.strictEqual(issuedTicket.currentAttendeeId, studentRecord.id, 'Ticket linked to StudentRecord id (rec-2026-00129)');
console.log(`  ✔ Ticket linked to Student Record (${issuedTicket.currentAttendeeId}), not a fake user`);

// 7. Event Staff Scanner validation works with physical ticket / QR
const scanResult = scanTicket(issuedTicket.qrToken);
assert.strictEqual(scanResult.success, true, 'Event scanner successfully verified QR token');
console.log(`  ✔ Event Staff scanner verified pass: "${scanResult.message}"`);

// Reset ticket status to ISSUED for Test B
issuedTicket.ticketStatus = 'ISSUED';
issuedTicket.attendanceStatus = 'not_entered';
console.log('  ✔ TEST A PASSED\n');

// ====================================================================
// TEST B — STUDENT REGISTERS LATER
// ====================================================================
console.log('▶ TEST B: Student self-registers later on the portal');

const ticketsBeforeRegCount = tickets.length;
const originalTicketNumber = issuedTicket.ticketNumber;
const originalQrToken = issuedTicket.qrToken;

const regResult = register({
  name: 'Christian Bautista',
  studentId: '2026-00129',
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: 'Section A',
  password: 'MyStrongPassword2026!',
});

assert.strictEqual(regResult.success, true, 'Registration must succeed');
const newRegisteredUser = regResult.user;
assert.ok(newRegisteredUser.id.startsWith('usr-student-'), 'New user ID generated');
assert.strictEqual(users.length, initialUserCount + 1, 'Exactly one user account created during registration');
console.log(`  ✔ Student account created: ID ${newRegisteredUser.id}, Name: ${newRegisteredUser.name}`);

// Verify Student Record linked
const updatedRecord = studentRecords.find(r => r.studentId === '2026-00129');
assert.strictEqual(updatedRecord.userId, newRegisteredUser.id, 'StudentRecord now points to new user ID');
console.log(`  ✔ StudentRecord linked: record.userId = ${updatedRecord.userId}`);

// Verify Existing Ticket was bound without creating duplicate or changing QR
assert.strictEqual(tickets.length, ticketsBeforeRegCount, 'No duplicate tickets created');
const studentTickets = getStudentDashboardTickets(newRegisteredUser);
assert.strictEqual(studentTickets.length, 1, 'Student sees exactly 1 ticket in their portal');
assert.strictEqual(studentTickets[0].ticketNumber, originalTicketNumber, 'Same ticket number retained');
assert.strictEqual(studentTickets[0].qrToken, originalQrToken, 'Same QR token retained');
assert.strictEqual(studentTickets[0].currentAttendeeId, newRegisteredUser.id, 'Ticket ownership updated to user ID');
console.log(`  ✔ Digital Pass available on portal: #${studentTickets[0].ticketNumber} with intact QR (${studentTickets[0].qrToken})`);

// Verify login with new password works
const newLogin = login('2026-00129', 'MyStrongPassword2026!');
assert.strictEqual(newLogin.success, true, 'Student can log in with their registered password');
console.log(`  ✔ Login with new password succeeded: "${newLogin.message}"`);
console.log('  ✔ TEST B PASSED\n');

// ====================================================================
// TEST C — SECURITY & ACCESS CONTROL
// ====================================================================
console.log('▶ TEST C: Security & Ticket Isolation');

// Other student user (e.g. Juan Dela Cruz 2026-00125)
const juanUser = users.find(u => u.studentId === '2026-00125');
assert.ok(juanUser, 'Juan user exists');

const juanTickets = getStudentDashboardTickets(juanUser);
const christianTickets = getStudentDashboardTickets(newRegisteredUser);

// Verify Juan cannot see Christian's tickets and Christian cannot see Juan's tickets
const juanHasChristianTicket = juanTickets.some(t => t.attendeeStudentId === '2026-00129');
const christianHasJuanTicket = christianTickets.some(t => t.attendeeStudentId === '2026-00125');

assert.strictEqual(juanHasChristianTicket, false, 'Juan MUST NOT see Christian\'s ticket');
assert.strictEqual(christianHasJuanTicket, false, 'Christian MUST NOT see Juan\'s ticket');
console.log('  ✔ Confirmed: Tickets are strictly isolated per authenticated student');

// Verify unauthenticated user gets empty tickets
const unauthTickets = getStudentDashboardTickets(null);
assert.strictEqual(unauthTickets.length, 0, 'Unauthenticated user gets 0 tickets');
console.log('  ✔ Confirmed: Unauthenticated queries return 0 tickets');
console.log('  ✔ TEST C PASSED\n');

console.log('================================================================');
console.log('ALL WORKFLOW & SECURITY TESTS PASSED PERFECTLY!');
console.log('================================================================');
