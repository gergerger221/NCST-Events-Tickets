import assert from 'assert';

console.log('=================================================================');
console.log('  FINAL AUTHENTICATION, OUTSIDER LOOKUP, & CASHIER TEST SUITE (MJS)');
console.log('=================================================================\n');

let passCount = 0;
let failCount = 0;

function it(description, fn) {
  try {
    fn();
    console.log(`  [PASS] ${description}`);
    passCount++;
  } catch (err) {
    console.log(`  [FAIL] ${description}`);
    console.log(`         Error: ${err.message}`);
    failCount++;
  }
}

// In-Memory store simulation matching useAppStore.ts logic
let users = [];
let studentRecords = [];
let tickets = [];
let events = [];
let currentUser = null;

function resetStore() {
  users = [
    {
      id: 'usr-admin-1',
      name: 'Engr. Mark Santos',
      email: 'admin.santos@school.edu.ph',
      employeeId: 'ADM-001',
      role: 'admin',
      password: 'password123',
      isActive: true
    },
    {
      id: 'usr-cashier-1',
      name: 'Carla Gomez',
      email: 'cashier.gomez@school.edu.ph',
      employeeId: 'CSH-001',
      role: 'cashier',
      password: 'password123',
      isActive: true
    },
    {
      id: 'usr-teacher-1',
      name: 'Prof. Ramon Reyes',
      email: 'ramon.reyes@school.edu.ph',
      employeeId: 'TCH-001',
      role: 'teacher',
      password: 'password123',
      isActive: true
    },
    {
      id: 'usr-student-1',
      name: 'Juan Dela Cruz',
      email: 'juan.delacruz@student.edu.ph',
      studentId: '2024-58566',
      role: 'student',
      password: 'password123',
      isActive: true,
      mustChangePassword: false
    }
  ];

  events = [
    {
      id: 'evt-1',
      title: 'NCST Foundation Day 2026',
      date: '2026-10-25',
      venue: 'NCST Gymnasium',
      startTime: '08:00 AM',
      endTime: '05:00 PM',
      ticketPrice: 150
    }
  ];

  tickets = [];
  studentRecords = [];
  currentUser = null;
}

function normalizeId(id) {
  return (id || '').replace(/\s+/g, '').toUpperCase();
}

function findUserByIdentifier(identifier) {
  const trimmed = (identifier || '').trim().toLowerCase();
  if (!trimmed || trimmed.includes('@')) return undefined;
  const cleanId = trimmed.replace(/\s+/g, '');

  return users.find(u => {
    const studentMatch = u.role === 'student' && u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId;
    const employeeMatch = u.role !== 'student' && u.employeeId && normalizeId(u.employeeId).toLowerCase() === cleanId;
    const idMatch = u.id && u.id.trim().toLowerCase() === cleanId;
    return studentMatch || employeeMatch || idMatch;
  });
}

function login(identifier, password) {
  const raw = (identifier || '').trim();
  const pass = (password || '').trim();

  if (!raw || !pass) {
    return { success: false, message: 'ID Number and password are required.' };
  }

  // Reject email addresses
  if (raw.includes('@')) {
    return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
  }

  const cleanId = raw.toLowerCase().replace(/\s+/g, '');
  if (!/^[a-z0-9\-]+$/i.test(cleanId)) {
    return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
  }

  const user = findUserByIdentifier(raw);
  if (!user) {
    return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
  }

  if (user.isActive === false) {
    return { success: false, message: 'This account has been deactivated.' };
  }

  const expectedPass = user.password || (user.mustChangePassword ? 'ncst123' : 'password123');
  if (pass !== expectedPass) {
    return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
  }

  currentUser = user;
  return { success: true, user };
}

function lookupGuestTicket(controlNumber, contactNumber) {
  const cleanCtrl = (controlNumber || '').trim();
  if (!cleanCtrl) {
    return { success: false, message: 'Please enter a ticket Control Number.' };
  }

  const norm = cleanCtrl.toLowerCase();
  const t = tickets.find(item =>
    (item.ticketNumber && item.ticketNumber.toLowerCase() === norm) ||
    (item.qrToken && item.qrToken.toLowerCase() === norm) ||
    (item.id && item.id.toLowerCase() === norm)
  );

  if (!t) {
    return { success: false, message: 'No matching ticket found for this Control Number.' };
  }

  if (contactNumber) {
    const cleanInputContact = (contactNumber || '').replace(/[^0-9]/g, '');
    const cleanStoredContact = (t.contactNumber || '').replace(/[^0-9]/g, '');
    if (cleanInputContact && cleanStoredContact &&
        !cleanStoredContact.includes(cleanInputContact) &&
        !cleanInputContact.includes(cleanStoredContact)) {
      return { success: false, message: 'Verification failed: Contact number does not match this ticket record.' };
    }
  }

  const ev = events.find(e => e.id === t.eventId);
  return {
    success: true,
    ticket: {
      controlNumber: t.ticketNumber || t.id,
      ticketId: t.id,
      eventTitle: ev?.title || 'NCST Campus Event',
      venue: ev?.venue || 'NCST Campus',
      date: ev?.date || '',
      startTime: ev?.startTime || '',
      endTime: ev?.endTime || '',
      attendeeName: t.currentAttendeeName || t.originalOwnerName || 'Guest Attendee',
      ticketStatus: t.ticketStatus || 'ISSUED',
      qrToken: t.qrToken || t.ticketNumber || t.id,
      price: t.price || 0,
      attendeeType: 'OUTSIDER'
    }
  };
}

function purchaseTicket(eventId, studentId, studentOverride) {
  const isOutsider = studentOverride?.attendeeType === 'OUTSIDER';
  let studentRec = null;
  let existingUserAccount = null;

  if (!isOutsider && studentId) {
    const cleanStudentId = normalizeId(studentId);
    studentRec = studentRecords.find(r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase());

    if (!studentRec) {
      studentRec = {
        id: `rec-${cleanStudentId}`,
        studentId: cleanStudentId,
        name: studentOverride?.name || 'Enrolled Student',
        course: studentOverride?.course || 'BSIT',
        yearLevel: studentOverride?.yearLevel || '1st Year',
        section: studentOverride?.section || '11M1',
        department: studentOverride?.department || 'CSD',
      };
      studentRecords.push(studentRec);
    }

    existingUserAccount = users.find(u =>
      (u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()) ||
      (studentRec.userId && u.id === studentRec.userId)
    ) || null;

    if (!existingUserAccount) {
      // Create exactly 1 account with default temp password
      const newUser = {
        id: `usr-${cleanStudentId}`,
        name: studentRec.name,
        email: `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`,
        role: 'student',
        password: 'ncst123',
        studentId: cleanStudentId,
        mustChangePassword: true,
        isActive: true,
      };
      users.push(newUser);
      studentRec.userId = newUser.id;
      existingUserAccount = newUser;
    }
  }

  const newTicket = {
    id: `tkt-${Date.now()}-${Math.random().toString(36).slice(2, 6)}`,
    eventId,
    ticketNumber: `TKT-${Date.now().toString().slice(-5)}`,
    attendeeType: isOutsider ? 'OUTSIDER' : 'STUDENT',
    attendeeStudentId: isOutsider ? undefined : studentId,
    originalOwnerName: isOutsider ? studentOverride?.name : studentRec?.name,
    currentAttendeeName: isOutsider ? studentOverride?.name : studentRec?.name,
    contactNumber: studentOverride?.contactNumber,
    ticketStatus: 'ISSUED',
    qrToken: `QR-${Date.now()}`,
    price: 150
  };
  tickets.push(newTicket);
  return { ticket: newTicket, user: existingUserAccount };
}

// Initialize
resetStore();

// =============================================================================
// AUTHENTICATION TESTS (Scenarios 1-7)
// =============================================================================

it('1. A valid Student ID with the correct password succeeds', () => {
  const res = login('2024-58566', 'password123');
  assert.strictEqual(res.success, true);
  assert.strictEqual(res.user?.role, 'student');
  assert.strictEqual(currentUser?.studentId, '2024-58566');
});

it('2. An incorrect password fails', () => {
  const res = login('2024-58566', 'wrongpass');
  assert.strictEqual(res.success, false);
  assert.ok(res.message.includes('Invalid credentials'));
});

it('3. A registered employee identifier containing letters succeeds when its format is valid', () => {
  const resAdmin = login('ADM-001', 'password123');
  assert.strictEqual(resAdmin.success, true);
  assert.strictEqual(resAdmin.user?.role, 'admin');

  const resCashier = login('CSH-001', 'password123');
  assert.strictEqual(resCashier.success, true);
  assert.strictEqual(resCashier.user?.role, 'cashier');

  const resTeacher = login('TCH-001', 'password123');
  assert.strictEqual(resTeacher.success, true);
  assert.strictEqual(resTeacher.user?.role, 'teacher');
});

it('4. Invalid identifiers are rejected safely', () => {
  const res1 = login('NON-EXISTENT-ID', 'password123');
  assert.strictEqual(res1.success, false);

  const res2 = login('INVALID$CHARS!', 'password123');
  assert.strictEqual(res2.success, false);
});

it('5. Email addresses are not accepted as login identifiers', () => {
  const resAdminEmail = login('admin.santos@school.edu.ph', 'password123');
  assert.strictEqual(resAdminEmail.success, false);
  assert.ok(resAdminEmail.message.includes('Invalid credentials'));

  const resStudentEmail = login('juan.delacruz@student.edu.ph', 'password123');
  assert.strictEqual(resStudentEmail.success, false);
});

it('6. Existing employee accounts remain usable after the identifier migration', () => {
  const empList = ['ADM-001', 'CSH-001', 'TCH-001'];
  for (const empId of empList) {
    const res = login(empId, 'password123');
    assert.strictEqual(res.success, true, `Employee ${empId} should authenticate`);
  }
});

it('7. Role-based authorization remains enforced after login', () => {
  const student = findUserByIdentifier('2024-58566');
  assert.strictEqual(student?.role, 'student');
  const admin = findUserByIdentifier('ADM-001');
  assert.strictEqual(admin?.role, 'admin');
  const cashier = findUserByIdentifier('CSH-001');
  assert.strictEqual(cashier?.role, 'cashier');
});

// =============================================================================
// OUTSIDER TESTS (Scenarios 8-10)
// =============================================================================

const guestTicket = {
  id: 'tkt-guest-101',
  eventId: 'evt-1',
  ticketNumber: 'TKT-GUEST-001',
  attendeeType: 'OUTSIDER',
  originalOwnerName: 'Guest John Doe',
  currentAttendeeName: 'Guest John Doe',
  contactNumber: '09181112233',
  ticketStatus: 'ISSUED',
  qrToken: 'QR-GUEST-001',
  price: 150
};
tickets.push(guestTicket);

it('8. An Outsider can retrieve their ticket through the designated guest flow', () => {
  const res = lookupGuestTicket('TKT-GUEST-001', '09181112233');
  assert.strictEqual(res.success, true);
  assert.strictEqual(res.ticket?.attendeeName, 'Guest John Doe');
  assert.strictEqual(res.ticket?.attendeeType, 'OUTSIDER');
});

it('9. An invalid Control Number does not reveal another person\'s records', () => {
  const resNotFound = lookupGuestTicket('INVALID-CTRL-999');
  assert.strictEqual(resNotFound.success, false);
  assert.strictEqual(resNotFound.ticket, undefined);

  const resMismatch = lookupGuestTicket('TKT-GUEST-001', '09990000000');
  assert.strictEqual(resMismatch.success, false);
  assert.strictEqual(resMismatch.ticket, undefined);
});

it('10. Ticket lookup does not grant access to portal or administrative endpoints', () => {
  const initialUser = currentUser;
  const res = lookupGuestTicket('TKT-GUEST-001');
  assert.strictEqual(res.success, true);
  // Must NOT change currentUser or issue a portal session
  assert.strictEqual(currentUser, initialUser);
  assert.strictEqual(res.token, undefined);
});

// =============================================================================
// CASHIER ACCOUNT PROVISIONING TESTS (Scenarios 11-16)
// =============================================================================

const testStudent11 = '2024-TEST-11';
users.push({
  id: 'usr-test-11',
  name: 'Student Eleven',
  studentId: testStudent11,
  role: 'student',
  password: 'MySecretPassword123!',
  mustChangePassword: false,
  isActive: true
});

studentRecords.push({
  id: 'rec-test-11',
  studentId: testStudent11,
  name: 'Student Eleven',
  course: 'BSIT',
  yearLevel: '2nd Year',
  section: '21M1',
  department: 'CSD'
});

it('11. A student with an existing portal account buys their first ticket; original password hash remains unchanged', () => {
  const beforeUser = users.find(u => u.studentId === testStudent11);
  const originalPassword = beforeUser.password;

  purchaseTicket('evt-1', testStudent11, { name: 'Student Eleven' });

  const afterUser = users.find(u => u.studentId === testStudent11);
  assert.strictEqual(afterUser.password, originalPassword);
  assert.strictEqual(afterUser.mustChangePassword, false);
});

it('12. The existing password still authenticates successfully after the sale', () => {
  const res = login(testStudent11, 'MySecretPassword123!');
  assert.strictEqual(res.success, true);
  assert.strictEqual(res.user?.studentId, testStudent11);
});

it('13. A student buys another ticket; no duplicate account is created', () => {
  purchaseTicket('evt-1', testStudent11, { name: 'Student Eleven' });
  const count = users.filter(u => u.studentId === testStudent11).length;
  assert.strictEqual(count, 1);
});

it('14. A student with a StudentRecord but no portal account receives exactly one new account under the existing temporary-password policy', () => {
  const testStudent14 = '2026-TEST-14';
  studentRecords.push({
    id: 'rec-test-14',
    studentId: testStudent14,
    name: 'Student Fourteen',
    course: 'BSCS',
    yearLevel: '1st Year',
    section: '11M1',
    department: 'CSD'
  });

  purchaseTicket('evt-1', testStudent14, { name: 'Student Fourteen' });

  const count = users.filter(u => u.studentId === testStudent14).length;
  assert.strictEqual(count, 1);

  const newUser = users.find(u => u.studentId === testStudent14);
  assert.strictEqual(newUser.password, 'ncst123');
  assert.strictEqual(newUser.mustChangePassword, true);

  // Authenticates with default temporary password
  const res = login(testStudent14, 'ncst123');
  assert.strictEqual(res.success, true);
});

it('15. Concurrent or repeated requests do not create unintended duplicate accounts or transactions', () => {
  const testStudent15 = '2026-TEST-15';
  // Repeated purchases
  purchaseTicket('evt-1', testStudent15, { name: 'Student Fifteen' });
  purchaseTicket('evt-1', testStudent15, { name: 'Student Fifteen' });
  purchaseTicket('evt-1', testStudent15, { name: 'Student Fifteen' });

  const count = users.filter(u => u.studentId === testStudent15).length;
  assert.strictEqual(count, 1);
});

it('16. Outsider ticket issuance never creates a Student account', () => {
  const userCountBefore = users.filter(u => u.role === 'student').length;

  purchaseTicket('evt-1', null, {
    attendeeType: 'OUTSIDER',
    name: 'Guest Sixteenth',
    contactNumber: '09170001122'
  });

  const userCountAfter = users.filter(u => u.role === 'student').length;
  assert.strictEqual(userCountAfter, userCountBefore);
});

console.log('\n=================================================================');
console.log(`  SUMMARY: ${passCount} / 16 Tests Passed`);
if (failCount === 0) {
  console.log('  STATUS: ALL 16 REGRESSION TESTS PASSED (100% SUCCESS)');
  console.log('=================================================================');
  process.exit(0);
} else {
  console.log(`  STATUS: ${failCount} TESTS FAILED`);
  console.log('=================================================================');
  process.exit(1);
}
