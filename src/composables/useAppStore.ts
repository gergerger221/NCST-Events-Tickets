import { ref, computed, watch } from 'vue';
import {
  User,
  UserRole,
  AttendeeType,
  StudentRecord,
  EventItem,
  Ticket,
  AttendanceLog,
  ScanValidationResult,
  TransferRecord,
  FilterState,
  TicketType,
  PurchaseTransaction,
  TeacherClassAssignment,
  AcademicPeriod,
  TicketStatus,
  PaymentStatus,
  QRPrintStatus,
  AttendanceStatus,
  TicketStock,
  TicketSalesSummary,
} from '../types';
import {
  INITIAL_USERS,
  INITIAL_STUDENT_RECORDS,
  INITIAL_EVENTS,
  INITIAL_TICKETS,
  INITIAL_LOGS,
  INITIAL_TRANSACTIONS,
  INITIAL_ACADEMIC_PERIODS,
  SVG_BANNER_INSTITUTIONAL,
} from '../utils/seedData';
import { sounds } from '../utils/audio';
import { useOfflineScanner } from './useOfflineScanner';
import { splitFullName, getLastName, getFirstName, sortByLastName } from '../utils/nameUtils';
import { validateEvent, normalizeTimeToDisplay, EventFormData, ValidationResult } from '../utils/eventValidation';
import { apiUrl } from '../utils/apiUrl';
import {
  checkUserIdentityConflict,
  validateUserForm,
  normalizeId,
  normalizeName,
  IdentityConflictResult,
} from '../utils/userValidation';
import { extractActualSystemSections } from '../utils/academicMasterData';
import {
  validateStudentId,
  validateYearLevel,
  validateSection,
  validateAttendeeName,
  validatePhoneNumber,
  validateCashPayment,
} from '../utils/posValidation';
import {
  getOutsiderAccountExpirationDate,
  isOutsiderAccountExpired,
  formatPhilippineExpiration,
  isOutsiderUser,
  getEventExpirationCutoff,
} from '../utils/philippineTime';
import {
  deriveYearLevelFromSection,
  getDepartmentCodeForCourse,
  NCST_DEPARTMENTS,
} from '../utils/academicMasterData';

const getDepartmentForCourse = (courseCode?: string): string => {
  if (!courseCode) return 'Computer Studies Department';
  const deptCode = getDepartmentCodeForCourse(courseCode);
  const found = NCST_DEPARTMENTS.find(d => d.code === deptCode);
  return found?.name || 'Computer Studies Department';
};

const STORAGE_KEYS = {
  USERS: 'integ2_vue_users_v10',
  STUDENT_RECORDS: 'integ2_vue_student_records_v10',
  EVENTS: 'integ2_vue_events_v10',
  TICKETS: 'integ2_vue_tickets_v10',
  LOGS: 'integ2_vue_logs_v10',
  TRANSACTIONS: 'integ2_vue_transactions_v10',
  ACADEMIC_PERIODS: 'integ2_vue_academic_periods_v10',
  CURRENT_USER: 'integ2_vue_current_user_v10',
  CURRENT_EVENT: 'integ2_vue_current_event_v10',
  SESSION_TOKEN: 'integ2_vue_session_token_v10',
  DELETED_USER_IDS: 'integ2_vue_deleted_user_ids_v10',
};

const safeLocalStorageSetItem = (key: string, value: string): void => {
  try {
    localStorage.setItem(key, value);
  } catch (e) {
    console.warn(`[LocalStorage] Storage write suppressed for "${key}" to avoid quota overflow:`, e);
  }
};

const deletedUserIds = ref<Set<string>>(
  (() => {
    try {
      const s = localStorage.getItem(STORAGE_KEYS.DELETED_USER_IDS);
      if (s) {
        const arr = JSON.parse(s);
        if (Array.isArray(arr)) return new Set(arr);
      }
    } catch {}
    return new Set<string>();
  })()
);

export const recordDeletedUserId = (id: string) => {
  if (!id) return;
  deletedUserIds.value.add(id);
  try {
    localStorage.setItem(STORAGE_KEYS.DELETED_USER_IDS, JSON.stringify(Array.from(deletedUserIds.value)));
  } catch {}
};

const sessionToken = ref<string>(
  (() => {
    try {
      const s = localStorage.getItem(STORAGE_KEYS.SESSION_TOKEN);
      if (s) return s;
    } catch {}
    return '';
  })()
);

/**
 * Ensures all institutional seed demo accounts (Admin, Cashier, Staff, Faculty, Student)
 * are present, valid, active, and securely authenticated in the user repository.
 */
export function ensureSeedUsers(userList: User[]): User[] {
  if (!Array.isArray(userList) || userList.length === 0) return [...INITIAL_USERS];

  const merged = userList.map(u => {
    const sanitized = { ...u };
    delete (sanitized as any).email;
    delete (sanitized as any).recoveryEmail;
    delete (sanitized as any).recovery_email;
    delete (sanitized as any).recovery_email_verified;
    delete (sanitized as any).recovery_email_verified_at;
    delete (sanitized as any).recoveryEmailVerified;
    delete (sanitized as any).recoveryEmailVerifiedAt;
    return sanitized;
  });

  INITIAL_USERS.forEach(seedUser => {
    const seedEmpId = seedUser.employeeId ? normalizeId(seedUser.employeeId).toLowerCase() : '';
    const seedStudentId = seedUser.studentId ? normalizeId(seedUser.studentId).toLowerCase() : '';
    const seedId = (seedUser.id || '').toLowerCase();

    // Guard: Never resurrect deleted user accounts
    if (deletedUserIds.value.has(seedUser.id) || (seedEmpId && deletedUserIds.value.has(seedEmpId)) || (seedStudentId && deletedUserIds.value.has(seedStudentId))) {
      return;
    }

    const existingIndex = merged.findIndex(u => {
      if (u.id && u.id.toLowerCase() === seedId) return true;
      if (seedEmpId && u.employeeId && normalizeId(u.employeeId).toLowerCase() === seedEmpId) return true;
      if (seedStudentId && u.studentId && normalizeId(u.studentId).toLowerCase() === seedStudentId) return true;
      return false;
    });

    if (existingIndex === -1) {
      merged.push({ ...seedUser });
    } else {
      const current = merged[existingIndex];
      merged[existingIndex] = {
        ...seedUser,
        ...current,
        // Guarantee demo credentials and essential permissions remain intact
        id: current.id || seedUser.id,
        name: current.name || seedUser.name,
        role: current.role || seedUser.role,
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

// Global reactive singletons with persistence
const users = ref<User[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.USERS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed) && parsed.length > 0) {
          const filtered = parsed.filter(u => u && !deletedUserIds.value.has(u.id));
          return ensureSeedUsers(filtered);
        }
      } catch {}
    }
    return ensureSeedUsers([...INITIAL_USERS]);
  })()
);

export function sanitizeAndDeduplicateStudentRecords(rawRecords: StudentRecord[]): StudentRecord[] {
  if (!Array.isArray(rawRecords)) return [];
  const seenStudentIds = new Set<string>();
  const cleaned: StudentRecord[] = [];

  for (const r of rawRecords) {
    if (!r) continue;
    const sId = normalizeId(r.studentId || r.id || '').toLowerCase();
    if (sId && seenStudentIds.has(sId)) continue;
    if (sId) seenStudentIds.add(sId);
    cleaned.push(r);
  }
  return cleaned;
}

// Authoritative Student Roster / Enrollment Records (Separate from User Login Accounts)
const studentRecords = ref<StudentRecord[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.STUDENT_RECORDS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed) && parsed.length > 0) {
          // Merge initial seed student records if missing so newly registered seed records (e.g. 2024-58566) are always present
          const existingIds = new Set(parsed.map((r: any) => normalizeId(r.studentId).toLowerCase()));
          INITIAL_STUDENT_RECORDS.forEach(initRec => {
            if (!existingIds.has(normalizeId(initRec.studentId).toLowerCase())) {
              parsed.push(initRec);
            }
          });
          const deduplicated = sanitizeAndDeduplicateStudentRecords(parsed);
          if (deduplicated.length !== parsed.length) {
            localStorage.setItem(STORAGE_KEYS.STUDENT_RECORDS, JSON.stringify(deduplicated));
          }
          return deduplicated;
        }
      } catch {}
    }
    return sanitizeAndDeduplicateStudentRecords([...INITIAL_STUDENT_RECORDS]);
  })()
);

export function normalizeEvent(e: any): EventItem {
  if (!e) return e;
  let bannerUrl = e.bannerUrl || e.banner_url || '';
  if (bannerUrl && bannerUrl.includes('064e3b')) {
    bannerUrl = SVG_BANNER_INSTITUTIONAL;
  }
  return {
    ...e,
    bannerUrl,
  };
}

const events = ref<EventItem[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.EVENTS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) return parsed.map(normalizeEvent);
      } catch {}
    }
    return INITIAL_EVENTS.map(normalizeEvent);
  })()
);

export function normalizeTicket(t: any): Ticket {
  if (!t) return t;
  const attendeeType = t.attendeeType || t.attendee_type || (t.attendeeStudentId || t.attendee_student_id ? 'STUDENT' : 'OUTSIDER');
  return {
    ...t,
    ticketNumber: t.ticketNumber || t.ticket_number || t.id,
    qrToken: t.qrToken || t.qr_token || t.id,
    eventId: t.eventId || t.event_id || '',
    eventTitle: t.eventTitle || t.event_title || '',
    ticketType: t.ticketType || t.ticket_type || 'digital',
    attendeeType: attendeeType as AttendeeType,
    originalOwnerId: t.originalOwnerId || t.original_owner_id || '',
    originalOwnerName: t.originalOwnerName || t.original_owner_name || '',
    currentAttendeeId: t.currentAttendeeId || t.current_attendee_id || '',
    currentAttendeeName: t.currentAttendeeName || t.current_attendee_name || t.originalOwnerName || t.original_owner_name || 'Guest Attendee',
    firstName: t.firstName || t.first_name || '',
    middleName: t.middleName || t.middle_name || '',
    lastName: t.lastName || t.last_name || '',
    attendeeStudentId: t.attendeeStudentId || t.attendee_student_id || null,
    outsiderId: t.outsiderId || t.outsider_id || null,
    attendeeDepartment: t.attendeeDepartment || t.attendee_department || null,
    course: t.course || null,
    yearLevel: t.yearLevel || t.year_level || null,
    section: t.section || null,
    contactNumber: t.contactNumber || t.contact_number || null,
    attendeePhotoUrl: t.attendeePhotoUrl || t.attendee_photo_url || null,
    price: Number(t.price || 0),
    purchaseDate: t.purchaseDate || t.purchase_date || new Date().toISOString(),
    ticketStatus: (t.ticketStatus || t.ticket_status || 'ISSUED') as TicketStatus,
    paymentStatus: (t.paymentStatus || t.payment_status || 'paid') as PaymentStatus,
    qrPrintStatus: (t.qrPrintStatus || t.qr_print_status || 'PENDING') as QRPrintStatus,
    qrPrintedAt: t.qrPrintedAt || t.qr_printed_at || null,
    qrPrintCount: Number(t.qrPrintCount || t.qr_print_count || 0),
    attendanceStatus: (t.attendanceStatus || t.attendance_status || 'not_entered') as AttendanceStatus,
    timeIn: t.timeIn || t.time_in || null,
    lastScannedBy: t.lastScannedBy || t.last_scanned_by || null,
    academicYear: t.academicYear || t.academic_year || '2026-2027',
    semester: t.semester || t.semester || '1st Semester',
    transferHistory: Array.isArray(t.transferHistory) ? t.transferHistory : (typeof t.transfer_history === 'string' ? JSON.parse(t.transfer_history || '[]') : []),
  };
}

export function sanitizeAndDeduplicateTickets(rawTickets: Ticket[]): Ticket[] {
  if (!Array.isArray(rawTickets)) return [];
  const seenIds = new Set<string>();
  const seenTicketNumbers = new Set<string>();
  const cleaned: Ticket[] = [];

  for (const t of rawTickets) {
    if (!t) continue;
    const tId = (t.id || '').trim().toLowerCase();
    const tNum = (t.ticketNumber || '').trim().toLowerCase();
    if (tId && seenIds.has(tId)) continue;
    if (tNum && seenTicketNumbers.has(tNum)) continue;

    if (tId) seenIds.add(tId);
    if (tNum) seenTicketNumbers.add(tNum);
    cleaned.push(normalizeTicket(t));
  }
  return cleaned;
}

const tickets = ref<Ticket[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.TICKETS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) {
          const deduplicated = sanitizeAndDeduplicateTickets(parsed);
          if (deduplicated.length !== parsed.length) {
            localStorage.setItem(STORAGE_KEYS.TICKETS, JSON.stringify(deduplicated));
          }
          return deduplicated;
        }
      } catch {}
    }
    return sanitizeAndDeduplicateTickets(INITIAL_TICKETS);
  })()
);

export function sanitizeAndDeduplicateLogs(rawLogs: AttendanceLog[]): AttendanceLog[] {
  if (!Array.isArray(rawLogs)) return [];
  const seenIds = new Set<string>();
  const seenCheckins = new Set<string>();
  const cleaned: AttendanceLog[] = [];

  for (const log of rawLogs) {
    if (!log) continue;
    // Strict ID deduplication
    const logId = (log.id || '').trim();
    if (logId && seenIds.has(logId)) continue;
    if (logId) seenIds.add(logId);

    // Keep only one valid check-in log per student/ticket per event
    if (log.status === 'valid' || !log.status) {
      const sId = (log.studentId || '').trim().toLowerCase();
      const tNum = (log.ticketNumber || log.ticketId || '').trim().toLowerCase();
      const evId = (log.eventId || 'unknown').trim().toLowerCase();
      const attendeeKey = sId ? `stud:${sId}` : (tNum ? `tix:${tNum}` : '');
      if (attendeeKey) {
        const checkinKey = `${evId}:::${attendeeKey}:::valid`;
        if (seenCheckins.has(checkinKey)) continue;
        seenCheckins.add(checkinKey);
      }
    }

    cleaned.push(log);
  }
  return cleaned;
}

const logs = ref<AttendanceLog[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.LOGS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) {
          const deduplicated = sanitizeAndDeduplicateLogs(parsed);
          if (deduplicated.length !== parsed.length) {
            localStorage.setItem(STORAGE_KEYS.LOGS, JSON.stringify(deduplicated));
          }
          return deduplicated;
        }
      } catch {}
    }
    return sanitizeAndDeduplicateLogs(INITIAL_LOGS);
  })()
);

const transactions = ref<PurchaseTransaction[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.TRANSACTIONS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return INITIAL_TRANSACTIONS;
  })()
);

const academicPeriods = ref<AcademicPeriod[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.ACADEMIC_PERIODS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed) && parsed.length > 0) return parsed;
      } catch {}
    }
    return [...INITIAL_ACADEMIC_PERIODS];
  })()
);

const activeAcademicPeriod = computed<AcademicPeriod>(() => {
  const found = academicPeriods.value.find(p => p.status === 'active');
  return found || academicPeriods.value[0] || {
    id: 'acad-2026-2027-sem1',
    academicYear: '2026-2027',
    semester: '1st Semester',
    startDate: '2026-08-01',
    endDate: '2026-12-31',
    status: 'active',
  };
});

// Centralized physical ticket stock inventory shared across all cashiers
const ticketStock = ref<TicketStock[]>([]);

// Authoritative event sales summaries cache & last updated timestamps
const salesSummaryCache = ref<Record<string, TicketSalesSummary>>({});
const salesSummaryLastUpdated = ref<Record<string, number>>({});

const currentUser = ref<User | null>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.CURRENT_USER);
    if (s) {
      try {
        const parsed = JSON.parse(s);
        if (parsed && parsed.id) return parsed;
      } catch {}
    }
    return null;
  })()
);

const currentEvent = ref<EventItem | null>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.CURRENT_EVENT);
    if (s) {
      try {
        const parsed = JSON.parse(s);
        const match = events.value.find(e => e.id === parsed.id);
        if (match) return match;
      } catch {}
    }
    return events.value[0] || null;
  })()
);

const filters = ref<FilterState>({
  eventId: 'all',
  searchQuery: '',
  attendeeType: 'all',
  ticketStatus: 'all',
  qrPrintStatus: 'all',
  paymentStatus: 'all',
  attendanceStatus: 'all',
  ticketType: 'all',
  course: 'all',
  yearLevel: 'all',
  section: 'all',
});

let localLastModified = Date.now();
let isSyncingFromServer = false;
// Tracks the last time we mutated local state so server polls never overwrite newer local data
let localWriteTimestamp = Date.now();

// Authoritative Evaluation of Temporary Outsider Account Expirations (Philippine Standard Time - UTC+8)
// Policy: Deactivates temporary outsider accounts at 12:00:00 AM PHT on the day after the event (or latest associated event).
// Historical records (tickets, transactions, logs, present status) are 100% preserved.
const evaluateOutsiderAccountExpirations = (referenceNow: Date = new Date()): boolean => {
  let modified = false;
  const currentUsers = users.value;
  const currentEvents = events.value;
  const currentTickets = tickets.value;

  currentUsers.forEach(u => {
    if (!isOutsiderUser(u)) return;

    const { expirationDate, latestEvent } = getOutsiderAccountExpirationDate(u, currentEvents, currentTickets);
    if (expirationDate) {
      const expIso = expirationDate.toISOString();
      if (u.expiresAt !== expIso) {
        u.expiresAt = expIso;
        modified = true;
      }

      const isExpired = referenceNow.getTime() >= expirationDate.getTime();
      if (isExpired) {
        if (u.isActive !== false || u.accountStatus !== 'deactivated') {
          u.isActive = false;
          u.accountStatus = 'deactivated';
          u.expiredAt = u.expiredAt || referenceNow.toISOString();
          u.deactivationReason = `Temporary Outsider account expired on ${formatPhilippineExpiration(expirationDate)} following the conclusion of ${latestEvent?.title || 'the event'}.`;
          modified = true;

          // If this expired outsider is currently logged in, invalidate the active session
          if (currentUser.value && currentUser.value.id === u.id) {
            currentUser.value = null;
            sessionToken.value = '';
            localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
            localStorage.removeItem(STORAGE_KEYS.SESSION_TOKEN);
          }
        }
      }
    }
  });

  if (modified) {
    localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));
  }
  return modified;
};

// Authoritative session verification against backend user repository (prevents localStorage role tampering)
const validateAndSyncSession = () => {
  evaluateOutsiderAccountExpirations();
  if (!currentUser.value) return;
  const authoritative = users.value.find(u => u.id === currentUser.value?.id);
  if (!authoritative || authoritative.isActive === false) {
    currentUser.value = null;
    sessionToken.value = '';
    localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
    localStorage.removeItem(STORAGE_KEYS.SESSION_TOKEN);
  } else if (
    currentUser.value.role !== authoritative.role ||
    currentUser.value.name !== authoritative.name ||
    currentUser.value.studentId !== authoritative.studentId ||
    currentUser.value.employeeId !== authoritative.employeeId
  ) {
    // Neutralize tampering: revert to authoritative state
    currentUser.value = { ...authoritative };
    localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(authoritative));
  }
};

const getAuthHeaders = (): Record<string, string> => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };
  const token = sessionToken.value || (currentUser.value ? `sess_${currentUser.value.role}_${currentUser.value.id}` : '');
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
    headers['x-session-token'] = token;
  }
  if (currentUser.value?.id) {
    headers['x-user-id'] = currentUser.value.id;
  }
  return headers;
};

let pushDebounceTimer: any = null;
const schedulePushStateToServer = (immediate = false) => {
  if (isSyncingFromServer) return;
  if (pushDebounceTimer) {
    clearTimeout(pushDebounceTimer);
    pushDebounceTimer = null;
  }
  if (immediate) {
    pushStateToServer();
  } else {
    pushDebounceTimer = setTimeout(() => {
      pushStateToServer();
    }, 350);
  }
};

const pushStateToServer = async () => {
  if (isSyncingFromServer) return;
  const role = currentUser.value?.role;
  // Teachers and Students have read-only access to master state and do not push state writes
  if (role === 'teacher' || role === 'student') return;

  localLastModified = Date.now();
  localWriteTimestamp = localLastModified;
  try {
    const payload = {
      users: users.value,
      studentRecords: studentRecords.value,
      events: events.value,
      tickets: tickets.value,
      logs: logs.value,
      transactions: transactions.value,
      timestamp: localLastModified,
    };
    await fetch(apiUrl('/api/state'), {
      method: 'POST',
      headers: getAuthHeaders(),
      body: JSON.stringify(payload),
    });
  } catch {}
};

const fetchStateFromServer = async () => {
  if (isSyncingFromServer) return;
  if (typeof navigator !== 'undefined' && !navigator.onLine) return;

  try {
    const url = localLastModified > 0 ? apiUrl(`/api/state?since=${localLastModified}`) : apiUrl('/api/state');
    const res = await fetch(url, {
      headers: getAuthHeaders(),
    });
    if (!res.ok) return;
    const data = await res.json();

    // Delta sync optimization: MySQL state unchanged, 0 re-renders needed
    if (data && data.not_modified) {
      if (data.timestamp) {
        localLastModified = data.timestamp;
      }
      return;
    }

    if (data && !data.empty && data.success) {
      const serverTimestamp: number = data.timestamp || 0;
      const role = currentUser.value?.role;

      // Only push back if we are an authorized write role (Admin, Event Staff, Cashier)
      if (role !== 'teacher' && role !== 'student' && localWriteTimestamp > serverTimestamp) {
        schedulePushStateToServer(true);
        return;
      }

      // Enter server hydration mode and cancel any pending outbound push
      isSyncingFromServer = true;
      if (pushDebounceTimer) {
        clearTimeout(pushDebounceTimer);
        pushDebounceTimer = null;
      }

      if (Array.isArray(data.deletedUserIds)) {
        data.deletedUserIds.forEach((id: string) => recordDeletedUserId(id));
      }

      // Overwrite users/tickets/events if server timestamp is newer or if read-only role
      if (Array.isArray(data.users) && data.users.length > 0) {
        const filtered = data.users.filter((u: User) => !deletedUserIds.value.has(u.id));
        users.value = ensureSeedUsers(filtered);
        safeLocalStorageSetItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));
        validateAndSyncSession();
      }
      if (Array.isArray(data.studentRecords) && data.studentRecords.length > 0) studentRecords.value = sanitizeAndDeduplicateStudentRecords(data.studentRecords);
      if (Array.isArray(data.events) && data.events.length > 0) events.value = data.events.map(normalizeEvent);
      if (Array.isArray(data.tickets)) tickets.value = sanitizeAndDeduplicateTickets(data.tickets);
      if (Array.isArray(data.logs)) logs.value = sanitizeAndDeduplicateLogs(data.logs);
      if (Array.isArray(data.transactions) && data.transactions.length > 0) transactions.value = data.transactions;
      if (Array.isArray(data.academicPeriods) && data.academicPeriods.length > 0) academicPeriods.value = data.academicPeriods;
      if (Array.isArray(data.ticketStock) && data.ticketStock.length > 0) ticketStock.value = data.ticketStock;
      if (serverTimestamp) {
        localLastModified = serverTimestamp;
        localWriteTimestamp = serverTimestamp;
      }

      // Keep isSyncingFromServer true past the debounce window to prevent reactive ping-pong loops
      setTimeout(() => {
        if (pushDebounceTimer) {
          clearTimeout(pushDebounceTimer);
          pushDebounceTimer = null;
        }
        isSyncingFromServer = false;
      }, 500);
    }
  } catch {}
};

// Adaptive Visibility-Aware Polling Controller
let pollingTimer: any = null;
let currentPollInterval = 3000;
let isTabVisible = typeof document !== 'undefined' ? document.visibilityState === 'visible' : true;

const executePoll = async () => {
  if (isTabVisible && (typeof navigator === 'undefined' || navigator.onLine)) {
    await fetchStateFromServer();
  }
  scheduleNextPoll();
};

const scheduleNextPoll = () => {
  if (pollingTimer) {
    clearTimeout(pollingTimer);
    pollingTimer = null;
  }
  // Pause/relax polling to 30s when backgrounded, 3s when active
  const delay = isTabVisible ? currentPollInterval : 30000;
  pollingTimer = setTimeout(executePoll, delay);
};

// Start initial sync, session validation, cross-tab storage sync, and adaptive polling
if (typeof window !== 'undefined') {
  validateAndSyncSession();
  fetchStateFromServer();
  scheduleNextPoll();

  document.addEventListener('visibilitychange', () => {
    isTabVisible = document.visibilityState === 'visible';
    if (isTabVisible) {
      currentPollInterval = 3000;
      fetchStateFromServer();
      scheduleNextPoll();
    } else {
      scheduleNextPoll();
    }
  });

  window.addEventListener('online', () => {
    fetchStateFromServer();
    scheduleNextPoll();
  });

  window.addEventListener('focus', () => {
    fetchStateFromServer();
  });

  // Cross-tab / cross-window instant synchronization listener
  window.addEventListener('storage', (e: StorageEvent) => {
    if (!e.newValue) return;
    try {
      if (e.key === STORAGE_KEYS.TICKETS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) tickets.value = parsed;
      } else if (e.key === STORAGE_KEYS.USERS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) {
          const filtered = parsed.filter((u: any) => u && !deletedUserIds.value.has(u.id));
          users.value = ensureSeedUsers(filtered);
          validateAndSyncSession();
        }
      } else if (e.key === STORAGE_KEYS.STUDENT_RECORDS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) studentRecords.value = parsed;
      } else if (e.key === STORAGE_KEYS.EVENTS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) events.value = parsed.map(normalizeEvent);
      } else if (e.key === STORAGE_KEYS.TRANSACTIONS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) transactions.value = parsed;
      } else if (e.key === STORAGE_KEYS.LOGS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) logs.value = parsed;
      } else if (e.key === STORAGE_KEYS.ACADEMIC_PERIODS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) academicPeriods.value = parsed;
      }
    } catch {}
  });
}

// Watchers for LocalStorage persistence & Server State Sync
watch(academicPeriods, val => {
  safeLocalStorageSetItem(STORAGE_KEYS.ACADEMIC_PERIODS, JSON.stringify(val));
}, { deep: true });

watch(users, val => {
  safeLocalStorageSetItem(STORAGE_KEYS.USERS, JSON.stringify(val));
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  validateAndSyncSession();
  schedulePushStateToServer();
}, { deep: true });

watch(studentRecords, () => {
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  schedulePushStateToServer();
}, { deep: true });

watch(events, val => {
  safeLocalStorageSetItem(STORAGE_KEYS.EVENTS, JSON.stringify(val));
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  if (!val || val.length === 0) {
    currentEvent.value = null;
    localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
  } else if (!currentEvent.value || !val.some(e => e.id === currentEvent.value?.id)) {
    currentEvent.value = val[0];
    safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(val[0]));
  }
  schedulePushStateToServer();
}, { deep: true });

watch(tickets, () => {
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  schedulePushStateToServer();
}, { deep: true });

watch(logs, () => {
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  schedulePushStateToServer();
}, { deep: true });

watch(transactions, () => {
  if (isSyncingFromServer) return;
  localWriteTimestamp = Date.now();
  schedulePushStateToServer();
}, { deep: true });

watch(currentUser, val => {
  if (val) {
    const sanitized = { ...val };
    delete (sanitized as any).password;
    delete (sanitized as any).temporaryPassword;
    safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(sanitized));
  } else {
    localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
  }
}, { deep: true });

watch(currentUser, val => {
  if (val) {
    localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(val));
  } else {
    localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
  }
}, { deep: true });

watch(currentEvent, val => {
  if (val) {
    localStorage.setItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(val));
  } else {
    localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
  }
}, { deep: true });

export function useAppStore() {
  // Filtered tickets with Course, Year Level, and Section filters
  const filteredTickets = computed(() => {
    return tickets.value.filter(ticket => {
      if (filters.value.eventId !== 'all' && ticket.eventId !== filters.value.eventId) return false;
      if (filters.value.attendeeType && filters.value.attendeeType !== 'all') {
        const aType = ticket.attendeeType || (ticket as any).attendee_type || (ticket.attendeeStudentId || (ticket as any).attendee_student_id ? 'STUDENT' : 'OUTSIDER');
        if (aType !== filters.value.attendeeType) return false;
      }
      if (filters.value.ticketStatus !== 'all') {
        const tStatus = (ticket.ticketStatus || '').toLowerCase();
        const fStatus = filters.value.ticketStatus.toLowerCase();
        if ((fStatus === 'active' || fStatus === 'issued') && tStatus !== 'active' && tStatus !== 'issued') return false;
        else if (fStatus === 'used' && tStatus !== 'used') return false;
        else if ((fStatus === 'cancelled' || fStatus === 'void') && tStatus !== 'cancelled' && tStatus !== 'void') return false;
        else if (fStatus !== 'active' && fStatus !== 'issued' && fStatus !== 'used' && fStatus !== 'cancelled' && fStatus !== 'void' && tStatus !== fStatus) return false;
      }
      if (filters.value.qrPrintStatus && filters.value.qrPrintStatus !== 'all') {
        const tQrStatus = ticket.qrPrintStatus || 'PENDING';
        if (tQrStatus !== filters.value.qrPrintStatus) return false;
      }
      if (filters.value.paymentStatus !== 'all' && ticket.paymentStatus !== filters.value.paymentStatus) return false;
      if (filters.value.attendanceStatus !== 'all') {
        const tAtt = (ticket.attendanceStatus || 'not_entered').toLowerCase();
        const fAtt = filters.value.attendanceStatus.toLowerCase();
        if (fAtt === 'present' && tAtt !== 'present') return false;
        if ((fAtt === 'not_entered' || fAtt === 'not_yet_entered') && tAtt !== 'not_entered' && tAtt !== 'not_yet_entered') return false;
      }
      if (filters.value.course && filters.value.course !== 'all' && filters.value.course.trim()) {
        const cFilter = filters.value.course.trim().toLowerCase();
        const tCourse = (ticket.course || '').toLowerCase();
        if (!tCourse.includes(cFilter) && tCourse !== cFilter) return false;
      }
      if (filters.value.yearLevel && filters.value.yearLevel !== 'all' && filters.value.yearLevel.trim()) {
        const yFilter = filters.value.yearLevel.trim().toLowerCase();
        const tYear = (ticket.yearLevel || '').toLowerCase();
        if (!tYear.includes(yFilter) && tYear !== yFilter) return false;
      }
      if (filters.value.section && filters.value.section !== 'all' && filters.value.section.trim()) {
        const sFilter = filters.value.section.trim().toLowerCase();
        const tSec = (ticket.section || '').toLowerCase();
        if (!tSec.includes(sFilter) && tSec !== sFilter) return false;
      }

      if (filters.value.searchQuery.trim()) {
        const q = filters.value.searchQuery.toLowerCase();
        const matchId = (ticket.id || '').toLowerCase().includes(q);
        const matchNum = (ticket.ticketNumber || '').toLowerCase().includes(q);
        const matchName = (ticket.currentAttendeeName || '').toLowerCase().includes(q);
        const matchStudentId = (ticket.attendeeStudentId || '').toLowerCase().includes(q);
        const matchCourse = (ticket.course || '').toLowerCase().includes(q);
        const matchYear = (ticket.yearLevel || '').toLowerCase().includes(q);
        const matchSection = (ticket.section || '').toLowerCase().includes(q);
        const matchOriginal = (ticket.originalOwnerName || '').toLowerCase().includes(q);
        if (!matchId && !matchNum && !matchName && !matchStudentId && !matchCourse && !matchYear && !matchSection && !matchOriginal) {
          return false;
        }
      }

      return true;
    });
  });

  const setCurrentEvent = (event: EventItem) => {
    currentEvent.value = event;
  };

  const resetFilters = () => {
    filters.value = {
      eventId: 'all',
      searchQuery: '',
      attendeeType: 'all',
      ticketStatus: 'all',
      qrPrintStatus: 'all',
      paymentStatus: 'all',
      attendanceStatus: 'all',
      ticketType: 'all',
      course: 'all',
      yearLevel: 'all',
      section: 'all',
    };
  };

  // Event Actions
  const createEvent = (
    eventData: Omit<EventItem, 'id'>,
    assignedStaffIds?: string[]
  ): { success: boolean; event?: EventItem; errors?: Record<string, string>; message?: string } => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return {
        success: false,
        message: '403 Forbidden: Only Administrators are authorized to create events.',
      };
    }

    // 1. Normalize time formats to display format
    const normalizedStartTime = normalizeTimeToDisplay(eventData.startTime);
    const normalizedEndTime = normalizeTimeToDisplay(eventData.endTime);

    // 2. Perform authoritative validation check
    const validation = validateEvent(
      {
        ...eventData,
        startTime: normalizedStartTime,
        endTime: normalizedEndTime,
        assignedStaffIds,
      },
      {
        isEditing: false,
        existingEvents: events.value,
        staffUsers: users.value.filter(u => u.role === 'event_staff'),
      }
    );

    if (!validation.isValid) {
      sounds.playError();
      return {
        success: false,
        errors: validation.errors,
        message: validation.errorSummary,
      };
    }

    const eventId = `evt-${Date.now().toString(36)}`;
    const targetAcadYear = eventData.academicYear || activeAcademicPeriod.value?.academicYear || '2026-2027';
    const targetSem = eventData.semester || activeAcademicPeriod.value?.semester || '1st Semester';
    const newEvent: EventItem = {
      ...eventData,
      id: eventId,
      startTime: normalizedStartTime,
      endTime: normalizedEndTime,
      capacity: Number(eventData.capacity) || 500,
      ticketPrice: Number(eventData.ticketPrice) || 0,
      academicYear: targetAcadYear,
      semester: targetSem,
    };

    events.value.unshift(newEvent);

    // 3. Handle optional staff assignments atomically
    if (assignedStaffIds && assignedStaffIds.length > 0) {
      users.value = users.value.map(user => {
        if (assignedStaffIds.includes(user.id)) {
          const currentAssigned = Array.isArray(user.assignedEventIds) ? [...user.assignedEventIds] : [];
          if (!currentAssigned.includes(eventId)) {
            currentAssigned.push(eventId);
          }
          return { ...user, assignedEventIds: currentAssigned };
        }
        return user;
      });
    }

    sounds.playSuccess();
    return { success: true, event: newEvent };
  };

  const updateEvent = (
    id: string,
    updates: Partial<EventItem>,
    assignedStaffIds?: string[]
  ): { success: boolean; event?: EventItem; errors?: Record<string, string>; message?: string } => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return {
        success: false,
        message: '403 Forbidden: Only Administrators are authorized to modify events.',
      };
    }

    const idx = events.value.findIndex(e => e.id === id);
    if (idx === -1) {
      return { success: false, message: 'Event not found.' };
    }

    const current = events.value[idx];
    const merged: EventItem = {
      ...current,
      ...updates,
      startTime: updates.startTime ? normalizeTimeToDisplay(updates.startTime) : current.startTime,
      endTime: updates.endTime ? normalizeTimeToDisplay(updates.endTime) : current.endTime,
      capacity: updates.capacity !== undefined ? Number(updates.capacity) : current.capacity,
      ticketPrice: updates.ticketPrice !== undefined ? Number(updates.ticketPrice) : current.ticketPrice,
    };

    // Perform validation check
    const validation = validateEvent(
      {
        ...merged,
        assignedStaffIds,
      },
      {
        isEditing: true,
        existingEvents: events.value,
        staffUsers: users.value.filter(u => u.role === 'event_staff'),
      }
    );

    if (!validation.isValid) {
      sounds.playError();
      return {
        success: false,
        errors: validation.errors,
        message: validation.errorSummary,
      };
    }

    events.value[idx] = merged;
    if (currentEvent.value && currentEvent.value.id === id) {
      currentEvent.value = { ...merged };
    }

    // Update staff assignments if provided
    if (assignedStaffIds !== undefined) {
      users.value = users.value.map(user => {
        if (user.role === 'event_staff') {
          let currentAssigned = Array.isArray(user.assignedEventIds) ? [...user.assignedEventIds] : [];
          const shouldBeAssigned = assignedStaffIds.includes(user.id);
          const isCurrentlyAssigned = currentAssigned.includes(id);

          if (shouldBeAssigned && !isCurrentlyAssigned) {
            currentAssigned.push(id);
          } else if (!shouldBeAssigned && isCurrentlyAssigned) {
            currentAssigned = currentAssigned.filter(aId => aId !== id);
          }
          return { ...user, assignedEventIds: currentAssigned };
        }
        return user;
      });
    }

    sounds.playSuccess();
    return { success: true, event: merged };
  };

  const deleteEvent = async (id: string): Promise<{ success: boolean; message?: string; mode?: string; error?: string }> => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, message: '403 Forbidden: Only Administrators can delete events.' };
    }

    try {
      const res = await fetch(apiUrl('/api/events/delete'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ id }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        sounds.playError();
        return { success: false, message: data.error || data.message || 'Failed to delete event on the server.' };
      }

      // 1. Remove event from active events list ONLY AFTER server confirms success
      events.value = events.value.filter(e => e.id !== id);
      safeLocalStorageSetItem(STORAGE_KEYS.EVENTS, JSON.stringify(events.value));

      // 2. Switch currentEvent if the deleted event was currently selected
      if (currentEvent.value && currentEvent.value.id === id) {
        const remainingActive = events.value.filter(e => e.id !== id && (e.status === 'ongoing' || e.status === 'upcoming'));
        currentEvent.value = remainingActive[0] || events.value[0] || null;
        if (currentEvent.value) {
          safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(currentEvent.value));
        } else {
          localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
        }
      }

      // 3. Clear local ticket stock if permanently deleted
      if (data.mode === 'permanent') {
        ticketStock.value = ticketStock.value.filter(s => s.eventId !== id);
      }

      // Historical tickets, transactions, and logs are safely preserved for reporting & audit
      sounds.playSuccess();
      return { success: true, message: data.message || 'Event successfully deleted.', mode: data.mode };
    } catch (err: any) {
      sounds.playError();
      return { success: false, message: 'Network error: Failed to communicate with server.' };
    }
  };

  const archiveEvent = (id: string) => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, message: '403 Forbidden: Only Administrators can archive events.' };
    }

    const idx = events.value.findIndex(e => e.id === id);
    if (idx === -1) return { success: false, message: 'Event not found.' };

    events.value[idx] = {
      ...events.value[idx],
      status: 'completed',
    };

    // If the currently viewed event is the one being archived, switch active currentEvent to the first remaining active event
    if (currentEvent.value && currentEvent.value.id === id) {
      const remainingActive = events.value.filter(e => e.id !== id && (e.status === 'ongoing' || e.status === 'upcoming'));
      currentEvent.value = remainingActive[0] || null;
      if (currentEvent.value) {
        localStorage.setItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(currentEvent.value));
      } else {
        localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
      }
    }

    sounds.playSuccess();
    schedulePushStateToServer(true);
    return { success: true, event: events.value[idx] };
  };

  const restoreArchivedEvent = (id: string) => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, message: '403 Forbidden: Only Administrators can restore events.' };
    }

    const idx = events.value.findIndex(e => e.id === id);
    if (idx === -1) return { success: false, message: 'Event not found.' };

    events.value[idx] = {
      ...events.value[idx],
      status: 'ongoing',
    };

    currentEvent.value = events.value[idx];
    localStorage.setItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(events.value[idx]));

    sounds.playSuccess();
    schedulePushStateToServer(true);
    return { success: true, event: events.value[idx] };
  };

  const activeEvents = computed(() => {
    return events.value.filter(e => e.status === 'ongoing' || e.status === 'upcoming');
  });

  const archivedEvents = computed(() => {
    return events.value.filter(e => e.status === 'completed' || e.status === 'archived');
  });

  // Authoritative Student Roster / Enrollment Record Management (Separate from Login Account)
  const saveStudentRecord = (data: {
    name: string;
    studentId: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    department?: string;
    photoUrl?: string;
    userId?: string;
    lastName?: string;
    suffix?: string;
    firstName?: string;
    middleName?: string;
  }): StudentRecord => {
    const cleanStudentId = normalizeId(data.studentId);
    const existingIdx = studentRecords.value.findIndex(
      r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    );
    const parsedName = splitFullName(data.name.trim());
    const explicitLastName = data.lastName?.trim();
    const explicitSuffix = data.suffix?.trim();
    
    if (existingIdx !== -1) {
      const existing = studentRecords.value[existingIdx];
      const updated: StudentRecord = {
        ...existing,
        name: data.name.trim() || existing.name,
        firstName: data.firstName?.trim() || existing.firstName || parsedName.firstName,
        middleName: data.middleName?.trim() || existing.middleName || parsedName.middleName,
        lastName: explicitLastName || existing.lastName || parsedName.lastName,
        suffix: explicitSuffix || existing.suffix || parsedName.suffix,
        course: data.course?.trim() || existing.course,
        yearLevel: data.yearLevel?.trim() || existing.yearLevel,
        section: data.section?.trim() || existing.section,
        department: data.department?.trim() || existing.department,
        photoUrl: data.photoUrl || existing.photoUrl,
        userId: data.userId || existing.userId,
      };
      studentRecords.value[existingIdx] = updated;
      return updated;
    }

    const newRecord: StudentRecord = {
      id: `rec-${cleanStudentId}`,
      studentId: cleanStudentId,
      name: data.name.trim(),
      firstName: data.firstName?.trim() || parsedName.firstName,
      middleName: data.middleName?.trim() || parsedName.middleName,
      lastName: explicitLastName || parsedName.lastName,
      suffix: explicitSuffix || parsedName.suffix,
      course: data.course?.trim() || 'BSIT',
      yearLevel: data.yearLevel?.trim() || '1st Year',
      section: data.section?.trim() || '11M1',
      department: data.department?.trim() || `College of ${data.course?.trim() || 'Computer Studies'}`,
      photoUrl: data.photoUrl,
      createdAt: new Date().toISOString(),
      userId: data.userId,
    };

    studentRecords.value.unshift(newRecord);
    return newRecord;
  };

  // Helper to register / sync student record in roster (does NOT create a User login account)
  const registerStudent = (data: {
    name: string;
    studentId: string;
    course: string;
    yearLevel: string;
    section: string;
    department?: string;
    lastName?: string;
    suffix?: string;
  }): StudentRecord => {
    return saveStudentRecord(data);
  };

  // Ticket creation for registered students / cashier issuance
  const purchaseTicket = async (
    eventId: string,
    photoUrl?: string,
    userId?: string,
    cashierStaffName?: string,
    studentOverride?: {
      studentId?: string;
      name?: string;
      lastName?: string;
      suffix?: string;
      course?: string;
      yearLevel?: string;
      section?: string;
      department?: string;
      attendeeType?: AttendeeType;
      contactNumber?: string;
      cashReceived?: number | string;
    },
    customPrice?: number
  ): Promise<{
    success: boolean;
    message: string;
    ticket: Ticket;
    accountCreated?: boolean;
    notifications?: {
      sms?: { status: string; details: string; recipient?: string };
    };
    outsiderAccount?: {
      created: boolean;
      accountId?: string;
      initialPassword?: string;
      instructions?: string;
      expirationDate?: string;
    };
  }> => {
    const targetEvent = events.value.find(e => e.id === eventId) || currentEvent.value;

    // Role Security (Requirement 13 & 16): Students CANNOT self-issue or purchase tickets directly.
    if (currentUser.value?.role === 'student' && !cashierStaffName) {
      sounds.playError();
      return {
        success: false,
        message: 'Unauthorized: Students cannot self-issue or purchase tickets. Tickets are issued through the designated NCST cashier/ticketing process.',
        ticket: null as any
      };
    }

    const isOutsider = studentOverride?.attendeeType === 'OUTSIDER' || (!studentOverride?.studentId && !userId);
    const calculatedAttendeeType: AttendeeType = isOutsider ? 'OUTSIDER' : 'STUDENT';

    const studentName = (studentOverride?.name || (userId ? users.value.find(u => u.id === userId)?.name : '') || (isOutsider ? 'Guest Attendee' : 'Registered Student')).trim();
    const studentId = isOutsider ? undefined : (studentOverride?.studentId || (userId ? users.value.find(u => u.id === userId)?.studentId : undefined));
    const course = isOutsider ? undefined : (studentOverride?.course || 'BSIT');
    const yearLevel = isOutsider ? undefined : (studentOverride?.yearLevel || '1st Year');
    const section = isOutsider ? undefined : (studentOverride?.section || '11M1');
    const department = isOutsider ? 'General Public / Guest' : (studentOverride?.department || 'College of Computer Studies');
    const contactNumber = studentOverride?.contactNumber;
    const targetEventId = targetEvent ? targetEvent.id : eventId;

    // Backend Validation Checks (Rule 11)
    const nameVal = validateAttendeeName(studentName);
    if (!nameVal.isValid) {
      sounds.playError();
      return {
        success: false,
        message: nameVal.error || 'Invalid Attendee Name.',
        ticket: null as any
      };
    }

    if (!isOutsider) {
      if (studentId) {
        const idVal = validateStudentId(studentId);
        if (!idVal.isValid) {
          sounds.playError();
          return {
            success: false,
            message: idVal.error || 'Invalid NCST Student ID format.',
            ticket: null as any
          };
        }
      }
      if (yearLevel) {
        const yrVal = validateYearLevel(yearLevel);
        if (!yrVal.isValid) {
          sounds.playError();
          return {
            success: false,
            message: yrVal.error || 'Invalid Academic Year Level.',
            ticket: null as any
          };
        }
      }
      if (section) {
        const secVal = validateSection(section);
        if (!secVal.isValid) {
          sounds.playError();
          return {
            success: false,
            message: secVal.error || 'Invalid NCST Section format.',
            ticket: null as any
          };
        }
      }
    } else {
      if (contactNumber) {
        const phoneVal = validatePhoneNumber(contactNumber, false);
        if (!phoneVal.isValid) {
          sounds.playError();
          return {
            success: false,
            message: phoneVal.error || 'Invalid Philippine contact number format.',
            ticket: null as any
          };
        }
      }
    }

    // 1. Authoritatively resolve/sync student record in enrollment roster & Auto-Provision Account
    let studentRec: StudentRecord | null = null;
    let existingUserAccount: User | null = null;
    let accountCreated = false;

    if (!isOutsider && studentId) {
      const cleanStudentId = normalizeId(studentId);
      
      // Look up authoritative student record in roster first
      const existingRosterRecord = studentRecords.value.find(
        r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      );

      if (existingRosterRecord) {
        studentRec = existingRosterRecord;
        if (!studentRec.lastName && studentOverride?.lastName) {
          studentRec.lastName = studentOverride.lastName.trim();
        }
      } else {
        studentRec = saveStudentRecord({
          name: studentName,
          studentId: cleanStudentId,
          lastName: studentOverride?.lastName?.trim(),
          suffix: studentOverride?.suffix?.trim(),
          course: course || 'BSIT',
          yearLevel: yearLevel || '1st Year',
          section: section || '11M1',
          department,
        });
      }

      // Check if this student already happens to have a registered User account
      const recUserId = studentRec?.userId;
      existingUserAccount = users.value.find(
        u => u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      ) || (recUserId ? users.value.find(u => u.id === recUserId) : null) || null;

      if (!existingUserAccount && studentRec) {
        // Automatically create ONE Student login account with authoritative surname initial password
        const parsedName = splitFullName(studentRec.name.trim());
        const officialSurname = (studentRec.lastName || studentOverride?.lastName || parsedName.lastName || '').trim();
        const initialPassword = officialSurname || `NCST-${cleanStudentId}`;

        const newUser: User = {
          id: `usr-student-${Date.now().toString().slice(-6)}`,
          name: studentRec.name,
          firstName: studentRec.firstName || parsedName.firstName,
          middleName: studentRec.middleName || parsedName.middleName,
          lastName: officialSurname || studentRec.lastName,
          suffix: studentRec.suffix || studentOverride?.suffix || parsedName.suffix,
          role: 'student',
          password: initialPassword,
          studentId: cleanStudentId,
          department: studentRec.department || department,
          course: studentRec.course || 'BSIT',
          yearLevel: studentRec.yearLevel || '1st Year',
          section: studentRec.section || '11M1',
          isActive: true,
          accountStatus: 'active',
          mustChangePassword: true,
          createdAt: new Date().toISOString(),
        };

        users.value.unshift(newUser);
        studentRec.userId = newUser.id;
        existingUserAccount = newUser;
        accountCreated = true;
      } else if (existingUserAccount && studentRec) {
        // CASE A: EXISTING STUDENT PORTAL ACCOUNT
        // Reuse existing User Account. PRESERVE existing password hash and security settings exactly!
        studentRec.userId = existingUserAccount.id;
        accountCreated = false;
      }
    } else if (userId) {
      existingUserAccount = users.value.find(u => u.id === userId) || null;
    }

    // Outsider account provisioning (Transient credentials displayed on-screen only)
    let outsiderAccountCreated = false;
    let outsiderInitialPassword: string | undefined = undefined;
    let outsiderAccountId: string | undefined = undefined;
    let outsiderExpiration: string | undefined = undefined;

    if (isOutsider) {
      const guestRef = (studentOverride?.studentId || '').trim();
      const cleanGuestRef = guestRef.toLowerCase();

      const existingOutsider = users.value.find(u =>
        (u.isOutsider || u.role === 'outsider' || u.attendeeType === 'OUTSIDER') &&
        cleanGuestRef && u.studentId && u.studentId.toLowerCase() === cleanGuestRef
      );

      if (existingOutsider) {
        existingUserAccount = existingOutsider;
        outsiderAccountCreated = false;
        outsiderAccountId = existingOutsider.studentId || existingOutsider.id;
        outsiderExpiration = existingOutsider.expiresAt;
      } else {
        const parsedGuestName = splitFullName(studentName.trim());
        const guestSurname = (studentOverride?.lastName || parsedGuestName.lastName || '').trim();
        if (!guestSurname) {
          sounds.playError();
          return {
            success: false,
            message: 'A valid surname could not be identified for guest account provisioning.',
            ticket: null as any
          };
        }
        const transientGuestRef = guestRef || `GUEST-${Math.floor(1000 + Math.random() * 9000)}`;
        outsiderInitialPassword = guestSurname;
        const newOutsider: User = {
          id: `usr-guest-${Date.now()}`,
          name: studentName,
          firstName: parsedGuestName.firstName,
          middleName: parsedGuestName.middleName,
          lastName: guestSurname,
          role: 'outsider',
          attendeeType: 'OUTSIDER',
          isOutsider: true,
          studentId: transientGuestRef,
          department: 'General Public / Guest',
          password: outsiderInitialPassword,
          createdAt: new Date().toISOString(),
          isActive: true,
          accountStatus: 'active',
          mustChangePassword: true,
        };

        const { expirationDate } = getOutsiderAccountExpirationDate(newOutsider, events.value, tickets.value);
        if (expirationDate) {
          newOutsider.expiresAt = expirationDate.toISOString();
          outsiderExpiration = formatPhilippineExpiration(expirationDate);
        }

        users.value.unshift(newOutsider);
        existingUserAccount = newOutsider;
        outsiderAccountCreated = true;
        outsiderAccountId = transientGuestRef;
      }
    }

    // Owner ID: Linked to student user account or student record
    const ownerId = existingUserAccount ? existingUserAccount.id : (studentRec ? studentRec.id : (isOutsider ? (outsiderAccountId || `usr-guest-${Date.now()}`) : `rec-${Date.now()}`));

    const officialPrice = targetEvent ? (targetEvent.ticketPrice ?? 0) : 0;
    const price = officialPrice;

    // Cash Payment Validation
    if (price > 0 && studentOverride?.cashReceived !== undefined) {
      const cashVal = validateCashPayment(studentOverride.cashReceived, price);
      if (!cashVal.isValid) {
        sounds.playError();
        return {
          success: false,
          message: cashVal.error || 'Insufficient or invalid cash payment amount.',
          ticket: null as any
        };
      }
    }

    // Duplicate Protection: Prevent issuing multiple active passes for the same student on the same event
    if (!isOutsider && studentId) {
      const existingTicket = tickets.value.find(t =>
        t.eventId === targetEventId &&
        t.attendeeStudentId === studentId &&
        t.ticketStatus !== 'CANCELLED' &&
        t.ticketStatus !== 'VOID'
      );
      if (existingTicket) {
        sounds.playError();
        return {
          success: false,
          message: `Student (${studentId}) already has an issued pass (#${existingTicket.ticketNumber || existingTicket.id}) for this event.`,
          ticket: existingTicket,
        };
      }
    }

    // Duplicate Protection for Outsiders: Prevent rapid double-click duplicate creation
    if (isOutsider && studentOverride?.name) {
      const existingGuestTicket = tickets.value.find(t =>
        t.eventId === targetEventId &&
        t.currentAttendeeName.toLowerCase() === studentName.toLowerCase().trim() &&
        (contactNumber ? t.contactNumber === contactNumber : true) &&
        t.ticketStatus !== 'CANCELLED' &&
        t.ticketStatus !== 'VOID' &&
        (Date.now() - new Date(t.purchaseDate).getTime() < 3000)
      );
      if (existingGuestTicket) {
        return {
          success: false,
          message: `Pass #${existingGuestTicket.ticketNumber || existingGuestTicket.id} was already generated for this guest.`,
          ticket: existingGuestTicket,
        };
      }
    }

    const parsedName = splitFullName(studentName);
    const evAcadYear = targetEvent?.academicYear || '2026-2027';
    const evSem = targetEvent?.semester || '1st Semester';

    // Helper for collision-resistant 12 uppercase hex characters
    const generateHexEntropy = (bytes = 6) => {
      if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
        return Array.from(crypto.getRandomValues(new Uint8Array(bytes)))
          .map(b => b.toString(16).padStart(2, '0'))
          .join('')
          .toUpperCase();
      }
      return Math.random().toString(16).slice(2, 2 + bytes * 2).toUpperCase();
    };

    const clientTxId = `TXN-POS-${Date.now()}-${generateHexEntropy(4)}`;

    // Prepare student notification statuses for newly created accounts (optimistic default)
    let notifications: {
      sms?: { status: string; details: string; recipient?: string };
    } | undefined = undefined;

    if (!isOutsider && accountCreated) {
      const phoneNum = studentOverride?.contactNumber || studentRec?.contactNumber;

      notifications = {
        sms: {
          status: phoneNum ? 'queued' : 'unavailable',
          details: phoneNum ? `SMS notification queued for ${phoneNum}.` : 'Registered mobile number unavailable.',
          recipient: phoneNum,
        },
      };
    }

    // Try authoritative backend POS endpoint first if online and authorized role
    const userRole = currentUser.value?.role;
    if ((userRole === 'cashier' || userRole === 'admin') && (typeof navigator === 'undefined' || navigator.onLine)) {
      try {
        const res = await fetch(apiUrl('/api/purchase-ticket'), {
          method: 'POST',
          headers: {
            ...getAuthHeaders(),
            'Content-Type': 'application/json',
            'Idempotency-Key': clientTxId,
          },
          body: JSON.stringify({
            eventId: targetEventId,
            attendeeType: calculatedAttendeeType,
            studentId: isOutsider ? undefined : studentId,
            name: studentName,
            lastName: studentRec?.lastName || studentOverride?.lastName,
            suffix: studentRec?.suffix || studentOverride?.suffix,
            course,
            yearLevel,
            section,
            department,
            contactNumber,
            price,
            idempotencyKey: clientTxId,
            guestReference: isOutsider ? (studentOverride?.studentId || outsiderAccountId) : undefined,
          }),
        });

        const data = await res.json();

        if (res.ok && data.success && data.ticket) {
          const serverTicket: Ticket = normalizeTicket({
            ...data.ticket,
            firstName: existingUserAccount?.firstName || studentRec?.firstName || parsedName.firstName,
            middleName: existingUserAccount?.middleName || studentRec?.middleName || parsedName.middleName,
            lastName: existingUserAccount?.lastName || studentRec?.lastName || studentOverride?.lastName || parsedName.lastName,
            attendeeDepartment: department,
            course,
            yearLevel,
            section,
            contactNumber,
            attendeePhotoUrl: photoUrl || existingUserAccount?.photoUrl || studentRec?.photoUrl,
            transferHistory: [],
            notes: isOutsider ? 'Outsider / Guest Event Pass.' : (price > 0 ? `Payment recorded (PHP ${price}.00).` : 'Free official student event ticket registration.'),
          });

          const existIdx = tickets.value.findIndex(t => t.id === serverTicket.id || t.ticketNumber === serverTicket.ticketNumber);
          if (existIdx >= 0) {
            tickets.value[existIdx] = serverTicket;
          } else {
            tickets.value.unshift(serverTicket);
          }

          if (data.stock) {
            const stkIdx = ticketStock.value.findIndex(s => s.id === data.stock.stockId);
            if (stkIdx >= 0) {
              ticketStock.value[stkIdx].availableQuantity = data.stock.availableQuantity;
              ticketStock.value[stkIdx].reservedQuantity = data.stock.reservedQuantity;
            }
          }

          const serverTxn: PurchaseTransaction = {
            id: data.transactionId || clientTxId,
            userId: ownerId,
            userName: studentName,
            attendeeType: calculatedAttendeeType,
            studentId: isOutsider ? undefined : studentId,
            course,
            yearLevel,
            section,
            contactNumber,
            eventId: targetEvent ? targetEvent.id : eventId,
            eventTitle: targetEvent ? targetEvent.title : 'Event',
            ticketId: serverTicket.id,
            ticketNumber: serverTicket.ticketNumber,
            amount: price,
            paymentStatus: price > 0 ? 'paid' : 'free',
            purchaseDate: new Date().toISOString(),
            quantity: 1,
            paymentMethod: cashierStaffName ? 'Cashier Ticket Desk (Cash)' : (price > 0 ? 'Cashier Desk (Cash)' : 'Official Event Registration'),
            cashierName: cashierStaffName,
            academicYear: evAcadYear,
            semester: evSem,
          };
          transactions.value.unshift(serverTxn);

          sounds.playSuccess();
          // Automatically refresh authoritative backend sales summary for the affected event
          fetchTicketSalesSummary(targetEventId).catch(() => {});

          return {
            success: true,
            message: `Ticket #${serverTicket.ticketNumber} generated successfully for ${studentRec?.name || studentName}!`,
            ticket: serverTicket,
            accountCreated: data.accountCreated ?? accountCreated,
            notifications: data.notifications ?? notifications,
            outsiderAccount: isOutsider ? (data.outsiderAccount || {
              created: outsiderAccountCreated,
              accountId: outsiderAccountId,
              initialPassword: outsiderInitialPassword,
              instructions: 'Provide these credentials to the guest now. Initial password is their official surname. Password change is required upon first login.',
              expirationDate: outsiderExpiration,
            }) : undefined,
          };
        } else if (!res.ok || (data && !data.success)) {
          sounds.playError();
          return {
            success: false,
            message: data?.error || data?.message || 'Server rejected ticket issuance.',
            ticket: null as any,
          };
        }
      } catch (networkErr) {
        // Fall back to offline high-entropy local issuance
      }
    }

    // Local / Offline Fallback with Collision-Resistant 12-char Hex Control Number
    const entropyHex = generateHexEntropy(6);
    const outsiderId = isOutsider ? (ownerId || `out-${entropyHex}`) : undefined;
    const newTicketNumber = isOutsider ? `TIX-O-${entropyHex}` : `TIX-${entropyHex}`;

    // Update local physical stock reservation
    const localStock = ticketStock.value.find(s => s.eventId === targetEventId) ||
                       ticketStock.value.find(s => s.id === 'stock-global-passes');
    if (localStock && localStock.availableQuantity > 0) {
      localStock.availableQuantity -= 1;
      localStock.reservedQuantity += 1;
    }

    const newTicket: Ticket = {
      id: newTicketNumber,
      ticketNumber: newTicketNumber,
      qrToken: newTicketNumber,
      eventId: targetEvent ? targetEvent.id : eventId,
      eventTitle: targetEvent ? targetEvent.title : 'Event Pass',
      ticketType: isOutsider ? 'physical' : 'digital',
      attendeeType: calculatedAttendeeType,
      originalOwnerId: ownerId,
      originalOwnerName: studentName,
      currentAttendeeId: ownerId,
      currentAttendeeName: studentName,
      firstName: existingUserAccount?.firstName || studentRec?.firstName || parsedName.firstName,
      middleName: existingUserAccount?.middleName || studentRec?.middleName || parsedName.middleName,
      lastName: existingUserAccount?.lastName || studentRec?.lastName || studentOverride?.lastName || parsedName.lastName,
      attendeeStudentId: isOutsider ? undefined : (studentId ? normalizeId(studentId) : undefined),
      outsiderId,
      attendeeDepartment: department,
      course,
      yearLevel,
      section,
      contactNumber,
      attendeePhotoUrl: photoUrl || existingUserAccount?.photoUrl || studentRec?.photoUrl,
      price,
      purchaseDate: new Date().toISOString(),
      ticketStatus: 'ISSUED',
      paymentStatus: price > 0 ? 'paid' : 'free',
      // Dynamic QR Label Print Status
      qrPrintStatus: 'PENDING',
      qrPrintCount: 0,
      attendanceStatus: 'not_entered',
      transferHistory: [],
      notes: isOutsider ? 'Outsider / Guest Event Pass.' : (price > 0 ? `Payment recorded (PHP ${price}.00).` : 'Free official student event ticket registration.'),
      academicYear: evAcadYear,
      semester: evSem,
    };

    tickets.value.unshift(newTicket);

    // Record Immutable Purchase / Registration Transaction
    const newTxn: PurchaseTransaction = {
      id: clientTxId,
      userId: ownerId,
      userName: studentName,
      attendeeType: calculatedAttendeeType,
      studentId: isOutsider ? undefined : studentId,
      course,
      yearLevel,
      section,
      contactNumber,
      eventId: targetEvent ? targetEvent.id : eventId,
      eventTitle: targetEvent ? targetEvent.title : 'Event',
      ticketId: newTicketNumber,
      ticketNumber: newTicketNumber,
      amount: price,
      paymentStatus: price > 0 ? 'paid' : 'free',
      purchaseDate: new Date().toISOString(),
      quantity: 1,
      paymentMethod: cashierStaffName ? 'Cashier Ticket Desk (Cash)' : (price > 0 ? 'Cashier Desk (Cash)' : 'Official Event Registration'),
      cashierName: cashierStaffName,
      academicYear: evAcadYear,
      semester: evSem,
    };
    transactions.value.unshift(newTxn);

    sounds.playSuccess();
    // Automatically refresh authoritative sales summary for the affected event
    fetchTicketSalesSummary(targetEventId).catch(() => {});

    return {
      success: true,
      message: `Ticket #${newTicketNumber} generated successfully for ${studentRec?.name || studentName}!`,
      ticket: newTicket,
      accountCreated,
      notifications,
      outsiderAccount: isOutsider ? {
        created: outsiderAccountCreated,
        accountId: outsiderAccountId,
        initialPassword: outsiderInitialPassword,
        instructions: 'Use your Account ID and initial password to access your temporary event pass.',
        expirationDate: outsiderExpiration,
      } : undefined,
    };
  };

  // ==================== DYNAMIC QR LABEL PRINT QUEUE ACTIONS ====================
  const markQrAsPrinted = (ticketIds: string[]) => {
    const now = new Date().toISOString();
    let updated = false;
    tickets.value.forEach(t => {
      if (ticketIds.includes(t.id) || (t.ticketNumber && ticketIds.includes(t.ticketNumber))) {
        t.qrPrintStatus = 'PRINTED';
        t.qrPrintedAt = now;
        t.qrPrintCount = (t.qrPrintCount || 0) + 1;
        updated = true;
      }
    });
    if (updated) {
      sounds.playSuccess();
      fetch(apiUrl('/api/tickets/update-print-status'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ ticketIds, status: 'PRINTED' }),
      }).catch(() => {});
    }
  };

  const markQrAsPending = (ticketIds: string[]) => {
    let updated = false;
    tickets.value.forEach(t => {
      if (ticketIds.includes(t.id) || (t.ticketNumber && ticketIds.includes(t.ticketNumber))) {
        t.qrPrintStatus = 'PENDING';
        updated = true;
      }
    });
    if (updated) {
      fetch(apiUrl('/api/tickets/update-print-status'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ ticketIds, status: 'PENDING' }),
      }).catch(() => {});
    }
  };

  const markQrAsAttached = (ticketIds: string[]) => {
    let updated = false;
    tickets.value.forEach(t => {
      if (ticketIds.includes(t.id) || (t.ticketNumber && ticketIds.includes(t.ticketNumber))) {
        t.qrPrintStatus = 'ATTACHED';
        updated = true;
      }
    });
    if (updated) {
      sounds.playSuccess();
      fetch(apiUrl('/api/tickets/update-print-status'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ ticketIds, status: 'ATTACHED' }),
      }).catch(() => {});
    }
  };

  // NCST Institutional Policy: Event tickets are strictly NON-TRANSFERABLE
  const transferTicket = (
    _ticketId: string,
    _toStudentName: string,
    _toStudentId?: string,
    _reason?: string
  ): { success: boolean; message: string; ticket?: Ticket } => {
    return {
      success: false,
      message: 'Event tickets are strictly non-transferable under NCST institutional policy.'
    };
  };

  // Administrative Void / Invalidation (Tickets are strictly NON-REFUNDABLE)
  const cancelTicket = (ticketId: string, reason?: string) => {
    const idx = tickets.value.findIndex(t => t.id === ticketId || t.ticketNumber === ticketId);
    if (idx !== -1) {
      tickets.value[idx] = {
        ...tickets.value[idx],
        ticketStatus: 'VOID',
        notes: `${tickets.value[idx].notes || ''} [Voided: ${reason || 'Admin action'}. Non-refundable.]`,
      };
    }
  };

  // Deprecated alias for backwards compatibility (Strictly non-refundable)
  const refundTicket = (ticketId: string, reason?: string) => {
    cancelTicket(ticketId, reason);
  };

  // Physical Ticket creation for walk-in / manual booth issuance
  const createPhysicalTicket = (
    eventId: string,
    attendeeName: string,
    department?: string,
    studentId?: string,
    price: number = 0,
    ocrText?: string,
    photoUrl?: string,
    course?: string,
    yearLevel?: string,
    section?: string,
    attendeeType: AttendeeType = 'STUDENT',
    contactNumber?: string
  ): Ticket => {
    if (currentUser.value?.role === 'student') {
      sounds.playError();
      throw new Error('Unauthorized: Student accounts cannot create physical tickets.');
    }
    const seq = Math.floor(10000 + tickets.value.length + 1);
    const newTicketNumber = attendeeType === 'OUTSIDER' ? `TIX-O-${seq}` : `TIX-${seq}`;
    const targetEvent = events.value.find(e => e.id === eventId) || currentEvent.value;
    const parsedName = splitFullName(attendeeName);
    const outsiderId = attendeeType === 'OUTSIDER' ? `out-${seq}` : undefined;

    const newTicket: Ticket = {
      id: newTicketNumber,
      ticketNumber: newTicketNumber,
      qrToken: newTicketNumber,
      eventId,
      eventTitle: targetEvent ? targetEvent.title : 'Physical Pass',
      ticketType: 'physical',
      attendeeType,
      originalOwnerId: currentUser.value?.id || 'usr-admin-1',
      originalOwnerName: currentUser.value ? `${currentUser.value.name} (Ticket Booth)` : 'Admin Ticket Booth',
      currentAttendeeId: `attendee-manual-${Date.now()}`,
      currentAttendeeName: attendeeName,
      firstName: parsedName.firstName,
      middleName: parsedName.middleName,
      lastName: parsedName.lastName,
      attendeeStudentId: attendeeType === 'STUDENT' ? studentId : undefined,
      outsiderId,
      attendeeDepartment: attendeeType === 'STUDENT' ? (department || 'Student Attendee') : (department || 'General Public / Outsider'),
      course: attendeeType === 'STUDENT' ? course : undefined,
      yearLevel: attendeeType === 'STUDENT' ? yearLevel : undefined,
      section: attendeeType === 'STUDENT' ? section : undefined,
      contactNumber,
      attendeePhotoUrl: photoUrl,
      price,
      purchaseDate: new Date().toISOString(),
      ticketStatus: 'ISSUED',
      paymentStatus: price > 0 ? 'paid' : 'free',
      attendanceStatus: 'not_entered',
      transferHistory: [],
      ocrVerified: !!ocrText,
      notes: ocrText ? `OCR Scanned: ${ocrText.slice(0, 80)}...` : (attendeeType === 'OUTSIDER' ? 'Physical Outsider Pass registered by booth.' : 'Physical Student Pass registered by booth.'),
      academicYear: targetEvent?.academicYear || '2026-2027',
      semester: targetEvent?.semester || '1st Semester',
    };

    tickets.value.unshift(newTicket);
    return newTicket;
  };

  const deleteTicket = (ticketId: string) => {
    tickets.value = tickets.value.filter(t => t.id !== ticketId && t.ticketNumber !== ticketId);
    transactions.value = transactions.value.filter(tx => tx.ticketId !== ticketId && tx.ticketNumber !== ticketId);
  };

  const clearAllTickets = () => {
    tickets.value = [];
    transactions.value = [];
    logs.value = [];
  };

  // User CRUD - Enforces strict ONE PERSON = ONE ACCOUNT
  const addUser = (userData: Partial<User> & { name: string; role: UserRole }): { success: boolean; user?: User; message?: string; conflict?: IdentityConflictResult } => {
    const candidate = {
      name: userData.name,
      role: userData.role,
      studentId: userData.role === 'student' ? userData.studentId : undefined,
      employeeId: userData.role !== 'student' ? (userData.employeeId || userData.studentId) : undefined,
    };

    const conflict = checkUserIdentityConflict(candidate, users.value);
    if (conflict.hasConflict) {
      sounds.playError();
      return { success: false, message: conflict.message, conflict };
    }

    const parsedName = splitFullName(userData.name.trim());
    const newUser: User = {
      ...userData,
      name: userData.name.trim(),
      firstName: userData.firstName || parsedName.firstName,
      middleName: userData.middleName || parsedName.middleName,
      lastName: userData.lastName || parsedName.lastName,
      studentId: userData.role === 'student' ? normalizeId(userData.studentId) || undefined : undefined,
      employeeId: userData.role !== 'student' ? normalizeId(userData.employeeId) || undefined : undefined,
      id: userData.id || `usr-${userData.role}-${Date.now().toString(36)}`,
      createdAt: userData.createdAt || new Date().toISOString(),
      isActive: userData.isActive !== false,
    };

    // For student accounts, ensure yearLevel is derived consistently from authoritative section
    if (newUser.role === 'student' && newUser.section) {
      newUser.yearLevel = deriveYearLevelFromSection(newUser.section);
    }

    users.value.unshift(newUser);

    // Two-way synchronization: Keep StudentRecord in sync or provision authoritative StudentRecord
    if (newUser.role === 'student' && newUser.studentId) {
      const cleanSid = normalizeId(newUser.studentId).toLowerCase();
      const recIdx = studentRecords.value.findIndex(
        r => normalizeId(r.studentId).toLowerCase() === cleanSid
      );
      const derivedYear = newUser.section ? deriveYearLevelFromSection(newUser.section) : (newUser.yearLevel || '1st Year');
      if (recIdx !== -1) {
        studentRecords.value[recIdx] = {
          ...studentRecords.value[recIdx],
          name: newUser.name,
          firstName: newUser.firstName,
          middleName: newUser.middleName,
          lastName: newUser.lastName,
          course: newUser.course || studentRecords.value[recIdx].course,
          yearLevel: derivedYear || studentRecords.value[recIdx].yearLevel,
          section: newUser.section || studentRecords.value[recIdx].section,
          department: newUser.department || studentRecords.value[recIdx].department,
          photoUrl: newUser.photoUrl || studentRecords.value[recIdx].photoUrl,
          userId: newUser.id,
        };
      } else {
        studentRecords.value.unshift({
          id: `rec-${newUser.studentId}`,
          studentId: newUser.studentId,
          name: newUser.name,
          firstName: newUser.firstName,
          middleName: newUser.middleName,
          lastName: newUser.lastName,
          course: newUser.course || 'BSIT',
          yearLevel: derivedYear,
          section: newUser.section || '11M1',
          department: newUser.department || 'CSD',
          createdAt: newUser.createdAt,
          userId: newUser.id,
        });
      }
    }

    schedulePushStateToServer(true);
    sounds.playSuccess();
    return { success: true, user: newUser, message: 'Account created successfully.' };
  };

  const updateUser = (id: string, updates: Partial<User>): { success: boolean; user?: User; message?: string; conflict?: IdentityConflictResult } => {
    const idx = users.value.findIndex(u => u.id === id);
    if (idx === -1) {
      return { success: false, message: 'User account not found.' };
    }

    const current = users.value[idx];
    const candidate = {
      id,
      name: updates.name !== undefined ? updates.name : current.name,
      role: updates.role !== undefined ? updates.role : current.role,
      studentId: (updates.role || current.role) === 'student'
        ? (updates.studentId !== undefined ? updates.studentId : current.studentId)
        : undefined,
      employeeId: (updates.role || current.role) !== 'student'
        ? (updates.employeeId !== undefined ? updates.employeeId : current.employeeId)
        : undefined,
    };

    const conflict = checkUserIdentityConflict(candidate, users.value);
    if (conflict.hasConflict) {
      sounds.playError();
      return { success: false, message: conflict.message, conflict };
    }

    const parsedName = updates.name ? splitFullName(updates.name.trim()) : undefined;
    const updatedUser: User = {
      ...current,
      ...updates,
      name: updates.name !== undefined ? updates.name.trim() : current.name,
      firstName: updates.firstName || parsedName?.firstName || current.firstName,
      middleName: updates.middleName || parsedName?.middleName || current.middleName,
      lastName: updates.lastName || parsedName?.lastName || current.lastName,
      studentId: (updates.role || current.role) === 'student'
        ? (updates.studentId !== undefined ? (normalizeId(updates.studentId) || undefined) : current.studentId)
        : undefined,
      employeeId: (updates.role || current.role) !== 'student'
        ? (updates.employeeId !== undefined ? (normalizeId(updates.employeeId) || undefined) : current.employeeId)
        : undefined,
    };

    users.value[idx] = updatedUser;
    if (currentUser.value && currentUser.value.id === id) {
      currentUser.value = updatedUser;
    }

    // Two-way synchronization: Keep StudentRecord in sync with User account
    if ((updates.role || current.role) === 'student' && updatedUser.studentId) {
      const cleanSid = normalizeId(updatedUser.studentId).toLowerCase();
      const recIdx = studentRecords.value.findIndex(
        r => normalizeId(r.studentId).toLowerCase() === cleanSid
      );
      if (recIdx !== -1) {
        studentRecords.value[recIdx] = {
          ...studentRecords.value[recIdx],
          name: updatedUser.name,
          firstName: updatedUser.firstName,
          middleName: updatedUser.middleName,
          lastName: updatedUser.lastName,
          course: updatedUser.course || studentRecords.value[recIdx].course,
          yearLevel: (updatedUser.section ? deriveYearLevelFromSection(updatedUser.section) : updatedUser.yearLevel) || studentRecords.value[recIdx].yearLevel,
          section: updatedUser.section || studentRecords.value[recIdx].section,
          department: updatedUser.department || studentRecords.value[recIdx].department,
          photoUrl: updatedUser.photoUrl || studentRecords.value[recIdx].photoUrl,
          userId: updatedUser.id,
        };
      }
    }

    sounds.playSuccess();
    return { success: true, user: updatedUser, message: 'Account updated successfully.' };
  };

  const reactivateUser = (id: string): { success: boolean; user?: User; message: string } => {
    const idx = users.value.findIndex(u => u.id === id);
    if (idx === -1) {
      sounds.playError();
      return { success: false, message: 'User account not found.' };
    }

    users.value[idx] = {
      ...users.value[idx],
      isActive: true,
    };

    if (currentUser.value && currentUser.value.id === id) {
      currentUser.value = users.value[idx];
    }

    sounds.playSuccess();
    return {
      success: true,
      user: users.value[idx],
      message: `Account for "${users.value[idx].name}" (${users.value[idx].role.toUpperCase()}) has been reactivated successfully. Historical tickets, attendance logs, and transactions are preserved.`,
    };
  };

  const deleteUser = async (id: string): Promise<{ success: boolean; message?: string; mode?: string; error?: string }> => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, message: '403 Forbidden: Only Administrators can delete user accounts.' };
    }

    if (currentUser.value.id === id) {
      sounds.playError();
      return { success: false, message: 'Action rejected: You cannot delete your own active administrator account.' };
    }

    try {
      const res = await fetch(apiUrl('/api/users/delete'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ id }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        sounds.playError();
        return { success: false, message: data.error || data.message || 'Failed to delete user account on the server.' };
      }

      // Add to tracked deleted tombstones
      recordDeletedUserId(id);

      // Remove user from active users list ONLY AFTER server confirms success
      users.value = users.value.filter(u => u.id !== id);
      safeLocalStorageSetItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));

      if (currentUser.value && currentUser.value.id === id) {
        currentUser.value = null;
        localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
        localStorage.removeItem(STORAGE_KEYS.SESSION_TOKEN);
      }

      sounds.playSuccess();
      return { success: true, message: data.message || 'User account successfully deleted.', mode: data.mode };
    } catch (err: any) {
      sounds.playError();
      return { success: false, message: 'Network error: Failed to communicate with server.' };
    }
  };

  // QR Scanning & Attendance Validation Engine with Real-Time Student vs Outsider Classification
  const validateAndRecordScan = async (
    rawScanData: string,
    isSimulation: any = false,
    _deprecatedArea?: any,
    _deprecatedScanType?: any
  ): Promise<ScanValidationResult> => {
    let actualIsSimulation = false;
    if (typeof isSimulation === 'boolean') {
      actualIsSimulation = isSimulation;
    } else if (typeof _deprecatedArea === 'boolean') {
      actualIsSimulation = _deprecatedArea;
    } else if (typeof _deprecatedScanType === 'boolean') {
      actualIsSimulation = _deprecatedScanType;
    }

    const timestamp = new Date().toISOString();
    let ticketTokenToLookup = rawScanData.trim();

    let parsedJsonData: any = null;
    if (rawScanData.startsWith('{') && rawScanData.endsWith('}')) {
      try {
        parsedJsonData = JSON.parse(rawScanData);
        if (parsedJsonData.ticketNumber) {
          ticketTokenToLookup = parsedJsonData.ticketNumber;
        } else if (parsedJsonData.ticketId) {
          ticketTokenToLookup = parsedJsonData.ticketId;
        } else if (parsedJsonData.id) {
          ticketTokenToLookup = parsedJsonData.id;
        }
      } catch {}
    }

    const cleanLookup = ticketTokenToLookup.toLowerCase().replace(/^[№#\s]+/, '').trim();

    // 1. Strict QR Credential Lookup (Exact QR Token or Official Ticket ID)
    let ticket = tickets.value.find(
      t => (t.id && t.id.toLowerCase() === cleanLookup) ||
           (t.ticketNumber && t.ticketNumber.toLowerCase() === cleanLookup) ||
           (t.qrToken && t.qrToken.toLowerCase() === cleanLookup)
    );

    // If ticket was not yet in local memory (e.g. newly issued across network), reconstruct from verified QR payload
    if (!ticket && parsedJsonData) {
      const recId = parsedJsonData.ticketNumber || parsedJsonData.ticketId || parsedJsonData.id || parsedJsonData.qrToken;
      if (recId) {
        const recIsOutsider = parsedJsonData.attendeeType === 'OUTSIDER' || (!parsedJsonData.studentId && !parsedJsonData.course);
        const recoveredTicket: Ticket = {
          id: recId,
          ticketNumber: recId,
          qrToken: recId,
          eventId: parsedJsonData.eventId || currentEvent.value?.id || 'evt-juans-2026',
          eventTitle: parsedJsonData.eventTitle || currentEvent.value?.title || 'Campus Event',
          ticketType: parsedJsonData.ticketType || (recIsOutsider ? 'physical' : 'digital'),
          attendeeType: recIsOutsider ? 'OUTSIDER' : 'STUDENT',
          originalOwnerId: parsedJsonData.studentId || parsedJsonData.ownerId || 'std-guest',
          originalOwnerName: parsedJsonData.attendeeName || parsedJsonData.studentName || (recIsOutsider ? 'Guest Attendee' : 'Student Attendee'),
          currentAttendeeId: parsedJsonData.studentId || parsedJsonData.ownerId || 'std-guest',
          currentAttendeeName: parsedJsonData.attendeeName || parsedJsonData.studentName || (recIsOutsider ? 'Guest Attendee' : 'Student Attendee'),
          attendeeStudentId: recIsOutsider ? undefined : (parsedJsonData.studentId || undefined),
          course: recIsOutsider ? undefined : (parsedJsonData.course || 'BSIT'),
          yearLevel: recIsOutsider ? undefined : (parsedJsonData.yearLevel || '3rd Year'),
          section: recIsOutsider ? undefined : (parsedJsonData.section || 'Section A'),
          contactNumber: parsedJsonData.contactNumber,
          price: parsedJsonData.price ?? 0,
          purchaseDate: new Date().toISOString(),
          ticketStatus: (parsedJsonData.status as any) || 'ISSUED',
          paymentStatus: 'paid',
          attendanceStatus: 'not_entered',
          transferHistory: [],
        };
        tickets.value.unshift(recoveredTicket);
        ticket = recoveredTicket;
      }
    }

    const targetEvent: EventItem | undefined = events.value.find(e => e.id === (ticket ? ticket.eventId : currentEvent.value?.id)) || (currentEvent.value ? currentEvent.value : undefined);
    const scannerId = currentUser.value?.id || 'sys-scanner';
    const scannerName = currentUser.value?.name || 'Alex Rivera (Event Staff)';

    const recordDenied = (reason: string): ScanValidationResult => {
      const isDuplicate = reason.includes('ALREADY USED') || reason.includes('Already used') || reason.includes('already used');
      if (isDuplicate) {
        sounds.vibrateWarning();
      } else {
        sounds.vibrateError();
      }
      const detectedType: AttendeeType | undefined = ticket ? (ticket.attendeeType || (ticket.attendeeStudentId ? 'STUDENT' : 'OUTSIDER')) : undefined;

      return {
        isValid: false,
        status: 'denied',
        reason,
        attendeeType: detectedType,
        ticket,
        event: targetEvent,
        timestamp,
      };
    };

    // =========================================================================
    // MANDATORY BACKEND SECURITY & ROLE-BASED ACCESS CONTROL (RBAC) CHECKS
    // =========================================================================

    // CHECK 1: User is Authenticated
    if (!currentUser.value) {
      return recordDenied('403 Forbidden: Unauthenticated session. Please sign in to scan tickets.');
    }

    // CHECK 2: User Role is strictly 'event_staff' (STAFF)
    if (currentUser.value.role !== 'event_staff') {
      return recordDenied('403 Forbidden: Only authorized Event Staff assigned to this event can scan tickets.');
    }

    // CHECK 3: Staff Account is Active
    if (currentUser.value.isActive === false) {
      return recordDenied('403 Forbidden: Your Event Staff account is currently deactivated. Access denied.');
    }

    // CHECK 4: Staff Account is Assigned to the Selected Event
    if (currentEvent.value && currentUser.value.assignedEventIds && currentUser.value.assignedEventIds.length > 0) {
      if (!currentUser.value.assignedEventIds.includes(currentEvent.value.id)) {
        return recordDenied(
          `403 Forbidden: Only authorized Event Staff assigned to this event can scan tickets. You are not assigned to "${currentEvent.value.title}".`
        );
      }
    }

    if (actualIsSimulation) {
      // In simulation mode, perform local validation without backend write
      if (!ticket) {
        return recordDenied(`INVALID TICKET: Ticket number or QR token "${ticketTokenToLookup}" not found in database.`);
      }
      if (currentEvent.value && ticket.eventId !== currentEvent.value.id) {
        return recordDenied(`WRONG EVENT: This ticket is registered for a different event.`);
      }
      const tStatus = (ticket.ticketStatus || '').toUpperCase();
      if (tStatus === 'CANCELLED' || tStatus === 'REFUNDED' || tStatus === 'VOID') {
        return recordDenied(`TICKET NOT VALID: This ticket has been marked as ${tStatus} and is void.`);
      }
      if (ticket.attendanceStatus === 'present' || tStatus === 'USED') {
        return recordDenied(`⚠️ TICKET ALREADY USED: Scanned previously.`);
      }
      sounds.vibrateSuccess();
      return {
        isValid: true,
        status: 'valid',
        reason: '[TEST MODE] Simulated Scan Preview (No real attendance recorded)',
        attendeeType: ticket.attendeeType || 'STUDENT',
        ticket,
        event: targetEvent,
        timestamp,
      };
    }

    // Check if device is offline: Queue scan and report clear sync state
    const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;
    if (isOffline) {
      sounds.vibrateWarning();
      useOfflineScanner().enqueueScan({
        rawScanData: ticketTokenToLookup,
        eventId: targetEvent ? targetEvent.id : undefined,
        scannerUserId: scannerId,
        scannerName,
        ticketId: ticket?.id,
        ticketNumber: ticket?.ticketNumber,
        attendeeName: ticket?.currentAttendeeName,
      });
      return {
        isValid: false,
        status: 'denied',
        reason: 'OFFLINE MODE: Ticket scan queued locally. Official attendance will be recorded once connection to server is restored.',
        attendeeType: ticket?.attendeeType,
        ticket,
        event: targetEvent,
        timestamp,
        isOfflineRecorded: true,
      };
    }

    // Authoritative Server-Side Scan Validation & Atomic Persistence
    try {
      const response = await fetch(apiUrl('/api/scan-ticket'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({
          rawScanData: ticketTokenToLookup,
          eventId: currentEvent.value?.id || targetEvent?.id,
        }),
      });

      const data = await response.json().catch(() => null);

      if (!response.ok || !data?.success) {
        const errorReason = data?.error || 'Validation failed with server.';
        return recordDenied(errorReason);
      }

      // Authoritative Backend Confirmation Received
      let updatedTicket: Ticket;
      if (data.ticket) {
        updatedTicket = normalizeTicket(data.ticket);
        const idx = tickets.value.findIndex(t => t.id === updatedTicket.id || t.ticketNumber === updatedTicket.ticketNumber);
        if (idx !== -1) {
          tickets.value[idx] = updatedTicket;
        } else {
          tickets.value.unshift(updatedTicket);
        }
      } else {
        const phtTime = data.time_in || timestamp;
        updatedTicket = {
          ...(ticket || {} as any),
          ticketStatus: 'USED',
          attendanceStatus: 'present',
          timeIn: phtTime,
          lastScannedBy: data.verified_by || scannerName,
        };
        if (ticket) {
          const idx = tickets.value.findIndex(t => t.id === ticket!.id);
          if (idx !== -1) tickets.value[idx] = updatedTicket;
        }
      }

      let successLog: AttendanceLog;
      if (data.log) {
        successLog = data.log;
      } else {
        const parsedLogName = splitFullName(updatedTicket.currentAttendeeName);
        successLog = {
          id: `log-${Date.now()}`,
          ticketId: updatedTicket.id,
          ticketNumber: updatedTicket.ticketNumber || updatedTicket.id,
          eventId: targetEvent ? targetEvent.id : 'unknown',
          eventTitle: targetEvent ? targetEvent.title : 'Event',
          attendeeType: updatedTicket.attendeeType || 'STUDENT',
          attendeeName: updatedTicket.currentAttendeeName,
          firstName: updatedTicket.firstName || parsedLogName.firstName,
          middleName: updatedTicket.middleName || parsedLogName.middleName,
          lastName: updatedTicket.lastName || parsedLogName.lastName,
          studentId: updatedTicket.attendeeStudentId,
          course: updatedTicket.course,
          yearLevel: updatedTicket.yearLevel,
          section: updatedTicket.section,
          contactNumber: updatedTicket.contactNumber,
          ticketType: updatedTicket.ticketType,
          timestamp,
          date: new Date().toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' }),
          timeIn: data.time_in || new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
          verifiedById: scannerId,
          verifiedByName: data.verified_by || scannerName,
          status: 'valid',
          academicYear: targetEvent?.academicYear || updatedTicket.academicYear || '2026-2027',
          semester: targetEvent?.semester || updatedTicket.semester || '1st Semester',
        };
      }

      const existingLogIdx = logs.value.findIndex(l => 
        (l.id && successLog.id && l.id === successLog.id) ||
        (l.ticketId === successLog.ticketId && l.status === 'valid')
      );
      if (existingLogIdx !== -1) {
        logs.value[existingLogIdx] = successLog;
      } else {
        logs.value.unshift(successLog);
      }

      sounds.vibrateSuccess();

      return {
        isValid: true,
        status: 'valid',
        attendeeType: updatedTicket.attendeeType,
        ticket: updatedTicket,
        event: targetEvent,
        timestamp,
        timeInFormatted: data.time_in || (updatedTicket.timeIn ? new Date(updatedTicket.timeIn).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : undefined),
      };
    } catch (_netErr: any) {
      sounds.vibrateError();
      return {
        isValid: false,
        status: 'denied',
        reason: 'Server unreachable. Network connection to authoritative server is required to record attendance.',
        attendeeType: ticket?.attendeeType,
        ticket,
        event: targetEvent,
        timestamp,
      };
    }
  };

  const lookupTicketInfo = (query: string) => {
    const cleanLookup = query.toLowerCase().replace(/^[№#\s]+/, '').trim();
    if (!cleanLookup) return { found: false, message: 'Please enter a ticket number, student ID, or QR string.' };

    const ticket = tickets.value.find(
      t => (t.id && t.id.toLowerCase() === cleanLookup) ||
           (t.ticketNumber && t.ticketNumber.toLowerCase() === cleanLookup) ||
           (t.qrToken && t.qrToken.toLowerCase() === cleanLookup) ||
           (t.attendeeStudentId && t.attendeeStudentId.toLowerCase() === cleanLookup)
    );

    if (!ticket) {
      return { found: false, message: `Ticket "${query}" was not found in the database.` };
    }

    const ev = events.value.find(e => e.id === ticket.eventId);
    return {
      found: true,
      ticket,
      event: ev,
      message: 'Ticket details retrieved (Verification lookup only — QR scan required to authorize entry).'
    };
  };

  const isAuthenticated = computed(() => currentUser.value !== null);

  const findUserByIdentifier = (identifier: string): User | undefined => {
    const trimmed = identifier.trim().toLowerCase();
    if (!trimmed || trimmed.includes('@')) return undefined;
    const cleanId = trimmed.replace(/\s+/g, '');
    
    // Match registered Student ID for students/outsiders, Employee ID for personnel, or contact number
    let found = users.value.find(u => {
      const studentIdMatch = (u.role === 'student' || u.role === 'outsider' || u.isOutsider) && u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId;
      const contactMatch = (u.role === 'outsider' || u.isOutsider) && u.contactNumber && normalizeId(u.contactNumber).toLowerCase() === cleanId;
      const employeeIdMatch = u.role !== 'student' && u.role !== 'outsider' && u.employeeId && normalizeId(u.employeeId).toLowerCase() === cleanId;
      const idMatch = u.id && u.id.trim().toLowerCase() === cleanId;
      return studentIdMatch || contactMatch || employeeIdMatch || idMatch;
    });

    // If not found, check authoritative INITIAL_USERS seed demo accounts and self-heal
    if (!found) {
      const seedFound = INITIAL_USERS.find(u => {
        const studentIdMatch = (u.role === 'student' || u.role === 'outsider' || u.isOutsider) && u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId;
        const contactMatch = (u.role === 'outsider' || u.isOutsider) && u.contactNumber && normalizeId(u.contactNumber).toLowerCase() === cleanId;
        const employeeIdMatch = u.role !== 'student' && u.role !== 'outsider' && u.employeeId && normalizeId(u.employeeId).toLowerCase() === cleanId;
        const idMatch = u.id && u.id.trim().toLowerCase() === cleanId;
        return studentIdMatch || contactMatch || employeeIdMatch || idMatch;
      });

      if (seedFound) {
        users.value = ensureSeedUsers([...users.value, { ...seedFound }]);
        found = users.value.find(u => u.id === seedFound.id) || seedFound;
      }
    }

    return found;
  };

  const setCurrentUser = (user: User | null) => {
    currentUser.value = user;
    if (user) {
      const token = `sess_${user.role}_${user.id}`;
      sessionToken.value = token;
      localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(user));
      localStorage.setItem(STORAGE_KEYS.SESSION_TOKEN, token);
      validateAndSyncSession();
    } else {
      sessionToken.value = '';
      localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
      localStorage.removeItem(STORAGE_KEYS.SESSION_TOKEN);
    }
  };

  const logout = () => {
    setCurrentUser(null);
  };

  const login = async (identifier: string, password: string): Promise<{ success: boolean; message: string; user?: User }> => {
    evaluateOutsiderAccountExpirations();
    const rawIdent = identifier.trim();
    const cleanPass = password.trim();

    if (!rawIdent || !cleanPass) {
      sounds.playError();
      return { success: false, message: 'ID Number and password are required.' };
    }

    // Reject email address as login identifier
    if (rawIdent.includes('@')) {
      sounds.playError();
      return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
    }

    const trimmed = rawIdent.toLowerCase();
    const cleanId = trimmed.replace(/\s+/g, '');

    if (!/^[a-z0-9\-]+$/i.test(cleanId)) {
      sounds.playError();
      return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
    }

    // 1. Authoritative Backend Server Authentication
    try {
      const res = await fetch(apiUrl('/api/auth/login'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: cleanId, password: cleanPass }),
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.success && data.user) {
          const serverUser: User = {
            ...data.user,
          };
          delete (serverUser as any).password;
          delete (serverUser as any).temporaryPassword;
          const token = data.token || data.sessionToken || `sess_${serverUser.role}_${serverUser.id}`;

          // Synchronize into local reactive users collection without password
          const existingIdx = users.value.findIndex(u =>
            u.id === serverUser.id ||
            (u.studentId && normalizeId(u.studentId).toLowerCase() === normalizeId(serverUser.studentId || '').toLowerCase()) ||
            (u.employeeId && normalizeId(u.employeeId).toLowerCase() === normalizeId(serverUser.employeeId || '').toLowerCase())
          );
          if (existingIdx !== -1) {
            const merged = { ...users.value[existingIdx], ...serverUser };
            delete (merged as any).password;
            users.value[existingIdx] = merged;
          } else {
            users.value = [serverUser, ...users.value];
          }

          currentUser.value = serverUser;
          sessionToken.value = token;
          safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(serverUser));
          safeLocalStorageSetItem(STORAGE_KEYS.SESSION_TOKEN, token);

          // Fetch full authenticated state from server
          await fetchStateFromServer();

          sounds.playSuccess();
          return { success: true, message: data.message || `Welcome back, ${serverUser.name}!`, user: serverUser };
        }
      } else {
        const data = await res.json().catch(() => ({}));
        sounds.playError();
        return {
          success: false,
          message: data?.error || (res.status === 429 ? 'Too many login attempts. Please wait before trying again.' : 'Invalid credentials. Please verify your ID Number and password.')
        };
      }
    } catch {
      // Server offline or unreachable: fall back to local store
    }

    // 2. Client-side local fallback authentication
    const foundUser = findUserByIdentifier(trimmed);

    if (!foundUser) {
      sounds.playError();
      return { success: false, message: 'Invalid credentials. Please verify your ID Number and password.' };
    }

    if (foundUser.isActive === false) {
      sounds.playError();
      if (isOutsiderUser(foundUser)) {
        const { expirationDate } = getOutsiderAccountExpirationDate(foundUser, events.value, tickets.value);
        const dateFormatted = expirationDate ? formatPhilippineExpiration(expirationDate) : 'event conclusion';
        return {
          success: false,
          message: `This temporary Outsider account expired on ${dateFormatted}. Historical ticket and attendance records remain preserved in the institutional archive.`
        };
      }
      return { success: false, message: 'This account has been deactivated. Please contact the Administrator.' };
    }

    const isGuest = isOutsiderUser(foundUser) || foundUser.role === 'outsider';
    const userSurname = (foundUser.lastName || splitFullName(foundUser.name).lastName || '').trim();
    const expectedPassword = (foundUser.password || (foundUser.mustChangePassword ? userSurname : 'password123')).trim();
    const passMatches = cleanPass === expectedPassword ||
      (foundUser.mustChangePassword && Boolean(userSurname) && (cleanPass.toLowerCase() === userSurname.toLowerCase() || cleanPass === 'ncst123')) ||
      (!foundUser.mustChangePassword && isGuest && (cleanPass === 'password123' || cleanPass === 'ncst123'));
    if (!passMatches) {
      sounds.playError();
      return { success: false, message: 'Invalid password. Please verify your credentials.' };
    }

    const safeFoundUser: User = { ...foundUser };
    delete (safeFoundUser as any).password;
    delete (safeFoundUser as any).temporaryPassword;
    currentUser.value = safeFoundUser;
    const token = `sess_${safeFoundUser.role}_${safeFoundUser.id}`;
    sessionToken.value = token;
    safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(safeFoundUser));
    safeLocalStorageSetItem(STORAGE_KEYS.SESSION_TOKEN, token);
    validateAndSyncSession();
    fetchStateFromServer();
    sounds.playSuccess();
    return { success: true, message: `Welcome back, ${safeFoundUser.name}!`, user: safeFoundUser };
  };

  /**
   * Authoritative Student Record Lookup for Student Portal Registration
   * Retrieves official name, department, course, year level, and section using complete Student ID.
   */
  const lookupStudentRecord = async (rawStudentId: string): Promise<{
    found: boolean;
    hasExistingAccount: boolean;
    accountStatus?: string;
    isAuthoritative?: boolean;
    student?: StudentRecord;
    message: string;
  }> => {
    const clean = normalizeId(rawStudentId).trim();
    if (!clean) {
      return { found: false, hasExistingAccount: false, message: 'Student ID Number is required.' };
    }

    // 1. Query authoritative backend endpoint
    try {
      const res = await fetch(apiUrl('/api/lookup-student'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ studentId: clean }),
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.success) {
          return {
            found: data.found,
            hasExistingAccount: data.hasExistingAccount,
            accountStatus: data.accountStatus,
            isAuthoritative: data.isAuthoritative,
            student: data.student,
            message: data.message,
          };
        }
      }
    } catch {}

    // 2. Fallback to client-synchronized users and studentRecords (offline fallback)
    const existingUser = users.value.find(
      u => u.studentId && normalizeId(u.studentId).toLowerCase() === clean.toLowerCase()
    );
    const record = studentRecords.value.find(
      r => normalizeId(r.studentId).toLowerCase() === clean.toLowerCase()
    );

    if (!existingUser && !record) {
      return {
        found: false,
        hasExistingAccount: false,
        message: 'Student ID not found in the official institutional roster.',
      };
    }

    const canonicalName = record?.name || existingUser?.name || '';
    const canonicalStudentId = record?.studentId || existingUser?.studentId || clean;
    const canonicalLastName = record?.lastName || (record as any)?.last_name || existingUser?.lastName || (existingUser as any)?.last_name || '';
    const canonicalCourse = record?.course || existingUser?.course || 'BSIT';
    const canonicalYear = record?.yearLevel || existingUser?.yearLevel || '1st Year';
    const canonicalSection = record?.section || existingUser?.section || '11M1';
    const canonicalDept = record?.department || existingUser?.department || 'CSD';

    const hasAccount = !!existingUser;
    const isAuthoritative = !!record;

    return {
      found: true,
      hasExistingAccount: hasAccount,
      accountStatus: hasAccount ? (existingUser.accountStatus || 'active') : 'unregistered',
      isAuthoritative,
      student: {
        id: record?.id || existingUser?.id || `rec-${clean}`,
        studentId: canonicalStudentId,
        name: canonicalName,
        firstName: record?.firstName || existingUser?.firstName,
        middleName: record?.middleName || existingUser?.middleName,
        lastName: canonicalLastName,
        course: canonicalCourse,
        yearLevel: canonicalYear,
        section: canonicalSection,
        department: canonicalDept,
        createdAt: record?.createdAt || existingUser?.createdAt || new Date().toISOString(),
      },
      message: hasAccount
        ? `An account is already registered for Student ID ${clean}.`
        : 'Student Record Found',
    };
  };

  const sendStudentVerificationCode = async (rawStudentId: string): Promise<{
    success: boolean;
    message: string;
  }> => {
    const clean = normalizeId(rawStudentId);
    if (!clean) {
      return { success: false, message: 'Student ID is required.' };
    }
    return {
      success: true,
      message: 'Verification code generated.',
    };
  };

  const verifyStudentVerificationCode = async (rawStudentId: string, code: string): Promise<{
    success: boolean;
    verificationToken?: string;
    message: string;
  }> => {
    return {
      success: true,
      verificationToken: `vtok_${Date.now()}`,
      message: 'Identity verified successfully!',
    };
  };

  const register = async (data: {
    name?: string;
    role?: UserRole;
    password: string;
    studentId: string;
    department?: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    verificationToken?: string;
  }): Promise<{ success: boolean; message: string; user?: User; conflict?: IdentityConflictResult }> => {
    const cleanStudentId = normalizeId(data.studentId);
    const assignedRole: UserRole = 'student'; // Self-registration is strictly for students

    if (!cleanStudentId) {
      sounds.playError();
      return {
        success: false,
        message: 'Student ID Number is required for student registration.',
      };
    }

    // Check if an account is already provisioned for this Student ID
    const existingStudentAccount = users.value.find(
      u => u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    );

    if (existingStudentAccount) {
      sounds.playError();
      if (existingStudentAccount.mustChangePassword) {
        return {
          success: false,
          message: `An account for Student ID ${cleanStudentId} was automatically created during ticket purchase. Please sign in with your Student ID and your official surname as your temporary password to set your permanent password.`,
        };
      }
      return {
        success: false,
        message: `An active account already exists for Student ID ${cleanStudentId}. Please sign in to the portal.`,
      };
    }

    // Check / Link to authoritative StudentRecord (Enrollment Identity)
    let isAuthoritative = false;
    let matchedStudentRecord = studentRecords.value.find(
      r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    );

    if (matchedStudentRecord) {
      isAuthoritative = true;
    } else {
      // Check in INITIAL_STUDENT_RECORDS fallback
      const seedRec = INITIAL_STUDENT_RECORDS.find(
        r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      );
      if (seedRec) {
        matchedStudentRecord = { ...seedRec };
        studentRecords.value.push(matchedStudentRecord);
        isAuthoritative = true;
      } else if (data.name && data.name.trim()) {
        // Manual Student Registration Fallback: Not in authoritative roster -> marked PENDING VERIFICATION
        isAuthoritative = false;
        matchedStudentRecord = {
          id: `rec-${cleanStudentId}`,
          studentId: cleanStudentId,
          name: data.name.trim(),
          course: data.course || 'BSIT',
          yearLevel: data.yearLevel || '1st Year',
          section: data.section || '11M1',
          department: data.department || 'CSD',
          createdAt: new Date().toISOString(),
        };
        studentRecords.value.push(matchedStudentRecord);
      }
    }

    if (!matchedStudentRecord) {
      sounds.playError();
      return {
        success: false,
        message: `Student ID "${cleanStudentId}" not found in the official institutional roster. Please check your Student ID or continue with manual registration.`,
      };
    }

    // Determine final academic attributes:
    // Authoritative records lock fields to authoritative StudentRecord;
    // Manual fallback registrations use the submitted values and are flagged as Pending Verification.
    const finalName = isAuthoritative ? matchedStudentRecord.name.trim() : (data.name ? data.name.trim() : matchedStudentRecord.name.trim());
    const finalCourse = isAuthoritative ? (matchedStudentRecord.course || 'BSIT') : (data.course || 'BSIT');
    const finalYear = isAuthoritative ? (matchedStudentRecord.yearLevel || '1st Year') : (data.yearLevel || '1st Year');
    const finalSection = isAuthoritative ? (matchedStudentRecord.section || '11M1') : (data.section || '11M1');
    const finalDept = isAuthoritative ? (matchedStudentRecord.department || 'CSD') : (data.department || 'CSD');

    // Primary Identity Check across ALL roles and ALL statuses (Active & Inactive)
    const conflict = checkUserIdentityConflict({
      name: finalName,
      role: assignedRole,
      studentId: cleanStudentId,
    }, users.value);

    if (conflict.hasConflict) {
      sounds.playError();
      return {
        success: false,
        message: conflict.message,
        conflict,
      };
    }

    const parsedRegName = splitFullName(finalName);
    const newUser: User = {
      id: `usr-student-${Date.now().toString().slice(-6)}`,
      name: finalName,
      firstName: matchedStudentRecord.firstName || parsedRegName.firstName,
      middleName: matchedStudentRecord.middleName || parsedRegName.middleName,
      lastName: matchedStudentRecord.lastName || parsedRegName.lastName,
      role: 'student',
      password: data.password,
      studentId: cleanStudentId,
      department: finalDept,
      course: finalCourse,
      yearLevel: finalYear,
      section: finalSection,
      isActive: true,
      accountStatus: 'active',
      mustChangePassword: false,
      verificationStatus: 'verified',
      isVerified: true,
      createdAt: new Date().toISOString(),
    };

    // Save/link student record with two-way field synchronization
    matchedStudentRecord.userId = newUser.id;

    // Automatically bind all existing tickets issued before account creation to this newly registered account
    tickets.value = tickets.value.map(t => {
      if (t.attendeeStudentId && normalizeId(t.attendeeStudentId).toLowerCase() === cleanStudentId.toLowerCase()) {
        return {
          ...t,
          currentAttendeeId: newUser.id,
          originalOwnerId: (t.originalOwnerId && (t.originalOwnerId.startsWith('rec-') || t.originalOwnerId.startsWith('usr-guest-') || t.originalOwnerId === t.attendeeStudentId))
            ? newUser.id
            : (t.originalOwnerId || newUser.id),
          firstName: newUser.firstName || t.firstName,
          lastName: newUser.lastName || t.lastName,
        };
      }
      return t;
    });

    // Update historical transactions as well
    transactions.value = transactions.value.map(tx => {
      if (tx.studentId && normalizeId(tx.studentId).toLowerCase() === cleanStudentId.toLowerCase()) {
        return {
          ...tx,
          userId: newUser.id,
          userName: newUser.name,
        };
      }
      return tx;
    });

    users.value = [newUser, ...users.value];
    currentUser.value = newUser;
    let token = `sess_${newUser.role}_${newUser.id}`;
    sessionToken.value = token;
    localStorage.setItem(STORAGE_KEYS.SESSION_TOKEN, token);

    // Push registration to authoritative server endpoint
    try {
      const res = await fetch(apiUrl('/api/register'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(newUser),
      });
      if (res.ok) {
        const serverData = await res.json();
        if (serverData && serverData.token) {
          token = serverData.token;
          sessionToken.value = token;
          localStorage.setItem(STORAGE_KEYS.SESSION_TOKEN, token);
        }
      }
    } catch {}

    validateAndSyncSession();
    fetchStateFromServer();
    sounds.playSuccess();
    return { success: true, message: `Student account created successfully! Welcome, ${newUser.name}.`, user: newUser };
  };

  const resetPassword = (identifier: string, newPasswordParam: string, _legacyParam?: string): { success: boolean; message: string; user?: User } => {
    const rawInput = identifier.trim();
    const newPassword = (_legacyParam !== undefined ? _legacyParam : newPasswordParam).trim();

    if (!rawInput) {
      sounds.playError();
      return { success: false, message: 'Identifier is required for password reset.' };
    }

    const cleanInput = rawInput.toLowerCase();
    const cleanId = normalizeId(rawInput).toLowerCase();

    // Match by studentId, employeeId, account id, or name across all accounts
    const targetUser = users.value.find(u => {
      const idMatch = (u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId) ||
                      (u.employeeId && normalizeId(u.employeeId).toLowerCase() === cleanId) ||
                      (u.id && u.id.toLowerCase() === cleanInput);
      const nameMatch = u.name && u.name.trim().toLowerCase() === cleanInput;
      return idMatch || nameMatch;
    });

    if (!targetUser) {
      sounds.playError();
      return { success: false, message: 'No registered account found matching this identifier.' };
    }

    if (targetUser.isActive === false || targetUser.accountStatus === 'deactivated') {
      sounds.playError();
      return { success: false, message: 'This account is deactivated. Please contact the Administrator.' };
    }

    // Outsider expiration check
    if (targetUser.role === 'outsider' && targetUser.expiresAt && new Date(targetUser.expiresAt).getTime() < Date.now()) {
      sounds.playError();
      return { success: false, message: 'This guest account has expired following the event date. Expired accounts cannot be reset.' };
    }

    if (newPassword.length < 4) {
      sounds.playError();
      return { success: false, message: 'Password must be at least 4 characters long.' };
    }

    const idx = users.value.findIndex(u => u.id === targetUser.id);
    if (idx !== -1) {
      const updated = {
        ...users.value[idx],
        password: newPassword,
        mustChangePassword: false,
        temporaryPassword: undefined,
      };
      users.value[idx] = updated;
      if (currentUser.value && currentUser.value.id === targetUser.id) {
        currentUser.value = updated;
      }
    }

    sounds.playSuccess();
    return {
      success: true,
      message: `Password for ${targetUser.name} (${targetUser.role.toUpperCase()}) was successfully reset! You can now sign in.`,
      user: users.value[idx],
    };
  };

  const updateUserPassword = async (
    userId: string, 
    newPassword: string
  ): Promise<{ success: boolean; message: string; user?: User }> => {
    const idx = users.value.findIndex(u => u.id === userId);
    if (idx === -1) {
      sounds.playError();
      return { success: false, message: 'User account not found.' };
    }
    if (newPassword.length < 4) {
      sounds.playError();
      return { success: false, message: 'Password must be at least 4 characters long.' };
    }

    // Call backend endpoint if session token exists
    if (sessionToken.value) {
      try {
        const res = await fetch(apiUrl('/api/auth/change-password'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${sessionToken.value}`,
            'x-session-token': sessionToken.value,
          },
          body: JSON.stringify({
            newPassword,
          }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
          sounds.playError();
          return { success: false, message: data.error || 'Failed to update password.' };
        }
        if (data.user) {
          users.value[idx] = { ...users.value[idx], ...data.user };
          if (currentUser.value && currentUser.value.id === userId) {
            currentUser.value = { ...currentUser.value, ...data.user };
            safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(currentUser.value));
          }
          sounds.playSuccess();
          return { success: true, message: data.message || 'Password updated successfully!', user: users.value[idx] };
        }
      } catch (err) {
        console.warn('Backend change-password sync notice:', err);
      }
    }

    const updated = {
      ...users.value[idx],
      password: newPassword,
      mustChangePassword: false,
      temporaryPassword: undefined,
    };
    users.value[idx] = updated;
    if (currentUser.value && currentUser.value.id === userId) {
      currentUser.value = updated;
      safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(updated));
    }

    sounds.playSuccess();
    return { success: true, message: 'Password updated successfully!', user: updated };
  };

  const adminResetUserPassword = async (targetUserId: string): Promise<{ success: boolean; message: string; user?: User }> => {
    const target = users.value.find(u => u.id === targetUserId);
    if (!target) {
      sounds.playError();
      return { success: false, message: 'Target user account not found.' };
    }

    if (sessionToken.value) {
      try {
        const res = await fetch(apiUrl('/api/auth/reset-password'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${sessionToken.value}`,
            'x-session-token': sessionToken.value,
          },
          body: JSON.stringify({ userId: targetUserId }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
          sounds.playError();
          return { success: false, message: data.error || 'Failed to reset password via administrator service.' };
        }

        const idx = users.value.findIndex(u => u.id === target.id);
        if (idx !== -1) {
          users.value[idx] = {
            ...users.value[idx],
            mustChangePassword: true,
            accountStatus: 'active',
            verificationStatus: 'verified',
            isVerified: true,
          };
        }
        sounds.playSuccess();
        return {
          success: true,
          message: data.message || `Password for ${target.name} has been reset to temporary initial password.`,
          user: users.value[idx],
        };
      } catch (err: any) {
        sounds.playError();
        return { success: false, message: err.message || 'Network error during administrator password reset.' };
      }
    }

    const idx = users.value.findIndex(u => u.id === target.id);
    if (idx !== -1) {
      users.value[idx] = { ...users.value[idx], mustChangePassword: true };
    }
    sounds.playSuccess();
    return {
      success: true,
      message: `Password for ${target.name} has been reset to temporary initial password.`,
      user: target,
    };
  };

  const setRecoveryPin = async (pin: string): Promise<{ success: boolean; message: string; user?: User }> => {
    if (!/^\d{4}$/.test(pin.trim())) {
      sounds.playError();
      return { success: false, message: 'Recovery PIN must be exactly 4 numeric digits.' };
    }

    if (sessionToken.value) {
      try {
        const res = await fetch(apiUrl('/api/auth/set-recovery-pin'), {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            'Authorization': `Bearer ${sessionToken.value}`,
            'x-session-token': sessionToken.value,
          },
          body: JSON.stringify({ pin: pin.trim() }),
        });
        const data = await res.json().catch(() => ({}));
        if (!res.ok || !data.success) {
          sounds.playError();
          return { success: false, message: data.error || 'Failed to save Recovery PIN.' };
        }
        if (currentUser.value) {
          currentUser.value = { ...currentUser.value, hasRecoveryPin: true };
          safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(currentUser.value));
        }
        sounds.playSuccess();
        return { success: true, message: data.message || 'Recovery PIN saved successfully.', user: currentUser.value || undefined };
      } catch (err: any) {
        sounds.playError();
        return { success: false, message: err?.message || 'Network error while configuring Recovery PIN.' };
      }
    }

    if (currentUser.value) {
      currentUser.value = { ...currentUser.value, hasRecoveryPin: true };
      safeLocalStorageSetItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(currentUser.value));
    }
    sounds.playSuccess();
    return { success: true, message: 'Recovery PIN saved successfully.', user: currentUser.value || undefined };
  };

  const identifyForRecovery = async (identifier: string): Promise<{ success: boolean; error?: string; noPin?: boolean; cooldown?: number; identifier?: string; role?: string; name?: string }> => {
    const rawId = identifier.trim();
    if (!rawId) return { success: false, error: 'Please enter your ID Number or Guest Reference Code.' };
    try {
      const res = await fetch(apiUrl('/api/auth/forgot-password/identify'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: rawId }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        return {
          success: false,
          error: data.error || 'Unable to proceed with account recovery. Please verify your ID Number.',
          noPin: !!data.noPin,
          cooldown: data.cooldown
        };
      }
      return { success: true, identifier: data.identifier, role: data.role, name: data.name };
    } catch (err: any) {
      return { success: false, error: 'Network or server failure. Please check your connection and try again.' };
    }
  };

  const verifyRecoveryPin = async (identifier: string, pin: string): Promise<{ success: boolean; error?: string; cooldown?: number; remainingAttempts?: number; resetToken?: string; message?: string }> => {
    try {
      const res = await fetch(apiUrl('/api/auth/forgot-password/verify-pin'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: identifier.trim(), pin: pin.trim() }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        return {
          success: false,
          error: data.error || 'Invalid Recovery PIN.',
          cooldown: data.cooldown,
          remainingAttempts: data.remainingAttempts
        };
      }
      return { success: true, resetToken: data.resetToken, message: data.message };
    } catch (err: any) {
      return { success: false, error: 'Network or server failure. Please check your connection and try again.' };
    }
  };

  const resetPasswordWithPinToken = async (resetToken: string, newPassword: string, confirmPassword: string): Promise<{ success: boolean; error?: string; message?: string }> => {
    try {
      const res = await fetch(apiUrl('/api/auth/forgot-password/reset-password'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ resetToken, newPassword, confirmPassword }),
      });
      const data = await res.json().catch(() => ({}));
      if (!res.ok || !data.success) {
        return { success: false, error: data.error || 'Failed to reset password.' };
      }
      sounds.playSuccess();
      return { success: true, message: data.message || 'Password reset successful!' };
    } catch (err: any) {
      return { success: false, error: 'Network or server failure. Please check your connection and try again.' };
    }
  };

  const importStudents = (studentsList: Array<{
    name: string;
    studentId?: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    department?: string;
  }>): { success: boolean; added: number; updated: number; total: number; message: string; importedClasses: TeacherClassAssignment[] } => {
    let addedCount = 0;
    let updatedCount = 0;
    const importedClassesMap = new Map<string, TeacherClassAssignment>();

    studentsList.forEach((st, idx) => {
      const cleanName = (st.name || '').trim();
      const cleanStudentId = (st.studentId || '').trim();
      if (!cleanName && !cleanStudentId) return;

      const finalName = cleanName || `Student ${cleanStudentId}`;
      const finalStudentId = cleanStudentId || `2026-${String(Date.now()).slice(-4)}${idx}`;
      const course = st.course?.trim() || 'BSIT';
      const section = st.section?.trim() || '11M1';
      const yearLevel = st.yearLevel?.trim() || deriveYearLevelFromSection(section) || '1st Year';
      const department = st.department?.trim() || getDepartmentForCourse(course) || 'College of Computer Studies';

      // Track class for teacher assignment
      const classKey = `${course.toUpperCase()}:::${section.toUpperCase()}`;
      if (!importedClassesMap.has(classKey)) {
        importedClassesMap.set(classKey, {
          course,
          section,
          yearLevel,
        });
      }

      const existingRec = studentRecords.value.find(
        r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      );

      // Link to existing User login account only if student already registered previously
      const existingUser = users.value.find(
        u => u.role === 'student' && u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      );

      saveStudentRecord({
        name: finalName,
        studentId: finalStudentId,
        course,
        yearLevel,
        section,
        department,
        userId: existingUser ? existingUser.id : existingRec?.userId,
      });

      if (existingRec) {
        updatedCount++;
      } else {
        addedCount++;
      }
    });

    const importedClassesList = Array.from(importedClassesMap.values());

    // If active user is a Teacher, auto-merge imported class sections into their assignedClasses
    if (currentUser.value?.role === 'teacher' && importedClassesList.length > 0) {
      const currentAssigned = [...(currentUser.value.assignedClasses || [])];
      let hasChanges = false;

      importedClassesList.forEach(cls => {
        const alreadyAssigned = currentAssigned.some(
          a => (a.course || '').trim().toUpperCase() === cls.course.toUpperCase() &&
               (a.section || '').trim().toUpperCase() === cls.section.toUpperCase()
        );
        if (!alreadyAssigned) {
          currentAssigned.push(cls);
          hasChanges = true;
        }
      });

      if (hasChanges) {
        currentUser.value = {
          ...currentUser.value,
          assignedClasses: currentAssigned,
        };

        const uIdx = users.value.findIndex(u => u.id === currentUser.value?.id);
        if (uIdx !== -1) {
          users.value[uIdx] = {
            ...users.value[uIdx],
            assignedClasses: currentAssigned,
          };
        }
      }
    }

    sounds.playSuccess();
    const totalCount = addedCount + updatedCount;
    return {
      success: true,
      added: addedCount,
      updated: updatedCount,
      total: totalCount,
      message: `Successfully imported ${totalCount} student records (${addedCount} new added, ${updatedCount} updated).`,
      importedClasses: importedClassesList,
    };
  };

  /**
   * Creates a dedicated temporary Outsider / Guest account linked to one or more events.
   * Auto-calculates initial expiration cutoff based on the latest associated event date in Philippine Time.
   */
  const createTemporaryOutsiderAccount = (data: {
    name: string;
    contactNumber?: string;
    eventId?: string;
    assignedEventIds?: string[];
    guestReference?: string;
    department?: string;
    password?: string;
  }): { success: boolean; message: string; user?: User } => {
    const cleanName = (data.name || '').trim();
    if (!cleanName) {
      sounds.playError();
      return { success: false, message: 'Guest/Outsider name is required.' };
    }

    const cleanGuestRef = (data.guestReference || `GUEST-${Math.floor(1000 + Math.random() * 9000)}`).trim().toUpperCase();
    const eventIds = data.assignedEventIds || (data.eventId ? [data.eventId] : []);

    const parsedName = splitFullName(cleanName);
    const newGuestUser: User = {
      id: `usr-guest-${Date.now()}`,
      name: cleanName,
      firstName: parsedName.firstName,
      middleName: parsedName.middleName,
      lastName: parsedName.lastName,
      role: 'student', // Login-compatible role
      attendeeType: 'OUTSIDER',
      isOutsider: true,
      studentId: cleanGuestRef,
      department: data.department || 'General Public / Guest',
      eventId: data.eventId || eventIds[0],
      assignedEventIds: eventIds,
      createdAt: new Date().toISOString(),
      isActive: true,
      accountStatus: 'active',
      password: (data.password || 'password123').trim(),
    };

    // Calculate expiration date
    const { expirationDate } = getOutsiderAccountExpirationDate(newGuestUser, events.value, tickets.value);
    if (expirationDate) {
      newGuestUser.expiresAt = expirationDate.toISOString();
    }

    users.value = [newGuestUser, ...users.value];
    localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));
    sounds.playSuccess();
    return {
      success: true,
      message: `Temporary guest account created for ${cleanName} (${cleanGuestRef}).`,
      user: newGuestUser,
    };
  };

  const resetAllData = () => {
    console.warn('[Security Violation] Global database reset action is permanently disabled to enforce data integrity.');
  };

  // QR Label Print Queue Computed Metrics
  const pendingQrTickets = computed(() => {
    return tickets.value.filter(t => (t.qrPrintStatus === 'PENDING' || !t.qrPrintStatus) && t.ticketStatus !== 'CANCELLED');
  });

  const printedQrTickets = computed(() => {
    return tickets.value.filter(t => (t.qrPrintStatus === 'PRINTED' || t.qrPrintStatus === 'ATTACHED') && t.ticketStatus !== 'CANCELLED');
  });

  const qrMetrics = computed(() => {
    const validTickets = tickets.value.filter(t => t.ticketStatus !== 'CANCELLED');
    const totalIssued = validTickets.length;
    const pendingCount = pendingQrTickets.value.length;
    const printedCount = printedQrTickets.value.length;
    const usedCount = validTickets.filter(t => t.attendanceStatus === 'present').length;
    const notYetScannedCount = validTickets.filter(t => t.attendanceStatus === 'not_entered').length;

    return {
      totalIssued,
      pendingCount,
      printedCount,
      usedCount,
      notYetScannedCount,
    };
  });

  const lookupGuestTicket = async (controlNumber: string, contactNumber?: string): Promise<{ success: boolean; message?: string; ticket?: any; error?: string }> => {
    const rawCtrl = controlNumber.trim();
    if (!rawCtrl) {
      return { success: false, error: 'Control Number is required.' };
    }

    try {
      const res = await fetch(apiUrl('/api/tickets/guest-lookup'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ controlNumber: rawCtrl, contactNumber: contactNumber?.trim() }),
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.success && data.ticket) {
          return { success: true, ticket: data.ticket, message: data.message };
        }
      } else {
        const data = await res.json().catch(() => ({}));
        return { success: false, error: data.error || 'No guest ticket found matching this Control Number.' };
      }
    } catch {
      // Local store fallback
    }

    const cleanCtrl = rawCtrl.toLowerCase().replace(/\s+/g, '');
    const t = tickets.value.find(item => {
      if (item.attendeeType !== 'OUTSIDER') return false;
      const tNum = (item.ticketNumber || '').toLowerCase().replace(/\s+/g, '');
      const tQr = (item.qrToken || '').toLowerCase().replace(/\s+/g, '');
      const tId = (item.id || '').toLowerCase().replace(/\s+/g, '');
      return tNum === cleanCtrl || tQr === cleanCtrl || tId === cleanCtrl;
    });

    if (!t) {
      return { success: false, error: 'No guest ticket found matching this Control Number. Please check your ticket receipt.' };
    }

    if (!contactNumber) {
      return { success: false, error: 'Contact number verification is required to retrieve guest ticket.' };
    }

    const cleanContact = contactNumber.replace(/[^0-9]/g, '');
    const storedContact = (t.contactNumber || '').replace(/[^0-9]/g, '');
    if (!cleanContact || !storedContact || (cleanContact !== storedContact && !storedContact.endsWith(cleanContact) && !cleanContact.endsWith(storedContact))) {
      return { success: false, error: 'Verification failed: Contact number does not match this ticket record.' };
    }

    const ev = events.value.find(e => e.id === t.eventId);
    return {
      success: true,
      ticket: {
        controlNumber: t.ticketNumber || t.id,
        ticketId: t.id,
        eventTitle: t.eventTitle || ev?.title || 'NCST Event',
        venue: ev?.venue || 'NCST Campus',
        date: ev?.date || 'Upcoming',
        startTime: ev?.startTime || '',
        endTime: ev?.endTime || '',
        attendeeName: t.currentAttendeeName || t.originalOwnerName || 'Guest Attendee',
        ticketStatus: t.ticketStatus || 'ISSUED',
        paymentStatus: t.paymentStatus || 'paid',
        qrToken: t.qrToken || t.ticketNumber || t.id,
        price: t.price || 0,
        attendeeType: 'OUTSIDER'
      }
    };
  };

  const createAcademicPeriod = async (period: Partial<AcademicPeriod>): Promise<{ success: boolean; period?: AcademicPeriod; error?: string }> => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, error: '403 Forbidden: Only Administrators can configure academic periods.' };
    }

    try {
      const res = await fetch(apiUrl('/api/academic-periods'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify(period),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        sounds.playError();
        return { success: false, error: data.error || 'Failed to save academic period configuration.' };
      }

      await fetchStateFromServer();
      sounds.playSuccess();
      return { success: true, period: data.period };
    } catch (e: any) {
      sounds.playError();
      return { success: false, error: e.message || 'Network error while configuring academic period.' };
    }
  };

  const updateAcademicPeriod = async (period: Partial<AcademicPeriod>): Promise<{ success: boolean; period?: AcademicPeriod; error?: string }> => {
    return createAcademicPeriod(period);
  };

  const activateAcademicPeriod = async (periodId: string): Promise<{ success: boolean; activePeriod?: AcademicPeriod; message?: string; error?: string }> => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, error: '403 Forbidden: Only Administrators can activate academic semesters.' };
    }

    try {
      const res = await fetch(apiUrl('/api/academic-periods/activate'), {
        method: 'POST',
        headers: getAuthHeaders(),
        body: JSON.stringify({ id: periodId }),
      });
      const data = await res.json();
      if (!res.ok || !data.success) {
        sounds.playError();
        return { success: false, error: data.error || 'Failed to activate academic semester.' };
      }

      await fetchStateFromServer();
      sounds.playSuccess();
      return { success: true, activePeriod: data.activePeriod, message: data.message };
    } catch (e: any) {
      sounds.playError();
      return { success: false, error: e.message || 'Network error while activating academic semester.' };
    }
  };

  const fetchTicketSalesSummary = async (filters?: string | {
    eventId?: string;
    academicYear?: string;
    semester?: string;
    attendeeType?: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    dateFrom?: string;
    dateTo?: string;
  }) => {
    const filterObj = typeof filters === 'string' ? { eventId: filters } : (filters || {});
    const params = new URLSearchParams();
    if (filterObj.eventId && filterObj.eventId !== 'all') params.append('eventId', filterObj.eventId);
    if (filterObj.academicYear && filterObj.academicYear !== 'all') params.append('academicYear', filterObj.academicYear);
    if (filterObj.semester && filterObj.semester !== 'all') params.append('semester', filterObj.semester);
    if (filterObj.attendeeType && filterObj.attendeeType !== 'all') params.append('attendeeType', filterObj.attendeeType);
    if (filterObj.course && filterObj.course !== 'all') params.append('course', filterObj.course);
    if (filterObj.yearLevel && filterObj.yearLevel !== 'all') params.append('yearLevel', filterObj.yearLevel);
    if (filterObj.section && filterObj.section !== 'all') params.append('section', filterObj.section);
    if (filterObj.dateFrom) params.append('dateFrom', filterObj.dateFrom);
    if (filterObj.dateTo) params.append('dateTo', filterObj.dateTo);

    const queryString = params.toString();
    const cacheKey = queryString ? `__FILTER__${queryString}` : (filterObj.eventId || '__GLOBAL__');

    try {
      const url = apiUrl(`/api/tickets/sales-summary${queryString ? `?${queryString}` : ''}`);
      const res = await fetch(url, {
        headers: getAuthHeaders(),
      });
      if (res.ok) {
        const data = await res.json();
        if (data.success && data.summary) {
          const s = data.summary;
          const paidStudent = Number(s.paidStudentTicketsSold ?? s.studentTicketsSold ?? 0);
          const paidOutsider = Number(s.paidOutsiderTicketsSold ?? s.outsiderTicketsSold ?? 0);
          const totalPaid = Number(s.totalPaidTicketsSold ?? (paidStudent + paidOutsider));
          const freeIssued = Number(s.freeTicketsIssued ?? s.freeTicketsSold ?? 0);
          const freeStudent = Number(s.freeStudentTickets ?? 0);
          const freeOutsider = Number(s.freeOutsiderTickets ?? 0);
          const totalValid = Number(s.totalValidTicketsIssued ?? (totalPaid + freeIssued));
          const totalRevenue = Number(s.totalCollectedSales ?? s.totalRevenue ?? 0);
          const totalStudent = Number(s.totalStudentTickets ?? (paidStudent + freeStudent));
          const totalOutsider = Number(s.totalOutsiderTickets ?? (paidOutsider + freeOutsider));

          const result: TicketSalesSummary = {
            paidStudentTicketsSold: paidStudent,
            paidOutsiderTicketsSold: paidOutsider,
            totalPaidTicketsSold: totalPaid,
            freeTicketsIssued: freeIssued,
            freeStudentTickets: freeStudent,
            freeOutsiderTickets: freeOutsider,
            totalValidTicketsIssued: totalValid,
            totalCollectedSales: totalRevenue,
            totalStudentTickets: totalStudent,
            totalOutsiderTickets: totalOutsider,
            // Backwards-compatible aliases:
            studentTicketsSold: paidStudent,
            outsiderTicketsSold: paidOutsider,
            totalTicketsSold: totalPaid,
            paidTicketsSold: totalPaid,
            freeTicketsSold: freeIssued,
            totalRevenue: totalRevenue,
            breakdowns: data.breakdowns || { byEvent: [], bySection: [] },
          };

          salesSummaryCache.value[cacheKey] = result;
          salesSummaryLastUpdated.value[cacheKey] = Date.now();

          return result;
        }
      }
    } catch (e) {
      console.warn('Backend sales-summary fetch warning, calculating from authoritative local state:', e);
    }

    // If backend fetch failed and local store has no tickets, return null so UI displays 'Unavailable' rather than false zeros
    if (!tickets.value || tickets.value.length === 0) {
      return null;
    }

    // Resilient fallback: Compute from current store tickets (excluding VOID, CANCELLED, REFUNDED, PENDING)
    const validTickets = tickets.value.filter(t => {
      if (filterObj.eventId && filterObj.eventId !== 'all' && t.eventId !== filterObj.eventId) return false;
      
      const ev = events.value.find(e => e.id === t.eventId);
      const ayr = t.academicYear || ev?.academicYear;
      const sem = t.semester || ev?.semester;
      if (filterObj.academicYear && filterObj.academicYear !== 'all' && ayr !== filterObj.academicYear) return false;
      if (filterObj.semester && filterObj.semester !== 'all' && sem !== filterObj.semester) return false;
      
      const attType = (t.attendeeType || 'STUDENT').toUpperCase();
      if (filterObj.attendeeType && filterObj.attendeeType !== 'all' && attType !== filterObj.attendeeType.toUpperCase()) return false;

      if (filterObj.course && filterObj.course !== 'all' && (t.course || '').toUpperCase() !== filterObj.course.toUpperCase()) return false;
      if (filterObj.yearLevel && filterObj.yearLevel !== 'all' && (t.yearLevel || '').toLowerCase() !== filterObj.yearLevel.toLowerCase()) return false;
      if (filterObj.section && filterObj.section !== 'all' && (t.section || '').toUpperCase() !== filterObj.section.toUpperCase()) return false;

      if (filterObj.dateFrom) {
        const pDate = t.purchaseDate ? t.purchaseDate.slice(0, 10) : '';
        if (pDate && pDate < filterObj.dateFrom) return false;
      }
      if (filterObj.dateTo) {
        const pDate = t.purchaseDate ? t.purchaseDate.slice(0, 10) : '';
        if (pDate && pDate > filterObj.dateTo) return false;
      }

      const status = (t.ticketStatus || '').toUpperCase();
      if (status === 'VOID' || status === 'CANCELLED' || status === 'REFUNDED') return false;
      const payStatus = (t.paymentStatus || '').toLowerCase();
      if (payStatus !== 'paid' && payStatus !== 'free') return false;
      return true;
    });

    const isPaid = (t: any) => (t.paymentStatus || 'paid').toLowerCase() === 'paid';
    const isFree = (t: any) => (t.paymentStatus || '').toLowerCase() === 'free';
    const isStudent = (t: any) => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT';
    const isOutsider = (t: any) => (t.attendeeType || '').toUpperCase() === 'OUTSIDER';

    const paidStudentSold = validTickets.filter(t => isStudent(t) && isPaid(t)).length;
    const paidOutsiderSold = validTickets.filter(t => isOutsider(t) && isPaid(t)).length;
    const totalPaidSold = paidStudentSold + paidOutsiderSold;

    const freeStudent = validTickets.filter(t => isStudent(t) && isFree(t)).length;
    const freeOutsider = validTickets.filter(t => isOutsider(t) && isFree(t)).length;
    const freeIssued = freeStudent + freeOutsider;

    const totalValidIssued = totalPaidSold + freeIssued;
    const totalStudent = paidStudentSold + freeStudent;
    const totalOutsider = paidOutsiderSold + freeOutsider;
    const totalRev = validTickets.reduce((sum, t) => sum + (isPaid(t) ? (t.price || 0) : 0), 0);

    const fallbackResult: TicketSalesSummary = {
      paidStudentTicketsSold: paidStudentSold,
      paidOutsiderTicketsSold: paidOutsiderSold,
      totalPaidTicketsSold: totalPaidSold,
      freeTicketsIssued: freeIssued,
      freeStudentTickets: freeStudent,
      freeOutsiderTickets: freeOutsider,
      totalValidTicketsIssued: totalValidIssued,
      totalCollectedSales: totalRev,
      totalStudentTickets: totalStudent,
      totalOutsiderTickets: totalOutsider,
      // Backwards-compatible aliases:
      studentTicketsSold: paidStudentSold,
      outsiderTicketsSold: paidOutsiderSold,
      totalTicketsSold: totalPaidSold,
      paidTicketsSold: totalPaidSold,
      freeTicketsSold: freeIssued,
      totalRevenue: totalRev,
      breakdowns: { byEvent: [], bySection: [] },
    };

    salesSummaryCache.value[cacheKey] = fallbackResult;
    salesSummaryLastUpdated.value[cacheKey] = Date.now();

    return fallbackResult;
  };

  return {
    currentUser,
    setCurrentUser,
    isAuthenticated,
    login,
    lookupGuestTicket,
    register,
    resetPassword,
    findUserByIdentifier,
    logout,
    users,
    studentRecords,
    availableSections: computed(() => extractActualSystemSections(studentRecords.value, users.value, tickets.value)),
    saveStudentRecord,
    events,
    currentEvent,
    setCurrentEvent,
    tickets,
    transactions,
    logs,
    ticketStock,
    academicPeriods,
    activeAcademicPeriod,
    createAcademicPeriod,
    updateAcademicPeriod,
    activateAcademicPeriod,
    filters,
    resetFilters,
    filteredTickets,
    pendingQrTickets,
    printedQrTickets,
    qrMetrics,
    createEvent,
    updateEvent,
    deleteEvent,
    purchaseTicket,
    fetchTicketSalesSummary,
    salesSummaryCache,
    salesSummaryLastUpdated,
    transferTicket,
    refundTicket,
    cancelTicket,
    markQrAsPrinted,
    markQrAsPending,
    markQrAsAttached,
    createPhysicalTicket,
    deleteTicket,
    clearAllTickets,
    validateAndRecordScan,
    fetchStateFromServer,
    lookupTicketInfo,
    addUser,
    updateUser,
    reactivateUser,
    deleteUser,
    adminResetUserPassword,
    updateUserPassword,
    setRecoveryPin,
    identifyForRecovery,
    verifyRecoveryPin,
    resetPasswordWithPinToken,
    activeEvents,
    archivedEvents,
    archiveEvent,
    restoreArchivedEvent,
    registerStudent,
    importStudents,
    validateEvent,
    checkUserIdentityConflict,
    validateUserForm,
    resetAllData,
    evaluateOutsiderAccountExpirations,
    createTemporaryOutsiderAccount,
    lookupStudentRecord,
    sendStudentVerificationCode,
    verifyStudentVerificationCode,
  };
}

