<template>
  <div class="space-y-6 max-w-4xl mx-auto">
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

    <!-- Recovery Email Setup Card for Active Guests -->
    <div class="p-5 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm space-y-4">
      <div class="flex items-center justify-between flex-wrap gap-2">
        <div class="flex items-center gap-3">
          <div class="p-2.5 bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 rounded-2xl">
            <Mail class="w-5 h-5" />
          </div>
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Guest Account Recovery Email</h3>
            <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3]">
              Register and verify a personal email to enable self-service password recovery.
            </p>
          </div>
        </div>
        <span
          class="text-[10px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full border flex items-center gap-1"
          :class="currentUser.recoveryEmailVerified
            ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
            : 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800'"
        >
          <ShieldCheck v-if="currentUser.recoveryEmailVerified" class="w-3.5 h-3.5" />
          <AlertCircle v-else class="w-3.5 h-3.5" />
          <span>{{ currentUser.recoveryEmailVerified ? 'Verified' : 'Unverified' }}</span>
        </span>
      </div>

      <div v-if="isExpired" class="p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 rounded-xl text-rose-700 dark:text-rose-300 text-xs">
        This guest pass has expired. Expired or deactivated guest accounts cannot modify recovery information or initiate password resets.
      </div>

      <div v-else class="space-y-3">
        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-600 dark:text-[#b3b3b3]">
            Current registered email: <strong>{{ currentUser.recoveryEmail || currentUser.email || 'None' }}</strong>
          </span>
          <button
            type="button"
            @click="isEditingGuestEmail = !isEditingGuestEmail"
            class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
          >
            {{ isEditingGuestEmail ? 'Cancel' : (currentUser.recoveryEmailVerified ? 'Update Email' : 'Register Email') }}
          </button>
        </div>

        <div v-if="isEditingGuestEmail" class="p-4 bg-slate-50 dark:bg-[#181818] border border-slate-200 dark:border-[#2e2e2e] rounded-2xl space-y-3">
          <div v-if="guestEmailError" class="p-2.5 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs">
            {{ guestEmailError }}
          </div>
          <div v-if="guestEmailSuccess" class="p-2.5 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-700 dark:text-emerald-300 text-xs font-bold">
            {{ guestEmailSuccess }}
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">Personal Recovery Email *</label>
            <div class="flex gap-2">
              <input
                v-model="guestRecoveryEmailInput"
                type="email"
                placeholder="e.g. personal@gmail.com"
                class="flex-1 px-3 py-2 bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
              <button
                type="button"
                @click="handleSendGuestOtp"
                :disabled="isSendingGuestOtp || !guestRecoveryEmailInput.trim() || !guestRecoveryEmailInput.includes('@')"
                class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer disabled:opacity-50 shrink-0 shadow-xs"
              >
                <span v-if="isSendingGuestOtp" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span v-else>{{ guestOtpSent ? 'Resend' : 'Send Code' }}</span>
              </button>
            </div>
            <p v-if="guestOtpFeedback" class="text-[11px] font-bold text-blue-600 dark:text-blue-400 mt-1">{{ guestOtpFeedback }}</p>
          </div>

          <div class="space-y-1">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">6-Digit Verification Code *</label>
            <div class="flex gap-2">
              <input
                v-model="guestOtpCode"
                type="text"
                maxlength="6"
                placeholder="Enter 6-digit OTP"
                class="flex-1 px-3 py-2 bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-center font-mono tracking-widest text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
              <button
                type="button"
                @click="handleVerifyGuestEmail"
                :disabled="isVerifyingGuestEmail || !guestRecoveryEmailInput.trim() || !guestOtpCode.trim()"
                class="px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition cursor-pointer disabled:opacity-50 shrink-0 shadow-xs"
              >
                <span v-if="isVerifyingGuestEmail" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span v-else>Verify & Save</span>
              </button>
            </div>
          </div>
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
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { Clock, CheckCircle2, MapPin, QrCode, Mail, ShieldCheck, AlertCircle } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { getOutsiderAccountExpirationDate, formatPhilippineExpiration } from '../../utils/philippineTime';
import DigitalTicketCard from '../tickets/DigitalTicketCard.vue';

const { currentUser, tickets, events, logout, requestEmailVerification, verifyRecoveryEmail } = useAppStore();

const isEditingGuestEmail = ref(false);
const guestRecoveryEmailInput = ref('');
const guestOtpCode = ref('');
const isSendingGuestOtp = ref(false);
const guestOtpSent = ref(false);
const guestOtpFeedback = ref('');
const isVerifyingGuestEmail = ref(false);
const guestEmailError = ref('');
const guestEmailSuccess = ref('');

const handleSendGuestOtp = async () => {
  guestEmailError.value = '';
  guestOtpFeedback.value = '';
  if (!guestRecoveryEmailInput.value.trim() || !guestRecoveryEmailInput.value.includes('@')) {
    guestEmailError.value = 'Please provide a valid personal email address.';
    return;
  }
  isSendingGuestOtp.value = true;
  const res = await requestEmailVerification(guestRecoveryEmailInput.value.trim());
  isSendingGuestOtp.value = false;
  if (res.success) {
    guestOtpSent.value = true;
    guestOtpFeedback.value = res.message || 'Verification code sent to your email!';
    if (res.dev_verification_code) {
      guestOtpCode.value = res.dev_verification_code;
    }
  } else {
    guestEmailError.value = res.error || 'Failed to send verification code.';
  }
};

const handleVerifyGuestEmail = async () => {
  guestEmailError.value = '';
  guestEmailSuccess.value = '';
  if (!guestOtpCode.value.trim() || guestOtpCode.value.trim().length !== 6) {
    guestEmailError.value = 'Please enter the complete 6-digit verification code.';
    return;
  }
  isVerifyingGuestEmail.value = true;
  const res = await verifyRecoveryEmail(guestRecoveryEmailInput.value.trim(), guestOtpCode.value.trim());
  isVerifyingGuestEmail.value = false;
  if (res.success) {
    guestEmailSuccess.value = 'Recovery email verified successfully!';
    setTimeout(() => {
      isEditingGuestEmail.value = false;
      guestEmailSuccess.value = '';
    }, 1200);
  } else {
    guestEmailError.value = res.error || 'Failed to verify recovery email code.';
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
