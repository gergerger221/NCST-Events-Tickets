<template>
  <div class="space-y-5 max-w-7xl mx-auto relative">
    <!-- Modern Floating Toast Notification -->
    <Teleport to="body">
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="toastNotification"
          class="fixed bottom-6 right-6 z-50 max-w-md w-full shadow-2xl rounded-2xl p-4 flex items-start gap-3 backdrop-blur-xl border pointer-events-auto"
          :class="toastNotification.type === 'success' ? 'bg-emerald-900/95 text-emerald-100 border-emerald-500/50 shadow-emerald-950/50' : 'bg-rose-900/95 text-rose-100 border-rose-500/50 shadow-rose-950/50'"
        >
          <CheckCircle2 v-if="toastNotification.type === 'success'" class="w-5 h-5 shrink-0 text-emerald-400 mt-0.5" />
          <AlertCircle v-else class="w-5 h-5 shrink-0 text-rose-400 mt-0.5" />
          <div class="flex-1 min-w-0">
            <h4 class="text-xs font-black uppercase tracking-wider text-white">
              {{ toastNotification.title }}
            </h4>
            <p class="text-xs opacity-90 mt-0.5 leading-relaxed font-medium">
              {{ toastNotification.message }}
            </p>
          </div>
          <button
            type="button"
            @click="toastNotification = null"
            class="text-white/60 hover:text-white text-sm p-1 rounded-lg hover:bg-white/10 transition cursor-pointer"
          >
            ✕
          </button>
        </div>
      </transition>
    </Teleport>

    <!-- Top Action Bar: Event Context & Quick Export Actions -->
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 pb-2 border-b border-slate-200 dark:border-[#333333]">
      <div class="flex items-center gap-3">
        <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
          <CalendarDays class="w-5 h-5" />
        </div>
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider">
            Academic Period & Event Monitoring
          </label>
          <div class="flex items-center gap-2 mt-0.5 flex-wrap">
            <select
              v-model="selectedPeriodId"
              class="px-2.5 py-1 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Academic Periods</option>
              <option v-for="p in academicPeriods" :key="p.id" :value="p.id">
                A.Y. {{ p.academicYear }} • {{ p.semester }} ({{ p.status }})
              </option>
            </select>
            <select
              :value="currentEvent?.id || ''"
              @change="onEventChange"
              class="px-2.5 py-1 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option v-for="ev in filteredEventsForTeacher" :key="ev.id" :value="ev.id">
                {{ ev.title }} ({{ ev.date }})
              </option>
            </select>
            <span
              class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
              :class="isEventClosed ? 'bg-slate-200 dark:bg-[#333] text-slate-700 dark:text-slate-300' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
            >
              {{ isEventClosed ? 'CLOSED' : 'ACTIVE' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Quick Export & Import & Copy Buttons -->
      <div class="flex items-center gap-2.5 self-end sm:self-auto flex-wrap">
        <!-- 1. Export (.xlsx) - Functional Muted Emerald Tint -->
        <button
          type="button"
          @click="handleExportAttendance"
          class="group flex items-center gap-2 px-4 py-2.5 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100/90 dark:hover:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200 rounded-xl text-xs font-bold border border-emerald-200 dark:border-emerald-800/80 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-xs hover:shadow-md hover:shadow-emerald-900/10 dark:hover:shadow-black/40 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          title="Download attendance roster as an Excel spreadsheet"
        >
          <Download class="w-4 h-4 text-emerald-600 dark:text-emerald-400 group-hover:translate-y-0.5 transition-transform shrink-0" />
          <span>Export (.xlsx)</span>
        </button>

        <!-- 2. Import Excel Class List - Matches against authorized roster & generates sorted report -->
        <button
          type="button"
          @click="isClassListModalOpen = true"
          class="group flex items-center gap-2 px-4 py-2.5 bg-gradient-to-b from-[#22446e] to-[#1E3A5F] hover:from-[#295285] hover:to-[#172d4a] text-white rounded-xl text-xs font-bold shadow-xs hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          title="Upload Excel class list (.xlsx, .xls, .csv) and generate sorted attendance report"
        >
          <FileSpreadsheet class="w-4 h-4 text-white group-hover:scale-110 transition-transform shrink-0" />
          <span>Import Excel</span>
        </button>

        <!-- 3. Import Roster (.xlsx) - Functional Cobalt Blue Tint -->
        <button
          type="button"
          @click="isImportModalOpen = true"
          class="group flex items-center gap-2 px-4 py-2.5 bg-blue-50 dark:bg-blue-950/40 hover:bg-blue-100/90 dark:hover:bg-blue-900/50 text-blue-800 dark:text-blue-200 rounded-xl text-xs font-bold border border-blue-200 dark:border-blue-800/80 hover:border-blue-300 dark:hover:border-blue-700 shadow-xs hover:shadow-md hover:shadow-blue-900/10 dark:hover:shadow-black/40 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          title="Upload or update official student roster"
        >
          <Upload class="w-4 h-4 text-blue-600 dark:text-blue-400 group-hover:-translate-y-0.5 transition-transform shrink-0" />
          <span>Import Roster (.xlsx)</span>
        </button>

        <!-- 3. Copy Attendance - Functional Neutral Slate Tint with Reactive Copied State -->
        <button
          type="button"
          @click="handleCopyAttendance"
          class="group flex items-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold border shadow-xs hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          :class="copied
            ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-200 border-emerald-300 dark:border-emerald-700 shadow-emerald-500/10'
            : 'bg-slate-100/80 dark:bg-[#252525] hover:bg-slate-200/80 dark:hover:bg-[#2e2e2e] text-slate-800 dark:text-slate-200 border-slate-200 dark:border-[#383838] hover:border-slate-300 dark:hover:border-slate-600 hover:shadow-slate-200/60 dark:hover:shadow-black/40'"
          title="Copy tab-separated attendance to clipboard"
        >
          <Check v-if="copied" class="w-4 h-4 text-emerald-600 dark:text-emerald-400 shrink-0" />
          <Copy v-else class="w-4 h-4 text-slate-500 dark:text-slate-400 group-hover:text-slate-800 dark:group-hover:text-white group-hover:scale-110 group-hover:rotate-3 transition-all shrink-0" />
          <span>{{ copied ? 'Copied to Clipboard!' : 'Copy Attendance' }}</span>
        </button>
      </div>
    </div>

    <!-- Section & Roster Control Bar (Per UI Specification) -->
    <div class="p-5 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4">
      <div class="flex flex-wrap items-center gap-4 sm:gap-6">
        <!-- 1. Assigned Class Selector -->
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-1">
            Assigned Class
          </label>
          <select
            v-model="selectedClassKey"
            class="px-3.5 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer min-w-[180px]"
          >
            <option
              v-for="cls in teacherAssignedClasses"
              :key="`${cls.course}-${cls.section}-${cls.yearLevel || ''}`"
              :value="`${cls.course}:::${cls.section}:::${cls.yearLevel || ''}:::${cls.department || ''}`"
            >
              {{ cls.course }} — {{ cls.section }} {{ cls.yearLevel ? `(${cls.yearLevel})` : '' }}
            </option>
            <option v-if="teacherAssignedClasses.length > 1" value="ALL">
              All Assigned Classes ({{ teacherAssignedClasses.length }})
            </option>
          </select>
        </div>

        <!-- 2. Attendance Summary Pills -->
        <div class="flex items-center gap-2 flex-wrap">
          <div class="px-3 py-1.5 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-900 text-blue-700 dark:text-blue-300 font-extrabold text-xs flex items-center gap-1.5" title="Total Scoped Students">
            <Users class="w-3.5 h-3.5" />
            <span>{{ displayedRoster.length }} Students</span>
          </div>

          <div class="px-3 py-1.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-900 text-emerald-700 dark:text-emerald-300 font-extrabold text-xs flex items-center gap-1.5" title="Students Present">
            <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
            <span>{{ assignedStudentsPresent.length }} Present</span>
          </div>

          <div class="px-3 py-1.5 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-900 text-amber-700 dark:text-amber-300 font-extrabold text-xs flex items-center gap-1.5" title="Pending Event Entry">
            <span class="w-2 h-2 rounded-full bg-amber-500" />
            <span>{{ assignedStudentsNotPresent.length }} {{ isEventClosed ? 'Absent' : 'Pending' }}</span>
          </div>
        </div>
      </div>

      <!-- Quick Search, Status Filter & Centralized Sort Control -->
      <div class="flex flex-wrap items-center gap-3">
        <!-- Centralized Sort Control -->
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-1">
            Sort students by
          </label>
          <div class="relative">
            <ArrowUpDown class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <select
              v-model="sortCriteria"
              class="pl-8 pr-3 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer min-w-[195px]"
            >
              <option value="name_asc">Student Name (A–Z)</option>
              <option value="name_desc">Student Name (Z–A)</option>
              <option value="id_asc">Student ID (Lowest first)</option>
              <option value="status_present">Present Students First</option>
              <option value="status_not_entered">Not Yet Entered First</option>
              <option value="time_in_earliest">Time In (Earliest first)</option>
            </select>
          </div>
        </div>

        <!-- Attendance Status Filter -->
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-1">
            Status Filter
          </label>
          <select
            v-model="filterStatus"
            class="px-3 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All ({{ scopedStudents.length }})</option>
            <option value="present">Present ({{ assignedStudentsPresent.length }})</option>
            <option value="not_entered">{{ isEventClosed ? 'Absent' : 'Not Yet Entered' }} ({{ assignedStudentsNotPresent.length }})</option>
          </select>
        </div>

        <!-- Search Student Input -->
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-1">
            Search Student
          </label>
          <div class="relative">
            <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Search by name or ID..."
              class="pl-8 pr-3 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition w-44 sm:w-52"
            />
          </div>
        </div>
      </div>
    </div>

    <!-- Attendance Table Container -->
    <div class="rounded-2xl border border-slate-200 dark:border-[#333333] bg-white dark:bg-[#1e1e1e] shadow-sm overflow-hidden flex flex-col">
      <div class="overflow-x-auto overflow-y-auto max-h-[560px] custom-scrollbar">
        <table class="w-full text-left text-xs border-collapse">
          <thead class="sticky top-0 z-10 bg-slate-50 dark:bg-[#242424] shadow-xs">
            <tr class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#a0a0a0] uppercase text-[10px] font-extrabold border-b border-slate-200 dark:border-[#333333] tracking-wider">
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#242424]">Student &amp; Class</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#242424]">Student ID</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#242424]">Attendance Status</th>
              <th class="py-3 px-4 bg-slate-50 dark:bg-[#242424]">Time In (PHT)</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
            <tr
              v-for="item in paginatedRoster"
              :key="item.student.id || item.student.studentId"
              class="hover:bg-slate-50 dark:hover:bg-[#242424] transition"
            >
              <!-- 1. Student Name & Academic Details Subtitle -->
              <td class="py-2.5 px-4 whitespace-nowrap">
                <div class="flex items-center gap-3">
                  <UserAvatar :src="item.student.photoUrl" :alt="item.student.name" size="xs" shape="circle" />
                  <div class="min-w-0">
                    <span class="font-bold text-slate-900 dark:text-white block truncate text-xs">{{ item.student.name }}</span>
                    <span class="text-[10px] text-slate-500 dark:text-[#8e8e8e] font-medium block truncate">
                      {{ item.student.course || 'BSIT' }} • {{ item.student.yearLevel || '1st Year' }} • Sec {{ item.student.section || '—' }}
                    </span>
                  </div>
                </div>
              </td>

              <!-- 2. Student ID -->
              <td class="py-2.5 px-4 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                {{ (item.student as any).studentId || (item.student as any).student_id || (item.student as any).id || 'N/A' }}
              </td>

              <!-- 3. Attendance Status -->
              <td class="py-2.5 px-4 whitespace-nowrap">
                <StatusBadge
                  type="attendance"
                  :status="item.isPresent ? 'present' : (isEventClosed ? 'absent' : 'not_entered')"
                />
              </td>

              <!-- 4. Time In (PHT) -->
              <td class="py-2.5 px-4 font-mono font-bold whitespace-nowrap" :class="item.isPresent ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                {{ item.timeInFormatted || '—' }}
              </td>
            </tr>

            <tr v-if="displayedRoster.length === 0">
              <td colspan="4" class="text-center py-12 text-slate-400 dark:text-slate-500">
                <Users class="w-8 h-8 mx-auto mb-2 opacity-40" />
                <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No students found</p>
                <p class="text-[11px] text-slate-500 mt-0.5">
                  No authoritative student records match the selected section ({{ selectedClassLabel }}).
                </p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Pagination Footer -->
      <div
        v-if="displayedRoster.length > 0"
        class="px-4 py-3 bg-slate-50/70 dark:bg-[#202020] border-t border-slate-200 dark:border-[#333333] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs text-slate-500 dark:text-slate-400"
      >
        <div class="flex items-center gap-1.5">
          <span>Showing</span>
          <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ paginationStart }}</span>
          <span>to</span>
          <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ paginationEnd }}</span>
          <span>of</span>
          <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">{{ displayedRoster.length }}</span>
          <span>students</span>
        </div>

        <div class="flex items-center gap-3">
          <!-- Page Size Selector -->
          <div class="flex items-center gap-1.5">
            <span class="text-[11px]">Per page:</span>
            <select
              v-model="pageSize"
              class="px-2 py-1 bg-white dark:bg-[#1a1a1a] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option :value="10">10</option>
              <option :value="15">15</option>
              <option :value="25">25</option>
              <option :value="50">50</option>
              <option :value="1000">All</option>
            </select>
          </div>

          <!-- Page Navigation Buttons -->
          <div v-if="totalPages > 1" class="flex items-center gap-1">
            <button
              type="button"
              @click="currentPage--"
              :disabled="currentPage <= 1"
              class="p-1 rounded-lg border border-slate-200 dark:border-[#3a3a3a] bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#2a2a2a] disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
              title="Previous Page"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>
            <span class="px-2.5 py-0.5 rounded-md bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] font-mono font-bold text-slate-800 dark:text-slate-200 text-xs">
              {{ currentPage }} / {{ totalPages }}
            </span>
            <button
              type="button"
              @click="currentPage++"
              :disabled="currentPage >= totalPages"
              class="p-1 rounded-lg border border-slate-200 dark:border-[#3a3a3a] bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#2a2a2a] disabled:opacity-40 disabled:cursor-not-allowed transition cursor-pointer"
              title="Next Page"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- Student Import Excel Modal -->
    <StudentImportModal
      :is-open="isImportModalOpen"
      @close="isImportModalOpen = false"
      @imported="handleImportSuccess"
    />

    <!-- Class List Attendance Reconciliation & Reporting Modal -->
    <ClassListAttendanceModal
      :is-open="isClassListModalOpen"
      :target-event="currentEvent"
      :class-label="selectedClassLabel"
      :selected-class-key="selectedClassKey"
      @close="isClassListModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Users, CalendarDays, Search, Download, Copy, Check, Upload, FileSpreadsheet,
  CheckCircle2, AlertCircle, ArrowUpDown, ChevronLeft, ChevronRight
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import UserAvatar from '../common/UserAvatar.vue';
import StatusBadge from '../common/StatusBadge.vue';
import StudentImportModal from './StudentImportModal.vue';
import ClassListAttendanceModal from './ClassListAttendanceModal.vue';
import { exportRosterAttendanceToExcel } from '../../utils/excelExport';
import { splitFullName, sortByLastName, sortAttendanceRoster, TeacherRosterSortCriteria } from '../../utils/nameUtils';
import { formatPhilippineTime } from '../../utils/philippineTime';
import { User, Ticket, StudentRecord, TeacherClassAssignment } from '../../types';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const {
  currentUser,
  currentEvent,
  setCurrentEvent,
  events,
  tickets,
  users,
  studentRecords,
  academicPeriods,
  activeAcademicPeriod
} = useAppStore();

const selectedPeriodId = ref<string>(activeAcademicPeriod.value?.id || 'all');
const sortCriteria = ref<TeacherRosterSortCriteria>('name_asc');

const selectedPeriod = computed(() => {
  if (selectedPeriodId.value === 'all') return null;
  return academicPeriods.value.find(p => p.id === selectedPeriodId.value) || null;
});

const filteredEventsForTeacher = computed(() => {
  if (!selectedPeriod.value) return events.value;
  return events.value.filter(e => e.academicYear === selectedPeriod.value?.academicYear && e.semester === selectedPeriod.value?.semester);
});

watch(filteredEventsForTeacher, (newEvents) => {
  if (newEvents.length > 0) {
    if (!currentEvent.value || !newEvents.some(e => e.id === currentEvent.value?.id)) {
      setCurrentEvent(newEvents[0]);
    }
  }
});

const isImportModalOpen = ref(false);
const isClassListModalOpen = ref(false);
const filterStatus = ref('all');
const searchQuery = ref('');
const copied = ref(false);
let copyTimeout: any = null;

const toastNotification = ref<{ title: string; message: string; type: 'success' | 'error' } | null>(null);
let toastTimer: any = null;

const showToast = (title: string, message: string, type: 'success' | 'error' = 'success') => {
  if (toastTimer) clearTimeout(toastTimer);
  toastNotification.value = { title, message, type };
  toastTimer = setTimeout(() => {
    toastNotification.value = null;
  }, 4500);
};

const handleImportSuccess = (res: { total: number; added: number; updated: number; message: string; importedClasses: any[] }) => {
  if (res.importedClasses && res.importedClasses.length > 0) {
    const firstCls = res.importedClasses[0];
    selectedClassKey.value = `${firstCls.course}:::${firstCls.section}`;
  } else {
    selectedClassKey.value = 'ALL';
  }

  showToast(
    'Roster Imported Successfully!',
    `Loaded ${res.total} student records (${res.added} new added, ${res.updated} updated) into your assigned class attendance.`,
    'success'
  );
};

// Lifecycle Determination: Closed if completed, archived, or cancelled
const isEventClosed = computed(() => {
  if (!currentEvent.value) return false;
  const status = (currentEvent.value.status || '').toLowerCase();
  return status === 'completed' || status === 'archived' || status === 'cancelled';
});

import { matchTeacherClassAssignment } from '../../utils/academicMasterData';

// Explicit Authorized Class Assignments for Current Teacher
const teacherAssignedClasses = computed<TeacherClassAssignment[]>(() => {
  return currentUser.value?.assignedClasses || [];
});

// Active Selected Class Key (Defaults to first assigned class e.g. "BSIT:::31M1:::3rd Year:::CSD" or "ALL")
const selectedClassKey = ref<string>('ALL');

watch(
  () => teacherAssignedClasses.value,
  classes => {
    if (classes.length > 0) {
      if (selectedClassKey.value !== 'ALL') {
        const match = classes.find(c => `${c.course}:::${c.section}:::${c.yearLevel || ''}:::${c.department || ''}` === selectedClassKey.value);
        if (!match) {
          selectedClassKey.value = 'ALL';
        }
      }
    } else {
      selectedClassKey.value = 'ALL';
    }
  },
  { immediate: true }
);

const selectedClassLabel = computed(() => {
  if (teacherAssignedClasses.value.length === 0) {
    return 'No Assigned Classes (0)';
  }
  if (selectedClassKey.value === 'ALL') {
    return `All Assigned Classes (${teacherAssignedClasses.value.length})`;
  }
  const found = teacherAssignedClasses.value.find(
    c => `${c.course}:::${c.section}:::${c.yearLevel || ''}:::${c.department || ''}` === selectedClassKey.value
  );
  if (found) {
    return `${found.course} — ${found.section} ${found.yearLevel ? `(${found.yearLevel})` : ''}`;
  }
  return selectedClassKey.value.replace(/:::/g, ' — ') || 'Select Class';
});

// Authoritative Master Student Pool: StudentRecord roster + enrolled users
const allUniqueStudents = computed<(StudentRecord | User)[]>(() => {
  const map = new Map<string, StudentRecord | User>();

  studentRecords.value.forEach(rec => {
    const sId = rec.studentId || (rec as any).student_id || (rec.id && !rec.id.startsWith('rec-') ? rec.id : '');
    const key = (sId || rec.id || '').toLowerCase().trim();
    if (!key) return;

    // Keep name in sync with registered user account if one exists
    const matchingUser = users.value.find(
      u => u.role === 'student' && ((u.studentId || (u as any).student_id) || '').toLowerCase().trim() === (sId ? sId.toLowerCase().trim() : key)
    );
    if (matchingUser) {
      const finalId = sId || matchingUser.studentId || (matchingUser as any).student_id || matchingUser.id;
      map.set(key, {
        ...rec,
        studentId: finalId,
        name: matchingUser.name || rec.name,
        firstName: matchingUser.firstName || rec.firstName,
        lastName: matchingUser.lastName || rec.lastName,
        email: matchingUser.email || rec.email,
        course: matchingUser.course || rec.course,
        yearLevel: matchingUser.yearLevel || rec.yearLevel,
        section: matchingUser.section || rec.section,
        department: matchingUser.department || rec.department,
        userId: matchingUser.id,
      });
    } else {
      map.set(key, {
        ...rec,
        studentId: sId || rec.id,
      });
    }
  });

  users.value.forEach(u => {
    const sId = u.studentId || (u as any).student_id;
    if (u.role === 'student' && sId) {
      const key = sId.toLowerCase().trim();
      if (!map.has(key)) {
        map.set(key, {
          ...u,
          studentId: sId,
        });
      }
    }
  });

  return Array.from(map.values());
});

// Scoped Students: Filtered by Selected Class Key (or all assigned classes if 'ALL')
const scopedStudents = computed<(StudentRecord | User)[]>(() => {
  const assigned = teacherAssignedClasses.value;
  if (assigned.length === 0) {
    return [];
  }

  if (!selectedClassKey.value || selectedClassKey.value === 'ALL') {
    return allUniqueStudents.value.filter(st => {
      return assigned.some(cls => matchTeacherClassAssignment(cls, st));
    });
  }

  const [reqCourse, reqSection, reqYear, reqDept] = selectedClassKey.value.split(':::');
  return allUniqueStudents.value.filter(st => {
    return matchTeacherClassAssignment(
      {
        course: reqCourse,
        section: reqSection,
        yearLevel: reqYear || undefined,
        department: reqDept || undefined,
      },
      st
    );
  });
});

// Current Event Valid Tickets
const currentEventTickets = computed<Ticket[]>(() => {
  if (!currentEvent.value) return [];
  return tickets.value.filter(t => t.eventId === currentEvent.value?.id && t.ticketStatus !== 'CANCELLED');
});

// Attendance Record mapping for each student in the selected section
interface StudentAttendanceItem {
  student: StudentRecord | User;
  ticket?: Ticket;
  isPresent: boolean;
  timeInFormatted?: string;
}

const sectionAttendanceRoster = computed<StudentAttendanceItem[]>(() => {
  const tixMapByStudentId = new Map<string, Ticket>();
  const tixMapByUserId = new Map<string, Ticket>();

  currentEventTickets.value.forEach(t => {
    if (t.attendeeStudentId) {
      tixMapByStudentId.set(t.attendeeStudentId.toLowerCase().trim(), t);
    }
    if (t.currentAttendeeId) {
      tixMapByUserId.set(t.currentAttendeeId, t);
    }
  });

  return scopedStudents.value.map(student => {
    const sId = (student.studentId || (student as any).student_id || student.id)?.toLowerCase().trim();
    const uId = (student as any).userId || student.id;

    const matchedTicket = (sId ? tixMapByStudentId.get(sId) : undefined) || (uId ? tixMapByUserId.get(uId) : undefined);
    const isPresent = matchedTicket?.attendanceStatus === 'present';

    let timeInFormatted: string | undefined = undefined;
    if (isPresent && matchedTicket?.timeIn) {
      timeInFormatted = formatPhilippineTime(matchedTicket.timeIn);
    }

    return {
      student,
      ticket: matchedTicket,
      isPresent,
      timeInFormatted,
    };
  });
});

// Filtered & Sorted Display Roster
const displayedRoster = computed<StudentAttendanceItem[]>(() => {
  // 1. Filter by status & search query
  const result = sectionAttendanceRoster.value.filter(item => {
    if (filterStatus.value === 'present' && !item.isPresent) return false;
    if (filterStatus.value === 'not_entered' && item.isPresent) return false;

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const nameMatch = item.student.name?.toLowerCase().includes(q);
      const idMatch = item.student.studentId?.toLowerCase().includes(q);
      if (!nameMatch && !idMatch) return false;
    }

    return true;
  });

  // 2. Centralized Roster Sorting using selected plain-language sortCriteria
  return sortAttendanceRoster(result, sortCriteria.value);
});

const assignedStudentsPresent = computed(() => sectionAttendanceRoster.value.filter(i => i.isPresent));
const assignedStudentsNotPresent = computed(() => sectionAttendanceRoster.value.filter(i => !i.isPresent));

// Pagination State & Computations
const currentPage = ref(1);
const pageSize = ref(15);

const totalPages = computed(() => {
  if (pageSize.value >= 1000) return 1;
  return Math.max(1, Math.ceil(displayedRoster.value.length / pageSize.value));
});

const paginatedRoster = computed(() => {
  if (pageSize.value >= 1000) return displayedRoster.value;
  const start = (currentPage.value - 1) * pageSize.value;
  return displayedRoster.value.slice(start, start + pageSize.value);
});

const paginationStart = computed(() => {
  if (displayedRoster.value.length === 0) return 0;
  return (currentPage.value - 1) * pageSize.value + 1;
});

const paginationEnd = computed(() => {
  if (pageSize.value >= 1000) return displayedRoster.value.length;
  return Math.min(currentPage.value * pageSize.value, displayedRoster.value.length);
});

// Auto-reset page when filtering, sorting or page size changes
watch([searchQuery, filterStatus, selectedClassKey, sortCriteria, pageSize], () => {
  currentPage.value = 1;
});

const onEventChange = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = events.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};

// Copy Attendance TSV (Tab-separated for spreadsheet paste)
const handleCopyAttendance = async () => {
  if (displayedRoster.value.length === 0) return;

  const header = ['Student Name', 'Student ID', 'Course', 'Year Level', 'Section', 'Status', 'Time In'].join('\t');
  const rows = displayedRoster.value.map(item => {
    const statusText = item.isPresent ? 'PRESENT' : (isEventClosed.value ? 'ABSENT' : 'NOT YET ENTERED');
    return [
      item.student.name || '---',
      item.student.studentId || 'N/A',
      item.student.course || 'BSIT',
      item.student.yearLevel || '1st Year',
      item.student.section || '---',
      statusText,
      item.timeInFormatted || '---',
    ].join('\t');
  });

  const tsvContent = [header, ...rows].join('\n');

  try {
    if (navigator?.clipboard?.writeText) {
      await navigator.clipboard.writeText(tsvContent);
    } else {
      const textarea = document.createElement('textarea');
      textarea.value = tsvContent;
      document.body.appendChild(textarea);
      textarea.select();
      document.execCommand('copy');
      document.body.removeChild(textarea);
    }

    copied.value = true;
    if (copyTimeout) clearTimeout(copyTimeout);
    copyTimeout = setTimeout(() => {
      copied.value = false;
    }, 2500);
  } catch (err) {
    console.error('Failed to copy section attendance table:', err);
  }
};

// Export Attendance (.xlsx)
const handleExportAttendance = () => {
  if (!currentEvent.value) return;
  const classSuffix = selectedClassKey.value.replace(':::', '_');
  const teacherName = currentUser.value?.name?.replace(/[^a-zA-Z0-9_-]/g, '_') || 'Teacher';

  exportRosterAttendanceToExcel(
    displayedRoster.value,
    currentEvent.value,
    `${teacherName}_${classSuffix}_Attendance.xlsx`,
    isEventClosed.value
  );
};
</script>
