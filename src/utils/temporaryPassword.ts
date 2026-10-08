/**
 * Generates a unique, non-predictable temporary password for first-time student onboarding.
 * Format: NCST-XXXX-XXXX (e.g. NCST-K7M4-X2P9)
 * Uses unambiguous alphanumeric characters (excludes 0, O, 1, I, L)
 */
export function generateTemporaryPassword(): string {
  const chars = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';
  let p1 = '';
  let p2 = '';
  
  if (typeof crypto !== 'undefined' && crypto.getRandomValues) {
    const bytes = new Uint8Array(8);
    crypto.getRandomValues(bytes);
    for (let i = 0; i < 4; i++) p1 += chars[bytes[i] % chars.length];
    for (let i = 4; i < 8; i++) p2 += chars[bytes[i] % chars.length];
  } else {
    for (let i = 0; i < 4; i++) p1 += chars[Math.floor(Math.random() * chars.length)];
    for (let i = 4; i < 8; i++) p2 += chars[Math.floor(Math.random() * chars.length)];
  }
  
  return `NCST-${p1}-${p2}`;
}
