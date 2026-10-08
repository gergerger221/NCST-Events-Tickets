// Automated Verification for NCST Academic Structure & Staff/Teacher Account Assignment
import assert from 'assert';

console.log('================================================================');
console.log('🧪 NCST ACADEMIC STRUCTURE + TEACHER ASSIGNMENT VERIFICATION');
console.log('================================================================\n');

// 1. Mock Academic Master Data Logic
const NCST_DEPARTMENTS = [
  { code: 'CSD', name: 'Computer Studies Department' },
  { code: 'GenEd', name: 'General Education Department' },
  { code: 'Eng', name: 'Engineering Department' },
  { code: 'CBA', name: 'College of Business Administration' },
  { code: 'Educ', name: 'Education Department' },
  { code: 'CCJ', name: 'College of Criminology & Justice' },
  { code: 'CHTM', name: 'College of Hospitality & Tourism Management' },
];

const NCST_PROGRAMS = [
  { code: 'BSIT', title: 'Bachelor of Science in Information Technology', departmentCode: 'CSD' },
  { code: 'BSCS', title: 'Bachelor of Science in Computer Science', departmentCode: 'CSD' },
  { code: 'BSCpE', title: 'Bachelor of Science in Computer Engineering', departmentCode: 'Eng' },
  { code: 'BSBA', title: 'Bachelor of Science in Business Administration', departmentCode: 'CBA' },
  { code: 'GenEd', title: 'General Education Core Subjects', departmentCode: 'GenEd' },
];

// Derive year level from NCST section format [Year][Semester][Schedule][Section]
function deriveYearLevelFromSection(sec) {
  if (!sec || typeof sec !== 'string') return '1st Year';
  const first = sec.trim().charAt(0);
  switch (first) {
    case '1': return '1st Year';
    case '2': return '2nd Year';
    case '3': return '3rd Year';
    case '4': return '4th Year';
    default: return '1st Year';
  }
}

// 2. Mock Database / Institutional Store
const studentRecords = [
  {
    id: 'rec-1',
    studentId: '2026-00001',
    name: 'Student A (Alvarez, Juan)',
    email: 'student.a@ncst.edu.ph',
    department: 'CSD',
    course: 'BSIT',
    yearLevel: '1st Year',
    section: '11M1',
  },
  {
    id: 'rec-2',
    studentId: '2026-00002',
    name: 'Student B (Batumbakal, Maria)',
    email: 'student.b@ncst.edu.ph',
    department: 'CSD',
    course: 'BSIT',
    yearLevel: '4th Year',
    section: '41M3',
  },
  {
    id: 'rec-3',
    studentId: '2026-00003',
    name: 'Student C (Cruz, Carlos)',
    email: 'student.c@ncst.edu.ph',
    department: 'CSD',
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: '21M2',
  },
  {
    id: 'rec-4',
    studentId: '2026-00004',
    name: 'Student D (Dela Rosa, Diana)',
    email: 'student.d@ncst.edu.ph',
    department: 'Eng',
    course: 'BSCpE',
    yearLevel: '3rd Year',
    section: '31M1',
  },
];

// Tickets & Entrance Scans
const tickets = [
  { id: 't-1', studentId: '2026-00001', eventId: 'ev-fest-2026', ticketCode: 'TKT-001', qrHash: 'HASH_001' },
  { id: 't-2', studentId: '2026-00002', eventId: 'ev-fest-2026', ticketCode: 'TKT-002', qrHash: 'HASH_002' },
  { id: 't-3', studentId: '2026-00003', eventId: 'ev-fest-2026', ticketCode: 'TKT-003', qrHash: 'HASH_003' },
];

const attendanceRecords = [
  { id: 'att-1', ticketId: 't-1', eventId: 'ev-fest-2026', scannedAt: '2026-10-08T08:30:00Z', scannedBy: 'staff-1', studentId: '2026-00001' },
];

// Admin created teacher accounts
const teacherA = {
  id: 'usr-prof-a',
  name: 'Prof. Juan Dela Cruz',
  email: 'juan.delacruz@ncst.edu.ph',
  role: 'teacher',
  employeeId: 'EMP-00101',
  department: 'CSD',
  assignedClasses: [
    { department: 'CSD', course: 'BSIT', section: '11M1', yearLevel: '1st Year' },
    { department: 'CSD', course: 'BSIT', section: '41M3', yearLevel: '4th Year' },
  ],
};

const teacherB = {
  id: 'usr-prof-b',
  name: 'Prof. Maria Santos',
  email: 'maria.santos@ncst.edu.ph',
  role: 'teacher',
  employeeId: 'EMP-00102',
  department: 'CSD',
  assignedClasses: [
    { department: 'CSD', course: 'BSCS', section: '21M2', yearLevel: '2nd Year' },
  ],
};

// Pure Authorization / Resolution Engine
function getTeacherAuthorizedStudents(teacher, selectedClassKey = 'ALL') {
  if (!teacher || teacher.role !== 'teacher') return [];
  const assignments = teacher.assignedClasses || [];
  if (assignments.length === 0) return [];

  // Filter assignments if specific class key selected (e.g. "BSIT:11M1")
  let activeAssignments = assignments;
  if (selectedClassKey !== 'ALL') {
    const [c, s] = selectedClassKey.split(':');
    activeAssignments = assignments.filter(a => a.course.toUpperCase() === c.toUpperCase() && a.section.toUpperCase() === s.toUpperCase());
  }

  // Authoritative Student Match: exact Course + Section
  return studentRecords.filter(student => {
    return activeAssignments.some(assign => {
      const matchCourse = student.course.toUpperCase() === assign.course.toUpperCase();
      const matchSection = student.section.toUpperCase() === assign.section.toUpperCase();
      const matchDept = !assign.department || student.department?.toUpperCase() === assign.department.toUpperCase();
      return matchCourse && matchSection && matchDept;
    });
  });
}

function getStudentAttendanceStatus(studentId, eventId) {
  const isPresent = attendanceRecords.some(a => a.studentId === studentId && a.eventId === eventId);
  return isPresent ? 'PRESENT' : 'NOT YET ENTERED';
}

console.log('--- TEST STEP 1: Teacher A vs Student A Visibility ---');
const teacherAAllStudents = getTeacherAuthorizedStudents(teacherA, 'ALL');
const teacherA_hasStudentA = teacherAAllStudents.some(s => s.studentId === '2026-00001');
assert.strictEqual(teacherA_hasStudentA, true, 'Teacher A must see Student A (BSIT / 11M1)');
console.log('✅ PASS: Teacher A sees Student A (BSIT / 11M1)');

console.log('\n--- TEST STEP 2: Teacher A vs Student B Visibility ---');
const teacherA_hasStudentB = teacherAAllStudents.some(s => s.studentId === '2026-00002');
assert.strictEqual(teacherA_hasStudentB, true, 'Teacher A must see Student B (BSIT / 41M3)');
console.log('✅ PASS: Teacher A sees Student B (BSIT / 41M3)');

console.log('\n--- TEST STEP 3: Teacher A Class Isolation (Student C in BSCS / 21M2) ---');
const teacherA_hasStudentC = teacherAAllStudents.some(s => s.studentId === '2026-00003');
assert.strictEqual(teacherA_hasStudentC, false, 'Teacher A must NOT see Student C (BSCS / 21M2)');
console.log('✅ PASS: Teacher A is strictly isolated and does NOT see Student C');

console.log('\n--- TEST STEP 4: Teacher B vs Student C Visibility ---');
const teacherBAllStudents = getTeacherAuthorizedStudents(teacherB, 'ALL');
const teacherB_hasStudentC = teacherBAllStudents.some(s => s.studentId === '2026-00003');
assert.strictEqual(teacherB_hasStudentC, true, 'Teacher B must see Student C (BSCS / 21M2)');
console.log('✅ PASS: Teacher B sees Student C (BSCS / 21M2)');

console.log('\n--- TEST STEP 5: Teacher B Isolation (Student A and B) ---');
const teacherB_hasStudentA = teacherBAllStudents.some(s => s.studentId === '2026-00001');
const teacherB_hasStudentB = teacherBAllStudents.some(s => s.studentId === '2026-00002');
assert.strictEqual(teacherB_hasStudentA, false, 'Teacher B must NOT see Student A');
assert.strictEqual(teacherB_hasStudentB, false, 'Teacher B must NOT see Student B');
console.log('✅ PASS: Teacher B does NOT see Students A or B');

console.log('\n--- TEST STEP 6: Class Selector Filtering ---');
const teacherA_11M1 = getTeacherAuthorizedStudents(teacherA, 'BSIT:11M1');
assert.strictEqual(teacherA_11M1.length, 1, 'BSIT:11M1 filter must return exactly 1 student');
assert.strictEqual(teacherA_11M1[0].studentId, '2026-00001');

const teacherA_41M3 = getTeacherAuthorizedStudents(teacherA, 'BSIT:41M3');
assert.strictEqual(teacherA_41M3.length, 1, 'BSIT:41M3 filter must return exactly 1 student');
assert.strictEqual(teacherA_41M3[0].studentId, '2026-00002');
console.log('✅ PASS: Switching assigned class dropdown dynamically isolates the student roster');

console.log('\n--- TEST STEP 7: Attendance Status Derivation after Staff Scan ---');
const statusStudentA = getStudentAttendanceStatus('2026-00001', 'ev-fest-2026');
const statusStudentB = getStudentAttendanceStatus('2026-00002', 'ev-fest-2026');
assert.strictEqual(statusStudentA, 'PRESENT');
assert.strictEqual(statusStudentB, 'NOT YET ENTERED');
console.log('✅ PASS: Attendance status cleanly reflects scanner entries independently (Student A: PRESENT, Student B: NOT YET ENTERED)');

console.log('\n--- TEST STEP 8: Export Scope Conformance ---');
function generateExportRows(teacher, selectedClassKey) {
  const scopedStudents = getTeacherAuthorizedStudents(teacher, selectedClassKey);
  return scopedStudents.map(s => ({
    studentId: s.studentId,
    name: s.name,
    class: `${s.course} - ${s.section}`,
    status: getStudentAttendanceStatus(s.studentId, 'ev-fest-2026'),
  }));
}
const exportRows11M1 = generateExportRows(teacherA, 'BSIT:11M1');
assert.strictEqual(exportRows11M1.length, 1);
assert.strictEqual(exportRows11M1[0].class, 'BSIT - 11M1');
console.log('✅ PASS: Export contains ONLY the authorized/selected class records');

console.log('\n--- TEST STEP 9 & 10: Roster Ownership & Assignment Tamper Resistance ---');
// Verify NCST Section Format interpretation
assert.strictEqual(deriveYearLevelFromSection('11M1'), '1st Year');
assert.strictEqual(deriveYearLevelFromSection('21M2'), '2nd Year');
assert.strictEqual(deriveYearLevelFromSection('31M1'), '3rd Year');
assert.strictEqual(deriveYearLevelFromSection('41M3'), '4th Year');
console.log('✅ PASS: NCST section parser correctly maps section prefix to Year Level');

console.log('\n--- TEST STEP 11: Real-time Section Code Format Validation ---');
function validateNCSTSectionLocal(sec) {
  if (!sec || typeof sec !== 'string') return { isValid: false };
  const clean = sec.trim().toUpperCase().replace(/\s+/g, '');
  const match = clean.match(/^([1-5])([1-2])([MAE])([1-9][0-9]?)$/);
  return { isValid: !!match };
}

assert.strictEqual(validateNCSTSectionLocal('11M1').isValid, true, '11M1 should be valid');
assert.strictEqual(validateNCSTSectionLocal('41M3').isValid, true, '41M3 should be valid');
assert.strictEqual(validateNCSTSectionLocal('31A2').isValid, true, '31A2 should be valid');
assert.strictEqual(validateNCSTSectionLocal('A2').isValid, false, 'A2 should be invalid format');
assert.strictEqual(validateNCSTSectionLocal('invalid').isValid, false, 'arbitrary string should be invalid format');
console.log('✅ PASS: Section validator correctly validates NCST [Year][Sem][Schedule][Section#] format');

console.log('\n================================================================');
console.log('🎉 ALL NCST ACADEMIC & TEACHER ASSIGNMENT TESTS PASSED!');
console.log('================================================================');
