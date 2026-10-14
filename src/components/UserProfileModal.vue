<template>
  <Modal :is-open="isOpen" title="Account & Profile" subtitle="Your Authenticated User Session" max-width="md" @close="$emit('close')">
    <div v-if="currentUser" class="space-y-6">
      <!-- Profile Header -->
      <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] transition-colors">
        <div class="relative group">
          <UserAvatar :src="currentUser.photoUrl" :alt="currentUser.name" size="lg" shape="rounded" />
          <input
            type="file"
            accept="image/*"
            ref="profileFileInput"
            @change="handleProfilePhotoUpload"
            class="hidden"
          />
          <button
            type="button"
            @click="profileFileInput?.click()"
            class="absolute -bottom-1 -right-1 p-1 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow transition cursor-pointer"
            title="Upload new profile picture"
          >
            <Camera class="w-3.5 h-3.5" />
          </button>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <h4 class="font-extrabold text-slate-900 dark:text-white text-base truncate">{{ currentUser.name }}</h4>
          </div>
          <p class="text-xs text-slate-500 dark:text-[#b3b3b3] truncate">{{ currentUser.email }}</p>
          <div class="mt-1.5 flex items-center gap-2">
            <StatusBadge type="role" :status="currentUser.role" />
            <span v-if="currentUser.studentId" class="text-[10px] font-mono text-slate-600 dark:text-[#b3b3b3] bg-slate-200 dark:bg-[#2c2c2c] px-2 py-0.5 rounded border border-slate-300 dark:border-[#3a3a3a]">
              ID: {{ currentUser.studentId }}
            </span>
          </div>
        </div>
      </div>

      <!-- Theme Preference Setting -->
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] space-y-2.5 transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
            <Palette class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            Display Theme
          </span>
          <span class="text-[11px] font-mono text-slate-500 dark:text-[#b3b3b3] capitalize">
            {{ isDark ? 'Dark Mode' : 'Light Mode' }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="setTheme('light')"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer"
            :class="!isDark ? 'bg-white text-blue-600 shadow-sm border border-blue-500/40 ring-2 ring-blue-500/20' : 'bg-slate-200/60 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white border border-transparent'"
          >
            <Sun class="w-4 h-4 text-amber-500" />
            <span>Light Mode</span>
          </button>

          <button
            type="button"
            @click="setTheme('dark')"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer"
            :class="isDark ? 'bg-[#2c2c2c] text-blue-400 shadow-sm border border-blue-500/40 ring-2 ring-blue-500/20' : 'bg-slate-200/60 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white border border-transparent'"
          >
            <Moon class="w-4 h-4 text-blue-400" />
            <span>Dark Mode</span>
          </button>
        </div>
      </div>

      <!-- User Details Grid -->
      <div class="space-y-2.5 text-xs">
        <div v-if="currentUser.course" class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Program / Year & Section</span>
          <span class="font-semibold text-slate-800 dark:text-slate-200 text-right truncate max-w-[200px]">
            {{ currentUser.course }} {{ currentUser.yearLevel ? `• ${currentUser.yearLevel}` : '' }} {{ currentUser.section ? `(${currentUser.section})` : '' }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Department / Affiliation</span>
          <span class="font-semibold text-slate-800 dark:text-slate-200 text-right truncate max-w-[200px]">
            {{ currentUser.department || 'Not specified' }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Account Type</span>
          <span class="font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-mono text-[11px]">
            {{ currentUser.role }}
          </span>
        </div>

        <div v-if="currentUser.expiresAt" class="flex items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-500/30">
          <span class="text-amber-700 dark:text-amber-300 font-medium">Pass Expires</span>
          <span class="font-mono text-amber-800 dark:text-amber-200 text-[11px]">
            {{ formatDateTime(currentUser.expiresAt) }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Member Since</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300">
            {{ formatDate(currentUser.createdAt) }}
          </span>
        </div>
      </div>

      <!-- Self-Service Recovery Email Card -->
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] space-y-3 transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
            <Mail class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            Recovery Email
          </span>
          <span
            class="text-[10px] font-bold uppercase tracking-wider px-2 py-0.5 rounded-full border flex items-center gap-1"
            :class="currentUser.recoveryEmailVerified
              ? 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border-emerald-300 dark:border-emerald-800'
              : 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border-amber-300 dark:border-amber-800'"
          >
            <ShieldCheck v-if="currentUser.recoveryEmailVerified" class="w-3 h-3" />
            <AlertCircle v-else class="w-3 h-3" />
            <span>{{ currentUser.recoveryEmailVerified ? 'Verified' : 'Unverified' }}</span>
          </span>
        </div>

        <div class="flex items-center justify-between text-xs">
          <span class="text-slate-500 dark:text-[#b3b3b3] truncate max-w-[240px]">
            {{ currentUser.recoveryEmail || currentUser.email || 'No recovery email registered' }}
          </span>
          <button
            type="button"
            @click="toggleEditEmail"
            class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer ml-2 whitespace-nowrap"
          >
            {{ isEditingEmail ? 'Close' : (currentUser.recoveryEmailVerified ? 'Change' : 'Register & Verify') }}
          </button>
        </div>

        <!-- Inline Verification Form -->
        <div v-if="isEditingEmail" class="pt-2 border-t border-slate-200 dark:border-[#2a2a2a] space-y-2.5">
          <div v-if="emailError" class="p-2 bg-rose-50 dark:bg-rose-950/50 border border-rose-200 dark:border-rose-800 rounded-lg text-rose-700 dark:text-rose-300 text-[11px]">
            {{ emailError }}
          </div>
          <div v-if="emailSuccess" class="p-2 bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 rounded-lg text-emerald-700 dark:text-emerald-300 text-[11px] font-bold">
            {{ emailSuccess }}
          </div>

          <div class="space-y-1">
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
              Recovery Email Address *
            </label>
            <div class="flex gap-2">
              <input
                v-model="newRecoveryEmail"
                type="email"
                placeholder="Institutional or personal email"
                class="flex-1 px-3 py-1.5 bg-white dark:bg-[#181818] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
              <button
                type="button"
                @click="handleSendEmailOtp"
                :disabled="isSendingOtp || !newRecoveryEmail.trim() || !newRecoveryEmail.includes('@')"
                class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-lg text-xs font-bold transition cursor-pointer disabled:opacity-50 shrink-0"
              >
                <span v-if="isSendingOtp" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span v-else>{{ otpSent ? 'Resend' : 'Send Code' }}</span>
              </button>
            </div>
            <p v-if="otpMessage" class="text-[10px] text-blue-600 dark:text-blue-400 font-bold mt-0.5">{{ otpMessage }}</p>
          </div>

          <div class="space-y-1">
            <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300">
              6-Digit Verification Code *
            </label>
            <div class="flex gap-2">
              <input
                v-model="otpCode"
                type="text"
                maxlength="6"
                placeholder="Enter 6-digit OTP"
                class="flex-1 px-3 py-1.5 bg-white dark:bg-[#181818] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-center font-mono tracking-widest text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
              <button
                type="button"
                @click="handleVerifyEmail"
                :disabled="isVerifying || !newRecoveryEmail.trim() || !otpCode.trim()"
                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold transition cursor-pointer disabled:opacity-50 shrink-0"
              >
                <span v-if="isVerifying" class="w-3 h-3 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <span v-else>Verify & Save</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="pt-2 border-t border-slate-200 dark:border-[#333333] flex items-center justify-between gap-3">
        <button
          @click="$emit('close')"
          class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition cursor-pointer"
        >
          Close
        </button>

        <button
          @click="handleLogout"
          class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
        >
          <LogOut class="w-4 h-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </div>

    <!-- Empty State if no current user is logged in -->
    <div v-else class="py-8 text-center space-y-3">
      <p class="text-sm text-slate-500 dark:text-slate-400">No active user session found.</p>
      <button
        @click="$emit('close')"
        class="py-2 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition cursor-pointer"
      >
        Close
      </button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { LogOut, Palette, Sun, Moon, Camera, Mail, ShieldCheck, AlertCircle } from 'lucide-vue-next';
import Modal from './common/Modal.vue';
import StatusBadge from './common/StatusBadge.vue';
import UserAvatar from './common/UserAvatar.vue';
import { useAppStore } from '../composables/useAppStore';
import { useTheme } from '../composables/useTheme';

defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { currentUser, updateUser, logout, requestEmailVerification, verifyRecoveryEmail } = useAppStore();
const { isDark, setTheme } = useTheme();
const profileFileInput = ref<HTMLInputElement | null>(null);

const isEditingEmail = ref(false);
const newRecoveryEmail = ref('');
const otpCode = ref('');
const isSendingOtp = ref(false);
const otpSent = ref(false);
const otpMessage = ref('');
const isVerifying = ref(false);
const emailError = ref('');
const emailSuccess = ref('');

const toggleEditEmail = () => {
  isEditingEmail.value = !isEditingEmail.value;
  if (isEditingEmail.value) {
    newRecoveryEmail.value = currentUser.value?.recoveryEmail || currentUser.value?.email || '';
    otpCode.value = '';
    emailError.value = '';
    emailSuccess.value = '';
    otpMessage.value = '';
    otpSent.value = false;
  }
};

const handleSendEmailOtp = async () => {
  emailError.value = '';
  otpMessage.value = '';
  if (!newRecoveryEmail.value.trim() || !newRecoveryEmail.value.includes('@')) {
    emailError.value = 'Please enter a valid recovery email address.';
    return;
  }
  isSendingOtp.value = true;
  const res = await requestEmailVerification(newRecoveryEmail.value.trim());
  isSendingOtp.value = false;
  if (res.success) {
    otpSent.value = true;
    otpMessage.value = res.message || 'Verification code sent to your email!';
    if (res.dev_verification_code) {
      otpCode.value = res.dev_verification_code;
    }
  } else {
    emailError.value = res.error || 'Failed to send verification code.';
  }
};

const handleVerifyEmail = async () => {
  emailError.value = '';
  emailSuccess.value = '';
  if (!otpCode.value.trim() || otpCode.value.trim().length !== 6) {
    emailError.value = 'Please enter the 6-digit verification code.';
    return;
  }
  isVerifying.value = true;
  const res = await verifyRecoveryEmail(newRecoveryEmail.value.trim(), otpCode.value.trim());
  isVerifying.value = false;
  if (res.success) {
    emailSuccess.value = 'Recovery email verified successfully!';
    setTimeout(() => {
      isEditingEmail.value = false;
      emailSuccess.value = '';
    }, 1200);
  } else {
    emailError.value = res.error || 'Failed to verify recovery email code.';
  }
};

const handleProfilePhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0 || !currentUser.value) return;

  const file = target.files[0];
  if (!file.type.startsWith('image/')) return;

  const reader = new FileReader();
  reader.onload = (event) => {
    if (event.target?.result && currentUser.value) {
      updateUser(currentUser.value.id, { photoUrl: event.target.result as string });
    }
  };
  reader.readAsDataURL(file);
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? 'N/A' : d.toLocaleDateString();
};

const formatDateTime = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? 'N/A' : d.toLocaleString();
};

const handleLogout = () => {
  emit('close');
  logout();
};
</script>
