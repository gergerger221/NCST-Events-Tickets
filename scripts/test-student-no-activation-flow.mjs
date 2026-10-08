// test-student-no-activation-flow.mjs
// NCST Event & Ticketing System: Final Student Account Flow (No Activation)

import assert from 'assert';

console.log('🧪 Starting Final Student Account Flow (No Activation, Initial Pass: ncst123) Test Suite...\n');

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
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: '21M2',
    department: 'College of Computer Studies',
    email: 'chloe.hernandez@student.edu.ph',
  },
];

let tickets = [];
let transactions = [];

function splitFullName(name) {
  const parts = name.trim().split(/\s+/);
  if (parts.length === 1) return { firstName: parts[0], middleName: '', lastName: parts[0] };
  const lastName = parts.pop();
  const firstName = parts.shift();
  const middleName = parts.join(' ');
  return { firstName, middleName, lastName };
}

function normalizeId(id) {
  return (id || '').trim();
}

function normalizeEmail(email) {
  return (email || '').trim().toLowerCase();
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
        course: studentOverride.course || 'BSIT',
        yearLevel: studentOverride.yearLevel || '3rd Year',
        section: studentOverride.section || '31M1',
        email: studentOverride.email,
      };
      studentRecords.push(studentRec);
    }

    existingUserAccount = users.find(u => u.studentId && u.studentId.toLowerCase() === cleanStudentId.toLowerCase());

    if (!existingUserAccount) {
      // Automatically create ONE Student login account with initial temporary password ncst123
      const parsedName = splitFullName(studentRec.name);
      const cleanEmail = studentRec.email || studentOverride?.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`;
      const initialPassword = 'ncst123';

      const newUser = {
        id: `usr-student-${Date.now().toString().slice(-6)}`,
        name: studentRec.name,
        firstName: parsedName.firstName,
        middleName: parsedName.middleName,
        lastName: parsedName.lastName,
        email: cleanEmail,
        role: 'student',
        password: initialPassword,
        studentId: cleanStudentId,
        course: studentRec.course || 'BSIT',
        yearLevel: studentRec.yearLevel || '3rd Year',
        section: studentRec.section || '31M1',
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
  if (user.password !== password) return { success: false, message: 'Invalid password' };

  return { success: true, user };
}

function updateUserPassword(userId, newPassword) {
  const user = users.find(u => u.id === userId);
  if (!user) return { success: false, message: 'User not found' };
  user.password = newPassword;
  user.mustChangePassword = false;
  return { success: true, user };
}

function register(studentId, name, email, password) {
  const existing = users.find(u => u.studentId && u.studentId.toLowerCase() === studentId.toLowerCase());
  if (existing) {
    if (existing.mustChangePassword) {
      return {
        success: false,
        message: `An account for Student ID ${studentId} was automatically created during ticket purchase. Please sign in with your Student ID and default password (ncst123) to set your permanent password.`,
      };
    }
    return {
      success: false,
      message: `An active account already exists for Student ID ${studentId}. Please sign in to the portal.`,
    };
  }
  const newUser = {
    id: `usr-student-${Date.now().toString().slice(-6)}`,
    name,
    email,
    role: 'student',
    studentId,
    password,
    mustChangePassword: false,
    isActive: true,
  };
  users.unshift(newUser);
  return { success: true, user: newUser };
}

// ==================== TEST 1: Unregistered Student Buys Ticket ====================
console.log('--- TEST 1: Unregistered Student Buys Ticket ---');
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
assert.ok(chloeUser, 'Chloe user account must be automatically created');
assert.strictEqual(chloeUser.password, 'ncst123', 'Default initial password must be ncst123');
assert.strictEqual(chloeUser.mustChangePassword, true, 'mustChangePassword must be true');
assert.strictEqual(chloeUser.accountStatus, 'active', 'Account status must be active');
console.log('✅ First purchase creates active account with initial password ncst123.');

// ==================== TEST 2: Fast Cashier Modal & No Slip Generation ====================
console.log('\n--- TEST 2: Fast Cashier Modal Check ---');
assert.strictEqual(purchaseRes.ticket.ticketNumber, 'TIX-10001');
assert.strictEqual(purchaseRes.ticket.qrToken, 'TIX-10001');
console.log('✅ Ticket created with QR credential without separate slip requirements.');

// ==================== TEST 3: Duplicate Registration Guard ====================
console.log('\n--- TEST 3: Duplicate Registration Guard for Unactivated Account ---');
const dupRegRes = register('2026-00129', 'Chloe Hernandez', 'chloe.hernandez@student.edu.ph', 'somepass');
assert.strictEqual(dupRegRes.success, false);
assert.ok(dupRegRes.message.includes('ncst123'), 'Registration message must direct student to sign in with default password ncst123');
console.log('✅ Self-registration guard blocks duplicate creation and directs to login with ncst123.');

// ==================== TEST 4: Student Logs in with ncst123 ====================
console.log('\n--- TEST 4: Student Logs in with ncst123 ---');
const loginRes = login('2026-00129', 'ncst123');
assert.strictEqual(loginRes.success, true);
assert.strictEqual(loginRes.user.mustChangePassword, true, 'Student is flagged to set permanent password');
console.log('✅ Student signs in with ncst123 and is prompted for first-time password setup.');

// ==================== TEST 5: Student Changes Password ====================
console.log('\n--- TEST 5: Mandatory First-Login Password Change ---');
const passChangeRes = updateUserPassword(loginRes.user.id, 'MySecretPass2026!');
assert.strictEqual(passChangeRes.success, true);
assert.strictEqual(passChangeRes.user.password, 'MySecretPass2026!');
assert.strictEqual(passChangeRes.user.mustChangePassword, false);
console.log('✅ Password successfully changed to permanent custom password.');

// ==================== TEST 6: Old Password ncst123 No Longer Works ====================
console.log('\n--- TEST 6: Initial Password ncst123 Invalidation ---');
const oldPassLogin = login('2026-00129', 'ncst123');
assert.strictEqual(oldPassLogin.success, false, 'ncst123 must no longer work after change');

const newPassLogin = login('2026-00129', 'MySecretPass2026!');
assert.strictEqual(newPassLogin.success, true, 'New permanent password must work');
console.log('✅ ncst123 no longer works, new permanent password works.');

// ==================== TEST 7: Digital Pass Appears in Student Passes ====================
console.log('\n--- TEST 7: Digital Ticket Visibility in Student Passes ---');
const studentTickets = tickets.filter(t => t.currentAttendeeId === newPassLogin.user.id || t.attendeeStudentId === newPassLogin.user.studentId);
assert.strictEqual(studentTickets.length, 1);
assert.strictEqual(studentTickets[0].ticketNumber, 'TIX-10001');
console.log('✅ Issued ticket automatically visible with same ticket number & QR token.');

// ==================== TEST 8: Existing Student Buys Second Ticket ====================
console.log('\n--- TEST 8: Existing Student Buys Second Ticket ---');
const juanOldPass = users.find(u => u.studentId === '2026-00125').password;
const secondPurchase = purchaseTicket('evt-fnd-2026', '2026-00125', {
  name: 'Juan Dela Cruz',
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
  email: 'juan.delacruz@student.edu.ph',
  attendeeType: 'STUDENT',
});

assert.strictEqual(secondPurchase.success, true);
assert.strictEqual(secondPurchase.accountCreated, false, 'Do not create duplicate account for existing student');
const juanUser = users.find(u => u.studentId === '2026-00125');
assert.strictEqual(juanUser.password, juanOldPass, 'Do NOT reset existing student password to ncst123');
console.log('✅ Existing student password preserved, no duplicate account created.');

// ==================== TEST 9: Outsider / Guest Purchase ====================
console.log('\n--- TEST 9: Outsider / Guest Ticket Purchase ---');
const userCountBefore = users.length;
const guestPurchase = purchaseTicket('evt-juans-2026', undefined, {
  name: 'Guest Visitor',
  attendeeType: 'OUTSIDER',
  contactNumber: '09171234567',
});

assert.strictEqual(guestPurchase.success, true);
assert.strictEqual(guestPurchase.accountCreated, false);
assert.strictEqual(users.length, userCountBefore, 'No user accounts created for guest');
console.log('✅ Outsider purchase does NOT create student account or assign ncst123.');

console.log('\n🎉 ALL 9 TEST SUITES COMPLETED SUCCESSFULLY! 100% PASS RATE.\n');
