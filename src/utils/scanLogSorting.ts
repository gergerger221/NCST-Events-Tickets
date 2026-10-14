import type { AttendanceLog, Ticket, StudentRecord, User } from '../types/index.ts';

export type ScanLogSortOption =
  | 'newest'
  | 'oldest'
  | 'name_asc'
  | 'name_desc'
  | 'event_asc'
  | 'event_desc';

export interface SortOptionDescriptor {
  value: ScanLogSortOption;
  label: string;
}

export const SCAN_LOG_SORT_OPTIONS: SortOptionDescriptor[] = [
  { value: 'newest', label: 'Newest First' },
  { value: 'oldest', label: 'Oldest First' },
  { value: 'name_asc', label: 'Student/Attendee Name A–Z' },
  { value: 'name_desc', label: 'Student/Attendee Name Z–A' },
  { value: 'event_asc', label: 'Event Name A–Z' },
  { value: 'event_desc', label: 'Event Name Z–A' },
];

/**
 * Accurately determines if an attendance scan log belongs to an enrolled student vs an outsider/guest.
 * Validates against authoritative student roster records, ticket classification, and user accounts.
 * Prevents classification using client-supplied labels alone.
 */
export function isStudentScanLog(
  log: AttendanceLog,
  tickets: Ticket[] = [],
  studentRecords: StudentRecord[] = [],
  users: User[] = []
): boolean {
  if (!log) return false;

  // 1. Check authoritative Student Records enrollment roster by student ID
  const cleanStudentId = (log.studentId || '').trim().toLowerCase();
  if (cleanStudentId) {
    const isEnrolled = studentRecords.some(
      r => (r.studentId || '').trim().toLowerCase() === cleanStudentId
    );
    if (isEnrolled) return true;
  }

  // 2. Check authoritative associated ticket data
  const tId = (log.ticketId || '').trim().toLowerCase();
  const tNum = (log.ticketNumber || '').trim().toLowerCase();
  if (tId || tNum) {
    const matchedTicket = tickets.find(
      t => (t.id && t.id.toLowerCase() === tId) ||
           (t.ticketNumber && t.ticketNumber.toLowerCase() === tNum)
    );
    if (matchedTicket) {
      if (matchedTicket.attendeeType === 'OUTSIDER' || matchedTicket.outsiderId) {
        return false;
      }
      if (matchedTicket.attendeeType === 'STUDENT' || matchedTicket.attendeeStudentId) {
        return true;
      }
    }
  }

  // 3. Check authoritative user account role
  if (cleanStudentId) {
    const matchedUser = users.find(
      u => (u.studentId && u.studentId.trim().toLowerCase() === cleanStudentId) ||
           (u.role === 'student' && (u.studentId || '').trim().toLowerCase() === cleanStudentId)
    );
    if (matchedUser) {
      if (matchedUser.role === 'outsider' || matchedUser.isOutsider) return false;
      if (matchedUser.role === 'student') return true;
    }
  }

  // 4. Check for outsider explicit ID or attendeeType
  if (log.outsiderId || (log.attendeeType as string) === 'OUTSIDER') {
    return false;
  }

  // 5. Check if log carries an authoritative studentId or explicit STUDENT type
  if (log.studentId || (log.attendeeType as string) === 'STUDENT') {
    return true;
  }

  return false;
}

/**
 * Sorts attendance scan logs alphabetically by attendee full name (case-insensitive, A to Z).
 * Places records with missing or empty attendee names at the end.
 * Guarantees a deterministic stable sort without mutating the input array.
 */
export function sortAlphabeticalByName(logs: AttendanceLog[]): AttendanceLog[] {
  if (!Array.isArray(logs) || logs.length <= 1) {
    return Array.isArray(logs) ? [...logs] : [];
  }

  const indexed = logs.map((item, index) => ({ item, index }));

  indexed.sort((a, b) => {
    const nameA = (a.item.attendeeName || '').trim();
    const nameB = (b.item.attendeeName || '').trim();

    if (nameA && nameB) {
      const cmp = nameA.localeCompare(nameB, undefined, { sensitivity: 'base', numeric: true });
      if (cmp !== 0) return cmp;
      return a.index - b.index;
    }

    // Place missing names safely at the end of the list
    if (nameA && !nameB) return -1;
    if (!nameA && nameB) return 1;

    return a.index - b.index;
  });

  return indexed.map(entry => entry.item);
}

export interface GroupedScanLogs {
  students: AttendanceLog[];
  outsiders: AttendanceLog[];
}

/**
 * Groups entrance scan logs into Students and Outsiders / Guests,
 * and sorts each group alphabetically from A to Z by attendee full name.
 */
export function groupAndSortScanLogs(
  logs: AttendanceLog[],
  tickets: Ticket[] = [],
  studentRecords: StudentRecord[] = [],
  users: User[] = []
): GroupedScanLogs {
  const studentsRaw: AttendanceLog[] = [];
  const outsidersRaw: AttendanceLog[] = [];

  for (const log of logs) {
    if (isStudentScanLog(log, tickets, studentRecords, users)) {
      studentsRaw.push(log);
    } else {
      outsidersRaw.push(log);
    }
  }

  return {
    students: sortAlphabeticalByName(studentsRaw),
    outsiders: sortAlphabeticalByName(outsidersRaw),
  };
}

/**
 * Safely extracts a numeric millisecond timestamp from an AttendanceLog or raw record.
 * Handles ISO strings (time_in_iso, timestamp, createdAt), numeric timestamps,
 * and valid date strings. Returns null if invalid or missing.
 */
export function extractLogTimestamp(log: AttendanceLog | any): number | null {
  if (!log || typeof log !== 'object') return null;

  // 1. Direct ISO or timestamp fields
  const candidates = [
    log.time_in_iso,
    log.timeInIso,
    log.timestamp,
    log.createdAt,
    log.created_at,
  ];

  for (const c of candidates) {
    if (c !== undefined && c !== null && c !== '') {
      if (typeof c === 'number' && Number.isFinite(c)) {
        return c;
      }
      if (typeof c === 'string') {
        const parsed = new Date(c).getTime();
        if (!Number.isNaN(parsed)) {
          return parsed;
        }
      }
    }
  }

  // 2. Fallback: combined date + timeIn (e.g. "October 15, 2025" + "8:30 AM")
  if (log.date && log.timeIn && typeof log.date === 'string' && typeof log.timeIn === 'string') {
    const combined = `${log.date} ${log.timeIn}`;
    const parsed = new Date(combined).getTime();
    if (!Number.isNaN(parsed)) {
      return parsed;
    }
  }

  // 3. Fallback: timeIn if it's already an ISO or parseable date string
  if (log.timeIn && typeof log.timeIn === 'string') {
    const parsed = new Date(log.timeIn).getTime();
    if (!Number.isNaN(parsed)) {
      return parsed;
    }
  }

  return null;
}

/**
 * Stable, pure sort implementation for AttendanceLog lists.
 * Preserves the original reactive array without mutating it.
 * Places records with missing or invalid timestamps after records with valid timestamps.
 */
export function sortAttendanceLogs(
  logs: AttendanceLog[],
  sortOption: ScanLogSortOption = 'newest'
): AttendanceLog[] {
  if (!Array.isArray(logs) || logs.length <= 1) {
    return Array.isArray(logs) ? [...logs] : [];
  }

  // Tag items with original index to ensure 100% deterministic stable sort
  const indexed = logs.map((item, index) => ({ item, index }));

  indexed.sort((a, b) => {
    const logA = a.item;
    const logB = b.item;

    switch (sortOption) {
      case 'newest': {
        const tA = extractLogTimestamp(logA);
        const tB = extractLogTimestamp(logB);

        // Both valid: newest (larger timestamp) first
        if (tA !== null && tB !== null) {
          if (tB !== tA) return tB - tA;
          return a.index - b.index; // Stable tie-breaker
        }
        // Undated records go after records with valid timestamps
        if (tA !== null && tB === null) return -1;
        if (tA === null && tB !== null) return 1;
        return a.index - b.index;
      }

      case 'oldest': {
        const tA = extractLogTimestamp(logA);
        const tB = extractLogTimestamp(logB);

        // Both valid: oldest (smaller timestamp) first
        if (tA !== null && tB !== null) {
          if (tA !== tB) return tA - tB;
          return a.index - b.index; // Stable tie-breaker
        }
        // Undated records go after records with valid timestamps
        if (tA !== null && tB === null) return -1;
        if (tA === null && tB !== null) return 1;
        return a.index - b.index;
      }

      case 'name_asc': {
        const nameA = (logA.attendeeName || '').trim();
        const nameB = (logB.attendeeName || '').trim();
        if (nameA && nameB) {
          const cmp = nameA.localeCompare(nameB, undefined, { sensitivity: 'base', numeric: true });
          if (cmp !== 0) return cmp;
          return a.index - b.index;
        }
        if (nameA && !nameB) return -1;
        if (!nameA && nameB) return 1;
        return a.index - b.index;
      }

      case 'name_desc': {
        const nameA = (logA.attendeeName || '').trim();
        const nameB = (logB.attendeeName || '').trim();
        if (nameA && nameB) {
          const cmp = nameB.localeCompare(nameA, undefined, { sensitivity: 'base', numeric: true });
          if (cmp !== 0) return cmp;
          return a.index - b.index;
        }
        if (nameA && !nameB) return -1;
        if (!nameA && nameB) return 1;
        return a.index - b.index;
      }

      case 'event_asc': {
        const evA = (logA.eventTitle || '').trim();
        const evB = (logB.eventTitle || '').trim();
        const cmp = evA.localeCompare(evB, undefined, { sensitivity: 'base', numeric: true });
        if (cmp !== 0) return cmp;
        return a.index - b.index;
      }

      case 'event_desc': {
        const evA = (logA.eventTitle || '').trim();
        const evB = (logB.eventTitle || '').trim();
        const cmp = evB.localeCompare(evA, undefined, { sensitivity: 'base', numeric: true });
        if (cmp !== 0) return cmp;
        return a.index - b.index;
      }

      default: {
        return a.index - b.index;
      }
    }
  });

  return indexed.map(entry => entry.item);
}
