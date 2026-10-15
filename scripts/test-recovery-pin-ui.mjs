import assert from 'node:assert';

console.log('========================================================');
console.log('  NCST Recovery PIN & Self-Service UI Logic Test Suite');
console.log('========================================================\n');

// 1. PIN numeric and 4-digit validation
function isValidPin(pin) {
  return /^\d{4}$/.test(pin.trim());
}

assert.strictEqual(isValidPin('1234'), true, 'Valid 4-digit PIN should pass');
assert.strictEqual(isValidPin('0000'), true, 'Valid 4 zeros PIN should pass');
assert.strictEqual(isValidPin('9999'), true, 'Valid 4 nines PIN should pass');
assert.strictEqual(isValidPin('123'), false, '3-digit PIN should fail');
assert.strictEqual(isValidPin('12345'), false, '5-digit PIN should fail');
assert.strictEqual(isValidPin('abcd'), false, 'Alphabetical PIN should fail');
assert.strictEqual(isValidPin('12a4'), false, 'Alphanumeric PIN should fail');
assert.strictEqual(isValidPin(''), false, 'Empty PIN should fail');
console.log('  [PASS] 1. PIN numeric and 4-digit regex validation');

// 2. PIN and Confirm PIN equality
function validatePinMatch(pin, confirmPin) {
  if (!isValidPin(pin)) return { valid: false, error: 'PIN must be exactly 4 numeric digits.' };
  if (!isValidPin(confirmPin)) return { valid: false, error: 'Confirm PIN must be exactly 4 numeric digits.' };
  if (pin !== confirmPin) return { valid: false, error: 'Recovery PIN entries do not match.' };
  return { valid: true };
}

assert.strictEqual(validatePinMatch('4829', '4829').valid, true);
assert.strictEqual(validatePinMatch('4829', '4828').valid, false);
assert.strictEqual(validatePinMatch('4829', '4828').error, 'Recovery PIN entries do not match.');
console.log('  [PASS] 2. PIN and Confirm PIN matching verification');

// 3. New password validation policy (no surname, no generic defaults, min 4 chars)
function validateNewPasswordPolicy(password, confirmPassword, surname) {
  const p = password.trim();
  const c = confirmPassword.trim();
  const s = (surname || '').trim().toLowerCase();

  if (p !== c) return { valid: false, error: 'Passwords do not match.' };
  if (p.length < 4) return { valid: false, error: 'Password must be at least 4 characters long.' };
  if (s && p.toLowerCase() === s) return { valid: false, error: 'New password cannot be your surname. Please choose a secure custom password.' };
  if (['ncst123', 'password123'].includes(p.toLowerCase())) return { valid: false, error: 'New password cannot be a generic default password.' };

  return { valid: true };
}

assert.strictEqual(validateNewPasswordPolicy('SecretPass!', 'SecretPass!', 'Mendoza').valid, true);
assert.strictEqual(validateNewPasswordPolicy('mendoza', 'mendoza', 'Mendoza').valid, false);
assert.strictEqual(validateNewPasswordPolicy('MENDOZA', 'MENDOZA', 'mendoza').valid, false);
assert.strictEqual(validateNewPasswordPolicy('123', '123', 'Smith').valid, false);
assert.strictEqual(validateNewPasswordPolicy('password123', 'password123', 'Smith').valid, false);
assert.strictEqual(validateNewPasswordPolicy('Pass1', 'Pass2', 'Smith').valid, false);
console.log('  [PASS] 3. Password policy compliance (surname restriction & minimum length)');

// 4. Cooldown timer display logic
function formatCooldown(seconds) {
  if (seconds <= 0) return '0s';
  const m = Math.floor(seconds / 60);
  const s = seconds % 60;
  if (m > 0) {
    return `${m}m ${s.toString().padStart(2, '0')}s`;
  }
  return `${s}s`;
}

assert.strictEqual(formatCooldown(300), '5m 00s');
assert.strictEqual(formatCooldown(65), '1m 05s');
assert.strictEqual(formatCooldown(45), '45s');
assert.strictEqual(formatCooldown(0), '0s');
console.log('  [PASS] 4. Rate-limit lockout countdown formatting');

// 5. User sanitization hasRecoveryPin mapping
function mockSanitizeUser(user) {
  const sanitized = { ...user };
  sanitized.hasRecoveryPin = Boolean(user.recovery_pin_hash || user.hasRecoveryPin);
  delete sanitized.password;
  delete sanitized.recovery_pin_hash;
  return sanitized;
}

const enrolledUser = mockSanitizeUser({ id: 'u1', name: 'Student 1', recovery_pin_hash: '$2y$10$xyz', role: 'student' });
assert.strictEqual(enrolledUser.hasRecoveryPin, true);
assert.strictEqual(enrolledUser.recovery_pin_hash, undefined);
assert.strictEqual(enrolledUser.password, undefined);

const nonEnrolledUser = mockSanitizeUser({ id: 'u2', name: 'Guest 1', recovery_pin_hash: null, role: 'outsider' });
assert.strictEqual(nonEnrolledUser.hasRecoveryPin, false);
assert.strictEqual(nonEnrolledUser.recovery_pin_hash, undefined);
console.log('  [PASS] 5. User serialization & secure hash redaction');

console.log('\n========================================================');
console.log('  ALL 5 FRONTEND RECOVERY PIN LOGIC CHECKS PASSED');
console.log('========================================================');
