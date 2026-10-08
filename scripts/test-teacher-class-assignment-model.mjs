import assert from 'assert';
import { INITIAL_USERS, INITIAL_STUDENT_RECORDS, INITIAL_EVENTS, INITIAL_TICKETS } from '../src/utils/seedData.ts';
import { sortByLastName } from '../src/utils/nameUtils.ts';

console.log('================================================================');
console.log('NCST TEACHER CLASS ASSIGNMENT MODEL & SECURITY AUDIT SUITE');
console.log('================================================================\n');

// 1. Setup simulated store state
let users = JSON.parse(JSON.stringify(INITIAL_USERS));
let studentRecords = JSON.parse(JSON.stringify(INITIAL_STUDENT_RECORDS));
let tickets = JSON.parse(JSON.stringify(INITIAL_TICKETS));
let events = JSON.parse(JSON.stringify(INITIAL_EVENTS));

// Ensure comprehensive test student records
const sampleRoster = [
  { id: 'rec-1', studentId: '2026-00001', name: 'Santos, Juan', firstName: 'Juan', lastName: 'Santos', course: 'BSIT', section: '11M1', yearLevel: '1st Year' },
  { id: 'rec-2', studentId: '2026-00002', name: 'Alvarez, Carla', firstName: 'Carla', lastName: 'Alvarez', course: 'BSIT', section: '41M3', yearLevel: '4th Year' },
  { id: 'rec-3', studentId: '2026-00003', name: 'Bernardo, Kathryn', firstName: 'Kathryn', lastName: 'Bernardo', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' }, // In BSIT, but section NOT assigned
  { id: 'rec-4', studentId: '2026-00004', name: 'Cruz, Danica', firstName: 'Danica', lastName: 'Cruz', course: 'BSCS', section: '11M1', yearLevel: '1st Year' }, // In 11M1, but course NOT assigned
  { id: 'rec-5', studentId: '2026-00005', name: 'Dela Cruz, Mark', firstName: 'Mark', lastName: 'Dela Cruz', course: 'BSCS', section: '21M2', yearLevel: '2nd Year' }, // In BSCS 21M2
  { id: 'rec-6', studentId: '2026-00006', name: 'Zaragosa, Zeus', firstName: 'Zeus', lastName: 'Zaragosa', course: 'BSIT', section: '11M1', yearLevel: '1st Year' },
];

studentRecords = sampleRoster;

// Simulated Teacher: Prof. Santos
// Assigned to: BSIT / 11M1 and BSIT / 41M3 ONLY
const teacherProfSantos = {
  id: 'usr-teach-santos',
  name: 'Prof. Santos',
  role: 'teacher',
  employeeId: 'EMP-00999',
  assignedClasses: [
    { course: 'BSIT', section: '11M1', yearLevel: '1st Year' },
    { course: 'BSIT', section: '41M3', yearLevel: '4th Year' },
  ],
};

console.log('--- TEST 1: Teacher Class Scope Authorization Verification ---');

function getScopedStudents(teacher, selectedClassKey = 'ALL') {
  const teacherClasses = teacher.assignedClasses || [];
  return studentRecords.filter(student => {
    const sCourse = (student.course || '').trim().toLowerCase();
    const sSection = (student.section || '').trim().toUpperCase();

    if (!sCourse || !sSection) return false;

    if (selectedClassKey !== 'ALL') {
      const [selCourse, selSection] = selectedClassKey.split(':::');
      return sCourse === (selCourse || '').trim().toLowerCase() && sSection === (selSection || '').trim().toUpperCase();
    }

    return teacherClasses.some(
      ac => ac.course.trim().toLowerCase() === sCourse && ac.section.trim().toUpperCase() === sSection
    );
  });
}

// Check "ALL" scope for Prof. Santos:
const allAssigned = getScopedStudents(teacherProfSantos, 'ALL');
console.log(`Found ${allAssigned.length} students in ALL assigned classes.`);

// Must include rec-1 (BSIT 11M1), rec-2 (BSIT 41M3), rec-6 (BSIT 11M1)
assert.strictEqual(allAssigned.length, 3, 'Must match exactly 3 students across both assigned classes');
assert.ok(allAssigned.some(s => s.studentId === '2026-00001'), 'Includes BSIT 11M1 student Juan Santos');
assert.ok(allAssigned.some(s => s.studentId === '2026-00002'), 'Includes BSIT 41M3 student Carla Alvarez');
assert.ok(allAssigned.some(s => s.studentId === '2026-00006'), 'Includes BSIT 11M1 student Zeus Zaragosa');

// SECURITY CHECK: Must NOT include cross-combinations or unassigned sections
assert.ok(!allAssigned.some(s => s.studentId === '2026-00003'), 'BLOCKED: Must NOT see BSIT / 31M1 (unassigned section)');
assert.ok(!allAssigned.some(s => s.studentId === '2026-00004'), 'BLOCKED: Must NOT see BSCS / 11M1 (unassigned course)');
assert.ok(!allAssigned.some(s => s.studentId === '2026-00005'), 'BLOCKED: Must NOT see BSCS / 21M2 (unassigned course and section)');
console.log('✅ Security isolation passed: Cross-combinations and unassigned sections strictly blocked.\n');

console.log('--- TEST 2: Active Class Selector Filtering & Reactivity ---');

// Select BSIT — 11M1
const class11M1 = getScopedStudents(teacherProfSantos, 'BSIT:::11M1');
assert.strictEqual(class11M1.length, 2, 'BSIT 11M1 has exactly 2 students');
assert.ok(class11M1.every(s => s.course === 'BSIT' && s.section === '11M1'), 'All students in BSIT 11M1 scope');
console.log('✅ Selector [ BSIT — 11M1 ] correctly filters to 2 students.');

// Select BSIT — 41M3
const class41M3 = getScopedStudents(teacherProfSantos, 'BSIT:::41M3');
assert.strictEqual(class41M3.length, 1, 'BSIT 41M3 has exactly 1 student');
assert.strictEqual(class41M3[0].studentId, '2026-00002', 'Contains Carla Alvarez');
console.log('✅ Selector [ BSIT — 41M3 ] correctly filters to 1 student.\n');

console.log('--- TEST 3: Attendance Status & Live Counters per Selected Class ---');

const eventId = 'evt-juans-2026';
// Simulate tickets for event:
// Juan Santos (2026-00001): PRESENT
// Zeus Zaragosa (2026-00006): NOT ENTERED
// Carla Alvarez (2026-00002): PRESENT
const eventTickets = [
  { id: 'tix-1', eventId, attendeeStudentId: '2026-00001', attendanceStatus: 'present', ticketStatus: 'USED', timeIn: '2026-11-05T18:15:00Z' },
  { id: 'tix-2', eventId, attendeeStudentId: '2026-00002', attendanceStatus: 'present', ticketStatus: 'USED', timeIn: '2026-11-05T18:30:00Z' },
  { id: 'tix-3', eventId, attendeeStudentId: '2026-00006', attendanceStatus: 'not_entered', ticketStatus: 'ISSUED' },
];

function calculateAttendanceMetrics(students, tickets) {
  let presentCount = 0;
  let notEnteredCount = 0;

  const roster = students.map(student => {
    const ticket = tickets.find(t => t.attendeeStudentId === student.studentId);
    const isPresent = ticket?.attendanceStatus === 'present' || ticket?.ticketStatus === 'USED';
    if (isPresent) presentCount++;
    else notEnteredCount++;
    return { student, ticket, isPresent, timeIn: ticket?.timeIn };
  });

  return {
    totalAssigned: students.length,
    presentCount,
    notEnteredCount,
    attendanceRate: students.length > 0 ? Math.round((presentCount / students.length) * 100) : 0,
    roster,
  };
}

// 1. Metrics for BSIT 11M1: Total 2, Present 1, Not Entered 1, Rate 50%
const metrics11M1 = calculateAttendanceMetrics(class11M1, eventTickets);
assert.strictEqual(metrics11M1.totalAssigned, 2);
assert.strictEqual(metrics11M1.presentCount, 1);
assert.strictEqual(metrics11M1.notEnteredCount, 1);
assert.strictEqual(metrics11M1.attendanceRate, 50);
console.log('✅ BSIT 11M1 Metrics: Total=2, Present=1, Not Yet Entered=1 (50% Rate)');

// 2. Metrics for BSIT 41M3: Total 1, Present 1, Not Entered 0, Rate 100%
const metrics41M3 = calculateAttendanceMetrics(class41M3, eventTickets);
assert.strictEqual(metrics41M3.totalAssigned, 1);
assert.strictEqual(metrics41M3.presentCount, 1);
assert.strictEqual(metrics41M3.notEnteredCount, 0);
assert.strictEqual(metrics41M3.attendanceRate, 100);
console.log('✅ BSIT 41M3 Metrics: Total=1, Present=1, Not Yet Entered=0 (100% Rate)\n');

console.log('--- TEST 4: Student Last Name Alphabetical Sorting (A–Z) ---');

const sortedRoster = sortByLastName(class11M1, s => s);
assert.strictEqual(sortedRoster[0].lastName, 'Santos', 'Santos comes first alphabetically before Zaragosa');
assert.strictEqual(sortedRoster[1].lastName, 'Zaragosa', 'Zaragosa comes second alphabetically');
console.log(`✅ Alphabetical Sort Verified: 1. ${sortedRoster[0].name} -> 2. ${sortedRoster[1].name}\n`);

console.log('================================================================');
console.log('ALL TEACHER ASSIGNMENT MODEL TESTS COMPLETED SUCCESSFULLY (4/4)');
console.log('================================================================');
