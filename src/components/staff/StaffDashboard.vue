<template>
  <div class="space-y-6 max-w-2xl mx-auto py-2">
    <!-- ==================== TOP CONTEXT & EVENT SWITCHER ==================== -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 px-1">
      <div class="flex items-center gap-2">
        <span class="px-3 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 text-[11px] font-black uppercase tracking-wider border border-blue-200 dark:border-blue-800/50 flex items-center gap-1.5">
          <ShieldCheck class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
          <span>Event Staff</span>
        </span>
        <span class="text-xs text-slate-400 font-bold">•</span>
        <span class="text-xs text-slate-500 dark:text-[#a0a0a0] font-medium">
          {{ currentUser?.name || 'Staff' }}
        </span>
      </div>

      <!-- Compact Event Selector (Only shown if staff is assigned to multiple events) -->
      <div v-if="assignedEventsList.length > 1" class="flex items-center gap-2 self-start sm:self-auto">
        <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Event:</span>
        <select
          :value="currentEvent?.id"
          @change="handleEventSelect"
          class="px-3 py-1.5 bg-white dark:bg-[#1f1f1f] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer shadow-xs max-w-[220px] truncate"
        >
          <option v-for="ev in assignedEventsList" :key="ev.id" :value="ev.id">
            {{ ev.title }} ({{ ev.code }})
          </option>
        </select>
      </div>
    </div>

    <!-- ==================== PRIMARY ENTRANCE SCANNER STATION ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-sm space-y-6 text-center transition-colors">
      <!-- Assigned Event Details -->
      <div class="space-y-1.5 max-w-xl mx-auto">
        <span class="text-[10px] font-black uppercase text-blue-600 dark:text-blue-400 tracking-widest block">
          Assigned Event
        </span>
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight leading-tight">
          {{ currentEvent?.title || 'No Assigned Event' }}
        </h1>
        <p class="text-xs sm:text-sm text-slate-500 dark:text-[#a0a0a0] flex items-center justify-center gap-2 flex-wrap font-medium pt-1">
          <span v-if="currentEvent?.date" class="flex items-center gap-1">
            <Calendar class="w-3.5 h-3.5 text-slate-400" />
            <span>{{ formatPhilippineDate(currentEvent.date) }}</span>
            <span v-if="currentEvent?.startTime">· {{ currentEvent.startTime }}</span>
          </span>
          <span v-if="currentEvent?.venue" class="flex items-center gap-1">
            <span>·</span>
            <MapPin class="w-3.5 h-3.5 text-slate-400" />
            <span>{{ currentEvent.venue }}</span>
          </span>
        </p>
      </div>

      <!-- DOMINANT PRIMARY ACTION: SCAN QR CODE -->
      <div class="py-2">
        <button
          type="button"
          @click="showQRScanner = true"
          class="w-full sm:max-w-md mx-auto py-5 px-8 rounded-2xl bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white shadow-xl shadow-blue-500/25 hover:shadow-2xl hover:shadow-blue-500/30 transition-all cursor-pointer flex items-center justify-center gap-3.5 font-black text-base sm:text-lg tracking-wide group"
        >
          <Camera class="w-6 h-6 group-hover:scale-110 transition-transform" />
          <span>SCAN QR CODE</span>
        </button>

        <!-- Scanner Readiness Status -->
        <div class="mt-3.5 flex items-center justify-center gap-2">
          <span class="w-2.5 h-2.5 rounded-full bg-emerald-500 animate-pulse"></span>
          <span class="text-xs font-black uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            READY TO SCAN
          </span>
        </div>
      </div>

      <!-- Status & Result Indicators: Present Count & Last Scan Result -->
      <div class="pt-4 border-t border-slate-100 dark:border-[#282828] grid grid-cols-1 sm:grid-cols-2 gap-3 max-w-xl mx-auto">
        <!-- Present Count -->
        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-emerald-100 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <CheckCircle2 class="w-5 h-5" />
          </div>
          <div class="text-left">
            <span class="text-[10px] font-extrabold uppercase text-slate-400 block tracking-wider">Present Count</span>
            <span class="text-lg font-black text-emerald-600 dark:text-emerald-400 leading-tight block">
              {{ presentCount }} Attendees
            </span>
          </div>
        </div>

        <!-- Last Scan Result -->
        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <Clock class="w-5 h-5" />
          </div>
          <div class="text-left min-w-0 flex-1">
            <span class="text-[10px] font-extrabold uppercase text-slate-400 block tracking-wider">Last Scan Result</span>
            <span class="text-xs font-bold text-slate-800 dark:text-slate-200 truncate block leading-snug">
              {{ lastScanSummary }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- Primary Camera QR Scanner Modal -->
    <QRScannerModal :is-open="showQRScanner" @close="showQRScanner = false" />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
  Camera,
  ShieldCheck,
  Calendar,
  Clock,
  MapPin,
  CheckCircle2
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { formatPhilippineDate, formatPhilippineTime } from '../../utils/philippineTime';
import { EventItem } from '../../types';
import QRScannerModal from '../scanner/QRScannerModal.vue';

const { currentUser, currentEvent, setCurrentEvent, events, tickets, logs } = useAppStore();

const showQRScanner = ref(false);

// Assigned events list for logged-in staff member
const assignedEventsList = computed<EventItem[]>(() => {
  if (currentUser.value?.assignedEventIds && currentUser.value.assignedEventIds.length > 0) {
    const assigned = events.value.filter(e => currentUser.value?.assignedEventIds?.includes(e.id));
    if (assigned.length > 0) return assigned;
  }
  return events.value;
});

// Auto-select assigned active event on mount if none or unassigned is selected
const ensureAssignedEventSelected = () => {
  if (assignedEventsList.value.length > 0) {
    if (!currentEvent.value || !assignedEventsList.value.some(e => e.id === currentEvent.value?.id)) {
      setCurrentEvent(assignedEventsList.value[0]);
    }
  }
};

onMounted(() => {
  ensureAssignedEventSelected();
});

watch(
  () => assignedEventsList.value,
  () => {
    ensureAssignedEventSelected();
  },
  { immediate: true }
);

const handleEventSelect = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = assignedEventsList.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};

// Tickets for active event
const eventTickets = computed(() => {
  if (!currentEvent.value) return [];
  return tickets.value.filter(t => t.eventId === currentEvent.value?.id);
});

// Present attendee count (Status: PRESENT)
const presentCount = computed(() => {
  return eventTickets.value.filter(t => t.attendanceStatus === 'present' || (t.ticketStatus as string) === 'USED').length;
});

// Event specific scan logs for last scan display
const eventLogs = computed(() => {
  if (!currentEvent.value) return [];
  return logs.value.filter(l => l.eventId === currentEvent.value?.id);
});

// Last scan summary line
const lastScanSummary = computed(() => {
  if (eventLogs.value.length === 0) return 'No attendees scanned yet';
  const latest = eventLogs.value[0];
  const name = latest.attendeeName || 'Attendee';
  const status = latest.status === 'valid' ? 'PRESENT' : 'BLOCKED';
  const time = latest.timeIn || (latest.timestamp ? formatPhilippineTime(latest.timestamp) : '');
  return `${name} · ${status}${time ? ' · ' + time : ''}`;
});
</script>
