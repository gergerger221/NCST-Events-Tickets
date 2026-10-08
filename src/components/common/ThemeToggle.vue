<template>
  <button
    @click="toggleTheme"
    type="button"
    class="relative flex items-center justify-center rounded-xl transition-all duration-200 cursor-pointer group focus:outline-none"
    :class="[
      variant === 'compact'
        ? 'p-2 text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-slate-200 hover:bg-slate-100 dark:hover:bg-slate-800 border border-slate-200 dark:border-slate-800'
        : variant === 'pill'
        ? 'px-3 py-1.5 gap-2 text-xs font-bold rounded-xl border border-slate-200 dark:border-slate-800 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800/80 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200'
        : 'p-2.5 text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white bg-slate-100 hover:bg-slate-200/80 dark:bg-slate-900 dark:hover:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-800 shadow-sm'
    ]"
    :title="isDark ? 'Switch to Light Mode (☀)' : 'Switch to Dark Mode (🌙)'"
    :aria-label="isDark ? 'Switch to Light Mode' : 'Switch to Dark Mode'"
  >
    <!-- Sun Icon (shown in dark mode to switch to light) -->
    <Sun
      v-if="isDark"
      class="w-4 h-4 text-amber-400 transition-transform duration-300 group-hover:rotate-45"
    />
    <!-- Moon Icon (shown in light mode to switch to dark) -->
    <Moon
      v-else
      class="w-4 h-4 text-indigo-600 transition-transform duration-300 group-hover:-rotate-12"
    />

    <span v-if="showLabel" class="text-xs font-semibold">
      {{ isDark ? 'Light Mode' : 'Dark Mode' }}
    </span>
  </button>
</template>

<script setup lang="ts">
import { Sun, Moon } from 'lucide-vue-next';
import { useTheme } from '../../composables/useTheme';

withDefaults(
  defineProps<{
    variant?: 'default' | 'compact' | 'pill';
    showLabel?: boolean;
  }>(),
  {
    variant: 'default',
    showLabel: false,
  }
);

const { isDark, toggleTheme } = useTheme();
</script>
