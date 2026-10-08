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
} from '../types';
import {
  INITIAL_USERS,
  INITIAL_STUDENT_RECORDS,
  INITIAL_EVENTS,
  INITIAL_TICKETS,
  INITIAL_LOGS,
  INITIAL_TRANSACTIONS
} from '../utils/seedData';
import { sounds } from '../utils/audio';
import { useOfflineScanner } from './useOfflineScanner';
import { splitFullName, getLastName, getFirstName, sortByLastName } from '../utils/nameUtils';
import { validateEvent, normalizeTimeToDisplay, EventFormData, ValidationResult } from '../utils/eventValidation';
import {
  checkUserIdentityConflict,
  validateUserForm,
  normalizeEmail,
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
  validateEmail as validateEmailFormat,
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
  CURRENT_USER: 'integ2_vue_current_user_v10',
  CURRENT_EVENT: 'integ2_vue_current_event_v10',
  SESSION_TOKEN: 'integ2_vue_session_token_v10',
};

const sessionToken = ref<string>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.SESSION_TOKEN);
    if (s) return s;
    return '';
  })()
);

/**
 * Ensures all institutional seed demo accounts (Admin, Cashier, Staff, Faculty, Student)
 * are present, valid, active, and securely authenticated in the user repository.
 */
export function ensureSeedUsers(userList: User[]): User[] {
  if (!Array.isArray(userList) || userList.length === 0) return [...INITIAL_USERS];

  const merged = [...userList];

  INITIAL_USERS.forEach(seedUser => {
    const seedEmail = normalizeEmail(seedUser.email);
    const seedEmpId = seedUser.employeeId ? normalizeId(seedUser.employeeId).toLowerCase() : '';
    const seedStudentId = seedUser.studentId ? normalizeId(seedUser.studentId).toLowerCase() : '';
    const seedId = (seedUser.id || '').toLowerCase();

    const existingIndex = merged.findIndex(u => {
      if (u.id && u.id.toLowerCase() === seedId) return true;
      if (u.email && normalizeEmail(u.email) === seedEmail) return true;
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
        email: current.email || seedUser.email,
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
          return ensureSeedUsers(parsed);
        }
      } catch {}
    }
    return [...INITIAL_USERS];
  })()
);

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
          return parsed;
        }
      } catch {}
    }
    return [...INITIAL_STUDENT_RECORDS];
  })()
);

const events = ref<EventItem[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.EVENTS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return INITIAL_EVENTS;
  })()
);

const tickets = ref<Ticket[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.TICKETS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return INITIAL_TICKETS;
  })()
);

const logs = ref<AttendanceLog[]>(
  (() => {
    const s = localStorage.getItem(STORAGE_KEYS.LOGS);
    if (s !== null) {
      try {
        const parsed = JSON.parse(s);
        if (Array.isArray(parsed)) return parsed;
      } catch {}
    }
    return INITIAL_LOGS;
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
    currentUser.value.email !== authoritative.email ||
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
      events: events.value,
      tickets: tickets.value,
      logs: logs.value,
      transactions: transactions.value,
      timestamp: localLastModified,
    };
    await fetch('/api/state', {
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
    const url = localLastModified > 0 ? `/api/state?since=${localLastModified}` : '/api/state';
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

      isSyncingFromServer = true;
      // Overwrite users/tickets/events if server timestamp is newer or if read-only role
      if (Array.isArray(data.users) && data.users.length > 0) {
        users.value = ensureSeedUsers(data.users);
        validateAndSyncSession();
      }
      if (Array.isArray(data.studentRecords) && data.studentRecords.length > 0) studentRecords.value = data.studentRecords;
      if (Array.isArray(data.events) && data.events.length > 0) events.value = data.events;
      if (Array.isArray(data.tickets) && data.tickets.length > 0) tickets.value = data.tickets;
      if (Array.isArray(data.logs) && data.logs.length > 0) logs.value = data.logs;
      if (Array.isArray(data.transactions) && data.transactions.length > 0) transactions.value = data.transactions;
      if (serverTimestamp) {
        localLastModified = serverTimestamp;
        localWriteTimestamp = serverTimestamp;
      }
      setTimeout(() => { isSyncingFromServer = false; }, 200);
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
          users.value = ensureSeedUsers(parsed);
          validateAndSyncSession();
        }
      } else if (e.key === STORAGE_KEYS.STUDENT_RECORDS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) studentRecords.value = parsed;
      } else if (e.key === STORAGE_KEYS.EVENTS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) events.value = parsed;
      } else if (e.key === STORAGE_KEYS.TRANSACTIONS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) transactions.value = parsed;
      } else if (e.key === STORAGE_KEYS.LOGS) {
        const parsed = JSON.parse(e.newValue);
        if (Array.isArray(parsed)) logs.value = parsed;
      }
    } catch {}
  });
}

// Watchers for LocalStorage persistence & Server State Sync
watch(users, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(val));
  validateAndSyncSession();
  schedulePushStateToServer();
}, { deep: true });

watch(studentRecords, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.STUDENT_RECORDS, JSON.stringify(val));
  schedulePushStateToServer();
}, { deep: true });

watch(events, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.EVENTS, JSON.stringify(val));
  if (!val || val.length === 0) {
    currentEvent.value = null;
    localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
  } else if (!currentEvent.value || !val.some(e => e.id === currentEvent.value?.id)) {
    currentEvent.value = val[0];
    localStorage.setItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(val[0]));
  }
  schedulePushStateToServer();
}, { deep: true });

watch(tickets, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.TICKETS, JSON.stringify(val));
  schedulePushStateToServer();
}, { deep: true });

watch(logs, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.LOGS, JSON.stringify(val));
  schedulePushStateToServer();
}, { deep: true });

watch(transactions, val => {
  localWriteTimestamp = Date.now();
  localStorage.setItem(STORAGE_KEYS.TRANSACTIONS, JSON.stringify(val));
  schedulePushStateToServer();
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
        const aType = ticket.attendeeType || (ticket.attendeeStudentId ? 'STUDENT' : 'OUTSIDER');
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
        const matchEmail = (ticket.attendeeEmail || '').toLowerCase().includes(q);
        const matchOriginal = (ticket.originalOwnerName || '').toLowerCase().includes(q);
        if (!matchId && !matchNum && !matchName && !matchStudentId && !matchCourse && !matchYear && !matchSection && !matchEmail && !matchOriginal) {
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
    const newEvent: EventItem = {
      ...eventData,
      id: eventId,
      startTime: normalizedStartTime,
      endTime: normalizedEndTime,
      capacity: Number(eventData.capacity) || 500,
      ticketPrice: Number(eventData.ticketPrice) || 0,
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

  const deleteEvent = (id: string) => {
    if (!currentUser.value || currentUser.value.role !== 'admin') {
      sounds.playError();
      return { success: false, message: '403 Forbidden: Only Administrators can delete events.' };
    }

    events.value = events.value.filter(e => e.id !== id);
    if (currentEvent.value && currentEvent.value.id === id) {
      currentEvent.value = events.value[0] || null;
      if (currentEvent.value) {
        localStorage.setItem(STORAGE_KEYS.CURRENT_EVENT, JSON.stringify(currentEvent.value));
      } else {
        localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
      }
    }
    // Also remove tickets, transactions, and logs belonging to the deleted event
    tickets.value = tickets.value.filter(t => t.eventId !== id);
    transactions.value = transactions.value.filter(tx => tx.eventId !== id);
    logs.value = logs.value.filter(l => l.eventId !== id);
    return { success: true };
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
    email?: string;
    department?: string;
    photoUrl?: string;
    userId?: string;
  }): StudentRecord => {
    const cleanStudentId = normalizeId(data.studentId);
    const existingIdx = studentRecords.value.findIndex(
      r => normalizeId(r.studentId).toLowerCase() === cleanStudentId.toLowerCase()
    );
    const parsedName = splitFullName(data.name.trim());
    
    if (existingIdx !== -1) {
      const existing = studentRecords.value[existingIdx];
      const updated: StudentRecord = {
        ...existing,
        name: data.name.trim() || existing.name,
        firstName: parsedName.firstName || existing.firstName,
        middleName: parsedName.middleName || existing.middleName,
        lastName: parsedName.lastName || existing.lastName,
        course: data.course?.trim() || existing.course,
        yearLevel: data.yearLevel?.trim() || existing.yearLevel,
        section: data.section?.trim() || existing.section,
        department: data.department?.trim() || existing.department,
        email: data.email?.trim() || existing.email,
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
      firstName: parsedName.firstName,
      middleName: parsedName.middleName,
      lastName: parsedName.lastName,
      course: data.course?.trim() || 'BSIT',
      yearLevel: data.yearLevel?.trim() || '3rd Year',
      section: data.section?.trim() || 'M1',
      department: data.department?.trim() || `College of ${data.course?.trim() || 'Computer Studies'}`,
      email: data.email?.trim() || `${data.name.toLowerCase().replace(/[^a-z0-9]/g, '.')}@student.edu.ph`,
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
    email?: string;
    department?: string;
  }): StudentRecord => {
    return saveStudentRecord(data);
  };

  // Ticket creation for registered students / cashier issuance
  const purchaseTicket = (
    eventId: string,
    photoUrl?: string,
    userId?: string,
    cashierStaffName?: string,
    studentOverride?: {
      studentId?: string;
      name?: string;
      course?: string;
      yearLevel?: string;
      section?: string;
      department?: string;
      email?: string;
      attendeeType?: AttendeeType;
      contactNumber?: string;
      cashReceived?: number | string;
    },
    customPrice?: number
  ): {
    success: boolean;
    message: string;
    ticket: Ticket;
    accountCreated?: boolean;
  } => {
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
    const yearLevel = isOutsider ? undefined : (studentOverride?.yearLevel || '3rd Year');
    const section = isOutsider ? undefined : (studentOverride?.section || 'M1');
    const department = isOutsider ? 'General Public / Guest' : (studentOverride?.department || 'College of Computer Studies');
    const email = studentOverride?.email || (isOutsider ? undefined : `${studentName.toLowerCase().replace(/[^a-z0-9]/g, '.')}@student.edu.ph`);
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
      if (email) {
        const emailVal = validateEmailFormat(email, false);
        if (!emailVal.isValid) {
          sounds.playError();
          return {
            success: false,
            message: emailVal.error || 'Invalid email format.',
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
      } else {
        studentRec = saveStudentRecord({
          name: studentName,
          studentId: cleanStudentId,
          course: course || 'BSIT',
          yearLevel: yearLevel || '3rd Year',
          section: section || 'M1',
          email,
          department,
        });
      }

      // Check if this student already happens to have a registered User account
      existingUserAccount = users.value.find(
        u => u.studentId && normalizeId(u.studentId).toLowerCase() === cleanStudentId.toLowerCase()
      ) || null;

      if (!existingUserAccount) {
        // Automatically create ONE Student login account with initial temporary password ncst123
        const parsedName = splitFullName(studentRec.name.trim());
        const cleanEmail = studentRec.email || email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`;
        const initialPassword = 'ncst123';

        const newUser: User = {
          id: `usr-student-${Date.now().toString().slice(-6)}`,
          name: studentRec.name,
          firstName: studentRec.firstName || parsedName.firstName,
          middleName: studentRec.middleName || parsedName.middleName,
          lastName: studentRec.lastName || parsedName.lastName,
          email: cleanEmail,
          role: 'student',
          password: initialPassword,
          studentId: cleanStudentId,
          department: studentRec.department || department,
          course: studentRec.course || 'BSIT',
          yearLevel: studentRec.yearLevel || '3rd Year',
          section: studentRec.section || 'M1',
          isActive: true,
          accountStatus: 'active',
          mustChangePassword: true,
          createdAt: new Date().toISOString(),
        };

        users.value.unshift(newUser);
        studentRec.userId = newUser.id;
        existingUserAccount = newUser;
        accountCreated = true;
      } else {
        studentRec.userId = existingUserAccount.id;
      }
    } else if (userId) {
      existingUserAccount = users.value.find(u => u.id === userId) || null;
    }

    // Owner ID: Linked to student user account or student record
    const ownerId = existingUserAccount ? existingUserAccount.id : (studentRec ? studentRec.id : (isOutsider ? (studentOverride?.studentId || `usr-guest-${Date.now()}`) : `rec-${Date.now()}`));

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
    const ticketSeq = Math.floor(10000 + tickets.value.length + 1);
    const outsiderId = isOutsider ? (ownerId || `out-${ticketSeq}`) : undefined;
    const newTicketNumber = isOutsider ? `TIX-O-${ticketSeq}` : `TIX-${ticketSeq}`;

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
      lastName: existingUserAccount?.lastName || studentRec?.lastName || parsedName.lastName,
      attendeeStudentId: isOutsider ? undefined : (studentId ? normalizeId(studentId) : undefined),
      outsiderId,
      attendeeEmail: email,
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
    };

    tickets.value.unshift(newTicket);

    // Record Immutable Purchase / Registration Transaction
    const newTxn: PurchaseTransaction = {
      id: `TXN-2026-${ticketSeq}`,
      userId: ownerId,
      userName: studentName,
      attendeeType: calculatedAttendeeType,
      studentId: isOutsider ? undefined : studentId,
      course,
      yearLevel,
      section,
      contactNumber,
      userEmail: email,
      eventId: targetEvent ? targetEvent.id : eventId,
      eventTitle: targetEvent ? targetEvent.title : 'Event',
      ticketId: newTicketNumber,
      ticketNumber: newTicketNumber,
      amount: price,
      paymentStatus: price > 0 ? 'paid' : 'free',
      purchaseDate: new Date().toISOString(),
      quantity: 1,
      paymentMethod: cashierStaffName ? 'Cashier Ticket Desk (POS)' : (price > 0 ? 'Online Payment Portal' : 'Official Event Registration'),
      cashierName: cashierStaffName,
    };
    transactions.value.unshift(newTxn);

    sounds.playSuccess();
    return {
      success: true,
      message: `Ticket #${newTicketNumber} generated successfully for ${studentRec?.name || studentName}!`,
      ticket: newTicket,
      accountCreated,
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
    }
  };

  const markQrAsPending = (ticketIds: string[]) => {
    tickets.value.forEach(t => {
      if (ticketIds.includes(t.id) || (t.ticketNumber && ticketIds.includes(t.ticketNumber))) {
        t.qrPrintStatus = 'PENDING';
      }
    });
  };

  const markQrAsAttached = (ticketIds: string[]) => {
    tickets.value.forEach(t => {
      if (ticketIds.includes(t.id) || (t.ticketNumber && ticketIds.includes(t.ticketNumber))) {
        t.qrPrintStatus = 'ATTACHED';
      }
    });
    sounds.playSuccess();
  };

  // NCST Institutional Policy: Event tickets are strictly NON-TRANSFERABLE
  const transferTicket = (
    _ticketId: string,
    _toStudentName: string,
    _toStudentId?: string,
    _toEmail?: string,
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
  const addUser = (userData: Partial<User> & { name: string; email: string; role: UserRole }): { success: boolean; user?: User; message?: string; conflict?: IdentityConflictResult } => {
    const candidate = {
      name: userData.name,
      email: userData.email,
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
      email: normalizeEmail(userData.email),
      studentId: userData.role === 'student' ? normalizeId(userData.studentId) || undefined : undefined,
      employeeId: userData.role !== 'student' ? normalizeId(userData.employeeId) || undefined : undefined,
      id: userData.id || `usr-${userData.role}-${Date.now().toString(36)}`,
      createdAt: userData.createdAt || new Date().toISOString(),
      isActive: userData.isActive !== false,
    };

    users.value.unshift(newUser);
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
      email: updates.email !== undefined ? updates.email : current.email,
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
      email: updates.email !== undefined ? normalizeEmail(updates.email) : current.email,
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
          email: updatedUser.email,
          course: updatedUser.course || studentRecords.value[recIdx].course,
          yearLevel: updatedUser.yearLevel || studentRecords.value[recIdx].yearLevel,
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

  const deleteUser = (id: string) => {
    users.value = users.value.filter(u => u.id !== id);
    if (currentUser.value && currentUser.value.id === id) {
      currentUser.value = null;
    }
  };

  // QR Scanning & Attendance Validation Engine with Real-Time Student vs Outsider Classification
  const validateAndRecordScan = (
    rawScanData: string,
    isSimulation: any = false,
    _deprecatedArea?: any,
    _deprecatedScanType?: any
  ): ScanValidationResult => {
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
        sounds.playWarning();
        sounds.vibrateWarning();
      } else {
        sounds.playError();
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

    // CHECK 5: Does the ticket exist in the database?
    if (!ticket) {
      return recordDenied(`INVALID TICKET: Ticket number or QR token "${ticketTokenToLookup}" not found in database.`);
    }

    // CHECK 6: Does the QR ticket belong to the selected event?
    if (currentEvent.value && ticket.eventId !== currentEvent.value.id) {
      const actualEvent = events.value.find(e => e.id === ticket.eventId);
      return recordDenied(
        `WRONG EVENT: This ticket is registered for "${actualEvent ? actualEvent.title : ticket.eventId}", not for "${currentEvent.value.title}".`
      );
    }

    // CHECK 7: Is the ticket active / valid / paid?
    const tStatus = (ticket.ticketStatus || '').toUpperCase();
    if (tStatus === 'CANCELLED' || tStatus === 'REFUNDED' || tStatus === 'VOID') {
      return recordDenied(`TICKET NOT VALID: This ticket has been marked as ${tStatus} and is void.`);
    }

    // CHECK 8: Validate Registered Ticket Owner
    const ownerName = (ticket.currentAttendeeName || ticket.originalOwnerName || '').trim();
    const ownerId = (ticket.attendeeStudentId || ticket.currentAttendeeId || ticket.outsiderId || '').trim();
    if (!ownerName && !ownerId) {
      return recordDenied('INVALID OWNER: Ticket does not have a verified registered owner record.');
    }

    // Determine exact Attendee Type: STUDENT vs OUTSIDER
    const isStudentAccount = !!(ticket.attendeeStudentId || users.value.some(u => u.studentId === ticket.attendeeStudentId && u.role === 'student'));
    const classification: AttendeeType = ticket.attendeeType || (isStudentAccount ? 'STUDENT' : 'OUTSIDER');

    // CHECK 9: Has the ticket already been used? (Duplicate Scan Protection on Venue Entry)
    if (ticket.attendanceStatus === 'present' || tStatus === 'USED') {
      const prevScanTime = ticket.timeIn ? new Date(ticket.timeIn).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : 'Earlier';
      return recordDenied(
        `⚠️ TICKET ALREADY USED: Scanned at ${prevScanTime} by ${ticket.lastScannedBy || 'Event Staff'}. Attendee: ${ticket.currentAttendeeName} [${classification}].`
      );
    }

    // ALL CHECKS PASSED: ENTRY ALLOWED & ATTENDANCE RECORDED IN REAL TIME
    const updatedTicket: Ticket = {
      ...ticket,
      attendeeType: classification,
      ticketStatus: 'USED',
      attendanceStatus: 'present',
      timeIn: ticket.timeIn || timestamp,
      lastScannedBy: scannerName,
    };

    if (!actualIsSimulation) {
      const idx = tickets.value.findIndex(t => t.id === ticket.id);
      if (idx !== -1) tickets.value[idx] = updatedTicket;

      const parsedLogName = splitFullName(ticket.currentAttendeeName);
      const successLog: AttendanceLog = {
        id: `log-${Date.now()}`,
        ticketId: ticket.id,
        ticketNumber: ticket.ticketNumber || ticket.id,
        eventId: targetEvent ? targetEvent.id : 'unknown',
        eventTitle: targetEvent ? targetEvent.title : 'Event',
        attendeeType: classification,
        attendeeName: ticket.currentAttendeeName,
        firstName: ticket.firstName || parsedLogName.firstName,
        middleName: ticket.middleName || parsedLogName.middleName,
        lastName: ticket.lastName || parsedLogName.lastName,
        studentId: classification === 'STUDENT' ? ticket.attendeeStudentId : undefined,
        outsiderId: classification === 'OUTSIDER' ? (ticket.outsiderId || ticket.id) : undefined,
        course: classification === 'STUDENT' ? ticket.course : undefined,
        yearLevel: classification === 'STUDENT' ? ticket.yearLevel : undefined,
        section: classification === 'STUDENT' ? ticket.section : undefined,
        contactNumber: ticket.contactNumber,
        ticketType: ticket.ticketType,
        timestamp,
        date: new Date(timestamp).toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' }),
        timeIn: new Date(timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }),
        verifiedById: scannerId,
        verifiedByName: scannerName,
        status: 'valid',
      };
      logs.value.unshift(successLog);
      sounds.playSuccess();
      sounds.vibrateSuccess();

      const isOffline = typeof navigator !== 'undefined' && !navigator.onLine;
      if (isOffline) {
        useOfflineScanner().enqueueScan({
          rawScanData: ticketTokenToLookup,
          eventId: targetEvent ? targetEvent.id : undefined,
          scannerUserId: scannerId,
          scannerName,
          ticketId: ticket.id,
          ticketNumber: ticket.ticketNumber,
          attendeeName: ticket.currentAttendeeName,
        });
      }
    } else {
      sounds.playSuccess();
      sounds.vibrateSuccess();
    }

    return {
      isValid: true,
      status: 'valid',
      reason: actualIsSimulation ? '[TEST MODE] Simulated Scan Preview (No real attendance recorded)' : undefined,
      attendeeType: classification,
      ticket: updatedTicket,
      event: targetEvent,
      timestamp,
      timeInFormatted: new Date(updatedTicket.timeIn!).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' }),
      isOfflineRecorded: typeof navigator !== 'undefined' && !navigator.onLine,
    };
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
    if (!trimmed) return undefined;
    
    // 1. Search in current reactive repository
    let found = users.value.find(u => {
      const emailMatch = u.email && (normalizeEmail(u.email) === trimmed || u.email.trim().toLowerCase() === trimmed);
      const studentIdMatch = u.studentId && normalizeId(u.studentId).toLowerCase() === trimmed;
      const employeeIdMatch = u.employeeId && normalizeId(u.employeeId).toLowerCase() === trimmed;
      const nameMatch = u.name && u.name.trim().toLowerCase() === trimmed;
      const idMatch = u.id && u.id.trim().toLowerCase() === trimmed;

      return emailMatch || studentIdMatch || employeeIdMatch || nameMatch || idMatch;
    });

    // 2. If not found, check authoritative INITIAL_USERS seed demo accounts and self-heal
    if (!found) {
      const seedFound = INITIAL_USERS.find(u => {
        const emailMatch = u.email && (normalizeEmail(u.email) === trimmed || u.email.trim().toLowerCase() === trimmed);
        const studentIdMatch = u.studentId && normalizeId(u.studentId).toLowerCase() === trimmed;
        const employeeIdMatch = u.employeeId && normalizeId(u.employeeId).toLowerCase() === trimmed;
        const nameMatch = u.name && u.name.trim().toLowerCase() === trimmed;
        const idMatch = u.id && u.id.trim().toLowerCase() === trimmed;

        return emailMatch || studentIdMatch || employeeIdMatch || nameMatch || idMatch;
      });

      if (seedFound) {
        users.value = ensureSeedUsers([...users.value, { ...seedFound }]);
        found = users.value.find(u => u.id === seedFound.id) || seedFound;
      }
    }

    // 3. Check for Outsider / Guest Ticket Holders
    if (!found) {
      const cleanId = trimmed.replace(/\s+/g, '');
      const matchedTicket = tickets.value.find(t => {
        if (t.attendeeType !== 'OUTSIDER') return false;
        const codeMatch = (t.ticketNumber && t.ticketNumber.toLowerCase().replace(/\s+/g, '') === cleanId) || (t.id && t.id.toLowerCase().replace(/\s+/g, '') === cleanId);
        const contactMatch = t.contactNumber && t.contactNumber.replace(/[^0-9]/g, '') === cleanId.replace(/[^0-9]/g, '');
        const nameMatch = (t.currentAttendeeName && t.currentAttendeeName.trim().toLowerCase() === trimmed) || (t.originalOwnerName && t.originalOwnerName.trim().toLowerCase() === trimmed);
        const emailMatch = t.attendeeEmail && t.attendeeEmail.trim().toLowerCase() === trimmed;
        const guestIdMatch = (t.attendeeStudentId && t.attendeeStudentId.toLowerCase() === cleanId) || (t.outsiderId && t.outsiderId.toLowerCase() === cleanId);
        return Boolean(codeMatch || contactMatch || nameMatch || emailMatch || guestIdMatch);
      });

      if (matchedTicket) {
        const guestUserId = matchedTicket.currentAttendeeId || matchedTicket.originalOwnerId || `usr-guest-${matchedTicket.ticketNumber || matchedTicket.id}`;
        let guestUser = users.value.find(u => u.id === guestUserId);
        if (!guestUser) {
          guestUser = {
            id: guestUserId,
            name: matchedTicket.currentAttendeeName || matchedTicket.originalOwnerName || 'Guest Attendee',
            email: matchedTicket.attendeeEmail || `${cleanId}@guest.ncst.edu.ph`,
            role: 'outsider',
            isOutsider: true,
            attendeeType: 'OUTSIDER',
            studentId: matchedTicket.ticketNumber || matchedTicket.contactNumber || 'GUEST-PASS',
            department: 'External Guest / Outsider',
            password: 'password123',
            isActive: true,
            accountStatus: 'active',
            createdAt: matchedTicket.purchaseDate || new Date().toISOString(),
            assignedEventIds: [matchedTicket.eventId],
          };
          users.value.push(guestUser);
          localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));
        }
        matchedTicket.currentAttendeeId = guestUser.id;
        matchedTicket.originalOwnerId = matchedTicket.originalOwnerId || guestUser.id;
        found = guestUser;
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
    const trimmed = identifier.trim().toLowerCase();
    const cleanPass = password.trim();

    // 1. Authoritative Backend Server Authentication
    try {
      const res = await fetch('/api/auth/login', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ identifier: trimmed, password: cleanPass }),
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.success && data.user) {
          const serverUser: User = {
            ...data.user,
            password: cleanPass,
          };
          const token = data.token || data.sessionToken || `sess_${serverUser.role}_${serverUser.id}`;

          // Synchronize into local reactive users collection
          const existingIdx = users.value.findIndex(u =>
            u.id === serverUser.id ||
            (u.studentId && normalizeId(u.studentId).toLowerCase() === normalizeId(serverUser.studentId || '').toLowerCase()) ||
            (u.email && normalizeEmail(u.email) === normalizeEmail(serverUser.email || ''))
          );
          if (existingIdx !== -1) {
            users.value[existingIdx] = { ...users.value[existingIdx], ...serverUser };
          } else {
            users.value = [serverUser, ...users.value];
          }

          currentUser.value = serverUser;
          sessionToken.value = token;
          localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(serverUser));
          localStorage.setItem(STORAGE_KEYS.SESSION_TOKEN, token);
          localStorage.setItem(STORAGE_KEYS.USERS, JSON.stringify(users.value));

          // Fetch full authenticated state from server
          await fetchStateFromServer();

          sounds.playSuccess();
          return { success: true, message: data.message || `Welcome back, ${serverUser.name}!`, user: serverUser };
        }
      } else if (res.status === 401 || res.status === 403) {
        const data = await res.json().catch(() => ({}));
        if (data && data.error) {
          sounds.playError();
          return { success: false, message: data.error };
        }
      }
    } catch {
      // Server offline or unreachable: fall back to local store
    }

    // 2. Client-side local fallback authentication
    const foundUser = findUserByIdentifier(trimmed);

    if (!foundUser) {
      sounds.playError();
      return { success: false, message: 'No account found with this Student ID or Employee ID.' };
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

    const expectedPassword = (foundUser.password || (foundUser.mustChangePassword ? 'ncst123' : 'password123')).trim();
    if (cleanPass !== expectedPassword) {
      sounds.playError();
      return { success: false, message: 'Invalid password. Please verify your credentials.' };
    }

    currentUser.value = foundUser;
    const token = `sess_${foundUser.role}_${foundUser.id}`;
    sessionToken.value = token;
    localStorage.setItem(STORAGE_KEYS.CURRENT_USER, JSON.stringify(foundUser));
    localStorage.setItem(STORAGE_KEYS.SESSION_TOKEN, token);
    validateAndSyncSession();
    fetchStateFromServer();
    sounds.playSuccess();
    return { success: true, message: `Welcome back, ${foundUser.name}!`, user: foundUser };
  };

  /**
   * Authoritative Student Record Lookup for Student Portal Registration
   * Retrieves official name, department, course, year level, and section using complete Student ID.
   */
  const lookupStudentRecord = async (rawStudentId: string): Promise<{
    found: boolean;
    hasExistingAccount: boolean;
    student?: StudentRecord;
    message: string;
  }> => {
    const clean = normalizeId(rawStudentId).trim();
    if (!clean) {
      return { found: false, hasExistingAccount: false, message: 'Student ID Number is required.' };
    }

    // 1. Check if an account already exists locally or in users
    const existingUser = users.value.find(
      u => u.studentId && normalizeId(u.studentId).toLowerCase() === clean.toLowerCase()
    );

    if (existingUser) {
      return {
        found: true,
        hasExistingAccount: true,
        message: existingUser.mustChangePassword
          ? `An account for Student ID ${clean} was automatically created during ticket purchase. Please sign in using default password "ncst123" to set your password.`
          : `An account is already registered for Student ID ${clean}. Please log in or use Forgot Password.`,
      };
    }

    // 2. Query authoritative backend endpoint
    try {
      const res = await fetch('/api/lookup-student', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ studentId: clean }),
      });
      if (res.ok) {
        const data = await res.json();
        if (data && data.success) {
          return {
            found: data.found,
            hasExistingAccount: data.hasExistingAccount,
            student: data.student,
            message: data.message,
          };
        }
      }
    } catch {}

    // 3. Fallback to client-synchronized studentRecords
    const record = studentRecords.value.find(
      r => normalizeId(r.studentId).toLowerCase() === clean.toLowerCase()
    );

    if (!record) {
      return {
        found: false,
        hasExistingAccount: false,
        message: 'Student ID not found in the official institutional roster.',
      };
    }

    return {
      found: true,
      hasExistingAccount: false,
      student: {
        ...record,
      },
      message: 'Student Record Found',
    };
  };

  const sendStudentVerificationCode = async (rawStudentId: string): Promise<{
    success: boolean;
    maskedEmail?: string;
    demoCode?: string;
    message: string;
  }> => {
    const clean = normalizeId(rawStudentId);
    if (!clean) {
      return { success: false, message: 'Student ID is required.' };
    }
    return {
      success: true,
      demoCode: '123456',
      message: 'Verification completed.',
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
    email?: string;
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
    const cleanEmail = normalizeEmail(data.email || (cleanStudentId ? `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph` : ''));
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
          message: `An account for Student ID ${cleanStudentId} was automatically created during ticket purchase. Please sign in with your Student ID and default password (ncst123) to set your permanent password.`,
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
          section: data.section || 'A1',
          department: data.department || 'CSD',
          email: cleanEmail,
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
    const finalSection = isAuthoritative ? (matchedStudentRecord.section || 'A1') : (data.section || 'A1');
    const finalDept = isAuthoritative ? (matchedStudentRecord.department || 'CSD') : (data.department || 'CSD');
    const finalEmail = cleanEmail || matchedStudentRecord.email || `${cleanStudentId.toLowerCase()}@student.ncst.edu.ph`;

    // Primary Identity Check across ALL roles and ALL statuses (Active & Inactive)
    const conflict = checkUserIdentityConflict({
      name: finalName,
      email: finalEmail,
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
      email: finalEmail,
      role: 'student',
      password: data.password,
      studentId: cleanStudentId,
      department: finalDept,
      course: finalCourse,
      yearLevel: finalYear,
      section: finalSection,
      isActive: true,
      verificationStatus: isAuthoritative ? 'verified' : 'pending',
      isVerified: isAuthoritative,
      createdAt: new Date().toISOString(),
    };

    // Save/link student record with two-way field synchronization
    matchedStudentRecord.userId = newUser.id;
    matchedStudentRecord.email = finalEmail;

    // Automatically bind all existing tickets issued before account creation to this newly registered account
    tickets.value = tickets.value.map(t => {
      if (t.attendeeStudentId && normalizeId(t.attendeeStudentId).toLowerCase() === cleanStudentId.toLowerCase()) {
        return {
          ...t,
          currentAttendeeId: newUser.id,
          originalOwnerId: (t.originalOwnerId && (t.originalOwnerId.startsWith('rec-') || t.originalOwnerId.startsWith('usr-guest-') || t.originalOwnerId === t.attendeeStudentId))
            ? newUser.id
            : (t.originalOwnerId || newUser.id),
          attendeeEmail: cleanEmail,
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
          userEmail: cleanEmail,
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
      const res = await fetch('/api/register', {
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

  const resetPassword = (studentIdOrIdentifier: string, newPasswordParam: string, _legacyParam?: string): { success: boolean; message: string; user?: User } => {
    const rawIdentifier = studentIdOrIdentifier.trim();
    const newPassword = (_legacyParam !== undefined ? _legacyParam : newPasswordParam).trim();

    if (!rawIdentifier) {
      sounds.playError();
      return { success: false, message: 'Student ID Number is required for password reset.' };
    }

    const cleanId = normalizeId(rawIdentifier).toLowerCase();
    const targetStudent = users.value.find(u => {
      if (u.role !== 'student') return false;
      const idMatch = u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId;
      const emailMatch = u.email && normalizeEmail(u.email) === cleanId;
      const nameMatch = u.name && u.name.trim().toLowerCase() === cleanId;
      return idMatch || emailMatch || nameMatch;
    });

    if (!targetStudent) {
      // Check if user is a privileged account
      const privilegedUser = users.value.find(u => {
        const idMatch = (u.employeeId && normalizeId(u.employeeId).toLowerCase() === cleanId) ||
                        (u.studentId && normalizeId(u.studentId).toLowerCase() === cleanId);
        const emailMatch = u.email && normalizeEmail(u.email) === cleanId;
        return idMatch || emailMatch;
      });

      sounds.playError();
      if (privilegedUser && privilegedUser.role !== 'student') {
        return {
          success: false,
          message: 'Privileged accounts (Faculty, Staff, Cashier, Admin) cannot be reset self-service. Please contact the NCST IT Administrator for assistance.'
        };
      }
      return { success: false, message: 'No registered student account found matching this Student ID Number.' };
    }

    if (targetStudent.isActive === false) {
      sounds.playError();
      return { success: false, message: 'This student account is deactivated. Please contact the Administrator.' };
    }

    if (newPassword.length < 4) {
      sounds.playError();
      return { success: false, message: 'Password must be at least 4 characters long.' };
    }

    const idx = users.value.findIndex(u => u.id === targetStudent.id);
    if (idx !== -1) {
      const updated = {
        ...users.value[idx],
        password: newPassword,
        mustChangePassword: false,
        temporaryPassword: undefined,
      };
      users.value[idx] = updated;
      if (currentUser.value && currentUser.value.id === targetStudent.id) {
        currentUser.value = updated;
      }
    }

    sounds.playSuccess();
    return {
      success: true,
      message: `Password for ${targetStudent.name} (${targetStudent.studentId}) was successfully reset! You can now sign in.`,
      user: users.value[idx],
    };
  };

  const updateUserPassword = (userId: string, newPassword: string): { success: boolean; message: string; user?: User } => {
    const idx = users.value.findIndex(u => u.id === userId);
    if (idx === -1) {
      sounds.playError();
      return { success: false, message: 'User account not found.' };
    }
    if (newPassword.length < 4) {
      sounds.playError();
      return { success: false, message: 'Password must be at least 4 characters long.' };
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
    }
    sounds.playSuccess();
    return { success: true, message: 'Password updated successfully!', user: updated };
  };

  const adminResetUserPassword = (targetUserId: string, newPass: string): { success: boolean; message: string; user?: User } => {
    const target = users.value.find(u => u.id === targetUserId);
    if (!target) {
      sounds.playError();
      return { success: false, message: 'Target user account not found.' };
    }
    if (newPass.length < 4) {
      sounds.playError();
      return { success: false, message: 'Password must be at least 4 characters long.' };
    }
    const idx = users.value.findIndex(u => u.id === target.id);
    if (idx !== -1) {
      users.value[idx] = { ...users.value[idx], password: newPass };
      if (currentUser.value && currentUser.value.id === target.id) {
        currentUser.value = { ...currentUser.value, password: newPass };
      }
    }
    sounds.playSuccess();
    return {
      success: true,
      message: `Password for ${target.name} (${target.role.toUpperCase()}) was successfully reset by Administrator.`,
      user: target,
    };
  };

  const importStudents = (studentsList: Array<{
    name: string;
    studentId?: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    email?: string;
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
        email: st.email?.trim() || existingRec?.email,
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
    email?: string;
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
    const cleanEmail = (data.email || `${cleanGuestRef.toLowerCase()}@guest.external`).trim().toLowerCase();
    const eventIds = data.assignedEventIds || (data.eventId ? [data.eventId] : []);

    const parsedName = splitFullName(cleanName);
    const newGuestUser: User = {
      id: `usr-guest-${Date.now()}`,
      name: cleanName,
      firstName: parsedName.firstName,
      middleName: parsedName.middleName,
      lastName: parsedName.lastName,
      email: cleanEmail,
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
    localStorage.removeItem(STORAGE_KEYS.USERS);
    localStorage.removeItem(STORAGE_KEYS.STUDENT_RECORDS);
    localStorage.removeItem(STORAGE_KEYS.EVENTS);
    localStorage.removeItem(STORAGE_KEYS.TICKETS);
    localStorage.removeItem(STORAGE_KEYS.LOGS);
    localStorage.removeItem(STORAGE_KEYS.TRANSACTIONS);
    localStorage.removeItem(STORAGE_KEYS.CURRENT_USER);
    localStorage.removeItem(STORAGE_KEYS.CURRENT_EVENT);
    users.value = INITIAL_USERS;
    studentRecords.value = [...INITIAL_STUDENT_RECORDS];
    events.value = INITIAL_EVENTS;
    tickets.value = INITIAL_TICKETS;
    logs.value = INITIAL_LOGS;
    transactions.value = INITIAL_TRANSACTIONS;
    currentUser.value = null;
    currentEvent.value = INITIAL_EVENTS[0];
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

  return {
    currentUser,
    setCurrentUser,
    isAuthenticated,
    login,
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
    lookupTicketInfo,
    addUser,
    updateUser,
    reactivateUser,
    deleteUser,
    adminResetUserPassword,
    updateUserPassword,
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

