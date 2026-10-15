<template>
  <div class="flex items-center justify-center gap-2.5 sm:gap-3" :class="{ 'opacity-60 pointer-events-none': disabled }">
    <input
      v-for="(_, index) in length"
      :key="index"
      :ref="(el) => setInputRef(el, index)"
      :type="mask ? 'password' : 'text'"
      inputmode="numeric"
      pattern="[0-9]*"
      maxlength="1"
      :disabled="disabled"
      :value="digits[index]"
      @input="handleInput($event, index)"
      @keydown="handleKeyDown($event, index)"
      @paste="handlePaste"
      @focus="handleFocus(index)"
      :aria-label="`${ariaLabel || 'PIN digit'} ${index + 1} of ${length}`"
      class="w-12 h-13 sm:w-14 sm:h-14 text-center text-lg sm:text-xl font-bold font-mono rounded-xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-blue-500 focus:ring-2 focus:ring-blue-500/20 transition-all select-none shadow-xs"
      :class="[
        hasError ? 'border-rose-400 dark:border-rose-500/70 bg-rose-50/20' : '',
        digits[index] ? 'border-blue-500/60 dark:border-blue-500/50' : ''
      ]"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, nextTick, onMounted } from 'vue';

const props = withDefaults(
  defineProps<{
    modelValue?: string;
    length?: number;
    mask?: boolean;
    disabled?: boolean;
    autofocus?: boolean;
    hasError?: boolean;
    ariaLabel?: string;
  }>(),
  {
    modelValue: '',
    length: 4,
    mask: true,
    disabled: false,
    autofocus: false,
    hasError: false,
    ariaLabel: 'Recovery PIN digit',
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', value: string): void;
  (e: 'complete', value: string): void;
}>();

const inputRefs = ref<(HTMLInputElement | null)[]>([]);
const digits = ref<string[]>(Array(props.length).fill(''));

const setInputRef = (el: any, index: number) => {
  inputRefs.value[index] = el;
};

// Sync from modelValue
watch(
  () => props.modelValue,
  (val) => {
    const raw = String(val || '');
    const arr = Array(props.length).fill('');
    for (let i = 0; i < props.length; i++) {
      arr[i] = raw[i] && /\d/.test(raw[i]) ? raw[i] : '';
    }
    digits.value = arr;
  },
  { immediate: true }
);

const updateValue = () => {
  const pin = digits.value.join('');
  emit('update:modelValue', pin);
  if (pin.length === props.length && !digits.value.includes('')) {
    emit('complete', pin);
  }
};

const handleInput = (e: Event, index: number) => {
  const target = e.target as HTMLInputElement;
  const val = target.value;

  // Take only the last numeric character if multiple typed
  const digit = val.replace(/\D/g, '').slice(-1);
  digits.value[index] = digit;
  target.value = digit;
  updateValue();

  if (digit && index < props.length - 1) {
    nextTick(() => {
      inputRefs.value[index + 1]?.focus();
      inputRefs.value[index + 1]?.select();
    });
  }
};

const handleKeyDown = (e: KeyboardEvent, index: number) => {
  if (e.key === 'Backspace') {
    if (!digits.value[index] && index > 0) {
      // Focus and clear previous box
      digits.value[index - 1] = '';
      updateValue();
      nextTick(() => {
        inputRefs.value[index - 1]?.focus();
      });
    } else {
      digits.value[index] = '';
      updateValue();
    }
  } else if (e.key === 'ArrowLeft' && index > 0) {
    e.preventDefault();
    inputRefs.value[index - 1]?.focus();
  } else if (e.key === 'ArrowRight' && index < props.length - 1) {
    e.preventDefault();
    inputRefs.value[index + 1]?.focus();
  }
};

const handlePaste = (e: ClipboardEvent) => {
  e.preventDefault();
  const pasted = e.clipboardData?.getData('text') || '';
  const clean = pasted.replace(/\D/g, '').slice(0, props.length);
  if (!clean) return;

  const arr = Array(props.length).fill('');
  for (let i = 0; i < clean.length; i++) {
    arr[i] = clean[i];
  }
  digits.value = arr;
  updateValue();

  const nextFocusIdx = Math.min(clean.length, props.length - 1);
  nextTick(() => {
    inputRefs.value[nextFocusIdx]?.focus();
  });
};

const handleFocus = (index: number) => {
  inputRefs.value[index]?.select();
};

onMounted(() => {
  if (props.autofocus && inputRefs.value[0]) {
    nextTick(() => {
      inputRefs.value[0]?.focus();
    });
  }
});
</script>
