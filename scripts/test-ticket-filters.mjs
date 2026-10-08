/**
 * Automated Verification Script for Ticket Registry Filtering Logic
 */

const mockTickets = [
  {
    id: 'tkt-001',
    ticketNumber: 'NCST-2026-001',
    eventId: 'evt-1',
    currentAttendeeName: 'Juan Dela Cruz',
    attendeeStudentId: '2026-00125',
    attendeeType: 'STUDENT',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: 'A',
    attendanceStatus: 'present',
    ticketStatus: 'USED',
    qrPrintStatus: 'PRINTED',
    paymentStatus: 'paid'
  },
  {
    id: 'tkt-002',
    ticketNumber: 'NCST-2026-002',
    eventId: 'evt-1',
    currentAttendeeName: 'Maria Santos',
    attendeeStudentId: '2026-00126',
    attendeeType: 'STUDENT',
    course: 'BSCS',
    yearLevel: '2nd Year',
    section: 'B',
    attendanceStatus: 'not_entered',
    ticketStatus: 'ISSUED',
    qrPrintStatus: 'PENDING',
    paymentStatus: 'paid'
  },
  {
    id: 'tkt-003',
    ticketNumber: 'NCST-2026-003',
    eventId: 'evt-1',
    currentAttendeeName: 'Robert Johnson',
    attendeeStudentId: '',
    outsiderId: 'OUT-001',
    attendeeType: 'OUTSIDER',
    course: '',
    yearLevel: '',
    section: '',
    attendanceStatus: 'not_entered',
    ticketStatus: 'ISSUED',
    qrPrintStatus: 'PRINTED',
    paymentStatus: 'paid'
  },
  {
    id: 'tkt-004',
    ticketNumber: 'NCST-2026-004',
    eventId: 'evt-1',
    currentAttendeeName: 'Carlos Mendoza',
    attendeeStudentId: '2026-00127',
    attendeeType: 'STUDENT',
    course: 'BSIT',
    yearLevel: '3rd Year',
    section: 'A',
    attendanceStatus: 'not_entered',
    ticketStatus: 'VOID',
    qrPrintStatus: 'PENDING',
    paymentStatus: 'free'
  }
];

function applyFilters(tickets, filters) {
  return tickets.filter(ticket => {
    if (filters.eventId && filters.eventId !== 'all' && ticket.eventId !== filters.eventId) return false;
    if (filters.attendeeType && filters.attendeeType !== 'all') {
      const aType = ticket.attendeeType || (ticket.attendeeStudentId ? 'STUDENT' : 'OUTSIDER');
      if (aType !== filters.attendeeType) return false;
    }
    if (filters.ticketStatus && filters.ticketStatus !== 'all') {
      const tStatus = (ticket.ticketStatus || '').toLowerCase();
      const fStatus = filters.ticketStatus.toLowerCase();
      if ((fStatus === 'active' || fStatus === 'issued') && tStatus !== 'active' && tStatus !== 'issued') return false;
      else if (fStatus === 'used' && tStatus !== 'used') return false;
      else if ((fStatus === 'cancelled' || fStatus === 'void') && tStatus !== 'cancelled' && tStatus !== 'void') return false;
      else if (fStatus !== 'active' && fStatus !== 'issued' && fStatus !== 'used' && fStatus !== 'cancelled' && fStatus !== 'void' && tStatus !== fStatus) return false;
    }
    if (filters.qrPrintStatus && filters.qrPrintStatus !== 'all') {
      const tQrStatus = ticket.qrPrintStatus || 'PENDING';
      if (tQrStatus !== filters.qrPrintStatus) return false;
    }
    if (filters.paymentStatus && filters.paymentStatus !== 'all' && ticket.paymentStatus !== filters.paymentStatus) return false;
    if (filters.attendanceStatus && filters.attendanceStatus !== 'all') {
      const tAtt = (ticket.attendanceStatus || 'not_entered').toLowerCase();
      const fAtt = filters.attendanceStatus.toLowerCase();
      if (fAtt === 'present' && tAtt !== 'present') return false;
      if ((fAtt === 'not_entered' || fAtt === 'not_yet_entered') && tAtt !== 'not_entered' && tAtt !== 'not_yet_entered') return false;
    }
    if (filters.course && filters.course !== 'all' && filters.course.trim()) {
      const cFilter = filters.course.trim().toLowerCase();
      const tCourse = (ticket.course || '').toLowerCase();
      if (!tCourse.includes(cFilter) && tCourse !== cFilter) return false;
    }
    if (filters.yearLevel && filters.yearLevel !== 'all' && filters.yearLevel.trim()) {
      const yFilter = filters.yearLevel.trim().toLowerCase();
      const tYear = (ticket.yearLevel || '').toLowerCase();
      if (!tYear.includes(yFilter) && tYear !== yFilter) return false;
    }
    if (filters.section && filters.section !== 'all' && filters.section.trim()) {
      const sFilter = filters.section.trim().toLowerCase();
      const tSec = (ticket.section || '').toLowerCase();
      if (!tSec.includes(sFilter) && tSec !== sFilter) return false;
    }

    if (filters.searchQuery && filters.searchQuery.trim()) {
      const q = filters.searchQuery.toLowerCase();
      const matchId = (ticket.id || '').toLowerCase().includes(q);
      const matchNum = (ticket.ticketNumber || '').toLowerCase().includes(q);
      const matchName = (ticket.currentAttendeeName || '').toLowerCase().includes(q);
      const matchStudentId = (ticket.attendeeStudentId || '').toLowerCase().includes(q);
      const matchCourse = (ticket.course || '').toLowerCase().includes(q);
      const matchYear = (ticket.yearLevel || '').toLowerCase().includes(q);
      const matchSection = (ticket.section || '').toLowerCase().includes(q);
      if (!matchId && !matchNum && !matchName && !matchStudentId && !matchCourse && !matchYear && !matchSection) {
        return false;
      }
    }

    return true;
  });
}

console.log('================================================================');
console.log('TICKET REGISTRY FILTER VERIFICATION');
console.log('================================================================');

// Test 1: Search by Student ID
{
  const res = applyFilters(mockTickets, { searchQuery: '2026-00125' });
  console.log(`Test 1 (Search Student ID): ${res.length === 1 && res[0].currentAttendeeName === 'Juan Dela Cruz' ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 2: Search by Ticket Number
{
  const res = applyFilters(mockTickets, { searchQuery: 'NCST-2026-002' });
  console.log(`Test 2 (Search Ticket #): ${res.length === 1 && res[0].currentAttendeeName === 'Maria Santos' ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 3: Type Filter (STUDENT vs OUTSIDER)
{
  const students = applyFilters(mockTickets, { attendeeType: 'STUDENT' });
  const outsiders = applyFilters(mockTickets, { attendeeType: 'OUTSIDER' });
  console.log(`Test 3 (Type Filter): ${students.length === 3 && outsiders.length === 1 ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 4: Attendance Filter (present vs not_entered)
{
  const present = applyFilters(mockTickets, { attendanceStatus: 'present' });
  const notEntered = applyFilters(mockTickets, { attendanceStatus: 'not_entered' });
  console.log(`Test 4 (Attendance Filter): ${present.length === 1 && notEntered.length === 3 ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 5: Ticket Status Filter (issued vs used vs void)
{
  const issued = applyFilters(mockTickets, { ticketStatus: 'issued' });
  const used = applyFilters(mockTickets, { ticketStatus: 'used' });
  const voidTickets = applyFilters(mockTickets, { ticketStatus: 'void' });
  console.log(`Test 5 (Ticket Status Filter): ${issued.length === 2 && used.length === 1 && voidTickets.length === 1 ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 6: Secondary Filters (Course, Year, Section, QR Print)
{
  const bsit = applyFilters(mockTickets, { course: 'BSIT' });
  const pending = applyFilters(mockTickets, { qrPrintStatus: 'PENDING' });
  const printed = applyFilters(mockTickets, { qrPrintStatus: 'PRINTED' });
  console.log(`Test 6 (Secondary Filters): ${bsit.length === 2 && pending.length === 2 && printed.length === 2 ? '✅ PASSED' : '❌ FAILED'}`);
}

// Test 7: Multi-filter combination (Type = STUDENT + Attendance = not_entered + Status = issued + QR Print = PENDING)
{
  const multi = applyFilters(mockTickets, {
    attendeeType: 'STUDENT',
    attendanceStatus: 'not_entered',
    ticketStatus: 'issued',
    qrPrintStatus: 'PENDING'
  });
  console.log(`Test 7 (Multi-filter Combination): ${multi.length === 1 && multi[0].currentAttendeeName === 'Maria Santos' ? '✅ PASSED' : '❌ FAILED'}`);
}

console.log('================================================================');
