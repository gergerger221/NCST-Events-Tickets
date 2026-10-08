/**
 * POS & Pass Generator Validation Utility
 * Enforces NCST specific field formats and business rules for ticket cashiering.
 */

export interface PosValidationResult {
  isValid: boolean;
  error?: string;
  normalized?: string;
}

export interface PosCashValidationResult {
  isValid: boolean;
  cashReceivedNum: number;
  change: number;
  error?: string;
}

export const VALID_YEAR_LEVELS = ['1st Year', '2nd Year', '3rd Year', '4th Year'] as const;
export type ValidYearLevel = typeof VALID_YEAR_LEVELS[number];

/**
 * 1. STUDENT ID VALIDATION
 * Expected NCST format: YYYY-XXXXX (e.g. 2026-00125 or 2025-1234)
 */
export function validateStudentId(rawId: string): PosValidationResult {
  const normalized = (rawId || '').trim();
  if (!normalized) {
    return { isValid: false, error: 'Student ID is required.' };
  }

  // Check for forbidden characters (letters or special symbols other than hyphen)
  if (/[a-zA-Z]/.test(normalized)) {
    return {
      isValid: false,
      error: 'Student ID cannot contain letters. Use format YYYY-XXXXX (e.g. 2026-00125).',
    };
  }

  if (/[^0-9-]/.test(normalized)) {
    return {
      isValid: false,
      error: 'Student ID contains unsupported symbols. Allowed: numbers and hyphen only.',
    };
  }

  // Strict NCST pattern: 4 digits year, hyphen, 4 to 5 digits serial
  const ncstPattern = /^\d{4}-\d{4,5}$/;
  if (!ncstPattern.test(normalized)) {
    return {
      isValid: false,
      error: 'Invalid Student ID format. Expected format: YYYY-XXXXX (e.g. 2026-00125).',
    };
  }

  return { isValid: true, normalized };
}

/**
 * 2. YEAR LEVEL VALIDATION
 * Fixed academic selection only
 */
export function validateYearLevel(yearLevel: string): PosValidationResult {
  const trimmed = (yearLevel || '').trim();
  if (!trimmed) {
    return { isValid: false, error: 'Year level is required.' };
  }
  if (!VALID_YEAR_LEVELS.includes(trimmed as ValidYearLevel)) {
    return {
      isValid: false,
      error: `Invalid Year Level. Must be one of: ${VALID_YEAR_LEVELS.join(', ')}.`,
    };
  }
  return { isValid: true, normalized: trimmed };
}

/**
 * 3. SECTION VALIDATION
 * NCST sections are alphanumeric (e.g. 11M1, 21M2, 31M1, M1, A1, E1, Section A)
 * Must NOT be restricted to numbers-only.
 */
export function validateSection(section: string): PosValidationResult {
  const normalized = (section || '').trim().toUpperCase();
  if (!normalized) {
    return { isValid: false, error: 'Section is required.' };
  }

  // Reject invalid special characters (allow letters, numbers, spaces, hyphens)
  if (!/^[0-9A-Z\s-]{2,10}$/.test(normalized)) {
    return {
      isValid: false,
      error: 'Section must be 2-10 alphanumeric characters (e.g. 11M1, 21M2, M1).',
    };
  }

  return { isValid: true, normalized };
}

/**
 * 4. CASH RECEIVED & CHANGE VALIDATION
 * Monetary value >= Amount Due (for paid events).
 */
export function validateCashPayment(
  rawCash: string | number,
  amountDue: number
): PosCashValidationResult {
  const due = Math.max(0, Number(amountDue) || 0);

  // If event is free, cash received can be 0
  if (due === 0) {
    return {
      isValid: true,
      cashReceivedNum: 0,
      change: 0,
    };
  }

  if (rawCash === '' || rawCash === null || rawCash === undefined) {
    return {
      isValid: false,
      cashReceivedNum: 0,
      change: 0,
      error: `Cash Received is required. Amount due is ₱${due.toFixed(2)}.`,
    };
  }

  const strValue = String(rawCash).trim();
  // Reject letters or invalid characters (allow optional decimal point)
  if (!/^\d+(\.\d{1,2})?$/.test(strValue)) {
    return {
      isValid: false,
      cashReceivedNum: 0,
      change: 0,
      error: 'Please enter a valid numeric monetary amount (e.g. 150 or 150.00).',
    };
  }

  const cashNum = parseFloat(strValue);
  if (isNaN(cashNum) || cashNum < 0) {
    return {
      isValid: false,
      cashReceivedNum: 0,
      change: 0,
      error: 'Invalid cash amount entered.',
    };
  }

  if (cashNum < due) {
    const deficit = (due - cashNum).toFixed(2);
    return {
      isValid: false,
      cashReceivedNum: cashNum,
      change: 0,
      error: `Insufficient cash received. Short by ₱${deficit} (Amount due: ₱${due.toFixed(2)}).`,
    };
  }

  const change = Math.round((cashNum - due) * 100) / 100;
  return {
    isValid: true,
    cashReceivedNum: cashNum,
    change,
  };
}

/**
 * 5. PHONE NUMBER VALIDATION
 * Supported Philippine mobile numbers (e.g. 09XXXXXXXXX or +639XXXXXXXXX)
 */
export function validatePhoneNumber(rawPhone: string, isRequired = false): PosValidationResult {
  const trimmed = (rawPhone || '').trim();
  if (!trimmed) {
    if (isRequired) {
      return { isValid: false, error: 'Phone number is required.' };
    }
    return { isValid: true, normalized: '' };
  }

  // Reject alphabetic characters
  if (/[a-zA-Z]/.test(trimmed)) {
    return {
      isValid: false,
      error: 'Phone number cannot contain letters.',
    };
  }

  // Clean format: remove spaces, dashes, parentheses
  const cleanDigits = trimmed.replace(/[\s\-()]/g, '');

  // Match Philippine mobile formats: 09XXXXXXXXX (11 digits), +639XXXXXXXXX (13 chars), 639XXXXXXXXX (12 digits)
  const phMobileRegex = /^(\+639\d{9}|639\d{9}|09\d{9})$/;
  if (!phMobileRegex.test(cleanDigits)) {
    return {
      isValid: false,
      error: 'Invalid Philippine mobile format. Expected 11 digits starting with 09 (e.g. 09171234567).',
    };
  }

  return { isValid: true, normalized: cleanDigits };
}

/**
 * 6. ATTENDEE / GUEST NAME VALIDATION
 * Letters, spaces, legitimate punctuation (', ., -), ñ/Ñ. Reject unexpected digits.
 */
export function validateAttendeeName(rawName: string): PosValidationResult {
  const trimmed = (rawName || '').trim();
  if (!trimmed) {
    return { isValid: false, error: 'Attendee Name is required.' };
  }

  if (trimmed.length < 2) {
    return { isValid: false, error: 'Name must be at least 2 characters.' };
  }

  if (trimmed.length > 80) {
    return { isValid: false, error: 'Name cannot exceed 80 characters.' };
  }

  // Reject numeric digits
  if (/\d/.test(trimmed)) {
    return {
      isValid: false,
      error: 'Name cannot contain numeric digits.',
    };
  }

  // Accept Latin letters, Filipino ñ/Ñ, accented letters, spaces, hyphens, apostrophes, periods
  const validNameRegex = /^[A-Za-zÀ-ÖØ-öø-ÿñÑ\s.'-]+$/;
  if (!validNameRegex.test(trimmed)) {
    return {
      isValid: false,
      error: 'Name contains invalid symbols. Only letters, spaces, hyphens, periods, and apostrophes are allowed.',
    };
  }

  return { isValid: true, normalized: trimmed };
}

/**
 * 7. EMAIL VALIDATION
 * Standard RFC email pattern
 */
export function validateEmail(rawEmail: string, isRequired = false): PosValidationResult {
  const trimmed = (rawEmail || '').trim();
  if (!trimmed) {
    if (isRequired) {
      return { isValid: false, error: 'Email address is required.' };
    }
    return { isValid: true, normalized: '' };
  }

  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!emailRegex.test(trimmed)) {
    return {
      isValid: false,
      error: 'Please enter a valid email address (e.g. user@example.com).',
    };
  }

  return { isValid: true, normalized: trimmed.toLowerCase() };
}

/**
 * 8. GUEST REFERENCE CODE GENERATOR & VALIDATION
 */
export function generateUniqueGuestReference(): string {
  const rand = Math.floor(1000 + Math.random() * 9000);
  return `GUEST-${rand}`;
}

export function validateGuestReference(code: string): PosValidationResult {
  const trimmed = (code || '').trim().toUpperCase();
  if (!trimmed) {
    return { isValid: false, error: 'Guest Reference is required.' };
  }

  if (!/^GUEST-[0-9A-Z]{3,10}$/.test(trimmed) && !/^[A-Z0-9-]{4,15}$/.test(trimmed)) {
    return {
      isValid: false,
      error: 'Invalid Guest Reference format (e.g. GUEST-1234).',
    };
  }

  return { isValid: true, normalized: trimmed };
}

/**
 * 9. COMPLETE POS TRANSACTION VALIDATION (Mirrorable on frontend and backend)
 */
export interface PosTransactionData {
  attendeeType: 'student' | 'outsider';
  name: string;
  studentId?: string;
  guestReference?: string;
  course?: string;
  yearLevel?: string;
  section?: string;
  contactNumber?: string;
  email?: string;
  ticketPrice: number;
  cashReceived?: string | number;
}

export function validatePosTransaction(data: PosTransactionData): {
  isValid: boolean;
  errors: Record<string, string>;
  change: number;
} {
  const errors: Record<string, string> = {};

  // 1. Name
  const nameRes = validateAttendeeName(data.name);
  if (!nameRes.isValid && nameRes.error) {
    errors.name = nameRes.error;
  }

  // 2. Attendee-specific
  if (data.attendeeType === 'student') {
    const idRes = validateStudentId(data.studentId || '');
    if (!idRes.isValid && idRes.error) {
      errors.studentId = idRes.error;
    }

    const yearRes = validateYearLevel(data.yearLevel || '');
    if (!yearRes.isValid && yearRes.error) {
      errors.yearLevel = yearRes.error;
    }

    const secRes = validateSection(data.section || '');
    if (!secRes.isValid && secRes.error) {
      errors.section = secRes.error;
    }

    if (!data.course || !data.course.trim()) {
      errors.course = 'Course / Program is required.';
    }
  } else {
    // Outsider / Guest
    const guestRefRes = validateGuestReference(data.guestReference || '');
    if (!guestRefRes.isValid && guestRefRes.error) {
      errors.guestReference = guestRefRes.error;
    }

    if (data.contactNumber && data.contactNumber.trim()) {
      const phoneRes = validatePhoneNumber(data.contactNumber, false);
      if (!phoneRes.isValid && phoneRes.error) {
        errors.contactNumber = phoneRes.error;
      }
    }

    if (data.email && data.email.trim()) {
      const emailRes = validateEmail(data.email, false);
      if (!emailRes.isValid && emailRes.error) {
        errors.email = emailRes.error;
      }
    }
  }

  // 3. Payment & Cash Received
  const paymentRes = validateCashPayment(data.cashReceived ?? '', data.ticketPrice);
  if (!paymentRes.isValid && paymentRes.error) {
    errors.cashReceived = paymentRes.error;
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    change: paymentRes.change,
  };
}
