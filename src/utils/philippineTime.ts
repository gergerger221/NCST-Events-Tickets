/**
 * Philippine Time (PHT / Asia/Manila - UTC+8) Utility Module
 * Guarantees accurate date/time calculations, calendar mapping, and real-time clock across all system hierarchies.
 */

import { ref, onMounted, onUnmounted, computed } from 'vue';

export const PHILIPPINE_TIMEZONE = 'Asia/Manila';
export const PHILIPPINE_TZ_OFFSET_HOURS = 8; // UTC+8

/**
 * Returns current timestamp formatted in Asia/Manila timezone
 */
export function getPhilippineNow(): Date {
  // Create Date object representing current UTC time
  const now = new Date();
  return now;
}

/**
 * Extracts YYYY-MM-DD in Philippine timezone
 */
export type ParsedDateResult =
  | { kind: 'date'; value: Date }
  | { kind: 'time_only'; value: string }
  | null;

/**
 * Safely parses any date/time input into a validated Date object, a preformatted time-only marker, or null.
 * Never throws RangeError: Invalid time value.
 */
export function parsePhilippineDateInput(date?: Date | string | number | null): ParsedDateResult {
  if (date === null || date === undefined || date === '' || date === '---' || date === '—' || date === 'N/A') {
    return null;
  }
  if (date instanceof Date) {
    return isNaN(date.getTime()) ? null : { kind: 'date', value: date };
  }
  if (typeof date === 'number') {
    if (!Number.isFinite(date)) return null;
    const d = new Date(date);
    return isNaN(d.getTime()) ? null : { kind: 'date', value: d };
  }
  if (typeof date !== 'string') return null;

  const s = date.trim();
  if (!s || s === 'null' || s === 'undefined' || s === 'Invalid Date') return null;

  // 12-hour or 24-hour time-only string (e.g. '08:48 AM', '8:48 AM', '08:48:46 AM', '14:30', '14:30:00')
  if (/^\d{1,2}:\d{2}(?::\d{2})?\s*(?:AM|PM)?$/i.test(s)) {
    return { kind: 'time_only', value: s.toUpperCase() };
  }

  // Date-only string 'YYYY-MM-DD'
  if (/^\d{4}-\d{2}-\d{2}$/.test(s)) {
    const [y, m, d] = s.split('-').map(Number);
    const dateObj = new Date(Date.UTC(y, m - 1, d, 12, 0, 0));
    return isNaN(dateObj.getTime()) ? null : { kind: 'date', value: dateObj };
  }

  // SQL datetime without timezone (e.g. 'YYYY-MM-DD HH:mm:ss' or 'YYYY-MM-DDTHH:mm:ss')
  const sqlMatch = s.match(/^(\d{4}-\d{2}-\d{2})[ T](\d{2}:\d{2}:\d{2})(?:\.\d+)?$/);
  if (sqlMatch) {
    // Treat explicitly as Asia/Manila (UTC+8)
    const isoWithTz = `${sqlMatch[1]}T${sqlMatch[2]}+08:00`;
    const d = new Date(isoWithTz);
    if (!isNaN(d.getTime())) return { kind: 'date', value: d };
  }

  // General ISO or RFC strings
  const d = new Date(s);
  return isNaN(d.getTime()) ? null : { kind: 'date', value: d };
}

export function getPhilippineDateString(date: Date | string | number = new Date()): string {
  const parsed = parsePhilippineDateInput(date);
  if (!parsed || parsed.kind !== 'date') return '';
  try {
    return new Intl.DateTimeFormat('en-CA', {
      timeZone: PHILIPPINE_TIMEZONE,
      year: 'numeric',
      month: '2-digit',
      day: '2-digit',
    }).format(parsed.value); // returns 'YYYY-MM-DD'
  } catch {
    return '';
  }
}

/**
 * Extracts year, month (0-11), day, hour, minute, second in Asia/Manila
 */
export function getPhilippineParts(date: Date | string | number = new Date()) {
  const parsed = parsePhilippineDateInput(date);
  const d = parsed && parsed.kind === 'date' ? parsed.value : new Date();

  try {
    const formatter = new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      year: 'numeric',
      month: 'numeric',
      day: 'numeric',
      hour: 'numeric',
      minute: 'numeric',
      second: 'numeric',
      hour12: false,
      weekday: 'short',
    });

    const parts = formatter.formatToParts(d);
    const map: Record<string, string> = {};
    for (const part of parts) {
      if (part.type !== 'literal') {
        map[part.type] = part.value;
      }
    }

    return {
      year: parseInt(map.year || '2026', 10),
      month: parseInt(map.month || '1', 10) - 1, // 0-indexed month
      day: parseInt(map.day || '1', 10),
      hour: parseInt(map.hour || '0', 10),
      minute: parseInt(map.minute || '0', 10),
      second: parseInt(map.second || '0', 10),
      weekday: map.weekday || 'Sun',
    };
  } catch {
    return {
      year: 2026,
      month: 0,
      day: 1,
      hour: 0,
      minute: 0,
      second: 0,
      weekday: 'Sun',
    };
  }
}

/**
 * Format full Philippine date and time with PHT indicator
 * Example: "Thursday, October 1, 2026 at 09:45 AM PHT"
 */
export function formatPhilippineFull(date?: Date | string | number | null): string {
  const parsed = parsePhilippineDateInput(date);
  if (!parsed) return '—';
  if (parsed.kind === 'time_only') {
    return `${parsed.value} PHT`;
  }
  try {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      weekday: 'long',
      year: 'numeric',
      month: 'long',
      day: 'numeric',
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
    }).format(parsed.value);
  } catch {
    return '—';
  }
}

/**
 * Format date in friendly Philippine format
 * Example: "October 5, 2026"
 */
export function formatPhilippineDate(date?: Date | string | number | null): string {
  const parsed = parsePhilippineDateInput(date);
  if (!parsed || parsed.kind !== 'date') return '—';
  try {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(parsed.value);
  } catch {
    return '—';
  }
}

/**
 * Format time in Philippine 12-hour AM/PM format
 */
export function formatPhilippineTime(date?: Date | string | number | null): string {
  const parsed = parsePhilippineDateInput(date);
  if (!parsed) return '—';
  if (parsed.kind === 'time_only') {
    return parsed.value;
  }
  try {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      hour: '2-digit',
      minute: '2-digit',
      hour12: true,
    }).format(parsed.value);
  } catch {
    return '—';
  }
}

/**
 * Checks if a given YYYY-MM-DD event date is TODAY in Philippine Time (PHT)
 */
export function isTodayPHT(dateStr: string): boolean {
  if (!dateStr) return false;
  const todayPHT = getPhilippineDateString(new Date());
  return dateStr.trim() === todayPHT;
}

/**
 * Checks if a given YYYY-MM-DD event date is in the PAST relative to Philippine Time (PHT)
 */
export function isPastPHT(dateStr: string): boolean {
  if (!dateStr) return false;
  const todayPHT = getPhilippineDateString(new Date());
  return dateStr.trim() < todayPHT;
}

/**
 * Checks if a given YYYY-MM-DD event date is in the FUTURE relative to Philippine Time (PHT)
 */
export function isUpcomingPHT(dateStr: string): boolean {
  if (!dateStr) return false;
  const todayPHT = getPhilippineDateString(new Date());
  return dateStr.trim() > todayPHT;
}

// Backward compatibility aliases
export const isTodayPST = isTodayPHT;
export const isPastPST = isPastPHT;
export const isUpcomingPST = isUpcomingPHT;

/**
 * Reactive Composable for Real-time Philippine Standard Time Clock
 */
export function usePhilippineClock() {
  const now = ref(new Date());
  let timer: any = null;

  onMounted(() => {
    timer = setInterval(() => {
      now.value = new Date();
    }, 1000);
  });

  onUnmounted(() => {
    if (timer !== null) {
      clearInterval(timer);
    }
  });

  const timeString = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
    }).format(now.value);
  });

  const dateString = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      weekday: 'short',
      month: 'short',
      day: 'numeric',
      year: 'numeric',
    }).format(now.value);
  });

  const fullDateString = computed(() => {
    return new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      weekday: 'long',
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(now.value);
  });

  const todayIso = computed(() => getPhilippineDateString(now.value));

  return {
    now,
    timeString,
    dateString,
    fullDateString,
    todayIso,
    timezone: PHILIPPINE_TIMEZONE,
    tzLabel: 'PHT (UTC+8)',
  };
}

/**
 * Calculates the exact expiration cutoff timestamp for an event in Philippine Time (Asia/Manila, UTC+8).
 * Per institutional policy:
 * Expiration = Day immediately following the event date at exactly 12:00:00 AM PHT (00:00:00).
 * E.g., Event date: November 5, 2026 -> Expiration: November 6, 2026 at 12:00:00 AM PHT (UTC 2026-11-05 16:00:00).
 */
export function getEventExpirationCutoff(eventDate: string): Date | null {
  if (!eventDate || !/^\d{4}-\d{2}-\d{2}$/.test(eventDate.trim())) {
    return null;
  }
  const [y, m, d] = eventDate.trim().split('-').map(Number);
  // Next calendar day at 00:00:00 PHT = 16:00:00 UTC of current calendar day (or d+1 at 00:00 PHT)
  return new Date(Date.UTC(y, m - 1, d + 1, 0 - PHILIPPINE_TZ_OFFSET_HOURS, 0, 0, 0));
}

/**
 * Checks if a user object is a temporary Outsider / Guest account
 */
export function isOutsiderUser(user: any): boolean {
  if (!user) return false;
  if (user.isOutsider === true) return true;
  if (user.attendeeType === 'OUTSIDER') return true;
  if (user.role === 'outsider') return true;
  if (typeof user.id === 'string' && user.id.startsWith('usr-guest-')) return true;
  if (typeof user.studentId === 'string' && (user.studentId.startsWith('GUEST-') || user.studentId.startsWith('TMP-'))) return true;
  return false;
}

/**
 * Resolves all associated events for a given temporary outsider account and finds the latest expiration date.
 * If associated with multiple events, expiration is strictly based on the LATEST event,
 * guaranteeing the account remains active while any associated event is still ongoing/valid.
 */
export function getOutsiderAccountExpirationDate(
  user: any,
  events: any[] = [],
  tickets: any[] = []
): { expirationDate: Date | null; latestEvent: any | null; associatedEvents: any[] } {
  if (!user) {
    return { expirationDate: null, latestEvent: null, associatedEvents: [] };
  }

  // 1. Collect candidate event IDs associated with this user
  const eventIds = new Set<string>();

  if (user.eventId) {
    eventIds.add(user.eventId);
  }
  if (Array.isArray(user.assignedEventIds)) {
    user.assignedEventIds.forEach((eid: string) => {
      if (eid) eventIds.add(eid);
    });
  }

  // Check tickets owned or held by this user
  const userId = user.id;
  const userGuestId = (user.studentId || '').trim();

  tickets.forEach(t => {
    const isOwner = t.originalOwnerId === userId || t.currentAttendeeId === userId;
    const isGuestRefMatch = userGuestId && (t.attendeeStudentId === userGuestId || t.outsiderId === userGuestId);
    
    if ((isOwner || isGuestRefMatch) && t.eventId) {
      eventIds.add(t.eventId);
    }
  });

  // 2. Resolve EventItem entities
  const associatedEvents = events.filter(e => eventIds.has(e.id));

  if (associatedEvents.length === 0) {
    // If no explicit events found but user has direct expiresAt, use that as fallback
    if (user.expiresAt) {
      const parsed = new Date(user.expiresAt);
      if (!isNaN(parsed.getTime())) {
        return { expirationDate: parsed, latestEvent: null, associatedEvents: [] };
      }
    }
    return { expirationDate: null, latestEvent: null, associatedEvents: [] };
  }

  // 3. Find the event with the latest expiration cutoff
  let latestCutoff: Date | null = null;
  let latestEvent: any | null = null;

  associatedEvents.forEach(evt => {
    const cutoff = getEventExpirationCutoff(evt.date);
    if (cutoff) {
      if (!latestCutoff || cutoff.getTime() > latestCutoff.getTime()) {
        latestCutoff = cutoff;
        latestEvent = evt;
      }
    }
  });

  return { expirationDate: latestCutoff, latestEvent, associatedEvents };
}

/**
 * Checks whether an outsider account has reached or exceeded its automatic expiration time.
 */
export function isOutsiderAccountExpired(
  user: any,
  events: any[] = [],
  tickets: any[] = [],
  referenceNow: Date = new Date()
): boolean {
  if (!isOutsiderUser(user)) {
    return false;
  }

  const { expirationDate } = getOutsiderAccountExpirationDate(user, events, tickets);
  if (!expirationDate) {
    return false;
  }

  return referenceNow.getTime() >= expirationDate.getTime();
}

/**
 * Formats a Date object in full Philippine Time with PHT indicator for expiration notices.
 * Example: "November 6, 2026 at 12:00:00 AM PHT"
 */
export function formatPhilippineExpiration(date: Date | string | number): string {
  const parsed = parsePhilippineDateInput(date);
  if (!parsed || parsed.kind !== 'date') return '---';

  try {
    const datePart = new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      month: 'long',
      day: 'numeric',
      year: 'numeric',
    }).format(parsed.value);

    const timePart = new Intl.DateTimeFormat('en-US', {
      timeZone: PHILIPPINE_TIMEZONE,
      hour: '2-digit',
      minute: '2-digit',
      second: '2-digit',
      hour12: true,
    }).format(parsed.value);

    return `${datePart} at ${timePart} PHT`;
  } catch {
    return '---';
  }
}

