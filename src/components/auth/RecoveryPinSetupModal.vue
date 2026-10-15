<template>
  <div>
    <!-- Main Recovery PIN Enrollment Modal -->
    <Modal
      :is-open="isOpen"
      @close="canDismiss ? $emit('close') : null"
      title="Set Up Recovery PIN"
      subtitle="4-digit personal security code for self-service account recovery"
      max-width="md"
    >
      <div class="space-y-5">
        <!-- Notice banner -->
        <div class="p-3.5 bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-2xl text-xs text-blue-900 dark:text-blue-300 space-y-1.5 leading-relaxed">
          <p class="font-bold flex items-center gap-1.5 text-blue-950 dark:text-blue-200">
            <ShieldCheck class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
            <span>Self-Service Password Recovery</span>
          </p>
          <p class="text-[11px] text-blue-800/90 dark:text-blue-300/90">
            Create a private <strong>4-digit numeric PIN</strong>. If you ever forget your password, you can reset it instantly without needing Campus Administrator assistance.
          </p>
        </div>

        <form @submit.prevent="handleSubmitPin" class="space-y-4">
          <!-- Step 1: PIN Input -->
          <div class="space-y-1.5 text-center">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              Enter 4-Digit Recovery PIN *
            </label>
            <PinInput
              v-model="pin"
              :length="4"
              :mask="true"
              :autofocus="true"
              :has-error="pinMismatch"
              @complete="focusConfirmPin"
              aria-label="Recovery PIN"
            />
          </div>

          <!-- Step 2: Confirm PIN Input -->
          <div class="space-y-1.5 text-center pt-2">
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
              Confirm 4-Digit PIN *
            </label>
            <PinInput
              ref="confirmInputRef"
              v-model="confirmPin"
              :length="4"
              :mask="true"
              :has-error="pinMismatch"
              aria-label="Confirm Recovery PIN"
            />
          </div>

          <!-- Inline feedback -->
          <div v-if="pinMismatch" class="p-2.5 bg-rose-50 dark:bg-rose-950/60 border border-rose-200 dark:border-rose-900/50 rounded-xl text-rose-700 dark:text-rose-300 text-xs text-center font-semibold flex items-center justify-center gap-1.5">
            <AlertCircle class="w-4 h-4 shrink-0" />
            <span>PIN entries do not match. Please verify your 4 digits.</span>
          </div>

          <div class="flex items-center justify-end gap-2.5 pt-3 border-t border-slate-200 dark:border-[#333333]">
            <button
              v-if="canDismiss"
              type="button"
              @click="$emit('close')"
              class="px-4 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#383838] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
            >
              Skip For Now
            </button>
            <button
              type="submit"
              :disabled="isSubmitting || pin.length !== 4 || confirmPin.length !== 4"
              class="w-full sm:w-auto px-6 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer shadow-md disabled:opacity-50 flex items-center justify-center gap-2"
            >
              <span v-if="isSubmitting" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <span>{{ isSubmitting ? 'Saving PIN...' : 'Save Recovery PIN & Continue' }}</span>
            </button>
          </div>
        </form>
      </div>
    </Modal>

    <!-- Notification Modal for Feedback / Alerts -->
    <Modal
      :is-open="isNoticeModalOpen"
      @close="handleNoticeClose"
      :title="noticeTitle"
      max-width="sm"
    >
      <div class="space-y-4 text-center py-2">
        <div
          class="w-14 h-14 rounded-2xl mx-auto flex items-center justify-center shadow-md"
          :class="noticeVariant === 'success' ? 'bg-emerald-100 text-emerald-600 dark:bg-emerald-950/60 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-800' : 'bg-rose-100 text-rose-600 dark:bg-rose-950/60 dark:text-rose-400 border border-rose-200 dark:border-rose-800'"
        >
          <CheckCircle2 v-if="noticeVariant === 'success'" class="w-8 h-8" />
          <AlertCircle v-else class="w-8 h-8" />
        </div>

        <div>
          <h4 class="text-base font-extrabold text-slate-900 dark:text-white">{{ noticeTitle }}</h4>
          <p class="text-xs text-slate-600 dark:text-[#b3b3b3] mt-1.5 leading-relaxed">{{ noticeMessage }}</p>
        </div>

        <div class="pt-2">
          <button
            type="button"
            @click="handleNoticeClose"
            class="w-full py-2.5 px-4 rounded-xl text-xs font-bold text-white transition shadow-md cursor-pointer"
            :class="noticeVariant === 'success' ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-slate-700 hover:bg-slate-800 dark:bg-slate-600 dark:hover:bg-slate-700'"
          >
            {{ noticeButtonText }}
          </button>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import Modal from '../common/Modal.vue';
import PinInput from '../common/PinInput.vue';
import { ShieldCheck, AlertCircle, CheckCircle2 } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    canDismiss?: boolean;
  }>(),
  {
    canDismiss: false,
  }
);

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'saved'): void;
}>();

const { setRecoveryPin } = useAppStore();

const pin = ref('');
const confirmPin = ref('');
const isSubmitting = ref(false);
const confirmInputRef = ref<any>(null);

const isNoticeModalOpen = ref(false);
const noticeTitle = ref('');
const noticeMessage = ref('');
const noticeVariant = ref<'success' | 'error'>('success');
const noticeButtonText = ref('OK');

const pinMismatch = computed(() => {
  return pin.value.length === 4 && confirmPin.value.length === 4 && pin.value !== confirmPin.value;
});

const focusConfirmPin = () => {
  // Focus confirm input when first PIN is complete
};

const showNotice = (title: string, message: string, variant: 'success' | 'error' = 'success', btnText: string = 'OK') => {
  noticeTitle.value = title;
  noticeMessage.value = message;
  noticeVariant.value = variant;
  noticeButtonText.value = btnText;
  isNoticeModalOpen.value = true;
};

const handleNoticeClose = () => {
  isNoticeModalOpen.value = false;
  if (noticeVariant.value === 'success') {
    emit('saved');
    emit('close');
  }
};

const handleSubmitPin = async () => {
  if (pin.value.length !== 4) {
    showNotice('Invalid PIN', 'Recovery PIN must be exactly 4 numeric digits.', 'error');
    return;
  }

  if (pin.value !== confirmPin.value) {
    showNotice('PIN Mismatch', 'The Recovery PIN entries do not match. Please verify your 4-digit entries and try again.', 'error');
    return;
  }

  isSubmitting.value = true;
  const res = await setRecoveryPin(pin.value);
  isSubmitting.value = false;

  if (res.success) {
    showNotice(
      'Recovery PIN Saved',
      'Your 4-digit Recovery PIN has been successfully configured. You can now use this PIN to self-reset your password if you ever forget it.',
      'success',
      'Enter Portal'
    );
  } else {
    showNotice('Setup Failed', res.message || 'Failed to save Recovery PIN. Please try again.', 'error');
  }
};
</script>
