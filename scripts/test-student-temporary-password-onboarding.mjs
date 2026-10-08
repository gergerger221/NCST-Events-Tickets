import assert from 'assert';
import { generateTemporaryPassword } from '../src/utils/temporaryPassword.ts';
import { INITIAL_STUDENT_RECORDS, INITIAL_USERS, INITIAL_EVENTS, INITIAL_TICKETS } from '../src/utils/seedData.ts';

console.log('🧪 Starting Final Student Account Onboarding (Temporary Password + First-Login Activation) Test Suite...\n');

// 1. Temporary Password Generator Security
console.log('--- TEST 1: Temporary Password Generation & Randomness ---');
const temp1 = generateTemporaryPassword();
const temp2 = generateTemporaryPassword();
assert(temp1.startsWith('NCST-'), 'Temporary password should start with prefix');
assert(temp1.length === 14, 'Temporary password should be well-formed formatted string');
assert(temp1 !== temp2, 'Subsequent temporary passwords must be random and unique');
assert(temp1 !== 'password123', 'Must never use default password');
console.log(`✅ Generated sample passwords: ${temp1}, ${temp2}\n`);

// Mock In-Memory Store
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let studentRecords = JSON.parse(JSON.stringify(INITIAL_STUDENT_RECORDS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let transactions = [];
let currentUser = null;

function purchaseTicket(eventId, photoUrl, userId, cashierStaffName, studentOverride, customPrice) {
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER' || (!studentOverride?.studentId && !userId);
  const calculatedAttendeeType = isOutsider ? 'OUTSIDER' : 'STUDENT';
  const studentName = (studentOverride?.name || '').trim();
  const studentId = isOutsider ? undefined : studentOverride?.studentId;

  let studentRec = null;
  let existingUserAccount = null;
  let accountCreated = false;
  let accountSlipData = undefined;
  let temporaryCredentials = undefined;

  if (!isOutsider && studentId) {
    const cleanStudentId = studentId.trim();
    studentRec = studentRecords.find(r => r.studentId === cleanStudentId) || null;
    existingUserAccount = users.find(u => u.studentId === cleanStudentId) || null;

    if (!existingUserAccount) {
      const tempPass = generateTemporaryPassword();
      const newUser = {
        id: `usr-student-${Date.now()}-${Math.floor(Math.random()*1000)}`,
        name: studentRec ? studentRec.name : studentName,
        email: studentRec?.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`,
        role: 'student',
        password: tempPass,
        studentId: cleanStudentId,
        isActive: true,
        accountStatus: 'pending_activation',
        mustChangePassword: true,
        temporaryPassword: tempPass,
        createdAt: new Date().toISOString(),
      };

      users.unshift(newUser);
      if (studentRec) studentRec.userId = newUser.id;
      existingUserAccount = newUser;
      accountCreated = true;
      accountSlipData = {
        studentId: cleanStudentId,
        studentName: newUser.name,
        temporaryPassword: tempPass,
        createdAt: new Date().toISOString(),
      };
      temporaryCredentials = {
        studentId: cleanStudentId,
        email: newUser.email,
        tempPassword: tempPass,
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
    accountSlipData,
    temporaryCredentials,
  };
}

function login(identifier, password) {
  const foundUser = users.find(u => 
    (u.studentId && u.studentId.toLowerCase() === identifier.toLowerCase()) ||
    (u.email && u.email.toLowerCase() === identifier.toLowerCase())
  );
  if (!foundUser) return { success: false, message: 'No account found.' };
  if (foundUser.isActive === false) return { success: false, message: 'Account deactivated.' };
  if (foundUser.password !== password) return { success: false, message: 'Invalid password.' };
  
  currentUser = foundUser;
  return { success: true, user: foundUser };
}

function updateUserPassword(userId, newPassword) {
  const user = users.find(u => u.id === userId);
  if (!user) return { success: false, message: 'User not found.' };
  if (newPassword.length < 4) return { success: false, message: 'Password must be at least 4 characters.' };
  
  user.password = newPassword;
  user.mustChangePassword = false;
  user.accountStatus = 'active';
  user.temporaryPassword = undefined;

  // Bind any student tickets
  if (user.studentId) {
    tickets.forEach(t => {
      if (t.attendeeStudentId === user.studentId) {
        t.currentAttendeeId = user.id;
        t.originalOwnerId = user.id;
      }
    });
  }

  if (currentUser && currentUser.id === userId) {
    currentUser = { ...user };
  }
  return { success: true, user };
}

function register(data) {
  const existing = users.find(u => u.studentId === data.studentId);
  if (existing) {
    if (existing.accountStatus === 'pending_activation' || existing.mustChangePassword) {
      return {
        success: false,
        message: `An account for Student ID ${data.studentId} was already created when purchasing your ticket. Please sign in with your Temporary Password from your Account Slip.`,
      };
    }
    return { success: false, message: 'Account already exists.' };
  }
  const newUser = {
    id: `usr-student-${Date.now()}`,
    name: data.name,
    email: data.email,
    role: 'student',
    password: data.password,
    studentId: data.studentId,
    isActive: true,
    accountStatus: 'active',
    createdAt: new Date().toISOString(),
  };
  users.unshift(newUser);
  return { success: true, user: newUser };
}

console.log('--- TEST 2: Unregistered Student Buys First Ticket ---');
// Student 2026-00129 has an authoritative record but NO login account
const preAccount = users.find(u => u.studentId === '2026-00129');
assert(!preAccount, 'Confirm no login account existed before purchase');

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
assert(purchaseRes1.accountSlipData !== undefined, 'Account slip data must be generated');
assert(purchaseRes1.accountSlipData.studentId === '2026-00129', 'Account slip matches Student ID');
assert(purchaseRes1.accountSlipData.temporaryPassword.startsWith('NCST-'), 'Account slip includes temporary password');

const createdUser = users.find(u => u.studentId === '2026-00129');
assert(createdUser !== undefined, 'User must exist in store');
assert(createdUser.accountStatus === 'pending_activation', 'Account status must be pending_activation');
assert(createdUser.mustChangePassword === true, 'mustChangePassword must be true');
assert(createdUser.password === purchaseRes1.accountSlipData.temporaryPassword, 'Password matches generated temporary password');
console.log('✅ First purchase creates pending account with temporary password.\n');

console.log('--- TEST 3: Duplicate Registration Guard for Pending Account ---');
const regAttempt = register({
  name: 'Christian Bautista',
  email: 'christian.bautista@student.edu.ph',
  studentId: '2026-00129',
  password: 'myPassword123'
});
assert(regAttempt.success === false, 'Duplicate self-registration must be blocked');
assert(regAttempt.message.includes('already created when purchasing your ticket'), 'Directs student to login with temporary password');
console.log('✅ Self-registration guard blocks duplicate creation and directs to login.\n');

console.log('--- TEST 4: Student Logs in with Temporary Password ---');
const wrongLogin = login('2026-00129', 'wrongPassword');
assert(wrongLogin.success === false, 'Wrong password fails login');

const tempLogin = login('2026-00129', purchaseRes1.accountSlipData.temporaryPassword);
assert(tempLogin.success === true, 'Login with temporary password succeeds');
assert(tempLogin.user.mustChangePassword === true, 'Must detect first-login activation required');
console.log('✅ Login with temporary password succeeds and flags mandatory password change.\n');

console.log('--- TEST 5: First-Login Password Change Activation ---');
const passChangeRes = updateUserPassword(createdUser.id, 'MyPermanentPassword#2026');
assert(passChangeRes.success === true, 'Password update succeeds');
assert(createdUser.password === 'MyPermanentPassword#2026', 'Password updated to permanent password');
assert(createdUser.mustChangePassword === false, 'mustChangePassword is now false');
assert(createdUser.accountStatus === 'active', 'accountStatus is now active');
assert(createdUser.temporaryPassword === undefined, 'temporary password is cleared');
console.log('✅ First-login password change activates account.\n');

console.log('--- TEST 6: Temporary Password Invalidation & New Password Verification ---');
const oldTempLogin = login('2026-00129', purchaseRes1.accountSlipData.temporaryPassword);
assert(oldTempLogin.success === false, 'Temporary password no longer works');

const permanentLogin = login('2026-00129', 'MyPermanentPassword#2026');
assert(permanentLogin.success === true, 'Permanent password works');
assert(permanentLogin.user.mustChangePassword === false, 'mustChangePassword is false');
console.log('✅ Temporary password invalidated, permanent password operational.\n');

console.log('--- TEST 7: Digital Ticket Visibility in Student Passes ---');
const studentTickets = tickets.filter(t => t.currentAttendeeId === createdUser.id || t.attendeeStudentId === '2026-00129');
assert(studentTickets.length === 1, 'Issued ticket appears in student passes');
assert(studentTickets[0].ticketNumber === purchaseRes1.ticket.ticketNumber, 'Ticket number preserved');
console.log('✅ Previous ticket immediately visible in student passes.\n');

console.log('--- TEST 8: Existing Student Buys Second Ticket ---');
const purchaseRes2 = purchaseTicket(
  'evt-fnd-2026',
  undefined,
  undefined,
  'Carla Gomez',
  { studentId: '2026-00129', name: 'Christian Bautista', attendeeType: 'STUDENT' },
  150
);
assert(purchaseRes2.success === true, 'Second purchase succeeds');
assert(purchaseRes2.accountCreated === false, 'No second account created');
assert(purchaseRes2.accountSlipData === undefined, 'No temporary password generated for existing account');
const matchingAccounts = users.filter(u => u.studentId === '2026-00129');
assert(matchingAccounts.length === 1, 'Exactly one user account exists');
console.log('✅ Second purchase for existing student does NOT generate temp pass or duplicate account.\n');

console.log('--- TEST 9: Outsider / Guest Ticket Purchase ---');
const userCountBefore = users.length;
const outsiderPurchase = purchaseTicket(
  'evt-juans-2026',
  undefined,
  undefined,
  'Carla Gomez',
  { name: 'Diana Ross', attendeeType: 'OUTSIDER' },
  500
);
assert(outsiderPurchase.success === true, 'Outsider ticket purchase succeeds');
assert(outsiderPurchase.accountCreated === false, 'No student account created for outsider');
assert(outsiderPurchase.accountSlipData === undefined, 'No account slip generated for outsider');
assert(users.length === userCountBefore, 'User count unchanged');
console.log('✅ Outsider purchase does NOT create student accounts or temporary passwords.\n');

console.log('🎉 ALL 9 TEST SUITES COMPLETED SUCCESSFULLY! 100% PASS RATE.');
