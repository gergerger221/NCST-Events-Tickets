import assert from 'assert';

console.log('🧪 Starting Cashier Account Provisioning & Password Integrity Test Suite (Frontend Mock/Store)...\\n');

// Mock in-memory state
let users = [];
let studentRecords = [];
let tickets = [];
let transactions = [];

function resetState() {
  users = [];
  studentRecords = [];
  tickets = [];
  transactions = [];
}

function normalizeId(id) {
  return (id || '').replace(/\s+/g, '').toUpperCase();
}

function purchaseTicket(eventId, studentId, studentOverride) {
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER';
  let studentRec = null;
  let existingUserAccount = null;
  let accountCreated = false;

  if (!isOutsider && studentId) {
    const cleanStudentId = normalizeId(studentId);
    studentRec = studentRecords.find(r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase());

    if (!studentRec) {
      studentRec = {
        id: `rec-${cleanStudentId}`,
        studentId: cleanStudentId,
        name: studentOverride.name,
        course: studentOverride.course || 'BSIT',
        yearLevel: studentOverride.yearLevel || '1st Year',
        section: studentOverride.section || '11M1',
        email: studentOverride.email,
        department: studentOverride.department || 'CSD',
      };
      studentRecords.push(studentRec);
    }

    // Check if user account already exists (by studentId OR by linked studentRec.userId)
    existingUserAccount = users.find(u =>
      (u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()) ||
      (studentRec.userId && u.id === studentRec.userId)
    ) || null;

    if (!existingUserAccount) {
      // CASE B: STUDENTRECORD EXISTS BUT NO PORTAL ACCOUNT EXISTS
      const initialPassword = 'ncst123';
      const cleanEmail = studentRec.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`;

      const newUser = {
        id: `usr-student-${Date.now().toString().slice(-6)}`,
        name: studentRec.name,
        email: cleanEmail,
        role: 'student',
        password: initialPassword,
        studentId: cleanStudentId,
        department: studentRec.department || 'CSD',
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
      // CASE A: EXISTING STUDENT PORTAL ACCOUNT
      // REUSE user account, STRICTLY PRESERVE password and security settings!
      studentRec.userId = existingUserAccount.id;
      accountCreated = false;
    }
  }

  const ownerId = existingUserAccount ? existingUserAccount.id : (studentRec ? studentRec.id : `usr-guest-${Date.now()}`);
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
    ticketStatus: 'ISSUED',
    paymentStatus: 'paid',
    attendanceStatus: 'not_entered',
  };
  tickets.unshift(newTicket);

  const newTxn = {
    id: `tx-${Date.now()}-${tickets.length}`,
    userId: ownerId,
    userName: studentOverride.name,
    attendeeType: isOutsider ? 'OUTSIDER' : 'STUDENT',
    studentId: isOutsider ? undefined : studentId,
    eventId,
    ticketId: newTicketNumber,
    amount: 150,
  };
  transactions.unshift(newTxn);

  return {
    success: true,
    ticket: newTicket,
    accountCreated,
    user: existingUserAccount,
  };
}

function login(identifier, password) {
  const clean = normalizeId(identifier).toLowerCase();
  const found = users.find(u =>
    (u.studentId && normalizeId(u.studentId).toLowerCase() === clean) ||
    (u.email && u.email.toLowerCase() === clean)
  );
  if (!found) return { success: false, message: 'User not found' };
  if (found.password !== password) return { success: false, message: 'Invalid password' };
  return { success: true, user: found };
}

// =============================================================================
// TEST 1: Existing account with personal password
// =============================================================================
console.log('--- TEST 1: Existing Account with Personal Password ---');
resetState();

studentRecords.push({
  id: 'rec-2026-00125',
  studentId: '2026-00125',
  name: 'Juan Dela Cruz',
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
  email: 'juan.delacruz@student.ncst.edu.ph',
});

// Student registers via portal and sets custom password
const personalPassword = 'MySecretCustomPass2026!';
const studentUser = {
  id: 'usr-student-00125',
  name: 'Juan Dela Cruz',
  email: 'juan.delacruz@student.ncst.edu.ph',
  role: 'student',
  password: personalPassword,
  studentId: '2026-00125',
  department: 'CSD',
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
  isActive: true,
  mustChangePassword: false,
};
users.push(studentUser);
studentRecords[0].userId = studentUser.id;

// Cashier issues ticket
const res1 = purchaseTicket('evt-1', '2026-00125', {
  name: 'Juan Dela Cruz',
  course: 'BSIT',
  yearLevel: '3rd Year',
  section: '31M1',
});

assert.strictEqual(res1.success, true);
assert.strictEqual(res1.accountCreated, false, 'No new account created for existing student');
assert.strictEqual(res1.user.password, personalPassword, 'Personal password must be preserved');
assert.strictEqual(res1.user.mustChangePassword, false, 'mustChangePassword remains false');

// Verify login with personal password succeeds
const loginRes1 = login('2026-00125', personalPassword);
assert.strictEqual(loginRes1.success, true, 'Personal password authenticates');

// Verify login with default password fails
const failLogin1 = login('2026-00125', 'ncst123');
assert.strictEqual(failLogin1.success, false, 'Default password ncst123 rejected');

console.log('✅ TEST 1 PASSED: Personal password preserved, no duplicate account, authentication intact.\n');

// =============================================================================
// TEST 2: Existing account with no prior ticket
// =============================================================================
console.log('--- TEST 2: Existing Account with No Prior Ticket ---');
resetState();

studentRecords.push({
  id: 'rec-2026-00126',
  studentId: '2026-00126',
  name: 'Maria Clara',
  course: 'BSCS',
  yearLevel: '1st Year',
  section: '11M1',
});
users.push({
  id: 'usr-student-00126',
  name: 'Maria Clara',
  role: 'student',
  password: 'MariaPass123!',
  studentId: '2026-00126',
  mustChangePassword: false,
});
studentRecords[0].userId = 'usr-student-00126';

assert.strictEqual(tickets.length, 0, 'No prior tickets');

const res2 = purchaseTicket('evt-1', '2026-00126', {
  name: 'Maria Clara',
  course: 'BSCS',
  yearLevel: '1st Year',
  section: '11M1',
});

assert.strictEqual(res2.success, true);
assert.strictEqual(res2.accountCreated, false, 'Do not create new account');
assert.strictEqual(res2.user.password, 'MariaPass123!', 'Password remains untouched');
console.log('✅ TEST 2 PASSED: First ticket purchase preserves existing account credentials.\n');

// =============================================================================
// TEST 3: StudentRecord without a portal account
// =============================================================================
console.log('--- TEST 3: StudentRecord without Portal Account ---');
resetState();

studentRecords.push({
  id: 'rec-2026-00129',
  studentId: '2026-00129',
  name: 'Chloe Santiago',
  course: 'BSIT',
  yearLevel: '1st Year',
  section: '11M1',
});

assert.strictEqual(users.length, 0, 'No account initially exists');

const res3 = purchaseTicket('evt-1', '2026-00129', {
  name: 'Chloe Santiago',
  course: 'BSIT',
  yearLevel: '1st Year',
  section: '11M1',
});

assert.strictEqual(res3.success, true);
assert.strictEqual(res3.accountCreated, true, 'Account created flag must be true');
assert.strictEqual(users.length, 1, 'Exactly one user account created');
assert.strictEqual(users[0].password, 'ncst123', 'Initial password is ncst123');
assert.strictEqual(users[0].mustChangePassword, true, 'First-login password change enforced');
console.log('✅ TEST 3 PASSED: Initial account created with temporary policy.\n');

// =============================================================================
// TEST 4: Repeated Cashier request / Retry
// =============================================================================
console.log('--- TEST 4: Repeated Cashier Request / Retry ---');
const userCountBefore = users.length;

// Cashier retries the purchase for Chloe
const res4 = purchaseTicket('evt-1', '2026-00129', {
  name: 'Chloe Santiago',
  course: 'BSIT',
  yearLevel: '1st Year',
  section: '11M1',
});

assert.strictEqual(res4.success, true);
assert.strictEqual(res4.accountCreated, false, 'No duplicate account on retry');
assert.strictEqual(users.length, userCountBefore, 'User count does not increase');
assert.strictEqual(res4.user.password, 'ncst123', 'Password preserved');
console.log('✅ TEST 4 PASSED: Retry reuses account and does not duplicate.\n');

// =============================================================================
// TEST 5: Outsider
// =============================================================================
console.log('--- TEST 5: Outsider / Guest Ticket Issuance ---');
const uCountBeforeGuest = users.length;

const res5 = purchaseTicket('evt-1', undefined, {
  name: 'Guest Attendee',
  attendeeType: 'OUTSIDER',
  contactNumber: '09171234567',
});

assert.strictEqual(res5.success, true);
assert.strictEqual(res5.accountCreated, false);
assert.strictEqual(users.length, uCountBeforeGuest, 'No student user account created for outsider');
console.log('✅ TEST 5 PASSED: Guest ticket issued with no student account creation.\n');

// =============================================================================
// TEST 6: Existing account with non-default password
// =============================================================================
console.log('--- TEST 6: Existing Account with Non-Default Changed Password ---');
// User Chloe changes password from ncst123 to permanent
const chloe = users.find(u => u.studentId === '2026-00129');
chloe.password = 'ChloePermanentSecurePassword999!';
chloe.mustChangePassword = false;

// Chloe purchases another event ticket
const res6 = purchaseTicket('evt-2', '2026-00129', {
  name: 'Chloe Santiago',
  course: 'BSIT',
  yearLevel: '1st Year',
  section: '11M1',
});

assert.strictEqual(res6.success, true);
assert.strictEqual(res6.accountCreated, false);
assert.strictEqual(res6.user.password, 'ChloePermanentSecurePassword999!', 'Permanent password preserved');
assert.strictEqual(res6.user.mustChangePassword, false, 'mustChangePassword remains false');

const chloeLogin = login('2026-00129', 'ChloePermanentSecurePassword999!');
assert.strictEqual(chloeLogin.success, true);
const chloeTempFail = login('2026-00129', 'ncst123');
assert.strictEqual(chloeTempFail.success, false, 'ncst123 is rejected');
console.log('✅ TEST 6 PASSED: Changed password preserved, credentials and account status intact.\n');

console.log('🎉 ALL 6 CASHIER ACCOUNT PROVISIONING REGRESSION TESTS PASSED!');
