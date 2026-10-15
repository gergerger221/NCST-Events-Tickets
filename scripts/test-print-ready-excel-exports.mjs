import assert from 'assert';
import * as XLSXModule from 'xlsx';
const XLSX = XLSXModule.default || XLSXModule;
import {
  exportTicketsToExcel,
  exportRosterAttendanceToExcel,
  exportReconciledClassAttendanceToExcel,
  exportMasterWorkbookToExcel,
  exportStudentAttendanceOnlyToExcel,
  exportOutsiderAttendanceOnlyToExcel
} from '../src/utils/excelExport.ts';

console.log('=== RUNNING PRINT-READY ATTENDANCE EXCEL EXPORT TEST SUITE ===\n');

// Mock Event
const mockEvent = {
  id: 'evt-test-1',
  title: 'NCST University Tech Summit 2026',
  code: 'TECH-2026',
  date: '2026-11-15',
  startTime: '08:00 AM',
  endTime: '05:00 PM',
  venue: 'NCST Gymnasium',
  ticketPrice: 150.00,
  capacity: 500,
  status: 'upcoming',
  organizer: 'College of Computer Studies',
  academicYear: '2026-2027',
  semester: '1st Semester'
};

// -----------------------------------------------------------------------------
// Test 1: Teacher Assigned Class Attendance Export (exportRosterAttendanceToExcel)
// -----------------------------------------------------------------------------
console.log('1. Testing Teacher Assigned Class Attendance Export...');
{
  const rosterItems = [
    {
      student: { studentId: '2024-00101', name: 'Juan Dela Cruz', course: 'BSIT', yearLevel: '3rd Year', section: '31M1' },
      isPresent: true,
      timeIn: '2026-11-15T08:15:00Z',
      timeInFormatted: '08:15 AM',
      attendanceStatus: 'present'
    },
    {
      student: { studentId: '2024-00102', name: 'Maria Clara Santos', course: 'BSIT', yearLevel: '3rd Year', section: '31M1' },
      isPresent: false,
      timeIn: null,
      timeInFormatted: '---',
      attendanceStatus: 'not_entered'
    },
    {
      student: { studentId: '2024-00103', name: 'Mark Anthony Reyes', course: 'BSIT', yearLevel: '3rd Year', section: '31M1' },
      isPresent: true,
      timeIn: '2026-11-15T08:30:00Z',
      timeInFormatted: '08:30 AM',
      attendanceStatus: 'present'
    }
  ];

  const testFile = 'scratch/test_teacher_roster.xlsx';
  await exportRosterAttendanceToExcel(rosterItems, mockEvent, testFile, false);

  const wb = XLSX.readFile(testFile, { cellStyles: true });
  assert.ok(wb.SheetNames.includes('Attendance Report'), 'Must contain Attendance Report sheet');

  const ws = wb.Sheets['Attendance Report'];
  // Institutional Header Check
  assert.strictEqual(ws['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(ws['A2']?.v, 'OFFICIAL CLASS ATTENDANCE ROSTER');
  assert.ok(ws['A3']?.v.includes('NCST University Tech Summit 2026'), 'A3 must contain event title');
  assert.ok(ws['A4']?.v.includes('2026-2027 1st Semester'), 'A4 must contain academic period');
  assert.ok(ws['A4']?.v.includes('BSIT 3rd Year - Section 31M1'), 'A4 must contain class section');

  // Column Headers at Row 6
  assert.strictEqual(ws['A6']?.v, '#');
  assert.strictEqual(ws['B6']?.v, 'Student ID');
  assert.strictEqual(ws['C6']?.v, 'Student Name');
  assert.strictEqual(ws['D6']?.v, 'Course');
  assert.strictEqual(ws['E6']?.v, 'Year Level');
  assert.strictEqual(ws['F6']?.v, 'Section');
  assert.strictEqual(ws['G6']?.v, 'Attendance Status');
  assert.strictEqual(ws['H6']?.v, 'Time In');

  // Data Rows start at Row 7
  assert.strictEqual(ws['A7']?.v, 1);
  assert.strictEqual(ws['B7']?.v, '2024-00101');
  assert.strictEqual(ws['C7']?.v, 'Juan Dela Cruz');
  assert.strictEqual(ws['G7']?.v, 'PRESENT');

  assert.strictEqual(ws['A8']?.v, 2);
  assert.strictEqual(ws['B8']?.v, '2024-00102');
  assert.strictEqual(ws['C8']?.v, 'Maria Clara Santos');
  assert.strictEqual(ws['G8']?.v, 'NOT YET ENTERED');

  // Print Margins
  assert.strictEqual(ws['!margins']?.left, 0.5, 'Left margin must be 0.5"');
  assert.strictEqual(ws['!margins']?.right, 0.5, 'Right margin must be 0.5"');

  // Column Widths
  assert.ok(Array.isArray(ws['!cols']) && ws['!cols'].length >= 8, 'Column widths must be configured');
  assert.strictEqual(ws['!cols'][2].wch, 28, 'Student Name column width must be 28');

  // Print Titles in Workbook
  const printTitle = wb.Workbook?.Names?.find(n => n.Name === '_xlnm.Print_Titles');
  assert.ok(printTitle, 'Print Titles defined name must exist in workbook');
  assert.ok(printTitle.Ref.includes('$6:$6'), 'Print titles must repeat row 6');

  console.log('   ✓ Teacher Assigned Class Attendance export verified print-ready.');
}

// -----------------------------------------------------------------------------
// Test 2: Teacher Reconciled Class Attendance Export
// -----------------------------------------------------------------------------
console.log('2. Testing Teacher Reconciled Class Attendance Export...');
{
  const reconciledItems = [
    {
      rowNumber: 1,
      studentId: '2024-00101',
      name: 'Juan Dela Cruz',
      course: 'BSIT',
      yearLevel: '3rd Year',
      section: '31M1',
      isPresent: true,
      attendanceStatus: 'present',
      timeIn: '08:15 AM',
      inRoster: true,
      isAuthorized: true,
      ticketNumber: 'TIX-001',
      remarks: 'Verified Attendance Scan'
    },
    {
      rowNumber: 2,
      studentId: '2024-00102',
      name: 'Maria Clara Santos',
      course: 'BSIT',
      yearLevel: '3rd Year',
      section: '31M1',
      isPresent: false,
      attendanceStatus: 'absent',
      timeIn: null,
      inRoster: true,
      isAuthorized: true,
      ticketNumber: 'TIX-002',
      remarks: 'No entrance scan'
    }
  ];

  const testFile = 'scratch/test_teacher_reconciled.xlsx';
  await exportReconciledClassAttendanceToExcel(reconciledItems, mockEvent, 'BSIT 31M1', 'Engr. Pedro Penduko', testFile);

  const wb = XLSX.readFile(testFile, { cellStyles: true });
  assert.ok(wb.SheetNames.includes('Reconciled Attendance'), 'Must contain Reconciled Attendance sheet');

  const ws = wb.Sheets['Reconciled Attendance'];
  assert.strictEqual(ws['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(ws['A2']?.v, 'RECONCILED CLASS ATTENDANCE REPORT');
  assert.ok(ws['A4']?.v.includes('Faculty: Engr. Pedro Penduko'), 'Must include professor name');
  assert.ok(ws['A4']?.v.includes('Class / Section: BSIT 31M1'), 'Must include class label');

  // Headers on Row 6
  assert.strictEqual(ws['A6']?.v, '#');
  assert.strictEqual(ws['B6']?.v, 'Student ID');
  assert.strictEqual(ws['C6']?.v, 'Student Full Name');
  assert.strictEqual(ws['I6']?.v, 'Roster Status');
  assert.strictEqual(ws['J6']?.v, 'Scope Permission');
  assert.strictEqual(ws['K6']?.v, 'Remarks / Notes');

  // Repeating Headers defined name
  const printTitle = wb.Workbook?.Names?.find(n => n.Name === '_xlnm.Print_Titles');
  assert.ok(printTitle, 'Print Titles defined name must exist');
  assert.ok(printTitle.Ref.includes('$6:$6'), 'Print title must target row 6');

  console.log('   ✓ Teacher Reconciled Class Attendance export verified print-ready.');
}

// -----------------------------------------------------------------------------
// Test 3: Admin Multi-Sheet Event Attendance Export (exportTicketsToExcel)
// -----------------------------------------------------------------------------
console.log('3. Testing Admin Multi-Sheet Event Attendance Export...');
{
  const tickets = [
    {
      id: 'tix-1',
      ticketNumber: 'TIX-001',
      attendeeType: 'STUDENT',
      attendeeStudentId: '2024-00101',
      currentAttendeeName: 'Juan Dela Cruz',
      course: 'BSIT',
      yearLevel: '3rd Year',
      section: '31M1',
      eventId: mockEvent.id,
      attendanceStatus: 'present',
      timeIn: '2026-11-15T08:15:00Z',
      lastScannedBy: 'Marshal Alpha'
    },
    {
      id: 'tix-2',
      ticketNumber: 'TIX-O-001',
      attendeeType: 'OUTSIDER',
      currentAttendeeName: 'Geronimo Guest',
      contactNumber: '09171234567',
      eventId: mockEvent.id,
      attendanceStatus: 'present',
      timeIn: '2026-11-15T08:45:00Z',
      lastScannedBy: 'Marshal Beta'
    }
  ];

  const testFile = 'scratch/test_admin_tickets.xlsx';
  await exportTicketsToExcel(tickets, [mockEvent], testFile);

  const wb = XLSX.readFile(testFile, { cellStyles: true });
  assert.deepStrictEqual(wb.SheetNames, ['STUDENT ATTENDANCE', 'OUTSIDER ATTENDANCE']);

  const wsStudent = wb.Sheets['STUDENT ATTENDANCE'];
  assert.strictEqual(wsStudent['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsStudent['A2']?.v, 'OFFICIAL STUDENT ATTENDANCE REPORT');
  assert.strictEqual(wsStudent['B6']?.v, 'Student ID');
  assert.strictEqual(wsStudent['C6']?.v, 'Student Name');
  assert.strictEqual(wsStudent['B7']?.v, '2024-00101');
  assert.strictEqual(wsStudent['C7']?.v, 'Juan Dela Cruz');

  const wsOutsider = wb.Sheets['OUTSIDER ATTENDANCE'];
  assert.strictEqual(wsOutsider['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsOutsider['A2']?.v, 'OFFICIAL OUTSIDER & GUEST ATTENDANCE REPORT');
  assert.strictEqual(wsOutsider['B6']?.v, 'Attendee Name');
  assert.strictEqual(wsOutsider['C6']?.v, 'Contact Number');
  assert.strictEqual(wsOutsider['B7']?.v, 'Geronimo Guest');

  // Both sheets must have print margins
  assert.strictEqual(wsStudent['!margins']?.left, 0.5);
  assert.strictEqual(wsOutsider['!margins']?.left, 0.5);

  // Both sheets must have defined print titles
  const printTitles = wb.Workbook?.Names?.filter(n => n.Name === '_xlnm.Print_Titles');
  assert.strictEqual(printTitles?.length, 2, 'Must have print titles for both sheets');

  console.log('   ✓ Admin Multi-Sheet Event Attendance export verified print-ready.');
}

// -----------------------------------------------------------------------------
// Test 4: Long Attendance List Spanning Multiple Pages
// -----------------------------------------------------------------------------
console.log('4. Testing Long Attendance List (Spanning Multi-Page Output)...');
{
  const longRoster = [];
  for (let i = 1; i <= 120; i++) {
    longRoster.push({
      student: {
        studentId: `2024-${String(i).padStart(5, '0')}`,
        name: `Student LongName_${i} Dela Cruz`,
        course: 'BSIT',
        yearLevel: '3rd Year',
        section: '31M1'
      },
      isPresent: i % 2 === 0,
      timeIn: i % 2 === 0 ? '2026-11-15T08:15:00Z' : null,
      timeInFormatted: i % 2 === 0 ? '08:15 AM' : '---',
      attendanceStatus: i % 2 === 0 ? 'present' : 'not_entered'
    });
  }

  const testFile = 'scratch/test_long_roster.xlsx';
  await exportRosterAttendanceToExcel(longRoster, mockEvent, testFile, false);

  const wb = XLSX.readFile(testFile, { cellStyles: true });
  const ws = wb.Sheets['Attendance Report'];

  // Check that row 126 is the 120th student (Row 1-5 metadata, Row 6 header, Rows 7-126 data)
  assert.strictEqual(ws['A126']?.v, 120, '120th record number');
  assert.strictEqual(ws['B126']?.v, '2024-00120');
  assert.strictEqual(ws['G126']?.v, 'PRESENT');

  // Check metadata summary reflects full count (120 records, 60 present, 50% turnout)
  assert.ok(ws['A5']?.v.includes('Enrolled: 120'), 'Summary must reflect 120 enrolled students');
  assert.ok(ws['A5']?.v.includes('Present: 60'), 'Summary must reflect 60 present students');
  assert.ok(ws['A5']?.v.includes('Turnout: 50%'), 'Summary must reflect 50% turnout rate');

  // Repeating header defined name must be configured for multi-page print
  const printTitle = wb.Workbook?.Names?.find(n => n.Name === '_xlnm.Print_Titles');
  assert.ok(printTitle, 'Print Titles must be set for multi-page attendance roster');
  assert.ok(printTitle.Ref.includes('$6:$6'), 'Header row 6 must repeat on all pages');

  console.log('   ✓ Long attendance roster (120 records) preserved and verified print-ready.');
}

// -----------------------------------------------------------------------------
// Test 5: Admin Master Attendance Workbook (exportMasterWorkbookToExcel)
// -----------------------------------------------------------------------------
console.log('5. Testing Admin Master Attendance Workbook (Multi-Sheet Audit Package)...');
{
  const tickets = [
    {
      id: 'tix-1',
      ticketNumber: 'TIX-001',
      attendeeType: 'STUDENT',
      attendeeStudentId: '2024-00101',
      currentAttendeeName: 'Juan Dela Cruz',
      course: 'BSIT',
      yearLevel: '3rd Year',
      section: '31M1',
      eventId: mockEvent.id,
      attendanceStatus: 'present',
      timeIn: '2026-11-15T08:15:00Z',
      lastScannedBy: 'Marshal Alpha'
    },
    {
      id: 'tix-2',
      ticketNumber: 'TIX-O-001',
      attendeeType: 'OUTSIDER',
      currentAttendeeName: 'Geronimo Guest',
      contactNumber: '09171234567',
      eventId: mockEvent.id,
      attendanceStatus: 'present',
      timeIn: '2026-11-15T08:45:00Z',
      lastScannedBy: 'Marshal Beta'
    }
  ];

  const logs = [
    {
      id: 'log-1',
      timestamp: '2026-11-15T08:15:00Z',
      ticketId: 'tix-1',
      ticketNumber: 'TIX-001',
      studentId: '2024-00101',
      attendeeName: 'Juan Dela Cruz',
      attendeeType: 'STUDENT',
      eventId: mockEvent.id,
      eventTitle: mockEvent.title,
      status: 'success',
      verifiedByName: 'Marshal Alpha',
      denialReason: ''
    }
  ];

  const testFile = 'scratch/test_admin_master_workbook.xlsx';
  await exportMasterWorkbookToExcel(mockEvent, tickets, logs, testFile);

  const wb = XLSX.readFile(testFile, { cellStyles: true });
  assert.deepStrictEqual(wb.SheetNames, [
    'EVENT SUMMARY',
    'ALL ATTENDEES',
    'STUDENTS',
    'OUTSIDERS',
    'SCAN AUDIT TRAIL'
  ]);

  // Overview Sheet
  const wsOverview = wb.Sheets['EVENT SUMMARY'];
  assert.strictEqual(wsOverview['!margins']?.left, 0.5);

  // Subsheets with defined names
  const printTitles = wb.Workbook?.Names?.filter(n => n.Name === '_xlnm.Print_Titles');
  assert.strictEqual(printTitles?.length, 4, 'Must have print titles for all 4 subsheets');

  console.log('   ✓ Admin Master Attendance Workbook verified print-ready.');
}

console.log('\n=== ALL PRINT-READY EXCEL EXPORT TESTS PASSED (100% SUCCESS) ===\n');
