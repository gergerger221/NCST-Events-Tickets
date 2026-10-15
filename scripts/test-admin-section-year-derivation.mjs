import assert from 'assert';
import {
  deriveYearLevelFromSection,
  validateNCSTSection,
  isValidNCSTSection,
  extractActualSystemSections,
  NCST_MASTER_SECTIONS,
  matchTeacherClassAssignment
} from '../src/utils/academicMasterData.ts';

console.log('=== RUNNING ADMIN SECTION & YEAR LEVEL DERIVATION TEST SUITE ===\n');

// 1. Year Level Derivation from NCST Section Code
console.log('1. Testing Year Level derivation from NCST section codes...');
assert.strictEqual(deriveYearLevelFromSection('11M1'), '1st Year');
assert.strictEqual(deriveYearLevelFromSection('12M2'), '1st Year');
assert.strictEqual(deriveYearLevelFromSection('21M1'), '2nd Year');
assert.strictEqual(deriveYearLevelFromSection('22M3'), '2nd Year');
assert.strictEqual(deriveYearLevelFromSection('31M1'), '3rd Year');
assert.strictEqual(deriveYearLevelFromSection('31A1'), '3rd Year');
assert.strictEqual(deriveYearLevelFromSection('32E2'), '3rd Year');
assert.strictEqual(deriveYearLevelFromSection('41M1'), '4th Year');
assert.strictEqual(deriveYearLevelFromSection('41M3'), '4th Year');
assert.strictEqual(deriveYearLevelFromSection('51M1'), '5th Year');
// Handles whitespace and lowercase
assert.strictEqual(deriveYearLevelFromSection('  31m2  '), '3rd Year');
// Fallback
assert.strictEqual(deriveYearLevelFromSection(''), '1st Year');
assert.strictEqual(deriveYearLevelFromSection(undefined), '1st Year');
console.log('   ✓ Year level derived correctly across all 1st-5th year codes.');

// 2. Section Validation & Rejection of Invalid Codes
console.log('2. Testing NCST Section Validation and rejection...');
const valid11M1 = validateNCSTSection('11M1');
assert.strictEqual(valid11M1.isValid, true);
assert.strictEqual(valid11M1.yearLevel, '1st Year');
assert.strictEqual(valid11M1.semester, '1st Semester');
assert.strictEqual(valid11M1.schedule, 'Morning');

const valid41M3 = validateNCSTSection('41M3');
assert.strictEqual(valid41M3.isValid, true);
assert.strictEqual(valid41M3.yearLevel, '4th Year');
assert.strictEqual(valid41M3.sectionNumber, 'Sec 3');

// Invalids
assert.strictEqual(validateNCSTSection('INVALID').isValid, false);
assert.strictEqual(validateNCSTSection('A2').isValid, false);
assert.strictEqual(validateNCSTSection('11X1').isValid, false); // 'X' is not M/A/E
assert.strictEqual(validateNCSTSection('61M1').isValid, false); // 6 is not year 1-5
assert.strictEqual(validateNCSTSection('13M1').isValid, false); // 3 is not semester 1-2
assert.strictEqual(validateNCSTSection('').isValid, false);
console.log('   ✓ Valid codes accepted and invalid/arbitrary codes rejected.');

// 3. Preset Section List Matching
console.log('3. Testing Authoritative System Preset Sections...');
const mockStudentRecords = [
  { section: '11M1' },
  { section: '31M1' },
  { section: '41M3' },
];
const mockUsers = [
  { section: '21M1' },
  { assignedClasses: [{ section: '31A1' }] }
];
const systemSections = extractActualSystemSections(mockStudentRecords, mockUsers);
assert.ok(systemSections.includes('11M1'));
assert.ok(systemSections.includes('21M1'));
assert.ok(systemSections.includes('31M1'));
assert.ok(systemSections.includes('31A1'));
assert.ok(systemSections.includes('41M3'));
assert.strictEqual(isValidNCSTSection('11M1', systemSections), true);
assert.strictEqual(isValidNCSTSection('41M3', systemSections), true);
assert.strictEqual(isValidNCSTSection('RANDOM_SECTION', systemSections), false);
console.log('   ✓ Preset sections correctly gathered and verified.');

// 4. Teacher Assignment & Roster Matching with Derived Year Level
console.log('4. Testing Teacher Class Assignment matching...');
const teacherAssignment = {
  department: 'CSD',
  course: 'BSIT',
  section: '31M1',
  yearLevel: deriveYearLevelFromSection('31M1'), // '3rd Year'
};
const studentA = {
  department: 'CSD',
  course: 'BSIT',
  section: '31M1',
  yearLevel: '3rd Year',
};
const studentB = {
  department: 'CSD',
  course: 'BSIT',
  section: '21M1',
  yearLevel: '2nd Year',
};
assert.strictEqual(matchTeacherClassAssignment(teacherAssignment, studentA), true);
assert.strictEqual(matchTeacherClassAssignment(teacherAssignment, studentB), false);
console.log('   ✓ Teacher roster filtering and class assignment matching verified.');

console.log('\n=== ALL ADMIN SECTION & YEAR LEVEL DERIVATION TESTS PASSED ===');
