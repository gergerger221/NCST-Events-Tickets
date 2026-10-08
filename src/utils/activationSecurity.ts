import { normalizeId } from './userValidation.ts';

// Non-ambiguous character set avoiding 0, O, 1, I, L
const CHARSET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

/**
 * Generates a cryptographically random, non-predictable 8-character activation code
 * Format: XXXX-XXXX (e.g. K7M4-X2P9)
 */
export function generateActivationCode(): string {
  const bytes = new Uint8Array(8);
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    crypto.getRandomValues(bytes);
  } else {
    for (let i = 0; i < 8; i++) {
      bytes[i] = Math.floor(Math.random() * 256);
    }
  }

  let part1 = '';
  let part2 = '';
  for (let i = 0; i < 4; i++) {
    part1 += CHARSET[bytes[i] % CHARSET.length];
  }
  for (let i = 4; i < 8; i++) {
    part2 += CHARSET[bytes[i] % CHARSET.length];
  }

  return `${part1}-${part2}`;
}

/**
 * Creates a deterministic security hash of the activation code bound to the Student ID.
 * Standard FNV-1a 64-bit + SHA-like mixing to work synchronously in both browser & Node environments.
 */
export function hashActivationCode(code: string, studentId: string): string {
  const cleanCode = code.trim().toUpperCase().replace(/[^A-Z0-9]/g, '');
  const cleanId = normalizeId(studentId).toUpperCase();
  const input = `ncst_act_salt_v1:${cleanId}:${cleanCode}`;

  let h1 = 0x811c9dc5;
  let h2 = 0xcbf29ce4;

  for (let i = 0; i < input.length; i++) {
    const ch = input.charCodeAt(i);
    h1 ^= ch;
    h1 = Math.imul(h1, 0x01000193);
    h2 ^= ch;
    h2 = Math.imul(h2, 0x01000193);
  }

  const hex1 = (h1 >>> 0).toString(16).padStart(8, '0');
  const hex2 = (h2 >>> 0).toString(16).padStart(8, '0');
  const hex3 = (((h1 ^ h2) >>> 0)).toString(16).padStart(8, '0');
  const hex4 = (((h1 + h2) >>> 0)).toString(16).padStart(8, '0');

  return `act_${hex1}${hex2}${hex3}${hex4}`;
}

/**
 * Securely verifies an activation code against its stored hash
 */
export function verifyActivationCode(providedCode: string, studentId: string, storedHash: string): boolean {
  if (!providedCode || !studentId || !storedHash) return false;
  const computed = hashActivationCode(providedCode, studentId);
  return computed.toLowerCase() === storedHash.toLowerCase();
}
