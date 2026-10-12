<template>
  <span
    class="inline-flex items-center gap-1.5 font-semibold rounded-full border transition-colors"
    :class="[variantClass, sizeClass, customClass]"
  >
    <span v-if="dot" class="w-1.5 h-1.5 rounded-full" :class="dotClass" />
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    variant?: 'success' | 'danger' | 'warning' | 'info' | 'purple' | 'neutral';
    size?: 'sm' | 'md' | 'lg';
    dot?: boolean;
    customClass?: string;
  }>(),
  {
    variant: 'neutral',
    size: 'md',
    dot: false,
    customClass: '',
  }
);

const variantClass = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-50 text-[#15803D] border-emerald-200 dark:bg-emerald-950/40 dark:text-emerald-400 dark:border-emerald-800/50';
    case 'danger':
      return 'bg-red-50 text-[#B91C1C] border-red-200 dark:bg-red-950/40 dark:text-red-400 dark:border-red-800/50';
    case 'warning':
      return 'bg-amber-50 text-[#B45309] border-amber-200 dark:bg-amber-950/40 dark:text-amber-400 dark:border-amber-800/50';
    case 'info':
      return 'bg-slate-100 text-[#1E3A5F] border-slate-200 dark:bg-slate-800/60 dark:text-blue-400 dark:border-slate-700/60';
    case 'purple':
    case 'neutral':
    default:
      return 'bg-slate-100 text-[#475569] border-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-700/60';
  }
});

const dotClass = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-[#15803D] dark:bg-emerald-400';
    case 'danger':
      return 'bg-[#B91C1C] dark:bg-red-400';
    case 'warning':
      return 'bg-[#B45309] dark:bg-amber-400';
    case 'info':
      return 'bg-[#1E3A5F] dark:bg-blue-400';
    case 'purple':
    default:
      return 'bg-[#64748B] dark:bg-slate-400';
  }
});

const sizeClass = computed(() => {
  switch (props.size) {
    case 'sm':
      return 'text-xs px-2 py-0.5';
    case 'lg':
      return 'text-sm px-3 py-1.5';
    case 'md':
    default:
      return 'text-xs px-2.5 py-1';
  }
});
</script>
