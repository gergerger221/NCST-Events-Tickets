import { useAppStore } from './useAppStore';

/**
 * Domain Composable: Attendance Scanning & Verification Logs
 */
export function useScanner() {
  const store = useAppStore();
  return {
    logs: store.logs,
    currentEvent: store.currentEvent,
    validateAndRecordScan: store.validateAndRecordScan,
  };
}
