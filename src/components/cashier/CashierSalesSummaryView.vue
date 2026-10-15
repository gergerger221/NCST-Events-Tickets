<template>
  <div class="space-y-6 max-w-6xl mx-auto">
    <!-- ==================== 1. HEADER & TOP BAR ==================== -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-7 shadow-xs transition-colors">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
          <div class="w-12 h-12 rounded-2xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <BarChart3 class="w-6 h-6 text-[#1E3A5F] dark:text-blue-400" />
          </div>
          <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-[#252525] text-[#475569] dark:text-slate-300 border border-slate-200 dark:border-[#333333]">
                Authoritative Metrics
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400">
                {{ currentUser?.role === 'admin' ? 'Administrator' : 'Cashier' }}: <strong class="text-slate-900 dark:text-white">{{ currentUser?.name || 'Staff' }}</strong>
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              Ticket Sales Summary
            </h1>
            <p class="text-xs text-[#64748B] dark:text-slate-400">
              Authoritative financial metrics, ticket distribution, and revenue collections across all active sales.
            </p>
          </div>
        </div>

        <!-- Quick Header Actions -->
        <div class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-[#2a2a2a] flex-wrap">
          <!-- Subtle Automatic-Update Status Indicator with small spinner during refresh -->
          <div
            class="flex items-center gap-2 px-3.5 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-medium text-slate-600 dark:text-slate-300 transition-colors shadow-2xs"
            role="status"
            aria-live="polite"
          >
            <span v-if="isSalesSummaryLoading" class="flex items-center gap-1.5 text-blue-600 dark:text-blue-400 font-semibold">
              <RotateCw class="w-3.5 h-3.5 animate-spin shrink-0" />
              <span>Updating totals...</span>
            </span>
            <span v-else class="flex items-center gap-1.5">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              <span class="text-slate-600 dark:text-slate-300 font-medium">
                {{ lastSyncedAt ? `Auto-updated ${formatTime(lastSyncedAt)}` : 'Live Sync Active' }}
              </span>
            </span>
          </div>

          <button
            type="button"
            @click="$emit('navigate', currentUser?.role === 'admin' ? 'dashboard' : 'cashier_pos')"
            class="flex items-center gap-1.5 px-4 py-2 bg-[#1E3A5F] hover:bg-[#162a45] text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
          >
            <component :is="currentUser?.role === 'admin' ? LayoutDashboard : CreditCard" class="w-3.5 h-3.5" />
            <span>{{ currentUser?.role === 'admin' ? 'Admin Dashboard →' : 'Go to Ticket POS →' }}</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== 2. EVENT SCOPE FILTER & CONTROLS ==================== -->
    <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col md:flex-row md:items-center justify-between gap-4 transition-colors">
      <div class="flex items-center gap-3 flex-1 flex-wrap">
        <label class="text-xs font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 whitespace-nowrap">
          Scope Event:
        </label>
        <div class="relative flex-1 min-w-[240px] max-w-md">
          <select
            v-model="selectedEventId"
            class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="">All Events (Global Summary)</option>
            <option v-for="ev in eligibleEvents" :key="ev.id" :value="ev.id">
              {{ ev.title }} ({{ ev.code }}) • {{ (ev.ticketPrice ?? 0) > 0 ? `₱${ev.ticketPrice}` : 'Free' }}
            </option>
          </select>
        </div>

        <!-- Selected Event Admission Badge -->
        <span
          v-if="selectedEvent"
          class="px-2.5 py-1 rounded-lg text-xs font-bold border"
          :class="isEventPaid
            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border-emerald-200 dark:border-emerald-800'
            : 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border-blue-200 dark:border-blue-800'"
        >
          {{ isEventPaid ? `Paid Event (₱${selectedEvent.ticketPrice})` : 'Free Admission Event' }}
        </span>
      </div>

      <div class="flex items-center gap-2 text-[11px] text-[#64748B] dark:text-slate-400">
        <span class="inline-flex items-center gap-1.5">
          <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
          Authoritative real-time sync with Cashier POS
        </span>
        <span v-if="lastSyncedAt" class="text-slate-400 dark:text-slate-500">
          • {{ formatTime(lastSyncedAt) }}
        </span>
      </div>
    </div>

    <!-- ==================== 3. UNAVAILABLE / ERROR STATE ==================== -->
    <div
      v-if="salesSummaryError"
      class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 flex items-center justify-between gap-3 text-xs text-amber-800 dark:text-amber-300"
    >
      <div class="flex items-center gap-2">
        <AlertCircle class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
        <span>Sales summary data is currently unavailable. Retrying connection...</span>
      </div>
      <button
        type="button"
        @click="loadSalesSummary()"
        class="px-3 py-1 bg-amber-200 dark:bg-amber-900/60 hover:bg-amber-300 dark:hover:bg-amber-800/80 rounded-lg font-bold text-amber-900 dark:text-amber-100 transition cursor-pointer"
      >
        Retry Sync
      </button>
    </div>

    <!-- ==================== 4. 6 AUTHORITATIVE METRIC CARDS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
      <!-- 1. Student Tickets Sold -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            {{ isEventPaid ? 'Student Tickets Sold' : 'Student Passes Issued' }}
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <GraduationCap class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : (isEventPaid ? (salesSummary?.paidStudentTicketsSold ?? 0) : (salesSummary?.freeStudentTickets ?? (salesSummary?.totalStudentTickets ?? 0)))) }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">
              passes
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            NCST Enrolled Students
            <span v-if="isEventPaid && (salesSummary?.freeStudentTickets || 0) > 0" class="text-slate-400 dark:text-slate-500">
              • {{ salesSummary?.freeStudentTickets }} free
            </span>
          </p>
        </div>
      </div>

      <!-- 2. Outsider Tickets Sold -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            {{ isEventPaid ? 'Outsider Tickets Sold' : 'Outsider Passes Issued' }}
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <Users class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : (isEventPaid ? (salesSummary?.paidOutsiderTicketsSold ?? 0) : (salesSummary?.freeOutsiderTickets ?? (salesSummary?.totalOutsiderTickets ?? 0)))) }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">
              passes
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            Guest & Public Passes
            <span v-if="isEventPaid && (salesSummary?.freeOutsiderTickets || 0) > 0" class="text-slate-400 dark:text-slate-500">
              • {{ salesSummary?.freeOutsiderTickets }} free
            </span>
          </p>
        </div>
      </div>

      <!-- 3. Total Paid Tickets Sold -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            {{ isEventPaid ? 'Total Paid Tickets Sold' : 'Total Passes Issued' }}
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <TicketIcon class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : (isEventPaid ? (salesSummary?.totalPaidTicketsSold ?? 0) : (salesSummary?.totalValidTicketsIssued ?? 0))) }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">
              tickets
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            <span v-if="isEventPaid">
              Student ({{ salesSummary?.paidStudentTicketsSold ?? 0 }}) + Outsider ({{ salesSummary?.paidOutsiderTicketsSold ?? 0 }})
            </span>
            <span v-else>
              Complimentary Pass Admission
            </span>
          </p>
        </div>
      </div>

      <!-- 4. Free Tickets Issued -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            Free Tickets Issued
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <Tag class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : (salesSummary?.freeTicketsIssued ?? 0)) }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">
              free passes
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            Complimentary & Free Admission Passes
          </p>
        </div>
      </div>

      <!-- 5. Total Valid Passes -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            Total Valid Passes
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <ShieldCheck class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : (salesSummary?.totalValidTicketsIssued ?? 0)) }}
            </span>
            <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">
              active passes
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            Active in circulation (excludes void & cancelled)
          </p>
        </div>
      </div>

      <!-- 6. Total Collected Sales (₱) -->
      <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
            Total Collected Sales
          </span>
          <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <CreditCard class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
          </div>
        </div>
        <div class="mt-3">
          <div class="flex items-baseline gap-2">
            <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
              {{ salesSummaryError ? 'Unavailable' : (isSalesSummaryLoading && !salesSummary ? '...' : `₱${formatCurrency(salesSummary?.totalCollectedSales)}`) }}
            </span>
          </div>
          <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
            Authoritative Confirmed Cash Collections
          </p>
        </div>
      </div>
    </div>

  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import {
  BarChart3,
  CreditCard,
  Ticket as TicketIcon,
  GraduationCap,
  Users,
  Tag,
  ShieldCheck,
  RotateCw,
  AlertCircle,
  LayoutDashboard
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem, TicketSalesSummary } from '../../types';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const {
  currentUser,
  events,
  currentEvent,
  tickets,
  transactions,
  academicPeriods,
  fetchTicketSalesSummary,
  salesSummaryCache,
  salesSummaryLastUpdated,
} = useAppStore();

// Authoritative Eligible Events (exclude archived periods and cancelled/completed events)
const eligibleEvents = computed<EventItem[]>(() => {
  return events.value.filter(ev => {
    if (ev.status === 'cancelled' || ev.status === 'completed') return false;
    if (ev.academicYear && ev.semester) {
      const period = academicPeriods.value.find(p => p.academicYear === ev.academicYear && p.semester === ev.semester);
      if (period && period.status === 'archived') {
        return false;
      }
    }
    return true;
  });
});

const selectedEventId = ref(
  (eligibleEvents.value.some(e => e.id === currentEvent.value?.id) ? currentEvent.value?.id : eligibleEvents.value[0]?.id) || ''
);

const selectedEvent = computed<EventItem | null>(() => {
  if (!selectedEventId.value) return null;
  return eligibleEvents.value.find(e => e.id === selectedEventId.value) || null;
});

const isEventPaid = computed(() => {
  if (!selectedEvent.value) return true;
  return (selectedEvent.value.ticketPrice ?? 0) > 0;
});

const cacheKey = computed(() => selectedEventId.value || '__GLOBAL__');

const salesSummary = ref<TicketSalesSummary | null>(
  salesSummaryCache.value[selectedEventId.value || '__GLOBAL__'] || null
);
const isSalesSummaryLoading = ref(false);
const salesSummaryError = ref(false);
const lastSyncedAt = ref<Date | null>(
  salesSummaryLastUpdated.value[selectedEventId.value || '__GLOBAL__']
    ? new Date(salesSummaryLastUpdated.value[selectedEventId.value || '__GLOBAL__'])
    : null
);

const formatCurrency = (val?: number) => {
  return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

const formatTime = (d: Date | null) => {
  if (!d) return '';
  return d.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit', second: '2-digit' });
};

const loadSalesSummary = async (eventId?: string) => {
  isSalesSummaryLoading.value = true;
  salesSummaryError.value = false;
  try {
    const targetEventId = eventId !== undefined ? eventId : selectedEventId.value;
    const summary = await fetchTicketSalesSummary(targetEventId || undefined);
    if (summary) {
      salesSummary.value = summary as TicketSalesSummary;
      salesSummaryError.value = false;
      lastSyncedAt.value = new Date();
    } else if (!salesSummary.value) {
      salesSummaryError.value = true;
    }
  } catch (err) {
    console.error('Failed to load authoritative ticket sales summary:', err);
    if (!salesSummary.value) {
      salesSummaryError.value = true;
    }
  } finally {
    isSalesSummaryLoading.value = false;
  }
};

let debounceTimer: ReturnType<typeof setTimeout> | null = null;
const debouncedLoadSalesSummary = (eventId?: string, delay = 250) => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
  }
  debounceTimer = setTimeout(() => {
    loadSalesSummary(eventId);
  }, delay);
};

// Immediate reload on event scope selection (display cached data first if available)
watch(selectedEventId, (newId) => {
  const cached = salesSummaryCache.value[newId || '__GLOBAL__'];
  if (cached) {
    salesSummary.value = cached;
    salesSummaryError.value = false;
    const ts = salesSummaryLastUpdated.value[newId || '__GLOBAL__'];
    if (ts) lastSyncedAt.value = new Date(ts);
  }
  loadSalesSummary(newId);
});

// Reactively adopt store cache updates for the currently selected scope
watch(
  () => salesSummaryCache.value[cacheKey.value],
  (newCachedSummary) => {
    if (newCachedSummary) {
      salesSummary.value = newCachedSummary;
      salesSummaryError.value = false;
      const ts = salesSummaryLastUpdated.value[cacheKey.value];
      if (ts) lastSyncedAt.value = new Date(ts);
    }
  },
  { deep: true }
);

// Cross-Cashier & Ticket state sync: deep watch tickets and transactions collections
watch(
  [tickets, transactions],
  () => {
    debouncedLoadSalesSummary(undefined, 250);
  },
  { deep: true }
);

const handleWindowFocus = () => {
  loadSalesSummary();
};

onMounted(() => {
  loadSalesSummary();
  window.addEventListener('focus', handleWindowFocus);
});

onUnmounted(() => {
  if (debounceTimer) {
    clearTimeout(debounceTimer);
    debounceTimer = null;
  }
  window.removeEventListener('focus', handleWindowFocus);
});
</script>
