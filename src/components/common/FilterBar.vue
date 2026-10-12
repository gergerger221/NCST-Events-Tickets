<template>
  <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-2xl p-4 sm:p-5 mb-6 shadow-xs transition-colors space-y-3.5">
    <!-- ==================== 1. TOP ROW: SEARCH & EVENT ==================== -->
    <div class="flex flex-col sm:flex-row gap-2.5 items-stretch sm:items-center justify-between">
      <!-- Search Input -->
      <div class="relative flex-1">
        <Search class="absolute left-3.5 top-1/2 -translate-y-1/2 w-4 h-4 text-slate-400 dark:text-slate-500" />
        <input
          type="text"
          placeholder="Search Ticket, Name, or Student ID..."
          v-model="filters.searchQuery"
          class="w-full bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl pl-10 pr-4 py-2 text-xs sm:text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-slate-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
        />
      </div>

      <!-- Event Dropdown -->
      <div v-if="showEventSelector" class="flex items-center gap-2">
        <select
          v-model="filters.eventId"
          @change="onEventChange"
          class="bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl px-3 py-2 text-xs sm:text-sm text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-bold max-w-[200px] truncate"
        >
          <option value="all">All Events ({{ events.length }})</option>
          <option v-for="ev in events" :key="ev.id" :value="ev.id">
            {{ ev.title }} ({{ ev.code }})
          </option>
        </select>
      </div>

      <!-- Reset Button -->
      <button
        type="button"
        @click="resetFilters"
        class="flex items-center justify-center gap-1.5 px-3 py-2 text-xs font-bold text-slate-600 dark:text-[#a0a0a0] hover:text-slate-900 dark:hover:text-white bg-slate-100 dark:bg-[#262626] hover:bg-slate-200 dark:hover:bg-[#303030] rounded-xl border border-slate-200 dark:border-[#383838] transition cursor-pointer whitespace-nowrap"
        title="Reset all filter options"
      >
        <RotateCcw class="w-3.5 h-3.5" />
        <span>Reset</span>
      </button>
    </div>

    <!-- ==================== 2. PRIMARY FILTERS ROW ==================== -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-2.5 pt-1">
      <!-- 1. Type (Students / Outsiders) -->
      <div>
        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-[#a0a0a0] mb-1">
          Type
        </label>
        <select
          v-model="filters.attendeeType"
          class="w-full bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-semibold"
        >
          <option value="all">All Types</option>
          <option value="STUDENT">Students</option>
          <option value="OUTSIDER">Outsiders</option>
        </select>
      </div>

      <!-- 2. Ticket Status -->
      <div>
        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-[#a0a0a0] mb-1">
          Ticket Status
        </label>
        <select
          v-model="filters.ticketStatus"
          class="w-full bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-semibold"
        >
          <option value="all">All Statuses</option>
          <option value="issued">Issued</option>
          <option value="used">Used</option>
          <option value="void">Void</option>
        </select>
      </div>

      <!-- 3. More Filters Toggle Button -->
      <div>
        <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-[#a0a0a0] mb-1 opacity-0 pointer-events-none">
          More
        </label>
        <button
          type="button"
          @click="isMoreFiltersOpen = !isMoreFiltersOpen"
          class="w-full flex items-center justify-between px-3 py-2 text-xs font-bold rounded-xl border transition cursor-pointer"
          :class="isMoreFiltersOpen || activeMoreFiltersCount > 0
            ? 'bg-blue-50 dark:bg-blue-950/40 text-blue-700 dark:text-blue-300 border-blue-300 dark:border-blue-800'
            : 'bg-slate-50 dark:bg-[#222222] text-slate-700 dark:text-slate-300 border-slate-200 dark:border-[#333333] hover:border-slate-300'"
        >
          <div class="flex items-center gap-1.5 min-w-0">
            <SlidersHorizontal class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
            <span class="truncate">More Filters</span>
            <span
              v-if="activeMoreFiltersCount > 0"
              class="px-1.5 py-0.2 rounded-full bg-blue-600 text-white text-[10px] font-black"
            >
              {{ activeMoreFiltersCount }}
            </span>
          </div>
          <ChevronDown
            class="w-4 h-4 shrink-0 transition-transform duration-200"
            :class="{ 'rotate-180': isMoreFiltersOpen }"
          />
        </button>
      </div>
    </div>

    <!-- ==================== 3. COLLAPSIBLE "MORE FILTERS" PANEL ==================== -->
    <div
      v-if="isMoreFiltersOpen"
      class="pt-3.5 pb-2 px-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#2f2f2f] rounded-2xl space-y-3 transition-all"
    >
      <!-- Section 1: Student Details & Attendance & QR Print Status -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        <!-- Course Filter -->
        <ComboboxFilter
          v-model="filters.course"
          label="Course"
          placeholder="All Courses"
          :options="availableCourses"
          all-label="All Courses"
        />

        <!-- Year Level Filter -->
        <ComboboxFilter
          v-model="filters.yearLevel"
          label="Year Level"
          placeholder="All Years"
          :options="availableYearLevels"
          all-label="All Years"
        />

        <!-- Section Filter -->
        <ComboboxFilter
          v-model="filters.section"
          label="Section"
          placeholder="All Sections"
          :options="availableSections"
          all-label="All Sections"
        />

        <!-- Attendance Status Filter (Secondary) -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-[#a0a0a0] mb-1">
            Attendance
          </label>
          <select
            v-model="filters.attendanceStatus"
            class="w-full bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#383838] rounded-xl px-2.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-semibold"
          >
            <option value="all">All Attendance</option>
            <option value="present">Present</option>
            <option value="not_entered">Not Yet Entered</option>
          </select>
        </div>

        <!-- QR Label Print Status Filter (Secondary) -->
        <div>
          <label class="block text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-[#a0a0a0] mb-1">
            Print Status
          </label>
          <select
            v-model="filters.qrPrintStatus"
            class="w-full bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#383838] rounded-xl px-2.5 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 font-semibold"
          >
            <option value="all">All</option>
            <option value="PENDING">Pending</option>
            <option value="PRINTED">Printed</option>
          </select>
        </div>
      </div>
    </div>

    <!-- ==================== 4. SUMMARY & RECORD COUNTER ==================== -->
    <div class="flex items-center justify-between text-xs text-slate-500 dark:text-[#a0a0a0] pt-1">
      <div class="flex items-center gap-1.5">
        <Filter class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
        <span>
          Showing <strong class="text-slate-900 dark:text-white">{{ filteredTickets.length }}</strong> matching records
        </span>
      </div>
      <span
        v-if="hasActiveFilters"
        class="text-blue-700 dark:text-blue-300 font-bold bg-blue-50 dark:bg-blue-500/10 px-2.5 py-0.5 rounded-full text-[11px] border border-blue-200 dark:border-blue-500/20"
      >
        Filters Active
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Search, RotateCcw, Filter, SlidersHorizontal, ChevronDown } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import ComboboxFilter from './ComboboxFilter.vue';

withDefaults(
  defineProps<{
    showEventSelector?: boolean;
    compact?: boolean;
  }>(),
  {
    showEventSelector: true,
    compact: false,
  }
);

const {
  filters,
  resetFilters,
  events,
  setCurrentEvent,
  filteredTickets,
  users,
  tickets,
  studentRecords,
  availableSections: storeSections
} = useAppStore();

const isMoreFiltersOpen = ref(false);

const availableCourses = computed(() => {
  const defaults = ['BSIT', 'BSCS', 'BSCpE', 'BSBA', 'BSED', 'BSHM', 'BSCRIM', 'BSN', 'BSTM', 'Senior High'];
  const userCourses = users.value.map(u => u.course || u.department).filter(Boolean) as string[];
  const ticketCourses = tickets.value.map(t => t.course || t.attendeeDepartment).filter(Boolean) as string[];
  return Array.from(new Set([...defaults, ...userCourses, ...ticketCourses])).filter(c => c !== 'all');
});

const availableYearLevels = computed(() => {
  const defaults = ['1st Year', '2nd Year', '3rd Year', '4th Year', 'Grade 11', 'Grade 12'];
  const userYears = users.value.map(u => u.yearLevel).filter(Boolean) as string[];
  const ticketYears = tickets.value.map(t => t.yearLevel).filter(Boolean) as string[];
  return Array.from(new Set([...defaults, ...userYears, ...ticketYears])).filter(y => y !== 'all');
});

const availableSections = computed(() => {
  const sections = storeSections?.value || storeSections;
  if (Array.isArray(sections) && sections.length > 0) {
    return sections;
  }
  const userSections = users.value.map(u => u.section).filter(Boolean) as string[];
  const ticketSections = tickets.value.map(t => t.section).filter(Boolean) as string[];
  const studentSections = (studentRecords?.value || []).map(r => r.section).filter(Boolean) as string[];
  const allSystemSections = Array.from(new Set([...studentSections, ...userSections, ...ticketSections])).filter(s => s && s !== 'all');
  return allSystemSections.length > 0 ? allSystemSections : ['11M1', '11M2', '12M1', '21M1', '21M2', '31M1', '31A1', '31A2', '41M1', '41A1'];
});

const activeMoreFiltersCount = computed(() => {
  let count = 0;
  if (filters.value.course && filters.value.course !== 'all') count++;
  if (filters.value.yearLevel && filters.value.yearLevel !== 'all') count++;
  if (filters.value.section && filters.value.section !== 'all') count++;
  if (filters.value.qrPrintStatus && filters.value.qrPrintStatus !== 'all') count++;
  return count;
});

const hasActiveFilters = computed(() => {
  return (
    Boolean(filters.value.searchQuery) ||
    filters.value.attendeeType !== 'all' ||
    filters.value.attendanceStatus !== 'all' ||
    filters.value.ticketStatus !== 'all' ||
    activeMoreFiltersCount.value > 0
  );
});

const onEventChange = () => {
  if (filters.value.eventId !== 'all') {
    const ev = events.value.find(e => e.id === filters.value.eventId);
    if (ev) setCurrentEvent(ev);
  }
};
</script>

