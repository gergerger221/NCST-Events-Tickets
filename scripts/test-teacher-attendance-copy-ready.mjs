import assert from 'assert';
import { sortByLastName } from '../src/utils/nameUtils.ts';

console.log('================================================================');
console.log('NCST TEACHER ATTENDANCE REPORTING & COPY-READY TSV TEST SUITE');
console.log('================================================================\n');

// 1. Setup 5 Authoritative Class Roster Students
const testClassRoster = [
  { id: 'usr-1', studentId: '2026-00001', name: 'Dela Cruz, Juan', firstName: 'Juan', lastName: 'Dela Cruz', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' },
  { id: 'usr-2', studentId: '2026-00002', name: 'Santos, Maria', firstName: 'Maria', lastName: 'Santos', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' },
  { id: 'usr-3', studentId: '2026-00003', name: 'Penduko, Pedro', firstName: 'Pedro', lastName: 'Penduko', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' },
  { id: 'usr-4', studentId: '2026-00004', name: 'Reyes, Ana', firstName: 'Ana', lastName: 'Reyes', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' },
  { id: 'usr-5', studentId: '2026-00005', name: 'Zaragosa, Zeus', firstName: 'Zeus', lastName: 'Zaragosa', course: 'BSIT', section: '31M1', yearLevel: '3rd Year' },
];

const eventId = 'evt-juans-2026';

// 2. Scans: Student A, B, E have valid scans. C, D have no scan.
const activeEventTickets = [
  { id: 'tix-1', eventId, attendeeStudentId: '2026-00001', attendanceStatus: 'present', ticketStatus: 'USED', timeIn: '2026-11-05T18:15:00Z' },
  { id: 'tix-2', eventId, attendeeStudentId: '2026-00002', attendanceStatus: 'present', ticketStatus: 'USED', timeIn: '2026-11-05T18:30:00Z' },
  { id: 'tix-5', eventId, attendeeStudentId: '2026-00005', attendanceStatus: 'present', ticketStatus: 'USED', timeIn: '2026-11-05T18:45:00Z' },
];

function generateAttendanceReport(roster, tickets, isClosed = false) {
  const mapped = roster.map(student => {
    const ticket = tickets.find(t => t.attendeeStudentId === student.studentId);
    const isPresent = ticket?.attendanceStatus === 'present' || ticket?.ticketStatus === 'USED';
    const status = isPresent ? 'PRESENT' : (isClosed ? 'ABSENT' : 'NOT YET ENTERED');
    const timeIn = isPresent ? (ticket?.timeIn || '—') : '—';

    return {
      student,
      ticket,
      isPresent,
      status,
      timeIn,
    };
  });

  // Sort alphabetically by last name A-Z
  return sortByLastName(mapped, item => item.student);
}

console.log('--- TEST 1: During Active Event Attendance States ---');
const activeReport = generateAttendanceReport(testClassRoster, activeEventTickets, false);

assert.strictEqual(activeReport.length, 5, 'Roster report must include all 5 students');
const activeStatusMap = Object.fromEntries(activeReport.map(r => [r.student.studentId, r.status]));

assert.strictEqual(activeStatusMap['2026-00001'], 'PRESENT', 'Student A (Juan Dela Cruz) is PRESENT');
assert.strictEqual(activeStatusMap['2026-00002'], 'PRESENT', 'Student B (Maria Santos) is PRESENT');
assert.strictEqual(activeStatusMap['2026-00003'], 'NOT YET ENTERED', 'Student C (Pedro Penduko) is NOT YET ENTERED during active event');
assert.strictEqual(activeStatusMap['2026-00004'], 'NOT YET ENTERED', 'Student D (Ana Reyes) is NOT YET ENTERED during active event');
assert.strictEqual(activeStatusMap['2026-00005'], 'PRESENT', 'Student E (Zeus Zaragosa) is PRESENT');

console.log('✅ Active Event: 3 PRESENT, 2 NOT YET ENTERED.\n');

console.log('--- TEST 2: After Attendance is Closed (Event Completed/Archived) ---');
const closedReport = generateAttendanceReport(testClassRoster, activeEventTickets, true);

assert.strictEqual(closedReport.length, 5, 'Roster report must include all 5 students');
const closedStatusMap = Object.fromEntries(closedReport.map(r => [r.student.studentId, r.status]));

assert.strictEqual(closedStatusMap['2026-00001'], 'PRESENT', 'Student A (Juan Dela Cruz) is PRESENT');
assert.strictEqual(closedStatusMap['2026-00002'], 'PRESENT', 'Student B (Maria Santos) is PRESENT');
assert.strictEqual(closedStatusMap['2026-00003'], 'ABSENT', 'Student C (Pedro Penduko) is ABSENT after closing');
assert.strictEqual(closedStatusMap['2026-00004'], 'ABSENT', 'Student D (Ana Reyes) is ABSENT after closing');
assert.strictEqual(closedStatusMap['2026-00005'], 'PRESENT', 'Student E (Zeus Zaragosa) is PRESENT');

console.log('✅ Closed Event: 3 PRESENT, 2 ABSENT.\n');

console.log('--- TEST 3: Copy Attendance TSV (Tab-Separated Values for Excel) ---');
function generateCopyAttendanceTSV(report) {
  const header = ['Student ID', 'Student Name', 'Course', 'Year Level', 'Section', 'Attendance Status', 'Time In'].join('\t');
  const rows = report.map(item => {
    const s = item.student;
    return [
      s.studentId,
      s.name,
      s.course,
      s.yearLevel,
      s.section,
      item.status,
      item.timeIn,
    ].join('\t');
  });
  return [header, ...rows].join('\n');
}

const tsvOutput = generateCopyAttendanceTSV(closedReport);
const lines = tsvOutput.split('\n');

assert.strictEqual(lines.length, 6, 'TSV must contain 1 header line and 5 student lines');
assert.strictEqual(lines[0], 'Student ID\tStudent Name\tCourse\tYear Level\tSection\tAttendance Status\tTime In');

// Verify columns in every row
for (let i = 1; i < lines.length; i++) {
  const cols = lines[i].split('\t');
  assert.strictEqual(cols.length, 7, `Row ${i} must have exactly 7 tab-separated columns`);
}

// Verify Alphabetical sorting: Dela Cruz -> Penduko -> Reyes -> Santos -> Zaragosa
const sortedLastNames = lines.slice(1).map(l => l.split('\t')[1].split(',')[0].trim());
assert.deepStrictEqual(sortedLastNames, ['Dela Cruz', 'Penduko', 'Reyes', 'Santos', 'Zaragosa']);

console.log('TSV Copy Preview:\n' + tsvOutput + '\n');
console.log('✅ Copy Attendance TSV format and sorting validated.\n');

console.log('================================================================');
console.log('ALL TEACHER ATTENDANCE COPY-READY TESTS PASSED (3/3)');
console.log('================================================================');
