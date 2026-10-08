<template>
  <Modal :is-open="isOpen" @close="$emit('close')" max-width="lg">
    <div v-if="result" class="text-center py-2">
      <!-- 1. VALID TICKET -->
      <div v-if="result.isValid" class="relative mb-5">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-emerald-100 dark:bg-emerald-500/20 border-3 border-emerald-500 text-emerald-600 dark:text-emerald-400 mb-3 shadow-lg animate-pulse">
          <CheckCircle2 class="w-12 h-12" />
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-emerald-600 dark:text-emerald-400 tracking-tight uppercase">
          ✅ TICKET VALID
        </h2>
        <p class="text-xs font-black text-emerald-700 dark:text-emerald-300 mt-1 uppercase tracking-widest">
          ENTRY ALLOWED • ATTENDANCE RECORDED
        </p>

        <!-- Offline Queue Notice Badge -->
        <div
          v-if="result.isOfflineRecorded"
          class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 text-[11px] font-bold mt-2.5 shadow-xs"
        >
          <WifiOff class="w-3.5 h-3.5 shrink-0" />
          <span>Offline Scan — Queued locally for cloud auto-sync</span>
        </div>
      </div>

      <!-- 2. DUPLICATE SCAN (TICKET ALREADY USED) -->
      <div v-else-if="isDuplicateScan" class="relative mb-5">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-amber-100 dark:bg-amber-500/20 border-3 border-amber-500 text-amber-600 dark:text-amber-400 mb-3 shadow-lg">
          <AlertTriangle class="w-12 h-12" />
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-amber-600 dark:text-amber-400 tracking-tight uppercase">
          ⚠️ TICKET ALREADY USED
        </h2>
        <p class="text-xs font-black text-amber-700 dark:text-amber-300 mt-1 uppercase tracking-widest">
          DUPLICATE SCAN BLOCKED • NO DUPLICATE RECORD CREATED
        </p>
      </div>

      <!-- 3. INVALID TICKET / WRONG EVENT / VOID -->
      <div v-else class="relative mb-5">
        <div class="inline-flex items-center justify-center w-20 h-20 rounded-full bg-rose-100 dark:bg-rose-500/20 border-3 border-rose-500 text-rose-600 dark:text-rose-400 mb-3 shadow-lg">
          <XCircle class="w-12 h-12" />
        </div>
        <h2 class="text-2xl sm:text-3xl font-black text-rose-600 dark:text-rose-400 tracking-tight uppercase">
          ❌ INVALID TICKET
        </h2>
        <p class="text-xs font-black text-rose-700 dark:text-rose-300 mt-1 uppercase tracking-widest">
          ENTRY DENIED
        </p>
        <div v-if="result.reason" class="mt-3 p-3.5 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800/80 rounded-2xl text-xs font-bold text-rose-800 dark:text-rose-200 leading-relaxed max-w-md mx-auto">
          <ShieldAlert class="w-4 h-4 inline mr-1.5 text-rose-600 dark:text-rose-400 -mt-0.5" />
          {{ result.reason }}
        </div>
      </div>

      <!-- Attendee Information Card: Focused on Entry Verification -->
      <div v-if="result.ticket" class="bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl p-5 text-left mb-6 space-y-4 transition-colors">
        
        <!-- Attendee Primary Verification Header -->
        <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-[#333333] gap-2 flex-wrap">
          <div class="min-w-0">
            <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">
              Verified Attendee
            </span>
            <h3 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white truncate">
              {{ result.ticket.currentAttendeeName }}
            </h3>
            <p v-if="result.ticket.attendeeStudentId" class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400 mt-0.5">
              Student ID: {{ result.ticket.attendeeStudentId }}
            </p>
          </div>

          <span
            class="px-3 py-1 rounded-full text-xs font-black uppercase tracking-wider shrink-0"
            :class="isOutsider
              ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800'
              : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-300 dark:border-blue-800'"
          >
            {{ isOutsider ? 'OUTSIDER' : 'STUDENT' }}
          </span>
        </div>

        <!-- Verification Data Grid -->
        <div class="grid grid-cols-2 gap-3.5 text-xs">
          <div>
            <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-extrabold uppercase">Ticket / Control No.</span>
            <span class="font-mono font-black text-blue-600 dark:text-blue-400 text-sm block mt-0.5">
              {{ result.ticket.ticketNumber || result.ticket.id }}
            </span>
          </div>

          <div>
            <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-extrabold uppercase">
              {{ isDuplicateScan ? 'Original Time In (PHT)' : 'Time In (PHT)' }}
            </span>
            <span class="font-mono font-black text-emerald-600 dark:text-emerald-400 text-sm flex items-center gap-1 mt-0.5">
              <Clock class="w-3.5 h-3.5" />
              {{ formatPhilippineTime(result.ticket.timeIn || result.timestamp) }}
            </span>
          </div>

          <div class="col-span-2">
            <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-extrabold uppercase">Event</span>
            <span class="font-bold text-slate-800 dark:text-slate-200 truncate block mt-0.5">
              {{ result.event ? result.event.title : (result.ticket.eventTitle || result.ticket.eventId) }}
            </span>
          </div>
        </div>

        <!-- Duplicate warning callout if already used -->
        <div v-if="isDuplicateScan" class="p-3.5 bg-amber-500/10 border border-amber-500/30 rounded-xl text-xs text-amber-800 dark:text-amber-200 text-left">
          <p class="font-black flex items-center gap-1.5">
            <AlertTriangle class="w-4 h-4 text-amber-500 shrink-0" />
            <span>TICKET ALREADY USED: Scanned previously</span>
          </p>
          <p class="text-[11px] mt-1 text-amber-700 dark:text-amber-300 font-medium">
            Original Time In was recorded at <strong>{{ formatPhilippineTime(result.ticket.timeIn) }} (PHT)</strong>. No duplicate attendance record was created.
          </p>
        </div>
      </div>

      <!-- Auto-dismiss Countdown Progress Bar (Continuous Scan Mode) -->
      <div v-if="autoResumeSeconds > 0" class="mb-4">
        <div class="flex items-center justify-between text-[11px] font-bold text-slate-500 dark:text-slate-400 mb-1 px-1">
          <span class="flex items-center gap-1.5">
            <span class="w-2 h-2 rounded-full bg-blue-500 animate-ping"></span>
            <span>Continuous Mode Active</span>
          </span>
          <span>Next scan in {{ autoResumeSeconds.toFixed(1) }}s</span>
        </div>
        <div class="w-full bg-slate-200 dark:bg-[#333] h-1.5 rounded-full overflow-hidden">
          <div
            class="bg-blue-600 dark:bg-blue-400 h-full transition-all duration-100 ease-linear"
            :style="{ width: `${autoResumePercent}%` }"
          />
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center gap-3 justify-end pt-2">
        <button
          type="button"
          @click="cancelAutoResumeAndClose"
          class="flex-1 px-4 py-2.5 rounded-xl border border-slate-300 dark:border-[#3a3a3a] bg-slate-100 dark:bg-[#2c2c2c] text-xs font-bold text-slate-700 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-[#333333] transition cursor-pointer"
        >
          {{ autoResumeSeconds > 0 ? 'Pause / Close' : 'Close' }}
        </button>
        <button
          type="button"
          @click="proceedToScanNext"
          class="flex-1 flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl text-xs font-black text-white transition shadow-md cursor-pointer"
          :class="result.isValid ? 'bg-emerald-600 hover:bg-emerald-700' : isDuplicateScan ? 'bg-amber-600 hover:bg-amber-700' : 'bg-blue-600 hover:bg-blue-700'"
        >
          <span>Scan Next Ticket</span>
          <ArrowRight class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { computed, ref, watch, onBeforeUnmount } from 'vue';
import { CheckCircle2, XCircle, AlertTriangle, Clock, ShieldAlert, ArrowRight, WifiOff } from 'lucide-vue-next';
import { ScanValidationResult } from '../../types';
import { formatPhilippineTime } from '../../utils/philippineTime';
import { useOfflineScanner } from '../../composables/useOfflineScanner';
import Modal from '../common/Modal.vue';

const props = defineProps<{
  result: ScanValidationResult | null;
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'scan-next'): void;
}>();

const { continuousScanMode } = useOfflineScanner();

const autoResumeSeconds = ref(0);
const autoResumePercent = ref(100);
let autoResumeTimer: any = null;

const isDuplicateScan = computed(() => {
  return !props.result?.isValid && (
    props.result?.reason?.includes('ALREADY USED') ||
    props.result?.reason?.includes('Already used') ||
    props.result?.reason?.includes('already used')
  );
});

const isOutsider = computed(() => {
  if (!props.result?.ticket) return false;
  return props.result.attendeeType === 'OUTSIDER' || props.result.ticket.attendeeType === 'OUTSIDER';
});

const startAutoResumeCountdown = () => {
  clearAutoResume();
  if (!continuousScanMode.value || !props.result?.isValid) return;

  const totalDuration = 1800; // 1.8 seconds display
  const startTime = Date.now();
  autoResumeSeconds.value = totalDuration / 1000;
  autoResumePercent.value = 100;

  autoResumeTimer = setInterval(() => {
    const elapsed = Date.now() - startTime;
    const remaining = Math.max(0, totalDuration - elapsed);
    autoResumeSeconds.value = remaining / 1000;
    autoResumePercent.value = (remaining / totalDuration) * 100;

    if (remaining <= 0) {
      clearAutoResume();
      emit('scan-next');
    }
  }, 50);
};

const clearAutoResume = () => {
  if (autoResumeTimer) {
    clearInterval(autoResumeTimer);
    autoResumeTimer = null;
  }
  autoResumeSeconds.value = 0;
  autoResumePercent.value = 100;
};

const cancelAutoResumeAndClose = () => {
  clearAutoResume();
  emit('close');
};

const proceedToScanNext = () => {
  clearAutoResume();
  emit('scan-next');
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      startAutoResumeCountdown();
    } else {
      clearAutoResume();
    }
  }
);

onBeforeUnmount(() => {
  clearAutoResume();
});
</script>
