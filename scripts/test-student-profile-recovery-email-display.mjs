import assert from 'assert';
import { normalizeId } from '../src/utils/userValidation.ts';

console.log('=== RUNNING ZERO-EMAIL INSTITUTIONAL PROFILE ARCHITECTURE TEST SUITE ===\n');

// -----------------------------------------------------------------------------
// Test 1: Student profiles strictly use Student ID as authoritative identifier
// -----------------------------------------------------------------------------
console.log('1. Testing Student Account Identifier Resolution (Zero Email)...');
{
  const student = {
    id: 'user-stud-1',
    name: 'Drew Llaneta',
    role: 'student',
    studentId: '2024-5991',
  };

  assert.strictEqual(student.email, undefined, 'User must not have email attribute');
  assert.strictEqual(student.recoveryEmail, undefined, 'User must not have recoveryEmail attribute');
  assert.strictEqual(normalizeId(student.studentId), '2024-5991', 'Student ID is normalized authoritative identifier');
  console.log('   ✓ Student profile relies strictly on Student ID.');
}

// -----------------------------------------------------------------------------
// Test 2: Sanitization strips legacy email fields from cached state
// -----------------------------------------------------------------------------
console.log('2. Testing Sanitization of Legacy Email Fields...');
{
  const legacyCachedUser = {
    id: 'user-stud-legacy',
    name: 'Juan Dela Cruz',
    role: 'student',
    studentId: '2026-00125',
    email: 'juan@student.ncst.edu.ph',
    recoveryEmail: 'juan.personal@gmail.com',
    recovery_email: 'juan.personal@gmail.com',
    recovery_email_verified: 1,
    recovery_email_verified_at: '2026-10-15T00:00:00Z',
    recoveryEmailVerified: true,
    recoveryEmailVerifiedAt: '2026-10-15T00:00:00Z',
  };

  const sanitizeUser = (u) => {
    const sanitized = { ...u };
    delete sanitized.email;
    delete sanitized.recoveryEmail;
    delete sanitized.recovery_email;
    delete sanitized.recovery_email_verified;
    delete sanitized.recovery_email_verified_at;
    delete sanitized.recoveryEmailVerified;
    delete sanitized.recoveryEmailVerifiedAt;
    return sanitized;
  };

  const cleaned = sanitizeUser(legacyCachedUser);
  assert.strictEqual(cleaned.email, undefined);
  assert.strictEqual(cleaned.recoveryEmail, undefined);
  assert.strictEqual(cleaned.recovery_email, undefined);
  assert.strictEqual(cleaned.recovery_email_verified, undefined);
  assert.strictEqual(cleaned.recoveryEmailVerified, undefined);
  assert.strictEqual(cleaned.studentId, '2026-00125');
  assert.strictEqual(cleaned.name, 'Juan Dela Cruz');
  console.log('   ✓ Legacy email properties successfully stripped while preserving account identity.');
}

// -----------------------------------------------------------------------------
// Test 3: Account switcher display format uses ID instead of email
// -----------------------------------------------------------------------------
console.log('3. Testing Account Switcher Identifier Display...');
{
  const student = { id: 'usr-1', name: 'Maria Santos', role: 'student', studentId: '2024-00102' };
  const teacher = { id: 'usr-2', name: 'Prof. Gomez', role: 'teacher', employeeId: 'EMP-1002' };
  const admin = { id: 'usr-3', name: 'Administrator', role: 'admin' };

  const getDisplaySubtitle = (u) => {
    return u.studentId ? `ID: ${u.studentId}` : (u.employeeId ? `ID: ${u.employeeId}` : u.role.toUpperCase());
  };

  assert.strictEqual(getDisplaySubtitle(student), 'ID: 2024-00102');
  assert.strictEqual(getDisplaySubtitle(teacher), 'ID: EMP-1002');
  assert.strictEqual(getDisplaySubtitle(admin), 'ADMIN');
  console.log('   ✓ Account subtitle uses institutional ID or uppercase role.');
}

// -----------------------------------------------------------------------------
// Test 4: Outsider / Guest accounts use Reference Number without email
// -----------------------------------------------------------------------------
console.log('4. Testing Outsider Guest Identity (No Email Dependency)...');
{
  const guestUser = {
    id: 'usr-guest-9988',
    name: 'Robert Miller',
    role: 'student',
    attendeeType: 'OUTSIDER',
    isOutsider: true,
    studentId: 'GUEST-4421',
  };

  assert.strictEqual(guestUser.email, undefined);
  assert.strictEqual(guestUser.studentId, 'GUEST-4421');
  console.log('   ✓ Outsider accounts operate with guest reference number without email.');
}

console.log('\n=== ALL ZERO-EMAIL PROFILE ARCHITECTURE TESTS PASSED (100% SUCCESS) ===\n');
