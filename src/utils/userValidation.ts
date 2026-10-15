import type { User, UserRole } from '../types/index.ts';

export interface IdentityConflictResult {
  hasConflict: boolean;
  conflictType: 'student_id' | 'employee_id' | 'none';
  existingUser?: User;
  isInactive: boolean;
  isCrossRole: boolean;
  isSameNameDifferentId: boolean;
  similarNameUser?: User;
  title: string;
  message: string;
  actionSuggestion: 'reactivate' | 'edit_role' | 'edit_account' | 'allow' | 'block';
}


/**
 * Normalizes institutional identifiers (Student ID / Employee ID)
 */
export function normalizeId(id?: string): string {
  return (id || '').trim();
}

/**
 * Normalizes name for soft similarity checks
 */
export function normalizeName(name?: string): string {
  return (name || '')
    .trim()
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .replace(/[.,]/g, '');
}

const SUFFIXES = ['jr.', 'jr', 'sr.', 'sr', 'ii', 'iii', 'iv', 'v', 'vi', 'vii', 'viii', 'ix', 'x'];
const PREFIXES = [
  'dela', 'de la', 'de los', 'delos', 'del', 'de', 'san', 'santa',
  'sto.', 'sta.', 'van', 'von', 'mc', 'mac', 'al', 'el', 'st.',
  'da', 'di', 'dos', 'das'
];

/**
 * Authoritative Student Surname Extractor for Frontend
 * Supports compound surnames (Dela Cruz, De Los Santos), suffixes (Jr., III), spaces, and hyphens
 */
export function extractSurname(input: string | { name?: string; lastName?: string; last_name?: string }): string {
  if (typeof input === 'object' && input !== null) {
    if (input.lastName?.trim()) return input.lastName.trim();
    if (input.last_name?.trim()) return input.last_name.trim();
    input = input.name || '';
  }
  const clean = (input || '').trim().replace(/\s+/g, ' ');
  if (!clean) return '';
  if (clean.includes(',')) {
    return clean.split(',')[0].trim();
  }
  const parts = clean.split(' ');
  if (parts.length === 1) return parts[0];

  // Strip trailing suffix
  if (parts.length > 1) {
    const lastClean = parts[parts.length - 1].toLowerCase().replace(/[.,]/g, '');
    if (SUFFIXES.includes(lastClean) || SUFFIXES.includes(parts[parts.length - 1].toLowerCase())) {
      parts.pop();
    }
  }
  if (parts.length === 1) return parts[0];
  if (parts.length === 2) return parts[1];

  for (let i = 1; i < parts.length; i++) {
    if (i + 1 < parts.length) {
      const twoWord = (parts[i] + ' ' + parts[i + 1]).toLowerCase();
      if (PREFIXES.includes(twoWord)) {
        return parts.slice(i).join(' ');
      }
    }
    const oneWord = parts[i].toLowerCase();
    if (PREFIXES.includes(oneWord)) {
      return parts.slice(i).join(' ');
    }
  }

  return parts[parts.length - 1];
}

/**
 * Split full name into firstName, middleName, lastName
 */
export function splitFullName(fullName: string): { firstName?: string; middleName?: string; lastName?: string } {
  if (!fullName) return {};
  const clean = fullName.trim().replace(/\s+/g, ' ');
  if (!clean) return {};
  if (clean.includes(',')) {
    const [last, firstMiddle] = clean.split(',').map(s => s.trim());
    const fmParts = (firstMiddle || '').split(' ');
    return {
      firstName: fmParts[0] || undefined,
      middleName: fmParts.length > 1 ? fmParts.slice(1).join(' ') : undefined,
      lastName: last || undefined,
    };
  }
  const surname = extractSurname(clean);
  const surnameIdx = clean.lastIndexOf(surname);
  if (surnameIdx > 0) {
    const firstMiddle = clean.substring(0, surnameIdx).trim();
    const fmParts = firstMiddle.split(' ');
    return {
      firstName: fmParts[0],
      middleName: fmParts.length > 1 ? fmParts.slice(1).join(' ') : undefined,
      lastName: surname,
    };
  }
  const parts = clean.split(' ');
  if (parts.length === 1) return { firstName: parts[0] };
  if (parts.length === 2) return { firstName: parts[0], lastName: parts[1] };
  return {
    firstName: parts[0],
    middleName: parts.slice(1, -1).join(' '),
    lastName: parts[parts.length - 1],
  };
}

/**
 * Checks if a role is considered an institutional employee / personnel
 */
export function isEmployeeRole(role: UserRole): boolean {
  return role === 'admin' || role === 'teacher' || role === 'cashier' || role === 'event_staff';
}

/**
 * Check whether a candidate user conflicts with existing users in the system
 * Strictly enforces: ONE PERSON = ONE ACCOUNT
 */
export function checkUserIdentityConflict(
  candidate: {
    id?: string; // Current user ID if editing
    name?: string;
    role?: UserRole;
    studentId?: string;
    employeeId?: string;
  },
  existingUsers: User[]
): IdentityConflictResult {
  const currentUserId = candidate.id;
  const targetStudentId = normalizeId(candidate.studentId);
  const targetEmployeeId = normalizeId(candidate.employeeId);
  const targetRole = candidate.role || 'student';
  const targetNameNormalized = normalizeName(candidate.name);

  // Exclude current user if editing existing profile
  const pool = currentUserId
    ? existingUsers.filter(u => u.id !== currentUserId)
    : existingUsers;

  // 1. PRIMARY IDENTITY CHECK: Student ID (case-insensitive across ALL roles)
  if (targetStudentId) {
    const studentMatch = pool.find(
      u => u.studentId && normalizeId(u.studentId).toLowerCase() === targetStudentId.toLowerCase()
    );

    if (studentMatch) {
      const isInactive = studentMatch.isActive === false;
      const isCrossRole = studentMatch.role !== targetRole;

      let message = '';
      if (isInactive) {
        message = `Student ID "${targetStudentId}" belongs to inactive account "${studentMatch.name}" (${studentMatch.role.toUpperCase()}). Please reactivate the existing account.`;
      } else if (isCrossRole) {
        message = `An account already exists for Student ID "${targetStudentId}" under role "${studentMatch.role.toUpperCase()}". Please edit the existing account to update role assignments.`;
      } else {
        message = `Student ID "${targetStudentId}" is already registered to "${studentMatch.name}".`;
      }

      return {
        hasConflict: true,
        conflictType: 'student_id',
        existingUser: studentMatch,
        isInactive,
        isCrossRole,
        isSameNameDifferentId: false,
        title: isInactive ? 'ACCOUNT ALREADY EXISTS — INACTIVE' : 'ACCOUNT ALREADY EXISTS',
        message,
        actionSuggestion: isInactive ? 'reactivate' : isCrossRole ? 'edit_role' : 'edit_account',
      };
    }
  }

  // 2. PRIMARY IDENTITY CHECK: Employee ID (case-insensitive across ALL roles)
  if (targetEmployeeId) {
    const employeeMatch = pool.find(
      u => u.employeeId && normalizeId(u.employeeId).toLowerCase() === targetEmployeeId.toLowerCase()
    );

    if (employeeMatch) {
      const isInactive = employeeMatch.isActive === false;
      const isCrossRole = employeeMatch.role !== targetRole;

      let message = '';
      if (isInactive) {
        message = `Employee ID "${targetEmployeeId}" belongs to inactive account "${employeeMatch.name}" (${employeeMatch.role.toUpperCase()}). Please reactivate the existing account.`;
      } else if (isCrossRole) {
        message = `AN ACCOUNT ALREADY EXISTS FOR THIS EMPLOYEE ID: "${employeeMatch.name}" is already registered as ${employeeMatch.role.toUpperCase()}. Please edit the existing account to update responsibility.`;
      } else {
        message = `Employee ID "${targetEmployeeId}" is already registered to "${employeeMatch.name}".`;
      }

      return {
        hasConflict: true,
        conflictType: 'employee_id',
        existingUser: employeeMatch,
        isInactive,
        isCrossRole,
        isSameNameDifferentId: false,
        title: isInactive ? 'ACCOUNT ALREADY EXISTS — INACTIVE' : 'AN ACCOUNT ALREADY EXISTS FOR THIS EMPLOYEE ID',
        message,
        actionSuggestion: isInactive ? 'reactivate' : isCrossRole ? 'edit_role' : 'edit_account',
      };
    }
  }

  // 3. SOFT NAME SIMILARITY CHECK (DO NOT BLOCK REGISTRATION IF IDENTIFIERS DIFFER)
  // Juan Dela Cruz (2025-00125) and Juan Dela Cruz (2026-00918) are two valid distinct people
  if (targetNameNormalized && targetNameNormalized.length >= 3) {
    const similarNameUser = pool.find(u => {
      const uNameNorm = normalizeName(u.name);
      return uNameNorm === targetNameNormalized;
    });

    if (similarNameUser) {
      const existingId = similarNameUser.studentId || similarNameUser.employeeId || similarNameUser.id;
      return {
        hasConflict: false, // NOT a hard blocking conflict!
        conflictType: 'none',
        existingUser: undefined,
        isInactive: false,
        isCrossRole: false,
        isSameNameDifferentId: true,
        similarNameUser,
        title: 'SIMILAR NAME FOUND',
        message: `⚠️ A similar name already exists ("${similarNameUser.name}" - ${similarNameUser.role.toUpperCase()} / ID: ${existingId}). Please verify the Student ID / Employee ID.`,
        actionSuggestion: 'allow',
      };
    }
  }

  return {
    hasConflict: false,
    conflictType: 'none',
    isInactive: false,
    isCrossRole: false,
    isSameNameDifferentId: false,
    title: 'NO CONFLICT',
    message: '✓ No existing account found. Ready for registration.',
    actionSuggestion: 'allow',
  };
}

/**
 * Full synchronous validation for creating or updating a user account
 */
export function validateUserForm(
  data: {
    id?: string;
    name: string;
    role: UserRole;
    studentId?: string;
    employeeId?: string;
    course?: string;
    yearLevel?: string;
    section?: string;
    department?: string;
    password?: string;
  },
  existingUsers: User[],
  options: { isEditing?: boolean } = {}
): {
  isValid: boolean;
  errors: Record<string, string>;
  warnings: Record<string, string>;
  conflict: IdentityConflictResult;
} {
  const errors: Record<string, string> = {};
  const warnings: Record<string, string> = {};

  // 1. Name Validation
  const cleanName = (data.name || '').trim();
  if (!cleanName) {
    errors.name = 'Full Name is required.';
  } else if (cleanName.length < 2) {
    errors.name = 'Full Name must be at least 2 characters.';
  } else if (cleanName.length > 100) {
    errors.name = 'Full Name cannot exceed 100 characters.';
  }

  // 2. Role-specific Primary Identifier
  if (data.role === 'student') {
    const cleanStudentId = normalizeId(data.studentId);
    if (!cleanStudentId) {
      errors.studentId = 'Student ID Number is required for students.';
    } else if (cleanStudentId.length < 3) {
      errors.studentId = 'Student ID must be at least 3 characters.';
    }

    if (!data.course || !data.course.trim()) {
      errors.course = 'Course / Program is required.';
    }
  } else {
    // Non-student employee roles
    const cleanEmployeeId = normalizeId(data.employeeId);
    if (!cleanEmployeeId && !options.isEditing) {
      // Required for employee registration
      errors.employeeId = 'Employee ID is required for institutional personnel.';
    } else if (cleanEmployeeId && cleanEmployeeId.length < 3) {
      errors.employeeId = 'Employee ID must be at least 3 characters.';
    }
  }

  // 3. Check Identity Conflicts
  const conflict = checkUserIdentityConflict(
    {
      id: data.id,
      name: cleanName,
      role: data.role,
      studentId: data.studentId,
      employeeId: data.employeeId,
    },
    existingUsers
  );

  if (conflict.hasConflict) {
    if (conflict.conflictType === 'student_id') {
      errors.studentId = conflict.message;
    } else if (conflict.conflictType === 'employee_id') {
      errors.employeeId = conflict.message;
    }
    errors._general = conflict.message;
  } else if (conflict.isSameNameDifferentId) {
    warnings.name = conflict.message;
  }

  return {
    isValid: Object.keys(errors).length === 0,
    errors,
    warnings,
    conflict,
  };
}

