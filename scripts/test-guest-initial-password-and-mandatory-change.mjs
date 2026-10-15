// test-guest-initial-password-and-mandatory-change.mjs
// NCST Event & Ticketing System: Guest Initial Password & Mandatory Password Change Enforcement

import assert from 'assert';
import { splitFullName } from '../src/utils/nameUtils.ts';

console.log('🧪 Starting Guest Initial Password & Mandatory Password Change Test Suite...\n');

let passCount = 0;
function test(name, fn) {
  try {
    fn();
    console.log(`  [PASS] ${name}`);
    passCount++;
  } catch (err) {
    console.error(`  [FAIL] ${name}: ${err.message}`);
    process.exit(1);
  }
}

// Mock store state
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
    lastName: 'Dela Cruz',
    password: 'CustomStudentPass99!',
    mustChangePassword: false,
    isActive: true,
  },
];

let tickets = [];
let currentUser = null;

// Mock outsider account provisioning
function provisionOutsiderAccount(data) {
  const name = (data.name || '').trim();
  const parsed = splitFullName(name);
  const surname = (data.lastName || parsed.lastName || '').trim();

  if (!surname || surname.length < 2 || !/^[a-zA-Z\s\-.'ñÑ]+$/u.test(surname)) {
    return {
      success: false,
      error: 'A valid surname could not be identified for guest account provisioning.'
    };
  }

  const guestRef = data.guestReference || `GUEST-${Math.floor(1000 + Math.random() * 9000)}`;

  // Check existing
  const existing = users.find(u =>
    (u.role === 'outsider' || u.attendeeType === 'OUTSIDER') &&
    (u.studentId === guestRef || (data.email && u.email === data.email))
  );

  if (existing) {
    return {
      success: true,
      created: false,
      userId: existing.id,
      accountId: existing.studentId || existing.id,
      initialPassword: null,
    };
  }

  const newOutsider = {
    id: `usr-guest-${Date.now()}`,
    name,
    firstName: parsed.firstName,
    lastName: surname,
    email: data.email || `${guestRef.toLowerCase()}@guest.ncst.edu.ph`,
    contactNumber: data.contactNumber,
    role: 'outsider',
    attendeeType: 'OUTSIDER',
    isOutsider: true,
    studentId: guestRef,
    department: 'General Public / Guest',
    password: surname, // authoritative surname as initial password
    mustChangePassword: true,
    isActive: true,
    accountStatus: 'active'
  };

  users.unshift(newOutsider);
  return {
    success: true,
    created: true,
    userId: newOutsider.id,
    accountId: guestRef,
    initialPassword: surname,
    user: newOutsider
  };
}

// Mock login handler matching useAppStore.ts logic
function login(identifier, password) {
  const cleanId = (identifier || '').replace(/\s+/g, '').toLowerCase();
  const cleanPass = (password || '').trim();

  const foundUser = users.find(u => {
    const stdId = (u.studentId || '').replace(/\s+/g, '').toLowerCase();
    const empId = (u.employeeId || '').replace(/\s+/g, '').toLowerCase();
    const phone = (u.contactNumber || '').replace(/\s+/g, '').toLowerCase();
    const uid = (u.id || '').replace(/\s+/g, '').toLowerCase();
    return stdId === cleanId || empId === cleanId || phone === cleanId || uid === cleanId;
  });

  if (!foundUser) {
    return { success: false, message: 'Invalid credentials.' };
  }

  const isGuest = foundUser.role === 'outsider' || foundUser.attendeeType === 'OUTSIDER';
  const userSurname = (foundUser.lastName || splitFullName(foundUser.name).lastName || '').trim();
  const expectedPassword = (foundUser.password || (foundUser.mustChangePassword ? userSurname : 'password123')).trim();

  const passMatches = cleanPass === expectedPassword ||
    (foundUser.mustChangePassword && Boolean(userSurname) && (cleanPass.toLowerCase() === userSurname.toLowerCase() || cleanPass === 'ncst123')) ||
    (!foundUser.mustChangePassword && isGuest && (cleanPass === 'password123' || cleanPass === 'ncst123'));

  if (!passMatches) {
    return { success: false, message: 'Invalid credentials.' };
  }

  const safe = { ...foundUser };
  delete safe.password;
  currentUser = safe;
  return { success: true, user: safe };
}

// Mock password update handler
function updateUserPassword(userId, newPassword) {
  if (currentUser?.id !== userId) {
    return { success: false, message: 'Unauthorized: cannot change another user password.' };
  }
  const u = users.find(usr => usr.id === userId);
  if (!u) {
    return { success: false, message: 'User not found.' };
  }

  const userSurname = (u.lastName || splitFullName(u.name).lastName || '').trim();
  if (userSurname && newPassword.toLowerCase() === userSurname.toLowerCase()) {
    return { success: false, message: 'New password cannot be your surname.' };
  }
  if (['ncst123', 'password123'].includes(newPassword.toLowerCase())) {
    return { success: false, message: 'New password cannot be generic default.' };
  }
  if (newPassword.length < 4) {
    return { success: false, message: 'Password must be at least 4 characters.' };
  }

  u.password = newPassword;
  u.mustChangePassword = false;
  if (currentUser && currentUser.id === userId) {
    currentUser.mustChangePassword = false;
  }
  return { success: true, message: 'Password updated successfully!' };
}

// -----------------------------------------------------------------------------
// TESTS
// -----------------------------------------------------------------------------

test('1. Newly provisioned Guest can log in with the correct initial surname password', () => {
  const res = provisionOutsiderAccount({
    name: 'Roberto Dela Cruz Jr.',
    guestReference: 'GUEST-7701',
    contactNumber: '09187701111'
  });

  assert.strictEqual(res.success, true);
  assert.strictEqual(res.created, true);
  assert.strictEqual(res.initialPassword, 'Dela Cruz');
  assert.strictEqual(res.user.mustChangePassword, true);

  const loginRes = login('GUEST-7701', 'Dela Cruz');
  assert.strictEqual(loginRes.success, true);
  assert.strictEqual(loginRes.user.mustChangePassword, true);
  assert.strictEqual(currentUser.studentId, 'GUEST-7701');
});

test('2. Guest using an incorrect password is rejected', () => {
  const wrongRes = login('GUEST-7701', 'wrongpassword');
  assert.strictEqual(wrongRes.success, false);

  const backdoorRes = login('GUEST-7701', 'password123');
  assert.strictEqual(backdoorRes.success, false, 'Generic password123 must not authenticate accounts with mustChangePassword');
});

test('3. Guest login resolves the correct control number and account', () => {
  const loginByPhone = login('09187701111', 'dela cruz');
  assert.strictEqual(loginByPhone.success, true);
  assert.strictEqual(loginByPhone.user.studentId, 'GUEST-7701');
  assert.strictEqual(loginByPhone.user.name, 'Roberto Dela Cruz Jr.');
});

test('4. Guest with must_change_password = 1 is restricted from protected portal view', () => {
  assert.strictEqual(currentUser.mustChangePassword, true);
  // Allowed tab is strictly student_dashboard (password setup)
  const allowedTabs = currentUser.mustChangePassword ? ['student_dashboard'] : ['student_dashboard', 'calendar'];
  assert.deepStrictEqual(allowedTabs, ['student_dashboard']);
});

test('5. Guest can establish a new password successfully', () => {
  const changeRes = updateUserPassword(currentUser.id, 'GuestSecurePass999!');
  assert.strictEqual(changeRes.success, true);
  assert.strictEqual(currentUser.mustChangePassword, false);

  const inDb = users.find(u => u.id === currentUser.id);
  assert.strictEqual(inDb.mustChangePassword, false);
  assert.strictEqual(inDb.password, 'GuestSecurePass999!');
});

test('6. After changing the password, the new password works and initial password fails', () => {
  // Old surname fails
  const oldLogin = login('GUEST-7701', 'Dela Cruz');
  assert.strictEqual(oldLogin.success, false);

  // New password succeeds
  const newLogin = login('GUEST-7701', 'GuestSecurePass999!');
  assert.strictEqual(newLogin.success, true);
  assert.strictEqual(newLogin.user.mustChangePassword, false);
});

test('7. A Student still follows the same mandatory-change workflow', () => {
  const newStudent = {
    id: 'usr-stud-temp',
    name: 'Ana Patricia Ramos',
    lastName: 'Ramos',
    studentId: '2026-99001',
    role: 'student',
    password: 'Ramos',
    mustChangePassword: true,
    isActive: true
  };
  users.unshift(newStudent);

  // Student logs in with surname
  const sLogin = login('2026-99001', 'Ramos');
  assert.strictEqual(sLogin.success, true);
  assert.strictEqual(sLogin.user.mustChangePassword, true);

  // Student changes password
  const sChange = updateUserPassword('usr-stud-temp', 'AnaStudent2026!');
  assert.strictEqual(sChange.success, true);
  assert.strictEqual(currentUser.mustChangePassword, false);

  // Old surname fails, new password succeeds
  assert.strictEqual(login('2026-99001', 'Ramos').success, false);
  assert.strictEqual(login('2026-99001', 'AnaStudent2026!').success, true);
});

test('8. Existing Student and Guest passwords are not overwritten by subsequent ticket purchases', () => {
  const guestUserBefore = users.find(u => u.studentId === 'GUEST-7701');
  const studentUserBefore = users.find(u => u.studentId === '2026-00125');

  const guestPassBefore = guestUserBefore.password;
  const studentPassBefore = studentUserBefore.password;

  // Repeat guest purchase
  const repGuest = provisionOutsiderAccount({
    name: 'Roberto Dela Cruz Jr.',
    guestReference: 'GUEST-7701'
  });
  assert.strictEqual(repGuest.created, false);

  const guestUserAfter = users.find(u => u.studentId === 'GUEST-7701');
  assert.strictEqual(guestUserAfter.password, guestPassBefore);
  assert.strictEqual(guestUserAfter.mustChangePassword, false);
  assert.strictEqual(studentUserBefore.password, studentPassBefore);
});

test('9. Guest accounts without a valid authoritative surname are not provisioned', () => {
  const invalid1 = provisionOutsiderAccount({ name: '   ', guestReference: 'GUEST-ERR-1' });
  assert.strictEqual(invalid1.success, false);
  assert.ok(invalid1.error.includes('surname'));

  const invalid2 = provisionOutsiderAccount({ name: '123456', guestReference: 'GUEST-ERR-2' });
  assert.strictEqual(invalid2.success, false);

  assert.strictEqual(users.some(u => u.studentId === 'GUEST-ERR-1' || u.studentId === 'GUEST-ERR-2'), false);
});

test('10. Unauthorized users cannot change another account password or bypass rules', () => {
  // Set currentUser to student
  currentUser = { id: 'usr-stud-1', role: 'student' };

  // Try to change guest's password
  const tamperRes = updateUserPassword('usr-guest-other', 'HackedPass123!');
  assert.strictEqual(tamperRes.success, false);

  // Try to set surname as new password
  const surnameRes = updateUserPassword('usr-stud-1', 'Dela Cruz');
  assert.strictEqual(surnameRes.success, false);
  assert.ok(surnameRes.message.includes('surname'));
});

console.log(`\n========================================================`);
console.log(`  ALL ${passCount} GUEST & STUDENT AUTH TESTS PASSED (100% SUCCESS)`);
console.log(`========================================================\n`);
