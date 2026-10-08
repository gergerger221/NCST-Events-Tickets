<template>
  <Modal :is-open="isOpen" @close="$emit('close')" max-width="lg">
    <div v-if="lookupData" class="space-y-4 py-1 text-left">
      
      <!-- Lookup Result Header -->
      <div v-if="lookupData.found && lookupData.ticket" class="space-y-3">
        <!-- Prominent Lookup Banner -->
        <div class="p-3.5 bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800 rounded-2xl flex items-start gap-3 text-blue-900 dark:text-blue-200 text-xs">
          <Info class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
          <div>
            <strong class="font-extrabold block uppercase tracking-wider text-[11px]">Verification Lookup Only</strong>
            <p class="text-[11px] mt-0.5 leading-relaxed text-blue-800 dark:text-blue-300">
              Manual lookup verifies database registration only. <strong>It does NOT mark attendance or authorize entrance.</strong> The attendee must scan their official QR code to check in.
            </p>
          </div>
        </div>

        <!-- Ticket Card -->
        <div class="bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl p-4 space-y-3">
          <div class="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-[#333333] gap-2 flex-wrap">
            <div>
              <span class="text-[10px] font-extrabold uppercase text-slate-400 tracking-wider block">Registered Attendee</span>
              <h3 class="text-base font-black text-slate-900 dark:text-white">
                {{ lookupData.ticket.currentAttendeeName }}
              </h3>
              <p v-if="lookupData.ticket.attendeeStudentId" class="text-xs font-mono font-bold text-blue-600 dark:text-blue-400">
                Student ID: {{ lookupData.ticket.attendeeStudentId }}
              </p>
            </div>

            <span
              class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider"
              :class="lookupData.ticket.attendeeType === 'OUTSIDER'
                ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800'
                : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-300 dark:border-blue-800'"
            >
              {{ lookupData.ticket.attendeeType || 'STUDENT' }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3 text-xs">
            <div>
              <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-bold uppercase">Ticket Number</span>
              <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs">
                {{ lookupData.ticket.ticketNumber || lookupData.ticket.id }}
              </span>
            </div>

            <div>
              <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-bold uppercase">Attendance State</span>
              <span
                class="font-extrabold text-xs inline-flex items-center gap-1"
                :class="lookupData.ticket.attendanceStatus === 'present' || lookupData.ticket.ticketStatus === 'USED'
                  ? 'text-emerald-600 dark:text-emerald-400'
                  : 'text-amber-600 dark:text-amber-400'"
              >
                {{ lookupData.ticket.attendanceStatus === 'present' || lookupData.ticket.ticketStatus === 'USED' ? '✅ PRESENT' : '⏳ NOT YET ENTERED' }}
              </span>
            </div>

            <div class="col-span-2">
              <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-bold uppercase">Event</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                {{ lookupData.event?.title || lookupData.ticket.eventTitle || lookupData.ticket.eventId }}
              </span>
            </div>

            <div v-if="lookupData.ticket.timeIn" class="col-span-2">
              <span class="text-slate-400 dark:text-slate-500 block text-[10px] font-bold uppercase">Recorded Time In (PHT)</span>
              <span class="font-mono font-bold text-slate-800 dark:text-slate-200 flex items-center gap-1">
                <Clock class="w-3.5 h-3.5 text-emerald-600" />
                {{ formatPhilippineTime(lookupData.ticket.timeIn) }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Ticket Not Found -->
      <div v-else class="text-center py-4 space-y-2">
        <div class="w-14 h-14 rounded-full bg-rose-100 dark:bg-rose-950/60 border border-rose-300 dark:border-rose-800 text-rose-600 dark:text-rose-400 flex items-center justify-center mx-auto">
          <XCircle class="w-8 h-8" />
        </div>
        <h3 class="text-base font-extrabold text-slate-900 dark:text-white">Ticket Not Found</h3>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0] max-w-sm mx-auto">
          {{ lookupData.message || 'No ticket matching this identifier was found in the database.' }}
        </p>
      </div>

      <!-- Footer Buttons -->
      <div class="flex justify-end gap-2 pt-2 border-t border-slate-200 dark:border-[#333333]">
        <button
          type="button"
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 dark:bg-[#2c2c2c] hover:bg-slate-200 dark:hover:bg-[#383838] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { Info, XCircle, Clock } from 'lucide-vue-next';
import { Ticket, EventItem } from '../../types';
import { formatPhilippineTime } from '../../utils/philippineTime';
import Modal from '../common/Modal.vue';

defineProps<{
  isOpen: boolean;
  lookupData: {
    found: boolean;
    ticket?: Ticket;
    event?: EventItem;
    message: string;
  } | null;
}>();

defineEmits<{
  (e: 'close'): void;
}>();
</script>
