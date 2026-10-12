// test-surname-password-onboarding.mjs
// NCST Event & Ticketing System: Automatic Student Account Creation with Temporary Surname Password

import assert from 'assert';
import { splitFullName } from '../src/utils/nameUtils.ts';

console.log('🧪 Starting Automatic Student Account Creation with Temporary Surname Password Test Suite...\n');

// Mock in-memory state simulating the composable store
let users = [
  {
    id: 'usr-admin-1',
    name: 'Engr. Mark Santos',
    email: 'admin.santos@school.edu.ph',
    role: 'admin',
    employeeId: 'EMP-00101',
    password: 'password123',
    isActive: true,
  },
  {
    id: 'usr-stud-1',
    name: 'Juan Dela Cruz',
    email: 'juan.delacruz@student.edu.ph',
    role: 'student',
    studentId: '2026-00125',
    password: 'CustomStudentPass99!',
    mustChangePassword: false,
    isActive: true,
  },
];

let studentRecords = [
  {
    id: 'rec-2026-00125',
    studentId: '2026-00125',
    name: 'Juan Dela Cruz',
    firstName: 'Juan',
    lastName: 'Dela Cruz',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    department: 'College of Computer Studies',
    email: 'juan.delacruz@student.edu.ph',
    userId: 'usr-stud-1',
  },
  {
    id: 'rec-2026-00129',
    studentId: '2026-00129',
    name: 'Chloe Mae Hernandez',
    firstName: 'Chloe Mae',
    lastName: 'Hernandez',
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: '21M2',
    department: 'College of Computer Studies',
    email: 'chloe.hernandez@student.edu.ph',
  },
  {
    id: 'rec-2026-00130',
    studentId: '2026-00130',
    name: 'Francis De Los Santos',
    firstName: 'Francis',
    lastName: 'De Los Santos',
    course: 'BSIT',
    yearLevel: '1st Year',
    section: '11M1',
    department: 'College of Computer Studies',
    email: 'francis.delossantos@student.edu.ph',
  }
];

let tickets = [];

function normalizeId(id) {
  return (id || '').trim();
}

function purchaseTicket(eventId, studentId, studentOverride) {
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER';
  let studentRec = null;
  let existingUserAccount = null;
  let accountCreated = false;

  if (!isOutsider && studentId) {
    const cleanStudentId = normalizeId(studentId);
    studentRec = studentRecords.find(r => r.studentId.toLowerCase() === cleanStudentId.toLowerCase());

    if (!studentRec) {
      studentRec = {
        id: `rec-${Date.now()}`,
        studentId: cleanStudentId,
        name: studentOverride.name,
        lastName: splitFullName(studentOverride.name).lastName,
        course: studentOverride.course || 'BSIT',
        yearLevel: studentOverride.yearLevel || '1st Year',
        section: studentOverride.section || '11M1',
        email: studentOverride.email,
      };
      studentRecords.push(studentRec);
    }

    existingUserAccount = users.find(u => u.studentId && u.studentId.toLowerCase() === cleanStudentId.toLowerCase());

    if (!existingUserAccount) {
      // Automatically create ONE Student login account with initial temporary password set to official surname
      const parsedName = splitFullName(studentRec.name);
      const cleanEmail = studentRec.email || studentOverride?.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`;
      const officialSurname = (studentRec.lastName || parsedName.lastName || 'Student').trim();
      const initialPassword = officialSurname;

      const newUser = {
        id: `usr-student-${Date.now().toString().slice(-6)}`,
        name: studentRec.name,
        firstName: studentRec.firstName || parsedName.firstName,
        middleName: studentRec.middleName || parsedName.middleName,
        lastName: studentRec.lastName || parsedName.lastName,
        email: cleanEmail,
        role: 'student',
        password: initialPassword,
        studentId: cleanStudentId,
        course: studentRec.course || 'BSIT',
        yearLevel: studentRec.yearLevel || '1st Year',
        section: studentRec.section || '11M1',
        isActive: true,
        accountStatus: 'active',
        mustChangePassword: true,
        createdAt: new Date().toISOString(),
      };

      users.unshift(newUser);
      studentRec.userId = newUser.id;
      existingUserAccount = newUser;
      accountCreated = true;
    } else {
      // Strictly preserve existing credentials
      studentRec.userId = existingUserAccount.id;
    }
  }

  const ownerId = existingUserAccount ? existingUserAccount.id : (studentRec ? studentRec.id : `guest-${Date.now()}`);
  const ticketSeq = 10000 + tickets.length + 1;
  const newTicketNumber = isOutsider ? `TIX-O-${ticketSeq}` : `TIX-${ticketSeq}`;

  const newTicket = {
    id: newTicketNumber,
    ticketNumber: newTicketNumber,
    qrToken: newTicketNumber,
    eventId,
    attendeeType: isOutsider ? 'OUTSIDER' : 'STUDENT',
    currentAttendeeId: ownerId,
    originalOwnerId: ownerId,
    currentAttendeeName: studentOverride.name,
    attendeeStudentId: isOutsider ? undefined : studentId,
    ticketStatus: 'active',
    attendanceStatus: 'not_entered',
    academicYear: '2026-2027',
    semester: '1st Semester',
  };

  tickets.push(newTicket);
  return { success: true, ticket: newTicket, accountCreated };
}

function login(identifier, password) {
  const trimmed = identifier.trim().toLowerCase();
  const user = users.find(u =>
    (u.email && u.email.toLowerCase() === trimmed) ||
    (u.role === 'student' && u.studentId && u.studentId.toLowerCase() === trimmed) ||
    (u.employeeId && u.employeeId.toLowerCase() === trimmed)
  );

  if (!user) return { success: false, message: 'User not found' };

  const userSurname = (user.lastName || splitFullName(user.name).lastName || '').trim();
  const passMatches = user.password === password ||
    (user.mustChangePassword && (user.password.toLowerCase() === password.toLowerCase() || userSurname.toLowerCase() === password.toLowerCase()));

  if (!passMatches) return { success: false, message: 'Invalid password' };

  return { success: true, user };
}

function updateUserPassword(userId, newPassword) {
  const user = users.find(u => u.id === userId);
  if (!user) return { success: false, message: 'User not found' };

  const userSurname = (user.lastName || splitFullName(user.name).lastName || '').trim();
  if (userSurname && newPassword.trim().toLowerCase() === userSurname.toLowerCase()) {
    return { success: false, message: 'New password cannot be your surname. Please choose a secure custom password.' };
  }

  if (newPassword.length < 4) {
    return { success: false, message: 'Password must be at least 4 characters long.' };
  }

  user.password = newPassword;
  user.mustChangePassword = false;
  return { success: true, user };
}

// ==================== TEST 1: Unregistered Student Buys First Ticket (Surname Password) ====================
console.log('--- TEST 1: Unregistered Student Buys Ticket & Auto-Provision Account ---');
const purchaseRes = purchaseTicket('evt-juans-2026', '2026-00129', {
  name: 'Chloe Mae Hernandez',
  course: 'BSCS',
  yearLevel: '2nd Year',
  section: '21M2',
  email: 'chloe.hernandez@student.edu.ph',
  attendeeType: 'STUDENT',
});

assert.strictEqual(purchaseRes.success, true);
assert.strictEqual(purchaseRes.accountCreated, true);

const chloeUser = users.find(u => u.studentId === '2026-00129');
assert.ok(chloeUser, 'User account must be automatically created');
assert.strictEqual(chloeUser.password, 'Hernandez', 'Initial temporary password must be student official surname');
assert.strictEqual(chloeUser.mustChangePassword, true, 'mustChangePassword flag must be set to true');
assert.strictEqual(chloeUser.accountStatus, 'active');
console.log('✅ First purchase creates active account with initial surname password "Hernandez".\n');

// ==================== TEST 2: Multi-Part Surname Student Account Creation ====================
console.log('--- TEST 2: Multi-Part Surname Student Account Creation ("De Los Santos") ---');
const purchaseRes2 = purchaseTicket('evt-juans-2026', '2026-00130', {
  name: 'Francis De Los Santos',
  course: 'BSIT',
  yearLevel: '1st Year',
  section: '11M1',
  email: 'francis.delossantos@student.edu.ph',
  attendeeType: 'STUDENT',
});

assert.strictEqual(purchaseRes2.success, true);
const francisUser = users.find(u => u.studentId === '2026-00130');
assert.ok(francisUser, 'Francis user account created');
assert.strictEqual(francisUser.password, 'De Los Santos', 'Multi-part surname "De Los Santos" assigned as temporary password');
assert.strictEqual(francisUser.mustChangePassword, true);
console.log('✅ Multi-part surname correctly recognized and assigned as temporary password.\n');

// ==================== TEST 3: Student Logs in with Official Surname ====================
console.log('--- TEST 3: Student Signs in with Official Surname Temporary Password ---');
// Exact case
const loginRes1 = login('2026-00129', 'Hernandez');
assert.strictEqual(loginRes1.success, true);
assert.strictEqual(loginRes1.user.mustChangePassword, true, 'mustChangePassword remains true before change');

// Case-insensitive case
const loginRes2 = login('2026-00129', 'hernandez');
assert.strictEqual(loginRes2.success, true, 'Case-insensitive surname login succeeds');

// Wrong password fails
const loginFail = login('2026-00129', 'WrongPass123');
assert.strictEqual(loginFail.success, false, 'Invalid credentials rejected');
console.log('✅ Student signs in using official surname in both exact and case-insensitive form.\n');

// ==================== TEST 4: Rejection of Surname as Permanent Password ====================
console.log('--- TEST 4: Mandatory Password Change Safeguards ---');
const attemptSurname = updateUserPassword(chloeUser.id, 'Hernandez');
assert.strictEqual(attemptSurname.success, false, 'Reusing surname as permanent password must be rejected');
assert.ok(attemptSurname.message.includes('surname'), 'Explains surname cannot be permanent password');

const attemptShort = updateUserPassword(chloeUser.id, 'abc');
assert.strictEqual(attemptShort.success, false, 'Short password (< 4 chars) rejected');
console.log('✅ Reusing surname and short passwords successfully blocked.\n');

// ==================== TEST 5: Successful Password Change & Invalidation ====================
console.log('--- TEST 5: Successful Permanent Password Setup & Invalidation ---');
const changeRes = updateUserPassword(chloeUser.id, 'MySecretPass2026!#');
assert.strictEqual(changeRes.success, true, 'Password change succeeds');
assert.strictEqual(chloeUser.password, 'MySecretPass2026!#');
assert.strictEqual(chloeUser.mustChangePassword, false, 'mustChangePassword cleared to false');

// Old surname no longer works
const oldSurnameLogin = login('2026-00129', 'Hernandez');
assert.strictEqual(oldSurnameLogin.success, false, 'Old temporary surname password no longer works');

// New permanent password works
const newPassLogin = login('2026-00129', 'MySecretPass2026!#');
assert.strictEqual(newPassLogin.success, true, 'New permanent password works');
assert.strictEqual(newPassLogin.user.mustChangePassword, false);
console.log('✅ Permanent password activated, old surname password invalidated.\n');

// ==================== TEST 6: Repeat Purchase for Existing Account ====================
console.log('--- TEST 6: Existing Student Buys Second Ticket (Credential Preservation) ---');
const passBefore = chloeUser.password;
const mustChangeBefore = chloeUser.mustChangePassword;

const purchaseRepeat = purchaseTicket('evt-fnd-2026', '2026-00129', {
  name: 'Chloe Mae Hernandez',
  attendeeType: 'STUDENT',
});

assert.strictEqual(purchaseRepeat.success, true);
assert.strictEqual(purchaseRepeat.accountCreated, false, 'No second account created');
assert.strictEqual(chloeUser.password, passBefore, 'Password is NOT reset to surname');
assert.strictEqual(chloeUser.mustChangePassword, mustChangeBefore, 'mustChangePassword remains false');

const matchingUsers = users.filter(u => u.studentId === '2026-00129');
assert.strictEqual(matchingUsers.length, 1, 'Exactly one user account exists; no duplicates');
console.log('✅ Existing account credentials strictly preserved, duplicate accounts prevented.\n');

// ==================== TEST 7: Outsider / Guest Ticket Purchase ====================
console.log('--- TEST 7: Outsider Ticket Purchase ---');
const userCountBefore = users.length;
const outsiderPurchase = purchaseTicket('evt-juans-2026', undefined, {
  name: 'Guest Visitor',
  attendeeType: 'OUTSIDER',
});

assert.strictEqual(outsiderPurchase.success, true);
assert.strictEqual(outsiderPurchase.accountCreated, false, 'No account created for outsider');
assert.strictEqual(users.length, userCountBefore, 'User count unchanged');
console.log('✅ Outsider purchase does not create account or assign temporary password.\n');

console.log('🎉 ALL 7 TEST CASES PASSED SUCCESSFULLY (100%)\n');
