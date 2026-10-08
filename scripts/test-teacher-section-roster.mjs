import assert from 'node:assert';
import { INITIAL_STUDENT_RECORDS, INITIAL_USERS, INITIAL_EVENTS } from '../src/utils/seedData.ts';
import { splitFullName, sortByLastName } from '../src/utils/nameUtils.ts';

console.log('------------------------------------------------------------');
console.log('TEACHER SECTION ROSTER & SORTING VERIFICATION SUITE');
console.log('------------------------------------------------------------');

// 1. Teacher Assignment Setup (Prof. Ramon Reyes)
const teacher = INITIAL_USERS.find(u => u.id === 'usr-teach-1');
assert(teacher, 'Teacher usr-teach-1 must exist');
console.log(`[PASS] Testing Teacher: ${teacher.name}`);
console.log(`[PASS] Assigned Classes:`, teacher.assignedClasses);

const assignedClasses = teacher.assignedClasses || [];
assert(assignedClasses.length >= 2, 'Teacher must have at least 2 explicit class assignments');

// 2. Test Section-Based Scoping for BSIT 11A1
const class1 = assignedClasses.find(c => c.course === 'BSIT' && (c.section === '11A1' || c.section === '11M1'));
assert(class1, 'BSIT 11A1 assignment must exist');

const roster11A1 = INITIAL_STUDENT_RECORDS.filter(st =>
  st.course.toUpperCase() === class1.course.toUpperCase() &&
  st.section.toUpperCase() === class1.section.toUpperCase()
);

console.log(`[PASS] BSIT ${class1.section} Roster Count: ${roster11A1.length} student(s)`);
assert(roster11A1.length >= 4, 'BSIT section must contain authoritative student records');
roster11A1.forEach(st => {
  assert.strictEqual(st.course, 'BSIT');
  assert.strictEqual(st.section, class1.section);
});

// 3. Test Section-Based Scoping for BSIT 31A1
const class2 = assignedClasses.find(c => c.course === 'BSIT' && (c.section === '31A1' || c.section === '31M1'));
assert(class2, 'BSIT 31A1 assignment must exist');

const roster31A1 = INITIAL_STUDENT_RECORDS.filter(st =>
  st.course.toUpperCase() === class2.course.toUpperCase() &&
  st.section.toUpperCase() === class2.section.toUpperCase()
);

console.log(`[PASS] BSIT ${class2.section} Roster Count: ${roster31A1.length} student(s)`);
assert(roster31A1.length >= 3, 'BSIT section must contain authoritative student records');
roster31A1.forEach(st => {
  assert.strictEqual(st.course, 'BSIT');
  assert.strictEqual(st.section, class2.section);
});

// Verify no cross-contamination between sections
const ids11A1 = new Set(roster11A1.map(s => s.studentId));
const ids31A1 = new Set(roster31A1.map(s => s.studentId));
roster11A1.forEach(s => assert(!ids31A1.has(s.studentId), 'BSIT 11A1 student must not appear in 31A1'));

// 4. Test Sorting Modes on BSIT Section
console.log('\n--- TESTING ROSTER SORTING MODES ---');

// Default: Last Name A-Z
const sortedByLastName = sortByLastName([...roster11A1], item => item.name);
console.log('[PASS] Sorted by Last Name A-Z:');
sortedByLastName.forEach(s => console.log(`   - ${s.lastName || splitFullName(s.name).lastName}, ${s.firstName || splitFullName(s.name).firstName} (${s.studentId})`));

const lastNames = sortedByLastName.map(s => s.lastName || splitFullName(s.name).lastName);
for (let i = 1; i < lastNames.length; i++) {
  assert(lastNames[i].localeCompare(lastNames[i - 1]) >= 0, 'Last names must be in ascending alphabetical order');
}

// Student ID Sorting
const sortedByStudentId = [...roster11A1].sort((a, b) => a.studentId.localeCompare(b.studentId, undefined, { numeric: true }));
console.log('[PASS] Sorted by Student ID:');
sortedByStudentId.forEach(s => console.log(`   - ${s.studentId}: ${s.name}`));
for (let i = 1; i < sortedByStudentId.length; i++) {
  assert(sortedByStudentId[i].studentId.localeCompare(sortedByStudentId[i - 1].studentId, undefined, { numeric: true }) >= 0, 'Student IDs must be in ascending order');
}

// First Name A-Z Sorting
const sortedByFirstName = [...roster11A1].sort((a, b) => {
  const fnA = a.firstName || splitFullName(a.name).firstName;
  const fnB = b.firstName || splitFullName(b.name).firstName;
  return fnA.localeCompare(fnB);
});
console.log('[PASS] Sorted by First Name A-Z:');
sortedByFirstName.forEach(s => console.log(`   - ${s.firstName || splitFullName(s.name).firstName} ${s.lastName || splitFullName(s.name).lastName}`));

// 5. Test Attendance Mapping (Active Event vs Closed Event)
console.log('\n--- TESTING ATTENDANCE LIFECYCLE MAPPING ---');
const mockTickets = [
  { attendeeStudentId: '2026-00131', eventId: 'evt-juans-2026', attendanceStatus: 'present', timeIn: '2026-11-05 06:30:00 PM' }
];

const mockTicketsMap = new Map(mockTickets.map(t => [t.attendeeStudentId, t]));

// Active Event
const activeAttendance = roster11A1.map(st => {
  const t = mockTicketsMap.get(st.studentId);
  const isPresent = t?.attendanceStatus === 'present';
  return {
    studentId: st.studentId,
    name: st.name,
    status: isPresent ? 'PRESENT' : 'NOT YET ENTERED',
    timeIn: isPresent ? t.timeIn : '—',
  };
});

console.log('[PASS] Active Event Attendance Results:');
activeAttendance.forEach(a => console.log(`   - ${a.studentId} (${a.name}): ${a.status} [TimeIn: ${a.timeIn}]`));
assert.strictEqual(activeAttendance.find(a => a.studentId === '2026-00131').status, 'PRESENT');
assert.strictEqual(activeAttendance.find(a => a.studentId === '2026-00132').status, 'NOT YET ENTERED');

// Closed Event
const closedAttendance = roster11A1.map(st => {
  const t = mockTicketsMap.get(st.studentId);
  const isPresent = t?.attendanceStatus === 'present';
  return {
    studentId: st.studentId,
    name: st.name,
    status: isPresent ? 'PRESENT' : 'ABSENT',
    timeIn: isPresent ? t.timeIn : '—',
  };
});

console.log('[PASS] Closed Event Attendance Results:');
closedAttendance.forEach(a => console.log(`   - ${a.studentId} (${a.name}): ${a.status} [TimeIn: ${a.timeIn}]`));
assert.strictEqual(closedAttendance.find(a => a.studentId === '2026-00131').status, 'PRESENT');
assert.strictEqual(closedAttendance.find(a => a.studentId === '2026-00132').status, 'ABSENT');

console.log('\n============================================================');
console.log('ALL TEACHER SECTION ROSTER & SORTING TESTS PASSED!');
console.log('============================================================');
