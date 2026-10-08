<template>
  <div class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-md space-y-4 transition-colors">
    <!-- Header with Month Controls -->
    <div class="flex items-center justify-between border-b border-slate-200 dark:border-[#333333] pb-4">
      <div>
        <div class="flex items-center gap-2">
          <Calendar class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          <h3 class="text-sm font-extrabold text-slate-900 dark:text-white">
            {{ title || 'Events Schedule' }}
          </h3>
        </div>
        <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3] mt-0.5">
          {{ fullDateString }} • <span class="font-mono font-bold text-slate-700 dark:text-slate-300">{{ timeString }}</span>
        </p>
      </div>

      <!-- Month Switcher -->
      <div class="flex items-center gap-1 bg-slate-100 dark:bg-[#242424] p-1 rounded-xl border border-slate-200 dark:border-[#333333]">
        <button
          @click="prevMonth"
          class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg transition cursor-pointer"
          title="Previous Month"
        >
          <ChevronLeft class="w-3.5 h-3.5" />
        </button>
        <span class="px-2 text-xs font-bold text-slate-800 dark:text-slate-200 min-w-[90px] text-center">
          {{ monthNames[currentMonth].slice(0, 3) }} {{ currentYear }}
        </span>
        <button
          @click="nextMonth"
          class="p-1 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white rounded-lg transition cursor-pointer"
          title="Next Month"
        >
          <ChevronRight class="w-3.5 h-3.5" />
        </button>
      </div>
    </div>

    <!-- Mini Calendar Day Header -->
    <div class="grid grid-cols-7 gap-1 text-center">
      <div
        v-for="d in ['Su', 'Mo', 'Tu', 'We', 'Th', 'Fr', 'Sa']"
        :key="d"
        class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 py-1"
      >
        {{ d }}
      </div>
    </div>

    <!-- Mini Calendar Grid Cells -->
    <div class="grid grid-cols-7 gap-1">
      <button
        v-for="(cell, idx) in miniCalendarCells"
        :key="idx"
        type="button"
        @click="selectedDateStr = cell.dateStr"
        class="h-8 rounded-xl flex flex-col items-center justify-center relative transition-all duration-150 cursor-pointer text-xs font-bold"
        :class="[
          !cell.isCurrentMonth
            ? 'text-slate-300 dark:text-slate-700 opacity-40 hover:opacity-80'
            : selectedDateStr === cell.dateStr
            ? 'bg-blue-600 text-white shadow-sm font-black scale-105 z-10'
            : cell.isToday
            ? 'bg-amber-100 text-amber-900 dark:bg-amber-950/60 dark:text-amber-300 border border-amber-300 dark:border-amber-600'
            : cell.hasEvents
            ? 'bg-blue-50/80 dark:bg-blue-950/30 text-blue-700 dark:text-blue-300 hover:bg-blue-100'
            : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828]'
        ]"
      >
        <span>{{ cell.dayNumber }}</span>

        <!-- Event Indicator Dot -->
        <span
          v-if="cell.hasEvents && selectedDateStr !== cell.dateStr"
          class="w-1 h-1 rounded-full bg-blue-600 dark:bg-blue-400 absolute bottom-1"
        />
      </button>
    </div>

    <!-- Selected Date / Upcoming Events Preview -->
    <div class="pt-3 border-t border-slate-200 dark:border-[#333333] space-y-2.5">
      <div class="flex items-center justify-between text-xs">
        <span class="font-extrabold text-slate-800 dark:text-slate-200 flex items-center gap-1.5">
          <Clock class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
          <span>{{ formatPhilippineDate(selectedDateStr) }}</span>
        </span>
        <span
          v-if="isTodayPHT(selectedDateStr)"
          class="text-[9px] font-bold uppercase px-1.5 py-0.2 bg-amber-500 text-white rounded font-mono"
        >
          TODAY
        </span>
      </div>

      <!-- Events on Selected Date -->
      <div v-if="selectedDayEvents.length > 0" class="space-y-2">
        <div
          v-for="ev in selectedDayEvents"
          :key="ev.id"
          class="p-2.5 rounded-2xl bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] flex items-center justify-between gap-3 text-xs hover:border-blue-500/50 transition shadow-2xs"
        >
          <div class="flex items-center gap-2.5 min-w-0 flex-1">
            <EventCoverImage
              :src="ev.bannerUrl"
              :alt="`${ev.title} cover`"
              :title="ev.title"
              :category="ev.category"
              :code="ev.code"
              size="sm"
              rounded="xl"
            />
            <div class="min-w-0 flex-1">
              <p class="font-extrabold text-slate-900 dark:text-white truncate">
                {{ ev.title }}
              </p>
              <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3] truncate flex items-center gap-1">
                <span>{{ ev.startTime }} - {{ ev.endTime }}</span> • <span>{{ ev.venue }}</span>
              </p>
            </div>
          </div>

          <button
            @click="handleEventClick(ev)"
            class="px-2.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white text-[10px] font-bold rounded-lg shadow-2xs transition shrink-0 cursor-pointer"
          >
            {{ currentEvent?.id === ev.id ? 'Active' : 'Select' }}
          </button>
        </div>
      </div>

      <!-- No events on selected date -->
      <div v-else class="text-center py-3 text-slate-400 dark:text-slate-500 text-xs">
        No campus activities on this date.
      </div>
    </div>

    <!-- Bottom Full Calendar Button -->
    <button
      @click="$emit('view-full-calendar')"
      class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#242424] dark:hover:bg-[#2c2c2c] text-slate-800 dark:text-slate-200 rounded-2xl text-xs font-bold border border-slate-200 dark:border-[#333333] transition flex items-center justify-center gap-2 cursor-pointer"
    >
      <Calendar class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
      <span>Open Full Calendar</span>
    </button>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Calendar, Clock, ChevronLeft, ChevronRight } from 'lucide-vue-next';
import EventCoverImage from '../common/EventCoverImage.vue';
import { useAppStore } from '../../composables/useAppStore';
import {
  usePhilippineClock,
  getPhilippineParts,
  formatPhilippineDate,
  isTodayPHT
} from '../../utils/philippineTime';
import { EventItem } from '../../types';

withDefaults(
  defineProps<{
    title?: string;
  }>(),
  {
    title: 'Philippine Events Schedule',
  }
);

const emit = defineEmits<{
  (e: 'view-full-calendar'): void;
  (e: 'select-event', event: EventItem): void;
}>();

const { events, currentEvent, setCurrentEvent } = useAppStore();
const { timeString, fullDateString, todayIso } = usePhilippineClock();

const nowParts = getPhilippineParts(new Date());
const currentYear = ref(nowParts.year);
const currentMonth = ref(nowParts.month);
const selectedDateStr = ref(todayIso.value);

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value--;
  } else {
    currentMonth.value--;
  }
};

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value++;
  } else {
    currentMonth.value++;
  }
};

interface MiniCell {
  dateStr: string;
  dayNumber: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  hasEvents: boolean;
}

const miniCalendarCells = computed<MiniCell[]>(() => {
  const year = currentYear.value;
  const month = currentMonth.value;

  const firstDay = new Date(year, month, 1);
  const startingDayOfWeek = firstDay.getDay();

  const daysInMonth = new Date(year, month + 1, 0).getDate();
  const daysInPrevMonth = new Date(year, month, 0).getDate();

  const cells: MiniCell[] = [];

  // Trailing previous month
  for (let i = startingDayOfWeek - 1; i >= 0; i--) {
    const day = daysInPrevMonth - i;
    const prevMonthIdx = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const mStr = String(prevMonthIdx + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${prevYear}-${mStr}-${dStr}`;

    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: false,
      isToday: isTodayPHT(dateStr),
      hasEvents: events.value.some(e => e.date === dateStr),
    });
  }

  // Current month
  for (let day = 1; day <= daysInMonth; day++) {
    const mStr = String(month + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${year}-${mStr}-${dStr}`;

    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: true,
      isToday: isTodayPHT(dateStr),
      hasEvents: events.value.some(e => e.date === dateStr),
    });
  }

  // Leading next month
  const remaining = (7 - (cells.length % 7)) % 7;
  for (let day = 1; day <= remaining; day++) {
    const nextMonthIdx = month === 11 ? 0 : month + 1;
    const nextYear = month === 11 ? year + 1 : year;
    const mStr = String(nextMonthIdx + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${nextYear}-${mStr}-${dStr}`;

    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: false,
      isToday: isTodayPHT(dateStr),
      hasEvents: events.value.some(e => e.date === dateStr),
    });
  }

  return cells;
});

const selectedDayEvents = computed(() => {
  return events.value.filter(e => e.date === selectedDateStr.value);
});

const handleEventClick = (ev: EventItem) => {
  setCurrentEvent(ev);
  emit('select-event', ev);
};
</script>
