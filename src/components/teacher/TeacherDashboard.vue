<template>
  <div class="space-y-6">
    <!-- Header: Faculty Advisory & Active Event Context -->
    <div class="relative overflow-hidden rounded-3xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-6 sm:p-8 shadow-sm dark:shadow-md transition-colors">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-2xl bg-blue-50 dark:bg-blue-500/10 border border-blue-200 dark:border-blue-500/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <GraduationCap class="w-7 h-7" />
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-blue-100 dark:bg-blue-500/20 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-500/30">
                Faculty Portal
              </span>
              <span class="text-xs text-slate-500 dark:text-[#b3b3b3] font-medium">
                {{ currentUser?.department || 'College of Computer Studies' }}
              </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
              Welcome, {{ currentUser?.name }}
            </h1>
            
            <!-- Assigned Class Scope Badges -->
            <div class="flex flex-wrap items-center gap-2 mt-3">
              <span class="text-[11px] font-bold uppercase text-slate-400 dark:text-slate-500 tracking-wider">Assigned Classes:</span>
              <template v-if="teacherAssignedClasses.length > 0">
                <span
                  v-for="cls in teacherAssignedClasses"
                  :key="`${cls.course}-${cls.section}`"
                  class="px-2.5 py-0.5 rounded-lg bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 font-extrabold text-xs border border-blue-200 dark:border-blue-800"
                >
                  {{ cls.course }} — {{ cls.section }}
                </span>
              </template>
              <span v-else class="text-xs text-amber-600 dark:text-amber-400 font-semibold italic">
                No classes assigned by Admin yet
              </span>
            </div>
          </div>
        </div>

        <!-- Primary Action: Open Dedicated Attendance Roster -->
        <div class="flex flex-wrap items-center gap-3">
          <button
            type="button"
            @click="$emit('navigate', 'teacher_attendance')"
            class="group flex items-center gap-2 px-5 py-2.5 bg-gradient-to-b from-[#22446e] to-[#1E3A5F] hover:from-[#295285] hover:to-[#172d4a] text-white rounded-xl text-xs font-bold shadow-md shadow-[#1E3A5F]/20 hover:shadow-lg hover:shadow-[#1E3A5F]/35 border border-blue-400/25 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          >
            <GraduationCap class="w-4 h-4 text-blue-200 group-hover:text-white group-hover:scale-110 transition-all duration-200" />
            <span>Open Students' Attendance Roster</span>
            <ArrowRight class="w-3.5 h-3.5 group-hover:translate-x-1 transition-transform duration-200" />
          </button>

          <button
            type="button"
            @click="isClassListModalOpen = true"
            class="group flex items-center gap-2 px-4 py-2.5 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100/90 dark:hover:bg-emerald-900/50 text-emerald-800 dark:text-emerald-200 rounded-xl text-xs font-bold border border-emerald-200 dark:border-emerald-800/80 hover:border-emerald-300 dark:hover:border-emerald-700 shadow-xs hover:shadow-md hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
            title="Import class list spreadsheet (.xlsx, .xls, .csv) and generate sorted attendance report"
          >
            <FileSpreadsheet class="w-4 h-4 text-emerald-600 dark:text-emerald-400 group-hover:scale-110 transition-transform shrink-0" />
            <span>Import Excel</span>
          </button>

          <button
            type="button"
            @click="$emit('navigate', 'student_roster_import')"
            class="group flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-[#242424] hover:bg-slate-50 dark:hover:bg-[#2c2c2c] text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white rounded-xl text-xs font-bold border border-slate-200 dark:border-[#383838] hover:border-slate-300 dark:hover:border-slate-600 shadow-xs hover:shadow-md hover:shadow-slate-200/60 dark:hover:shadow-black/40 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          >
            <Upload class="w-4 h-4 text-slate-400 group-hover:text-blue-600 dark:text-slate-400 dark:group-hover:text-blue-400 group-hover:-translate-y-0.5 transition-all duration-200" />
            <span>Import Class Roster</span>
          </button>

          <button
            type="button"
            @click="$emit('navigate', 'reports')"
            class="group flex items-center gap-2 px-4 py-2.5 bg-white dark:bg-[#242424] hover:bg-slate-50 dark:hover:bg-[#2c2c2c] text-slate-700 hover:text-slate-900 dark:text-slate-200 dark:hover:text-white rounded-xl text-xs font-bold border border-slate-200 dark:border-[#383838] hover:border-slate-300 dark:hover:border-slate-600 shadow-xs hover:shadow-md hover:shadow-slate-200/60 dark:hover:shadow-black/40 hover:-translate-y-0.5 active:translate-y-0 active:scale-[0.98] transition-all duration-200 cursor-pointer select-none"
          >
            <FileSpreadsheet class="w-4 h-4 text-slate-400 group-hover:text-blue-600 dark:text-slate-400 dark:group-hover:text-blue-400 group-hover:scale-110 transition-all duration-200" />
            <span>Attendance Reports</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Active Event & Class Scope Selectors -->
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
      <!-- 1. Active Event Selector -->
      <div class="p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <CalendarDays class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider">
              Event for Attendance Monitoring
            </label>
            <div class="flex items-center gap-2 mt-0.5">
              <p class="text-sm font-black text-slate-900 dark:text-white truncate">
                {{ currentEvent?.title || 'Select an Event' }}
              </p>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider"
                :class="isEventClosed ? 'bg-slate-200 dark:bg-[#333] text-slate-700 dark:text-slate-300' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
              >
                {{ isEventClosed ? 'CLOSED' : 'ACTIVE' }}
              </span>
            </div>
          </div>
        </div>

        <select
          :value="currentEvent?.id || ''"
          @change="onEventChange"
          class="px-3 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer max-w-[200px]"
        >
          <option v-for="ev in events" :key="ev.id" :value="ev.id">
            {{ ev.title }} ({{ ev.status.toUpperCase() }})
          </option>
        </select>
      </div>

      <!-- 2. Assigned Class Selector -->
      <div class="p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex items-center justify-between gap-4">
        <div class="flex items-center gap-3 min-w-0">
          <div class="w-10 h-10 rounded-xl bg-blue-50 dark:bg-blue-900/30 text-blue-600 dark:text-blue-400 flex items-center justify-center shrink-0">
            <BookOpen class="w-5 h-5" />
          </div>
          <div class="min-w-0">
            <label class="block text-[10px] font-black uppercase text-slate-400 dark:text-slate-500 tracking-wider">
              Active Class Scope
            </label>
            <p class="text-sm font-black text-slate-900 dark:text-white truncate">
              {{ selectedClassLabel }}
            </p>
          </div>
        </div>

        <select
          v-model="selectedClassKey"
          class="px-3 py-2 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer max-w-[220px]"
        >
          <option v-if="teacherAssignedClasses.length > 1" value="ALL">
            All Assigned Classes ({{ teacherAssignedClasses.length }})
          </option>
          <option
            v-for="cls in teacherAssignedClasses"
            :key="`${cls.course}-${cls.section}-${cls.yearLevel || ''}`"
            :value="`${cls.course}:::${cls.section}:::${cls.yearLevel || ''}:::${cls.department || ''}`"
          >
            {{ cls.course }} — {{ cls.section }} {{ cls.yearLevel ? `(${cls.yearLevel})` : '' }}
          </option>
        </select>
      </div>
    </div>

    <!-- Real-Time Metric Summary Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <!-- 1. Present -->
      <div class="p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[11px] font-extrabold uppercase tracking-wider text-emerald-600 dark:text-emerald-400">
            Present
          </span>
          <p class="text-3xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
            {{ assignedStudentsPresent.length }}
          </p>
          <span class="text-[11px] text-emerald-600/80 font-semibold">
            {{ assignedAttendanceRate }}% attendance rate
          </span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center">
          <CheckCircle2 class="w-6 h-6" />
        </div>
      </div>

      <!-- 2. Not Yet Entered / Absent -->
      <div class="p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex items-center justify-between">
        <div>
          <span
            class="text-[11px] font-extrabold uppercase tracking-wider"
            :class="isEventClosed ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'"
          >
            {{ isEventClosed ? 'Absent' : 'Not Yet Entered' }}
          </span>
          <p
            class="text-3xl font-black mt-1"
            :class="isEventClosed ? 'text-rose-600 dark:text-rose-400' : 'text-amber-600 dark:text-amber-400'"
          >
            {{ assignedStudentsNotPresent.length }}
          </p>
          <span class="text-[11px] text-slate-400">
            {{ isEventClosed ? 'Did not attend event' : 'Awaiting scan at entrance' }}
          </span>
        </div>
        <div
          class="w-12 h-12 rounded-2xl flex items-center justify-center"
          :class="isEventClosed ? 'bg-rose-50 dark:bg-rose-500/10 text-rose-600 dark:text-rose-400' : 'bg-amber-50 dark:bg-amber-500/10 text-amber-600 dark:text-amber-400'"
        >
          <XCircle v-if="isEventClosed" class="w-6 h-6" />
          <Clock v-else class="w-6 h-6" />
        </div>
      </div>

      <!-- 3. Total Enrolled in Scope -->
      <div class="p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex items-center justify-between">
        <div>
          <span class="text-[11px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-[#b3b3b3]">
            Total Enrolled
          </span>
          <p class="text-3xl font-black text-slate-900 dark:text-white mt-1">
            {{ scopedStudents.length }}
          </p>
          <span class="text-[11px] text-slate-400">Authoritative roster count</span>
        </div>
        <div class="w-12 h-12 rounded-2xl bg-blue-50 dark:bg-blue-500/10 text-blue-600 dark:text-blue-400 flex items-center justify-center">
          <Users class="w-6 h-6" />
        </div>
      </div>
    </div>

    <!-- Assigned Classes Breakdown Cards -->
    <div class="space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
          <BookOpen class="w-5 h-5 text-blue-600 dark:text-blue-400" />
          Assigned Class Overview
        </h2>
        <span class="text-xs text-slate-500 dark:text-slate-400 font-semibold">
          {{ teacherAssignedClasses.length }} Authorized Class {{ teacherAssignedClasses.length === 1 ? 'Assignment' : 'Assignments' }}
        </span>
      </div>

      <div v-if="classBreakdownList.length > 0" class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div
          v-for="cls in classBreakdownList"
          :key="`${cls.course}-${cls.section}`"
          class="p-6 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm hover:shadow-md transition space-y-4"
        >
          <div class="flex items-start justify-between gap-4">
            <div>
              <div class="flex items-center gap-2">
                <span class="text-sm font-black text-slate-900 dark:text-white">
                  {{ cls.course }} — {{ cls.section }}
                </span>
                <span class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800">
                  {{ cls.yearLevel || 'Undergraduate' }}
                </span>
              </div>
              <p v-if="cls.subject" class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-1 font-medium">
                {{ cls.subject }}
              </p>
            </div>

            <button
              type="button"
              @click="$emit('navigate', 'teacher_attendance')"
              class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/80 text-blue-700 dark:text-blue-300 rounded-xl text-xs font-bold border border-blue-200 dark:border-blue-800 transition cursor-pointer"
            >
              <span>View Roster</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </button>
          </div>

          <!-- Progress Bar & Stats -->
          <div class="space-y-2 pt-2 border-t border-slate-100 dark:border-[#2a2a2a]">
            <div class="flex items-center justify-between text-xs">
              <span class="text-slate-500 dark:text-[#b3b3b3] font-semibold">Attendance Rate</span>
              <span class="font-black text-emerald-600 dark:text-emerald-400">{{ cls.rate }}%</span>
            </div>
            <div class="w-full bg-slate-100 dark:bg-[#282828] h-2.5 rounded-full overflow-hidden">
              <div
                class="h-full rounded-full transition-all duration-500"
                :class="cls.rate >= 75 ? 'bg-emerald-500' : (cls.rate >= 50 ? 'bg-blue-500' : 'bg-amber-500')"
                :style="{ width: `${cls.rate}%` }"
              />
            </div>
            <div class="flex items-center justify-between text-[11px] text-slate-400">
              <span>Present: <strong class="text-emerald-600 dark:text-emerald-400">{{ cls.presentCount }}</strong></span>
              <span>{{ isEventClosed ? 'Absent' : 'Pending' }}: <strong :class="isEventClosed ? 'text-rose-500' : 'text-amber-500'">{{ cls.notPresentCount }}</strong></span>
              <span>Total: <strong class="text-slate-700 dark:text-slate-200">{{ cls.totalCount }}</strong></span>
            </div>
          </div>
        </div>
      </div>
      <div v-else class="p-8 text-center bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl space-y-2">
        <BookOpen class="w-10 h-10 text-slate-300 dark:text-slate-600 mx-auto" />
        <h3 class="text-sm font-bold text-slate-800 dark:text-slate-200">No Authorized Class Assignments</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 max-w-md mx-auto">
          The Administrator has not provisioned any Course + Section pairs for your faculty account yet. Please contact your Department Admin.
        </p>
      </div>
    </div>

    <!-- Quick Navigation Banner -->
    <div class="p-6 rounded-3xl bg-gradient-to-r from-blue-900/20 via-indigo-900/20 to-purple-900/20 border border-blue-500/20 flex flex-col sm:flex-row items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <div class="w-12 h-12 rounded-2xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-lg shadow-blue-500/30">
          <GraduationCap class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-base font-black text-slate-900 dark:text-white">
            Looking for the detailed attendance list?
          </h3>
          <p class="text-xs text-slate-500 dark:text-slate-300">
            Open the dedicated Assigned Students' Attendance view to view student timestamps, copy TSV data, and export clean Excel reports.
          </p>
        </div>
      </div>

      <button
        type="button"
        @click="$emit('navigate', 'teacher_attendance')"
        class="flex items-center gap-2 px-5 py-2.5 bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white rounded-xl text-xs font-black shadow-md shadow-blue-500/25 transition cursor-pointer shrink-0"
      >
        <span>Go to Attendance Roster</span>
        <ArrowRight class="w-4 h-4" />
      </button>
    </div>

    <!-- Class List Attendance Reconciliation & Reporting Modal -->
    <ClassListAttendanceModal
      :is-open="isClassListModalOpen"
      :target-event="currentEvent"
      :class-label="selectedClassLabel"
      @close="isClassListModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  GraduationCap, Users, CheckCircle2, Clock, CalendarDays, BookOpen, ArrowRight, FileSpreadsheet, XCircle
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { TeacherClassAssignment, StudentRecord, User, Ticket } from '../../types';
import ClassListAttendanceModal from './ClassListAttendanceModal.vue';

defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const { currentUser, currentEvent, setCurrentEvent, events, tickets, users, studentRecords } = useAppStore();

const isClassListModalOpen = ref(false);

const isEventClosed = computed(() => {
  if (!currentEvent.value) return false;
  const status = (currentEvent.value.status || '').toLowerCase();
  return status === 'completed' || status === 'archived' || status === 'cancelled';
});

const teacherAssignedClasses = computed<TeacherClassAssignment[]>(() => {
  return currentUser.value?.assignedClasses || [];
});

const selectedClassKey = ref<string>('ALL');

watch(
  () => teacherAssignedClasses.value,
  classes => {
    if (classes.length === 1) {
      selectedClassKey.value = `${classes[0].course}:::${classes[0].section}:::${classes[0].yearLevel || ''}:::${classes[0].department || ''}`;
    } else if (classes.length > 1 && selectedClassKey.value !== 'ALL') {
      const match = classes.some(c => `${c.course}:::${c.section}:::${c.yearLevel || ''}:::${c.department || ''}` === selectedClassKey.value);
      if (!match) selectedClassKey.value = 'ALL';
    }
  },
  { immediate: true }
);

const selectedClassLabel = computed(() => {
  if (selectedClassKey.value === 'ALL') {
    return `All Assigned Classes (${teacherAssignedClasses.value.length})`;
  }
  const found = teacherAssignedClasses.value.find(
    c => `${c.course}:::${c.section}:::${c.yearLevel || ''}:::${c.department || ''}` === selectedClassKey.value
  );
  if (found) {
    return `${found.course} — ${found.section} ${found.yearLevel ? `(${found.yearLevel})` : ''}`;
  }
  return 'Selected Class';
});

const allUniqueStudents = computed<(StudentRecord | User)[]>(() => {
  const map = new Map<string, StudentRecord | User>();

  studentRecords.value.forEach(rec => {
    const key = rec.studentId?.toLowerCase().trim() || rec.id;
    map.set(key, rec);
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

import { matchTeacherClassAssignment } from '../../utils/academicMasterData';

const scopedStudents = computed<(StudentRecord | User)[]>(() => {
  return allUniqueStudents.value.filter(st => {
    if (selectedClassKey.value === 'ALL') {
      return teacherAssignedClasses.value.some(cls => matchTeacherClassAssignment(cls, st));
    }

    const [reqCourse, reqSection, reqYear, reqDept] = selectedClassKey.value.split(':::');
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

const currentEventTickets = computed<Ticket[]>(() => {
  if (!currentEvent.value) return [];
  return tickets.value.filter(t => t.eventId === currentEvent.value?.id && t.ticketStatus !== 'CANCELLED');
});

const assignedStudentsPresent = computed(() => {
  const tixMapByStudentId = new Set<string>();
  const tixMapByUserId = new Set<string>();

  currentEventTickets.value.forEach(t => {
    if (t.attendanceStatus === 'present') {
      if (t.attendeeStudentId) tixMapByStudentId.add(t.attendeeStudentId.toLowerCase().trim());
      if (t.currentAttendeeId) tixMapByUserId.add(t.currentAttendeeId);
    }
  });

  return scopedStudents.value.filter(st => {
    const sId = st.studentId?.toLowerCase().trim();
    const uId = (st as any).userId || st.id;
    return (sId && tixMapByStudentId.has(sId)) || (uId && tixMapByUserId.has(uId));
  });
});

const assignedStudentsNotPresent = computed(() => {
  const presentSet = new Set(assignedStudentsPresent.value.map(s => s.id));
  return scopedStudents.value.filter(st => !presentSet.has(st.id));
});

const assignedAttendanceRate = computed(() => {
  if (scopedStudents.value.length === 0) return 0;
  return Math.round((assignedStudentsPresent.value.length / scopedStudents.value.length) * 100);
});

const onEventChange = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = events.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};

// Breakdown per assigned class
const classBreakdownList = computed(() => {
  const presentStudentIds = new Set(
    currentEventTickets.value
      .filter(t => t.attendanceStatus === 'present')
      .map(t => t.attendeeStudentId?.toLowerCase().trim())
      .filter(Boolean)
  );

  return teacherAssignedClasses.value.map(cls => {
    const classStudents = allUniqueStudents.value.filter(st => {
      return (
        (st.course || '').toUpperCase().trim() === cls.course.toUpperCase().trim() &&
        (st.section || '').toUpperCase().trim() === cls.section.toUpperCase().trim()
      );
    });

    const totalCount = classStudents.length;
    const presentCount = classStudents.filter(st => {
      const sId = st.studentId?.toLowerCase().trim();
      return sId && presentStudentIds.has(sId);
    }).length;
    const notPresentCount = Math.max(0, totalCount - presentCount);
    const rate = totalCount > 0 ? Math.round((presentCount / totalCount) * 100) : 0;

    return {
      ...cls,
      totalCount,
      presentCount,
      notPresentCount,
      rate,
    };
  });
});
</script>
