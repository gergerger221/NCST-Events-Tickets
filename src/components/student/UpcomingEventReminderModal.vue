<template>
  <div
    v-if="isOpen && targetEvent"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/40 backdrop-blur-xs transition-opacity duration-200"
    @click.self="handleClose"
  >
    <div
      class="bg-white dark:bg-[#1e1e1e] border border-slate-200/80 dark:border-slate-800 rounded-3xl max-w-sm w-full p-5 sm:p-6 shadow-xl text-center space-y-3.5 transform transition-all animate-in fade-in zoom-in-95 duration-200"
    >
      <!-- Icon & Label -->
      <div class="flex flex-col items-center gap-1.5">
        <div class="w-10 h-10 rounded-2xl bg-blue-50 dark:bg-blue-900/30 border border-blue-100 dark:border-blue-800/40 flex items-center justify-center text-blue-600 dark:text-blue-400 shadow-xs">
          <Calendar class="w-5 h-5" />
        </div>
        <span class="text-[10px] font-extrabold uppercase tracking-widest text-blue-600 dark:text-blue-400">
          Upcoming Event
        </span>
      </div>

      <!-- Title & Relative Badge -->
      <div class="space-y-1">
        <h3 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight leading-snug">
          {{ targetEvent.title }}
        </h3>
        <div class="inline-block px-2.5 py-0.5 rounded-full bg-blue-50 dark:bg-blue-950/50 border border-blue-200/60 dark:border-blue-800/40 text-blue-700 dark:text-blue-300 font-bold text-xs">
          {{ countdownText }}
        </div>
      </div>

      <!-- Date & Schedule (Philippine Time) -->
      <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
        {{ formatPhilippineDate(targetEvent.date) }} · {{ targetEvent.startTime }} - {{ targetEvent.endTime }}
      </p>

      <!-- Actions -->
      <div class="flex items-center gap-2 pt-1.5">
        <button
          type="button"
          @click="handleViewEvent"
          class="flex-1 py-2 px-4 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-xl shadow-xs transition cursor-pointer"
        >
          View Event
        </button>
        <button
          type="button"
          @click="handleClose"
          class="py-2 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#383838] text-slate-700 dark:text-slate-200 font-bold text-xs rounded-xl transition cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue';
import { Calendar } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem } from '../../types';
import { formatPhilippineDate, getPhilippineDateString } from '../../utils/philippineTime';

const emit = defineEmits<{
  (e: 'view-event', event: EventItem): void;
}>();

const { events, currentUser, tickets, setCurrentEvent } = useAppStore();

const isOpen = ref(false);
const targetEvent = ref<EventItem | null>(null);

const countdownText = computed(() => {
  if (!targetEvent.value) return 'Upcoming';
  const todayStr = getPhilippineDateString(new Date());
  const eventDateStr = targetEvent.value.date;

  if (eventDateStr === todayStr) {
    return 'Happening Today';
  }

  const [ty, tm, td] = todayStr.split('-').map(Number);
  const [ey, em, ed] = eventDateStr.split('-').map(Number);
  const todayUtc = Date.UTC(ty, tm - 1, td);
  const eventUtc = Date.UTC(ey, em - 1, ed);
  const diffDays = Math.round((eventUtc - todayUtc) / (1000 * 60 * 60 * 24));

  if (diffDays === 1) return 'Coming up tomorrow';
  if (diffDays > 1) return `Coming up in ${diffDays} days`;
  if (diffDays === 0) return 'Happening Today';
  return 'Upcoming';
});

const checkUpcomingReminder = () => {
  if (!currentUser.value || currentUser.value.role !== 'student') {
    isOpen.value = false;
    return;
  }

  const todayStr = getPhilippineDateString(new Date());
  const [ty, tm, td] = todayStr.split('-').map(Number);
  const todayUtc = Date.UTC(ty, tm - 1, td);

  // Exclude cancelled and completed events
  const validUpcomingEvents = events.value.filter(e => {
    if (e.status === 'cancelled' || e.status === 'completed') return false;
    if (!e.date) return false;
    const [ey, em, ed] = e.date.split('-').map(Number);
    const eventUtc = Date.UTC(ey, em - 1, ed);
    const diffDays = Math.round((eventUtc - todayUtc) / (1000 * 60 * 60 * 24));
    // Check if event is within upcoming 1 to 7 days or today
    return diffDays >= 0 && diffDays <= 7;
  });

  if (validUpcomingEvents.length === 0) return;

  // Find nearest upcoming event
  const sorted = [...validUpcomingEvents].sort((a, b) => {
    return new Date(a.date).getTime() - new Date(b.date).getTime();
  });

  const nextEvent = sorted[0];
  if (!nextEvent) return;

  // Check if reminder was already dismissed in this session
  const storageKey = `ncst_dismissed_reminder_${currentUser.value.id || 'student'}_${nextEvent.id}_${nextEvent.date}`;
  if (sessionStorage.getItem(storageKey) === 'true') {
    return;
  }

  targetEvent.value = nextEvent;
  isOpen.value = true;
};

const handleClose = () => {
  if (targetEvent.value && currentUser.value) {
    const storageKey = `ncst_dismissed_reminder_${currentUser.value.id || 'student'}_${targetEvent.value.id}_${targetEvent.value.date}`;
    try {
      sessionStorage.setItem(storageKey, 'true');
    } catch {}
  }
  isOpen.value = false;
};

const handleViewEvent = () => {
  if (targetEvent.value) {
    const ev = targetEvent.value;
    handleClose();
    setCurrentEvent(ev);
    emit('view-event', ev);
  }
};

onMounted(() => {
  // Small delay to allow store hydration
  setTimeout(() => {
    checkUpcomingReminder();
  }, 400);
});
</script>
