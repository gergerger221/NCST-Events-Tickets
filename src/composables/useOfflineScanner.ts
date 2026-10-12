import { ref, computed, watch, onMounted, onBeforeUnmount } from 'vue';
import { QueuedScan } from '../types';
import { apiUrl } from '../utils/apiUrl';

const STORAGE_KEYS = {
  QUEUE: 'integ2_offline_scan_queue_v1',
  CONTINUOUS: 'integ2_scanner_continuous_mode_v1',
  LAST_SYNC: 'integ2_scanner_last_sync_v1',
};

// Singleton state for offline resilience
const isOnline = ref<boolean>(typeof navigator !== 'undefined' ? navigator.onLine : true);
const isSyncing = ref<boolean>(false);
const syncError = ref<string | null>(null);
const lastSyncTime = ref<string | null>(
  typeof localStorage !== 'undefined' ? localStorage.getItem(STORAGE_KEYS.LAST_SYNC) : null
);

const loadQueue = (): QueuedScan[] => {
  if (typeof localStorage === 'undefined') return [];
  try {
    const raw = localStorage.getItem(STORAGE_KEYS.QUEUE);
    return raw ? JSON.parse(raw) : [];
  } catch {
    return [];
  }
};

const saveQueue = (queue: QueuedScan[]) => {
  if (typeof localStorage === 'undefined') return;
  try {
    localStorage.setItem(STORAGE_KEYS.QUEUE, JSON.stringify(queue));
  } catch {}
};

const queuedScans = ref<QueuedScan[]>(loadQueue());

const continuousScanMode = ref<boolean>(
  typeof localStorage !== 'undefined'
    ? localStorage.getItem(STORAGE_KEYS.CONTINUOUS) === 'true'
    : false
);

export function useOfflineScanner() {
  const queuedCount = computed(() => queuedScans.value.length);
  const hasPendingScans = computed(() => queuedScans.value.length > 0);

  const toggleContinuousMode = (): boolean => {
    continuousScanMode.value = !continuousScanMode.value;
    if (typeof localStorage !== 'undefined') {
      try {
        localStorage.setItem(STORAGE_KEYS.CONTINUOUS, String(continuousScanMode.value));
      } catch {}
    }
    return continuousScanMode.value;
  };

  const enqueueScan = (item: {
    rawScanData: string;
    eventId?: string;
    scannerUserId: string;
    scannerName: string;
    ticketId?: string;
    ticketNumber?: string;
    attendeeName?: string;
  }): QueuedScan => {
    const scan: QueuedScan = {
      id: `offline-scan-${Date.now()}-${Math.random().toString(36).substring(2, 7)}`,
      rawScanData: item.rawScanData,
      eventId: item.eventId,
      scannedAt: new Date().toISOString(),
      scannerUserId: item.scannerUserId,
      scannerName: item.scannerName,
      ticketId: item.ticketId,
      ticketNumber: item.ticketNumber,
      attendeeName: item.attendeeName,
      status: 'pending',
    };

    queuedScans.value.push(scan);
    saveQueue(queuedScans.value);
    return scan;
  };

  const removeQueuedScan = (scanId: string) => {
    queuedScans.value = queuedScans.value.filter(s => s.id !== scanId);
    saveQueue(queuedScans.value);
  };

  const clearQueue = () => {
    queuedScans.value = [];
    saveQueue([]);
  };

  /**
   * Flushes queued offline scans to the backend server.
   */
  const flushQueue = async (
    getAuthToken?: () => string,
    onSuccessItem?: (synced: QueuedScan) => void
  ): Promise<{ syncedCount: number; failedCount: number }> => {
    if (queuedScans.value.length === 0 || isSyncing.value) {
      return { syncedCount: 0, failedCount: 0 };
    }

    if (!navigator.onLine) {
      syncError.value = 'Cannot sync while offline. Network connection required.';
      return { syncedCount: 0, failedCount: queuedScans.value.length };
    }

    isSyncing.value = true;
    syncError.value = null;
    let syncedCount = 0;
    let failedCount = 0;

    const remainingItems: QueuedScan[] = [];
    const token = getAuthToken ? getAuthToken() : (localStorage.getItem('integ2_vue_session_token_v10') || '');

    for (let i = 0; i < queuedScans.value.length; i++) {
      const scan = queuedScans.value[i];
      try {
        scan.status = 'syncing';
        const headers: Record<string, string> = {
          'Content-Type': 'application/json',
        };
        if (token) {
          headers['Authorization'] = `Bearer ${token}`;
        }

        const res = await fetch(apiUrl('/api/scan-ticket'), {
          method: 'POST',
          headers,
          body: JSON.stringify({
            rawScanData: scan.rawScanData,
            eventId: scan.eventId,
            user_id: scan.scannerUserId,
            scanned_at: scan.scannedAt,
            offline_id: scan.id,
          }),
        });

        const data = await res.json().catch(() => null);

        // 1. Success or Server-Resolved Duplicate (Duplicate is terminal resolved state)
        const isDuplicate = res.status === 409 || (data && (data.is_duplicate || data.already_scanned || data.status === 'ALREADY_USED'));
        if (res.ok || isDuplicate) {
          scan.status = 'synced';
          scan.error = undefined;
          syncedCount++;
          if (onSuccessItem) onSuccessItem(scan);
          continue;
        }

        // 2. Expired Authentication Session (401 / 403) - Preserve pending scans!
        if (res.status === 401 || res.status === 403) {
          scan.status = 'pending';
          scan.error = 'Session expired. Please re-authenticate to sync scans.';
          syncError.value = 'Session expired. Scans preserved in queue. Please log in again to sync.';
          // Keep current scan and all remaining un-processed scans in queue
          remainingItems.push(scan);
          for (let j = i + 1; j < queuedScans.value.length; j++) {
            queuedScans.value[j].status = 'pending';
            remainingItems.push(queuedScans.value[j]);
          }
          failedCount += (queuedScans.value.length - i);
          break; // Stop syncing until user re-authenticates
        }

        // 3. Rate Limiting (429) - Preserve pending scans!
        if (res.status === 429) {
          scan.status = 'pending';
          scan.error = data?.error || 'Rate limit reached. Retrying shortly.';
          syncError.value = 'Rate limit reached. Scans preserved in queue. Retrying shortly.';
          remainingItems.push(scan);
          for (let j = i + 1; j < queuedScans.value.length; j++) {
            queuedScans.value[j].status = 'pending';
            remainingItems.push(queuedScans.value[j]);
          }
          failedCount += (queuedScans.value.length - i);
          break; // Pause syncing to respect server rate limit
        }

        // 4. Permanent Validation Rejections (400, 404, 422 - Invalid credential, wrong event, void ticket)
        if (res.status === 400 || res.status === 404 || res.status === 422) {
          scan.status = 'failed';
          scan.error = data?.error || 'Permanently rejected: Invalid ticket or event mismatch';
          failedCount++;
          // Do not re-enqueue permanently invalid ticket credentials
          continue;
        }

        // 5. Transient Server Errors (5xx)
        scan.status = 'pending';
        scan.error = data?.error || 'Server error, retrying...';
        remainingItems.push(scan);
        failedCount++;
      } catch (err: any) {
        // Network fetch exception / connection drop - Keep in queue for automatic retry
        scan.status = 'pending';
        scan.error = err?.message || 'Network request failed';
        remainingItems.push(scan);
        failedCount++;
      }
    }

    queuedScans.value = remainingItems;
    saveQueue(remainingItems);

    const nowPht = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
    lastSyncTime.value = nowPht;
    if (typeof localStorage !== 'undefined') {
      try {
        localStorage.setItem(STORAGE_KEYS.LAST_SYNC, nowPht);
      } catch {}
    }

    isSyncing.value = false;
    return { syncedCount, failedCount };
  };

  return {
    isOnline,
    isSyncing,
    syncError,
    lastSyncTime,
    queuedScans,
    queuedCount,
    hasPendingScans,
    continuousScanMode,
    toggleContinuousMode,
    enqueueScan,
    removeQueuedScan,
    clearQueue,
    flushQueue,
  };
}

// Global window network listeners
if (typeof window !== 'undefined') {
  window.addEventListener('online', () => {
    isOnline.value = true;
    syncError.value = null;
    const { flushQueue } = useOfflineScanner();
    flushQueue();
  });

  window.addEventListener('offline', () => {
    isOnline.value = false;
  });
}
