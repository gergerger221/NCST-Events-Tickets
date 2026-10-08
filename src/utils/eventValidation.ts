/**
 * Event Validation and Time Standardization Engine
 * Implements strict Philippine Time (PHT / UTC+8) and institutional NCST business rules.
 */

import { EventItem, EventStatus, User } from '../types';
import { getPhilippineDateString, isPastPST } from './philippineTime';

export interface EventFormData {
  id?: string;
  title: string;
  code?: string;
  description?: string;
  category: string;
  venue: string;
  date: string;
  startTime: string; // Display format: "08:00 AM" or 24h "08:00"
  endTime: string;   // Display format: "05:00 PM" or 24h "17:00"
  capacity: number | string;
  ticketPrice: number | string;
  isFreeAdmission?: boolean;
  status: EventStatus;
  organizer?: string;
  bannerUrl?: string;
  ticketDesignUrl?: string;
  ticketDesignOffsetY?: number;
  assignedStaffIds?: string[];
}

export interface ValidationResult {
  isValid: boolean;
  errors: Record<string, string>;
  warnings: Record<string, string>;
  errorCount: number;
  errorSummary: string;
}

/**
 * Valid event categories
 */
export const VALID_EVENT_CATEGORIES = [
  'Institutional',
  'Foundation',
  'Concert',
  'Battle of the bands',
  'Academic',
  'Technology',
  'Sports',
  'Cultural',
] as const;

/**
 * Normalizes 12-hour or 24-hour time string to 24-hour format "HH:mm:ss"
 * Accepts: "08:00 AM", "8:00 AM", "5:00 PM", "17:00", "17:00:00"
 * Returns null if invalid or incomplete (e.g. "5", "500", "25:00")
 */
export function parseTimeTo24Hour(timeStr: string): string | null {
  if (!timeStr || typeof timeStr !== 'string') return null;
  const trimmed = timeStr.trim().toUpperCase();
  if (!trimmed) return null;

  // Pattern 1: 12-hour AM/PM (e.g., "05:00 PM", "8:30 AM", "12:00 PM")
  const match12 = trimmed.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?\s*(AM|PM)$/);
  if (match12) {
    let hour = parseInt(match12[1], 10);
    const minute = parseInt(match12[2], 10);
    const second = match12[3] ? parseInt(match12[3], 10) : 0;
    const period = match12[4];

    if (hour < 1 || hour > 12) return null;
    if (minute < 0 || minute > 59) return null;
    if (second < 0 || second > 59) return null;

    if (period === 'AM') {
      if (hour === 12) hour = 0;
    } else if (period === 'PM') {
      if (hour !== 12) hour += 12;
    }

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:${String(second).padStart(2, '0')}`;
  }

  // Pattern 2: 24-hour (e.g., "17:00", "08:30", "17:00:00")
  const match24 = trimmed.match(/^(\d{1,2}):(\d{2})(?::(\d{2}))?$/);
  if (match24) {
    const hour = parseInt(match24[1], 10);
    const minute = parseInt(match24[2], 10);
    const second = match24[3] ? parseInt(match24[3], 10) : 0;

    if (hour < 0 || hour > 23) return null;
    if (minute < 0 || minute > 59) return null;
    if (second < 0 || second > 59) return null;

    return `${String(hour).padStart(2, '0')}:${String(minute).padStart(2, '0')}:${String(second).padStart(2, '0')}`;
  }

  return null;
}

/**
 * Converts any valid time string into standard 12-hour display format "hh:mm A"
 * e.g. "17:00" -> "05:00 PM", "08:00 AM" -> "08:00 AM"
 */
export function normalizeTimeToDisplay(timeStr: string): string {
  const time24 = parseTimeTo24Hour(timeStr);
  if (!time24) return timeStr;

  const [hStr, mStr] = time24.split(':');
  let hour = parseInt(hStr, 10);
  const minute = mStr;
  const period = hour >= 12 ? 'PM' : 'AM';

  if (hour === 0) {
    hour = 12;
  } else if (hour > 12) {
    hour -= 12;
  }

  return `${String(hour).padStart(2, '0')}:${minute} ${period}`;
}

/**
 * Converts 12-hour display string ("08:00 AM") to HTML time input value ("08:00")
 */
export function timeDisplayToInputTime(timeStr: string): string {
  const time24 = parseTimeTo24Hour(timeStr);
  if (!time24) return '08:00';
  const parts = time24.split(':');
  return `${parts[0]}:${parts[1]}`;
}

/**
 * Converts HTML time input value ("17:00") to 12-hour display string ("05:00 PM")
 */
export function inputTimeToDisplay(inputVal: string): string {
  if (!inputVal) return '';
  return normalizeTimeToDisplay(inputVal);
}

/**
 * Compares two time strings.
 * Returns:
 * < 0 if timeA < timeB
 *   0 if timeA === timeB
 * > 0 if timeA > timeB
 * null if either time string is invalid
 */
export function compareTimes(timeA: string, timeB: string): number | null {
  const tA = parseTimeTo24Hour(timeA);
  const tB = parseTimeTo24Hour(timeB);
  if (!tA || !tB) return null;
  return tA.localeCompare(tB);
}

/**
 * Check if the combined event Date + Time is in the past relative to Philippine Time
 */
export function isEventDateTimeInPast(dateStr: string, timeStr: string): boolean {
  if (!dateStr) return false;
  const todayPst = getPhilippineDateString(new Date());

  if (dateStr < todayPst) return true;
  if (dateStr > todayPst) return false;

  // Same date (today) -> compare current Philippine time
  const time24 = parseTimeTo24Hour(timeStr);
  if (!time24) return false;

  const now = new Date();
  const currentPstTime24 = new Intl.DateTimeFormat('en-US', {
    timeZone: 'Asia/Manila',
    hour: '2-digit',
    minute: '2-digit',
    second: '2-digit',
    hour12: false,
  }).format(now);

  return time24 < currentPstTime24;
}

/**
 * Checks for potential duplicate or conflicting events
 */
export function checkDuplicateEvent(
  form: EventFormData,
  existingEvents: EventItem[]
): { exactDuplicate: boolean; similarWarning: boolean; conflictingEvent?: EventItem } {
  const cleanTitle = (form.title || '').trim().toLowerCase();
  const cleanDate = (form.date || '').trim();
  const cleanVenue = (form.venue || '').trim().toLowerCase();
  const currentId = form.id;

  if (!cleanTitle || !cleanDate) {
    return { exactDuplicate: false, similarWarning: false };
  }

  for (const ev of existingEvents) {
    if (currentId && ev.id === currentId) continue;

    const evTitle = (ev.title || '').trim().toLowerCase();
    const evDate = (ev.date || '').trim();
    const evVenue = (ev.venue || '').trim().toLowerCase();

    // Exact duplicate: Same title on the same date
    if (evTitle === cleanTitle && evDate === cleanDate) {
      return { exactDuplicate: true, similarWarning: true, conflictingEvent: ev };
    }

    // Similar warning: Similar name and same venue on the same date
    const isSimilar = (evTitle.includes(cleanTitle) || cleanTitle.includes(evTitle)) && cleanTitle.length >= 4;
    if (isSimilar && evDate === cleanDate && evVenue === cleanVenue) {
      return { exactDuplicate: false, similarWarning: true, conflictingEvent: ev };
    }
  }

  return { exactDuplicate: false, similarWarning: false };
}

/**
 * Master Event Validator for NCST Events & Tickets
 */
export function validateEvent(
  form: EventFormData,
  options?: {
    isEditing?: boolean;
    existingEvents?: EventItem[];
    staffUsers?: User[];
  }
): ValidationResult {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};
  const isEditing = options?.isEditing ?? !!form.id;
  const existingEvents = options?.existingEvents || [];
  const staffUsers = options?.staffUsers || [];

  // ==================== 1. EVENT NAME ====================
  const trimmedTitle = (form.title || '').trim();
  if (!trimmedTitle) {
    errors.title = 'Event name is required.';
  } else if (trimmedTitle.length < 3) {
    errors.title = 'Event name must be at least 3 characters.';
  } else if (trimmedTitle.length > 150) {
    errors.title = 'Event name cannot exceed 150 characters.';
  }

  // Duplicate Check
  if (trimmedTitle && form.date) {
    const dupCheck = checkDuplicateEvent(form, existingEvents);
    if (dupCheck.exactDuplicate) {
      errors.title = 'An event with this name already exists on this date.';
    } else if (dupCheck.similarWarning) {
      warnings.duplicate = `A similar event ("${dupCheck.conflictingEvent?.title}") already exists on this date and venue. Please review before proceeding.`;
    }
  }

  // ==================== 2. EVENT CATEGORY ====================
  const trimmedCategory = (form.category || '').trim();
  if (!trimmedCategory || trimmedCategory.toLowerCase() === 'select category' || trimmedCategory === 'all') {
    errors.category = 'Please select an event category.';
  }

  // ==================== 3. EVENT DATE ====================
  const trimmedDate = (form.date || '').trim();
  const dateRegex = /^\d{4}-\d{2}-\d{2}$/;
  if (!trimmedDate || !dateRegex.test(trimmedDate)) {
    errors.date = 'Please enter a valid event date.';
  } else {
    const [y, m, d] = trimmedDate.split('-').map(Number);
    const dateObj = new Date(y, m - 1, d);
    if (isNaN(dateObj.getTime()) || dateObj.getMonth() !== m - 1 || dateObj.getDate() !== d) {
      errors.date = 'Please enter a valid event date.';
    } else if (!isEditing && form.status === 'upcoming' && isPastPST(trimmedDate)) {
      errors.date = 'Event date cannot be in the past.';
    }
  }

  // ==================== 4. START TIME ====================
  const parsedStartTime = parseTimeTo24Hour(form.startTime);
  if (!form.startTime || !form.startTime.trim()) {
    errors.startTime = 'Start time is required.';
  } else if (!parsedStartTime) {
    errors.startTime = 'Please enter a complete valid start time, e.g. 08:00 AM.';
  }

  // ==================== 5. END TIME ====================
  const parsedEndTime = parseTimeTo24Hour(form.endTime);
  if (!form.endTime || !form.endTime.trim()) {
    errors.endTime = 'End time is required.';
  } else if (!parsedEndTime) {
    errors.endTime = 'Please enter a complete valid end time, e.g. 05:00 PM.';
  }

  // ==================== 6. START TIME MUST BE BEFORE END TIME ====================
  if (parsedStartTime && parsedEndTime) {
    const timeDiff = compareTimes(form.startTime, form.endTime);
    if (timeDiff !== null && timeDiff >= 0) {
      errors.endTime = 'End time must be later than start time.';
      errors.timeOrder = 'End time must be later than start time.';
    }
  }

  // ==================== 8. VENUE ====================
  const trimmedVenue = (form.venue || '').trim();
  if (!trimmedVenue) {
    errors.venue = 'Venue is required.';
  } else if (trimmedVenue.length < 3) {
    errors.venue = 'Venue must be at least 3 characters.';
  } else if (trimmedVenue.length > 200) {
    errors.venue = 'Venue cannot exceed 200 characters.';
  }

  // ==================== 10 & 11. TICKET PRICE & FREE ADMISSION ====================
  if (form.isFreeAdmission) {
    // Ticket price is automatically 0
    if (Number(form.ticketPrice) !== 0) {
      errors.ticketPrice = 'Free admission events must have a ticket price of ₱0.00.';
    }
  } else {
    const priceNum = Number(form.ticketPrice);
    if (form.ticketPrice === '' || form.ticketPrice === null || form.ticketPrice === undefined || isNaN(priceNum)) {
      errors.ticketPrice = 'Ticket price is required.';
    } else if (priceNum < 0) {
      errors.ticketPrice = 'Ticket price cannot be negative.';
    } else if (priceNum > 50000) {
      errors.ticketPrice = 'Ticket price cannot exceed ₱50,000.';
    }
  }

  // ==================== 15. STAFF ASSIGNMENTS ====================
  if (form.assignedStaffIds && form.assignedStaffIds.length > 0 && staffUsers.length > 0) {
    const seenStaff = new Set<string>();
    for (const sId of form.assignedStaffIds) {
      if (seenStaff.has(sId)) {
        errors.staff = 'Duplicate staff assignments detected.';
        break;
      }
      seenStaff.add(sId);

      const staffAccount = staffUsers.find(u => u.id === sId);
      if (staffAccount) {
        if (staffAccount.role !== 'event_staff') {
          errors.staff = `User "${staffAccount.name}" is not an Event Staff member.`;
          break;
        }
        if (staffAccount.isActive === false) {
          errors.staff = `Event Staff "${staffAccount.name}" account is deactivated.`;
          break;
        }
      }
    }
  }

  const errorKeys = Object.keys(errors);
  const errorCount = errorKeys.length;
  const errorSummary = errorCount > 0
    ? `Please correct ${errorCount} field${errorCount === 1 ? '' : 's'} before creating the event.`
    : '';

  return {
    isValid: errorCount === 0,
    errors,
    warnings,
    errorCount,
    errorSummary,
  };
}
