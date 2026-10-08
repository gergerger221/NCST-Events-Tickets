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
      return 'bg-emerald-50 text-emerald-700 border-emerald-200 dark:bg-emerald-500/15 dark:text-emerald-400 dark:border-emerald-500/30';
    case 'danger':
      return 'bg-rose-50 text-rose-700 border-rose-200 dark:bg-rose-500/15 dark:text-rose-400 dark:border-rose-500/30';
    case 'warning':
      return 'bg-amber-50 text-amber-700 border-amber-200 dark:bg-amber-500/15 dark:text-amber-400 dark:border-amber-500/30';
    case 'info':
      return 'bg-blue-50 text-blue-700 border-blue-200 dark:bg-blue-500/15 dark:text-blue-400 dark:border-blue-500/30';
    case 'purple':
      return 'bg-indigo-50 text-indigo-700 border-indigo-200 dark:bg-indigo-500/15 dark:text-indigo-400 dark:border-indigo-500/30';
    case 'neutral':
    default:
      return 'bg-slate-100 text-slate-700 border-slate-200 dark:bg-slate-800/60 dark:text-slate-300 dark:border-slate-700/60';
  }
});

const dotClass = computed(() => {
  switch (props.variant) {
    case 'success':
      return 'bg-emerald-500 dark:bg-emerald-400';
    case 'danger':
      return 'bg-rose-500 dark:bg-rose-400';
    case 'warning':
      return 'bg-amber-500 dark:bg-amber-400';
    case 'info':
      return 'bg-blue-500 dark:bg-blue-400';
    case 'purple':
      return 'bg-indigo-500 dark:bg-indigo-400';
    default:
      return 'bg-slate-500 dark:bg-slate-400';
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
