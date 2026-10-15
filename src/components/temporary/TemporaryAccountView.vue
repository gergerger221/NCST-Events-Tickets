<template>
  <!-- 1. MANDATORY FIRST-LOGIN SETUP VIEW FOR GUESTS -->
  <div v-if="currentUser?.mustChangePassword" class="max-w-xl mx-auto py-6 sm:py-10 px-2 sm:px-4">
    <div class="bg-white dark:bg-[#1e1e1e] border border-amber-200/90 dark:border-amber-800/80 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
      <div class="flex items-center gap-4">
        <div class="p-3 bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-2xl shadow-xs">
          <KeyRound class="w-7 h-7" />
        </div>
        <div>
          <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
            First-Login Activation Required
          </span>
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">
            Set Your Permanent Password
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Welcome, <strong>{{ currentUser.name }}</strong> (Guest ID: {{ currentUser.studentId || currentUser.id }})
          </p>
        </div>
      </div>

      <div class="p-4 bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl text-xs text-amber-800 dark:text-amber-300 space-y-1.5 leading-relaxed">
        <p class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-200">
          <KeyRound class="w-4 h-4 text-amber-600 dark:text-amber-400" />
          Mandatory Password Change
        </p>
        <p class="text-[11px] text-amber-700 dark:text-amber-300">
          Your account was automatically created during ticket purchase using your <strong>official surname</strong> as your initial temporary password.
        </p>
        <p class="text-[11px] text-amber-700 dark:text-amber-300">
          To protect your digital pass and event admission records, you must create a secure permanent password before accessing portal features.
        </p>
      </div>

      <div v-if="passError" class="p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs font-semibold">
        {{ passError }}
      </div>
      <div v-if="passSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-700 dark:text-emerald-300 text-xs font-bold">
        {{ passSuccess }}
      </div>

      <form @submit.prevent="handleChangePassword" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">New Permanent Password *</label>
          <div class="relative">
            <input
              v-model="newPassword"
              :type="showNewPassword ? 'text' : 'password'"
              required
              minlength="4"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#151515] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 pr-10"
              placeholder="At least 4 characters (cannot be your surname)"
            />
            <button
              type="button"
              @click="showNewPassword = !showNewPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
            >
              <Eye v-if="!showNewPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
          <div class="relative">
            <input
              v-model="confirmNewPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              required
              minlength="4"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#151515] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-amber-500 pr-10"
              placeholder="Re-type your new password"
            />
            <button
              type="button"
              @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
            >
              <Eye v-if="!showConfirmPassword" class="w-4 h-4" />
              <EyeOff v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <button
          type="submit"
          :disabled="isSubmitting || !newPassword.trim() || !confirmNewPassword.trim()"
          class="w-full py-3 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition shadow-md disabled:opacity-50 cursor-pointer"
        >
          {{ isSubmitting ? 'Saving Password...' : 'Set Permanent Password & Enter Portal' }}
        </button>
      </form>
    </div>
  </div>

  <!-- 2. NORMAL TEMPORARY GUEST PASS VIEW -->
  <div v-else class="space-y-6 max-w-4xl mx-auto">
    <!-- Temporary Guest Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-slate-900 via-amber-950/40 to-slate-900 border border-amber-500/30 p-6 sm:p-8 shadow-xl">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div>
          <div class="flex items-center gap-2 mb-2">
            <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-amber-500/20 text-amber-300 border border-amber-500/30">
              Temporary Guest Account
            </span>
            <span class="text-xs text-amber-400 font-mono font-bold">
              Guest ID: {{ currentUser.studentId || 'TMP-GUEST' }}
            </span>
          </div>
          <h1 class="text-2xl sm:text-3xl font-extrabold text-white tracking-tight">
            {{ currentUser.name }}
          </h1>
          <p class="text-xs text-slate-300 mt-1">
            Affiliation: {{ currentUser.department || 'External Participant' }}
          </p>
        </div>

        <!-- Expiration Timer Card -->
        <div class="p-4 bg-slate-950/90 border border-amber-500/40 rounded-2xl text-center min-w-[200px]">
          <span class="text-[10px] uppercase font-bold text-amber-400 flex items-center justify-center gap-1">
            <Clock class="w-3.5 h-3.5" />
            Pass Expiration
          </span>
          <p v-if="isExpired" class="text-base font-black text-rose-400 mt-1 uppercase">EXPIRED</p>
          <div v-else-if="timeLeft" class="text-xl font-black font-mono text-white mt-1">
            <span v-if="timeLeft.days > 0" class="text-amber-400 mr-1">{{ timeLeft.days }}d</span>
            <span>{{ String(timeLeft.hours).padStart(2, '0') }}:{{ String(timeLeft.minutes).padStart(2, '0') }}:{{ String(timeLeft.seconds).padStart(2, '0') }}</span>
          </div>
          <span class="text-[10px] text-slate-400 block mt-0.5 truncate max-w-[200px]" :title="formattedExpiration">
            Expires: {{ formattedExpiration }}
          </span>
        </div>
      </div>
    </div>

    <!-- Pass Guidelines -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3.5 text-xs">
      <div class="p-4 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-1 shadow-sm">
        <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
          <CheckCircle2 class="w-4 h-4 text-emerald-500 dark:text-emerald-400" />
          1. Validated at Entry
        </span>
        <p class="text-slate-500 dark:text-[#b3b3b3]">
          Show your digital QR pass at the entrance scanner station for verified access.
        </p>
      </div>

      <div class="p-4 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-1 shadow-sm">
        <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
          <MapPin class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          2. Instant Validation
        </span>
        <p class="text-slate-500 dark:text-[#b3b3b3]">
          Instant verification upon presentation of the QR badge to Event Staff.
        </p>
      </div>

      <div class="p-4 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-1 shadow-sm">
        <span class="font-bold text-slate-900 dark:text-white flex items-center gap-1.5">
          <Clock class="w-4 h-4 text-amber-500 dark:text-amber-400" />
          3. Auto Expiry
        </span>
        <p class="text-slate-500 dark:text-[#b3b3b3]">
          Once expired, the temporary QR code will be invalidated automatically by security.
        </p>
      </div>
    </div>

    <!-- Guest Account Information Card -->
    <div class="p-5 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm space-y-3">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="p-2.5 bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 rounded-2xl">
            <Mail class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Guest Account Information</h3>
            <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3]">
              Transient account provisioned for event admission
            </p>
          </div>
        </div>
        <span
          class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border"
          :class="!isExpired
            ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
            : 'bg-rose-100 dark:bg-rose-950/40 text-rose-700 dark:text-rose-300 border-rose-300 dark:border-rose-800'"
        >
          {{ !isExpired ? 'ACTIVE PASS' : 'EXPIRED' }}
        </span>
      </div>

      <div class="pt-2 border-t border-slate-100 dark:border-[#2a2a2a] text-xs text-slate-600 dark:text-[#b3b3b3] flex items-center justify-between flex-wrap gap-2">
        <div>
          <p>Guest Reference / ID: <strong class="font-mono text-slate-900 dark:text-white">{{ currentUser.studentId || currentUser.id }}</strong></p>
          <p v-if="currentUser.email" class="mt-1">Contact Email: <strong class="font-mono text-slate-900 dark:text-white">{{ currentUser.email }}</strong></p>
        </div>
        <div>
          <span v-if="currentUser.hasRecoveryPin" class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-xl text-xs font-bold bg-emerald-100/90 dark:bg-emerald-950/60 text-emerald-800 dark:text-emerald-300 border border-emerald-300/80 dark:border-emerald-800">
            <ShieldCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
            <span>Recovery PIN Active</span>
          </span>
          <button
            v-else
            type="button"
            @click="isRecoveryPinModalOpen = true"
            class="inline-flex items-center gap-1.5 px-3 py-1 rounded-xl text-xs font-bold bg-amber-100 hover:bg-amber-200 dark:bg-amber-950/60 dark:hover:bg-amber-900/60 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800 transition cursor-pointer"
          >
            <KeyRound class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400" />
            <span>Configure 4-Digit Recovery PIN</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Ticket Pass Display -->
    <div class="space-y-4">
      <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
        <QrCode class="w-5 h-5 text-blue-600 dark:text-blue-400" />
        Connected Temporary Event Pass
      </h2>

      <div v-if="tempTickets.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-6">
        <DigitalTicketCard
          v-for="t in tempTickets"
          :key="t.id"
          :ticket="t"
          :event="getEventForTicket(t.eventId)"
          :can-transfer="false"
        />
      </div>

      <div v-else class="p-8 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl text-center text-slate-500 dark:text-[#b3b3b3] text-xs">
        No temporary ticket pass linked. Contact event administration booth.
      </div>
    </div>

    <!-- Recovery PIN Setup Modal -->
    <RecoveryPinSetupModal
      :is-open="isRecoveryPinModalOpen"
      :can-dismiss="true"
      @close="isRecoveryPinModalOpen = false"
      @saved="fetchStateFromServer"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Clock, CheckCircle2, MapPin, QrCode, Mail, ShieldCheck, AlertCircle, KeyRound, Eye, EyeOff } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { getOutsiderAccountExpirationDate, formatPhilippineExpiration } from '../../utils/philippineTime';
import DigitalTicketCard from '../tickets/DigitalTicketCard.vue';
import RecoveryPinSetupModal from '../auth/RecoveryPinSetupModal.vue';

const { currentUser, tickets, events, logout, updateUserPassword, fetchStateFromServer } = useAppStore();

const newPassword = ref('');
const confirmNewPassword = ref('');
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const passError = ref('');
const passSuccess = ref('');
const isSubmitting = ref(false);
const isRecoveryPinModalOpen = ref(false);

const handleChangePassword = async () => {
  passError.value = '';
  passSuccess.value = '';
  if (newPassword.value !== confirmNewPassword.value) {
    passError.value = 'Passwords do not match. Please verify.';
    return;
  }
  if (newPassword.value.length < 4) {
    passError.value = 'Password must be at least 4 characters long.';
    return;
  }

  isSubmitting.value = true;
  const res = await updateUserPassword(
    currentUser.value?.id || '',
    newPassword.value
  );
  isSubmitting.value = false;

  if (res.success) {
    passSuccess.value = 'Permanent password established successfully! Next, configure your 4-digit Recovery PIN.';
    newPassword.value = '';
    confirmNewPassword.value = '';
    await fetchStateFromServer();
    setTimeout(() => {
      passSuccess.value = '';
      if (!currentUser.value?.hasRecoveryPin) {
        isRecoveryPinModalOpen.value = true;
      }
    }, 1000);
  } else {
    passError.value = res.message || 'Failed to update password.';
  }
};

const timeLeft = ref<{ days: number; hours: number; minutes: number; seconds: number } | null>(null);

const tempTickets = computed(() => {
  if (!currentUser.value) return [];
  const uid = currentUser.value.id;
  const guestRef = currentUser.value.studentId;
  const email = (currentUser.value.email || '').trim().toLowerCase();

  return tickets.value.filter(t => {
    const isOwner = t.currentAttendeeId === uid || t.originalOwnerId === uid;
    const isGuestRef = Boolean(guestRef && (t.attendeeStudentId === guestRef || t.outsiderId === guestRef || t.ticketNumber === guestRef || t.id === guestRef));
    const isContact = Boolean(guestRef && t.contactNumber && (t.contactNumber === guestRef || guestRef.includes(t.contactNumber) || t.contactNumber.includes(guestRef)));
    const isEmail = Boolean(email && t.attendeeEmail && t.attendeeEmail.trim().toLowerCase() === email);
    return isOwner || isGuestRef || isContact || isEmail;
  });
});

const expirationInfo = computed(() =>
  currentUser.value ? getOutsiderAccountExpirationDate(currentUser.value, events.value, tickets.value) : { expirationDate: null, latestEvent: null, associatedEvents: [] }
);

const expiresAtTimestamp = computed(() => {
  if (expirationInfo.value.expirationDate) {
    return expirationInfo.value.expirationDate.getTime();
  }
  if (currentUser.value?.expiresAt) {
    const t = new Date(currentUser.value.expiresAt).getTime();
    if (!isNaN(t)) return t;
  }
  return Date.now() + 24 * 3600 * 1000;
});

const formattedExpiration = computed(() => {
  if (expirationInfo.value.expirationDate) {
    return formatPhilippineExpiration(expirationInfo.value.expirationDate);
  }
  return 'Event Conclusion (12:00:00 AM PHT)';
});

const isExpired = computed(() => Date.now() >= expiresAtTimestamp.value);

const getEventForTicket = (eventId: string) => {
  return events.value.find(e => e.id === eventId) || events.value[0];
};

let interval: any = null;

const updateCountdown = () => {
  const diff = Math.max(0, expiresAtTimestamp.value - Date.now());
  const days = Math.floor(diff / (1000 * 60 * 60 * 24));
  const hours = Math.floor((diff % (1000 * 60 * 60 * 24)) / (1000 * 60 * 60));
  const minutes = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
  const seconds = Math.floor((diff % (1000 * 60)) / 1000);
  timeLeft.value = { days, hours, minutes, seconds };

  if (diff === 0 && currentUser.value && currentUser.value.isActive !== false) {
    // Auto-expired in real-time
    currentUser.value.isActive = false;
    currentUser.value.accountStatus = 'deactivated';
  }
};

onMounted(() => {
  updateCountdown();
  interval = setInterval(updateCountdown, 1000);
});

onUnmounted(() => {
  if (interval) clearInterval(interval);
});
</script>
