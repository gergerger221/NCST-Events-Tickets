import assert from 'assert';
import {
  validateStudentId,
  validateYearLevel,
  validateSection,
  validateAttendeeName,
  validatePhoneNumber,
  validateEmail,
  validateCashPayment,
  validateGuestReference,
  generateUniqueGuestReference,
  validatePosTransaction
} from '../src/utils/posValidation.ts';

console.log('=== RUNNING POS FIELD-LEVEL VALIDATION TESTS ===\n');

// 1. STUDENT ID VALIDATION
console.log('1. Testing Student ID Validation...');
{
  const valid1 = validateStudentId('2026-00125');
  assert.strictEqual(valid1.isValid, true, '2026-00125 must be valid');
  assert.strictEqual(valid1.normalized, '2026-00125');

  const valid2 = validateStudentId('  2025-1234  ');
  assert.strictEqual(valid2.isValid, true, 'Spaced 2025-1234 must normalize to valid');
  assert.strictEqual(valid2.normalized, '2025-1234');

  const letterErr = validateStudentId('2026-ABCDE');
  assert.strictEqual(letterErr.isValid, false, 'Student ID with letters must be rejected');
  assert.match(letterErr.error, /cannot contain letters/i);

  const symbolErr = validateStudentId('2026/00125');
  assert.strictEqual(symbolErr.isValid, false, 'Student ID with slash must be rejected');

  const formatErr = validateStudentId('123456');
  assert.strictEqual(formatErr.isValid, false, 'Raw numbers without hyphen must be rejected');

  const emptyErr = validateStudentId('');
  assert.strictEqual(emptyErr.isValid, false, 'Empty student ID must be rejected');

  console.log('   ✓ Student ID tests passed.');
}

// 2. YEAR LEVEL VALIDATION
console.log('2. Testing Year Level Validation...');
{
  assert.strictEqual(validateYearLevel('1st Year').isValid, true);
  assert.strictEqual(validateYearLevel('2nd Year').isValid, true);
  assert.strictEqual(validateYearLevel('3rd Year').isValid, true);
  assert.strictEqual(validateYearLevel('4th Year').isValid, true);

  assert.strictEqual(validateYearLevel('5th Year').isValid, false);
  assert.strictEqual(validateYearLevel('Freshman').isValid, false);
  assert.strictEqual(validateYearLevel('').isValid, false);

  console.log('   ✓ Year Level tests passed.');
}

// 3. SECTION VALIDATION (ALPHANUMERIC NCST FORMAT)
console.log('3. Testing Section Validation (Alphanumeric NCST)...');
{
  const sections = ['11M1', '21M2', '31M1', 'M1', 'M2', 'A1', 'E1', 'Section A', 'BSIT-3A'];
  for (const sec of sections) {
    const res = validateSection(sec);
    assert.strictEqual(res.isValid, true, `Section ${sec} should be valid`);
  }

  // Reject invalid symbols
  const invalidSec = validateSection('M1@#$%');
  assert.strictEqual(invalidSec.isValid, false, 'Section with illegal symbols must be rejected');

  const emptySec = validateSection('');
  assert.strictEqual(emptySec.isValid, false, 'Empty section must be rejected');

  console.log('   ✓ Section tests passed.');
}

// 4. CASH RECEIVED & CHANGE CALCULATION
console.log('4. Testing Cash Received & Change Validation...');
{
  // Sufficient payment
  const res1 = validateCashPayment('200', 150);
  assert.strictEqual(res1.isValid, true);
  assert.strictEqual(res1.cashReceivedNum, 200);
  assert.strictEqual(res1.change, 50);

  // Exact payment
  const res2 = validateCashPayment('150.00', 150);
  assert.strictEqual(res2.isValid, true);
  assert.strictEqual(res2.change, 0);

  // Insufficient payment
  const res3 = validateCashPayment('100', 150);
  assert.strictEqual(res3.isValid, false);
  assert.match(res3.error, /insufficient cash/i);

  // Letters in cash
  const res4 = validateCashPayment('two hundred', 150);
  assert.strictEqual(res4.isValid, false);
  assert.match(res4.error, /numeric monetary amount/i);

  // Free event (amountDue = 0)
  const res5 = validateCashPayment('0', 0);
  assert.strictEqual(res5.isValid, true);
  assert.strictEqual(res5.change, 0);

  console.log('   ✓ Cash Received & Change tests passed.');
}

// 5. PHONE NUMBER VALIDATION (PHILIPPINE FORMAT)
console.log('5. Testing Phone Number Validation...');
{
  const validPhones = ['09171234567', '+639171234567', '0918-987-6543', '0919 123 4567'];
  for (const p of validPhones) {
    const res = validatePhoneNumber(p, true);
    assert.strictEqual(res.isValid, true, `Phone ${p} must be valid`);
  }

  const letterPhone = validatePhoneNumber('0917ABC4567', true);
  assert.strictEqual(letterPhone.isValid, false, 'Phone with letters must fail');

  const shortPhone = validatePhoneNumber('091234', true);
  assert.strictEqual(shortPhone.isValid, false, 'Short phone must fail');

  console.log('   ✓ Phone Number tests passed.');
}

// 6. ATTENDEE NAME VALIDATION
console.log('6. Testing Attendee Name Validation...');
{
  const validNames = ['Juan Dela Cruz', "Maria O'Connor", 'Jose Rizal Jr.', 'Mary-Anne Santos', 'Iñigo Pascual'];
  for (const name of validNames) {
    const res = validateAttendeeName(name);
    assert.strictEqual(res.isValid, true, `Name ${name} must be valid`);
  }

  const digitName = validateAttendeeName('Juan2 Dela Cruz');
  assert.strictEqual(digitName.isValid, false, 'Name with numbers must fail');

  const shortName = validateAttendeeName('J');
  assert.strictEqual(shortName.isValid, false, 'Single char name must fail');

  console.log('   ✓ Attendee Name tests passed.');
}

// 7. GUEST REFERENCE GENERATION & VALIDATION
console.log('7. Testing Guest Reference Code...');
{
  const code = generateUniqueGuestReference();
  assert.match(code, /^GUEST-\d{4}$/, 'Generated code should match GUEST-XXXX format');
  assert.strictEqual(validateGuestReference(code).isValid, true);
  assert.strictEqual(validateGuestReference('GUEST-9999').isValid, true);
  assert.strictEqual(validateGuestReference('').isValid, false);

  console.log('   ✓ Guest Reference tests passed.');
}

// 8. OVERALL POS TRANSACTION INTEGRATION
console.log('8. Testing Full Pos Transaction Validation...');
{
  const validStudentTxn = validatePosTransaction({
    attendeeType: 'student',
    name: 'Juan Dela Cruz',
    studentId: '2026-00125',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '11M1',
    ticketPrice: 150,
    cashReceived: '200',
  });
  assert.strictEqual(validStudentTxn.isValid, true);
  assert.strictEqual(validStudentTxn.change, 50);

  const invalidStudentTxn = validatePosTransaction({
    attendeeType: 'student',
    name: 'Juan Dela Cruz',
    studentId: '2026-INVALID',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '11M1',
    ticketPrice: 150,
    cashReceived: '100', // insufficient
  });
  assert.strictEqual(invalidStudentTxn.isValid, false);
  assert.ok(invalidStudentTxn.errors.studentId);
  assert.ok(invalidStudentTxn.errors.cashReceived);

  console.log('   ✓ Full POS Transaction integration tests passed.');
}

console.log('\n🎉 ALL POS VALIDATION TESTS PASSED SUCCESSFULLY!');
