<template>
  <div
    class="relative overflow-hidden rounded-3xl bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] shadow-xl hover:shadow-2xl transition-all duration-300 max-w-md w-full mx-auto group"
  >
    <!-- Top Accent Ribbon / Custom Design Banner Header -->
    <div v-if="safeEvent.ticketDesignUrl || safeEvent.bannerUrl" class="relative h-20 w-full overflow-hidden bg-slate-900 border-b border-slate-200 dark:border-slate-800">
      <img
        :src="safeEvent.ticketDesignUrl || safeEvent.bannerUrl"
        alt="Ticket Artwork"
        class="w-full h-full object-cover opacity-80 group-hover:scale-105 transition-transform duration-500"
        :style="{ objectPosition: `center ${safeEvent.ticketDesignOffsetY ?? 20}%` }"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent" />
      <div class="absolute bottom-2 left-4">
        <span class="text-[9px] uppercase font-bold tracking-widest px-2 py-0.5 rounded bg-black/60 text-amber-300 border border-amber-400/30 backdrop-blur-xs">
          Official Event Pass
        </span>
      </div>
    </div>
    <div v-else class="h-2.5 w-full bg-blue-600" />

    <!-- Card Body -->
    <div class="p-6">
      <!-- Top Bar with Official NCST Logo & Status Badges -->
      <div class="flex items-center justify-between gap-2 mb-4">
        <div class="flex items-center gap-2">
          <img
            src="/ncst-logo.png"
            alt="NCST Logo"
            class="w-7 h-7 object-contain rounded-full shadow-xs bg-white p-0.5"
          />
          <span class="text-[11px] font-extrabold tracking-wider uppercase px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-500/10 text-blue-700 dark:text-blue-400 border border-blue-200 dark:border-blue-500/20">
            {{ safeEvent.category || 'Campus' }} Event
          </span>
          <span class="text-[9.5px] font-black tracking-widest uppercase px-2 py-0.5 rounded bg-amber-400 text-slate-950 shadow-xs">
            LIVE @ NCST
          </span>
        </div>
        <div class="flex items-center gap-1.5">
          <StatusBadge type="ticket" :status="ticket.ticketStatus" />
          <StatusBadge type="attendance" :status="ticket.attendanceStatus" />
        </div>
      </div>

      <!-- Event Title -->
      <h3 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
        {{ safeEvent.title || ticket.eventTitle || 'Campus Event' }}
      </h3>
      <p class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-1 line-clamp-1">{{ safeEvent.venue || 'Campus Grounds' }}</p>

      <!-- Attendee Info & Photo ID -->
      <div class="mt-5 p-4 rounded-2xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] flex items-center gap-3.5 transition-colors">
        <UserAvatar :src="ticket.attendeePhotoUrl" :alt="ticket.currentAttendeeName" size="lg" shape="rounded" />

        <div class="flex-1 min-w-0">
          <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 tracking-wider block">
            Official Attendee Pass
          </span>
          <h4 class="text-sm font-bold text-slate-900 dark:text-white truncate">
            {{ ticket.currentAttendeeName }}
          </h4>
          <p class="text-xs text-blue-600 dark:text-blue-400 font-mono font-bold">
            {{ ticket.attendeeStudentId ? `ID: ${ticket.attendeeStudentId}` : ticket.attendeeEmail || 'Registered Attendee' }}
          </p>
          <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3] truncate">
            <span v-if="ticket.course">{{ ticket.course }} {{ ticket.yearLevel ? `• ${ticket.yearLevel}` : '' }} {{ ticket.section ? `• Sec ${ticket.section}` : '' }}</span>
            <span v-else>{{ ticket.attendeeDepartment || 'Student Body' }}</span>
          </p>
        </div>
      </div>

      <!-- Event Lifecycle Banner Alerts -->
      <div v-if="isCancelledEvent" class="mt-3 p-3 bg-rose-500/10 border border-rose-500/30 rounded-2xl flex items-center gap-2.5 text-rose-600 dark:text-rose-400">
        <AlertCircle class="w-4 h-4 shrink-0" />
        <div class="text-[11px] leading-tight">
          <strong class="font-bold uppercase tracking-wider block">EVENT CANCELLED</strong>
          <span class="text-[10px] text-rose-500/80">This event has been cancelled and this pass is no longer valid for venue admission.</span>
        </div>
      </div>
      <div v-else-if="isCompletedEvent" class="mt-3 p-2.5 bg-slate-100 dark:bg-[#2c2c2c] border border-slate-200 dark:border-slate-700 rounded-2xl flex items-center gap-2 text-slate-600 dark:text-slate-400 text-xs">
        <span class="text-[10px] font-bold uppercase tracking-wider">COMPLETED EVENT • ARCHIVED RECORD</span>
      </div>

      <!-- QR Code Center Hub (Always High-Contrast White Background for Camera Scanning) -->
      <div class="my-6 flex flex-col items-center justify-center">
        <div 
          @click="showFocusQR = true"
          class="relative p-4 bg-white rounded-2xl shadow-md border border-slate-200 dark:border-slate-700 transition-transform group-hover:scale-105 duration-300 cursor-pointer"
          title="Click to enlarge QR for scanning"
        >
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            :alt="ticket.ticketNumber || ticket.id"
            class="w-40 h-40 object-contain"
          />
          <div v-else class="w-40 h-40 flex items-center justify-center text-slate-400 text-xs">
            Generating QR...
          </div>

          <!-- Overlay if Void / Cancelled -->
          <div v-if="isVoid || isCancelledEvent" class="absolute inset-0 bg-rose-950/90 rounded-2xl flex flex-col items-center justify-center p-2 text-center text-rose-300">
            <AlertCircle class="w-8 h-8 text-rose-400 mb-1" />
            <span class="text-xs font-black uppercase">VOID / CANCELLED</span>
          </div>
        </div>

        <div class="mt-3 text-center space-y-1.5">
          <div>
            <span class="text-xs font-mono font-bold tracking-widest text-blue-700 dark:text-blue-300 bg-blue-50 dark:bg-blue-950/60 px-3 py-1 rounded-full border border-blue-200 dark:border-blue-800">
              {{ ticket.ticketNumber || ticket.id }}
            </span>
          </div>
          <p class="text-[10px] text-slate-500 dark:text-[#b3b3b3]">
            Present this official QR pass to Event Staff at the venue entrance
          </p>
          <button
            type="button"
            @click="showFocusQR = true"
            class="inline-flex items-center gap-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer pt-0.5"
          >
            <Maximize2 class="w-3 h-3" />
            <span>Show Large QR</span>
          </button>
        </div>
      </div>

      <!-- Details Grid -->
      <div class="grid grid-cols-2 gap-2 text-xs py-3 border-y border-dashed border-slate-200 dark:border-[#333333]">
        <div>
          <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-semibold">Event Date & Time</span>
          <span class="font-semibold text-slate-800 dark:text-slate-200">
            {{ formatPhilippineDate(safeEvent.date) }}
          </span>
          <span class="text-slate-500 dark:text-[#b3b3b3] text-[11px] block">{{ safeEvent.startTime || '08:00 AM' }} - {{ safeEvent.endTime || '05:00 PM' }}</span>
        </div>
        <div>
          <span class="text-slate-400 dark:text-slate-500 block text-[10px] uppercase font-semibold">Attendance Status</span>
          <div v-if="isPresent" class="space-y-0.5">
            <span class="text-emerald-600 dark:text-emerald-400 font-extrabold flex items-center gap-1.5 text-xs">
              <span class="w-2 h-2 rounded-full bg-emerald-500 shrink-0"></span>
              PRESENT
            </span>
            <span v-if="ticket.timeIn" class="text-[11px] text-slate-500 dark:text-slate-400 block font-mono font-semibold">
              Entered: {{ formatPhilippineTime(ticket.timeIn) }}
            </span>
          </div>
          <div v-else class="space-y-0.5">
            <span class="text-amber-600 dark:text-amber-400 font-bold flex items-center gap-1.5 text-xs">
              <span class="w-2 h-2 rounded-full bg-amber-500 shrink-0"></span>
              NOT YET ENTERED
            </span>
          </div>
        </div>
      </div>

      <!-- Institutional Policy Footer -->
      <div class="mt-4 p-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-xl text-center">
        <span class="text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-[#999999]">
          OFFICIAL PASS • NON-TRANSFERABLE
        </span>
        <p class="text-[9px] text-slate-400 dark:text-slate-500 mt-0.5">
          This digital pass is exclusively valid for {{ ticket.currentAttendeeName }}
        </p>
      </div>

      <!-- Official Print Button strictly restricted to Cashier -->
      <div v-if="isCashier" class="mt-4">
        <button
          @click="showPrintModal = true"
          class="w-full flex items-center justify-center gap-1.5 py-2.5 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer"
        >
          <Printer class="w-3.5 h-3.5" />
          <span>Print Pass</span>
        </button>
      </div>
    </div>

    <!-- Mobile Focus / Fullscreen QR Modal -->
    <div
      v-if="showFocusQR"
      class="fixed inset-0 z-50 bg-black/80 backdrop-blur-sm flex items-center justify-center p-4"
      @click.self="showFocusQR = false"
    >
      <div class="bg-white dark:bg-[#1e1e1e] rounded-3xl max-w-sm w-full p-6 text-center shadow-2xl border border-slate-200 dark:border-slate-800 space-y-4">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-extrabold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
            Official QR Pass
          </span>
          <button
            @click="showFocusQR = false"
            class="p-1 rounded-full text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition cursor-pointer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <div>
          <h3 class="font-extrabold text-slate-900 dark:text-white text-base leading-snug">
            {{ safeEvent.title || ticket.eventTitle || 'Campus Event' }}
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
            {{ ticket.currentAttendeeName }} • ID: {{ ticket.attendeeStudentId || 'Attendee' }}
          </p>
        </div>

        <!-- High-contrast QR with max optical size -->
        <div class="p-4 bg-white rounded-2xl border-2 border-slate-200 shadow-inner flex justify-center">
          <img
            v-if="qrDataUrl"
            :src="qrDataUrl"
            :alt="ticket.ticketNumber || ticket.id"
            class="w-64 h-64 object-contain"
          />
        </div>

        <div class="space-y-1">
          <span class="font-mono font-extrabold text-blue-600 dark:text-blue-400 text-sm tracking-widest block">
            {{ ticket.ticketNumber || ticket.id }}
          </span>
          <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider block">
            OFFICIAL PASS • NON-TRANSFERABLE
          </span>
        </div>

        <button
          @click="showFocusQR = false"
          class="w-full py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#383838] text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>

    <!-- Printable Ticket Modal -->
    <PrintableTicketModal
      v-if="showPrintModal"
      :is-open="showPrintModal"
      :ticket="ticket"
      :event="safeEvent"
      @close="showPrintModal = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Printer, CheckCircle2, AlertCircle, Maximize2, X } from 'lucide-vue-next';
import { Ticket, EventItem } from '../../types';
import { formatPhilippineDate, formatPhilippineTime } from '../../utils/philippineTime';
import { useAppStore } from '../../composables/useAppStore';
import StatusBadge from '../common/StatusBadge.vue';
import UserAvatar from '../common/UserAvatar.vue';
import PrintableTicketModal from './PrintableTicketModal.vue';

const { currentUser } = useAppStore();
const isCashier = computed(() => currentUser.value?.role === 'cashier');

const props = defineProps<{
  ticket: Ticket;
  event?: EventItem | null;
}>();

const safeEvent = computed<EventItem>(() => {
  return props.event || {
    id: props.ticket.eventId || 'evt-default',
    title: props.ticket.eventTitle || 'Campus Event',
    code: 'EVT-SCH',
    description: '',
    category: 'Campus',
    venue: 'Campus Grounds',
    date: '2026-10-05',
    startTime: '08:00 AM',
    endTime: '05:00 PM',
    capacity: 1000,
    ticketPrice: props.ticket.price || 0,
    status: 'ongoing',
    bannerUrl: '',
    organizer: 'NCST Student Affairs'
  };
});

const showPrintModal = ref(false);
const showFocusQR = ref(false);
const qrDataUrl = ref<string>('');

const isPresent = computed(() => props.ticket.attendanceStatus === 'present');
const isVoid = computed(() => {
  const s = String(props.ticket.ticketStatus || '').toLowerCase();
  return s === 'cancelled' || s === 'void' || s === 'refunded';
});
const isCancelledEvent = computed(() => {
  const s = String(safeEvent.value.status || '').toLowerCase();
  return s === 'cancelled';
});
const isCompletedEvent = computed(() => {
  const s = String(safeEvent.value.status || '').toLowerCase();
  return s === 'completed' || s === 'archived';
});

const generateQRCode = async () => {
  const payload = {
    ticketNumber: props.ticket.ticketNumber || props.ticket.id,
    ticketId: props.ticket.id,
    eventId: props.ticket.eventId,
    eventTitle: props.ticket.eventTitle,
    attendeeName: props.ticket.currentAttendeeName,
    studentId: props.ticket.attendeeStudentId,
    course: props.ticket.course,
    yearLevel: props.ticket.yearLevel,
    section: props.ticket.section,
    price: props.ticket.price,
    status: props.ticket.ticketStatus,
  };
  const token = JSON.stringify(payload);

  try {
    qrDataUrl.value = await QRCode.toDataURL(token, {
      errorCorrectionLevel: 'M',
      margin: 1,
      width: 250,
      color: {
        dark: '#0f172a',
        light: '#ffffff',
      },
    });
  } catch (err) {
    console.error('QR Error:', err);
  }
};

onMounted(() => generateQRCode());
watch(() => props.ticket, () => generateQRCode(), { deep: true });
</script>
