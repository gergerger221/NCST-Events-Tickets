<template>
  <Teleport to="body">
    <div v-if="isOpen" class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto modal-overlay">
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-slate-900/60 dark:bg-black/75 backdrop-blur-sm transition-opacity duration-300"
        @click="$emit('close')"
      />

      <!-- Modal Card -->
      <div
        class="relative w-full bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-2xl shadow-2xl overflow-hidden transform transition-all duration-300 z-10 my-8"
        :class="maxWidthClass"
        @click.stop
      >
        <!-- Header -->
        <div v-if="title || subtitle" class="no-print flex items-center justify-between px-6 py-4 border-b border-slate-200 dark:border-[#333333] bg-slate-50/80 dark:bg-[#1e1e1e]/90 modal-header">
          <div>
            <h3 v-if="title" class="text-lg font-bold text-slate-900 dark:text-white tracking-tight">{{ title }}</h3>
            <p v-if="subtitle" class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-0.5">{{ subtitle }}</p>
          </div>
          <button
            @click="$emit('close')"
            class="p-1.5 text-slate-400 hover:text-slate-700 dark:text-[#b3b3b3] dark:hover:text-white hover:bg-slate-200 dark:hover:bg-[#2c2c2c] rounded-lg transition-colors cursor-pointer"
          >
            <X class="w-5 h-5" />
          </button>
        </div>

        <!-- Body -->
        <div class="p-6 max-h-[calc(85vh-80px)] overflow-y-auto text-slate-900 dark:text-white">
          <slot />
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, onMounted, onUnmounted, watch } from 'vue';
import { X } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    title?: string;
    subtitle?: string;
    maxWidth?: 'sm' | 'md' | 'lg' | 'xl' | '2xl' | '3xl' | '4xl' | '5xl';
  }>(),
  {
    maxWidth: 'lg',
  }
);

defineEmits<{
  (e: 'close'): void;
}>();

const maxWidthClass = computed(() => {
  switch (props.maxWidth) {
    case 'sm': return 'max-w-sm';
    case 'md': return 'max-w-md';
    case 'xl': return 'max-w-xl';
    case '2xl': return 'max-w-2xl';
    case '3xl': return 'max-w-3xl';
    case '4xl': return 'max-w-4xl';
    case '5xl': return 'max-w-5xl';
    case 'lg':
    default:
      return 'max-w-lg';
  }
});

const handleKeyDown = (e: KeyboardEvent) => {
  if (e.key === 'Escape' && props.isOpen) {
    // Esc handled
  }
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      document.body.style.overflow = 'hidden';
    } else {
      document.body.style.overflow = '';
    }
  },
  { immediate: true }
);

onMounted(() => window.addEventListener('keydown', handleKeyDown));
onUnmounted(() => {
  document.body.style.overflow = '';
  window.removeEventListener('keydown', handleKeyDown);
});
</script>
