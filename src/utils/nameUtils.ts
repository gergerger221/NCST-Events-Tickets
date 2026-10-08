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

export interface ParsedName {
  firstName: string;
  middleName?: string;
  lastName: string;
  fullName: string;
}

/**
 * Splits a full name string into First, Middle, and Last name components.
 * Intelligently recognizes multi-word surnames (e.g. "Juan Dela Cruz" -> First: "Juan", Last: "Dela Cruz").
 */
export function splitFullName(fullName: string): ParsedName {
  const clean = (fullName || '').trim().replace(/\s+/g, ' ');
  if (!clean) {
    return { firstName: '', lastName: '', fullName: '' };
  }

  // If name has a comma (e.g. "Dela Cruz, Juan M."), parse as Last, First Middle
  if (clean.includes(',')) {
    const parts = clean.split(',').map(p => p.trim());
    const lastName = parts[0] || '';
    const remainder = parts[1] || '';
    const remParts = remainder.split(' ').filter(Boolean);
    const firstName = remParts[0] || '';
    const middleName = remParts.slice(1).join(' ') || undefined;
    return {
      firstName,
      middleName,
      lastName,
      fullName: clean,
    };
  }

  const parts = clean.split(' ').filter(Boolean);
  if (parts.length === 1) {
    return { firstName: parts[0], lastName: parts[0], fullName: clean };
  }

  if (parts.length === 2) {
    return { firstName: parts[0], lastName: parts[1], fullName: clean };
  }

  // For 3 or more parts, check for multi-word surname prefixes
  // e.g. ["Juan", "Dela", "Cruz"] -> prefix "dela" at index 1 -> Last: "Dela Cruz"
  for (let i = 1; i < parts.length - 1; i++) {
    const twoWordPrefix = `${parts[i]} ${parts[i + 1]}`.toLowerCase();
    const oneWordPrefix = parts[i].toLowerCase();

    if (SURNAME_PREFIXES.includes(twoWordPrefix)) {
      const firstName = parts.slice(0, i).join(' ');
      const lastName = parts.slice(i).join(' ');
      return { firstName, lastName, fullName: clean };
    }

    if (SURNAME_PREFIXES.includes(oneWordPrefix)) {
      const firstName = parts.slice(0, i).join(' ');
      const lastName = parts.slice(i).join(' ');
      return { firstName, lastName, fullName: clean };
    }
  }

  // Standard 3-part name: First Name, Middle Initial/Name, Last Name
  // e.g. ["Maria", "Clara", "Santos"] or ["Juan", "M.", "Reyes"]
  if (parts.length === 3) {
    return {
      firstName: parts[0],
      middleName: parts[1],
      lastName: parts[2],
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
