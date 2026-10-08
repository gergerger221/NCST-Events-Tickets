import type { User, UserRole } from '../types/index.ts';

export interface IdentityConflictResult {
  hasConflict: boolean;
  conflictType: 'student_id' | 'employee_id' | 'email' | 'none';
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
 * Normalizes email address for strict case-insensitive uniqueness
 * e.g. Juan.DelaCruz@school.edu.ph -> juan.delacruz@school.edu.ph
 */
export function normalizeEmail(email: string): string {
  return (email || '').trim().toLowerCase();
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

/**
 * Split full name into firstName, middleName, lastName
 */
export function splitFullName(fullName: string): { firstName?: string; middleName?: string; lastName?: string } {
  if (!fullName) return {};
  const parts = fullName.trim().split(/\s+/).filter(Boolean);
  if (parts.length === 0) return {};
  if (parts.length === 1) return { firstName: parts[0] };
  if (parts.length === 2) return { firstName: parts[0], lastName: parts[1] };
  
  const firstName = parts[0];
  const lastName = parts[parts.length - 1];
  const middleName = parts.slice(1, -1).join(' ');
  return { firstName, middleName, lastName };
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
    email?: string;
    role?: UserRole;
    studentId?: string;
    employeeId?: string;
  },
  existingUsers: User[]
): IdentityConflictResult {
  const currentUserId = candidate.id;
  const targetEmail = normalizeEmail(candidate.email || '');
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

  // 3. SECONDARY UNIQUE IDENTIFIER CHECK: Institutional Email (case-insensitive across ALL roles)
  if (targetEmail) {
    const emailMatch = pool.find(
      u => u.email && normalizeEmail(u.email) === targetEmail
    );

    if (emailMatch) {
      const isInactive = emailMatch.isActive === false;
      const isCrossRole = emailMatch.role !== targetRole;

      const message = isInactive
        ? `Email "${targetEmail}" belongs to an existing INACTIVE account (${emailMatch.name}). Please reactivate the account instead of registering again.`
        : `Institutional email "${targetEmail}" is already registered to "${emailMatch.name}" (${emailMatch.role.toUpperCase()}).`;

      return {
        hasConflict: true,
        conflictType: 'email',
        existingUser: emailMatch,
        isInactive,
        isCrossRole,
        isSameNameDifferentId: false,
        title: isInactive ? 'ACCOUNT ALREADY EXISTS — INACTIVE' : 'EMAIL ALREADY REGISTERED',
        message,
        actionSuggestion: isInactive ? 'reactivate' : 'edit_account',
      };
    }
  }

  // 4. SOFT NAME SIMILARITY CHECK (DO NOT BLOCK REGISTRATION IF IDENTIFIERS DIFFER)
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
    email: string;
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

  // 2. Email Validation
  const cleanEmail = normalizeEmail(data.email);
  const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  if (!cleanEmail) {
    errors.email = 'Institutional Email is required.';
  } else if (!emailRegex.test(cleanEmail)) {
    errors.email = 'Please provide a valid institutional email address.';
  }

  // 3. Role-specific Primary Identifier
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

  // 4. Check Identity Conflicts
  const conflict = checkUserIdentityConflict(
    {
      id: data.id,
      name: cleanName,
      email: cleanEmail,
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
    } else if (conflict.conflictType === 'email') {
      errors.email = conflict.message;
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
