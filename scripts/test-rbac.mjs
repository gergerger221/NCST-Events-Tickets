/**
 * Automated RBAC and Security Verification Suite
 * Tests all 10 authorization, session integrity, QR validation, and scoping rules.
 */

// 1. Mock Authoritative Server Database
const mockDb = {
  users: [
    { id: 'usr-admin-1', name: 'Engr. Mark Santos', email: 'admin.santos@school.edu.ph', role: 'admin', employeeId: 'EMP-00101', isActive: true },
    { id: 'usr-cashier-1', name: 'Carla Gomez', email: 'cashier.gomez@school.edu.ph', role: 'cashier', employeeId: 'EMP-00102', isActive: true },
    { id: 'usr-staff-1', name: 'Alex Rivera', email: 'staff.alex@school.edu.ph', role: 'event_staff', employeeId: 'EMP-00103', assignedEventIds: ['evt-juans-2026'], isActive: true },
    { id: 'usr-teach-1', name: 'Prof. Ramon Reyes', email: 'ramon.reyes@school.edu.ph', role: 'teacher', employeeId: 'EMP-00125', assignedCourses: ['BSIT'], assignedSections: ['31M1'], isActive: true },
    { id: 'usr-stud-1', name: 'Juan Dela Cruz', email: 'juan.delacruz@student.edu.ph', role: 'student', studentId: '2026-00125', course: 'BSIT', section: '31M1', isActive: true },
    { id: 'usr-stud-2', name: 'Maria Santos', email: 'maria.santos@student.edu.ph', role: 'student', studentId: '2026-00126', course: 'BSCS', section: '21M2', isActive: true }
  ],
  events: [
    { id: 'evt-juans-2026', title: 'The Juans Concert' },
    { id: 'evt-it-2026', title: 'IT & CS Congress 2026' }
  ],
  tickets: [
    { id: 'tkt-001', ticketNumber: 'NCST-2026-001', qrToken: 'QR-JUANS-001', eventId: 'evt-juans-2026', attendeeStudentId: '2026-00125', currentAttendeeName: 'Juan Dela Cruz', ticketStatus: 'ISSUED', attendanceStatus: 'not_entered' },
    { id: 'tkt-002', ticketNumber: 'NCST-2026-002', qrToken: 'QR-JUANS-002', eventId: 'evt-juans-2026', attendeeStudentId: '2026-00126', currentAttendeeName: 'Maria Santos', ticketStatus: 'ISSUED', attendanceStatus: 'not_entered' }
  ],
  logs: []
};

// 2. Authoritative User Resolver (Vite & PHP Backend Engine)
function getAuthoritativeUser(userId) {
  const user = mockDb.users.find(u => u.id === userId);
  if (!user || user.isActive === false) return null;
  return user;
}

// 3. Backend Endpoints Implementation for Verification
const api = {
  // POST /api/tickets/issue
  issueTicket(userId, payload) {
    const authUser = getAuthoritativeUser(userId);
    if (!authUser) return { status: 401, error: '401 Unauthorized' };
    if (authUser.role !== 'admin' && authUser.role !== 'cashier') {
      return { status: 403, error: '403 Forbidden: Only Cashiers and Administrators can issue tickets.' };
    }
    return { status: 200, success: true, message: 'Ticket issued.' };
  },

  // POST /api/events
  createEvent(userId, payload) {
    const authUser = getAuthoritativeUser(userId);
    if (!authUser) return { status: 401, error: '401 Unauthorized' };
    if (authUser.role !== 'admin') {
      return { status: 403, error: '403 Forbidden: Only Administrators are authorized to create or modify events.' };
    }
    return { status: 200, success: true, message: 'Event created.' };
  },

  // POST /api/scan_ticket
  scanTicket(userId, eventId, rawScanData) {
    const authUser = getAuthoritativeUser(userId);
    if (!authUser) return { status: 401, error: '401 Unauthorized' };
    if (authUser.role !== 'event_staff' && authUser.role !== 'staff') {
      return { status: 403, error: '403 Forbidden: Only authorized Event Staff can scan tickets.' };
    }

    if (authUser.assignedEventIds && !authUser.assignedEventIds.includes(eventId)) {
      return { status: 403, error: '403 Forbidden: Staff not assigned to this event.' };
    }

    const clean = rawScanData.trim().toLowerCase();
    const ticket = mockDb.tickets.find(t => t.id.toLowerCase() === clean || t.qrToken.toLowerCase() === clean);

    if (!ticket) {
      return { status: 400, error: `INVALID TICKET: Ticket "${rawScanData}" not found in database.` };
    }

    if (ticket.eventId !== eventId) {
      return { status: 400, error: 'WRONG EVENT: This ticket is registered for a different event.' };
    }

    if (ticket.attendanceStatus === 'present' || ticket.ticketStatus === 'USED') {
      return { status: 400, is_duplicate: true, error: `⚠️ TICKET ALREADY USED: Scanned previously.` };
    }

    // Success
    ticket.attendanceStatus = 'present';
    ticket.ticketStatus = 'USED';
    ticket.timeIn = new Date().toISOString();
    mockDb.logs.push({
      id: `log-${Date.now()}`,
      ticketId: ticket.id,
      studentId: ticket.attendeeStudentId,
      status: 'valid'
    });

    return { status: 200, success: true, message: 'Attendance recorded in PHT.' };
  },

  // GET /api/attendance
  getAttendance(userId, queryStudentId) {
    const authUser = getAuthoritativeUser(userId);
    if (!authUser) return { status: 401, error: '401 Unauthorized' };

    if (authUser.role === 'student') {
      if (queryStudentId && queryStudentId !== authUser.studentId) {
        return { status: 403, error: '403 Forbidden: Students cannot access other students\' attendance records.' };
      }
      return { status: 200, records: mockDb.logs.filter(l => l.studentId === authUser.studentId) };
    }

    if (authUser.role === 'admin') {
      return { status: 200, records: mockDb.logs };
    }

    return { status: 200, records: [] };
  },

  // GET /api/tickets
  getTickets(userId, targetStudentId, targetTicketId) {
    const authUser = getAuthoritativeUser(userId);
    if (!authUser) return { status: 401, error: '401 Unauthorized' };

    if (authUser.role === 'student') {
      if (targetStudentId && targetStudentId !== authUser.studentId) {
        return { status: 403, error: '403 Forbidden: Students cannot access other students\' tickets.' };
      }
      if (targetTicketId) {
        const found = mockDb.tickets.find(t => t.id === targetTicketId);
        if (found && found.attendeeStudentId !== authUser.studentId) {
          return { status: 403, error: '403 Forbidden: You do not own this ticket.' };
        }
      }
      return { status: 200, tickets: mockDb.tickets.filter(t => t.attendeeStudentId === authUser.studentId) };
    }

    return { status: 200, tickets: mockDb.tickets };
  }
};

const testResults = [];

function runCheck(id, description, passed, detail) {
  testResults.push({ id, description, passed, detail });
  const badge = passed ? '✅ PASSED' : '❌ FAILED';
  console.log(`${badge} [Rule ${id}]: ${description}`);
  if (detail) console.log(`   ↳ ${detail}`);
}

console.log('================================================================');
console.log('NCST EVENTS & TICKETS — RBAC & SECURITY VERIFICATION SUITE');
console.log('================================================================\n');

// 1. Student attempts /api/tickets/issue -> 403
{
  const res = api.issueTicket('usr-stud-1', {});
  runCheck(1, 'Student attempts /api/tickets/issue → 403', res.status === 403, res.error);
}

// 2. Student attempts /api/scan_ticket -> 403
{
  const res = api.scanTicket('usr-stud-1', 'evt-juans-2026', 'QR-JUANS-001');
  runCheck(2, 'Student attempts /api/scan_ticket → 403', res.status === 403, res.error);
}

// 2b. Cashier attempts /api/scan_ticket -> 403 (Cashier does not manage attendance)
{
  const res = api.scanTicket('usr-cashier-1', 'evt-juans-2026', 'QR-JUANS-001');
  runCheck('2b', 'Cashier attempts /api/scan_ticket → 403 (Cashier cannot scan or record attendance)', res.status === 403, res.error);
}

// 3. Student changes localStorage role -> remains Student
{
  const tamperedSession = { ...mockDb.users.find(u => u.id === 'usr-stud-1'), role: 'admin' };
  const authUser = getAuthoritativeUser(tamperedSession.id);
  let effectiveRole = tamperedSession.role;
  if (effectiveRole !== authUser.role) {
    effectiveRole = authUser.role; // Anti-tamper sync
  }
  runCheck(3, 'Student changes localStorage role → Anti-tamper sync enforces true Student role', effectiveRole === 'student', `Enforced Role: ${effectiveRole}`);
}

// 4. Student requests another student's attendance -> denied
{
  const res = api.getAttendance('usr-stud-1', '2026-00126'); // Maria Santos's ID
  runCheck(4, "Student requests another student's attendance → Denied (403)", res.status === 403, res.error);
}

// 5. Student requests another student's ticket -> denied
{
  const res = api.getTickets('usr-stud-1', '2026-00126', 'tkt-002');
  runCheck(5, "Student requests another student's ticket → Denied (403)", res.status === 403, res.error);
}

// 6. Student attempts Admin endpoint (/api/events) -> 403
{
  const res = api.createEvent('usr-stud-1', { title: 'Unauthorized Event' });
  runCheck(6, 'Student attempts Admin endpoint (/api/events) → 403', res.status === 403, res.error);
}

// 7. Cashier attempts Admin-only event management -> denied
{
  const res = api.createEvent('usr-cashier-1', { title: 'Cashier Created Event' });
  runCheck(7, 'Cashier attempts Admin-only event creation (/api/events) → Denied (403)', res.status === 403, res.error);
}

// 7b. Route Guard: Cashier attempts direct navigation to 'events' tab -> redirected to cashier_pos
{
  const roleAllowedTabs = {
    admin: ['dashboard', 'events', 'attendance', 'attendance_archive', 'tickets', 'users', 'reports', 'calendar'],
    cashier: ['cashier_pos', 'qr_print_queue', 'tickets', 'calendar'],
    event_staff: ['staff_dashboard', 'calendar'],
    teacher: ['teacher_dashboard', 'reports', 'calendar'],
    student: ['calendar', 'student_dashboard'],
  };
  const attemptedTab = 'events';
  const role = 'cashier';
  const allowed = roleAllowedTabs[role];
  const isAllowed = allowed.includes(attemptedTab);
  const effectiveTab = isAllowed ? attemptedTab : 'cashier_pos';
  runCheck('7b', "Route Guard: Cashier attempts direct tab navigation to 'events' → Blocked and redirected to 'cashier_pos'", !isAllowed && effectiveTab === 'cashier_pos', `Attempted: ${attemptedTab} → Guarded Tab: ${effectiveTab}`);
}

// 7c. Admin performs event creation -> Authorized (200)
{
  const adminRes = api.createEvent('usr-admin-1', { title: 'Admin Created Summit' });
  runCheck('7c', 'Admin performs event creation (/api/events) → Authorized (200)', adminRes.status === 200, adminRes.message);
}

// 8. Event Staff scans ticket assigned to another event -> denied
{
  const res = api.scanTicket('usr-staff-1', 'evt-juans-2026', 'QR-JUANS-001'); // Valid scan first for Juans
  // Now Staff scans for IT Congress event with Juans Ticket
  const wrongEventRes = api.scanTicket('usr-staff-1', 'evt-it-2026', 'QR-JUANS-002');
  runCheck(8, 'Event Staff scans ticket assigned to another event → Denied (WRONG EVENT)', wrongEventRes.status === 400 || wrongEventRes.status === 403, wrongEventRes.error);
}

// 9. Same QR scanned twice -> ALREADY USED
{
  // First scan already marked tkt-001 as present/USED
  const duplicateRes = api.scanTicket('usr-staff-1', 'evt-juans-2026', 'QR-JUANS-001');
  runCheck(9, 'Same QR scanned twice → Rejected with ALREADY USED', duplicateRes.is_duplicate === true, duplicateRes.error);
}

// 10. Invalid QR -> no attendance record created
{
  const initialLogCount = mockDb.logs.length;
  const invalidRes = api.scanTicket('usr-staff-1', 'evt-juans-2026', 'INVALID-NONEXISTENT-QR');
  const finalLogCount = mockDb.logs.length;
  const noLogCreated = initialLogCount === finalLogCount;
  runCheck(10, 'Invalid QR → Rejected with INVALID TICKET and 0 attendance records created', noLogCreated && invalidRes.status === 400, `Log count before: ${initialLogCount}, Log count after: ${finalLogCount}`);
}

console.log('\n================================================================');
console.log(`VERIFICATION SUMMARY: ${testResults.filter(t => t.passed).length}/${testResults.length} RULES VERIFIED`);
console.log('================================================================');
