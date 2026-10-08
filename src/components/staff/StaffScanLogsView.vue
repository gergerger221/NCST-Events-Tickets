<template>
  <div class="space-y-6 max-w-5xl mx-auto py-2">
    <!-- Top Header & Event Context -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 text-[10px] font-black uppercase tracking-wider border border-blue-200 dark:border-blue-800/50">
            Event Staff
          </span>
          <span class="text-xs text-slate-400 font-bold">•</span>
          <span class="text-xs text-slate-500 dark:text-[#a0a0a0] font-medium">
            Scan History
          </span>
        </div>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
          Recent Entrance Scans
        </h1>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
          Chronological entrance scan activity for <strong>{{ currentEvent?.title || 'Selected Event' }}</strong>.
        </p>
      </div>

      <!-- Action & Event Selector -->
      <div class="flex items-center gap-2.5 self-start sm:self-auto">
        <!-- Event Switcher if assigned to multiple -->
        <div v-if="assignedEventsList.length > 1" class="flex items-center gap-2">
          <span class="text-[11px] font-bold text-slate-400 uppercase tracking-wider">Event:</span>
          <select
            :value="currentEvent?.id"
            @change="handleEventSelect"
            class="px-3 py-1.5 bg-white dark:bg-[#1f1f1f] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer shadow-xs max-w-[200px] truncate"
          >
            <option v-for="ev in assignedEventsList" :key="ev.id" :value="ev.id">
              {{ ev.title }} ({{ ev.code }})
            </option>
          </select>
        </div>

        <button
          type="button"
          @click="$emit('navigate', 'staff_dashboard')"
          class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1.5"
        >
          <Camera class="w-3.5 h-3.5" />
          <span>Open Scanner</span>
        </button>
      </div>
    </div>

    <!-- Offline Resilience Banner if queued scans exist -->
    <div
      v-if="queuedCount > 0"
      class="p-4 bg-amber-500/10 border border-amber-500/30 rounded-2xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs"
    >
      <div class="flex items-center gap-2.5 text-amber-800 dark:text-amber-200">
        <WifiOff class="w-4 h-4 text-amber-500 shrink-0" />
        <div>
          <span class="font-bold">{{ queuedCount }} Offline Scan{{ queuedCount > 1 ? 's' : '' }} Pending Cloud Sync</span>
          <p class="text-[11px] text-amber-700 dark:text-amber-300">
            {{ isOnline ? 'Network connection active. You can sync now.' : 'Scans were recorded offline and will auto-sync with MySQL once connected.' }}
          </p>
        </div>
      </div>
      <button
        v-if="isOnline"
        type="button"
        @click="flushQueue"
        :disabled="isSyncing"
        class="px-3.5 py-1.5 bg-amber-600 hover:bg-amber-700 text-white font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5 disabled:opacity-50 shrink-0"
      >
        <RotateCw class="w-3.5 h-3.5" :class="{ 'animate-spin': isSyncing }" />
        <span>{{ isSyncing ? 'Syncing...' : 'Sync Cloud Records' }}</span>
      </button>
    </div>

    <!-- Main Table Container -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-6 shadow-xs space-y-4 transition-colors">
      <!-- Search & Filters Toolbar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 border-b border-slate-100 dark:border-[#2a2a2a] pb-4">
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300">
            Total Scanned:
          </span>
          <span class="px-2 py-0.5 rounded-md bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 text-xs font-black font-mono">
            {{ filteredEventLogs.length }}
          </span>
        </div>

        <!-- Search Bar -->
        <div class="relative min-w-[240px] sm:w-72">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search attendee, ticket, or ID..."
            class="w-full pl-8 pr-3 py-1.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
          />
        </div>
      </div>

      <!-- Chronological Scan Table -->
      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#333333]">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-[#222222] border-b border-slate-200 dark:border-[#333333] text-[10px] font-black uppercase text-slate-400 tracking-wider">
              <th class="py-2.5 px-3.5">Time In (PHT)</th>
              <th class="py-2.5 px-3.5">Ticket / Control No.</th>
              <th class="py-2.5 px-3.5">Attendee Name</th>
              <th class="py-2.5 px-3.5">Classification</th>
              <th class="py-2.5 px-3.5 text-right">Validation Status</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
            <tr
              v-for="log in filteredEventLogs"
              :key="log.id"
              class="hover:bg-slate-50 dark:hover:bg-[#222222] transition"
            >
              <!-- Time In -->
              <td class="py-3 px-3.5 font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ log.timeIn || '—' }}
              </td>

              <!-- Ticket / Control Number -->
              <td class="py-3 px-3.5 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                {{ log.ticketNumber || log.ticketId }}
              </td>

              <!-- Attendee Name & ID -->
              <td class="py-3 px-3.5 font-bold text-slate-900 dark:text-white">
                <div class="truncate max-w-[220px]">{{ log.attendeeName }}</div>
                <div v-if="log.studentId" class="text-[10px] text-slate-400 font-mono font-normal">
                  ID: {{ log.studentId }}
                </div>
              </td>

              <!-- Classification -->
              <td class="py-3 px-3.5 whitespace-nowrap">
                <span
                  class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                  :class="log.attendeeType === 'OUTSIDER'
                    ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800'
                    : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-300 dark:border-blue-800'"
                >
                  {{ log.attendeeType || 'STUDENT' }}
                </span>
              </td>

              <!-- Validation Status -->
              <td class="py-3 px-3.5 text-right whitespace-nowrap">
                <span
                  v-if="log.status === 'valid'"
                  class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60"
                >
                  PRESENT
                </span>
                <span
                  v-else
                  class="px-2.5 py-0.5 rounded text-[10px] font-black uppercase bg-rose-100 dark:bg-rose-950/60 text-rose-700 dark:text-rose-300 border border-rose-200 dark:border-rose-800/60"
                >
                  {{ log.denialReason?.includes('ALREADY') ? 'DUPLICATE' : 'INVALID' }}
                </span>
              </td>
            </tr>

            <tr v-if="filteredEventLogs.length === 0">
              <td colspan="5" class="py-12 text-center text-slate-400">
                <Clock class="w-8 h-8 mx-auto mb-2 opacity-40" />
                <p class="font-bold text-xs">No scan records found for this event.</p>
                <p class="text-[11px] text-slate-400 mt-0.5">Scanned QR codes will appear here chronologically.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Camera, Clock, Search, WifiOff, RotateCw } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { useOfflineScanner } from '../../composables/useOfflineScanner';
import { EventItem } from '../../types';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const { currentUser, currentEvent, setCurrentEvent, events, logs } = useAppStore();
const { isOnline, isSyncing, queuedCount, flushQueue } = useOfflineScanner();

const searchQuery = ref('');

// Assigned events list for logged-in staff member
const assignedEventsList = computed<EventItem[]>(() => {
  if (currentUser.value?.assignedEventIds && currentUser.value.assignedEventIds.length > 0) {
    const assigned = events.value.filter(e => currentUser.value?.assignedEventIds?.includes(e.id));
    if (assigned.length > 0) return assigned;
  }
  return events.value;
});

const handleEventSelect = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = assignedEventsList.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};

// Event specific scan logs
const eventLogs = computed(() => {
  if (!currentEvent.value) return [];
  return logs.value.filter(l => l.eventId === currentEvent.value?.id);
});

// Filtered logs by search query
const filteredEventLogs = computed(() => {
  return eventLogs.value.filter(l => {
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase();
      const matchName = (l.attendeeName || '').toLowerCase().includes(q);
      const matchTix = (l.ticketNumber || l.ticketId || '').toLowerCase().includes(q);
      const matchStudentId = (l.studentId || '').toLowerCase().includes(q);
      if (!matchName && !matchTix && !matchStudentId) return false;
    }
    return true;
  });
});
</script>
