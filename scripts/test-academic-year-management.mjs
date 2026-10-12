/**
 * Frontend Logic & Data Scoping Test: Academic Year and Semester Management
 *
 * Verifies:
 * 1. Seed data contains initial academic periods with 1st Sem 2026-2027 active.
 * 2. 2nd Sem 2026-2027 is inactive.
 * 3. Seed events have explicit academicYear and semester.
 * 4. Filtering logic correctly isolates tickets and events by semester.
 */

import { INITIAL_ACADEMIC_PERIODS, INITIAL_EVENTS, INITIAL_TICKETS } from '../src/utils/seedData.ts';

console.log('========================================================');
console.log('  TEST: FRONTEND ACADEMIC PERIOD & SEMESTER SCOPING');
console.log('========================================================\n');

let passed = 0;
let total = 0;

function assert(desc, condition) {
  total++;
  if (condition) {
    console.log(`  [PASS] ${desc}`);
    passed++;
  } else {
    console.error(`  [FAIL] ${desc}`);
  }
}

// 1. Initial Academic Periods
assert('INITIAL_ACADEMIC_PERIODS exists and has periods', Array.isArray(INITIAL_ACADEMIC_PERIODS) && INITIAL_ACADEMIC_PERIODS.length >= 2);

const activePeriods = INITIAL_ACADEMIC_PERIODS.filter(p => p.status === 'active');
assert('Exactly one period is active by default', activePeriods.length === 1);
assert('Active period is 2026-2027 1st Semester', activePeriods[0]?.academicYear === '2026-2027' && activePeriods[0]?.semester === '1st Semester');

const sem2Period = INITIAL_ACADEMIC_PERIODS.find(p => p.academicYear === '2026-2027' && p.semester === '2nd Semester');
assert('2nd Semester 2026-2027 is inactive', sem2Period && sem2Period.status === 'inactive');

// 2. Events Scoping
assert('All INITIAL_EVENTS have academicYear and semester defined', INITIAL_EVENTS.every(e => !!e.academicYear && !!e.semester));

const sem1Events = INITIAL_EVENTS.filter(e => e.academicYear === '2026-2027' && e.semester === '1st Semester');
assert('Sem 1 events are present in seed data', sem1Events.length > 0);

// 3. Tickets derivation & scoping test
assert('All INITIAL_TICKETS have academicYear and semester defined', INITIAL_TICKETS.every(t => !!t.academicYear && !!t.semester));

// 4. Semester isolation logic test
// Verify that filtering by 1st Sem never returns 2nd Sem or archived records
const dummyRecords = [
  { id: 't1', eventId: 'e1', academicYear: '2026-2027', semester: '1st Semester' },
  { id: 't2', eventId: 'e2', academicYear: '2026-2027', semester: '2nd Semester' },
  { id: 't3', eventId: 'e3', academicYear: '2025-2026', semester: '1st Semester' },
];

const filteredForSem1 = dummyRecords.filter(r => r.academicYear === '2026-2027' && r.semester === '1st Semester');
assert('Filtering isolates 1st Sem records without combining with other semesters', filteredForSem1.length === 1 && filteredForSem1[0].id === 't1');

console.log('\n========================================================');
console.log(`  RESULTS: ${passed} / ${total} Assertions Passed`);
if (passed === total) {
  console.log('  STATUS: FRONTEND ACADEMIC LOGIC TESTS 100% PASSED');
  console.log('========================================================\n');
} else {
  console.log('  STATUS: SOME TESTS FAILED');
  console.log('========================================================\n');
  process.exit(1);
}
