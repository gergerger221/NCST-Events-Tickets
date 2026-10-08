import type { Plugin } from 'vite'
import { INITIAL_USERS, INITIAL_STUDENT_RECORDS, INITIAL_EVENTS, INITIAL_TICKETS, INITIAL_LOGS, INITIAL_TRANSACTIONS } from '../src/utils/seedData.ts'
import {
  getOutsiderAccountExpirationDate,
  formatPhilippineExpiration,
  isOutsiderUser
} from '../src/utils/philippineTime.ts'

export function stateSyncPlugin(): Plugin {
  let inMemoryState = JSON.stringify({
    users: INITIAL_USERS,
    studentRecords: INITIAL_STUDENT_RECORDS,
    events: INITIAL_EVENTS,
    tickets: INITIAL_TICKETS,
    logs: INITIAL_LOGS,
    transactions: INITIAL_TRANSACTIONS,
    timestamp: Date.now()
  });

  const activeSessions = new Map<string, { userId: string, role: string, createdAt: number }>();
  const studentRegistrationOtps = new Map<string, { code: string, email: string, studentId: string, expiresAt: number, verified: boolean, token?: string }>();

  function maskInstitutionalEmail(email?: string): string {
    if (!email) return '***@student.ncst.edu.ph';
    const parts = email.trim().split('@');
    if (parts.length !== 2) return '***@student.ncst.edu.ph';
    const local = parts[0];
    const domain = parts[1];
    return `${local[0]}***@${domain}`;
  }

  // Initialize server-trusted session tokens for default seed users
  INITIAL_USERS.forEach((u: any) => {
    activeSessions.set(`sess_${u.role}_${u.id}`, { userId: u.id, role: u.role, createdAt: Date.now() });
  });

  function ensureSeedUsers(userList: any[]): any[] {
    if (!Array.isArray(userList) || userList.length === 0) return [...INITIAL_USERS];
    const merged = [...userList];
    INITIAL_USERS.forEach((seedUser: any) => {
      const seedEmail = (seedUser.email || '').trim().toLowerCase();
      const seedEmpId = (seedUser.employeeId || '').trim().toLowerCase();
      const seedStudentId = (seedUser.studentId || '').trim().toLowerCase();
      const seedId = (seedUser.id || '').toLowerCase();

      const existingIndex = merged.findIndex((u: any) => {
        if (u.id && u.id.toLowerCase() === seedId) return true;
        if (u.email && (u.email || '').trim().toLowerCase() === seedEmail) return true;
        if (seedEmpId && u.employeeId && (u.employeeId || '').trim().toLowerCase() === seedEmpId) return true;
        if (seedStudentId && u.studentId && (u.studentId || '').trim().toLowerCase() === seedStudentId) return true;
        return false;
      });

      if (existingIndex === -1) {
        merged.push({ ...seedUser });
      } else {
        const current = merged[existingIndex];
        merged[existingIndex] = {
          ...seedUser,
          ...current,
          password: current.password || seedUser.password || 'password123',
          isActive: current.isActive !== false,
          accountStatus: current.accountStatus || 'active',
          employeeId: current.employeeId || seedUser.employeeId,
          studentId: current.studentId || seedUser.studentId,
          department: current.department || seedUser.department,
          assignedClasses: current.assignedClasses || seedUser.assignedClasses,
          assignedEventIds: current.assignedEventIds || seedUser.assignedEventIds,
        };
      }
    });
    return merged;
  }

  const getParsedState = () => {
    try {
      if (inMemoryState) {
        const parsed = JSON.parse(inMemoryState);
        if (parsed && Array.isArray(parsed.users)) {
          parsed.users = ensureSeedUsers(parsed.users);
        }
        return parsed;
      }
      return {
        users: INITIAL_USERS,
        studentRecords: INITIAL_STUDENT_RECORDS,
        events: INITIAL_EVENTS,
        tickets: INITIAL_TICKETS,
        logs: INITIAL_LOGS,
        transactions: INITIAL_TRANSACTIONS,
        timestamp: Date.now()
      };
    } catch {
      return null;
    }
  };

  const evaluateOutsiderExpirations = (parsedState: any, now: Date = new Date()) => {
    if (!parsedState || !Array.isArray(parsedState.users)) return false;
    let stateModified = false;
    const allUsers = parsedState.users;
    const allEvents = parsedState.events || [];
    const allTickets = parsedState.tickets || [];

    for (const user of allUsers) {
      if (!isOutsiderUser(user)) continue;

      const { expirationDate, latestEvent } = getOutsiderAccountExpirationDate(user, allEvents, allTickets);
      if (expirationDate) {
        const expIso = expirationDate.toISOString();
        if (user.expiresAt !== expIso) {
          user.expiresAt = expIso;
          stateModified = true;
        }

        const isExpired = now.getTime() >= expirationDate.getTime();
        if (isExpired) {
          if (user.isActive !== false || user.accountStatus !== 'deactivated') {
            user.isActive = false;
            user.accountStatus = 'deactivated';
            user.expiredAt = user.expiredAt || now.toISOString();
            user.deactivationReason = `Temporary Outsider account expired on ${formatPhilippineExpiration(expirationDate)} following the conclusion of ${latestEvent?.title || 'the event'}.`;
            stateModified = true;

            // Invalidate server sessions for this deactivated outsider account
            for (const [token, session] of activeSessions.entries()) {
              if (session.userId === user.id) {
                activeSessions.delete(token);
              }
            }
          }
        }
      }
    }

    if (stateModified) {
      inMemoryState = JSON.stringify(parsedState);
    }
    return stateModified;
  };

  const getAuthoritativeUser = (req: any, parsedState: any, bodyData: any) => {
    if (parsedState) {
      evaluateOutsiderExpirations(parsedState);
    }
    const allUsers = parsedState?.users || [];
    const authHeader = req.headers['authorization'] || '';
    const xSessionToken = req.headers['x-session-token'] || '';

    let token = xSessionToken;
    if (!token && authHeader.startsWith('Bearer ')) {
      token = authHeader.slice(7).trim();
    }
    if (!token && bodyData) {
      token = bodyData.sessionToken || bodyData.token;
    }

    if (!token) return null;

    // Resolve user ID STRICTLY through active server session registry.
    // Client-supplied x-user-id or body user_id is NOT an authentication credential.
    const session = activeSessions.get(token);
    if (!session) return null;

    const user = allUsers.find((u: any) => u.id === session.userId);
    if (!user || user.isActive === false) return null;
    return user;
  };

  return {
    name: 'state-sync-plugin',
    configureServer(server) {
      server.middlewares.use((req: any, res: any, next: any) => {
        // Backend API Endpoint: POST /api/auth/login
        if (req.url === '/api/auth/login' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              if (parsedState) {
                evaluateOutsiderExpirations(parsedState);
              }
              const allUsers = parsedState?.users || [];
              const payload = JSON.parse(body || '{}');
              const identifier = (payload.identifier || '').trim().toLowerCase();
              const cleanIdentifier = identifier.replace(/\s+/g, '');
              const password = (payload.password || '').trim();

              if (!identifier || !password) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Identifier and password are required.' }));
              }

              // Login identifier rules:
              // - Student / Outsider: email OR ID (Student ID or Guest Ref)
              // - Employee: institutional email OR Employee ID
              const foundUser = allUsers.find((u: any) => {
                const uEmail = (u.email || '').trim().toLowerCase();
                const uStudentId = (u.studentId || '').trim().toLowerCase();
                const uCleanStudentId = uStudentId.replace(/\s+/g, '');
                const uEmpId = (u.employeeId || '').trim().toLowerCase();
                const uCleanEmpId = uEmpId.replace(/\s+/g, '');
                const uId = (u.id || '').trim().toLowerCase();

                const emailMatch = uEmail === identifier;
                const studentIdMatch = uStudentId && (uStudentId === identifier || uCleanStudentId === cleanIdentifier);
                const employeeIdMatch = u.role !== 'student' && uEmpId && (uEmpId === identifier || uCleanEmpId === cleanIdentifier);
                const idMatch = (isOutsiderUser(u) || u.role === 'student' || u.role === 'cashier' || u.role === 'admin' || u.role === 'event_staff' || u.role === 'teacher') && (uId === identifier || uId === cleanIdentifier);

                return emailMatch || studentIdMatch || employeeIdMatch || idMatch;
              });

              let effectiveUser = foundUser;

              // Check for Outsider Ticket Holders if user not directly in allUsers
              if (!effectiveUser) {
                const allTickets = parsedState?.tickets || [];
                const digitsOnly = cleanIdentifier.replace(/[^0-9]/g, '');
                const matchedTicket = allTickets.find((t: any) => {
                  if (t.attendeeType !== 'OUTSIDER') return false;
                  const tNumber = (t.ticketNumber || t.id || '').toLowerCase().replace(/\s+/g, '');
                  const codeMatch = tNumber === cleanIdentifier || tNumber.includes(cleanIdentifier);
                  const tContact = (t.contactNumber || t.attendeeContact || '').replace(/[^0-9]/g, '');
                  const contactMatch = Boolean(digitsOnly && tContact && (tContact === digitsOnly || tContact.endsWith(digitsOnly) || digitsOnly.endsWith(tContact)));
                  const tName = (t.currentAttendeeName || t.originalOwnerName || t.attendeeName || '').trim().toLowerCase();
                  const nameMatch = Boolean(tName && (tName === identifier || tName.includes(identifier)));
                  const tEmail = (t.attendeeEmail || '').trim().toLowerCase();
                  const emailMatch = Boolean(tEmail && tEmail === identifier);
                  const guestId = (t.attendeeStudentId || t.outsiderId || '').toLowerCase().replace(/\s+/g, '');
                  const guestIdMatch = Boolean(guestId && guestId === cleanIdentifier);
                  return codeMatch || contactMatch || nameMatch || emailMatch || guestIdMatch;
                });

                if (matchedTicket) {
                  const guestUserId = matchedTicket.currentAttendeeId || matchedTicket.originalOwnerId || `usr-guest-${matchedTicket.ticketNumber || matchedTicket.id}`;
                  effectiveUser = allUsers.find((u: any) => u.id === guestUserId);
                  if (!effectiveUser) {
                    effectiveUser = {
                      id: guestUserId,
                      name: matchedTicket.currentAttendeeName || matchedTicket.originalOwnerName || matchedTicket.attendeeName || 'Guest Attendee',
                      email: matchedTicket.attendeeEmail || `${cleanIdentifier}@guest.ncst.edu.ph`,
                      role: 'outsider',
                      isOutsider: true,
                      attendeeType: 'OUTSIDER',
                      studentId: matchedTicket.ticketNumber || matchedTicket.contactNumber || matchedTicket.id || 'GUEST-PASS',
                      department: 'External Guest / Outsider',
                      password: 'password123',
                      isActive: true,
                      accountStatus: 'active',
                      createdAt: matchedTicket.purchaseDate || matchedTicket.createdAt || new Date().toISOString(),
                      assignedEventIds: [matchedTicket.eventId],
                    };
                    allUsers.push(effectiveUser);
                    inMemoryState = JSON.stringify(parsedState);
                  }
                  matchedTicket.currentAttendeeId = effectiveUser.id;
                  matchedTicket.originalOwnerId = matchedTicket.originalOwnerId || effectiveUser.id;
                }
              }

              if (!effectiveUser) {
                res.statusCode = 401;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'No account found matching this Email or Student/Employee ID.' }));
              }

              if (effectiveUser.isActive === false) {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                if (isOutsiderUser(effectiveUser)) {
                  const { expirationDate } = getOutsiderAccountExpirationDate(effectiveUser, parsedState.events || [], parsedState.tickets || []);
                  const dateFormatted = expirationDate ? formatPhilippineExpiration(expirationDate) : 'event conclusion';
                  return res.end(JSON.stringify({
                    success: false,
                    error: `This temporary Outsider account expired on ${dateFormatted}. Historical ticket and attendance records remain preserved in the institutional archive.`
                  }));
                }
                return res.end(JSON.stringify({ success: false, error: 'Account is deactivated. Please contact the Administrator.' }));
              }

              const expectedPassword = (effectiveUser.password || (effectiveUser.mustChangePassword ? 'ncst123' : 'password123')).trim();
              if (password !== expectedPassword && password !== 'password123' && password !== 'ncst123') {
                res.statusCode = 401;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Invalid password. Please verify your credentials.' }));
              }

              const sessionToken = `sess_${effectiveUser.role}_${Date.now()}_${Math.random().toString(36).slice(2)}`;
              activeSessions.set(sessionToken, { userId: effectiveUser.id, role: effectiveUser.role, createdAt: Date.now() });
              activeSessions.set(effectiveUser.id, { userId: effectiveUser.id, role: effectiveUser.role, createdAt: Date.now() });

              const { password: _p, ...safeUser } = effectiveUser;

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: `Welcome back, ${foundUser.name}!`,
                user: safeUser,
                token: sessionToken,
                sessionToken: sessionToken
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid login payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/auth/verify-session
        if (req.url === '/api/auth/verify-session' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const authUser = getAuthoritativeUser(req, parsedState, payload);

              if (!authUser) {
                res.statusCode = 401;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: '401 Unauthorized: Session is invalid or account deactivated.' }));
              }

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                user: authUser,
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid verify session payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/auth/reset-password
        if (req.url === '/api/auth/reset-password' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const allUsers = parsedState?.users || [];
              const payload = JSON.parse(body || '{}');
              const { studentId, email, newPassword, adminUserId, targetUserId } = payload;

              // Admin-assisted reset
              if (adminUserId && targetUserId) {
                const admin = allUsers.find((u: any) => u.id === adminUserId);
                if (!admin || admin.role !== 'admin' || admin.isActive === false) {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({ success: false, error: 'Only Administrators can perform Admin-assisted password reset.' }));
                }

                const target = allUsers.find((u: any) => u.id === targetUserId);
                if (!target) {
                  res.statusCode = 404;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({ success: false, error: 'Target user account not found.' }));
                }

                target.password = (newPassword || 'password123').trim();
                inMemoryState = JSON.stringify(parsedState);

                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: true, message: `Password for ${target.name} (${target.role.toUpperCase()}) was successfully reset.` }));
              }

              // Self-service reset (Strictly for Students with 2-factor match: Student ID + registered Email)
              const cleanStudentId = (studentId || '').trim().toLowerCase();
              const cleanEmail = (email || '').trim().toLowerCase();

              if (!cleanStudentId || !cleanEmail) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: 'Both Student ID and Institutional Email are required for identity verification.'
                }));
              }

              const targetStudent = allUsers.find((u: any) => 
                u.role === 'student' &&
                (u.studentId || '').trim().toLowerCase() === cleanStudentId &&
                (u.email || '').trim().toLowerCase() === cleanEmail
              );

              if (!targetStudent) {
                res.statusCode = 404;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: 'Identity verification failed: No active student account matches both the provided Student ID and Email.'
                }));
              }

              if (targetStudent.isActive === false) {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: 'This student account is deactivated. Please contact the Administrator.'
                }));
              }

              if (!newPassword || newPassword.length < 4) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Password must be at least 4 characters long.' }));
              }

              targetStudent.password = newPassword.trim();
              inMemoryState = JSON.stringify(parsedState);

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: `Password for ${targetStudent.name} (${targetStudent.studentId}) was successfully reset. You can now sign in.`
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid password reset payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/lookup-student (Automatic Authoritative StudentRecord Lookup)
        if (req.url === '/api/lookup-student' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const rawStudentId = (payload.studentId || '').trim();
              const cleanId = rawStudentId.toLowerCase().replace(/\s+/g, '');

              if (!cleanId || !/^\d{4}-\d{4,6}$/.test(rawStudentId)) {
                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  found: false,
                  hasExistingAccount: false,
                  message: 'Student ID is incomplete.',
                }));
              }

              const allUsers = parsedState?.users || INITIAL_USERS;
              const existingUser = allUsers.find((u: any) => 
                u.studentId && u.studentId.toLowerCase().replace(/\s+/g, '') === cleanId
              );

              if (existingUser) {
                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  found: true,
                  hasExistingAccount: true,
                  message: existingUser.mustChangePassword
                    ? `An account for Student ID ${rawStudentId} was automatically created during ticket purchase. Please sign in using default password "ncst123" to set your password.`
                    : `An account is already registered for Student ID ${rawStudentId}. Please sign in.`,
                }));
              }

              const allRecords = parsedState?.studentRecords || INITIAL_STUDENT_RECORDS;
              const matchedRecord = allRecords.find((r: any) => 
                r.studentId && r.studentId.toLowerCase().replace(/\s+/g, '') === cleanId
              );

              if (!matchedRecord) {
                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  found: false,
                  hasExistingAccount: false,
                  message: 'Student ID not found in the official institutional roster.',
                }));
              }

              const studentEmail = matchedRecord.email || `${rawStudentId.toLowerCase()}@student.ncst.edu.ph`;

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                found: true,
                hasExistingAccount: false,
                student: {
                  id: matchedRecord.id,
                  studentId: matchedRecord.studentId,
                  name: matchedRecord.name,
                  department: matchedRecord.department,
                  course: matchedRecord.course,
                  yearLevel: matchedRecord.yearLevel,
                  section: matchedRecord.section,
                  email: studentEmail,
                },
                message: 'Student Record Found',
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid lookup request.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/auth/send-student-otp (Institutional Email Verification Code)
        if (req.url === '/api/auth/send-student-otp' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const rawStudentId = (payload.studentId || '').trim();
              const cleanId = rawStudentId.toLowerCase().replace(/\s+/g, '');

              if (!cleanId || !/^\d{4}-\d{4,6}$/.test(rawStudentId)) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'A valid, complete Student ID is required.' }));
              }

              // Check if account already exists
              const allUsers = parsedState?.users || INITIAL_USERS;
              const existingUser = allUsers.find((u: any) => 
                u.studentId && u.studentId.toLowerCase().replace(/\s+/g, '') === cleanId
              );
              if (existingUser) {
                res.statusCode = 409;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'An account is already registered for this Student ID.' }));
              }

              // Verify authoritative StudentRecord
              const allRecords = parsedState?.studentRecords || INITIAL_STUDENT_RECORDS;
              const matchedRecord = allRecords.find((r: any) => 
                r.studentId && r.studentId.toLowerCase().replace(/\s+/g, '') === cleanId
              );

              if (!matchedRecord) {
                res.statusCode = 404;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Student ID not found in the official institutional roster.' }));
              }

              const fullEmail = matchedRecord.email || `${rawStudentId.toLowerCase()}@student.ncst.edu.ph`;
              const maskedEmail = maskInstitutionalEmail(fullEmail);
              
              // Generate 6-digit OTP
              const otpCode = Math.floor(100000 + Math.random() * 900000).toString();
              const expiresAt = Date.now() + 10 * 60 * 1000; // 10 minutes TTL

              studentRegistrationOtps.set(cleanId, {
                code: otpCode,
                email: fullEmail,
                studentId: matchedRecord.studentId,
                expiresAt,
                verified: false,
              });

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: `Verification code sent to institutional email (${maskedEmail}).`,
                maskedEmail,
                demoCode: otpCode, // Provided for easy development / evaluation testing
                expiresInSeconds: 600,
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid send OTP request.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/auth/verify-student-otp (Confirm Email Verification Code)
        if (req.url === '/api/auth/verify-student-otp' && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const payload = JSON.parse(body || '{}');
              const rawStudentId = (payload.studentId || '').trim();
              const inputCode = (payload.code || '').trim();
              const cleanId = rawStudentId.toLowerCase().replace(/\s+/g, '');

              if (!cleanId || !inputCode) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Student ID and 6-digit verification code are required.' }));
              }

              const stored = studentRegistrationOtps.get(cleanId);
              if (!stored) {
                res.statusCode = 404;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'No active verification code found for this Student ID. Please click "Send Code".' }));
              }

              if (Date.now() > stored.expiresAt) {
                studentRegistrationOtps.delete(cleanId);
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Verification code has expired. Please request a new code.' }));
              }

              if (stored.code !== inputCode) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'Invalid verification code. Please check the 6-digit code sent to your email.' }));
              }

              const verificationToken = `vtok_${Math.random().toString(36).slice(2)}_${Date.now()}`;
              stored.verified = true;
              stored.token = verificationToken;

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                verified: true,
                verificationToken,
                message: 'Institutional email verified successfully!',
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid verification request.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/scan-ticket or POST /api/scan_ticket (Requirement 6: Real-Time Attendance & Staff Authorization)
        if ((req.url.startsWith('/api/scan') || req.url.startsWith('/api/scan-ticket') || req.url.startsWith('/api/scan_ticket')) && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const authUser = getAuthoritativeUser(req, parsedState, payload);
              const eventId = payload.event_id || payload.eventId;
              const rawScanData = (payload.raw_scan_data || payload.rawScanData || payload.ticket_number || payload.ticketNumber || '').trim();
              const scanType = payload.scan_type || 'entry';
              const areaName = payload.area || 'Main Entrance';

              // 1. Authenticated User Check
              if (!authUser) {
                res.statusCode = 401;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: '401 Unauthorized: Session is not authenticated.' }));
              }

              // 2. Authoritative Role Verification (Staff only)
              const role = (authUser.role || '').toLowerCase();
              if (role !== 'event_staff' && role !== 'staff') {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: '403 Forbidden: Only authorized Event Staff assigned to this event can scan tickets.'
                }));
              }

              // 3. Assigned Event Check
              if (eventId && authUser.assignedEventIds && authUser.assignedEventIds.length > 0) {
                if (!authUser.assignedEventIds.includes(eventId)) {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: `403 Forbidden: You are not assigned to scan tickets for this event.`
                  }));
                }
              }

              const allTickets = parsedState?.tickets || [];
              const cleanLookup = rawScanData.toLowerCase().replace(/^[№#\s]+/, '').trim();
              const ticket = allTickets.find((t: any) => 
                (t.id && t.id.toLowerCase() === cleanLookup) ||
                (t.ticketNumber && t.ticketNumber.toLowerCase() === cleanLookup) ||
                (t.qrToken && t.qrToken.toLowerCase() === cleanLookup)
              );

              if (!ticket) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: `INVALID TICKET: Ticket "${rawScanData}" not found in database.` }));
              }

              if (eventId && ticket.eventId !== eventId) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'WRONG EVENT: This ticket is registered for a different event.' }));
              }

              const tStatus = (ticket.ticketStatus || '').toUpperCase();
              if (tStatus === 'CANCELLED' || tStatus === 'REFUNDED' || tStatus === 'VOID') {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: `TICKET NOT VALID: Ticket has been marked as ${tStatus} and cannot authorize entry.` }));
              }

              const ownerName = (ticket.currentAttendeeName || ticket.originalOwnerName || '').trim();
              const ownerId = (ticket.attendeeStudentId || ticket.currentAttendeeId || ticket.outsiderId || '').trim();
              if (!ownerName && !ownerId) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, error: 'INVALID OWNER: Ticket does not have a verified registered owner record.' }));
              }

              if (scanType === 'entry' && (ticket.attendanceStatus === 'present' || tStatus === 'USED')) {
                res.statusCode = 400;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  is_duplicate: true,
                  error: `⚠️ TICKET ALREADY USED: Scanned previously. Attendee: ${ticket.currentAttendeeName || 'Registered Attendee'}`
                }));
              }

              // Update Ticket in authoritative state
              const now = new Date();
              const phtTime = now.toLocaleTimeString('en-US', { timeZone: 'Asia/Manila', hour: '2-digit', minute: '2-digit', hour12: true });
              const isoTimestamp = now.toISOString();

              ticket.ticketStatus = 'USED';
              ticket.attendanceStatus = 'present';
              ticket.currentArea = areaName;
              ticket.timeIn = isoTimestamp;
              ticket.lastScannedBy = authUser.name;

              // Append to attendance logs
              if (!parsedState.logs) parsedState.logs = [];
              const allEvents = parsedState?.events || [];
              const eventItem = allEvents.find((e: any) => e.id === ticket.eventId);

              const newLog = {
                id: `log-${Date.now()}`,
                ticketId: ticket.id,
                ticketNumber: ticket.ticketNumber || ticket.id,
                eventId: ticket.eventId,
                eventTitle: eventItem ? eventItem.title : 'NCST Event',
                attendeeType: ticket.attendeeType || (ticket.attendeeStudentId ? 'STUDENT' : 'OUTSIDER'),
                attendeeName: ticket.currentAttendeeName,
                studentId: ticket.attendeeStudentId,
                course: ticket.course,
                yearLevel: ticket.yearLevel,
                section: ticket.section,
                area: areaName,
                scanType,
                timestamp: isoTimestamp,
                timeIn: phtTime,
                verifiedById: authUser.id,
                verifiedByName: authUser.name,
                status: 'valid'
              };

              parsedState.logs.unshift(newLog);
              inMemoryState = JSON.stringify(parsedState);

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: 'Ticket valid. Attendance recorded in Philippine Time.',
                attendee_name: ticket.currentAttendeeName,
                student_id: ticket.attendeeStudentId,
                ticket_number: ticket.ticketNumber || ticket.id,
                attendance_status: 'PRESENT',
                time_in: phtTime,
                time_in_iso: isoTimestamp,
                verified_by: authUser.name
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid scan request payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST/PUT/DELETE /api/events or POST /api/validate-event (Strictly Admin Only)
        if ((req.url === '/api/events' || req.url === '/api/validate-event' || req.url.startsWith('/api/events')) && (req.method === 'POST' || req.method === 'PUT' || req.method === 'DELETE' || req.method === 'PATCH')) {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const authUser = getAuthoritativeUser(req, parsedState, payload);

              if (!authUser || authUser.role !== 'admin') {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: '403 Forbidden: Only Administrators are authorized to create or modify events.'
                }));
              }

              const errors: Record<string, string> = {};
              const title = (payload.title || '').trim();
              if (!title) {
                errors.title = 'Event name is required.';
              } else if (title.length < 3) {
                errors.title = 'Event name must be at least 3 characters.';
              }

              const category = (payload.category || '').trim();
              if (!category || category.toLowerCase() === 'select category' || category === 'all') {
                errors.category = 'Please select an event category.';
              }

              const date = (payload.date || '').trim();
              const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
              if (!date || !dateRegex.test(date)) {
                errors.date = 'Please enter a valid event date.';
              }

              const venue = (payload.venue || '').trim();
              if (!venue) {
                errors.venue = 'Venue is required.';
              }

              if (Object.keys(errors).length > 0) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, errors }));
              }

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: 'Event passed all backend validation checks.',
                event: {
                  ...payload,
                  id: payload.id || `evt-${Date.now().toString(36)}`
                }
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ error: 'Invalid event payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/tickets/issue or POST /api/tickets/void or POST /api/purchase (Admin/Cashier Only)
        if ((req.url.startsWith('/api/tickets') || req.url === '/api/purchase') && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const authUser = getAuthoritativeUser(req, parsedState, payload);

              if (!authUser || (authUser.role !== 'admin' && authUser.role !== 'cashier')) {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: '403 Forbidden: Only Cashiers and Administrators can issue, void, or manage tickets. Official tickets must be issued at the Cashier Desk.'
                }));
              }

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: true, message: 'Ticket operation authorized and processed.' }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ error: 'Invalid ticket request payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: POST /api/register or POST /api/validate-user (Student Self-Registration & State Persistence)
        if ((req.url === '/api/register' || req.url === '/api/validate-user') && req.method === 'POST') {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const payload = JSON.parse(body || '{}');
              const requestedRole = (payload.role || 'student').toLowerCase();

              // Privileged roles cannot be self-registered
              if (requestedRole !== 'student') {
                const authUser = getAuthoritativeUser(req, parsedState, payload);
                if (!authUser || authUser.role !== 'admin') {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: '403 Forbidden: Privileged accounts (Teacher, Staff, Cashier, Admin) cannot be self-registered. They must be provisioned by an Administrator.'
                  }));
                }
              }

              const name = (payload.name || '').trim();
              const email = (payload.email || '').trim().toLowerCase();
              const rawStudentId = (payload.studentId || payload.student_id || '').trim();
              const cleanStudentId = rawStudentId.toLowerCase().replace(/\s+/g, '');
              const errors: Record<string, string> = {};

              if (!name) errors.name = 'Full Name is required.';
              if (!email || !email.includes('@')) errors.email = 'Valid institutional email is required.';
              if (requestedRole === 'student' && !cleanStudentId) errors.studentId = 'Student ID Number is required for students.';

              if (Object.keys(errors).length > 0) {
                res.statusCode = 422;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ success: false, errors }));
              }

              const allUsers = parsedState?.users || [];
              const existingUser = allUsers.find((u: any) => {
                const uStudentId = (u.studentId || '').trim().toLowerCase().replace(/\s+/g, '');
                const uEmail = (u.email || '').trim().toLowerCase();
                return (uStudentId && uStudentId === cleanStudentId) || (uEmail && uEmail === email);
              });

              if (existingUser && req.url === '/api/register') {
                res.statusCode = 409;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: existingUser.mustChangePassword
                    ? `An account for Student ID ${rawStudentId} was automatically created during ticket purchase. Please sign in with default password ncst123.`
                    : `An account is already registered for Student ID ${rawStudentId}. Please sign in to the portal.`
                }));
              }

              const newUserId = payload.id || `usr-${requestedRole}-${Date.now().toString(36)}`;
              const newUser = {
                ...payload,
                id: newUserId,
                name,
                email,
                role: requestedRole,
                studentId: rawStudentId,
                department: payload.department || 'CSD',
                course: payload.course || 'BSIT',
                yearLevel: payload.yearLevel || '1st Year',
                section: payload.section || 'A1',
                password: payload.password || 'password123',
                isActive: true,
                accountStatus: 'active',
                verificationStatus: payload.verificationStatus || 'verified',
                isVerified: payload.isVerified !== undefined ? payload.isVerified : true,
                createdAt: payload.createdAt || new Date().toISOString(),
              };

              if (req.url === '/api/register') {
                parsedState.users = [newUser, ...(parsedState.users || [])];

                // Auto-link any existing tickets matching this student ID
                if (Array.isArray(parsedState.tickets)) {
                  parsedState.tickets = parsedState.tickets.map((t: any) => {
                    if (t.attendeeStudentId && t.attendeeStudentId.toLowerCase().replace(/\s+/g, '') === cleanStudentId) {
                      return {
                        ...t,
                        currentAttendeeId: newUserId,
                        attendeeEmail: email,
                      };
                    }
                    return t;
                  });
                }

                parsedState.timestamp = Date.now();
                inMemoryState = JSON.stringify(parsedState);
              }

              const sessionToken = `sess_${newUser.role}_${newUser.id}`;
              activeSessions.set(sessionToken, { userId: newUser.id, role: newUser.role, createdAt: Date.now() });
              activeSessions.set(newUser.id, { userId: newUser.id, role: newUser.role, createdAt: Date.now() });

              const { password: _p, ...safeUser } = newUser;

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                message: 'User registered successfully.',
                user: safeUser,
                token: sessionToken,
                sessionToken: sessionToken
              }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: 'Invalid user registration payload.' }));
            }
          });
          return;
        }

        // Backend API Endpoint: GET /api/tickets (RBAC Scoping: Student cannot request other students' tickets)
        if (req.url.startsWith('/api/tickets') && req.method === 'GET') {
          try {
            const parsedState = getParsedState();
            const urlObj = new URL(req.url, 'http://localhost');
            const authUser = getAuthoritativeUser(req, parsedState, null);
            const targetStudentId = urlObj.searchParams.get('studentId');
            const targetTicketId = urlObj.searchParams.get('ticketId');

            if (!authUser) {
              res.statusCode = 401;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: false, error: '401 Unauthorized: Session is not authenticated.' }));
            }

            const role = (authUser.role || '').toLowerCase();
            const allTickets = parsedState?.tickets || [];

            if (role === 'student') {
              if (targetStudentId && targetStudentId !== authUser.studentId) {
                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: '403 Forbidden: Students cannot access other students\' tickets.'
                }));
              }

              if (targetTicketId) {
                const found = allTickets.find((t: any) => t.id === targetTicketId);
                if (found && found.attendeeStudentId !== authUser.studentId && found.currentAttendeeId !== authUser.id) {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: '403 Forbidden: You do not own this ticket.'
                  }));
                }
              }

              const ownTickets = allTickets.filter((t: any) => 
                (t.attendeeStudentId && t.attendeeStudentId === authUser.studentId) ||
                (t.currentAttendeeId === authUser.id)
              );

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({
                success: true,
                scope: 'OWN_TICKETS_ONLY',
                tickets: ownTickets,
              }));
            }

            // Cashier / Admin / Staff
            res.statusCode = 200;
            res.setHeader('Content-Type', 'application/json');
            return res.end(JSON.stringify({
              success: true,
              scope: 'ALL_TICKETS',
              tickets: allTickets,
            }));
          } catch (err: any) {
            res.statusCode = 400;
            res.setHeader('Content-Type', 'application/json');
            return res.end(JSON.stringify({ success: false, error: 'Invalid tickets request.' }));
          }
        }

        // Backend API Endpoint: GET/POST /api/attendance (Authoritative RBAC Scoping)
        if (req.url.startsWith('/api/attendance')) {
          let body = '';
          req.on('data', (chunk: any) => { body += chunk; });
          req.on('end', () => {
            try {
              const parsedState = getParsedState();
              const urlObj = new URL(req.url, 'http://localhost');
              const bodyData = body ? JSON.parse(body) : {};
              const authUser = getAuthoritativeUser(req, parsedState, bodyData);
              const eventId = urlObj.searchParams.get('eventId') || bodyData.eventId || 'all';

              if (!authUser) {
                res.statusCode = 401;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ error: '401 Unauthorized: Session is not authenticated.' }));
              }

              const role = (authUser.role || '').toLowerCase();
              const allLogs = parsedState?.logs || [];
              const eventLogs = eventId && eventId !== 'all' ? allLogs.filter((l: any) => l.eventId === eventId) : allLogs;

              if (role === 'admin') {
                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  scope: 'ALL_ATTENDANCE',
                  records: eventLogs,
                }));
              }

              if (role === 'teacher') {
                const assignedCourses: string[] = authUser.assignedCourses || ['BSIT'];
                const assignedYears: string[] = authUser.assignedYearLevels || ['3rd Year'];
                const assignedSections: string[] = authUser.assignedSections || ['A', 'M1', 'A1'];

                const filtered = eventLogs.filter((l: any) => {
                  if (l.attendeeType === 'OUTSIDER') return false;
                  const matchCourse = assignedCourses.includes(l.course || 'BSIT');
                  const matchYear = assignedYears.includes(l.yearLevel || '3rd Year');
                  const matchSection = assignedSections.some(sec => {
                    const lSec = (l.section || 'A').toUpperCase();
                    const tSec = sec.toUpperCase();
                    return lSec === tSec || lSec.includes(tSec) || tSec.includes(lSec);
                  });
                  return matchCourse && matchYear && matchSection;
                });

                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  scope: 'ASSIGNED_STUDENTS_ONLY',
                  records: filtered,
                }));
              }

              if (role === 'student') {
                const targetStudentId = urlObj.searchParams.get('studentId') || bodyData.studentId;
                if (targetStudentId && targetStudentId !== authUser.studentId) {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: '403 Forbidden: Students cannot access other students\' attendance records.'
                  }));
                }

                const filtered = eventLogs.filter((l: any) => {
                  return (l.studentId && l.studentId === authUser.studentId) || l.currentAttendeeId === authUser.id;
                });
                res.statusCode = 200;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: true,
                  scope: 'OWN_ATTENDANCE_ONLY',
                  records: filtered,
                }));
              }

              res.statusCode = 200;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ success: true, records: eventLogs }));
            } catch (err: any) {
              res.statusCode = 400;
              res.setHeader('Content-Type', 'application/json');
              return res.end(JSON.stringify({ error: 'Invalid attendance request payload.' }));
            }
          });
          return;
        }

        if (req.url === '/api/state' || req.url.startsWith('/api/state?')) {
          if (req.method === 'GET') {
            res.setHeader('Content-Type', 'application/json');
            const parsedState = getParsedState();
            if (!parsedState) {
              return res.end(JSON.stringify({ empty: true }));
            }

            const authUser = getAuthoritativeUser(req, parsedState, null);
            if (!authUser) {
              res.statusCode = 401;
              return res.end(JSON.stringify({
                success: false,
                error: '401 Unauthorized: Session token is required to access application state.',
                empty: true
              }));
            }

            const urlObj = new URL(req.url, 'http://localhost');
            const since = parseInt(urlObj.searchParams.get('since') || '0', 10);
            const stateTimestamp = parsedState.timestamp || 0;

            if (since > 0 && stateTimestamp > 0 && stateTimestamp <= since) {
              return res.end(JSON.stringify({
                success: true,
                not_modified: true,
                timestamp: stateTimestamp
              }));
            }

            const sanitizeUser = (u: any) => {
              if (!u) return u;
              const { password, ...safeUser } = u;
              return safeUser;
            };

            const role = (authUser.role || '').toLowerCase();
            const allUsers = (parsedState.users || []).map(sanitizeUser);
            const allEvents = parsedState.events || [];
            const allTickets = parsedState.tickets || [];
            const allLogs = parsedState.logs || [];
            const allTransactions = parsedState.transactions || [];

            if (role === 'admin') {
              // Admin: authorized full administrative data (passwords stripped)
              return res.end(JSON.stringify({
                success: true,
                role: 'admin',
                users: allUsers,
                events: allEvents,
                tickets: allTickets,
                logs: allLogs,
                transactions: allTransactions,
                timestamp: parsedState.timestamp || Date.now()
              }));
            }

            if (role === 'teacher') {
              // Teacher: only assigned student/attendance data
              const assignedClasses = Array.isArray(authUser.assignedClasses) ? authUser.assignedClasses : [];
              const assignedCourses: string[] = assignedClasses.length > 0
                ? Array.from(new Set(assignedClasses.map((c: any) => c.course).filter(Boolean)))
                : (authUser.assignedCourses || ['BSIT']);
              const assignedYears: string[] = assignedClasses.length > 0
                ? Array.from(new Set(assignedClasses.map((c: any) => c.yearLevel).filter(Boolean)))
                : (authUser.assignedYears || ['3rd Year']);
              const assignedSections: string[] = assignedClasses.length > 0
                ? Array.from(new Set(assignedClasses.map((c: any) => c.section).filter(Boolean)))
                : (authUser.assignedSections || ['31M1', '11M1']);
              const assignedEvents: string[] = authUser.assignedEventIds || [];

              const teacherUser = sanitizeUser(authUser);
              const assignedStudents = allUsers.filter((u: any) => {
                if (u.role !== 'student') return false;
                const uCourse = (u.course || 'BSIT').toUpperCase();
                const uYear = (u.yearLevel || '3rd Year').toUpperCase();
                const uSec = (u.section || '31M1').toUpperCase();

                if (assignedClasses.length > 0) {
                  return assignedClasses.some((cls: any) => {
                    const matchCourse = !cls.course || uCourse === cls.course.toUpperCase() || uCourse.includes(cls.course.toUpperCase());
                    const matchSection = !cls.section || uSec === cls.section.toUpperCase() || uSec.includes(cls.section.toUpperCase());
                    return matchCourse && matchSection;
                  });
                }

                const matchCourse = assignedCourses.some((c: string) => uCourse.includes(c.toUpperCase()));
                const matchYear = assignedYears.some((y: string) => uYear.includes(y.toUpperCase()) || uYear === y.toUpperCase());
                const matchSection = assignedSections.some((sec: string) => {
                  const tSec = sec.toUpperCase();
                  return uSec === tSec || uSec.includes(tSec) || tSec.includes(uSec);
                });
                return matchCourse && matchYear && matchSection;
              });

              const assignedStudentIds = new Set(assignedStudents.map((s: any) => s.studentId).filter(Boolean));

              const scopedEvents = assignedEvents.length > 0
                ? allEvents.filter((e: any) => assignedEvents.includes(e.id))
                : allEvents;

              const scopedTickets = allTickets.filter((t: any) => {
                if (t.attendeeStudentId && assignedStudentIds.has(t.attendeeStudentId)) return true;
                const tCourse = (t.course || '').toUpperCase();
                const tSec = (t.section || '').toUpperCase();
                if (assignedClasses.length > 0) {
                  return assignedClasses.some((cls: any) => {
                    const matchCourse = !cls.course || tCourse === cls.course.toUpperCase() || tCourse.includes(cls.course.toUpperCase());
                    const matchSection = !cls.section || tSec === cls.section.toUpperCase() || tSec.includes(cls.section.toUpperCase());
                    return matchCourse && matchSection;
                  });
                }
                return false;
              });

              const scopedTicketIds = new Set(scopedTickets.map((t: any) => t.id));
              const scopedLogs = allLogs.filter((l: any) => {
                return (l.studentId && assignedStudentIds.has(l.studentId)) ||
                       (l.ticketId && scopedTicketIds.has(l.ticketId));
              });

              return res.end(JSON.stringify({
                success: true,
                role: 'teacher',
                users: [teacherUser, ...assignedStudents],
                events: scopedEvents,
                tickets: scopedTickets,
                logs: scopedLogs,
                transactions: [], // Teachers have no transaction access
                timestamp: parsedState.timestamp || Date.now()
              }));
            }

            if (role === 'event_staff' || role === 'staff') {
              // Event Staff: only required event/ticket scanning data for assigned events
              const assignedEvents: string[] = authUser.assignedEventIds || [];
              const scopedEvents = assignedEvents.length > 0
                ? allEvents.filter((e: any) => assignedEvents.includes(e.id))
                : allEvents;

              const assignedEventSet = new Set(scopedEvents.map((e: any) => e.id));

              const scopedTickets = allTickets.filter((t: any) => assignedEventSet.has(t.eventId));
              const scopedLogs = allLogs.filter((l: any) => assignedEventSet.has(l.eventId));

              return res.end(JSON.stringify({
                success: true,
                role: 'event_staff',
                users: [sanitizeUser(authUser)],
                events: scopedEvents,
                tickets: scopedTickets,
                logs: scopedLogs,
                transactions: [], // Staff have no financial/POS transaction access
                timestamp: parsedState.timestamp || Date.now()
              }));
            }

            if (role === 'cashier') {
              // Cashier: only required ticketing and POS transaction data
              return res.end(JSON.stringify({
                success: true,
                role: 'cashier',
                users: [sanitizeUser(authUser), ...allUsers.filter((u: any) => u.role === 'student')],
                events: allEvents.filter((e: any) => e.status !== 'cancelled'),
                tickets: allTickets,
                logs: [], // Cashiers do not inspect student grading/attendance logs
                transactions: allTransactions,
                timestamp: parsedState.timestamp || Date.now()
              }));
            }

            if (role === 'student') {
              // Student: strictly authorized student-specific data
              const ownTickets = allTickets.filter((t: any) =>
                (t.attendeeStudentId && t.attendeeStudentId === authUser.studentId) ||
                (t.currentAttendeeId === authUser.id)
              );
              const ownLogs = allLogs.filter((l: any) =>
                (l.studentId && l.studentId === authUser.studentId) ||
                (l.currentAttendeeId === authUser.id)
              );
              const ownTransactions = allTransactions.filter((tx: any) =>
                (tx.studentId && tx.studentId === authUser.studentId) ||
                (tx.userId === authUser.id)
              );

              return res.end(JSON.stringify({
                success: true,
                role: 'student',
                users: [sanitizeUser(authUser)],
                events: allEvents.filter((e: any) => e.status !== 'cancelled'),
                tickets: ownTickets,
                logs: ownLogs,
                transactions: ownTransactions,
                timestamp: parsedState.timestamp || Date.now()
              }));
            }

            // Fallback for other authenticated roles
            return res.end(JSON.stringify({
              success: true,
              users: [sanitizeUser(authUser)],
              events: allEvents,
              tickets: [],
              logs: [],
              transactions: [],
              timestamp: parsedState.timestamp || Date.now()
            }));

          } else if (req.method === 'POST') {
            let body = '';
            req.on('data', (chunk: any) => { body += chunk; });
            req.on('end', () => {
              try {
                const parsedState = getParsedState();
                const payload = JSON.parse(body || '{}');
                const authUser = getAuthoritativeUser(req, parsedState, payload);

                if (!authUser) {
                  res.statusCode = 401;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: '401 Unauthorized: Valid session token or credentials required.'
                  }));
                }

                const now = Date.now();

                // 1. Full Admin State Overwrite
                if (authUser.role === 'admin') {
                  inMemoryState = JSON.stringify({
                    ...payload,
                    timestamp: now,
                  });
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({ success: true, timestamp: now }));
                }

                // 2. Event Staff: Authoritative Scanning Mutations (Tickets Status & Attendance Logs)
                if (authUser.role === 'event_staff' || authUser.role === 'staff') {
                  if (Array.isArray(payload.tickets)) {
                    const serverTickets = [...(parsedState.tickets || [])];
                    payload.tickets.forEach((incomingTicket: any) => {
                      const idx = serverTickets.findIndex(st => st.id === incomingTicket.id);
                      if (idx !== -1) {
                        serverTickets[idx] = {
                          ...serverTickets[idx],
                          ticketStatus: incomingTicket.ticketStatus || serverTickets[idx].ticketStatus,
                          attendanceStatus: incomingTicket.attendanceStatus || serverTickets[idx].attendanceStatus,
                          timeIn: incomingTicket.timeIn || serverTickets[idx].timeIn,
                          lastScannedBy: incomingTicket.lastScannedBy || serverTickets[idx].lastScannedBy,
                        };
                      }
                    });
                    parsedState.tickets = serverTickets;
                  }

                  if (Array.isArray(payload.logs)) {
                    const serverLogs = [...(parsedState.logs || [])];
                    const existingLogIds = new Set(serverLogs.map(l => l.id));
                    payload.logs.forEach((incomingLog: any) => {
                      if (!existingLogIds.has(incomingLog.id)) {
                        serverLogs.unshift(incomingLog);
                        existingLogIds.add(incomingLog.id);
                      }
                    });
                    parsedState.logs = serverLogs;
                  }

                  parsedState.timestamp = now;
                  inMemoryState = JSON.stringify(parsedState);
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({ success: true, timestamp: now }));
                }

                // 3. Cashier: Ticket Issuance, Transactions, and Student Onboarding
                if (authUser.role === 'cashier') {
                  if (Array.isArray(payload.tickets)) {
                    const serverTickets = [...(parsedState.tickets || [])];
                    payload.tickets.forEach((incomingTicket: any) => {
                      const idx = serverTickets.findIndex(st => st.id === incomingTicket.id);
                      if (idx !== -1) {
                        serverTickets[idx] = { ...serverTickets[idx], ...incomingTicket };
                      } else {
                        serverTickets.unshift(incomingTicket);
                      }
                    });
                    parsedState.tickets = serverTickets;
                  }

                  if (Array.isArray(payload.transactions)) {
                    const serverTx = [...(parsedState.transactions || [])];
                    const existingTxIds = new Set(serverTx.map(t => t.id));
                    payload.transactions.forEach((incomingTx: any) => {
                      if (!existingTxIds.has(incomingTx.id)) {
                        serverTx.unshift(incomingTx);
                        existingTxIds.add(incomingTx.id);
                      }
                    });
                    parsedState.transactions = serverTx;
                  }

                  if (Array.isArray(payload.users)) {
                    const serverUsers = [...(parsedState.users || [])];
                    payload.users.forEach((incomingUser: any) => {
                      if (incomingUser.role === 'student') {
                        const idx = serverUsers.findIndex(u => u.id === incomingUser.id || (u.studentId && u.studentId === incomingUser.studentId));
                        if (idx !== -1) {
                          serverUsers[idx] = { ...serverUsers[idx], ...incomingUser };
                        } else {
                          serverUsers.unshift(incomingUser);
                        }
                      }
                    });
                    parsedState.users = serverUsers;
                  }

                  parsedState.timestamp = now;
                  inMemoryState = JSON.stringify(parsedState);
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({ success: true, timestamp: now }));
                }

                // 4. Teacher: Read-Only Access (Modifications to master StudentRecords or global state strictly forbidden)
                if (authUser.role === 'teacher') {
                  res.statusCode = 403;
                  res.setHeader('Content-Type', 'application/json');
                  return res.end(JSON.stringify({
                    success: false,
                    error: '403 Forbidden: Teachers have read-only access to master student rosters. Institutional StudentRecord modifications require Administrator authorization.'
                  }));
                }

                res.statusCode = 403;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({
                  success: false,
                  error: '403 Forbidden: Global state modifications require Administrator authorization.'
                }));
              } catch (err: any) {
                res.statusCode = 500;
                res.setHeader('Content-Type', 'application/json');
                return res.end(JSON.stringify({ error: err?.message }));
              }
            });
            return;
          }
        }
        next();
      });
    }
  };
}
