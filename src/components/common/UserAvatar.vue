<template>
  <div
    :class="[
      'flex items-center justify-center flex-shrink-0 transition-colors',
      roundedClass,
      bgClass,
      sizeClass
    ]"
  >
    <img
      v-if="src"
      :src="src"
      :alt="alt || 'User Avatar'"
      class="w-full h-full object-cover"
      :class="roundedClass"
    />
    <svg
      v-else
      viewBox="0 0 24 24"
      fill="none"
      stroke="currentColor"
      stroke-width="2.2"
      stroke-linecap="round"
      stroke-linejoin="round"
      class="text-slate-400 dark:text-[#b3b3b3] transition-colors"
      :class="iconSizeClass"
    >
      <circle cx="12" cy="7" r="4" />
      <path d="M5.5 21a6.5 6.5 0 0 1 13 0" />
    </svg>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue';

const props = withDefaults(
  defineProps<{
    src?: string;
    alt?: string;
    size?: 'xs' | 'sm' | 'md' | 'lg' | 'xl';
    shape?: 'rounded' | 'circle' | 'square';
  }>(),
  {
    size: 'md',
    shape: 'rounded',
  }
);

const sizeClass = computed(() => {
  switch (props.size) {
    case 'xs': return 'w-7 h-7';
    case 'sm': return 'w-8 h-8';
    case 'md': return 'w-10 h-10';
    case 'lg': return 'w-12 h-12 sm:w-14 sm:h-14';
    case 'xl': return 'w-16 h-16';
    default: return 'w-10 h-10';
  }
});

const iconSizeClass = computed(() => {
  switch (props.size) {
    case 'xs': return 'w-4 h-4';
    case 'sm': return 'w-4 h-4';
    case 'md': return 'w-5 h-5';
    case 'lg': return 'w-7 h-7';
    case 'xl': return 'w-8 h-8';
    default: return 'w-5 h-5';
  }
});

const roundedClass = computed(() => {
  switch (props.shape) {
    case 'circle': return 'rounded-full';
    case 'square': return 'rounded-lg';
    case 'rounded':
    default:
      if (props.size === 'xs' || props.size === 'sm') return 'rounded-xl';
      if (props.size === 'xl' || props.size === 'lg') return 'rounded-2xl';
      return 'rounded-xl';
  }
});

const bgClass = computed(() => {
  return 'bg-slate-200/80 dark:bg-[#2c2c2c] border border-slate-300/80 dark:border-[#3a3a3a]';
});
</script>
