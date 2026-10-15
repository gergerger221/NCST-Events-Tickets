/**
 * Name Utilities for Filipino and International Names
 * Supports multi-part surnames (e.g., "Dela Cruz", "De Los Santos", "San Juan", "Del Rosario", "Van Buren", "McIntyre")
 * and provides robust A-Z Last Name sorting.
 */

// Common multi-word surname prefixes in Philippine and Spanish/European naming conventions
const SURNAME_PREFIXES = [
  'dela',
  'de la',
  'de los',
  'delos',
  'del',
  'de',
  'san',
  'santa',
  'sto.',
  'sta.',
  'van',
  'von',
  'mc',
  'mac',
  'al',
  'el',
  'st.'
];

// Common generational and name suffixes
const NAME_SUFFIXES = ['jr', 'jr.', 'sr', 'sr.', 'ii', 'iii', 'iv', 'v'];

export interface ParsedName {
  firstName: string;
  middleName?: string;
  lastName: string;
  suffix?: string;
  fullName: string;
}

/**
 * Splits a full name string into First, Middle, Last name, and Suffix components.
 * Intelligently recognizes multi-word surnames (e.g. "Juan Dela Cruz" -> First: "Juan", Last: "Dela Cruz")
 * and generational suffixes (e.g. "Jr.", "Sr.", "II", "III").
 */
export function splitFullName(fullName: string): ParsedName {
  const clean = (fullName || '').trim().replace(/\s+/g, ' ');
  if (!clean) {
    return { firstName: '', lastName: '', fullName: '' };
  }

  // If name has a comma (e.g. "Dela Cruz, Juan M. Jr."), parse as Last, First Middle Suffix
  if (clean.includes(',')) {
    const parts = clean.split(',').map(p => p.trim());
    const lastName = parts[0] || '';
    const remainder = parts[1] || '';
    let remParts = remainder.split(' ').filter(Boolean);
    let suffix: string | undefined = undefined;
    if (remParts.length > 1 && NAME_SUFFIXES.includes(remParts[remParts.length - 1].toLowerCase())) {
      suffix = remParts.pop();
    }
    const firstName = remParts[0] || '';
    const middleName = remParts.slice(1).join(' ') || undefined;
    return {
      firstName,
      middleName,
      lastName,
      suffix,
      fullName: clean,
    };
  }

  let parts = clean.split(' ').filter(Boolean);
  let suffix: string | undefined = undefined;
  if (parts.length > 1 && NAME_SUFFIXES.includes(parts[parts.length - 1].toLowerCase())) {
    suffix = parts.pop();
  }

  if (parts.length === 1) {
    return { firstName: parts[0], lastName: parts[0], suffix, fullName: clean };
  }

  if (parts.length === 2) {
    return { firstName: parts[0], lastName: parts[1], suffix, fullName: clean };
  }

  // For 3 or more parts, check for multi-word surname prefixes
  // e.g. ["Juan", "Dela", "Cruz"] -> prefix "dela" at index 1 -> Last: "Dela Cruz"
  for (let i = 1; i < parts.length - 1; i++) {
    const twoWordPrefix = `${parts[i]} ${parts[i + 1]}`.toLowerCase();
    const oneWordPrefix = parts[i].toLowerCase();

    if (SURNAME_PREFIXES.includes(twoWordPrefix)) {
      const firstName = parts.slice(0, i).join(' ');
      const lastName = parts.slice(i).join(' ');
      return { firstName, lastName, suffix, fullName: clean };
    }

    if (SURNAME_PREFIXES.includes(oneWordPrefix)) {
      const firstName = parts.slice(0, i).join(' ');
      const lastName = parts.slice(i).join(' ');
      return { firstName, lastName, suffix, fullName: clean };
    }
  }

  // Standard 3-part name: First Name, Middle Initial/Name, Last Name
  // e.g. ["Maria", "Clara", "Santos"] or ["Juan", "M.", "Reyes"]
  if (parts.length === 3) {
    return {
      firstName: parts[0],
      middleName: parts[1],
      lastName: parts[2],
      suffix,
      fullName: clean,
    };
  }

  // 4+ parts: First 2 are first names, remaining are middle/last
  const firstName = parts.slice(0, parts.length - 2).join(' ');
  const middleName = parts[parts.length - 2];
  const lastName = parts[parts.length - 1];

  return {
    firstName,
    middleName,
    lastName,
    suffix,
    fullName: clean,
  };
}

/**
 * Returns the effective last name from a person record.
 * Uses explicit `lastName` if present, otherwise splits `name`.
 */
export function getLastName(person: { name?: string; lastName?: string; first_name?: string; last_name?: string }): string {
  if (person.lastName && person.lastName.trim()) return person.lastName.trim();
  if (person.last_name && person.last_name.trim()) return person.last_name.trim();
  if (person.name && person.name.trim()) {
    return splitFullName(person.name).lastName;
  }
  return '';
}

/**
 * Returns the effective first name from a person record.
 */
export function getFirstName(person: { name?: string; firstName?: string; first_name?: string }): string {
  if (person.firstName && person.firstName.trim()) return person.firstName.trim();
  if (person.first_name && person.first_name.trim()) return person.first_name.trim();
  if (person.name && person.name.trim()) {
    return splitFullName(person.name).firstName;
  }
  return '';
}

/**
 * Sorts an array of items by Last Name A-Z (case-insensitive).
 * When last names are identical, secondary sort by First Name.
 */
export function sortByLastName<T>(
  list: T[],
  nameExtractor?: (item: T) => { name?: string; lastName?: string; firstName?: string } | string
): T[] {
  return [...list].sort((a, b) => {
    let personA: { name?: string; lastName?: string; firstName?: string };
    let personB: { name?: string; lastName?: string; firstName?: string };

    if (nameExtractor) {
      const extA = nameExtractor(a);
      const extB = nameExtractor(b);
      personA = typeof extA === 'string' ? { name: extA } : extA;
      personB = typeof extB === 'string' ? { name: extB } : extB;
    } else {
      personA = a as any;
      personB = b as any;
    }

    const lastA = getLastName(personA).toLowerCase();
    const lastB = getLastName(personB).toLowerCase();

    if (lastA < lastB) return -1;
    if (lastA > lastB) return 1;

    // Tie-breaker: First Name
    const firstA = getFirstName(personA).toLowerCase();
    const firstB = getFirstName(personB).toLowerCase();
    if (firstA < firstB) return -1;
    if (firstA > firstB) return 1;

    return 0;
  });
}

export type TeacherRosterSortCriteria =
  | 'name_asc'
  | 'name_desc'
  | 'id_asc'
  | 'status_present'
  | 'status_not_entered'
  | 'time_in_earliest';

export interface RosterSortableItem {
  student: {
    name?: string;
    studentId?: string;
    [key: string]: any;
  };
  isPresent?: boolean;
  timeIn?: string | null;
  [key: string]: any;
}

/**
 * Centrally sorts an attendance roster with plain-language criteria:
 * - 'name_asc': Student Name (A–Z) [Default: Surname A-Z, given name tie-breaker]
 * - 'name_desc': Student Name (Z–A)
 * - 'id_asc': Student ID (Lowest first, numeric comparison)
 * - 'status_present': Present Students First (Tie-breaker: Surname A-Z)
 * - 'status_not_entered': Not Yet Entered First (Tie-breaker: Surname A-Z)
 * - 'time_in_earliest': Time In (Earliest first; missing time values placed at end)
 */
export function sortAttendanceRoster<T extends RosterSortableItem>(
  list: T[],
  criteria: TeacherRosterSortCriteria = 'name_asc'
): T[] {
  const compareSurnameAsc = (a: T, b: T): number => {
    const lastA = getLastName(a.student).toLowerCase();
    const lastB = getLastName(b.student).toLowerCase();
    if (lastA < lastB) return -1;
    if (lastA > lastB) return 1;

    const firstA = getFirstName(a.student).toLowerCase();
    const firstB = getFirstName(b.student).toLowerCase();
    if (firstA < firstB) return -1;
    if (firstA > firstB) return 1;

    return (a.student.studentId || '').localeCompare(b.student.studentId || '', undefined, { numeric: true });
  };

  const compareSurnameDesc = (a: T, b: T): number => compareSurnameAsc(b, a);

  return [...list].sort((a, b) => {
    switch (criteria) {
      case 'name_desc':
        return compareSurnameDesc(a, b);

      case 'id_asc': {
        const idA = (a.student.studentId || '').trim();
        const idB = (b.student.studentId || '').trim();
        const cmp = idA.localeCompare(idB, undefined, { numeric: true, sensitivity: 'base' });
        return cmp !== 0 ? cmp : compareSurnameAsc(a, b);
      }

      case 'status_present': {
        const presA = Boolean(a.isPresent);
        const presB = Boolean(b.isPresent);
        if (presA !== presB) {
          return presA ? -1 : 1;
        }
        return compareSurnameAsc(a, b);
      }

      case 'status_not_entered': {
        const presA = Boolean(a.isPresent);
        const presB = Boolean(b.isPresent);
        if (presA !== presB) {
          return !presA ? -1 : 1;
        }
        return compareSurnameAsc(a, b);
      }

      case 'time_in_earliest': {
        const timeA = a.timeIn ? new Date(a.timeIn).getTime() : Infinity;
        const timeB = b.timeIn ? new Date(b.timeIn).getTime() : Infinity;
        if (timeA !== timeB) {
          return timeA - timeB;
        }
        return compareSurnameAsc(a, b);
      }

      case 'name_asc':
      default:
        return compareSurnameAsc(a, b);
    }
  });
}
