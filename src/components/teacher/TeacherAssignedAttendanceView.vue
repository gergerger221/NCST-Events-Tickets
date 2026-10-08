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
            Event for Attendance Monitoring
          </label>
          <div class="flex items-center gap-2 mt-0.5">
            <select
              :value="currentEvent?.id || ''"
              @change="onEventChange"
              class="px-2.5 py-1 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-lg text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option v-for="ev in events" :key="ev.id" :value="ev.id">
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
        <button
          type="button"
          @click="isImportModalOpen = true"
          class="flex items-center gap-1.5 px-3.5 py-2 bg-purple-600 hover:bg-purple-700 active:scale-[0.98] text-white rounded-xl text-xs font-black shadow-sm transition cursor-pointer"
        >
          <Upload class="w-3.5 h-3.5" />
          <span>Import Roster (.xlsx)</span>
        </button>

        <button
          type="button"
          @click="handleCopyAttendance"
          class="flex items-center gap-1.5 px-3.5 py-2 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white rounded-xl text-xs font-black shadow-sm transition cursor-pointer"
        >
          <Check v-if="copied" class="w-3.5 h-3.5 text-emerald-300" />
          <Copy v-else class="w-3.5 h-3.5" />
          <span>{{ copied ? 'Copied to Clipboard!' : 'Copy Attendance' }}</span>
        </button>

        <button
          type="button"
          @click="handleExportAttendance"
          class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 active:scale-[0.98] text-white rounded-xl text-xs font-black shadow-sm transition cursor-pointer"
        >
          <Download class="w-3.5 h-3.5" />
          <span>Export (.xlsx)</span>
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
            class="px-3.5 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-black text-purple-700 dark:text-purple-300 focus:outline-none focus:border-purple-500 cursor-pointer min-w-[180px]"
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

        <!-- 2. Roster Count -->
        <div>
          <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider mb-1">
            Roster
          </label>
          <div class="px-3.5 py-2 rounded-xl bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-900 text-blue-700 dark:text-blue-300 font-extrabold text-xs flex items-center gap-1.5">
            <Users class="w-3.5 h-3.5" />
            <span>{{ displayedRoster.length }} Students</span>
          </div>
        </div>
      </div>

      <!-- Quick Search & Status Filter -->
      <div class="flex flex-wrap items-center gap-3">
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

    <!-- Attendance Table -->
    <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#333333] bg-white dark:bg-[#1e1e1e] shadow-sm">
      <table class="w-full text-left text-xs border-collapse">
        <thead>
          <tr class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#a0a0a0] uppercase text-[10px] font-extrabold border-b border-slate-200 dark:border-[#333333] tracking-wider">
            <th class="py-3 px-4">Student Name</th>
            <th class="py-3 px-4">Student ID</th>
            <th class="py-3 px-4">Course</th>
            <th class="py-3 px-4">Year Level</th>
            <th class="py-3 px-4">Section</th>
            <th class="py-3 px-4">Attendance Status</th>
            <th class="py-3 px-4">Time In (PHT)</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
          <tr
            v-for="item in displayedRoster"
            :key="item.student.id || item.student.studentId"
            class="hover:bg-slate-50 dark:hover:bg-[#242424] transition"
          >
            <!-- 1. Student Name -->
            <td class="py-3 px-4 font-bold text-slate-900 dark:text-white whitespace-nowrap">
              <div class="flex items-center gap-2.5">
                <UserAvatar :src="item.student.photoUrl" :alt="item.student.name" size="xs" shape="circle" />
                <span>{{ item.student.name }}</span>
              </div>
            </td>

            <!-- 2. Student ID -->
            <td class="py-3 px-4 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
              {{ item.student.studentId || 'N/A' }}
            </td>

            <!-- 3. Course -->
            <td class="py-3 px-4 text-slate-800 dark:text-slate-200 font-semibold whitespace-nowrap">
              {{ item.student.course || 'BSIT' }}
            </td>

            <!-- 4. Year Level -->
            <td class="py-3 px-4 text-slate-600 dark:text-[#a0a0a0] whitespace-nowrap">
              {{ item.student.yearLevel || '3rd Year' }}
            </td>

            <!-- 5. Section -->
            <td class="py-3 px-4 font-mono font-bold text-slate-800 dark:text-slate-200 whitespace-nowrap">
              {{ item.student.section || 'N/A' }}
            </td>

            <!-- 6. Attendance Status -->
            <td class="py-3 px-4 whitespace-nowrap">
              <StatusBadge
                type="attendance"
                :status="item.isPresent ? 'present' : (isEventClosed ? 'absent' : 'not_entered')"
              />
            </td>

            <!-- 7. Time In (PHT) -->
            <td class="py-3 px-4 font-mono font-bold whitespace-nowrap" :class="item.isPresent ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
              {{ item.timeInFormatted || '—' }}
            </td>
          </tr>

          <tr v-if="displayedRoster.length === 0">
            <td colspan="7" class="text-center py-12 text-slate-400 dark:text-slate-500">
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

    <!-- Student Import Excel Modal -->
    <StudentImportModal
      :is-open="isImportModalOpen"
      @close="isImportModalOpen = false"
      @imported="handleImportSuccess"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Users, CalendarDays, Search, Download, Copy, Check, Upload, FileSpreadsheet,
  CheckCircle2, AlertCircle
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import UserAvatar from '../common/UserAvatar.vue';
import StatusBadge from '../common/StatusBadge.vue';
import StudentImportModal from './StudentImportModal.vue';
import { exportRosterAttendanceToExcel } from '../../utils/excelExport';
import { splitFullName, sortByLastName } from '../../utils/nameUtils';
import { formatPhilippineTime } from '../../utils/philippineTime';
import { User, Ticket, StudentRecord, TeacherClassAssignment } from '../../types';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const { currentUser, currentEvent, setCurrentEvent, events, tickets, users, studentRecords } = useAppStore();

const isImportModalOpen = ref(false);
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

// Active Selected Class Key (Defaults to first assigned class e.g. "BSIT:::A1:::3rd Year:::CSD" or "ALL")
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
    const key = rec.studentId?.toLowerCase().trim() || rec.id;
    // Keep name in sync with registered user account if one exists
    const matchingUser = users.value.find(
      u => u.role === 'student' && u.studentId && u.studentId.toLowerCase().trim() === key
    );
    if (matchingUser) {
      map.set(key, {
        ...rec,
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
      map.set(key, rec);
    }
  });

  users.value.forEach(u => {
    if (u.role === 'student' && u.studentId) {
      const key = u.studentId.toLowerCase().trim();
      if (!map.has(key)) {
        map.set(key, u);
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
    const sId = student.studentId?.toLowerCase().trim();
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
  let result = sectionAttendanceRoster.value.filter(item => {
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

  // 2. Automatically sort roster alphabetically by student last name (A–Z)
  return sortByLastName([...result], item => item.student.name || '');
});

const assignedStudentsPresent = computed(() => sectionAttendanceRoster.value.filter(i => i.isPresent));
const assignedStudentsNotPresent = computed(() => sectionAttendanceRoster.value.filter(i => !i.isPresent));

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
