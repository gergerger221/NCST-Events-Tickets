<template>
  <div>
    <!-- Main Forgot Password Multi-Step Modal -->
    <Modal
      :is-open="isOpen"
      @close="handleClose"
      :title="modalTitle"
      :subtitle="modalSubtitle"
      max-width="md"
    >
      <div class="space-y-4">
        <!-- Error Banner inside form -->
        <div
          v-if="formError"
          class="p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 shrink-0 text-rose-600 dark:text-rose-400" />
          <span>{{ formError }}</span>
        </div>

        <!-- Cooldown / Lockout Banner -->
        <div
          v-if="cooldownSeconds > 0"
          class="p-3 bg-amber-50 dark:bg-amber-950/70 border border-amber-200 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-300 text-xs flex items-center gap-2"
        >
          <Clock class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
          <span>Recovery locked. Please wait {{ cooldownSeconds }}s before trying again.</span>
        </div>

        <!-- ========================================== -->
        <!-- STEP 1: Identify Account                   -->
        <!-- ========================================== -->
        <form v-if="step === 'identify'" @submit.prevent="handleIdentify" class="space-y-4">
          <div class="p-3 bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/50 rounded-2xl text-xs text-blue-900 dark:text-blue-300 space-y-1">
            <p class="font-bold flex items-center gap-1.5">
              <KeyRound class="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <span>Self-Service Password Recovery</span>
            </p>
            <p class="text-[11px] text-blue-800 dark:text-blue-300/90 leading-relaxed">
              Enter your official Student ID or Guest Reference Code to verify your Recovery PIN and set a new password.
            </p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              ID Number / Guest Reference *
            </label>
            <div class="relative">
              <UserIcon class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="identifier"
                type="text"
                required
                :disabled="isLoading || cooldownSeconds > 0"
                placeholder="e.g. 2026-00125 or GUEST-1234"
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white placeholder:font-normal placeholder:text-slate-400 focus:outline-none focus:border-blue-500 transition"
              />
            </div>
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              Supports Student ID Number, Guest Reference Code, or Ticket Control Number.
            </p>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="button"
              @click="step = 'admin_fallback'"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline cursor-pointer font-medium"
            >
              No PIN? Visit Administrator
            </button>
            <button
              type="submit"
              :disabled="isLoading || !identifier.trim() || cooldownSeconds > 0"
              class="py-2.5 px-5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <span>Continue</span>
            </button>
          </div>
        </form>

        <!-- ========================================== -->
        <!-- STEP 2: Verify 4-Digit Recovery PIN        -->
        <!-- ========================================== -->
        <form v-else-if="step === 'verify_pin'" @submit.prevent="handleVerifyPin" class="space-y-4">
          <div class="p-3 bg-slate-50 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-2xl flex items-center justify-between text-xs">
            <div>
              <p class="font-bold text-slate-800 dark:text-slate-200">{{ accountDisplayName }}</p>
              <p class="text-[10.5px] text-slate-500 font-mono">{{ identifier }}</p>
            </div>
            <button
              type="button"
              @click="step = 'identify'"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
            >
              Change
            </button>
          </div>

          <div class="space-y-2 text-center py-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              Enter Your 4-Digit Recovery PIN *
            </label>
            <PinInput
              v-model="recoveryPin"
              :length="4"
              :mask="true"
              :autofocus="true"
              :disabled="isLoading || cooldownSeconds > 0"
              @complete="handleVerifyPin"
              aria-label="Recovery PIN"
            />
            <p class="text-[10.5px] text-slate-400 dark:text-slate-500 mt-1">
              Enter the 4-digit numeric code configured on your account.
            </p>
          </div>

          <div class="flex items-center justify-between pt-2 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="button"
              @click="step = 'admin_fallback'"
              class="text-xs text-slate-500 dark:text-slate-400 hover:underline cursor-pointer"
            >
              Forgot Recovery PIN?
            </button>
            <button
              type="submit"
              :disabled="isLoading || recoveryPin.length !== 4 || cooldownSeconds > 0"
              class="py-2.5 px-5 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-xs transition flex items-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <span>Verify PIN</span>
            </button>
          </div>
        </form>

        <!-- ========================================== -->
        <!-- STEP 3: Set New Password                   -->
        <!-- ========================================== -->
        <form v-else-if="step === 'new_password'" @submit.prevent="handleResetPassword" class="space-y-3.5">
          <div class="p-3 bg-emerald-50/80 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-xs text-emerald-800 dark:text-emerald-300 flex items-center gap-2">
            <ShieldCheck class="w-4 h-4 shrink-0 text-emerald-600 dark:text-emerald-400" />
            <span>Identity verified! Create your new confidential password.</span>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">New Password *</label>
            <div class="relative">
              <input
                v-model="newPassword"
                :type="showNewPassword ? 'text' : 'password'"
                required
                minlength="4"
                placeholder="At least 4 characters"
                class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
              />
              <button
                type="button"
                @click="showNewPassword = !showNewPassword"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <EyeOff v-if="showNewPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
            <div class="relative">
              <input
                v-model="confirmPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                required
                minlength="4"
                placeholder="Re-type new password"
                class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 cursor-pointer"
              >
                <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="pt-2 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="submit"
              :disabled="isLoading || !newPassword.trim() || !confirmPassword.trim()"
              class="w-full py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <span>Reset Password & Complete</span>
            </button>
          </div>
        </form>

        <!-- ========================================== -->
        <!-- STEP 4: Reset Success View                 -->
        <!-- ========================================== -->
        <div v-else-if="step === 'success'" class="text-center py-4 space-y-4">
          <div class="w-16 h-16 rounded-3xl mx-auto flex items-center justify-center bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800 shadow-lg">
            <CheckCircle2 class="w-9 h-9" />
          </div>

          <div>
            <h4 class="text-lg font-extrabold text-slate-900 dark:text-white">Password Reset Successful!</h4>
            <p class="text-xs text-slate-600 dark:text-slate-300 mt-1.5 leading-relaxed max-w-sm mx-auto">
              Your account password has been updated. Please sign in now using your newly established password.
            </p>
          </div>

          <div class="pt-2">
            <button
              type="button"
              @click="handleFinishSuccess"
              class="w-full py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-md transition cursor-pointer"
            >
              Return to Login
            </button>
          </div>
        </div>

        <!-- ========================================== -->
        <!-- FALLBACK: Administrator Assisted Guidance  -->
        <!-- ========================================== -->
        <div v-else-if="step === 'admin_fallback'" class="space-y-3.5 text-xs text-slate-600 dark:text-slate-300">
          <div class="p-3 bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl space-y-1">
            <p class="font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
              <ShieldAlert class="w-4 h-4 text-amber-600 dark:text-amber-400" />
              <span>Institutional Identity Verification Fallback</span>
            </p>
            <p class="text-[11px] text-amber-800 dark:text-amber-300 leading-relaxed">
              If you have not set up a Recovery PIN or forgot both your password and PIN, visit the Campus Administrator or Registrar Office.
            </p>
          </div>

          <ol class="list-decimal list-inside space-y-1.5 text-[11px] text-slate-600 dark:text-slate-400 pl-1">
            <li>Visit the <strong>Campus Administrator Desk</strong> or <strong>Registrar Office</strong>.</li>
            <li>Present your official <strong>Student ID Card</strong> or admission receipt.</li>
            <li>Authorized personnel will verify your identity and reset your account to your official surname.</li>
          </ol>

          <div class="pt-3 border-t border-slate-200 dark:border-[#333333] flex items-center justify-between">
            <button
              type="button"
              @click="step = 'identify'"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
            >
              Back to Self-Service Reset
            </button>
            <button
              type="button"
              @click="handleClose"
              class="px-4 py-2 bg-slate-100 dark:bg-[#333] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
            >
              Close
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Sub-Modal for Modal Notifications (Mandatory Requirement 4) -->
    <Modal
      :is-open="isNoticeOpen"
      @close="isNoticeOpen = false"
      :title="noticeTitle"
      max-width="sm"
    >
      <div class="space-y-4 text-center py-2">
        <div
          class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center shadow-md"
          :class="noticeVariant === 'success' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : (noticeVariant === 'warning' ? 'bg-amber-100 text-amber-600 dark:bg-amber-950/60 dark:text-amber-400 border border-amber-200 dark:border-amber-800' : 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800')"
        >
          <CheckCircle2 v-if="noticeVariant === 'success'" class="w-8 h-8" />
          <Clock v-else-if="noticeVariant === 'warning'" class="w-8 h-8" />
          <AlertCircle v-else class="w-8 h-8" />
        </div>

        <div>
          <h4 class="text-base font-extrabold text-slate-900 dark:text-white">{{ noticeTitle }}</h4>
          <p class="text-xs text-slate-600 dark:text-[#b3b3b3] mt-1.5 leading-relaxed">{{ noticeMessage }}</p>
        </div>

        <div class="pt-2">
          <button
            type="button"
            @click="isNoticeOpen = false"
            class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-white transition shadow-md cursor-pointer"
            :class="noticeVariant === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : (noticeVariant === 'warning' ? 'bg-amber-600 hover:bg-amber-700' : 'bg-slate-700 hover:bg-slate-800 dark:bg-slate-600 dark:hover:bg-slate-700')"
          >
            {{ noticeButtonText }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import Modal from '../common/Modal.vue';
import PinInput from '../common/PinInput.vue';
import {
  User as UserIcon,
  KeyRound,
  ShieldCheck,
  ShieldAlert,
  AlertCircle,
  CheckCircle2,
  Clock,
  Eye,
  EyeOff
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'success'): void;
}>();

const { identifyForRecovery, verifyRecoveryPin, resetPasswordWithPinToken } = useAppStore();

type RecoveryStep = 'identify' | 'verify_pin' | 'new_password' | 'success' | 'admin_fallback';

const step = ref<RecoveryStep>('identify');
const identifier = ref('');
const accountDisplayName = ref('');
const recoveryPin = ref('');
const resetToken = ref('');
const newPassword = ref('');
const confirmPassword = ref('');
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const isLoading = ref(false);
const formError = ref('');
const cooldownSeconds = ref(0);
let cooldownTimer: any = null;

// Modal Notification State
const isNoticeOpen = ref(false);
const noticeTitle = ref('');
const noticeMessage = ref('');
const noticeVariant = ref<'success' | 'warning' | 'error'>('error');
const noticeButtonText = ref('Understood');

const showModalNotification = (title: string, message: string, variant: 'success' | 'warning' | 'error' = 'error', btnText: string = 'Understood') => {
  noticeTitle.value = title;
  noticeMessage.value = message;
  noticeVariant.value = variant;
  noticeButtonText.value = btnText;
  isNoticeOpen.value = true;
};

const modalTitle = computed(() => {
  switch (step.value) {
    case 'identify': return 'Forgot Password';
    case 'verify_pin': return 'Verify Recovery PIN';
    case 'new_password': return 'Set New Password';
    case 'success': return 'Password Updated';
    case 'admin_fallback': return 'Administrator Assistance';
    default: return 'Account Recovery';
  }
});

const modalSubtitle = computed(() => {
  switch (step.value) {
    case 'identify': return 'Step 1 of 3: Enter your account identifier';
    case 'verify_pin': return 'Step 2 of 3: Enter your 4-digit security code';
    case 'new_password': return 'Step 3 of 3: Create a new confidential password';
    case 'success': return 'Your account is ready';
    case 'admin_fallback': return 'Institutional desk support';
    default: return '';
  }
});

const startCooldown = (seconds: number) => {
  if (cooldownTimer) clearInterval(cooldownTimer);
  cooldownSeconds.value = seconds;
  cooldownTimer = setInterval(() => {
    cooldownSeconds.value--;
    if (cooldownSeconds.value <= 0) {
      clearInterval(cooldownTimer);
      cooldownTimer = null;
    }
  }, 1000);
};

watch(() => props.isOpen, (open) => {
  if (open) {
    step.value = 'identify';
    identifier.value = '';
    accountDisplayName.value = '';
    recoveryPin.value = '';
    resetToken.value = '';
    newPassword.value = '';
    confirmPassword.value = '';
    formError.value = '';
  }
});

const handleClose = () => {
  emit('close');
};

const handleFinishSuccess = () => {
  emit('success');
  emit('close');
};

// STEP 1: Identify Account
const handleIdentify = async () => {
  formError.value = '';
  if (!identifier.value.trim()) {
    formError.value = 'Please enter your ID Number or Guest Reference Code.';
    return;
  }

  isLoading.value = true;
  const res = await identifyForRecovery(identifier.value.trim());
  isLoading.value = false;

  if (res.success) {
    accountDisplayName.value = res.name || res.identifier || 'Verified Account';
    step.value = 'verify_pin';
  } else {
    if (res.cooldown) {
      startCooldown(res.cooldown);
      showModalNotification(
        'Too Many Attempts',
        `Account recovery is temporarily locked due to multiple incorrect attempts. Please wait ${res.cooldown} seconds before trying again.`,
        'warning'
      );
    } else if (res.noPin) {
      step.value = 'admin_fallback';
      showModalNotification(
        'No Recovery PIN Configured',
        res.error || 'No Recovery PIN was found for this account. Please visit the Campus Administrator Desk for assisted recovery.',
        'warning'
      );
    } else {
      formError.value = res.error || 'Unable to proceed with account recovery.';
      showModalNotification(
        'Recovery Notice',
        res.error || 'Unable to process recovery request. Please check your ID Number.',
        'error'
      );
    }
  }
};

// STEP 2: Verify PIN
const handleVerifyPin = async () => {
  formError.value = '';
  if (recoveryPin.value.length !== 4) {
    formError.value = 'Recovery PIN must be exactly 4 digits.';
    return;
  }

  isLoading.value = true;
  const res = await verifyRecoveryPin(identifier.value.trim(), recoveryPin.value);
  isLoading.value = false;

  if (res.success && res.resetToken) {
    resetToken.value = res.resetToken;
    step.value = 'new_password';
  } else {
    recoveryPin.value = '';
    if (res.cooldown) {
      startCooldown(res.cooldown);
      showModalNotification(
        'Too Many Attempts',
        `Too many incorrect Recovery PIN attempts. Account recovery has been temporarily locked for ${res.cooldown} seconds.`,
        'warning'
      );
    } else {
      const remainingMsg = res.remainingAttempts !== undefined
        ? ` (${res.remainingAttempts} attempt(s) remaining)`
        : '';
      formError.value = (res.error || 'Invalid Recovery PIN.') + remainingMsg;
      showModalNotification(
        'Invalid Recovery PIN',
        (res.error || 'The entered Recovery PIN is incorrect. Please check your 4 digits.') + remainingMsg,
        'error'
      );
    }
  }
};

// STEP 3: Reset Password
const handleResetPassword = async () => {
  formError.value = '';

  if (newPassword.value.length < 4) {
    formError.value = 'New password must be at least 4 characters long.';
    showModalNotification(
      'Invalid New Password',
      'Password must be at least 4 characters long and cannot be your surname or generic default password.',
      'error'
    );
    return;
  }

  if (newPassword.value !== confirmPassword.value) {
    formError.value = 'Passwords do not match.';
    showModalNotification(
      'Password Confirmation Mismatch',
      'The entered passwords do not match. Please re-confirm your new password.',
      'error'
    );
    return;
  }

  isLoading.value = true;
  const res = await resetPasswordWithPinToken(resetToken.value, newPassword.value, confirmPassword.value);
  isLoading.value = false;

  if (res.success) {
    step.value = 'success';
    showModalNotification(
      'Password Reset Successful',
      'Your password has been updated successfully! You can now sign in using your new credentials.',
      'success',
      'Continue to Login'
    );
  } else {
    formError.value = res.error || 'Failed to reset password.';
    showModalNotification(
      'Reset Failed',
      res.error || 'Failed to reset password. Please try again or restart recovery.',
      'error'
    );
  }
};
</script>
