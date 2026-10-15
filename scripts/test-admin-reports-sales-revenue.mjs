import assert from 'assert';
import * as XLSXModule from 'xlsx';
const XLSX = XLSXModule.default || XLSXModule;
import {
  exportAdminReportsMasterWorkbookToExcel,
  sanitizeFormulaInjection
} from '../src/utils/excelExport.ts';

console.log('=== RUNNING ADMIN REPORTS SALES & REVENUE TEST SUITE ===\n');

// Mock Events
const mockEvents = [
  {
    id: 'evt-tech-2026',
    title: 'NCST University Tech Summit 2026',
    code: 'TECH-2026',
    date: '2026-11-15',
    startTime: '08:00 AM',
    endTime: '05:00 PM',
    venue: 'NCST Gymnasium',
    ticketPrice: 250.00,
    capacity: 500,
    status: 'upcoming',
    organizer: 'College of Computer Studies',
    academicYear: '2026-2027',
    semester: '1st Semester'
  },
  {
    id: 'evt-free-2026',
    title: 'NCST Foundation Day Opening Concert',
    code: 'FND-2026',
    date: '2026-12-01',
    startTime: '06:00 PM',
    endTime: '10:00 PM',
    venue: 'NCST Grand Quadrangle',
    ticketPrice: 0.00,
    capacity: 1000,
    status: 'upcoming',
    organizer: 'Student Affairs Office',
    academicYear: '2026-2027',
    semester: '1st Semester'
  }
];

// Mock Tickets Dataset
const mockTickets = [
  // 1. Paid Student, Used / Scanned (Price: 250.00)
  {
    id: 'TIX-001',
    ticketNumber: 'TIX-001',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Juan Dela Cruz',
    attendeeStudentId: '2024-00101',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    price: 250.00,
    paymentStatus: 'paid',
    ticketStatus: 'USED',
    attendanceStatus: 'present',
    timeIn: '2026-11-15T08:15:00Z',
    purchaseDate: '2026-11-01T10:00:00Z',
    qrPrintStatus: 'PRINTED'
  },
  // 2. Paid Student, Active / Issued (Price: 250.00)
  {
    id: 'TIX-002',
    ticketNumber: 'TIX-002',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Maria Clara Santos',
    attendeeStudentId: '2024-00102',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    price: 250.00,
    paymentStatus: 'paid',
    ticketStatus: 'ISSUED',
    attendanceStatus: 'not_entered',
    purchaseDate: '2026-11-02T11:00:00Z',
    qrPrintStatus: 'PRINTED'
  },
  // 3. Paid Student, Different Section (Price: 250.00)
  {
    id: 'TIX-003',
    ticketNumber: 'TIX-003',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Mark Anthony Reyes',
    attendeeStudentId: '2024-00103',
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: '21M2',
    price: 250.00,
    paymentStatus: 'paid',
    ticketStatus: 'ISSUED',
    attendanceStatus: 'present',
    timeIn: '2026-11-15T08:45:00Z',
    purchaseDate: '2026-11-03T09:30:00Z',
    qrPrintStatus: 'ATTACHED'
  },
  // 4. Paid Outsider (Price: 250.00)
  {
    id: 'TIX-004',
    ticketNumber: 'TIX-O-001',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'OUTSIDER',
    currentAttendeeName: 'Geronimo Guest',
    contactNumber: '09171234567',
    price: 250.00,
    paymentStatus: 'paid',
    ticketStatus: 'USED',
    attendanceStatus: 'present',
    timeIn: '2026-11-15T09:00:00Z',
    purchaseDate: '2026-11-04T14:00:00Z',
    qrPrintStatus: 'PRINTED'
  },
  // 5. Free Complimentary Pass for Student (Price: 0.00)
  {
    id: 'TIX-005',
    ticketNumber: 'TIX-F-001',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Angelica Torres (Scholar)',
    attendeeStudentId: '2024-00104',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    price: 0.00,
    paymentStatus: 'free',
    ticketStatus: 'ISSUED',
    attendanceStatus: 'present',
    timeIn: '2026-11-15T08:20:00Z',
    purchaseDate: '2026-11-05T08:00:00Z',
    qrPrintStatus: 'PENDING'
  },
  // 6. Free Pass for Free Event (Price: 0.00)
  {
    id: 'TIX-006',
    ticketNumber: 'TIX-F-002',
    eventId: 'evt-free-2026',
    eventTitle: 'NCST Foundation Day Opening Concert',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Mico De Guzman',
    attendeeStudentId: '2024-58566',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    price: 0.00,
    paymentStatus: 'free',
    ticketStatus: 'USED',
    attendanceStatus: 'present',
    timeIn: '2026-12-01T18:15:00Z',
    purchaseDate: '2026-11-10T12:00:00Z',
    qrPrintStatus: 'PRINTED'
  },
  // 7. Cancelled / Void Ticket (Must be excluded from valid sales & revenue)
  {
    id: 'TIX-007',
    ticketNumber: 'TIX-VOID-001',
    eventId: 'evt-tech-2026',
    eventTitle: 'NCST University Tech Summit 2026',
    attendeeType: 'STUDENT',
    currentAttendeeName: 'Cancelled Order Student',
    attendeeStudentId: '2024-00999',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: '31M1',
    price: 250.00,
    paymentStatus: 'refunded',
    ticketStatus: 'CANCELLED',
    attendanceStatus: 'not_entered',
    purchaseDate: '2026-11-02T10:00:00Z'
  }
];

// -----------------------------------------------------------------------------
// Test 1: Authoritative Metric Calculations & Business Rules
// -----------------------------------------------------------------------------
console.log('1. Testing Authoritative Metric Calculations & Financial Integrity...');
{
  const validTickets = mockTickets.filter(t => {
    const st = (t.ticketStatus || '').toUpperCase();
    return st !== 'VOID' && st !== 'CANCELLED' && st !== 'REFUNDED';
  });

  const isPaid = (t) => (t.paymentStatus || 'paid').toLowerCase() === 'paid';
  const isFree = (t) => (t.paymentStatus || '').toLowerCase() === 'free';
  const isStudent = (t) => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT';
  const isOutsider = (t) => (t.attendeeType || '').toUpperCase() === 'OUTSIDER';

  const paidStudentSold = validTickets.filter(t => isStudent(t) && isPaid(t)).length;
  const paidOutsiderSold = validTickets.filter(t => isOutsider(t) && isPaid(t)).length;
  const totalPaidSold = paidStudentSold + paidOutsiderSold;

  const freeTicketsIssued = validTickets.filter(t => isFree(t)).length;
  const totalValidPasses = totalPaidSold + freeTicketsIssued;
  const totalCollectedSales = validTickets.reduce((sum, t) => sum + (isPaid(t) ? (Number(t.price) || 0) : 0), 0);

  // Assertions:
  assert.strictEqual(paidStudentSold, 3, 'Paid student tickets must be exactly 3 (TIX-001, TIX-002, TIX-003)');
  assert.strictEqual(paidOutsiderSold, 1, 'Paid outsider tickets must be exactly 1 (TIX-004)');
  assert.strictEqual(totalPaidSold, 4, 'Total paid tickets must equal student + outsider (3 + 1 = 4)');
  assert.strictEqual(freeTicketsIssued, 2, 'Free tickets issued must be exactly 2 (TIX-005, TIX-006)');
  assert.strictEqual(totalValidPasses, 6, 'Total valid passes must equal total paid + free (4 + 2 = 6)');

  // Revenue must equal 4 * 250.00 = 1000.00 (Free tickets do not inflate revenue, Cancelled excluded)
  assert.strictEqual(totalCollectedSales, 1000.00, 'Total collected sales must equal ₱1,000.00');

  // Used ticket (TIX-001) is preserved in historical sales
  const usedTicket = validTickets.find(t => t.id === 'TIX-001');
  assert.ok(usedTicket && usedTicket.ticketStatus === 'USED', 'Used/scanned tickets must remain in valid historical sales');

  // Cancelled ticket (TIX-007) is excluded
  assert.ok(!validTickets.some(t => t.id === 'TIX-007'), 'Cancelled/voided tickets must be excluded from valid sales');

  console.log('   ✓ Metrics verified: 4 Paid Sold (3 Student, 1 Outsider), 2 Free, 6 Valid Passes, ₱1,000.00 Collected Revenue.');
}

// -----------------------------------------------------------------------------
// Test 2: Multi-Criteria Scoping & Filtering
// -----------------------------------------------------------------------------
console.log('2. Testing Multi-Criteria Scoping (Course, Section, Event, Date)...');
{
  // Filter by Course BSIT
  const bsitTickets = mockTickets.filter(t => {
    const st = (t.ticketStatus || '').toUpperCase();
    if (st === 'VOID' || st === 'CANCELLED' || st === 'REFUNDED') return false;
    return (t.attendeeType || 'STUDENT') === 'STUDENT' && t.course === 'BSIT';
  });
  assert.strictEqual(bsitTickets.length, 4, 'BSIT student tickets count matches (TIX-001, TIX-002, TIX-005, TIX-006)');

  // Filter by Section 31M1
  const sec31M1Tickets = bsitTickets.filter(t => t.section === '31M1');
  assert.strictEqual(sec31M1Tickets.length, 4, 'Section 31M1 tickets count matches');
  const paid31M1 = sec31M1Tickets.filter(t => t.paymentStatus === 'paid');
  const free31M1 = sec31M1Tickets.filter(t => t.paymentStatus === 'free');
  assert.strictEqual(paid31M1.length, 2, 'Paid tickets in 31M1: exactly 2');
  assert.strictEqual(free31M1.length, 2, 'Free tickets in 31M1: exactly 2');

  // Filter by Event Tech Summit
  const techSummitTickets = mockTickets.filter(t => {
    const st = (t.ticketStatus || '').toUpperCase();
    return st !== 'CANCELLED' && t.eventId === 'evt-tech-2026';
  });
  assert.strictEqual(techSummitTickets.length, 5, 'Tech Summit valid tickets matches');

  console.log('   ✓ Multi-criteria filtering verified.');
}

// -----------------------------------------------------------------------------
// Test 3: Admin 5-Sheet Master Excel Export (exportAdminReportsMasterWorkbookToExcel)
// -----------------------------------------------------------------------------
console.log('3. Testing Admin 5-Sheet Master Excel Workbook Export...');
{
  const testFile = 'scratch/test_admin_reports_master.xlsx';

  await exportAdminReportsMasterWorkbookToExcel({
    tickets: mockTickets,
    events: mockEvents,
    academicPeriodText: 'A.Y. 2026-2027 • 1st Semester',
    eventScopeText: 'All Events in Scope',
    filterScopeText: 'All Attendees',
    fileName: testFile
  });

  const wb = XLSX.readFile(testFile, { cellStyles: true });

  // 1. Verify exact 5 worksheets
  const expectedSheets = [
    'Attendance Summary',
    'Ticket Sales Summary',
    'Sales by Event',
    'Student Sales by Section',
    'Transaction Details'
  ];
  assert.deepStrictEqual(wb.SheetNames, expectedSheets, 'Workbook must contain the exact 5 specified worksheets');

  // 2. Verify Sheet 1: Attendance Summary
  const wsAtt = wb.Sheets['Attendance Summary'];
  assert.strictEqual(wsAtt['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsAtt['A2']?.v, 'ATTENDANCE & GATE OPERATIONS SUMMARY REPORT');
  assert.strictEqual(wsAtt['B6']?.v, 'Attendance Metric');
  assert.strictEqual(wsAtt['C6']?.v, 'Count / Value');
  // Check Total Attendees Present = 5 (TIX-001, 003, 004, 005, 006)
  assert.strictEqual(wsAtt['C8']?.v, 5, 'Total present attendees count matches in Attendance Summary');

  // 3. Verify Sheet 2: Ticket Sales Summary
  const wsSales = wb.Sheets['Ticket Sales Summary'];
  assert.strictEqual(wsSales['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsSales['A2']?.v, 'OFFICIAL TICKET SALES & COLLECTED REVENUE SUMMARY');
  assert.strictEqual(wsSales['B7']?.v, 'Total Paid Tickets Sold');
  assert.strictEqual(wsSales['C7']?.v, 4, 'Total Paid Tickets Sold must be 4');
  assert.strictEqual(wsSales['B8']?.v, 'Paid Student Tickets Sold');
  assert.strictEqual(wsSales['C8']?.v, 3, 'Paid Student Tickets Sold must be 3');
  assert.strictEqual(wsSales['B9']?.v, 'Paid Outsider Tickets Sold');
  assert.strictEqual(wsSales['C9']?.v, 1, 'Paid Outsider Tickets Sold must be 1');
  assert.strictEqual(wsSales['B10']?.v, 'Free Tickets Issued');
  assert.strictEqual(wsSales['C10']?.v, 2, 'Free Tickets Issued must be 2');
  assert.strictEqual(wsSales['B11']?.v, 'Total Valid Passes');
  assert.strictEqual(wsSales['C11']?.v, 6, 'Total Valid Passes must be 6');
  assert.strictEqual(wsSales['B12']?.v, 'Total Collected Sales');
  assert.strictEqual(wsSales['C12']?.v, 1000, 'Total Collected Sales must be 1000.00');

  // 4. Verify Sheet 3: Sales by Event
  const wsEvents = wb.Sheets['Sales by Event'];
  assert.strictEqual(wsEvents['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsEvents['A2']?.v, 'OFFICIAL TICKET SALES & REVENUE BY EVENT');
  assert.strictEqual(wsEvents['B6']?.v, 'Event Code');
  assert.strictEqual(wsEvents['C6']?.v, 'Event Title');
  assert.strictEqual(wsEvents['B7']?.v, 'TECH-2026');
  assert.strictEqual(wsEvents['L7']?.v, 1000, 'Tech Summit collected revenue matches ₱1000');
  assert.strictEqual(wsEvents['B8']?.v, 'FND-2026');
  assert.strictEqual(wsEvents['L8']?.v, 0, 'Foundation Day free concert revenue is 0');

  // 5. Verify Sheet 4: Student Sales by Section
  const wsSections = wb.Sheets['Student Sales by Section'];
  assert.strictEqual(wsSections['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsSections['A2']?.v, 'OFFICIAL STUDENT TICKET SALES BY ACADEMIC SECTION');
  assert.strictEqual(wsSections['B6']?.v, 'Course');
  assert.strictEqual(wsSections['D6']?.v, 'Section (NCST Format)');
  assert.strictEqual(wsSections['E6']?.v, 'Paid Student Tickets');
  assert.strictEqual(wsSections['F6']?.v, 'Free Student Tickets');
  assert.strictEqual(wsSections['H6']?.v, 'Collected Sales (PHP)');

  // 6. Verify Sheet 5: Transaction Details
  const wsTxn = wb.Sheets['Transaction Details'];
  assert.strictEqual(wsTxn['A1']?.v, 'NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY');
  assert.strictEqual(wsTxn['A2']?.v, 'OFFICIAL TICKET TRANSACTION & ISSUANCE DETAILS');
  assert.strictEqual(wsTxn['B6']?.v, 'Ticket Number');
  assert.strictEqual(wsTxn['D6']?.v, 'Attendee Name');
  assert.strictEqual(wsTxn['M6']?.v, 'Amount (PHP)');

  // 7. Verify Print-Ready Properties and Repeating Headers
  const printTitles = wb.Workbook?.Names?.filter(n => n.Name === '_xlnm.Print_Titles') || [];
  assert.ok(printTitles.length >= 5, 'Print titles must be defined for all 5 worksheets');

  for (const sheetName of expectedSheets) {
    const s = wb.Sheets[sheetName];
    assert.ok(s['!autofilter'], `Sheet ${sheetName} must have AutoFilter defined`);
  }

  console.log('   ✓ 5-Sheet Master Excel Export verified print-ready and fully reconciled.');
}

// -----------------------------------------------------------------------------
// Test 4: Formula Injection Protection
// -----------------------------------------------------------------------------
console.log('4. Testing Formula Injection Protection in Exports...');
{
  const unsafe1 = '=SUM(A1:A10)';
  const unsafe2 = '+12345';
  const unsafe3 = '@HYPERLINK("http://evil.com")';
  const safeText = 'BSIT 31M1';

  assert.strictEqual(sanitizeFormulaInjection(unsafe1), "'=SUM(A1:A10)");
  assert.strictEqual(sanitizeFormulaInjection(unsafe2), "'+12345");
  assert.strictEqual(sanitizeFormulaInjection(unsafe3), "'@HYPERLINK(\"http://evil.com\")");
  assert.strictEqual(sanitizeFormulaInjection(safeText), 'BSIT 31M1');

  console.log('   ✓ Formula injection protection verified.');
}

console.log('\n========================================================');
console.log('  ALL ADMIN REPORTS SALES & REVENUE TESTS PASSED (100%)');
console.log('========================================================\n');
