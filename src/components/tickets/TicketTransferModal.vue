<template>
  <Modal
    :is-open="isOpen"
    @close="$emit('close')"
    title="Controlled Ticket Transfer"
    :subtitle="`Ticket ID: ${ticket.id}`"
    max-width="lg"
  >
    <div class="space-y-5">
      <!-- Security Rule Notice -->
      <div class="p-3.5 bg-blue-50 dark:bg-[#1e1e1e] border border-blue-200 dark:border-blue-500/30 rounded-2xl flex items-start gap-3 transition-colors">
        <ShieldCheck class="w-5 h-5 text-blue-600 dark:text-blue-400 flex-shrink-0 mt-0.5" />
        <div class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
          <p class="font-bold text-slate-900 dark:text-white">Immutable Transfer Security Policy</p>
          <p class="text-slate-600 dark:text-[#b3b3b3] mt-0.5">
            The original purchaser (<strong class="text-blue-600 dark:text-blue-400">{{ ticket.originalOwnerName }}</strong>) remains permanently logged. The ticket QR will be re-assigned to the new attendee.
          </p>
        </div>
      </div>

      <!-- Prior Transfer History -->
      <div v-if="ticket.transferHistory && ticket.transferHistory.length > 0" class="p-3.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-xl space-y-2">
        <div class="flex items-center gap-1.5 text-xs font-bold text-amber-600 dark:text-amber-400 uppercase tracking-wider">
          <History class="w-3.5 h-3.5" />
          Prior Transfer History
        </div>
        <div class="space-y-1.5 text-xs text-slate-700 dark:text-slate-300">
          <div v-for="(tr, idx) in ticket.transferHistory" :key="idx" class="p-2 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#3a3a3a] rounded-lg flex items-center justify-between">
            <div>
              <span class="text-slate-500 dark:text-[#b3b3b3]">{{ tr.fromUserName }}</span>
              <ArrowRight class="w-3 h-3 inline mx-1.5 text-blue-600 dark:text-blue-400" />
              <strong class="text-slate-900 dark:text-white">{{ tr.toUserName }}</strong>
            </div>
            <span class="text-[10px] text-slate-400 dark:text-slate-500 font-mono">
              {{ new Date(tr.timestamp).toLocaleDateString() }}
            </span>
          </div>
        </div>
      </div>

      <!-- Transfer Form -->
      <form @submit.prevent="handleTransfer" class="space-y-4">
        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Recipient Full Name *
          </label>
          <input
            type="text"
            placeholder="e.g. Maria Clara Santos"
            v-model="recipientName"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3.5 py-2.5 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500"
            required
          />
        </div>

        <div class="grid grid-cols-2 gap-3">
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Recipient Student ID (Optional)
            </label>
            <input
              type="text"
              placeholder="e.g. 2026-00142"
              v-model="recipientStudentId"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-mono text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Recipient Email
            </label>
            <input
              type="email"
              placeholder="student@school.edu.ph"
              v-model="recipientEmail"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Quick Suggestions -->
        <div>
          <span class="block text-[11px] font-semibold text-slate-500 dark:text-[#b3b3b3] mb-1.5">
            Quick Select Registered Classmate:
          </span>
          <div class="flex flex-wrap gap-1.5">
            <button
              type="button"
              v-for="s in studentSuggestions"
              :key="s.id"
              @click="handleSelectStudent(s)"
              class="px-2.5 py-1 bg-slate-100 dark:bg-[#1e1e1e] hover:bg-slate-200 dark:hover:bg-[#2c2c2c] border border-slate-200 dark:border-[#333333] hover:border-blue-500 rounded-lg text-[11px] text-slate-700 dark:text-[#b3b3b3] flex items-center gap-1.5 transition cursor-pointer"
            >
              <UserIcon class="w-3 h-3 text-blue-600 dark:text-blue-400" />
              {{ s.name }} ({{ s.studentId }})
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">
            Reason / Transfer Note
          </label>
          <textarea
            rows="2"
            placeholder="e.g. Schedule conflict; transferred to classmate for foundation day."
            v-model="reason"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3.5 py-2 text-xs text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500 resize-none"
          />
        </div>

        <!-- Feedback Alert -->
        <div v-if="error" class="p-3 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 rounded-xl text-xs text-rose-700 dark:text-rose-300 flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 flex-shrink-0" />
          <span>{{ error }}</span>
        </div>

        <div v-if="success" class="p-3 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded-xl text-xs text-emerald-700 dark:text-emerald-300 flex items-center gap-2">
          <CheckCircle class="w-4 h-4 flex-shrink-0" />
          <span>{{ success }}</span>
        </div>

        <!-- Actions -->
        <div class="flex items-center gap-3 pt-2">
          <button
            type="button"
            @click="$emit('close')"
            class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-700 dark:text-slate-300 text-xs font-semibold rounded-xl transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="!!success"
            class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <Share2 class="w-4 h-4" />
            Confirm Transfer
          </button>
        </div>
      </form>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Share2, ShieldCheck, History, ArrowRight, User as UserIcon, AlertTriangle, CheckCircle } from 'lucide-vue-next';
import { Ticket, User } from '../../types';
import { useAppStore } from '../../composables/useAppStore';
import Modal from '../common/Modal.vue';

const props = defineProps<{
  ticket: Ticket;
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { transferTicket, users } = useAppStore();

const recipientName = ref('');
const recipientStudentId = ref('');
const recipientEmail = ref('');
const reason = ref('');
const error = ref<string | null>(null);
const success = ref<string | null>(null);

const studentSuggestions = computed(() =>
  users.value.filter(u => u.role === 'student' && u.id !== props.ticket.currentAttendeeId)
);

const handleSelectStudent = (u: User) => {
  recipientName.value = u.name;
  recipientStudentId.value = u.studentId || '';
  recipientEmail.value = u.email;
};

const handleTransfer = () => {
  error.value = null;

  if (!recipientName.value.trim()) {
    error.value = 'Please provide the recipient student full name.';
    return;
  }

  const res = transferTicket(
    props.ticket.id,
    recipientName.value.trim(),
    recipientStudentId.value.trim() || undefined,
    recipientEmail.value.trim() || undefined,
    reason.value.trim() || 'Ticket transferred to student.'
  );

  if (res.success) {
    success.value = res.message;
    setTimeout(() => {
      emit('close');
    }, 1800);
  } else {
    error.value = res.message;
  }
};
</script>
