import { ref } from 'vue';

export type ToastType = 'success' | 'error' | 'warning' | 'info';

export interface ToastItem {
  id: string;
  type: ToastType;
  title?: string;
  message: string;
  duration?: number;
  createdAt: number;
}

const toasts = ref<ToastItem[]>([]);

export function useToast() {
  const addToast = (type: ToastType, message: string, title?: string, duration = 4000): string => {
    const id = `toast-${Date.now()}-${Math.random().toString(36).substring(2, 6)}`;
    const item: ToastItem = {
      id,
      type,
      title,
      message,
      duration,
      createdAt: Date.now(),
    };

    // Keep max 5 active toasts to prevent viewport clutter
    if (toasts.value.length >= 5) {
      toasts.value.pop();
    }

    toasts.value.unshift(item);

    if (duration > 0) {
      setTimeout(() => {
        removeToast(id);
      }, duration);
    }

    return id;
  };

  const removeToast = (id: string) => {
    toasts.value = toasts.value.filter(t => t.id !== id);
  };

  const clearAllToasts = () => {
    toasts.value = [];
  };

  const success = (message: string, title?: string, duration?: number) =>
    addToast('success', message, title || 'Success', duration);

  const error = (message: string, title?: string, duration?: number) =>
    addToast('error', message, title || 'Error', duration);

  const warning = (message: string, title?: string, duration?: number) =>
    addToast('warning', message, title || 'Notice', duration);

  const info = (message: string, title?: string, duration?: number) =>
    addToast('info', message, title || 'Information', duration);

  return {
    toasts,
    addToast,
    removeToast,
    clearAllToasts,
    success,
    error,
    warning,
    info,
  };
}
