<template>
  <div
    aria-live="polite"
    class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
  >
    <transition-group
      name="toast-slide"
      tag="div"
      class="flex flex-col gap-2.5 w-full"
    >
      <div
        v-for="toast in toasts"
        :key="toast.id"
        class="pointer-events-auto flex items-start gap-3 p-4 rounded-2xl shadow-xl border backdrop-blur-md transition-all duration-300"
        :class="getToastClasses(toast.type)"
        role="alert"
      >
        <!-- Icon -->
        <component
          :is="getToastIcon(toast.type)"
          class="w-5 h-5 shrink-0 mt-0.5"
          :class="getIconColor(toast.type)"
        />

        <!-- Content -->
        <div class="flex-1 min-w-0 pr-1">
          <h4
            v-if="toast.title"
            class="text-xs font-black tracking-tight mb-0.5"
            :class="getTitleColor(toast.type)"
          >
            {{ toast.title }}
          </h4>
          <p class="text-xs font-medium text-slate-700 dark:text-slate-200 leading-snug break-words">
            {{ toast.message }}
          </p>
        </div>

        <!-- Dismiss Button -->
        <button
          type="button"
          @click="removeToast(toast.id)"
          class="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 transition p-1 -mr-1 -mt-1 rounded-lg cursor-pointer"
          aria-label="Dismiss notification"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </transition-group>
  </div>
</template>

<script setup lang="ts">
import { useToast, ToastType } from '../../composables/useToast';
import { CheckCircle2, AlertCircle, AlertTriangle, Info, X } from 'lucide-vue-next';

const { toasts, removeToast } = useToast();

const getToastClasses = (type: ToastType): string => {
  switch (type) {
    case 'success':
      return 'bg-emerald-50/95 dark:bg-[#13231c]/95 border-emerald-300 dark:border-emerald-700/60 shadow-emerald-500/10 text-emerald-950 dark:text-emerald-100';
    case 'error':
      return 'bg-rose-50/95 dark:bg-[#271518]/95 border-rose-300 dark:border-rose-700/60 shadow-rose-500/10 text-rose-950 dark:text-rose-100';
    case 'warning':
      return 'bg-amber-50/95 dark:bg-[#281e13]/95 border-amber-300 dark:border-amber-700/60 shadow-amber-500/10 text-amber-950 dark:text-amber-100';
    case 'info':
    default:
      return 'bg-blue-50/95 dark:bg-[#14202e]/95 border-blue-300 dark:border-blue-700/60 shadow-blue-500/10 text-blue-950 dark:text-blue-100';
  }
};

const getToastIcon = (type: ToastType) => {
  switch (type) {
    case 'success':
      return CheckCircle2;
    case 'error':
      return AlertCircle;
    case 'warning':
      return AlertTriangle;
    case 'info':
    default:
      return Info;
  }
};

const getIconColor = (type: ToastType): string => {
  switch (type) {
    case 'success':
      return 'text-emerald-500 dark:text-emerald-400';
    case 'error':
      return 'text-rose-500 dark:text-rose-400';
    case 'warning':
      return 'text-amber-500 dark:text-amber-400';
    case 'info':
    default:
      return 'text-blue-500 dark:text-blue-400';
  }
};

const getTitleColor = (type: ToastType): string => {
  switch (type) {
    case 'success':
      return 'text-emerald-900 dark:text-emerald-300';
    case 'error':
      return 'text-rose-900 dark:text-rose-300';
    case 'warning':
      return 'text-amber-900 dark:text-amber-300';
    case 'info':
    default:
      return 'text-blue-900 dark:text-blue-300';
  }
};
</script>

<style scoped>
.toast-slide-enter-active,
.toast-slide-leave-active {
  transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
}

.toast-slide-enter-from {
  opacity: 0;
  transform: translateY(12px) scale(0.96);
}

.toast-slide-leave-to {
  opacity: 0;
  transform: translateX(20px) scale(0.95);
}
</style>
