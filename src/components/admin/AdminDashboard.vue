<template>
  <div class="space-y-8 max-w-5xl mx-auto py-4">
    <!-- ==================== 1. EVENT HEADER ==================== -->
    <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-6 sm:p-8 shadow-xs transition-colors">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-6">
        <!-- Event Name, Date, Venue -->
        <div class="space-y-1.5 min-w-0">
          <div class="flex items-center gap-2.5">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight truncate">
              {{ currentEvent?.title || 'No Active Event Selected' }}
            </h1>
            <span
              v-if="currentEvent"
              class="px-2 py-0.5 rounded text-[10px] font-extrabold uppercase bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60"
            >
              Active
            </span>
          </div>
          <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
            {{ currentEvent?.date ? formatPhilippineDate(currentEvent.date) : 'No date set' }}
            <span v-if="currentEvent?.venue" class="mx-2">•</span>
            {{ currentEvent?.venue || 'NCST Gymnasium' }}
          </p>
        </div>

        <!-- Actions -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <!-- Primary CTA: View Real-Time Attendance -->
          <button
            @click="$emit('navigate', 'attendance')"
            class="inline-flex items-center gap-2 px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition cursor-pointer"
          >
            <span>View Attendance</span>
            <ArrowRight class="w-4 h-4" />
          </button>

          <!-- Subordinate Secondary Actions -->
          <div class="flex items-center gap-1.5 pl-2 border-l border-slate-200 dark:border-[#2e2e2e]">
            <button
              @click="$emit('navigate', 'events')"
              class="px-3 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#252525] rounded-xl text-xs font-semibold transition cursor-pointer"
              title="Open Events"
            >
              Events
            </button>
            <button
              @click="$emit('navigate', 'tickets')"
              class="px-3 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#252525] rounded-xl text-xs font-semibold transition cursor-pointer"
              title="Open Issued Tickets Registry"
            >
              Tickets
            </button>
            <button
              @click="$emit('navigate', 'reports')"
              class="px-3 py-2 text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#252525] rounded-xl text-xs font-semibold transition cursor-pointer"
              title="Open Reports"
            >
              Reports
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 2. TWO PRIMARY SUMMARY METRICS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5 sm:gap-6">
      <!-- CARD 1 — TOTAL ATTENDANCE -->
      <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-6 sm:p-7 shadow-xs space-y-2 transition-colors">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">
          TOTAL ATTENDANCE
        </span>
        <p class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
          {{ presentCount }}
        </p>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          {{ studentsPresentCount }} Students • {{ outsidersPresentCount }} Outsiders
        </p>
      </div>

      <!-- CARD 2 — TICKETS ISSUED -->
      <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-6 sm:p-7 shadow-xs space-y-2 transition-colors">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 block">
          TICKETS ISSUED
        </span>
        <p class="text-3xl sm:text-4xl font-black text-slate-900 dark:text-white tracking-tight">
          {{ totalTicketsCount }}
        </p>
        <p class="text-sm font-medium text-slate-500 dark:text-slate-400">
          Issued for this event
        </p>
      </div>
    </div>

    <!-- ==================== 3. RECENT ATTENDANCE (SMALL PREVIEW ONLY) ==================== -->
    <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-6 sm:p-7 shadow-xs space-y-5 transition-colors">
      <div class="flex items-center justify-between">
        <h2 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
          RECENT ATTENDANCE
        </h2>
        <button
          @click="$emit('navigate', 'attendance')"
          class="text-xs sm:text-sm font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 inline-flex items-center gap-1.5 transition cursor-pointer"
        >
          <span>View Real-Time Attendance</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>

      <!-- Small Preview List (3 to 5 items) -->
      <div v-if="recentAttendanceList.length > 0" class="divide-y divide-slate-100 dark:divide-[#262626]">
        <div
          v-for="item in recentAttendanceList"
          :key="item.id"
          class="py-3 sm:py-3.5 flex items-center justify-between gap-4 first:pt-0 last:pb-0"
        >
          <div class="flex items-center gap-3 sm:gap-4 min-w-0">
            <span class="text-xs font-mono font-medium text-slate-400 dark:text-slate-500 w-20 sm:w-24 shrink-0">
              {{ item.timeFormatted }}
            </span>
            <span class="text-sm font-semibold text-slate-900 dark:text-white truncate">
              {{ item.name }}
            </span>
          </div>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-bold bg-emerald-50 dark:bg-emerald-950/50 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800/60 shrink-0">
            PRESENT
          </span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="py-8 text-center text-slate-400 dark:text-slate-500">
        <p class="text-sm font-medium">No check-ins recorded yet for this event.</p>
      </div>

      <!-- Bottom action link -->
      <div class="pt-3 border-t border-slate-100 dark:border-[#262626] flex justify-end">
        <button
          @click="$emit('navigate', 'attendance')"
          class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 inline-flex items-center gap-1 transition cursor-pointer"
        >
          <span>View Real-Time Attendance &rarr;</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ArrowRight } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { formatPhilippineDate } from '../../utils/philippineTime';

defineEmits<{
  (e: 'navigate', tab: string): void;
}>();

const { currentEvent, tickets, logs } = useAppStore();

// Filtered event tickets
const eventTickets = computed(() => tickets.value.filter(t => t.eventId === currentEvent.value?.id));

// 1. Total Tickets Issued
const totalTicketsCount = computed(() => eventTickets.value.length);

// 2. Attendance Counts (Students vs Outsiders)
const presentCount = computed(() => eventTickets.value.filter(t => t.attendanceStatus === 'present').length);

const studentsPresentCount = computed(() => {
  return eventTickets.value.filter(t => t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length;
});

const outsidersPresentCount = computed(() => {
  return eventTickets.value.filter(t => t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length;
});

// Recent Check-ins (Top 3-5 latest successful scans)
interface RecentScanItem {
  id: string;
  timeFormatted: string;
  name: string;
  timestamp: string;
}

const recentAttendanceList = computed<RecentScanItem[]>(() => {
  const verifiedList: RecentScanItem[] = [];

  // Logs for current event
  const currentLogs = logs.value.filter(l => l.eventId === currentEvent.value?.id || l.eventId === 'unknown');

  currentLogs
    .filter(l => l.status === 'valid' || (l.status as string) === 'success')
    .forEach(l => {
      verifiedList.push({
        id: l.id,
        timeFormatted: l.timeIn || (l.timestamp ? new Date(l.timestamp).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true }) : '---'),
        name: l.attendeeName,
        timestamp: l.timestamp,
      });
    });

  // Include present tickets not logged separately
  eventTickets.value
    .filter(t => t.attendanceStatus === 'present')
    .forEach(t => {
      const alreadyIncluded = verifiedList.some(item => item.name === t.currentAttendeeName);
      if (!alreadyIncluded) {
        verifiedList.push({
          id: `tix-${t.id}`,
          timeFormatted: t.timeIn ? new Date(t.timeIn).toLocaleTimeString([], { hour: 'numeric', minute: '2-digit', hour12: true }) : 'Just now',
          name: t.currentAttendeeName,
          timestamp: t.timeIn || t.purchaseDate,
        });
      }
    });

  // Sort descending by timestamp and take top 5
  return verifiedList
    .sort((a, b) => new Date(b.timestamp).getTime() - new Date(a.timestamp).getTime())
    .slice(0, 5);
});
</script>
