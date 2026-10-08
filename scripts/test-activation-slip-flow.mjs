import assert from 'assert';
import { generateActivationCode, hashActivationCode, verifyActivationCode } from '../src/utils/activationSecurity.ts';
import { INITIAL_STUDENT_RECORDS, INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS } from '../src/utils/seedData.ts';

console.log('🧪 Starting Final Student Account Onboarding & Activation Slip Test Suite...\n');

// 1. Test activation security primitives
console.log('--- TEST 1: Activation Security & Cryptographic Generation ---');
const code1 = generateActivationCode();
const code2 = generateActivationCode();
assert(code1.length === 9, 'Activation code must be 9 characters including hyphen (XXXX-XXXX)');
assert(/^[2-9A-HJ-NP-Z]{4}-[2-9A-HJ-NP-Z]{4}$/.test(code1), 'Code must use non-ambiguous charset');
assert(code1 !== code2, 'Subsequent activation codes must be unique and non-predictable');

const studentIdA = '2026-00129';
const studentIdB = '2026-00130';
const hashA = hashActivationCode(code1, studentIdA);
const hashB = hashActivationCode(code1, studentIdB);

assert(hashA.startsWith('act_'), 'Hash must start with secure prefix');
assert(hashA !== hashB, 'Hash must be tenant-bound to the specific Student ID');
assert(verifyActivationCode(code1, studentIdA, hashA) === true, 'Verification of correct code + studentId must succeed');
assert(verifyActivationCode(code1, studentIdB, hashA) === false, 'Cross-student activation must fail');
assert(verifyActivationCode('WRONG-CODE', studentIdA, hashA) === false, 'Wrong code verification must fail');
console.log('✅ Activation Security Primitives passed.\n');

// Mock in-memory state simulating useAppStore logic
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let studentRecords = JSON.parse(JSON.stringify(INITIAL_STUDENT_RECORDS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let transactions = [];

function purchaseTicket(eventId, photoUrl, userId, cashierStaffName, studentOverride, customPrice) {
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER' || (!studentOverride?.studentId && !userId);
  const calculatedAttendeeType = isOutsider ? 'OUTSIDER' : 'STUDENT';
  const studentName = (studentOverride?.name || '').trim();
  const studentId = isOutsider ? undefined : studentOverride?.studentId;

  let studentRec = null;
  let existingUserAccount = null;
  let accountCreated = false;
  let activationSlipData = undefined;

  if (!isOutsider && studentId) {
    const cleanStudentId = studentId.trim();
    studentRec = studentRecords.find(r => r.studentId === cleanStudentId) || null;
    existingUserAccount = users.find(u => u.studentId === cleanStudentId) || null;

    if (!existingUserAccount) {
      const activationCode = generateActivationCode();
      const activationCodeHash = hashActivationCode(activationCode, cleanStudentId);
      const expiresAt = new Date(Date.now() + 30 * 24 * 60 * 60 * 1000).toISOString();

      const newUser = {
        id: `usr-student-${Date.now()}-${Math.floor(Math.random()*1000)}`,
        name: studentRec ? studentRec.name : studentName,
        email: studentRec?.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`,
        role: 'student',
        password: '',
        studentId: cleanStudentId,
        isActive: false,
        accountStatus: 'pending_activation',
        activationCodeHash,
        activationCodeExpiresAt: expiresAt,
        activationCodeUsed: false,
        createdAt: new Date().toISOString(),
      };

      users.unshift(newUser);
      if (studentRec) studentRec.userId = newUser.id;
      existingUserAccount = newUser;
      accountCreated = true;
      activationSlipData = {
        studentId: cleanStudentId,
        studentName: newUser.name,
        activationCode,
        createdAt: new Date().toISOString(),
        expiresAt,
      };
    }
  }

  const ownerId = existingUserAccount ? existingUserAccount.id : (isOutsider ? 'usr-guest' : 'rec-student');
  const ticketSeq = 10000 + tickets.length + 1;
  const newTicketNumber = isOutsider ? `TIX-O-${ticketSeq}` : `TIX-${ticketSeq}`;

  const newTicket = {
    id: newTicketNumber,
    ticketNumber: newTicketNumber,
    eventId,
    attendeeType: calculatedAttendeeType,
    originalOwnerId: ownerId,
    currentAttendeeId: ownerId,
    currentAttendeeName: studentName,
    attendeeStudentId: isOutsider ? undefined : studentId,
    ticketStatus: 'ISSUED',
    paymentStatus: 'paid',
    attendanceStatus: 'not_entered',
    qrPrintStatus: 'PENDING',
    purchaseDate: new Date().toISOString(),
  };

  tickets.unshift(newTicket);
  return {
    success: true,
    ticket: newTicket,
    accountCreated,
    activationSlipData,
  };
}

function login(identifier, password) {
  const foundUser = users.find(u => 
    (u.studentId && u.studentId.toLowerCase() === identifier.toLowerCase()) ||
    (u.email && u.email.toLowerCase() === identifier.toLowerCase())
  );
  if (!foundUser) return { success: false, message: 'No account found.' };
  if (foundUser.accountStatus === 'pending_activation' || (!foundUser.password && foundUser.activationCodeUsed === false)) {
    return { success: false, message: 'This student account has not been activated yet.' };
  }
  if (foundUser.password !== password) return { success: false, message: 'Invalid password.' };
  return { success: true, user: foundUser };
}

function activateStudentAccount(studentId, activationCode, newPassword) {
  const cleanId = studentId.trim();
  const cleanCode = activationCode.trim().toUpperCase();

  const targetUser = users.find(u => u.role === 'student' && u.studentId === cleanId);
  if (!targetUser) return { success: false, message: 'No student account found for this Student ID.' };
  if (targetUser.accountStatus === 'active' && targetUser.activationCodeUsed) {
    return { success: false, message: 'This student account is already active.' };
  }
  if (targetUser.activationCodeUsed) {
    return { success: false, message: 'This activation code has already been used.' };
  }
  if (!verifyActivationCode(cleanCode, cleanId, targetUser.activationCodeHash)) {
    return { success: false, message: 'Invalid activation code for this Student ID.' };
  }

  targetUser.password = newPassword;
  targetUser.isActive = true;
  targetUser.accountStatus = 'active';
  targetUser.activationCodeUsed = true;
  targetUser.activationCodeUsedAt = new Date().toISOString();

  // Bind previous tickets
  tickets.forEach(t => {
    if (t.attendeeStudentId === cleanId) {
      t.currentAttendeeId = targetUser.id;
      t.originalOwnerId = targetUser.id;
    }
  });

  return { success: true, message: 'Account activated successfully.', user: targetUser };
}

console.log('--- TEST 2: Unregistered Enrolled Student Buys First Ticket ---');
// Student 2026-00129 has an authoritative record but no login account
const preAccount = users.find(u => u.studentId === '2026-00129');
assert(!preAccount, 'Confirm no active login account existed before purchase');

const purchaseRes1 = purchaseTicket(
  'evt-juans-2026',
  undefined,
  undefined,
  'Carla Gomez',
  { studentId: '2026-00129', name: 'Christian Bautista', attendeeType: 'STUDENT' },
  350
);

assert(purchaseRes1.success === true, 'Ticket purchase must succeed');
assert(purchaseRes1.accountCreated === true, 'Account creation flag must be true');
assert(purchaseRes1.activationSlipData !== undefined, 'Activation slip data must be generated');
assert(purchaseRes1.activationSlipData.studentId === '2026-00129', 'Activation slip must match Student ID');
assert(purchaseRes1.activationSlipData.activationCode.length === 9, 'Activation code must be valid format');
assert(!('tempPassword' in purchaseRes1.activationSlipData), 'Must NOT contain temporary passwords');

const createdUser = users.find(u => u.studentId === '2026-00129');
assert(createdUser !== undefined, 'User must exist in pending state');
assert(createdUser.accountStatus === 'pending_activation', 'Account status must be pending_activation');
assert(createdUser.isActive === false, 'isActive must be false prior to activation');
assert(createdUser.password === '', 'Password must be empty prior to activation');
console.log('✅ First purchase creates pending account with activation slip data.\n');

console.log('--- TEST 3: Login Attempt Before Activation ---');
const preLogin = login('2026-00129', 'password123');
assert(preLogin.success === false, 'Login before activation must fail');
assert(preLogin.message.includes('not been activated yet'), 'Must inform student to activate account');
console.log('✅ Unactivated student cannot sign in before activation.\n');

console.log('--- TEST 4: Student Activation with Student ID + Activation Code ---');
// Attempt with wrong code
const wrongCodeRes = activateStudentAccount('2026-00129', 'AAAA-BBBB', 'MySecret2026!');
assert(wrongCodeRes.success === false, 'Activation with wrong code must fail');

// Attempt with another student ID
const wrongIdRes = activateStudentAccount('2026-00130', purchaseRes1.activationSlipData.activationCode, 'MySecret2026!');
assert(wrongIdRes.success === false, 'Activation for mismatched student ID must fail');

// Successful activation
const actSuccessRes = activateStudentAccount(
  '2026-00129',
  purchaseRes1.activationSlipData.activationCode,
  'PermanentPass#2026'
);
assert(actSuccessRes.success === true, 'Activation with valid code must succeed');
assert(createdUser.accountStatus === 'active', 'User accountStatus must be active');
assert(createdUser.isActive === true, 'User isActive must be true');
assert(createdUser.password === 'PermanentPass#2026', 'User password must be set to permanent password');
assert(createdUser.activationCodeUsed === true, 'Activation code must be marked as used');
console.log('✅ Student self-activation with custom permanent password succeeded.\n');

console.log('--- TEST 5: Login and Ticket Visibility After Activation ---');
const postLogin = login('2026-00129', 'PermanentPass#2026');
assert(postLogin.success === true, 'Login with permanent password must succeed');
assert(postLogin.user.studentId === '2026-00129', 'Logged in user matches student');

// Verify ticket ownership binding
const studentTickets = tickets.filter(t => t.currentAttendeeId === createdUser.id || t.attendeeStudentId === '2026-00129');
assert(studentTickets.length === 1, 'Issued ticket must be linked and visible to student');
assert(studentTickets[0].ticketNumber === purchaseRes1.ticket.ticketNumber, 'Ticket number matches issued ticket');
console.log('✅ Login successful and existing ticket is immediately visible.\n');

console.log('--- TEST 6: Activation Code Re-use Protection ---');
const reuseRes = activateStudentAccount(
  '2026-00129',
  purchaseRes1.activationSlipData.activationCode,
  'AnotherPassword123'
);
assert(reuseRes.success === false, 'Reusing an activation code must fail');
console.log('✅ Activation code re-use strictly blocked.\n');

console.log('--- TEST 7: Existing Student Buys Second Ticket ---');
const purchaseRes2 = purchaseTicket(
  'evt-fnd-2026',
  undefined,
  undefined,
  'Carla Gomez',
  { studentId: '2026-00129', name: 'Christian Bautista', attendeeType: 'STUDENT' },
  150
);
assert(purchaseRes2.success === true, 'Second ticket purchase must succeed');
assert(purchaseRes2.accountCreated === false, 'No second account created for existing student');
assert(purchaseRes2.activationSlipData === undefined, 'No activation slip generated for existing student');
const studentAccounts = users.filter(u => u.studentId === '2026-00129');
assert(studentAccounts.length === 1, 'Exactly one user account must exist for the student');
console.log('✅ Second ticket purchase for existing student does NOT create duplicate account.\n');

console.log('--- TEST 8: Outsider / Guest Ticket Purchase ---');
const initialUserCount = users.length;
const outsiderPurchase = purchaseTicket(
  'evt-juans-2026',
  undefined,
  undefined,
  'Carla Gomez',
  { name: 'Robert Rodriguez', attendeeType: 'OUTSIDER', studentId: undefined },
  500
);
assert(outsiderPurchase.success === true, 'Outsider ticket purchase must succeed');
assert(outsiderPurchase.accountCreated === false, 'No account created for outsider');
assert(outsiderPurchase.activationSlipData === undefined, 'No activation slip for outsider');
assert(users.length === initialUserCount, 'User count remains unchanged');
console.log('✅ Outsider purchase does NOT create student accounts or activation slips.\n');

console.log('🎉 ALL 8 TEST SUITES COMPLETED SUCCESSFULLY! 100% PASS RATE.');
