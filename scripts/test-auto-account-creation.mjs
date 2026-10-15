import assert from 'assert';
import { INITIAL_USERS, INITIAL_STUDENT_RECORDS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_TRANSACTIONS, INITIAL_LOGS } from '../src/utils/seedData.ts';
import { normalizeId } from '../src/utils/userValidation.ts';
import { splitFullName } from '../src/utils/nameUtils.ts';

console.log('================================================================');
console.log('NCST AUTOMATIC STUDENT ACCOUNT PROVISIONING & SECURITY AUDIT');
console.log('================================================================\n');

// Mock Store Environment simulating useAppStore.ts
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let studentRecords = JSON.parse(JSON.stringify(INITIAL_STUDENT_RECORDS));
let events = JSON.parse(JSON.stringify(INITIAL_EVENTS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let transactions = JSON.parse(JSON.stringify(INITIAL_TRANSACTIONS));
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
    section: data.section?.trim() || '31M1',
    department: data.department?.trim() || `College of ${data.course?.trim() || 'Computer Studies'}`,
    photoUrl: data.photoUrl,
    createdAt: new Date().toISOString(),
    userId: data.userId,
  };
  studentRecords.unshift(newRecord);
  return newRecord;
};

const purchaseTicket = (eventId, photoUrl, userId, cashierStaffName, studentOverride, customPrice) => {
  const targetEvent = events.find(e => e.id === eventId) || events[0];
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER' || (!studentOverride?.studentId && !userId);
  const calculatedAttendeeType = isOutsider ? 'OUTSIDER' : 'STUDENT';

  const studentName = (studentOverride?.name || (userId ? users.find(u => u.id === userId)?.name : '') || 'Registered Student').trim();
  const studentId = isOutsider ? undefined : (studentOverride?.studentId || (userId ? users.find(u => u.id === userId)?.studentId : undefined));
  const course = isOutsider ? undefined : (studentOverride?.course || 'BSIT');
  const yearLevel = isOutsider ? undefined : (studentOverride?.yearLevel || '3rd Year');
  const section = isOutsider ? undefined : (studentOverride?.section || '31M1');
  const department = isOutsider ? 'General Public / Guest' : (studentOverride?.department || 'College of Computer Studies');

  let studentRec = null;
  let existingUserAccount = null;
  let accountCreated = false;
  let temporaryCredentials = undefined;

  if (!isOutsider && studentId) {
    const cleanStudentId = normalizeId(studentId);
    
    // Look up authoritative record first
    const existingRosterRecord = studentRecords.find(
      r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    );

    if (existingRosterRecord) {
      studentRec = existingRosterRecord;
    } else {
      studentRec = saveStudentRecord({
        name: studentName,
        studentId: cleanStudentId,
        course: course || 'BSIT',
        yearLevel: yearLevel || '3rd Year',
        section: section || '31M1',
        department,
      });
    }

    // Check if this student already happens to have a registered User account
    existingUserAccount = users.find(
      u => u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    ) || null;

    if (!existingUserAccount) {
      // Automatically create ONE Student login account using authoritative StudentRecord!
      const parsedName = splitFullName(studentRec.name.trim());
      const tempPassword = 'ncst123';

      const newUser = {
        id: `usr-student-${Date.now().toString().slice(-6)}`,
        name: studentRec.name,
        firstName: studentRec.firstName || parsedName.firstName,
        middleName: studentRec.middleName || parsedName.middleName,
        lastName: studentRec.lastName || parsedName.lastName,
        role: 'student',
        password: tempPassword,
        studentId: cleanStudentId,
        department: studentRec.department || department,
        course: studentRec.course || 'BSIT',
        yearLevel: studentRec.yearLevel || '3rd Year',
        section: studentRec.section || '31M1',
        isActive: true,
        mustChangePassword: true,
        temporaryPassword: tempPassword,
        createdAt: new Date().toISOString(),
      };

      users.unshift(newUser);
      studentRec.userId = newUser.id;
      existingUserAccount = newUser;
      accountCreated = true;
      temporaryCredentials = {
        studentId: cleanStudentId,
        tempPassword,
      };
    } else {
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
    originalOwnerName: studentRec?.name || studentName,
    currentAttendeeId: ownerId,
    currentAttendeeName: studentRec?.name || studentName,
    firstName: existingUserAccount?.firstName || studentRec?.firstName || parsedName.firstName,
    middleName: existingUserAccount?.middleName || studentRec?.middleName || parsedName.middleName,
    lastName: existingUserAccount?.lastName || studentRec?.lastName || parsedName.lastName,
    attendeeStudentId: isOutsider ? undefined : (studentId ? normalizeId(studentId) : undefined),
    attendeeDepartment: studentRec?.department || department,
    course: studentRec?.course || course,
    yearLevel: studentRec?.yearLevel || yearLevel,
    section: studentRec?.section || section,
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
  return {
    success: true,
    message: `Ticket #${newTicketNumber} generated successfully for ${studentRec?.name || studentName}!`,
    ticket: newTicket,
    accountCreated,
    temporaryCredentials,
  };
};

const login = (identifier, password) => {
  const trimmed = identifier.trim().toLowerCase();
  const cleanPass = password.trim();
  const foundUser = users.find(u => {
    const studentIdMatch = u.role === 'student' && u.studentId && normalizeId(u.studentId).toLowerCase() === trimmed;
    return studentIdMatch;
  });

  if (!foundUser) {
    return { success: false, message: 'No account found with this credential.' };
  }
  if (cleanPass !== foundUser.password) {
    return { success: false, message: 'Invalid password.' };
  }
  currentUser = foundUser;
  return { success: true, message: `Welcome, ${foundUser.name}!`, user: foundUser, mustChangePassword: foundUser.mustChangePassword };
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

// ====================================================================
// TEST A: Student with no account -> Cashier issues ticket
// ====================================================================
console.log('▶ TEST A: Student with no account -> Ticket issued & account auto-created');

const initialUsersCount = users.length;
const testStudentId = '2026-00129';
const studentRecA = studentRecords.find(r => r.studentId === testStudentId);
assert.ok(studentRecA, 'StudentRecord 2026-00129 must exist');
assert.strictEqual(users.find(u => u.studentId === testStudentId), undefined, 'No user account exists prior to purchase');

// Cashier issues ticket
const resA = purchaseTicket('evt-juans-2026', undefined, undefined, 'Cashier Desk', {
  studentId: testStudentId,
  name: studentRecA.name,
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
});

assert.strictEqual(resA.success, true, 'Issuance must succeed');
assert.strictEqual(resA.accountCreated, true, 'accountCreated flag must be true');
assert.ok(resA.temporaryCredentials, 'Temporary credentials must be generated');
assert.strictEqual(resA.temporaryCredentials.studentId, testStudentId);
assert.strictEqual(resA.temporaryCredentials.tempPassword, 'ncst123', 'Initial password is ncst123');
assert.strictEqual(users.length, initialUsersCount + 1, 'Exactly 1 new user account created');

const createdUserA = users.find(u => u.studentId === testStudentId);
assert.ok(createdUserA, 'Created user exists in users table');
assert.strictEqual(createdUserA.name, studentRecA.name, 'Name matches authoritative record');
assert.strictEqual(createdUserA.role, 'student');
assert.strictEqual(createdUserA.mustChangePassword, true, 'mustChangePassword flagged');
assert.strictEqual(resA.ticket.currentAttendeeId, createdUserA.id, 'Ticket linked to new user ID');

// Verify student can immediately login with initial temporary password ncst123
const loginA = login(testStudentId, 'ncst123');
assert.strictEqual(loginA.success, true, 'Student logs in with initial password ncst123');
assert.strictEqual(loginA.mustChangePassword, true, 'Login informs student to change password');

// Verify Digital Ticket is in student dashboard
const ticketsA = getStudentDashboardTickets(createdUserA);
assert.strictEqual(ticketsA.length, 1, 'Student sees their newly issued digital ticket');
assert.strictEqual(ticketsA[0].ticketNumber, resA.ticket.ticketNumber, 'Ticket number retained');
assert.strictEqual(ticketsA[0].qrToken, resA.ticket.qrToken, 'QR token retained');
console.log('  ✔ TEST A PASSED\n');

// ====================================================================
// TEST B: Same student buys another ticket later -> Existing account reused
// ====================================================================
console.log('▶ TEST B: Same student buys another ticket later -> Existing account reused');

const usersCountBeforeB = users.length;
const resB = purchaseTicket('evt-fnd-2026', undefined, undefined, 'Cashier Desk', {
  studentId: testStudentId,
  name: studentRecA.name,
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
});

assert.strictEqual(resB.success, true);
assert.strictEqual(resB.accountCreated, false, 'Should NOT create another account');
assert.strictEqual(users.length, usersCountBeforeB, 'User count MUST NOT increase');
assert.strictEqual(resB.ticket.currentAttendeeId, createdUserA.id, 'New ticket linked to same user account');

const ticketsB = getStudentDashboardTickets(createdUserA);
assert.strictEqual(ticketsB.length, 2, 'Student now sees both tickets in portal');
console.log('  ✔ TEST B PASSED\n');

// ====================================================================
// TEST C: Student who already has an account -> Ticket issued without duplicate account
// ====================================================================
console.log('▶ TEST C: Existing student account -> No duplicate account created');

const existingStudentId = '2026-00125'; // Juan Dela Cruz already has usr-stud-1
const existingUser = users.find(u => u.studentId === existingStudentId);
assert.ok(existingUser, 'Juan Dela Cruz exists in users');

const usersCountBeforeC = users.length;
const resC = purchaseTicket('evt-fnd-2026', undefined, undefined, 'Cashier Desk', {
  studentId: existingStudentId,
  name: existingUser.name,
  course: existingUser.course,
  yearLevel: existingUser.yearLevel,
  section: existingUser.section,
});

assert.strictEqual(resC.success, true);
assert.strictEqual(resC.accountCreated, false, 'Account must NOT be created for existing student');
assert.strictEqual(users.length, usersCountBeforeC, 'Users count must remain identical');
assert.strictEqual(resC.ticket.currentAttendeeId, existingUser.id, 'Ticket attached to Juan Dela Cruz');
console.log('  ✔ TEST C PASSED\n');

// ====================================================================
// TEST D: Outsider / Guest Ticket -> Zero login accounts created
// ====================================================================
console.log('▶ TEST D: Outsider / Guest Ticket -> Zero login accounts created');

const usersCountBeforeD = users.length;
const resD = purchaseTicket('evt-juans-2026', undefined, undefined, 'Cashier Desk', {
  name: 'John Guest Doe',
  attendeeType: 'OUTSIDER',
  contactNumber: '09171234567',
});

assert.strictEqual(resD.success, true);
assert.strictEqual(resD.accountCreated, false, 'Must NOT create account for guest');
assert.strictEqual(users.length, usersCountBeforeD, 'Users count must remain identical');
assert.strictEqual(resD.ticket.attendeeType, 'OUTSIDER');
console.log('  ✔ TEST D PASSED\n');

// ====================================================================
// TEST E: Security & Tenant Isolation
// ====================================================================
console.log('▶ TEST E: Security & Access Control');

// Verify Juan Dela Cruz (2026-00125) cannot see Christian Bautista's (2026-00129) tickets
const juanTickets = getStudentDashboardTickets(existingUser);
const christianTickets = getStudentDashboardTickets(createdUserA);

const juanHasChristian = juanTickets.some(t => t.attendeeStudentId === testStudentId);
const christianHasJuan = christianTickets.some(t => t.attendeeStudentId === existingStudentId);

assert.strictEqual(juanHasChristian, false, 'Juan cannot view Christian tickets');
assert.strictEqual(christianHasJuan, false, 'Christian cannot view Juan tickets');

// Tampering simulation: Unauthenticated access returns 0 tickets
assert.strictEqual(getStudentDashboardTickets(null).length, 0, 'Unauthenticated query returns 0');
assert.strictEqual(getStudentDashboardTickets({ role: 'teacher', id: 'usr-teach-1' }).length, 0, 'Non-student role returns 0');

console.log('  ✔ TEST E PASSED\n');

console.log('================================================================');
console.log('ALL AUTO-PROVISIONING & SECURITY TESTS PASSED PERFECTLY!');
console.log('================================================================');
