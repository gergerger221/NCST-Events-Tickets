import type { Ticket, EventItem, AttendanceLog, TicketSalesSummary } from '../types/index.ts';
import { formatPhilippineFull, formatPhilippineTime, formatPhilippineDate } from './philippineTime.ts';

const getXLSX = async () => {
  return await import('xlsx');
};

/**
 * Formula Injection Protection: Sanitizes values exported to Excel/CSV.
 * Prepends a single quote if string begins with =, +, -, @, \t, or \r.
 */
export function sanitizeFormulaInjection(value: any): any {
  if (typeof value !== 'string') return value;
  const trimmed = value.trim();
  if (/^[=+\-@\t\r]/.test(trimmed)) {
    return "'" + trimmed;
  }
  return value;
}

/**
 * Standard A4 Print-Ready Worksheet Configuration:
 * Formats sheets for immediate Ctrl+P printing in Microsoft Excel.
 * - Margins: 0.5" left/right, 0.75" top/bottom, 0.3" header/footer
 * - Paper size: 9 (A4)
 * - Orientation: landscape (with vertical multi-page flow)
 * - Fit: 1 page wide (fitToWidth: 1, fitToHeight: 0)
 * - Table Column Headers: repeated on every subsequent printed page via _xlnm.Print_Titles
 * - AutoFilter: enabled across table headers
 */
interface PrintReadySheetOptions {
  sheetName: string;
  headerRowIndex: number; // 1-based index where table headers start (e.g. 6)
  lastColLetter: string;  // Last column letter (e.g. 'H', 'K', 'M')
  colWidths: Array<{ wch: number }>;
  sheetIndex?: number;
  orientation?: 'landscape' | 'portrait';
}

function applyPrintReadySheetSettings(
  ws: any,
  options: PrintReadySheetOptions,
  wb?: any
) {
  // 1. Column Widths
  ws['!cols'] = options.colWidths;

  // 2. Margins (balanced 0.5" horizontal margins for A4 printing)
  ws['!margins'] = {
    left: 0.5,
    right: 0.5,
    top: 0.75,
    bottom: 0.75,
    header: 0.3,
    footer: 0.3
  };

  // 3. Page Setup (A4 paperSize 9, landscape, fit to 1 page wide while allowing vertical paging)
  ws['!pageSetup'] = {
    orientation: options.orientation || 'landscape',
    paperSize: 9, // A4
    scale: 100,
    fitToWidth: 1,
    fitToHeight: 0
  };

  // 4. AutoFilter across table headers
  ws['!autofilter'] = {
    ref: `A${options.headerRowIndex}:${options.lastColLetter}${options.headerRowIndex}`
  };

  // 5. Repeating Column Headers across Multiple Printed Pages
  if (wb) {
    if (!wb.Workbook) wb.Workbook = {};
    if (!wb.Workbook.Names) wb.Workbook.Names = [];
    wb.Workbook.Names.push({
      Name: '_xlnm.Print_Titles',
      Ref: `'${options.sheetName}'!$${options.headerRowIndex}:$${options.headerRowIndex}`,
      Sheet: options.sheetIndex !== undefined ? options.sheetIndex : 0
    });
  }
}

export const exportTicketsToExcel = async (
  tickets: Ticket[],
  events: EventItem[],
  fileName: string = 'Event_Attendance_Report.xlsx'
) => {
  const XLSX = await getXLSX();
  const eventMap = new Map(events.map(e => [e.id, e.title]));
  const venueMap = new Map(events.map(e => [e.id, e.venue]));

  // Separate Students and Outsiders
  const studentTickets = tickets.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT');
  const outsiderTickets = tickets.filter(t => t.attendeeType === 'OUTSIDER');

  // Student Rows
  const studentRows = studentTickets.map((t, idx) => ({
    '#': idx + 1,
    'Student ID': sanitizeFormulaInjection(t.attendeeStudentId || 'N/A'),
    'Student Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Course': sanitizeFormulaInjection(t.course || 'N/A'),
    'Year Level': sanitizeFormulaInjection(t.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(t.section || 'N/A'),
    'Event Name': sanitizeFormulaInjection(eventMap.get(t.eventId) || t.eventId),
    'Ticket No.': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Event Venue': sanitizeFormulaInjection(venueMap.get(t.eventId) || 'NCST Campus'),
    'Scanned By': sanitizeFormulaInjection(t.lastScannedBy || '---'),
  }));

  // Outsider Rows
  const outsiderRows = outsiderTickets.map((t, idx) => ({
    '#': idx + 1,
    'Attendee Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Contact Number': sanitizeFormulaInjection(t.contactNumber || 'N/A'),
    'Ticket No.': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Event Name': sanitizeFormulaInjection(eventMap.get(t.eventId) || t.eventId),
    'Attendee Type': 'OUTSIDER',
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Event Venue': sanitizeFormulaInjection(venueMap.get(t.eventId) || 'NCST Campus'),
    'Scanned By': sanitizeFormulaInjection(t.lastScannedBy || '---'),
  }));

  const workbook = XLSX.utils.book_new();

  // 1. Student Attendance Sheet (with institutional print-ready header)
  const studentPresentCount = studentTickets.filter(t => t.attendanceStatus === 'present').length;
  const studentHeaderAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL STUDENT ATTENDANCE REPORT'],
    [`Academic Period: 2026-2027 1st Semester | Source: Authoritative Ticket System`],
    [`Generated: ${formatPhilippineFull(new Date())} | Enrolled Students: ${studentTickets.length} | Present: ${studentPresentCount} | Unentered: ${studentTickets.length - studentPresentCount}`],
    [],
  ];
  const wsStudents = XLSX.utils.aoa_to_sheet(studentHeaderAoa);
  XLSX.utils.sheet_add_json(
    wsStudents,
    studentRows.length > 0 ? studentRows : [{ '#': '', 'Student ID': '', 'Student Name': 'No student records found', 'Course': '', 'Year Level': '', 'Section': '', 'Event Name': '', 'Ticket No.': '', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '', 'Event Venue': '', 'Scanned By': '' }],
    { origin: 'A6' }
  );
  applyPrintReadySheetSettings(wsStudents, {
    sheetName: 'STUDENT ATTENDANCE',
    headerRowIndex: 6,
    lastColLetter: 'M',
    colWidths: [
      { wch: 4 }, { wch: 16 }, { wch: 26 }, { wch: 10 }, { wch: 12 }, { wch: 10 }, { wch: 26 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 22 }, { wch: 18 }
    ],
    sheetIndex: 0
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsStudents, 'STUDENT ATTENDANCE');

  // 2. Outsider Attendance Sheet (with institutional print-ready header)
  const outsiderPresentCount = outsiderTickets.filter(t => t.attendanceStatus === 'present').length;
  const outsiderHeaderAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL OUTSIDER & GUEST ATTENDANCE REPORT'],
    [`Academic Period: 2026-2027 1st Semester | Source: Authoritative Ticket System`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Outsiders: ${outsiderTickets.length} | Present: ${outsiderPresentCount} | Unentered: ${outsiderTickets.length - outsiderPresentCount}`],
    [],
  ];
  const wsOutsiders = XLSX.utils.aoa_to_sheet(outsiderHeaderAoa);
  XLSX.utils.sheet_add_json(
    wsOutsiders,
    outsiderRows.length > 0 ? outsiderRows : [{ '#': '', 'Attendee Name': 'No outsider records found', 'Contact Number': '', 'Ticket No.': '', 'Event Name': '', 'Attendee Type': 'OUTSIDER', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '', 'Event Venue': '', 'Scanned By': '' }],
    { origin: 'A6' }
  );
  applyPrintReadySheetSettings(wsOutsiders, {
    sheetName: 'OUTSIDER ATTENDANCE',
    headerRowIndex: 6,
    lastColLetter: 'K',
    colWidths: [
      { wch: 4 }, { wch: 26 }, { wch: 16 }, { wch: 16 }, { wch: 26 }, { wch: 14 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 22 }, { wch: 18 }
    ],
    sheetIndex: 1
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsOutsiders, 'OUTSIDER ATTENDANCE');

  // Generate binary output and download
  XLSX.writeFile(workbook, fileName);
};

export const exportStudentAttendanceOnlyToExcel = async (
  tickets: Ticket[],
  events: EventItem[],
  fileName: string = 'Student_Attendance_Report.xlsx'
) => {
  const XLSX = await getXLSX();
  const eventMap = new Map(events.map(e => [e.id, e.title]));
  const studentTickets = tickets.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT');

  const rows = studentTickets.map((t, idx) => ({
    '#': idx + 1,
    'Student ID': sanitizeFormulaInjection(t.attendeeStudentId || 'N/A'),
    'Student Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Course': sanitizeFormulaInjection(t.course || 'N/A'),
    'Year Level': sanitizeFormulaInjection(t.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(t.section || 'N/A'),
    'Event': sanitizeFormulaInjection(eventMap.get(t.eventId) || t.eventId),
    'Ticket Number': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
  }));

  const presentCount = studentTickets.filter(t => t.attendanceStatus === 'present').length;
  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL STUDENT ATTENDANCE REPORT'],
    [`Academic Period: 2026-2027 1st Semester | Source: Authoritative Ticket System`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Students: ${studentTickets.length} | Present: ${presentCount} | Unentered: ${studentTickets.length - presentCount}`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    rows.length > 0 ? rows : [{ '#': '', 'Student ID': '', 'Student Name': 'No student records found', 'Course': '', 'Year Level': '', 'Section': '', 'Event': '', 'Ticket Number': '', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '' }],
    { origin: 'A6' }
  );

  const workbook = XLSX.utils.book_new();
  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'STUDENT ATTENDANCE',
    headerRowIndex: 6,
    lastColLetter: 'K',
    colWidths: [
      { wch: 4 }, { wch: 16 }, { wch: 26 }, { wch: 10 }, { wch: 12 }, { wch: 10 }, { wch: 26 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'STUDENT ATTENDANCE');
  XLSX.writeFile(workbook, fileName);
};

export const exportOutsiderAttendanceOnlyToExcel = async (
  tickets: Ticket[],
  events: EventItem[],
  fileName: string = 'Outsider_Attendance_Report.xlsx'
) => {
  const XLSX = await getXLSX();
  const eventMap = new Map(events.map(e => [e.id, e.title]));
  const outsiderTickets = tickets.filter(t => t.attendeeType === 'OUTSIDER');

  const rows = outsiderTickets.map((t, idx) => ({
    '#': idx + 1,
    'Attendee Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Contact Number': sanitizeFormulaInjection(t.contactNumber || 'N/A'),
    'Event': sanitizeFormulaInjection(eventMap.get(t.eventId) || t.eventId),
    'Ticket Number': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
  }));

  const presentCount = outsiderTickets.filter(t => t.attendanceStatus === 'present').length;
  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL OUTSIDER & GUEST ATTENDANCE REPORT'],
    [`Academic Period: 2026-2027 1st Semester | Source: Authoritative Ticket System`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Outsiders: ${outsiderTickets.length} | Present: ${presentCount} | Unentered: ${outsiderTickets.length - presentCount}`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    rows.length > 0 ? rows : [{ '#': '', 'Attendee Name': 'No outsider records found', 'Contact Number': '', 'Event': '', 'Ticket Number': '', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '' }],
    { origin: 'A6' }
  );

  const workbook = XLSX.utils.book_new();
  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'OUTSIDER ATTENDANCE',
    headerRowIndex: 6,
    lastColLetter: 'H',
    colWidths: [
      { wch: 4 }, { wch: 26 }, { wch: 18 }, { wch: 26 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'OUTSIDER ATTENDANCE');
  XLSX.writeFile(workbook, fileName);
};

export const exportScanLogsToExcel = async (
  logs: AttendanceLog[],
  fileName: string = 'Scan_Audit_Logs.xlsx'
) => {
  const XLSX = await getXLSX();
  const rows = logs.map((log, idx) => ({
    '#': idx + 1,
    'Scan ID': sanitizeFormulaInjection(log.id),
    'Ticket No.': sanitizeFormulaInjection(log.ticketNumber || log.ticketId),
    'Event Title': sanitizeFormulaInjection(log.eventTitle),
    'Attendee Name': sanitizeFormulaInjection(log.attendeeName),
    'Student ID': sanitizeFormulaInjection(log.studentId || 'N/A'),
    'Course': sanitizeFormulaInjection(log.course || 'N/A'),
    'Year': sanitizeFormulaInjection(log.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(log.section || 'N/A'),
    'Scan Status': log.status.toUpperCase(),
    'Reason / Note': sanitizeFormulaInjection(log.denialReason || 'Success'),
    'Timestamp': formatPhilippineFull(log.timestamp),
    'Verified By (Staff)': sanitizeFormulaInjection(log.verifiedByName),
  }));

  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL SCAN AUDIT & ENTRY LOGS'],
    [`Audit Scope: Gate Scans & Entrance Validations`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Logs: ${logs.length}`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    rows.length > 0 ? rows : [{ '#': '', 'Scan ID': '', 'Ticket No.': '', 'Event Title': 'No scan logs', 'Attendee Name': '', 'Student ID': '', 'Course': '', 'Year': '', 'Section': '', 'Scan Status': '', 'Reason / Note': '', 'Timestamp': '', 'Verified By (Staff)': '' }],
    { origin: 'A6' }
  );

  const workbook = XLSX.utils.book_new();
  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'Scan Logs',
    headerRowIndex: 6,
    lastColLetter: 'M',
    colWidths: [
      { wch: 4 }, { wch: 15 }, { wch: 16 }, { wch: 26 }, { wch: 22 }, { wch: 16 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 14 }, { wch: 28 }, { wch: 26 }, { wch: 20 }
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'Scan Logs');
  XLSX.writeFile(workbook, fileName);
};

export const exportTransactionsToExcel = async (
  transactions: any[],
  fileName: string = 'Ticket_Transactions_Report.xlsx'
) => {
  const XLSX = await getXLSX();
  const rows = transactions.map((txn, idx) => ({
    '#': idx + 1,
    'Transaction ID': sanitizeFormulaInjection(txn.id),
    'Date & Time': formatPhilippineFull(txn.purchaseDate),
    'Purchaser Name': sanitizeFormulaInjection(txn.userName),
    'Student ID': sanitizeFormulaInjection(txn.studentId || 'N/A'),
    'Course': sanitizeFormulaInjection(txn.course || 'N/A'),
    'Year': sanitizeFormulaInjection(txn.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(txn.section || 'N/A'),
    'Event Title': sanitizeFormulaInjection(txn.eventTitle),
    'Ticket No. Issued': sanitizeFormulaInjection(txn.ticketNumber || txn.ticketId),
    'Amount (PHP)': txn.amount,
    'Payment Status': (txn.paymentStatus || 'paid').toUpperCase(),
    'Payment Method': txn.paymentMethod || 'Cashier POS / Cash',
    'Cashier / Staff': sanitizeFormulaInjection(txn.cashierName || 'Online Portal'),
  }));

  const totalAmount = transactions.reduce((sum, t) => sum + (Number(t.amount) || 0), 0);
  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL TICKET PURCHASE TRANSACTIONS REPORT'],
    [`Audit Scope: Point-of-Sale & Online Purchases | Total Collected: ₱${totalAmount.toLocaleString('en-US', { minimumFractionDigits: 2 })}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Transactions: ${transactions.length}`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    rows.length > 0 ? rows : [{ '#': '', 'Transaction ID': '', 'Date & Time': '', 'Purchaser Name': 'No transactions', 'Student ID': '', 'Course': '', 'Year': '', 'Section': '', 'Event Title': '', 'Ticket No. Issued': '', 'Amount (PHP)': 0, 'Payment Status': '', 'Payment Method': '', 'Cashier / Staff': '' }],
    { origin: 'A6' }
  );

  const workbook = XLSX.utils.book_new();
  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'Transactions',
    headerRowIndex: 6,
    lastColLetter: 'N',
    colWidths: [
      { wch: 4 }, { wch: 18 }, { wch: 22 }, { wch: 24 }, { wch: 16 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 26 }, { wch: 18 }, { wch: 14 }, { wch: 16 }, { wch: 20 }, { wch: 18 }
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'Transactions');
  XLSX.writeFile(workbook, fileName);
};

export const downloadStudentImportTemplate = async (fileName: string = 'Student_Roster_Import_Template.xlsx') => {
  const XLSX = await getXLSX();
  const sampleData = [
    {
      'Student ID': '2024-00101',
      'Student Name': 'Juan Dela Cruz',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1'
    },
    {
      'Student ID': '2024-00102',
      'Student Name': 'Maria Clara Santos',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1'
    },
    {
      'Student ID': '2024-00103',
      'Student Name': 'Mark Anthony Reyes',
      'Course': 'BSCS',
      'Year Level': '2nd Year',
      'Section': '21A1'
    },
    {
      'Student ID': '2024-00104',
      'Student Name': 'Angelica Torres',
      'Course': 'BSCpE',
      'Year Level': '4th Year',
      'Section': '41E1'
    }
  ];

  const worksheet = XLSX.utils.json_to_sheet(sampleData);
  worksheet['!cols'] = [
    { wch: 18 }, // Student ID
    { wch: 26 }, // Student Name
    { wch: 14 }, // Course
    { wch: 14 }, // Year Level
    { wch: 12 }, // Section
  ];
  worksheet['!margins'] = { left: 0.5, right: 0.5, top: 0.75, bottom: 0.75, header: 0.3, footer: 0.3 };
  worksheet['!pageSetup'] = { orientation: 'landscape', paperSize: 9, fitToWidth: 1, fitToHeight: 0 };

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Students');
  XLSX.writeFile(workbook, fileName);
};

export const exportMasterWorkbookToExcel = async (
  event: EventItem,
  tickets: Ticket[],
  logs: AttendanceLog[],
  fileName?: string
) => {
  const XLSX = await getXLSX();
  const safeTitle = event.title.replace(/[^a-zA-Z0-9_-]/g, '_');
  const finalFileName = fileName || `${event.code || safeTitle}_Master_Attendance_Workbook.xlsx`;
  const workbook = XLSX.utils.book_new();

  // 1. Overview Sheet
  const presentCount = tickets.filter(t => t.attendanceStatus === 'present').length;
  const studentPresentCount = tickets.filter(t => t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length;
  const outsiderPresentCount = tickets.filter(t => t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length;
  const absentCount = tickets.filter(t => t.attendanceStatus !== 'present').length;

  const overviewData = [
    { 'Parameter': 'Institution', 'Value': 'National College of Science and Technology' },
    { 'Parameter': 'Report Title', 'Value': 'Master Event Attendance & Audit Summary' },
    { 'Parameter': 'Event Name', 'Value': event.title },
    { 'Parameter': 'Event Code', 'Value': event.code },
    { 'Parameter': 'Event Date', 'Value': formatPhilippineDate(event.date) },
    { 'Parameter': 'Time Schedule', 'Value': `${event.startTime} - ${event.endTime}` },
    { 'Parameter': 'Venue', 'Value': event.venue },
    { 'Parameter': 'Academic Period', 'Value': `${event.academicYear || '2026-2027'} ${event.semester || '1st Semester'}` },
    { 'Parameter': 'Event Status', 'Value': event.status.toUpperCase() },
    { 'Parameter': 'Organizer', 'Value': event.organizer },
    { 'Parameter': 'Total Tickets Issued', 'Value': tickets.length },
    { 'Parameter': 'Total Present (Turnout)', 'Value': presentCount },
    { 'Parameter': 'Students Present', 'Value': studentPresentCount },
    { 'Parameter': 'Outsiders Present', 'Value': outsiderPresentCount },
    { 'Parameter': 'Absent / Unentered', 'Value': absentCount },
    { 'Parameter': 'Attendance Turnout Rate', 'Value': `${tickets.length > 0 ? Math.round((presentCount / tickets.length) * 100) : 0}%` },
    { 'Parameter': 'Total Gate Scan Operations', 'Value': logs.length },
    { 'Parameter': 'Generated Timestamp', 'Value': formatPhilippineFull(new Date()) },
  ];
  const wsOverview = XLSX.utils.json_to_sheet(overviewData);
  wsOverview['!cols'] = [{ wch: 30 }, { wch: 50 }];
  wsOverview['!margins'] = { left: 0.5, right: 0.5, top: 0.75, bottom: 0.75, header: 0.3, footer: 0.3 };
  wsOverview['!pageSetup'] = { orientation: 'portrait', paperSize: 9, fitToWidth: 1, fitToHeight: 0 };
  XLSX.utils.book_append_sheet(workbook, wsOverview, 'EVENT SUMMARY');

  // Common Header Generator for Master Subsheets
  const makeSubsheetHeader = (subTitle: string, count: number) => [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    [`MASTER ATTENDANCE WORKBOOK — ${subTitle}`],
    [`Event: ${event.title} (${event.code || 'NCST'}) | Date: ${formatPhilippineDate(event.date)} | Venue: ${event.venue}`],
    [`Academic Period: ${event.academicYear || '2026-2027'} ${event.semester || '1st Semester'} | Generated: ${formatPhilippineFull(new Date())}`],
    [`Total Records in Sheet: ${count} | Overall Turnout: ${tickets.length > 0 ? Math.round((presentCount / tickets.length) * 100) : 0}%`],
    [],
  ];

  // 2. Master All Attendees Sheet
  const masterRows = tickets.map((t, idx) => ({
    '#': idx + 1,
    'Attendee Type': t.attendeeType || 'STUDENT',
    'Identifier': sanitizeFormulaInjection(t.attendeeStudentId || t.contactNumber || 'N/A'),
    'Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Course': sanitizeFormulaInjection(t.course || 'N/A'),
    'Year': sanitizeFormulaInjection(t.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(t.section || 'N/A'),
    'Ticket No.': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': sanitizeFormulaInjection(event.venue),
    'Verified By': sanitizeFormulaInjection(t.lastScannedBy || '---'),
  }));
  const wsMaster = XLSX.utils.aoa_to_sheet(makeSubsheetHeader('ALL ATTENDEES', masterRows.length));
  XLSX.utils.sheet_add_json(wsMaster, masterRows.length > 0 ? masterRows : [{ '#': '', 'Attendee Type': '', 'Identifier': '', 'Name': 'No attendees', 'Course': '', 'Year': '', 'Section': '', 'Ticket No.': '', 'Status': '', 'Scan Date': '', 'Scan Time': '', 'Venue': '', 'Verified By': '' }], { origin: 'A6' });
  applyPrintReadySheetSettings(wsMaster, {
    sheetName: 'ALL ATTENDEES',
    headerRowIndex: 6,
    lastColLetter: 'M',
    colWidths: [
      { wch: 4 }, { wch: 14 }, { wch: 18 }, { wch: 26 }, { wch: 10 }, { wch: 10 }, { wch: 10 }, { wch: 16 }, { wch: 16 }, { wch: 14 }, { wch: 14 }, { wch: 22 }, { wch: 18 }
    ],
    sheetIndex: 1
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsMaster, 'ALL ATTENDEES');

  // 3. Students Only Sheet
  const studentTickets = tickets.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT');
  const studentRows = studentTickets.map((t, idx) => ({
    '#': idx + 1,
    'Student ID': sanitizeFormulaInjection(t.attendeeStudentId || 'N/A'),
    'Student Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Course': sanitizeFormulaInjection(t.course || 'N/A'),
    'Year Level': sanitizeFormulaInjection(t.yearLevel || 'N/A'),
    'Section': sanitizeFormulaInjection(t.section || 'N/A'),
    'Ticket No.': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Time In': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': sanitizeFormulaInjection(event.venue),
  }));
  const wsStudents = XLSX.utils.aoa_to_sheet(makeSubsheetHeader('ENROLLED STUDENTS', studentRows.length));
  XLSX.utils.sheet_add_json(wsStudents, studentRows.length > 0 ? studentRows : [{ '#': '', 'Student ID': '', 'Student Name': 'No students', 'Course': '', 'Year Level': '', 'Section': '', 'Ticket No.': '', 'Status': '', 'Time In': '', 'Venue': '' }], { origin: 'A6' });
  applyPrintReadySheetSettings(wsStudents, {
    sheetName: 'STUDENTS',
    headerRowIndex: 6,
    lastColLetter: 'J',
    colWidths: [
      { wch: 4 }, { wch: 16 }, { wch: 26 }, { wch: 10 }, { wch: 12 }, { wch: 10 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 22 }
    ],
    sheetIndex: 2
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsStudents, 'STUDENTS');

  // 4. Outsiders Only Sheet
  const outsiderTickets = tickets.filter(t => t.attendeeType === 'OUTSIDER');
  const outsiderRows = outsiderTickets.map((t, idx) => ({
    '#': idx + 1,
    'Guest Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Contact Phone': sanitizeFormulaInjection(t.contactNumber || 'N/A'),
    'Ticket No.': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Time In': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': sanitizeFormulaInjection(event.venue),
  }));
  const wsOutsiders = XLSX.utils.aoa_to_sheet(makeSubsheetHeader('GUESTS & OUTSIDERS', outsiderRows.length));
  XLSX.utils.sheet_add_json(wsOutsiders, outsiderRows.length > 0 ? outsiderRows : [{ '#': '', 'Guest Name': 'No outsider records', 'Contact Phone': '', 'Ticket No.': '', 'Status': '', 'Time In': '', 'Venue': '' }], { origin: 'A6' });
  applyPrintReadySheetSettings(wsOutsiders, {
    sheetName: 'OUTSIDERS',
    headerRowIndex: 6,
    lastColLetter: 'G',
    colWidths: [
      { wch: 4 }, { wch: 26 }, { wch: 18 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 22 }
    ],
    sheetIndex: 3
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsOutsiders, 'OUTSIDERS');

  // 5. Scan Audit Log Sheet
  const scanRows = logs.map((l, idx) => ({
    '#': idx + 1,
    'Timestamp': formatPhilippineFull(l.timestamp),
    'Attendee': sanitizeFormulaInjection(l.attendeeName),
    'Type': l.attendeeType || 'STUDENT',
    'Ticket / ID': sanitizeFormulaInjection(l.ticketNumber || l.studentId || l.ticketId),
    'Venue': sanitizeFormulaInjection(event.venue),
    'Result Status': l.status.toUpperCase(),
    'Verified By': sanitizeFormulaInjection(l.verifiedByName || 'Scanner Marshal'),
    'Notes / Denial Reason': sanitizeFormulaInjection(l.denialReason || '---'),
  }));
  const wsLogs = XLSX.utils.aoa_to_sheet(makeSubsheetHeader('SCAN AUDIT TRAIL', scanRows.length));
  XLSX.utils.sheet_add_json(wsLogs, scanRows.length > 0 ? scanRows : [{ '#': '', 'Timestamp': 'No logs', 'Attendee': '', 'Type': '', 'Ticket / ID': '', 'Venue': '', 'Result Status': '', 'Verified By': '', 'Notes / Denial Reason': '' }], { origin: 'A6' });
  applyPrintReadySheetSettings(wsLogs, {
    sheetName: 'SCAN AUDIT TRAIL',
    headerRowIndex: 6,
    lastColLetter: 'I',
    colWidths: [
      { wch: 4 }, { wch: 22 }, { wch: 24 }, { wch: 12 }, { wch: 18 }, { wch: 22 }, { wch: 14 }, { wch: 20 }, { wch: 28 }
    ],
    sheetIndex: 4
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsLogs, 'SCAN AUDIT TRAIL');

  XLSX.writeFile(workbook, finalFileName);
};

export const exportAttendanceLogsToExcel = exportScanLogsToExcel;

export const exportRosterAttendanceToExcel = async (
  rosterItems: Array<{
    student: any;
    isPresent: boolean;
    timeIn?: string;
    timeInFormatted?: string;
    attendanceStatus?: string;
  }>,
  eventOrTitle: EventItem | string | null | undefined,
  fileName: string = 'Assigned_Student_Attendance.xlsx',
  isClosed: boolean = false
) => {
  const XLSX = await getXLSX();

  const eventTitle = typeof eventOrTitle === 'object' && eventOrTitle ? eventOrTitle.title : (eventOrTitle || 'NCST Campus Event');
  const eventDate = typeof eventOrTitle === 'object' && eventOrTitle?.date ? formatPhilippineDate(eventOrTitle.date) : '---';
  const eventVenue = typeof eventOrTitle === 'object' && eventOrTitle?.venue ? eventOrTitle.venue : 'NCST Campus';
  const acadYear = typeof eventOrTitle === 'object' && eventOrTitle?.academicYear ? eventOrTitle.academicYear : '2026-2027';
  const semester = typeof eventOrTitle === 'object' && eventOrTitle?.semester ? eventOrTitle.semester : '1st Semester';

  const sampleStudent = rosterItems[0]?.student;
  const courseSection = sampleStudent
    ? `${sampleStudent.course || 'BSIT'} ${sampleStudent.yearLevel || ''} - Section ${sampleStudent.section || 'All'}`
    : 'Assigned Class Section';

  const presentCount = rosterItems.filter(i => i.isPresent || i.attendanceStatus === 'present').length;
  const totalCount = rosterItems.length;
  const turnoutRate = totalCount > 0 ? Math.round((presentCount / totalCount) * 100) : 0;

  const rows = rosterItems.map((item, idx) => {
    const rawStatus = item.attendanceStatus || (item.isPresent ? 'PRESENT' : (isClosed ? 'ABSENT' : 'NOT YET ENTERED'));
    const status = rawStatus === 'present' ? 'PRESENT' : (rawStatus === 'absent' ? 'ABSENT' : (rawStatus === 'not_entered' ? 'NOT YET ENTERED' : rawStatus.toUpperCase()));
    const timeDisplay = item.timeInFormatted || (item.isPresent && item.timeIn ? formatPhilippineTime(item.timeIn) : '---');
    return {
      '#': idx + 1,
      'Student ID': sanitizeFormulaInjection(item.student?.studentId || 'N/A'),
      'Student Name': sanitizeFormulaInjection(item.student?.name || 'N/A'),
      'Course': sanitizeFormulaInjection(item.student?.course || 'N/A'),
      'Year Level': sanitizeFormulaInjection(item.student?.yearLevel || 'N/A'),
      'Section': sanitizeFormulaInjection(item.student?.section || 'N/A'),
      'Attendance Status': status,
      'Time In': timeDisplay,
    };
  });

  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL CLASS ATTENDANCE ROSTER'],
    [`Event: ${eventTitle} | Date: ${eventDate} | Venue: ${eventVenue}`],
    [`Academic Period: ${acadYear} ${semester} | Class: ${courseSection}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Enrolled: ${totalCount} | Present: ${presentCount} | Turnout: ${turnoutRate}%`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    rows.length > 0 ? rows : [{ '#': '', 'Student ID': '', 'Student Name': 'No students in selected class', 'Course': '', 'Year Level': '', 'Section': '', 'Attendance Status': '', 'Time In': '' }],
    { origin: 'A6' }
  );

  const workbook = XLSX.utils.book_new();
  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'Attendance Report',
    headerRowIndex: 6,
    lastColLetter: 'H',
    colWidths: [
      { wch: 4 }, { wch: 16 }, { wch: 28 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 20 }, { wch: 14 }
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'Attendance Report');
  XLSX.writeFile(workbook, fileName);
};

export const downloadClassListTemplate = async (fileName: string = 'NCST_Class_List_Sample.xlsx') => {
  const XLSX = await getXLSX();
  const sampleData = [
    {
      'Student ID': '2024-58566',
      'Student Full Name': 'Mico De Guzman',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1',
    },
    {
      'Student ID': '2024-00101',
      'Student Full Name': 'Juan Dela Cruz',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1',
    },
    {
      'Student ID': '2024-00102',
      'Student Full Name': 'Maria Clara Santos',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1',
    },
    {
      'Student ID': '2024-00103',
      'Student Full Name': 'Mark Anthony Reyes',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1',
    },
    {
      'Student ID': '2024-00104',
      'Student Full Name': 'Angelica Torres',
      'Course': 'BSCS',
      'Year Level': '2nd Year',
      'Section': '21A1',
    },
  ];

  const worksheet = XLSX.utils.json_to_sheet(sampleData);
  worksheet['!cols'] = [
    { wch: 18 }, // Student ID
    { wch: 28 }, // Student Full Name
    { wch: 14 }, // Course
    { wch: 14 }, // Year Level
    { wch: 12 }, // Section
  ];
  worksheet['!margins'] = { left: 0.5, right: 0.5, top: 0.75, bottom: 0.75, header: 0.3, footer: 0.3 };
  worksheet['!pageSetup'] = { orientation: 'landscape', paperSize: 9, fitToWidth: 1, fitToHeight: 0 };

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Class List');
  XLSX.writeFile(workbook, fileName);
};

export interface ReconciledAttendanceExportItem {
  rowNumber: number;
  studentId: string;
  name: string;
  course: string;
  yearLevel: string;
  section: string;
  isPresent: boolean;
  attendanceStatus: string;
  timeIn?: string | null;
  inRoster: boolean;
  isAuthorized: boolean;
  ticketNumber?: string | null;
  remarks?: string;
}

export const exportReconciledClassAttendanceToExcel = async (
  items: ReconciledAttendanceExportItem[],
  event: EventItem | null,
  classLabel: string,
  teacherName: string,
  fileName: string = 'Reconciled_Class_Attendance.xlsx'
) => {
  const XLSX = await getXLSX();
  const workbook = XLSX.utils.book_new();

  const formattedRows = items.map((item, idx) => ({
    '#': idx + 1,
    'Student ID': sanitizeFormulaInjection(item.studentId),
    'Student Full Name': sanitizeFormulaInjection(item.name),
    'Course': sanitizeFormulaInjection(item.course),
    'Year Level': sanitizeFormulaInjection(item.yearLevel),
    'Section': sanitizeFormulaInjection(item.section),
    'Attendance Status': item.isPresent ? 'PRESENT' : (item.attendanceStatus === 'not_found' ? 'NOT FOUND IN ROSTER' : (item.attendanceStatus === 'absent' ? 'ABSENT' : 'NOT YET ENTERED')),
    'Time In (PHT)': item.timeIn ? (item.timeIn.includes('T') ? formatPhilippineTime(item.timeIn) : item.timeIn) : '---',
    'Roster Status': item.inRoster ? 'Verified in Roster' : 'Unregistered Student',
    'Scope Permission': item.isAuthorized ? 'Authorized Class' : 'Out of Scope',
    'Remarks / Notes': sanitizeFormulaInjection(item.remarks || (item.isPresent ? 'Verified Attendance Scan' : (item.attendanceStatus === 'not_found' ? 'Student ID not in school roster' : 'No entrance scan'))),
  }));

  const presentCount = items.filter(i => i.isPresent).length;
  const totalCount = items.length;

  const headerAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['RECONCILED CLASS ATTENDANCE REPORT'],
    [`Event: ${event?.title || 'NCST Campus Event'} | Date: ${event ? formatPhilippineDate(event.date) : '---'} | Venue: ${event?.venue || 'NCST Campus'}`],
    [`Class / Section: ${classLabel || 'Class'} | Faculty: ${teacherName || 'Instructor'} | Period: ${event?.academicYear || '2026-2027'} ${event?.semester || '1st Semester'}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Reconciled Roster: ${totalCount} Students | Present: ${presentCount} | Unentered: ${totalCount - presentCount}`],
    [],
  ];

  const worksheet = XLSX.utils.aoa_to_sheet(headerAoa);
  XLSX.utils.sheet_add_json(
    worksheet,
    formattedRows.length > 0 ? formattedRows : [{
      '#': '',
      'Student ID': '',
      'Student Full Name': 'No students in class list',
      'Course': '',
      'Year Level': '',
      'Section': '',
      'Attendance Status': '',
      'Time In (PHT)': '',
      'Roster Status': '',
      'Scope Permission': '',
      'Remarks / Notes': '',
    }],
    { origin: 'A6' }
  );

  applyPrintReadySheetSettings(worksheet, {
    sheetName: 'Reconciled Attendance',
    headerRowIndex: 6,
    lastColLetter: 'K',
    colWidths: [
      { wch: 4 },  // #
      { wch: 16 }, // Student ID
      { wch: 28 }, // Name
      { wch: 10 }, // Course
      { wch: 10 }, // Year
      { wch: 10 }, // Section
      { wch: 20 }, // Attendance Status
      { wch: 14 }, // Time In
      { wch: 20 }, // Roster Status
      { wch: 16 }, // Scope
      { wch: 28 }, // Remarks
    ],
    sheetIndex: 0
  }, workbook);

  XLSX.utils.book_append_sheet(workbook, worksheet, 'Reconciled Attendance');
  XLSX.writeFile(workbook, fileName);
};

export interface AdminReportsMasterExportOptions {
  tickets: Ticket[];
  events: EventItem[];
  transactions?: any[];
  academicPeriodText?: string;
  eventScopeText?: string;
  filterScopeText?: string;
  fileName?: string;
  salesSummary?: TicketSalesSummary | null;
}

export const exportAdminReportsMasterWorkbookToExcel = async (
  options: AdminReportsMasterExportOptions
) => {
  const XLSX = await getXLSX();
  const {
    tickets,
    events,
    transactions = [],
    academicPeriodText = 'All Academic Periods',
    eventScopeText = 'All Events in Scope',
    filterScopeText = 'All Attendees',
    fileName = 'Admin_Attendance_And_Sales_Master_Report.xlsx',
    salesSummary
  } = options;

  const workbook = XLSX.utils.book_new();

  // Prepare maps
  const eventMap = new Map(events.map(e => [e.id, e.title]));

  // Valid tickets for financial accuracy: exclude VOID, CANCELLED, REFUNDED
  const validTickets = tickets.filter(t => {
    const st = (t.ticketStatus || '').toUpperCase();
    return st !== 'VOID' && st !== 'CANCELLED' && st !== 'REFUNDED';
  });

  const isPaid = (t: Ticket) => (t.paymentStatus || 'paid').toLowerCase() === 'paid';
  const isFree = (t: Ticket) => (t.paymentStatus || '').toLowerCase() === 'free';
  const isStudent = (t: Ticket) => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT';
  const isOutsider = (t: Ticket) => (t.attendeeType || '').toUpperCase() === 'OUTSIDER';

  // Sales Totals
  const paidStudentSold = salesSummary?.paidStudentTicketsSold ?? validTickets.filter(t => isStudent(t) && isPaid(t)).length;
  const paidOutsiderSold = salesSummary?.paidOutsiderTicketsSold ?? validTickets.filter(t => isOutsider(t) && isPaid(t)).length;
  const totalPaidSold = salesSummary?.totalPaidTicketsSold ?? (paidStudentSold + paidOutsiderSold);
  const freeStudentIssued = salesSummary?.freeStudentTickets ?? validTickets.filter(t => isStudent(t) && isFree(t)).length;
  const freeOutsiderIssued = salesSummary?.freeOutsiderTickets ?? validTickets.filter(t => isOutsider(t) && isFree(t)).length;
  const freeIssued = salesSummary?.freeTicketsIssued ?? (freeStudentIssued + freeOutsiderIssued);
  const totalValidIssued = salesSummary?.totalValidTicketsIssued ?? (totalPaidSold + freeIssued);
  const totalRevenue = salesSummary?.totalCollectedSales ?? validTickets.reduce((sum, t) => sum + (isPaid(t) ? (Number(t.price) || 0) : 0), 0);
  const studentRevenue = validTickets.filter(t => isStudent(t) && isPaid(t)).reduce((sum, t) => sum + (Number(t.price) || 0), 0);
  const outsiderRevenue = validTickets.filter(t => isOutsider(t) && isPaid(t)).reduce((sum, t) => sum + (Number(t.price) || 0), 0);

  // Attendance Totals
  const presentTickets = tickets.filter(t => t.attendanceStatus === 'present');
  const presentCount = presentTickets.length;
  const studentPresentCount = presentTickets.filter(t => isStudent(t)).length;
  const outsiderPresentCount = presentTickets.filter(t => isOutsider(t)).length;
  const turnoutRate = tickets.length > 0 ? Math.round((presentCount / tickets.length) * 100) : 0;
  const qrPendingCount = tickets.filter(t => t.qrPrintStatus === 'PENDING' || !t.qrPrintStatus).length;
  const qrPrintedCount = tickets.filter(t => t.qrPrintStatus === 'PRINTED' || t.qrPrintStatus === 'ATTACHED').length;
  const unenteredCount = tickets.filter(t => t.attendanceStatus !== 'present').length;

  // =========================================================================
  // 1. Attendance Summary Sheet
  // =========================================================================
  const attSummaryAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['ATTENDANCE & GATE OPERATIONS SUMMARY REPORT'],
    [`Academic Period: ${academicPeriodText} | Event Scope: ${eventScopeText}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Scope Filter: ${filterScopeText}`],
    [],
    ['#', 'Attendance Metric', 'Count / Value', 'Unit', 'Operational Description'],
    [1, 'Total Tickets Issued', tickets.length, 'passes', 'All passes issued within the selected scope'],
    [2, 'Total Attendees Present', presentCount, 'attendees', 'Verified gate entrance scans (Turnout: ' + turnoutRate + '%)'],
    [3, 'Enrolled Students Present', studentPresentCount, 'students', 'Active NCST enrolled students admitted at gates'],
    [4, 'Guests & Outsiders Present', outsiderPresentCount, 'guests', 'Verified public and guest admissions'],
    [5, 'Unentered / Absent Attendees', unenteredCount, 'attendees', 'Issued passes that have not yet entered venue gates'],
    [6, 'QR Badges Printed / Ready', qrPrintedCount, 'badges', 'Physical / PVC credentials ready or printed'],
    [7, 'QR Badges Pending Print', qrPendingCount, 'badges', 'Awaiting queue batch printing']
  ];
  const wsAttSummary = XLSX.utils.aoa_to_sheet(attSummaryAoa);
  applyPrintReadySheetSettings(wsAttSummary, {
    sheetName: 'Attendance Summary',
    headerRowIndex: 6,
    lastColLetter: 'E',
    colWidths: [
      { wch: 4 }, { wch: 30 }, { wch: 16 }, { wch: 14 }, { wch: 45 }
    ],
    sheetIndex: 0
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsAttSummary, 'Attendance Summary');

  // =========================================================================
  // 2. Ticket Sales Summary Sheet
  // =========================================================================
  const salesSummaryAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL TICKET SALES & COLLECTED REVENUE SUMMARY'],
    [`Academic Period: ${academicPeriodText} | Event Scope: ${eventScopeText}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Scope Filter: ${filterScopeText}`],
    [],
    ['#', 'Authoritative Financial Metric', 'Value / Count', 'Unit', 'Financial Reconciliation Details'],
    [1, 'Total Paid Tickets Sold', totalPaidSold, 'tickets', 'Authoritative confirmed paid ticket sales across all attendee types'],
    [2, 'Paid Student Tickets Sold', paidStudentSold, 'tickets', 'Confirmed paid admissions sold to NCST enrolled students'],
    [3, 'Paid Outsider Tickets Sold', paidOutsiderSold, 'tickets', 'Confirmed paid admissions sold to public & guest attendees'],
    [4, 'Free Tickets Issued', freeIssued, 'free passes', 'Complimentary / free admission passes issued (PHP 0.00 collected revenue)'],
    [5, 'Total Valid Passes', totalValidIssued, 'active passes', 'Total active passes in circulation (Paid + Free, excludes void/cancelled)'],
    [6, 'Total Collected Sales', totalRevenue, 'PHP (₱)', 'Total confirmed cash and online collections from paid tickets'],
    [7, 'Student Collected Revenue', studentRevenue, 'PHP (₱)', 'Collections attributed to enrolled NCST students'],
    [8, 'Outsider Collected Revenue', outsiderRevenue, 'PHP (₱)', 'Collections attributed to public / guest attendees']
  ];
  const wsSalesSummary = XLSX.utils.aoa_to_sheet(salesSummaryAoa);
  applyPrintReadySheetSettings(wsSalesSummary, {
    sheetName: 'Ticket Sales Summary',
    headerRowIndex: 6,
    lastColLetter: 'E',
    colWidths: [
      { wch: 4 }, { wch: 34 }, { wch: 18 }, { wch: 14 }, { wch: 50 }
    ],
    sheetIndex: 1
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsSalesSummary, 'Ticket Sales Summary');

  // =========================================================================
  // 3. Sales by Event Sheet
  // =========================================================================
  const eventIdsInScope = Array.from(new Set(validTickets.map(t => t.eventId).concat(events.map(e => e.id))));
  const salesByEventData: any[] = [];
  let sumEventPaidStudent = 0;
  let sumEventPaidOutsider = 0;
  let sumEventTotalPaid = 0;
  let sumEventFree = 0;
  let sumEventValid = 0;
  let sumEventRevenue = 0;

  eventIdsInScope.forEach((evId) => {
    const ev = events.find(e => e.id === evId);
    const evTix = validTickets.filter(t => t.eventId === evId);
    if (evTix.length === 0 && (!ev || events.length > 1)) {
      return;
    }
    const evPaidStudent = evTix.filter(t => isStudent(t) && isPaid(t)).length;
    const evPaidOutsider = evTix.filter(t => isOutsider(t) && isPaid(t)).length;
    const evTotalPaid = evPaidStudent + evPaidOutsider;
    const evFree = evTix.filter(t => isFree(t)).length;
    const evTotalValid = evTotalPaid + evFree;
    const evRev = evTix.reduce((sum, t) => sum + (isPaid(t) ? (Number(t.price) || 0) : 0), 0);

    sumEventPaidStudent += evPaidStudent;
    sumEventPaidOutsider += evPaidOutsider;
    sumEventTotalPaid += evTotalPaid;
    sumEventFree += evFree;
    sumEventValid += evTotalValid;
    sumEventRevenue += evRev;

    salesByEventData.push({
      '#': salesByEventData.length + 1,
      'Event Code': sanitizeFormulaInjection(ev?.code || 'NCST'),
      'Event Title': sanitizeFormulaInjection(ev?.title || evTix[0]?.eventTitle || evId),
      'Academic Period': sanitizeFormulaInjection(`${ev?.academicYear || '2026-2027'} ${ev?.semester || '1st Semester'}`),
      'Admission Type': (ev?.ticketPrice ?? 0) > 0 ? 'Paid Admission' : 'Free Admission',
      'Price Per Ticket (PHP)': Number(ev?.ticketPrice ?? 0),
      'Paid Student Tickets': evPaidStudent,
      'Paid Outsider Tickets': evPaidOutsider,
      'Total Paid Tickets': evTotalPaid,
      'Free Tickets Issued': evFree,
      'Total Valid Passes': evTotalValid,
      'Total Collected Sales (PHP)': evRev
    });
  });

  if (salesByEventData.length === 0) {
    salesByEventData.push({
      '#': '—',
      'Event Code': '—',
      'Event Title': 'No events found in scope',
      'Academic Period': '—',
      'Admission Type': '—',
      'Price Per Ticket (PHP)': 0,
      'Paid Student Tickets': 0,
      'Paid Outsider Tickets': 0,
      'Total Paid Tickets': 0,
      'Free Tickets Issued': 0,
      'Total Valid Passes': 0,
      'Total Collected Sales (PHP)': 0
    });
  } else {
    salesByEventData.push({
      '#': 'TOTALS',
      'Event Code': '—',
      'Event Title': 'RECONCILED ALL EVENTS',
      'Academic Period': '—',
      'Admission Type': '—',
      'Price Per Ticket (PHP)': 0,
      'Paid Student Tickets': sumEventPaidStudent,
      'Paid Outsider Tickets': sumEventPaidOutsider,
      'Total Paid Tickets': sumEventTotalPaid,
      'Free Tickets Issued': sumEventFree,
      'Total Valid Passes': sumEventValid,
      'Total Collected Sales (PHP)': sumEventRevenue
    });
  }

  const salesByEventHeaderAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL TICKET SALES & REVENUE BY EVENT'],
    [`Academic Period: ${academicPeriodText} | Event Scope: ${eventScopeText}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Reconciled Total Revenue: ₱${sumEventRevenue.toLocaleString('en-US', { minimumFractionDigits: 2 })}`],
    [],
  ];
  const wsSalesByEvent = XLSX.utils.aoa_to_sheet(salesByEventHeaderAoa);
  XLSX.utils.sheet_add_json(wsSalesByEvent, salesByEventData, { origin: 'A6' });
  applyPrintReadySheetSettings(wsSalesByEvent, {
    sheetName: 'Sales by Event',
    headerRowIndex: 6,
    lastColLetter: 'L',
    colWidths: [
      { wch: 6 }, { wch: 12 }, { wch: 28 }, { wch: 22 }, { wch: 16 }, { wch: 18 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 16 }, { wch: 24 }
    ],
    sheetIndex: 2
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsSalesByEvent, 'Sales by Event');

  // =========================================================================
  // 4. Student Sales by Section Sheet
  // =========================================================================
  const studentValidTickets = validTickets.filter(t => isStudent(t));
  const sectionMap = new Map<string, {
    course: string;
    yearLevel: string;
    section: string;
    paidCount: number;
    freeCount: number;
    totalPasses: number;
    revenue: number;
  }>();

  studentValidTickets.forEach(t => {
    const course = (t.course || 'Unassigned').trim().toUpperCase();
    const yearLevel = (t.yearLevel || 'N/A').trim();
    const section = (t.section || 'Unassigned').trim().toUpperCase();
    const key = `${course}___${yearLevel}___${section}`;
    const cur = sectionMap.get(key) || {
      course,
      yearLevel,
      section,
      paidCount: 0,
      freeCount: 0,
      totalPasses: 0,
      revenue: 0
    };
    if (isPaid(t)) {
      cur.paidCount += 1;
      cur.revenue += Number(t.price) || 0;
    } else {
      cur.freeCount += 1;
    }
    cur.totalPasses += 1;
    sectionMap.set(key, cur);
  });

  const sectionRows: any[] = [];
  let sumSectionPaid = 0;
  let sumSectionFree = 0;
  let sumSectionPasses = 0;
  let sumSectionRevenue = 0;

  Array.from(sectionMap.values())
    .sort((a, b) => a.course.localeCompare(b.course) || a.section.localeCompare(b.section))
    .forEach((s, idx) => {
      sumSectionPaid += s.paidCount;
      sumSectionFree += s.freeCount;
      sumSectionPasses += s.totalPasses;
      sumSectionRevenue += s.revenue;
      sectionRows.push({
        '#': idx + 1,
        'Course': sanitizeFormulaInjection(s.course),
        'Year Level': sanitizeFormulaInjection(s.yearLevel),
        'Section (NCST Format)': sanitizeFormulaInjection(s.section),
        'Paid Student Tickets': s.paidCount,
        'Free Student Tickets': s.freeCount,
        'Total Student Passes': s.totalPasses,
        'Collected Sales (PHP)': s.revenue
      });
    });

  if (sectionRows.length === 0) {
    sectionRows.push({
      '#': '—',
      'Course': 'No student sales recorded',
      'Year Level': '—',
      'Section (NCST Format)': '—',
      'Paid Student Tickets': 0,
      'Free Student Tickets': 0,
      'Total Student Passes': 0,
      'Collected Sales (PHP)': 0
    });
  } else {
    sectionRows.push({
      '#': 'TOTALS',
      'Course': 'ALL SECTIONS',
      'Year Level': '—',
      'Section (NCST Format)': '—',
      'Paid Student Tickets': sumSectionPaid,
      'Free Student Tickets': sumSectionFree,
      'Total Student Passes': sumSectionPasses,
      'Collected Sales (PHP)': sumSectionRevenue
    });
  }

  const sectionHeaderAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL STUDENT TICKET SALES BY ACADEMIC SECTION'],
    [`Academic Period: ${academicPeriodText} | Event Scope: ${eventScopeText}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Scope: ${filterScopeText} (Students Only, Outsiders Excluded)`],
    [],
  ];
  const wsSectionSales = XLSX.utils.aoa_to_sheet(sectionHeaderAoa);
  XLSX.utils.sheet_add_json(wsSectionSales, sectionRows, { origin: 'A6' });
  applyPrintReadySheetSettings(wsSectionSales, {
    sheetName: 'Student Sales by Section',
    headerRowIndex: 6,
    lastColLetter: 'H',
    colWidths: [
      { wch: 6 }, { wch: 16 }, { wch: 14 }, { wch: 22 }, { wch: 18 }, { wch: 18 }, { wch: 18 }, { wch: 22 }
    ],
    sheetIndex: 3
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsSectionSales, 'Student Sales by Section');

  // =========================================================================
  // 5. Transaction Details Sheet
  // =========================================================================
  const txnRows: any[] = validTickets.map((t, idx) => ({
    '#': idx + 1,
    'Ticket Number': sanitizeFormulaInjection(t.ticketNumber || t.id),
    'Purchase Date': t.purchaseDate ? formatPhilippineFull(t.purchaseDate) : '---',
    'Attendee Name': sanitizeFormulaInjection(t.currentAttendeeName),
    'Attendee Type': t.attendeeType || 'STUDENT',
    'Student ID / Contact': sanitizeFormulaInjection(t.attendeeStudentId || t.contactNumber || 'N/A'),
    'Course': t.attendeeType === 'OUTSIDER' ? '—' : sanitizeFormulaInjection(t.course || 'BSIT'),
    'Year Level': t.attendeeType === 'OUTSIDER' ? '—' : sanitizeFormulaInjection(t.yearLevel || 'N/A'),
    'Section': t.attendeeType === 'OUTSIDER' ? '—' : sanitizeFormulaInjection(t.section || 'N/A'),
    'Event Title': sanitizeFormulaInjection(eventMap.get(t.eventId) || t.eventTitle || t.eventId),
    'Payment Status': (t.paymentStatus || 'paid').toUpperCase(),
    'Ticket Status': (t.ticketStatus || 'ISSUED').toUpperCase(),
    'Amount (PHP)': isPaid(t) ? (Number(t.price) || 0) : 0,
    'Payment Channel': isPaid(t) ? 'Cashier POS / Cash' : 'Complimentary Pass'
  }));

  if (txnRows.length > 0) {
    txnRows.push({
      '#': 'TOTAL',
      'Ticket Number': '—',
      'Purchase Date': '—',
      'Attendee Name': 'TOTAL CONFIRMED REVENUE',
      'Attendee Type': '—',
      'Student ID / Contact': '—',
      'Course': '—',
      'Year Level': '—',
      'Section': '—',
      'Event Title': '—',
      'Payment Status': '—',
      'Ticket Status': '—',
      'Amount (PHP)': totalRevenue,
      'Payment Channel': '—'
    });
  } else {
    txnRows.push({
      '#': '—',
      'Ticket Number': 'No records found',
      'Purchase Date': '—',
      'Attendee Name': '—',
      'Attendee Type': '—',
      'Student ID / Contact': '—',
      'Course': '—',
      'Year Level': '—',
      'Section': '—',
      'Event Title': '—',
      'Payment Status': '—',
      'Ticket Status': '—',
      'Amount (PHP)': 0,
      'Payment Channel': '—'
    });
  }

  const txnHeaderAoa = [
    ['NATIONAL COLLEGE OF SCIENCE AND TECHNOLOGY'],
    ['OFFICIAL TICKET TRANSACTION & ISSUANCE DETAILS'],
    [`Academic Period: ${academicPeriodText} | Event Scope: ${eventScopeText}`],
    [`Generated: ${formatPhilippineFull(new Date())} | Total Records: ${validTickets.length} | Revenue: ₱${totalRevenue.toLocaleString('en-US', { minimumFractionDigits: 2 })}`],
    [],
  ];
  const wsTxnDetails = XLSX.utils.aoa_to_sheet(txnHeaderAoa);
  XLSX.utils.sheet_add_json(wsTxnDetails, txnRows, { origin: 'A6' });
  applyPrintReadySheetSettings(wsTxnDetails, {
    sheetName: 'Transaction Details',
    headerRowIndex: 6,
    lastColLetter: 'N',
    colWidths: [
      { wch: 6 }, { wch: 18 }, { wch: 22 }, { wch: 26 }, { wch: 14 }, { wch: 18 }, { wch: 10 }, { wch: 10 }, { wch: 12 }, { wch: 26 }, { wch: 14 }, { wch: 14 }, { wch: 16 }, { wch: 18 }
    ],
    sheetIndex: 4
  }, workbook);
  XLSX.utils.book_append_sheet(workbook, wsTxnDetails, 'Transaction Details');

  // Trigger file download
  XLSX.writeFile(workbook, fileName);
};
