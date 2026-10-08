<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 flex-wrap">
          <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
            Issued Tickets Registry
          </h2>
          <span class="text-[10px] font-black uppercase px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-[#252525] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">
            Official Registry
          </span>
        </div>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
          Search, filter, view details, and manage all previously issued event tickets.
        </p>
      </div>

      <div class="flex items-center gap-2">
        <button
          @click="handleExportExcel"
          class="flex items-center gap-2 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
          title="Export Issued Tickets to Excel"
        >
          <FileSpreadsheet class="w-4 h-4" />
          <span>Export Excel</span>
        </button>
      </div>
    </div>

    <!-- Filter Bar Component -->
    <FilterBar />

    <!-- Issued Tickets Table -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl overflow-hidden shadow-xs transition-colors flex flex-col">
      <div class="overflow-x-auto overflow-y-auto max-h-[480px] custom-scrollbar">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-[#222222] text-slate-600 dark:text-[#a0a0a0] uppercase tracking-wider text-[10px] font-extrabold border-b border-slate-200 dark:border-[#2e2e2e] shadow-xs">
            <tr>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Ticket #</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Attendee</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Type</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Event</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Payment</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Status</th>
              <th class="py-3 px-3.5 text-center bg-slate-50 dark:bg-[#222222]">QR Label</th>
              <th class="py-3 px-3.5 bg-slate-50 dark:bg-[#222222]">Issued At</th>
              <th class="py-3 px-3.5 text-right bg-slate-50 dark:bg-[#222222]">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
            <tr
              v-for="ticket in filteredTickets"
              :key="ticket.id"
              class="hover:bg-slate-50 dark:hover:bg-[#222222] transition-colors group cursor-pointer"
              @click="openDetail(ticket)"
            >
              <!-- 1. Ticket Number -->
              <td class="py-3.5 px-3.5 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                {{ ticket.ticketNumber || ticket.id }}
              </td>

              <!-- 2. Attendee -->
              <td class="py-3.5 px-3.5">
                <div class="flex items-center gap-2.5">
                  <UserAvatar :src="ticket.attendeePhotoUrl" :alt="ticket.currentAttendeeName" size="sm" shape="circle" />
                  <div class="min-w-0">
                    <div class="font-bold text-slate-900 dark:text-white truncate max-w-[160px] group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors">
                      {{ ticket.currentAttendeeName }}
                    </div>
                    <div class="text-[10px] text-slate-400 font-mono">
                      {{ ticket.attendeeStudentId || 'Guest Attendee' }}
                      <span v-if="ticket.course" class="text-slate-400 font-sans ml-1">({{ ticket.course }})</span>
                    </div>
                  </div>
                </div>
              </td>

              <!-- 3. Type -->
              <td class="py-3.5 px-3.5 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block"
                  :class="ticket.attendeeType === 'OUTSIDER'
                    ? 'bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800'
                    : 'bg-blue-100 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800'"
                >
                  {{ ticket.attendeeType || 'STUDENT' }}
                </span>
              </td>

              <!-- 4. Event -->
              <td class="py-3.5 px-3.5 whitespace-nowrap text-slate-700 dark:text-slate-300 font-semibold">
                <span class="truncate max-w-[150px] block">
                  {{ getEventForTicket(ticket.eventId).title }}
                </span>
              </td>

              <!-- 5. Payment -->
              <td class="py-3.5 px-3.5 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded text-[10px] font-black uppercase inline-block"
                  :class="ticket.paymentStatus === 'paid'
                    ? 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'
                    : ticket.paymentStatus === 'free'
                    ? 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300'
                    : 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'"
                >
                  {{ ticket.paymentStatus }}
                </span>
              </td>

              <!-- 6. Status -->
              <td class="py-3.5 px-3.5 whitespace-nowrap">
                <StatusBadge type="ticket" :status="ticket.ticketStatus" />
              </td>

              <!-- 7. QR Print Status -->
              <td class="py-3.5 px-3.5 whitespace-nowrap text-center">
                <span
                  v-if="(ticket.qrPrintStatus || 'PENDING') === 'PENDING'"
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                >
                  Pending
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                >
                  Printed
                </span>
              </td>

              <!-- 8. Issued At -->
              <td class="py-3.5 px-3.5 whitespace-nowrap font-mono text-slate-500 dark:text-[#888888] text-[11px]">
                {{ ticket.purchaseDate ? new Date(ticket.purchaseDate).toLocaleDateString([], { month: 'short', day: 'numeric', year: 'numeric' }) : '---' }}
              </td>

              <!-- 9. Actions -->
              <td class="py-3.5 px-3.5 text-right whitespace-nowrap" @click.stop>
                <div class="flex items-center justify-end gap-1">
                  <!-- Print / Reprint QR Sticker (Official Workflow) -->
                  <button
                    @click="openQrStickerModal(ticket)"
                    class="p-1.5 text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 rounded-lg transition cursor-pointer"
                    :title="(ticket.qrPrintStatus || 'PENDING') === 'PENDING' ? 'Print Dynamic QR Sticker' : 'Reprint Dynamic QR Sticker'"
                  >
                    <Tag class="w-4 h-4" />
                  </button>

                  <!-- Print Full Pass Preview -->
                  <button
                    @click="openPrintPreview(ticket)"
                    class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#282828] rounded-lg transition cursor-pointer"
                    title="Preview Full Ticket Pass"
                  >
                    <Printer class="w-4 h-4" />
                  </button>

                  <!-- View Details -->
                  <button
                    @click="openDetail(ticket)"
                    class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-[#a0a0a0] dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#282828] rounded-lg transition cursor-pointer"
                    title="View Full Ticket Details"
                  >
                    <Eye class="w-4 h-4" />
                  </button>

                  <!-- Administrative Void (Admin only, non-refundable) -->
                  <button
                    v-if="isAdmin && (ticket.ticketStatus === 'active' || ticket.ticketStatus === 'ISSUED')"
                    @click="ticketToVoid = ticket"
                    class="p-1.5 text-slate-400 hover:text-rose-600 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition cursor-pointer"
                    title="Administrative Void / Cancel Pass"
                  >
                    <Ban class="w-4 h-4" />
                  </button>
                </div>
              </td>
            </tr>

            <!-- Modern Empty State Notification -->
            <tr v-if="filteredTickets.length === 0">
              <td colspan="9" class="py-12 px-4 text-center">
                <div class="max-w-md mx-auto p-6 bg-slate-50/80 dark:bg-[#202020] border border-slate-200/80 dark:border-[#333333] rounded-3xl shadow-xs space-y-3">
                  <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-900 text-blue-600 dark:text-blue-400 flex items-center justify-center mx-auto">
                    <TicketIcon class="w-6 h-6" />
                  </div>
                  <div>
                    <h3 class="text-sm font-black text-slate-900 dark:text-white">
                      No Matching Tickets Found
                    </h3>
                    <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-1">
                      No issued tickets match the selected filters or search query.
                    </p>
                  </div>
                  <button
                    type="button"
                    @click="resetFilters"
                    class="inline-flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer"
                  >
                    <RotateCcw class="w-3.5 h-3.5" />
                    <span>Reset All Filters</span>
                  </button>
                </div>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Footer summary info with Modern Live Status Notification -->
      <div v-if="filteredTickets.length > 0" class="px-4 py-2.5 bg-slate-50/80 dark:bg-[#1f1f1f] border-t border-slate-200 dark:border-[#2e2e2e] flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <span>Showing <strong class="text-slate-700 dark:text-slate-300">{{ filteredTickets.length }}</strong> of <strong class="text-slate-700 dark:text-slate-300">{{ totalTickets }}</strong> issued passes</span>
        <div class="flex items-center gap-2">
          <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-extrabold bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800">
            <span class="relative flex h-1.5 w-1.5">
              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
              <span class="relative inline-flex rounded-full h-1.5 w-1.5 bg-emerald-500"></span>
            </span>
            <span>Live Registry Synced</span>
          </span>
        </div>
      </div>
    </div>

    <!-- ==================== TICKET AUDIT DETAIL MODAL ==================== -->
    <Modal
      v-if="selectedTicket"
      :is-open="isDetailModalOpen"
      @close="isDetailModalOpen = false"
      :title="`Ticket Details: ${selectedTicket.ticketNumber || selectedTicket.id}`"
      subtitle="Complete lifecycle, payment, and attendance metadata"
      max-width="2xl"
    >
      <div class="space-y-4">
        <!-- Top Attendee Card -->
        <div class="flex items-center gap-3.5 p-4 bg-slate-50 dark:bg-[#222222] rounded-2xl border border-slate-200 dark:border-[#333333]">
          <UserAvatar :src="selectedTicket.attendeePhotoUrl" :alt="selectedTicket.currentAttendeeName" size="xl" shape="rounded" />
          <div class="flex-1 min-w-0">
            <div class="flex items-center gap-2 flex-wrap">
              <h3 class="text-base font-extrabold text-slate-900 dark:text-white truncate">{{ selectedTicket.currentAttendeeName }}</h3>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider inline-block"
                :class="selectedTicket.attendeeType === 'OUTSIDER'
                  ? 'bg-purple-100 dark:bg-purple-950/70 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800'
                  : 'bg-blue-100 dark:bg-blue-950/70 text-blue-700 dark:text-blue-300 border border-blue-300 dark:border-blue-800'"
              >
                {{ selectedTicket.attendeeType || 'STUDENT' }}
              </span>
            </div>
            <p class="text-xs text-blue-600 dark:text-blue-400 font-mono font-bold mt-0.5">
              ID: {{ selectedTicket.attendeeStudentId || 'External Guest' }}
            </p>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
              {{ selectedTicket.course ? `${selectedTicket.course} • ${selectedTicket.yearLevel || ''} • Sec ${selectedTicket.section || ''}` : 'General Attendee' }}
            </p>
          </div>
        </div>

        <!-- Institutional Policy Callout -->
        <div class="p-3 bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] rounded-xl text-xs flex items-center justify-between">
          <div class="flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="font-bold text-slate-800 dark:text-slate-200">
              Pass Classification:
            </span>
            <span class="text-slate-600 dark:text-slate-300 font-medium">
              NCST Event Pass
            </span>
          </div>
          <span class="text-[10px] font-mono text-slate-400">Official Pass Record</span>
        </div>

        <!-- Metadata Grid -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 text-xs">
          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Event</span>
            <span class="font-bold text-slate-900 dark:text-white mt-0.5 block truncate">
              {{ getEventForTicket(selectedTicket.eventId).title }}
            </span>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Payment Status</span>
            <span class="font-bold text-emerald-600 dark:text-emerald-400 mt-0.5 block capitalize">
              {{ selectedTicket.paymentStatus }} (₱{{ selectedTicket.price || 0 }}.00)
            </span>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Ticket Status</span>
            <span class="font-bold text-blue-600 dark:text-blue-400 mt-0.5 block uppercase">
              {{ selectedTicket.ticketStatus }}
            </span>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Attendance Check-in</span>
            <span class="font-bold mt-0.5 block" :class="selectedTicket.attendanceStatus === 'present' ? 'text-emerald-600' : 'text-amber-600'">
              {{ selectedTicket.attendanceStatus === 'present' ? 'PRESENT' : 'NOT YET ENTERED' }}
            </span>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Scan Timestamp</span>
            <span class="font-mono text-slate-800 dark:text-slate-200 mt-0.5 block">
              {{ selectedTicket.timeIn ? new Date(selectedTicket.timeIn).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '---' }}
            </span>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-slate-400 block text-[10px] uppercase font-extrabold">Issued By / Operator</span>
            <span class="font-semibold text-slate-700 dark:text-slate-300 mt-0.5 block truncate">
              {{ selectedTicket.originalOwnerName || 'Cashier Desk' }}
            </span>
          </div>
        </div>

        <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-[#333333]">
          <button
            @click="openPrintPreview(selectedTicket)"
            class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
          >
            <Printer class="w-3.5 h-3.5" />
            <span>Print Ticket Pass</span>
          </button>
          <button
            @click="isDetailModalOpen = false"
            class="px-4 py-2 bg-slate-100 dark:bg-[#2c2c2c] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-200 dark:hover:bg-[#383838] transition cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>

    <!-- Administrative Void Confirmation Modal -->
    <ConfirmModal
      :is-open="!!ticketToVoid"
      title="Administrative Void Ticket Pass"
      :message="`Are you sure you want to void ticket #${ticketToVoid?.ticketNumber || ticketToVoid?.id} for attendee ${ticketToVoid?.currentAttendeeName}? The ticket will be marked as VOID. Event tickets are strictly non-refundable.`"
      confirm-text="Void Ticket"
      variant="danger"
      @confirm="confirmVoidTicket"
      @cancel="ticketToVoid = null"
    />

    <!-- Dynamic QR Label Sticker Print Modal -->
    <QRLabelPrintModal
      v-if="ticketForQrSticker"
      :is-open="isQrStickerModalOpen"
      :ticket="ticketForQrSticker"
      @close="isQrStickerModalOpen = false"
    />

    <!-- Printable Full Ticket Modal -->
    <PrintableTicketModal
      v-if="ticketToPrint"
      :is-open="isPrintModalOpen"
      :ticket="ticketToPrint"
      :event="getEventForTicket(ticketToPrint.eventId)"
      @close="isPrintModalOpen = false"
    />

    <!-- Modern Floating Toast Notification -->
    <transition
      enter-active-class="transition duration-300 ease-out"
      enter-from-class="transform translate-y-4 opacity-0 scale-95"
      enter-to-class="transform translate-y-0 opacity-100 scale-100"
      leave-active-class="transition duration-200 ease-in"
      leave-from-class="transform translate-y-0 opacity-100 scale-100"
      leave-to-class="transform translate-y-4 opacity-0 scale-95"
    >
      <div
        v-if="toastNotification"
        class="fixed bottom-6 right-6 z-50 max-w-sm w-full bg-white dark:bg-[#202020] border border-slate-200 dark:border-[#383838] rounded-2xl p-4 shadow-2xl flex items-start gap-3 backdrop-blur-md"
      >
        <div
          class="p-2 rounded-xl shrink-0"
          :class="toastNotification.type === 'success' ? 'bg-emerald-50 dark:bg-emerald-950/70 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-50 dark:bg-rose-950/70 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-800'"
        >
          <CheckCircle2 v-if="toastNotification.type === 'success'" class="w-4 h-4" />
          <AlertCircle v-else class="w-4 h-4" />
        </div>
        <div class="flex-1 min-w-0">
          <h4 class="text-xs font-black text-slate-900 dark:text-white">
            {{ toastNotification.title }}
          </h4>
          <p class="text-[11px] text-slate-500 dark:text-[#a0a0a0] mt-0.5 leading-relaxed">
            {{ toastNotification.message }}
          </p>
        </div>
        <button
          type="button"
          @click="toastNotification = null"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 p-1 rounded-lg hover:bg-slate-100 dark:hover:bg-[#2a2a2a] transition cursor-pointer"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  FileSpreadsheet,
  Eye,
  Printer,
  Ticket as TicketIcon,
  Ban,
  ShieldCheck,
  Tag,
  RotateCcw,
  CheckCircle2,
  AlertCircle,
  X
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { Ticket, EventItem } from '../../types';
import FilterBar from '../common/FilterBar.vue';
import StatusBadge from '../common/StatusBadge.vue';
import UserAvatar from '../common/UserAvatar.vue';
import Modal from '../common/Modal.vue';
import ConfirmModal from '../common/ConfirmModal.vue';
import PrintableTicketModal from '../tickets/PrintableTicketModal.vue';
import QRLabelPrintModal from '../tickets/QRLabelPrintModal.vue';
import { exportTicketsToExcel } from '../../utils/excelExport';

const { currentUser, tickets, filteredTickets, events, currentEvent, cancelTicket, resetFilters } = useAppStore();
const isAdmin = computed(() => currentUser.value?.role === 'admin');
const totalTickets = computed(() => tickets.value.length);

const selectedTicket = ref<Ticket | null>(null);
const ticketToVoid = ref<Ticket | null>(null);
const ticketToPrint = ref<Ticket | null>(null);
const ticketForQrSticker = ref<Ticket | null>(null);
const isDetailModalOpen = ref(false);
const isPrintModalOpen = ref(false);
const isQrStickerModalOpen = ref(false);

const toastNotification = ref<{ title: string; message: string; type: 'success' | 'error' } | null>(null);
let toastTimer: any = null;

const showToast = (title: string, message: string, type: 'success' | 'error' = 'success') => {
  if (toastTimer) clearTimeout(toastTimer);
  toastNotification.value = { title, message, type };
  toastTimer = setTimeout(() => {
    toastNotification.value = null;
  }, 4000);
};

const getEventForTicket = (eventId: string): EventItem => {
  return events.value.find(e => e.id === eventId) || currentEvent.value || {
    id: eventId,
    title: 'School Event',
    code: 'EVT-SCH',
    description: '',
    category: 'Institutional',
    venue: 'Campus Grounds',
    date: '2026-10-15',
    startTime: '08:00 AM',
    endTime: '05:00 PM',
    capacity: 1000,
    ticketPrice: 0,
    status: 'ongoing',
    bannerUrl: '',
    organizer: 'Student Affairs'
  };
};

const openDetail = (t: Ticket) => {
  selectedTicket.value = t;
  isDetailModalOpen.value = true;
};

const openPrintPreview = (t: Ticket) => {
  ticketToPrint.value = t;
  isPrintModalOpen.value = true;
};

const openQrStickerModal = (t: Ticket) => {
  ticketForQrSticker.value = t;
  isQrStickerModalOpen.value = true;
};

const confirmVoidTicket = () => {
  if (ticketToVoid.value) {
    const tNum = ticketToVoid.value.ticketNumber || ticketToVoid.value.id;
    cancelTicket(ticketToVoid.value.id, 'Administrative Void');
    showToast('Ticket Pass Voided', `Ticket #${tNum} has been voided successfully. Non-refundable policy recorded.`, 'success');
    ticketToVoid.value = null;
  }
};

const handleExportExcel = () => {
  exportTicketsToExcel(filteredTickets.value, events.value, `Issued_Tickets_Registry_${Date.now()}.xlsx`);
  showToast('Export Successful', `Exported ${filteredTickets.value.length} issued ticket pass records to Excel file.`, 'success');
};
</script>
