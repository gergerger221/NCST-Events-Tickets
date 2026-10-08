<template>
  <Teleport to="body">
    <div
      v-if="isOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 sm:p-6 overflow-y-auto modal-overlay animate-in fade-in duration-200"
    >
      <!-- Backdrop -->
      <div
        class="fixed inset-0 bg-slate-900/60 dark:bg-black/80 backdrop-blur-sm transition-opacity"
        @click="$emit('cancel')"
      />

      <!-- Modal Container -->
      <div
        class="relative w-full max-w-md bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-2xl overflow-hidden z-10 transform transition-all p-6 text-left"
        @click.stop
      >
        <div class="flex items-start gap-4">
          <!-- Icon Badge -->
          <div
            class="w-12 h-12 rounded-2xl flex items-center justify-center flex-shrink-0"
            :class="variantClasses.badge"
          >
            <component :is="activeIcon" class="w-6 h-6" :class="variantClasses.icon" />
          </div>

          <!-- Content -->
          <div class="flex-1 min-w-0">
            <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white tracking-tight">
              {{ title }}
            </h3>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-[#b3b3b3] mt-1.5 leading-relaxed">
              {{ message }}
            </p>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="mt-6 pt-4 border-t border-slate-100 dark:border-[#2c2c2c] flex items-center justify-end gap-3">
          <button
            type="button"
            @click="$emit('cancel')"
            class="px-4 py-2.5 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] transition cursor-pointer"
          >
            {{ cancelText }}
          </button>
          <button
            type="button"
            @click="$emit('confirm')"
            class="px-5 py-2.5 rounded-xl text-xs font-extrabold text-white shadow-lg transition flex items-center gap-2 cursor-pointer transform hover:scale-[1.02] active:scale-[0.98]"
            :class="variantClasses.button"
          >
            <span>{{ confirmText }}</span>
          </button>
        </div>
      </div>
    </div>
  </Teleport>
</template>

<script setup lang="ts">
import { computed, type Component } from 'vue';
import { AlertTriangle, RotateCcw, Trash2, Info, AlertCircle } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    isOpen: boolean;
    title: string;
    message: string;
    confirmText?: string;
    cancelText?: string;
    variant?: 'danger' | 'warning' | 'info' | 'primary';
    icon?: Component;
  }>(),
  {
    confirmText: 'Confirm',
    cancelText: 'Cancel',
    variant: 'danger',
  }
);

defineEmits<{
  (e: 'confirm'): void;
  (e: 'cancel'): void;
}>();

const activeIcon = computed(() => {
  if (props.icon) return props.icon;
  switch (props.variant) {
    case 'warning': return AlertTriangle;
    case 'info': return Info;
    case 'primary': return RotateCcw;
    case 'danger':
    default:
      return AlertCircle;
  }
});

const variantClasses = computed(() => {
  switch (props.variant) {
    case 'warning':
      return {
        badge: 'bg-amber-100 dark:bg-amber-500/20 text-amber-600 dark:text-amber-400 border border-amber-200 dark:border-amber-500/30',
        icon: 'text-amber-600 dark:text-amber-400',
        button: 'bg-amber-600 hover:bg-amber-700 shadow-amber-600/25',
      };
    case 'info':
    case 'primary':
      return {
        badge: 'bg-blue-100 dark:bg-blue-500/20 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-500/30',
        icon: 'text-blue-600 dark:text-blue-400',
        button: 'bg-blue-600 hover:bg-blue-700 shadow-blue-600/25',
      };
    case 'danger':
    default:
      return {
        badge: 'bg-rose-100 dark:bg-rose-500/20 text-rose-600 dark:text-rose-400 border border-rose-200 dark:border-rose-500/30',
        icon: 'text-rose-600 dark:text-rose-400',
        button: 'bg-rose-600 hover:bg-rose-700 shadow-rose-600/25',
      };
  }
});
</script>
