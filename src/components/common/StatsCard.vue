<template>
  <div
    @click="$emit('click')"
    class="group relative overflow-hidden rounded-2xl bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] p-5 transition-all duration-200 hover:border-blue-500/50 hover:-translate-y-0.5 cursor-pointer shadow-sm dark:shadow-md"
  >
    <div class="flex items-start justify-between">
      <div>
        <p class="text-xs font-bold uppercase tracking-wider text-slate-500 dark:text-[#b3b3b3]">{{ title }}</p>
        <div class="mt-2 flex items-baseline gap-2">
          <span class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">{{ value }}</span>
          <span
            v-if="trend"
            class="text-xs font-semibold"
            :class="trend.isPositive ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600 dark:text-rose-400'"
          >
            {{ trend.value }}
          </span>
        </div>
        <p v-if="subtitle" class="mt-1 text-xs text-slate-500 dark:text-[#b3b3b3]">{{ subtitle }}</p>
      </div>
      <div class="p-3 rounded-xl transition-colors" :class="scheme.iconBg">
        <component :is="icon" class="w-6 h-6" />
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    title: string;
    value: string | number;
    subtitle?: string;
    icon: any;
    color?: 'indigo' | 'emerald' | 'amber' | 'rose' | 'cyan' | 'purple';
    trend?: {
      value: string;
      isPositive: boolean;
    };
  }>(),
  {
    color: 'indigo',
  }
);

defineEmits<{
  (e: 'click'): void;
}>();

const colorMap = {
  indigo: {
    iconBg: 'bg-blue-50 dark:bg-blue-950/40 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30',
  },
  emerald: {
    iconBg: 'bg-emerald-50 dark:bg-emerald-950/40 text-emerald-600 dark:text-emerald-400 border border-emerald-200 dark:border-emerald-500/30',
  },
  amber: {
    iconBg: 'bg-amber-50 dark:bg-amber-950/40 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30',
  },
  rose: {
    iconBg: 'bg-rose-50 dark:bg-rose-950/40 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30',
  },
  cyan: {
    iconBg: 'bg-cyan-50 dark:bg-cyan-950/40 text-cyan-600 dark:text-cyan-400 border border-cyan-200 dark:border-cyan-500/30',
  },
  purple: {
    iconBg: 'bg-purple-50 dark:bg-purple-950/40 text-purple-600 dark:text-purple-400 border border-purple-200 dark:border-purple-500/30',
  },
};

const scheme = computed(() => colorMap[props.color]);
</script>
