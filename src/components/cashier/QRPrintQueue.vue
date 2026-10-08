<template>
  <div class="space-y-5">
    <!-- ==================== 1. TOP HEADER & PRIMARY ACTION ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-5 sm:p-6 shadow-xs transition-colors">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
        <!-- Title & Pending Indicator -->
        <div class="flex items-center gap-4">
          <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <Printer class="w-6 h-6" />
          </div>
          <div>
            <div class="flex items-center gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                QR Print Queue
              </h1>
              <!-- Prominent Pending Badge -->
              <span
                class="px-2.5 py-0.5 rounded-full text-xs font-black uppercase tracking-wider flex items-center gap-1.5"
                :class="pendingTickets.length > 0
                  ? 'bg-amber-100 dark:bg-amber-950/80 text-amber-900 dark:text-amber-300 border border-amber-300 dark:border-amber-700'
                  : 'bg-slate-100 dark:bg-[#282828] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#3a3a3a]'"
              >
                <Clock class="w-3.5 h-3.5" />
                <span>{{ pendingTickets.length }} Pending</span>
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
              Print dynamic QR stickers to affix onto pre-printed event ticket passes.
            </p>
          </div>
        </div>

        <!-- Primary Action: Print All Pending & Stock Template -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <button
            type="button"
            @click="isStockModalOpen = true"
            class="px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-[#252525] dark:hover:bg-[#303030] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition flex items-center gap-2 cursor-pointer"
            title="Pre-printed stock physical layout template"
          >
            <Layers class="w-4 h-4 text-slate-400" />
            <span>Stock Template</span>
          </button>

          <!-- PRIMARY BUTTON: Print All Pending -->
          <button
            type="button"
            @click="handlePrintAllPending"
            :disabled="pendingTickets.length === 0"
            class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 disabled:bg-slate-200 dark:disabled:bg-[#2a2a2a] disabled:text-slate-400 text-white text-xs font-black uppercase tracking-wider shadow-md transition cursor-pointer disabled:cursor-not-allowed"
          >
            <Printer class="w-4 h-4" />
            <span>Print All Pending ({{ pendingTickets.length }})</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== 2. SEARCH & FILTER BAR ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-4 sm:p-5 shadow-xs transition-colors space-y-3">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-3">
        <!-- Search & Filter Controls -->
        <div class="grid grid-cols-1 sm:grid-cols-12 gap-3 flex-1">
          <!-- Search Input -->
          <div class="sm:col-span-6 relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Search by ticket # or attendee..."
              class="w-full pl-10 pr-4 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
            />
          </div>

          <!-- Status Filter -->
          <div class="sm:col-span-3">
            <select
              v-model="statusFilter"
              class="w-full py-2 px-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500"
            >
              <option value="all">All Statuses ({{ queueTickets.length }})</option>
              <option value="PENDING">Pending Only ({{ pendingTickets.length }})</option>
              <option value="PRINTED">Printed Only ({{ printedTickets.length }})</option>
            </select>
          </div>

          <!-- Event Filter -->
          <div class="sm:col-span-3">
            <select
              v-model="selectedEventId"
              class="w-full py-2 px-3 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 truncate"
            >
              <option value="all">All Events</option>
              <option v-for="e in events" :key="e.id" :value="e.id">
                {{ e.title }}
              </option>
            </select>
          </div>
        </div>

        <!-- Batch Action when items are selected -->
        <div v-if="selectedTicketIds.length > 0" class="flex items-center gap-2 shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100 dark:border-[#2a2a2a]">
          <button
            type="button"
            @click="handlePrintSelected"
            class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 dark:bg-[#282828] dark:hover:bg-[#333333] text-white text-xs font-bold transition cursor-pointer"
          >
            <Tag class="w-3.5 h-3.5 text-blue-400" />
            <span>Print Selected ({{ selectedTicketIds.length }})</span>
          </button>
          <button
            type="button"
            @click="selectedTicketIds = []"
            class="text-xs text-slate-500 hover:text-slate-800 dark:hover:text-white underline cursor-pointer"
          >
            Clear
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== 3. QUEUE TABLE ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl overflow-hidden shadow-xs transition-colors flex flex-col">
      <div class="overflow-x-auto overflow-y-auto max-h-[480px] custom-scrollbar">
        <table class="w-full text-left border-collapse">
          <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-[#202020] shadow-xs">
            <tr class="border-b border-slate-200 dark:border-[#2e2e2e] bg-slate-50/95 dark:bg-[#202020]/95 text-[10px] uppercase font-black tracking-wider text-slate-500 dark:text-[#a0a0a0]">
              <th class="py-3 px-4 w-10 text-center bg-slate-50 dark:bg-[#202020]">
                <input
                  type="checkbox"
                  :checked="isAllSelected"
                  @change="toggleSelectAll"
                  class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                  title="Select all"
                />
              </th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#202020]">Ticket Number</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#202020]">Attendee Name</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#202020]">Event</th>
              <th class="py-3 px-4 text-center bg-slate-50 dark:bg-[#202020]">Print Status</th>
              <th class="py-3 px-4 text-right bg-slate-50 dark:bg-[#202020]">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#262626] text-xs">
            <tr
              v-for="t in filteredQueueTickets"
              :key="t.id"
              class="hover:bg-slate-50/80 dark:hover:bg-[#242424]/60 transition"
              :class="{ 'bg-blue-50/30 dark:bg-blue-950/20': selectedTicketIds.includes(t.id) }"
            >
              <!-- Checkbox -->
              <td class="py-3 px-4 text-center">
                <input
                  type="checkbox"
                  :value="t.id"
                  v-model="selectedTicketIds"
                  class="rounded text-blue-600 focus:ring-blue-500 cursor-pointer"
                />
              </td>

              <!-- Ticket Number -->
              <td class="py-3 px-4 font-mono font-black text-slate-900 dark:text-white">
                <div class="flex items-center gap-1.5">
                  <Tag class="w-3.5 h-3.5 text-slate-400" />
                  <span>{{ t.ticketNumber || t.id }}</span>
                </div>
              </td>

              <!-- Attendee Name -->
              <td class="py-3 px-4">
                <div>
                  <p class="font-bold text-slate-900 dark:text-white">{{ t.currentAttendeeName }}</p>
                  <p v-if="t.attendeeStudentId || t.course" class="text-[11px] text-slate-500 dark:text-[#a0a0a0]">
                    <span v-if="t.attendeeStudentId" class="font-mono text-blue-600 dark:text-blue-400 font-bold">
                      {{ t.attendeeStudentId }}
                    </span>
                    <span v-if="t.attendeeStudentId && t.course"> • </span>
                    <span>{{ t.course }}</span>
                  </p>
                </div>
              </td>

              <!-- Event -->
              <td class="py-3 px-4 font-medium text-slate-700 dark:text-slate-300">
                <p class="truncate max-w-[200px]" :title="t.eventTitle">{{ t.eventTitle || 'Event' }}</p>
              </td>

              <!-- Print Status -->
              <td class="py-3 px-4 text-center">
                <span
                  v-if="(t.qrPrintStatus || 'PENDING') === 'PENDING'"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-200 dark:border-amber-800"
                >
                  <Clock class="w-3 h-3 text-amber-600" />
                  <span>PENDING</span>
                </span>
                <span
                  v-else
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800"
                >
                  <CheckCircle2 class="w-3 h-3 text-emerald-600" />
                  <span>PRINTED</span>
                </span>
              </td>

              <!-- Action -->
              <td class="py-3 px-4 text-right">
                <button
                  type="button"
                  @click="handlePrintSingleTicket(t)"
                  class="px-3 py-1.5 rounded-lg text-xs font-bold transition inline-flex items-center gap-1.5 cursor-pointer"
                  :class="(t.qrPrintStatus || 'PENDING') === 'PENDING'
                    ? 'bg-blue-600 hover:bg-blue-700 text-white shadow-xs'
                    : 'bg-slate-100 dark:bg-[#282828] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#383838]'"
                  :title="(t.qrPrintStatus || 'PENDING') === 'PENDING' ? 'Print Single QR Label' : 'Reprint Single QR Label'"
                >
                  <Printer class="w-3.5 h-3.5" />
                  <span>{{ (t.qrPrintStatus || 'PENDING') === 'PENDING' ? 'Print Label' : 'Reprint Label' }}</span>
                </button>
              </td>
            </tr>

            <!-- Empty State -->
            <tr v-if="filteredQueueTickets.length === 0">
              <td colspan="6" class="py-12 text-center text-slate-400 dark:text-[#888888] space-y-2">
                <Printer class="w-8 h-8 mx-auto text-slate-300 dark:text-[#444444]" />
                <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No tickets found in print queue</p>
                <p class="text-xs text-slate-400">Newly issued tickets will automatically appear here as PENDING.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
      <!-- Footer summary info -->
      <div v-if="filteredQueueTickets.length > 0" class="px-4 py-2.5 bg-slate-50/80 dark:bg-[#1f1f1f] border-t border-slate-200 dark:border-[#2e2e2e] flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
        <span>Showing <strong class="text-slate-700 dark:text-slate-300">{{ filteredQueueTickets.length }}</strong> in print queue</span>
        <span class="text-[10px] text-slate-400">Scrollable view</span>
      </div>
    </div>

    <!-- ==================== DYNAMIC QR LABEL PRINT MODAL ==================== -->
    <QRLabelPrintModal
      :is-open="isPrintModalOpen"
      :tickets="batchTicketsToPrint"
      @close="isPrintModalOpen = false"
      @printed="handleAfterBatchPrinted"
    />

    <!-- ==================== PRE-PRINTED STOCK TEMPLATE MODAL ==================== -->
    <PrePrintedBulkTicketModal
      :is-open="isStockModalOpen"
      :selected-event="activeEventObject"
      @close="isStockModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Printer,
  Tag,
  Clock,
  CheckCircle2,
  Search,
  Layers,
} from 'lucide-vue-next';
import { Ticket } from '../../types';
import { useAppStore } from '../../composables/useAppStore';
import QRLabelPrintModal from '../tickets/QRLabelPrintModal.vue';
import PrePrintedBulkTicketModal from '../tickets/PrePrintedBulkTicketModal.vue';

const { tickets, events, currentEvent } = useAppStore();

const searchQuery = ref('');
const statusFilter = ref<'all' | 'PENDING' | 'PRINTED'>('all');
const selectedEventId = ref('all');
const selectedTicketIds = ref<string[]>([]);

const isPrintModalOpen = ref(false);
const isStockModalOpen = ref(false);
const batchTicketsToPrint = ref<Ticket[]>([]);

const queueTickets = computed(() => {
  return tickets.value.filter(t => t.ticketStatus !== 'CANCELLED' && t.ticketStatus !== 'VOID');
});

const pendingTickets = computed(() => {
  return queueTickets.value.filter(t => (t.qrPrintStatus || 'PENDING') === 'PENDING');
});

const printedTickets = computed(() => {
  return queueTickets.value.filter(t => t.qrPrintStatus === 'PRINTED' || t.qrPrintStatus === 'ATTACHED');
});

const activeEventObject = computed(() => {
  if (selectedEventId.value !== 'all') {
    return events.value.find(e => e.id === selectedEventId.value) || currentEvent.value;
  }
  return currentEvent.value;
});

const filteredQueueTickets = computed(() => {
  return queueTickets.value.filter(t => {
    if (selectedEventId.value !== 'all' && t.eventId !== selectedEventId.value) {
      return false;
    }
    if (statusFilter.value !== 'all') {
      const tStatus = t.qrPrintStatus || 'PENDING';
      if (tStatus !== statusFilter.value) return false;
    }
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase();
      const matchNum = (t.ticketNumber || t.id || '').toLowerCase().includes(q);
      const matchName = (t.currentAttendeeName || '').toLowerCase().includes(q);
      const matchStudId = (t.attendeeStudentId || '').toLowerCase().includes(q);
      if (!matchNum && !matchName && !matchStudId) return false;
    }
    return true;
  });
});

const isAllSelected = computed(() => {
  return (
    filteredQueueTickets.value.length > 0 &&
    filteredQueueTickets.value.every(t => selectedTicketIds.value.includes(t.id))
  );
});

const toggleSelectAll = () => {
  if (isAllSelected.value) {
    selectedTicketIds.value = [];
  } else {
    selectedTicketIds.value = filteredQueueTickets.value.map(t => t.id);
  }
};

const handlePrintSingleTicket = (ticket: Ticket) => {
  batchTicketsToPrint.value = [ticket];
  isPrintModalOpen.value = true;
};

const handlePrintAllPending = () => {
  if (pendingTickets.value.length === 0) return;
  batchTicketsToPrint.value = pendingTickets.value;
  isPrintModalOpen.value = true;
};

const handlePrintSelected = () => {
  const selected = tickets.value.filter(t => selectedTicketIds.value.includes(t.id));
  if (selected.length === 0) return;
  batchTicketsToPrint.value = selected;
  isPrintModalOpen.value = true;
};

const handleAfterBatchPrinted = (printedIds: string[]) => {
  selectedTicketIds.value = selectedTicketIds.value.filter(id => !printedIds.includes(id));
};
</script>

