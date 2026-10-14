import { Ticket, EventItem, AttendanceLog } from '../types';
import { formatPhilippineFull, formatPhilippineTime, formatPhilippineDate } from './philippineTime';

const getXLSX = async () => {
  return await import('xlsx');
};

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
    'Student ID': t.attendeeStudentId || 'N/A',
    'Student Name': t.currentAttendeeName,
    'Course': t.course || 'N/A',
    'Year Level': t.yearLevel || 'N/A',
    'Section': t.section || 'N/A',
    'Event Name': eventMap.get(t.eventId) || t.eventId,
    'Ticket No.': t.ticketNumber || t.id,
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Event Venue': venueMap.get(t.eventId) || 'NCST Campus',
    'Scanned By': t.lastScannedBy || '---',
  }));

  // Outsider Rows
  const outsiderRows = outsiderTickets.map((t, idx) => ({
    '#': idx + 1,
    'Attendee Name': t.currentAttendeeName,
    'Contact Number': t.contactNumber || 'N/A',
    'Ticket No.': t.ticketNumber || t.id,
    'Event Name': eventMap.get(t.eventId) || t.eventId,
    'Attendee Type': 'OUTSIDER',
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Event Venue': venueMap.get(t.eventId) || 'NCST Campus',
    'Scanned By': t.lastScannedBy || '---',
  }));

  const workbook = XLSX.utils.book_new();

  // 1. Student Attendance Sheet
  const wsStudents = XLSX.utils.json_to_sheet(studentRows.length > 0 ? studentRows : [{ '#': '', 'Student ID': '', 'Student Name': 'No student records', 'Course': '', 'Year Level': '', 'Section': '', 'Event Name': '', 'Ticket No.': '', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '', 'Event Venue': '', 'Scanned By': '' }]);
  wsStudents['!cols'] = [
    { wch: 4 }, { wch: 16 }, { wch: 24 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 28 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 24 }, { wch: 18 }
  ];
  XLSX.utils.book_append_sheet(workbook, wsStudents, 'STUDENT ATTENDANCE');

  // 2. Outsider Attendance Sheet
  const wsOutsiders = XLSX.utils.json_to_sheet(outsiderRows.length > 0 ? outsiderRows : [{ '#': '', 'Attendee Name': 'No outsider records', 'Contact Number': '', 'Ticket No.': '', 'Event Name': '', 'Attendee Type': 'OUTSIDER', 'Attendance Status': '', 'Scan Date': '', 'Scan Time': '', 'Event Venue': '', 'Scanned By': '' }]);
  wsOutsiders['!cols'] = [
    { wch: 4 }, { wch: 24 }, { wch: 16 }, { wch: 16 }, { wch: 28 }, { wch: 14 }, { wch: 18 }, { wch: 14 }, { wch: 14 }, { wch: 24 }, { wch: 18 }
  ];
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
    'Student ID': t.attendeeStudentId || 'N/A',
    'Student Name': t.currentAttendeeName,
    'Course': t.course || 'N/A',
    'Year Level': t.yearLevel || 'N/A',
    'Section': t.section || 'N/A',
    'Event': eventMap.get(t.eventId) || t.eventId,
    'Ticket Number': t.ticketNumber || t.id,
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
  }));

  const worksheet = XLSX.utils.json_to_sheet(rows);
  worksheet['!cols'] = [
    { wch: 4 }, { wch: 16 }, { wch: 24 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 28 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }
  ];

  const workbook = XLSX.utils.book_new();
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
    'Attendee Name': t.currentAttendeeName,
    'Contact Number': t.contactNumber || 'N/A',
    'Event': eventMap.get(t.eventId) || t.eventId,
    'Ticket Number': t.ticketNumber || t.id,
    'Attendance Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
  }));

  const worksheet = XLSX.utils.json_to_sheet(rows);
  worksheet['!cols'] = [
    { wch: 4 }, { wch: 24 }, { wch: 18 }, { wch: 28 }, { wch: 16 }, { wch: 18 }, { wch: 14 }, { wch: 14 }
  ];

  const workbook = XLSX.utils.book_new();
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
    'Scan ID': log.id,
    'Ticket No.': log.ticketNumber || log.ticketId,
    'Event Title': log.eventTitle,
    'Attendee Name': log.attendeeName,
    'Student ID': log.studentId || 'N/A',
    'Course': log.course || 'N/A',
    'Year': log.yearLevel || 'N/A',
    'Section': log.section || 'N/A',
    'Scan Status': log.status.toUpperCase(),
    'Reason / Note': log.denialReason || 'Success',
    'Timestamp': formatPhilippineFull(log.timestamp),
    'Verified By (Staff)': log.verifiedByName,
  }));

  const worksheet = XLSX.utils.json_to_sheet(rows);
  worksheet['!cols'] = [
    { wch: 4 },
    { wch: 15 },
    { wch: 16 },
    { wch: 28 },
    { wch: 22 },
    { wch: 16 },
    { wch: 10 },
    { wch: 10 },
    { wch: 10 },
    { wch: 12 },
    { wch: 30 },
    { wch: 28 },
    { wch: 20 },
  ];

  const workbook = XLSX.utils.book_new();
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
    'Transaction ID': txn.id,
    'Date & Time': formatPhilippineFull(txn.purchaseDate),
    'Purchaser Name': txn.userName,
    'Student ID': txn.studentId || 'N/A',
    'Course': txn.course || 'N/A',
    'Year': txn.yearLevel || 'N/A',
    'Section': txn.section || 'N/A',
    'Email Address': txn.userEmail || 'N/A',
    'Event Title': txn.eventTitle,
    'Ticket No. Issued': txn.ticketNumber || txn.ticketId,
    'Amount (PHP)': txn.amount,
    'Payment Status': (txn.paymentStatus || 'paid').toUpperCase(),
    'Payment Method': txn.paymentMethod || 'Cashier POS / Cash',
    'Cashier / Staff': txn.cashierName || 'Online Portal',
  }));

  const worksheet = XLSX.utils.json_to_sheet(rows);
  worksheet['!cols'] = [
    { wch: 4 },
    { wch: 18 },
    { wch: 22 },
    { wch: 24 },
    { wch: 16 },
    { wch: 10 },
    { wch: 10 },
    { wch: 10 },
    { wch: 26 },
    { wch: 28 },
    { wch: 18 },
    { wch: 14 },
    { wch: 16 },
    { wch: 20 },
    { wch: 18 },
  ];

  const workbook = XLSX.utils.book_new();
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
      'Section': '31M1',
      'Email': 'juan.delacruz@student.ncst.edu.ph'
    },
    {
      'Student ID': '2024-00102',
      'Student Name': 'Maria Clara Santos',
      'Course': 'BSIT',
      'Year Level': '3rd Year',
      'Section': '31M1',
      'Email': 'maria.santos@student.ncst.edu.ph'
    },
    {
      'Student ID': '2024-00103',
      'Student Name': 'Mark Anthony Reyes',
      'Course': 'BSCS',
      'Year Level': '2nd Year',
      'Section': '21A1',
      'Email': 'mark.reyes@student.ncst.edu.ph'
    },
    {
      'Student ID': '2024-00104',
      'Student Name': 'Angelica Torres',
      'Course': 'BSCpE',
      'Year Level': '4th Year',
      'Section': '41E1',
      'Email': 'angelica.torres@student.ncst.edu.ph'
    }
  ];

  const worksheet = XLSX.utils.json_to_sheet(sampleData);
  worksheet['!cols'] = [
    { wch: 18 }, // Student ID
    { wch: 26 }, // Student Name
    { wch: 14 }, // Course
    { wch: 14 }, // Year Level
    { wch: 12 }, // Section
    { wch: 32 }, // Email
  ];

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
    { 'Parameter': 'Event Name', 'Value': event.title },
    { 'Parameter': 'Event Code', 'Value': event.code },
    { 'Parameter': 'Event Date', 'Value': formatPhilippineDate(event.date) },
    { 'Parameter': 'Time Schedule', 'Value': `${event.startTime} - ${event.endTime}` },
    { 'Parameter': 'Venue', 'Value': event.venue },
    { 'Parameter': 'Event Status', 'Value': event.status.toUpperCase() },
    { 'Parameter': 'Organizer', 'Value': event.organizer },
    { 'Parameter': 'Tickets Issued', 'Value': tickets.length },
    { 'Parameter': 'Total Present (Turnout)', 'Value': presentCount },
    { 'Parameter': 'Students Present', 'Value': studentPresentCount },
    { 'Parameter': 'Outsiders Present', 'Value': outsiderPresentCount },
    { 'Parameter': 'Absent / Unclaimed', 'Value': absentCount },
    { 'Parameter': 'Attendance Rate', 'Value': `${tickets.length > 0 ? Math.round((presentCount / tickets.length) * 100) : 0}%` },
    { 'Parameter': 'Total Scan Operations', 'Value': logs.length },
  ];
  const wsOverview = XLSX.utils.json_to_sheet(overviewData);
  wsOverview['!cols'] = [{ wch: 28 }, { wch: 45 }];
  XLSX.utils.book_append_sheet(workbook, wsOverview, 'EVENT SUMMARY');

  // 2. Master All Attendees Sheet
  const masterRows = tickets.map((t, idx) => ({
    '#': idx + 1,
    'Attendee Type': t.attendeeType || 'STUDENT',
    'Identifier': t.attendeeStudentId || t.contactNumber || 'N/A',
    'Name': t.currentAttendeeName,
    'Course': t.course || 'N/A',
    'Year': t.yearLevel || 'N/A',
    'Section': t.section || 'N/A',
    'Ticket No.': t.ticketNumber || t.id,
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Scan Date': t.timeIn ? formatPhilippineDate(t.timeIn) : '---',
    'Scan Time': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': event.venue,
    'Verified By': t.lastScannedBy || '---',
  }));
  const wsMaster = XLSX.utils.json_to_sheet(masterRows);
  wsMaster['!cols'] = [
    { wch: 4 }, { wch: 14 }, { wch: 18 }, { wch: 26 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 16 }, { wch: 16 }, { wch: 14 }, { wch: 14 }, { wch: 24 }, { wch: 20 }
  ];
  XLSX.utils.book_append_sheet(workbook, wsMaster, 'ALL ATTENDEES');

  // 3. Students Only Sheet
  const studentRows = tickets.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT').map((t, idx) => ({
    '#': idx + 1,
    'Student ID': t.attendeeStudentId || 'N/A',
    'Student Name': t.currentAttendeeName,
    'Course': t.course || 'N/A',
    'Year Level': t.yearLevel || 'N/A',
    'Section': t.section || 'N/A',
    'Ticket No.': t.ticketNumber || t.id,
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Time In': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': event.venue,
  }));
  const wsStudents = XLSX.utils.json_to_sheet(studentRows);
  wsStudents['!cols'] = [
    { wch: 4 }, { wch: 18 }, { wch: 26 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 16 }, { wch: 16 }, { wch: 14 }, { wch: 24 }
  ];
  XLSX.utils.book_append_sheet(workbook, wsStudents, 'STUDENTS');

  // 4. Outsiders Only Sheet
  const outsiderRows = tickets.filter(t => t.attendeeType === 'OUTSIDER').map((t, idx) => ({
    '#': idx + 1,
    'Guest Name': t.currentAttendeeName,
    'Contact Phone': t.contactNumber || 'N/A',
    'Ticket No.': t.ticketNumber || t.id,
    'Status': t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED',
    'Time In': t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
    'Venue': event.venue,
  }));
  const wsOutsiders = XLSX.utils.json_to_sheet(outsiderRows.length > 0 ? outsiderRows : [{ '#': '', 'Guest Name': 'No outsider records', 'Contact Phone': '', 'Ticket No.': '', 'Status': '', 'Time In': '', 'Venue': '' }]);
  wsOutsiders['!cols'] = [
    { wch: 4 }, { wch: 26 }, { wch: 18 }, { wch: 16 }, { wch: 16 }, { wch: 14 }, { wch: 24 }
  ];
  XLSX.utils.book_append_sheet(workbook, wsOutsiders, 'OUTSIDERS');

  // 5. Scan Audit Log Sheet
  const scanRows = logs.map((l, idx) => ({
    '#': idx + 1,
    'Timestamp': formatPhilippineFull(l.timestamp),
    'Attendee': l.attendeeName,
    'Type': l.attendeeType || 'STUDENT',
    'Ticket / ID': l.ticketNumber || l.studentId || l.ticketId,
    'Venue': event.venue,
    'Result Status': l.status.toUpperCase(),
    'Verified By': l.verifiedByName || 'Scanner Marshal',
    'Notes / Denial Reason': l.denialReason || '---',
  }));
  const wsLogs = XLSX.utils.json_to_sheet(scanRows.length > 0 ? scanRows : [{ '#': '', 'Timestamp': 'No logs', 'Attendee': '', 'Type': '', 'Ticket / ID': '', 'Venue': '', 'Result Status': '', 'Verified By': '', 'Notes / Denial Reason': '' }]);
  wsLogs['!cols'] = [
    { wch: 4 }, { wch: 24 }, { wch: 24 }, { wch: 12 }, { wch: 18 }, { wch: 24 }, { wch: 14 }, { wch: 20 }, { wch: 30 }
  ];
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
  _eventOrTitle: EventItem | string | null | undefined,
  fileName: string = 'Assigned_Student_Attendance.xlsx',
  isClosed: boolean = false
) => {
  const XLSX = await getXLSX();
  const rows = rosterItems.map((item, idx) => {
    const status = item.attendanceStatus || (item.isPresent ? 'PRESENT' : (isClosed ? 'ABSENT' : 'NOT YET ENTERED'));
    const timeDisplay = item.timeInFormatted || (item.isPresent && item.timeIn ? formatPhilippineTime(item.timeIn) : '---');
    return {
      '#': idx + 1,
      'Student ID': item.student.studentId || 'N/A',
      'Student Name': item.student.name,
      'Course': item.student.course || 'N/A',
      'Year Level': item.student.yearLevel || 'N/A',
      'Section': item.student.section || 'N/A',
      'Attendance Status': status,
      'Time In': timeDisplay,
    };
  });

  const worksheet = XLSX.utils.json_to_sheet(rows.length > 0 ? rows : [{ '#': '', 'Student ID': '', 'Student Name': 'No students in selected class', 'Course': '', 'Year Level': '', 'Section': '', 'Attendance Status': '', 'Time In': '' }]);
  worksheet['!cols'] = [
    { wch: 4 }, { wch: 16 }, { wch: 28 }, { wch: 12 }, { wch: 12 }, { wch: 10 }, { wch: 20 }, { wch: 14 }
  ];

  const workbook = XLSX.utils.book_new();
  XLSX.utils.book_append_sheet(workbook, worksheet, 'Attendance Report');
  XLSX.writeFile(workbook, fileName);
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
  _event: EventItem | null,
  _classLabel: string,
  _teacherName: string,
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

  const worksheet = XLSX.utils.json_to_sheet(formattedRows.length > 0 ? formattedRows : [{
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
  }]);

  worksheet['!cols'] = [
    { wch: 4 },  // #
    { wch: 18 }, // Student ID
    { wch: 30 }, // Name
    { wch: 12 }, // Course
    { wch: 12 }, // Year
    { wch: 10 }, // Section
    { wch: 22 }, // Attendance Status
    { wch: 16 }, // Time In
    { wch: 22 }, // Roster Status
    { wch: 18 }, // Scope
    { wch: 32 }, // Remarks
  ];

  XLSX.utils.book_append_sheet(workbook, worksheet, 'Reconciled Attendance');
  XLSX.writeFile(workbook, fileName);
};

