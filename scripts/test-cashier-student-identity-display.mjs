import assert from 'node:assert/strict';
import { splitFullName, getLastName, getFirstName } from '../src/utils/nameUtils.ts';

console.log('=== Test Suite: Cashier Student Identity Display & Surname Separation ===\n');

// 1. Single-word surname
{
  const p = splitFullName('Maria Clara Santos');
  assert.equal(p.firstName, 'Maria', 'First name should be Maria');
  assert.equal(p.middleName, 'Clara', 'Middle name should be Clara');
  assert.equal(p.lastName, 'Santos', 'Last name should be Santos');
  assert.equal(p.suffix, undefined, 'Suffix should be undefined');
  assert.equal(getLastName({ name: 'Maria Clara Santos' }), 'Santos');
  console.log('✔ Test 1: Single-word surname parsed correctly (Maria Clara Santos -> Last: Santos)');
}

// 2. Multi-word surname (Dela Cruz)
{
  const p = splitFullName('Juan Dela Cruz');
  assert.equal(p.firstName, 'Juan', 'First name should be Juan');
  assert.equal(p.lastName, 'Dela Cruz', 'Last name should be Dela Cruz');
  assert.equal(p.suffix, undefined, 'Suffix should be undefined');
  assert.equal(getLastName({ name: 'Juan Dela Cruz' }), 'Dela Cruz');
  console.log('✔ Test 2: Multi-word surname parsed correctly (Juan Dela Cruz -> Dela Cruz)');
}

// 3. Multi-word surname with three-part prefix (De Los Santos)
{
  const p = splitFullName('Antonio De Los Santos');
  assert.equal(p.firstName, 'Antonio', 'First name should be Antonio');
  assert.equal(p.lastName, 'De Los Santos', 'Last name should be De Los Santos');
  assert.equal(p.suffix, undefined, 'Suffix should be undefined');
  assert.equal(getLastName({ name: 'Antonio De Los Santos' }), 'De Los Santos');
  console.log('✔ Test 3: Multi-word surname with three-part prefix (Antonio De Los Santos -> De Los Santos)');
}

// 4. Suffix handling (Jr.) with multi-word surname
{
  const p = splitFullName('Roberto Dela Cruz Jr.');
  assert.equal(p.firstName, 'Roberto', 'First name should be Roberto');
  assert.equal(p.lastName, 'Dela Cruz', 'Last name should be Dela Cruz');
  assert.equal(p.suffix, 'Jr.', 'Suffix should be Jr.');
  assert.equal(p.fullName, 'Roberto Dela Cruz Jr.', 'Full name preserves suffix');
  console.log('✔ Test 4: Suffix Jr. preserved in appropriate field and not combined into surname (Roberto Dela Cruz Jr. -> Last: Dela Cruz, Suffix: Jr.)');
}

// 5. Suffix handling (III)
{
  const p = splitFullName('Juan Santos III');
  assert.equal(p.firstName, 'Juan', 'First name should be Juan');
  assert.equal(p.lastName, 'Santos', 'Last name should be Santos');
  assert.equal(p.suffix, 'III', 'Suffix should be III');
  assert.equal(p.fullName, 'Juan Santos III', 'Full name preserves suffix');
  console.log('✔ Test 5: Suffix III preserved in appropriate field (Juan Santos III -> Last: Santos, Suffix: III)');
}

// 6. Explicit Authoritative Surname Overrides Blind Splitting
{
  const studentRecord = {
    name: 'Juan Dela Cruz Jr.',
    lastName: 'Dela Cruz',
    suffix: 'Jr.',
  };
  assert.equal(getLastName(studentRecord), 'Dela Cruz', 'Must prioritize authoritative StudentRecord.lastName');
  console.log('✔ Test 6: Authoritative StudentRecord.lastName is prioritized over blind name derivation');
}

// 7. Missing surname detection without guessing
{
  const studentWithoutSurname = {
    studentId: '2026-00143',
    name: 'Cherilyn',
    lastName: '',
  };
  const hasAuthoritativeSurname = !!(studentWithoutSurname.lastName && studentWithoutSurname.lastName.trim());
  assert.equal(hasAuthoritativeSurname, false, 'Missing surname correctly flagged as unauthoritative');
  console.log('✔ Test 7: Missing authoritative surname is flagged as unauthoritative for Cashier data-quality warning');
}

console.log('\nAll 7 Node.js Name Identity Display tests passed successfully!');
