<template>
  <div class="space-y-6 max-w-5xl mx-auto py-2 sm:py-4">
    <!-- ==================== 1. EVENT CONTEXT HEADER ==================== -->
    <div class="surface-card">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-5">
        <!-- Event Name, Status, Date, Venue -->
        <div class="space-y-1.5 min-w-0">
          <div class="flex items-center gap-2.5 flex-wrap">
            <h1 class="page-title truncate">
              {{ currentEvent?.title || 'No Active Event Selected' }}
            </h1>
            <span
              v-if="currentEvent"
              class="px-2.5 py-0.5 rounded-full text-xs font-semibold badge-success"
            >
              Active Event
            </span>
          </div>
          <p class="page-subtitle flex items-center gap-2 flex-wrap">
            <span v-if="currentEvent?.date" class="flex items-center gap-1">
              <Calendar class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ formatPhilippineDate(currentEvent.date) }}</span>
            </span>
            <span v-if="currentEvent?.venue" class="flex items-center gap-1">
              <span>•</span>
              <MapPin class="w-3.5 h-3.5 text-slate-400" />
              <span>{{ currentEvent.venue }}</span>
            </span>
            <span v-if="!currentEvent">Please select or configure a campus event</span>
          </p>
        </div>

        <!-- Cohesive Action Controls -->
        <div class="flex flex-wrap items-center gap-2.5 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-[#2a2a2a]">
          <!-- Primary CTA: View Real-Time Attendance -->
          <button
            type="button"
            @click="$emit('navigate', 'attendance')"
            class="btn-primary"
            title="Open Live Attendance Monitor"
          >
            <span>View Attendance</span>
            <ArrowRight class="w-4 h-4" />
          </button>

          <!-- Subordinate Secondary Navigation -->
          <div class="flex items-center gap-1 pl-1 sm:border-l sm:border-slate-200 sm:dark:border-[#2e2e2e]">
            <button
              type="button"
              @click="$emit('navigate', 'events')"
              class="btn-ghost"
              title="Open Campus Events Manager"
            >
              Events
            </button>
            <button
              type="button"
              @click="$emit('navigate', 'tickets')"
              class="btn-ghost"
              title="Open Issued Tickets Registry"
            >
              Tickets
            </button>
            <button
              type="button"
              @click="$emit('navigate', 'reports')"
              class="btn-ghost"
              title="Open Official Reports Center"
            >
              Reports
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 2. TWO PRIMARY SUMMARY METRICS ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-6">
      <!-- CARD 1 — TOTAL ATTENDANCE -->
      <div class="surface-card space-y-2">
        <div class="flex items-center justify-between">
          <span class="kpi-label">
            Total Attendance
          </span>
          <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-[#282828] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center">
            <UserCheck class="w-4 h-4" />
          </div>
        </div>
        <p class="kpi-number text-[#1E3A5F] dark:text-blue-400">
          {{ presentCount }}
        </p>
        <p class="kpi-subtext text-[#475569] dark:text-slate-400">
          {{ studentsPresentCount }} Students • {{ outsidersPresentCount }} Outsiders
        </p>
      </div>

      <!-- CARD 2 — TICKETS ISSUED -->
      <div class="surface-card space-y-2">
        <div class="flex items-center justify-between">
          <span class="kpi-label">
            Tickets Issued
          </span>
          <div class="w-8 h-8 rounded-lg bg-slate-100 dark:bg-[#282828] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center">
            <Ticket class="w-4 h-4" />
          </div>
        </div>
        <p class="kpi-number text-[#1E3A5F] dark:text-blue-400">
          {{ totalTicketsCount }}
        </p>
        <p class="kpi-subtext text-[#475569] dark:text-slate-400">
          Total registrations recorded for this event
        </p>
      </div>
    </div>

    <!-- ==================== 3. RECENT ATTENDANCE (PREVIEW LIST) ==================== -->
    <div class="surface-card space-y-4">
      <div class="flex items-center justify-between pb-3 border-b border-slate-100 dark:border-[#262626]">
        <div>
          <h2 class="section-heading">
            Recent Attendance
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            Latest entrance check-in activity for this event
          </p>
        </div>
        <button
          type="button"
          @click="$emit('navigate', 'attendance')"
          class="text-xs font-semibold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 inline-flex items-center gap-1 transition cursor-pointer"
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
          class="py-3 flex items-center justify-between gap-4 first:pt-0 last:pb-0"
        >
          <div class="flex items-center gap-3 sm:gap-4 min-w-0">
            <span class="text-xs font-mono font-medium text-slate-500 dark:text-slate-400 w-20 sm:w-24 shrink-0">
              {{ item.timeFormatted }}
            </span>
            <span class="text-xs sm:text-sm font-semibold text-slate-900 dark:text-white truncate">
              {{ item.name }}
            </span>
          </div>
          <span class="px-2.5 py-0.5 rounded-full text-xs font-semibold badge-success shrink-0">
            PRESENT
          </span>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="py-8 text-center text-slate-500 dark:text-slate-400 space-y-2">
        <Clock class="w-8 h-8 text-slate-300 dark:text-slate-600 mx-auto" />
        <p class="text-xs sm:text-sm font-medium">No check-ins recorded yet for this event.</p>
      </div>

      <!-- Bottom action link -->
      <div class="pt-3 border-t border-slate-100 dark:border-[#262626] flex justify-end">
        <button
          type="button"
          @click="$emit('navigate', 'attendance')"
          class="text-xs font-semibold text-slate-500 dark:text-slate-400 hover:text-blue-600 dark:hover:text-blue-400 inline-flex items-center gap-1 transition cursor-pointer"
        >
          <span>Open Full Attendance Monitor &rarr;</span>
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  ArrowRight,
  UserCheck,
  Ticket,
  Calendar,
  MapPin,
  Clock,
} from 'lucide-vue-next';
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
