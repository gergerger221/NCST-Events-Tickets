/**
 * Dynamic API URL resolver for NCST Events & Tickets System
 * Handles both Vite development server (root domain) and production subfolder deployments (e.g. /Integ 2/)
 */

export function getApiBase(): string {
  const envUrl = (import.meta as any).env?.VITE_API_URL;
  if (envUrl) {
    return envUrl.replace(/\/$/, '');
  }
  if (typeof window !== 'undefined' && window.location) {
    const pathname = window.location.pathname;
    const lastSlash = pathname.lastIndexOf('/');
    if (lastSlash > 0) {
      return pathname.substring(0, lastSlash);
    }
  }
  return '';
}

export function apiUrl(endpoint: string): string {
  const clean = endpoint.startsWith('/') ? endpoint : `/${endpoint}`;
  const base = getApiBase();
  return `${base}${clean}`;
}
