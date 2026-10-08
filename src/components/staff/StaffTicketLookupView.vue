<template>
  <div class="space-y-6 max-w-2xl mx-auto py-2">
    <!-- Top Header & Event Context -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-950/60 text-blue-800 dark:text-blue-300 text-[10px] font-black uppercase tracking-wider border border-blue-200 dark:border-blue-800/50">
            Event Staff
          </span>
          <span class="text-xs text-slate-400 font-bold">•</span>
          <span class="text-xs text-slate-500 dark:text-[#a0a0a0] font-medium">
            Verification Tool
          </span>
        </div>
        <h1 class="text-2xl font-black text-slate-900 dark:text-white tracking-tight">
          Manual Ticket Verification
        </h1>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
          Read-only registration inspection for troubleshooting.
        </p>
      </div>

      <button
        type="button"
        @click="$emit('navigate', 'staff_dashboard')"
        class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1.5 self-start sm:self-auto"
      >
        <Camera class="w-3.5 h-3.5" />
        <span>Open Scanner</span>
      </button>
    </div>

    <!-- Ticket Lookup Card -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-6 transition-colors">
      <div class="space-y-2">
        <h2 class="text-base font-black text-slate-900 dark:text-white flex items-center gap-2">
          <Search class="w-5 h-5 text-blue-600 dark:text-blue-400" />
          <span>Search Registered Pass / Ticket</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
          Enter a ticket control number or NCST Student ID to verify its issuance status.
        </p>
      </div>

      <!-- Search Form -->
      <form @submit.prevent="handleManualLookupSubmit" class="flex gap-2">
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            v-model="manualLookupInput"
            placeholder="Enter Ticket # or Student ID (e.g. 2024-00123)..."
            class="w-full pl-10 pr-3.5 py-3 bg-slate-50 dark:bg-[#222222] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
          />
        </div>
        <button
          type="submit"
          :disabled="!manualLookupInput.trim()"
          class="px-6 py-3 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white rounded-xl text-xs font-bold transition cursor-pointer disabled:opacity-50 disabled:cursor-not-allowed shadow-md shadow-blue-500/20"
        >
          Verify
        </button>
      </form>

      <!-- Lookup Result Card -->
      <div
        v-if="manualLookupResult"
        class="p-5 rounded-2xl border transition-all"
        :class="manualLookupResult.found
          ? 'bg-slate-50 dark:bg-[#222222] border-slate-200 dark:border-[#333333]'
          : 'bg-rose-50/50 dark:bg-rose-950/20 border-rose-200 dark:border-rose-900/40'"
      >
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-[#2e2e2e]">
          <span class="font-black uppercase text-[10px] tracking-wider text-slate-400">Lookup Result</span>
          <span
            class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
            :class="manualLookupResult.found ? 'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/80 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' : 'bg-rose-100 text-rose-700 dark:bg-rose-950/80 dark:text-rose-300 border border-rose-300 dark:border-rose-800'"
          >
            {{ manualLookupResult.found ? 'REGISTERED' : 'NOT FOUND' }}
          </span>
        </div>

        <div v-if="manualLookupResult.ticket" class="space-y-3 pt-3">
          <div>
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Attendee Name</span>
            <p class="font-black text-slate-900 dark:text-white text-base">
              {{ manualLookupResult.ticket.currentAttendeeName }}
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
            <div class="p-3 rounded-xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e]">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Ticket / Control No.</span>
              <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs">
                {{ manualLookupResult.ticket.ticketNumber || manualLookupResult.ticket.id }}
              </span>
            </div>

            <div v-if="manualLookupResult.ticket.attendeeStudentId" class="p-3 rounded-xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e]">
              <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Student ID</span>
              <span class="font-mono font-bold text-slate-800 dark:text-slate-200 text-xs">
                {{ manualLookupResult.ticket.attendeeStudentId }}
              </span>
            </div>
          </div>

          <div class="p-3 rounded-xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] flex items-center justify-between">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400">Current Entrance Status:</span>
            <span
              class="px-2.5 py-0.5 rounded text-[11px] font-black uppercase"
              :class="manualLookupResult.ticket.attendanceStatus === 'present'
                ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                : 'bg-slate-200 dark:bg-[#333] text-slate-700 dark:text-slate-300'"
            >
              {{ manualLookupResult.ticket.attendanceStatus === 'present' ? 'Already Entered (PRESENT)' : 'Not Yet Entered' }}
            </span>
          </div>
        </div>

        <p v-else class="text-slate-600 dark:text-slate-400 text-xs pt-3">
          {{ manualLookupResult.message }}
        </p>

        <div class="mt-4 pt-3 border-t border-slate-200 dark:border-[#2e2e2e] flex items-center gap-2 text-[11px] text-slate-400">
          <ShieldAlert class="w-3.5 h-3.5 text-amber-500 shrink-0" />
          <span>Verification only. Official entrance attendance requires scanning the valid QR code.</span>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { Search, Camera, ShieldAlert } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem } from '../../types';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const { lookupTicketInfo } = useAppStore();

const manualLookupInput = ref('');
const manualLookupResult = ref<{
  found: boolean;
  ticket?: any;
  event?: EventItem;
  message: string;
} | null>(null);

const handleManualLookupSubmit = () => {
  if (!manualLookupInput.value.trim()) return;
  const res = lookupTicketInfo(manualLookupInput.value.trim());
  manualLookupResult.value = res;
};
</script>
