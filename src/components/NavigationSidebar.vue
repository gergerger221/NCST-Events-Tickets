<template>
  <aside
    class="bg-white/95 dark:bg-[#181818]/95 backdrop-blur-xl border-r border-slate-200/80 dark:border-[#2a2a2a] flex flex-col justify-between shrink-0 transition-all duration-300 z-30 select-none shadow-sm dark:shadow-none"
    :class="[
      isMobileOpen
        ? 'fixed inset-y-0 left-0 w-72 max-w-[85vw] h-full shadow-2xl flex overscroll-contain z-50 rounded-r-3xl border-r border-slate-200 dark:border-[#333]'
        : isDesktopCollapsed
        ? 'sticky top-16 h-[calc(100vh-4rem)] w-[72px] hidden md:flex items-center'
        : 'sticky top-16 h-[calc(100vh-4rem)] w-64 hidden md:flex'
    ]"
  >
    <!-- ==================== DESKTOP COLLAPSED / ICON RAIL MODE ==================== -->
    <div v-if="isDesktopCollapsed && !isMobileOpen" class="flex flex-col h-full w-full py-4 items-center justify-between">
      <!-- Center Nav Icons with Floating Tooltips -->
      <div class="flex flex-col items-center gap-2 w-full px-2 flex-1 overflow-y-auto py-2 custom-scrollbar">
        <div
          v-for="item in flatNavItems"
          :key="item.id"
          class="relative group w-full flex justify-center"
        >
          <button
            @click="selectTab(item.id)"
            class="w-11 h-11 rounded-2xl flex items-center justify-center transition-all cursor-pointer relative"
            :class="[
              activeTab === item.id
                ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/30 scale-105'
                : 'text-slate-500 dark:text-[#a0a0a0] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#262626]'
            ]"
          >
            <component :is="item.icon" class="w-5 h-5" />

            <!-- Mini Notification Dot for Badges -->
            <span
              v-if="item.badge !== undefined && item.badge > 0"
              class="absolute top-1.5 right-1.5 w-2.5 h-2.5 bg-blue-500 rounded-full ring-2 ring-white dark:ring-[#181818]"
            />
          </button>

          <!-- Floating Tooltip on Hover -->
          <div
            class="fixed left-[76px] ml-2 px-3 py-1.5 bg-slate-900 dark:bg-[#2a2a2a] text-white text-xs font-semibold rounded-xl shadow-xl border border-slate-700/50 dark:border-[#3a3a3a] opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50 whitespace-nowrap flex items-center gap-2"
          >
            <span>{{ item.label }}</span>
            <span
              v-if="item.badge !== undefined"
              class="px-1.5 py-0.2 bg-blue-500/30 text-blue-300 text-[10px] font-mono rounded"
            >
              {{ item.badge }}
            </span>
          </div>
        </div>
      </div>

      <!-- Bottom Mini Status & Expand Button -->
      <div class="flex flex-col items-center gap-2 pt-2 border-t border-slate-200 dark:border-[#2c2c2c] w-full px-2">
        <!-- Mini Live Event Indicator (hidden for Student role) -->
        <div v-if="currentUser?.role !== 'student'" class="relative group flex justify-center w-full">
          <div
            class="w-9 h-9 rounded-xl flex items-center justify-center cursor-help transition-colors"
            :class="currentEvent ? 'bg-blue-500/10 border border-blue-500/20 text-blue-600 dark:text-blue-400' : 'bg-slate-100 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] text-slate-400'"
          >
            <Calendar class="w-4 h-4" />
          </div>
          <!-- Tooltip with Event Info -->
          <div
            class="fixed left-[76px] bottom-14 ml-2 px-3 py-2 bg-slate-900 dark:bg-[#2a2a2a] text-white text-xs rounded-xl shadow-xl border border-slate-700/50 dark:border-[#3a3a3a] opacity-0 pointer-events-none group-hover:opacity-100 group-hover:pointer-events-auto transition-all duration-150 z-50 whitespace-nowrap"
          >
            <template v-if="currentEvent">
              <p class="font-bold">{{ currentEvent.title }}</p>
              <p class="text-[11px] text-slate-400">{{ currentEvent.code }} • {{ currentEvent.venue }}</p>
            </template>
            <template v-else>
              <p class="font-bold">No Events</p>
              <p class="text-[11px] text-slate-400">No active campus event</p>
            </template>
          </div>
        </div>

        <!-- Sidebar Expand Button -->
        <button
          @click="$emit('toggle-collapse')"
          class="w-10 h-10 rounded-xl flex items-center justify-center text-slate-400 hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#262626] transition cursor-pointer"
          title="Expand Sidebar"
        >
          <ChevronRight class="w-5 h-5" />
        </button>
      </div>
    </div>

    <!-- ==================== EXPANDED SIDEBAR / MOBILE DRAWER ==================== -->
    <div v-else class="flex flex-col h-full w-full justify-between overflow-hidden">
      <!-- 1. Header & Navigation Links Grouped by Section (Requirement 9) -->
      <div class="flex-1 overflow-y-auto px-3 py-4 space-y-4 custom-scrollbar">
        <!-- Top Title Bar -->
        <div class="flex items-center justify-between px-2 pb-1">
          <span class="text-[10px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider">
            {{ roleSectionTitle }}
          </span>

          <!-- Mobile Close Button -->
          <button
            v-if="isMobileOpen"
            @click="$emit('close-mobile')"
            class="p-1 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#262626] rounded-lg transition md:hidden cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>

          <!-- Desktop Collapse Button -->
          <button
            v-else
            @click="$emit('toggle-collapse')"
            class="hidden md:flex p-1 text-slate-400 hover:text-slate-700 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#262626] rounded-lg transition cursor-pointer"
            title="Collapse to mini rail"
          >
            <ChevronLeft class="w-3.5 h-3.5" />
          </button>
        </div>

        <!-- Categorized Nav Groups -->
        <div v-for="group in navGroups" :key="group.title" class="space-y-1">
          <div v-if="group.title" class="px-2 pt-2 pb-1">
            <span class="text-[9px] uppercase font-black text-slate-400 dark:text-[#666666] tracking-widest">
              {{ group.title }}
            </span>
          </div>

          <!-- Nav Items in Group -->
          <button
            v-for="item in group.items"
            :key="item.id"
            @click="selectTab(item.id)"
            class="w-full flex items-center justify-between px-3 py-2 rounded-xl text-xs font-bold transition-all duration-150 cursor-pointer text-left group"
            :class="[
              activeTab === item.id
                ? 'bg-blue-600 text-white shadow-md shadow-blue-500/25 font-extrabold'
                : 'text-slate-600 dark:text-[#a6a6a6] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#242424]'
            ]"
          >
            <div class="flex items-center gap-2.5 min-w-0">
              <component
                :is="item.icon"
                class="w-4 h-4 flex-shrink-0 transition-transform duration-150 group-hover:scale-105"
                :class="activeTab === item.id ? 'text-white' : 'text-slate-500 dark:text-[#888888] group-hover:text-blue-600 dark:group-hover:text-blue-400'"
              />
              <span class="truncate">{{ item.label }}</span>
            </div>

            <!-- Badge Count -->
            <span
              v-if="item.badge !== undefined"
              class="text-[10px] font-mono font-bold px-1.5 py-0.2 rounded-full transition-colors"
              :class="[
                activeTab === item.id
                  ? 'bg-white/25 text-white'
                  : 'bg-slate-200/80 dark:bg-[#2c2c2c] text-slate-600 dark:text-[#a0a0a0] group-hover:bg-blue-100 group-hover:text-blue-700 dark:group-hover:bg-blue-900/40 dark:group-hover:text-blue-300'
              ]"
            >
              {{ item.badge }}
            </span>
          </button>
        </div>
      </div>

      <!-- 2. Bottom Live Event Widget & Collapse Bar (hidden for Student role) -->
      <div v-if="currentUser?.role !== 'student'" class="p-3 border-t border-slate-200/80 dark:border-[#2a2a2a] bg-slate-50/50 dark:bg-[#1c1c1c]/50">
        <!-- Active Live Event Card -->
        <div v-if="currentEvent" class="p-3 bg-white dark:bg-[#222222] border border-slate-200/80 dark:border-[#2e2e2e] rounded-2xl text-xs space-y-1.5 shadow-xs">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-1.5">
              <span class="text-[10px] text-slate-500 dark:text-[#a0a0a0] uppercase font-bold tracking-wider">
                Live Event
              </span>
            </div>
            <span v-if="currentEvent.code" class="text-blue-600 dark:text-blue-400 font-mono text-[10px] font-bold px-1.5 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30 border border-blue-200/50 dark:border-blue-800/40">
              {{ currentEvent.code }}
            </span>
          </div>

          <p class="font-extrabold text-slate-900 dark:text-white truncate text-xs">
            {{ currentEvent.title }}
          </p>

          <div v-if="currentEvent.venue" class="flex items-center gap-1.5 text-[11px] text-slate-500 dark:text-[#999999] truncate">
            <MapPin class="w-3 h-3 flex-shrink-0 text-slate-400" />
            <span class="truncate">{{ currentEvent.venue }}</span>
          </div>
        </div>

        <!-- Empty State Card when there is No Event -->
        <div v-else class="p-3 bg-white dark:bg-[#222222] border border-slate-200/80 dark:border-[#2e2e2e] rounded-2xl text-xs space-y-1 shadow-xs">
          <div class="flex items-center gap-1.5 text-slate-400 dark:text-slate-500">
            <span class="w-2 h-2 rounded-full bg-slate-300 dark:bg-slate-600"></span>
            <span class="text-[10px] uppercase font-bold tracking-wider">
              Live Event
            </span>
          </div>
          <p class="font-extrabold text-slate-700 dark:text-slate-300 text-xs">
            No Events
          </p>
        </div>
      </div>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import {
  LayoutDashboard,
  Calendar,
  CalendarDays,
  Ticket as TicketIcon,
  MapPin,
  Users,
  FileSpreadsheet,
  GraduationCap,
  QrCode,
  CreditCard,
  Printer,
  Tag,
  X,
  ChevronLeft,
  ChevronRight,
  Activity,
  Archive,
  Clock,
  Search
} from 'lucide-vue-next';
import { useAppStore } from '../composables/useAppStore';

withDefaults(
  defineProps<{
    activeTab: string;
    isMobileOpen: boolean;
    isDesktopCollapsed?: boolean;
  }>(),
  {
    isDesktopCollapsed: false,
  }
);

const emit = defineEmits<{
  (e: 'select-tab', tabId: string): void;
  (e: 'close-mobile'): void;
  (e: 'toggle-collapse'): void;
}>();

const { currentUser, currentEvent, events, activeEvents, archivedEvents, tickets, pendingQrTickets, logs, users } = useAppStore();

const liveAttendanceCount = computed(() => {
  if (!currentEvent.value) return 0;
  return tickets.value.filter(t => t.eventId === currentEvent.value?.id && t.attendanceStatus === 'present').length;
});

const teacherAssignedClasses = computed(() => {
  return currentUser.value?.assignedClasses || [];
});

const teacherAssignedStudents = computed(() => {
  return users.value.filter(u => {
    if (u.role !== 'student') return false;
    const uCourse = (u.course || '').trim().toLowerCase();
    const uSection = (u.section || '').trim().toUpperCase();
    return teacherAssignedClasses.value.some(
      ac => ac.course.trim().toLowerCase() === uCourse && ac.section.trim().toUpperCase() === uSection
    );
  });
});

const teacherLivePresentCount = computed(() => {
  if (!currentEvent.value) return 0;
  const assignedIds = new Set(teacherAssignedStudents.value.map(s => s.studentId).filter(Boolean));
  const assignedUserIds = new Set(teacherAssignedStudents.value.map(s => s.id));
  return tickets.value.filter(t => {
    if (t.eventId !== currentEvent.value?.id || t.attendanceStatus !== 'present') return false;
    if (t.attendeeType === 'OUTSIDER') return false;
    return (t.attendeeStudentId && assignedIds.has(t.attendeeStudentId)) || assignedUserIds.has(t.currentAttendeeId);
  }).length;
});

const staffEventLogsCount = computed(() => {
  if (!currentEvent.value) return 0;
  return logs.value.filter(l => l.eventId === currentEvent.value?.id).length;
});

const roleSectionTitle = computed(() => {
  switch (currentUser.value?.role) {
    case 'admin': return 'Administrator Portal';
    case 'teacher': return 'Faculty Portal';
    case 'cashier': return 'Cashier & Ticket Staff';
    case 'event_staff': return 'Event Staff Portal';
    case 'student': return 'Student Portal';
    default: return 'Navigation';
  }
});

interface NavItem {
  id: string;
  label: string;
  icon: any;
  badge?: number;
}

interface NavGroup {
  title?: string;
  items: NavItem[];
}

// Role-Based Navigation Groups (Section 14: Visual Sidebar Structure & Requirement 23: UI Labels)
const navGroups = computed<NavGroup[]>(() => {
  switch (currentUser.value?.role) {
    case 'admin':
      return [
        {
          title: 'OVERVIEW',
          items: [
            { id: 'dashboard', label: 'Admin Dashboard', icon: LayoutDashboard },
          ]
        },
        {
          title: 'EVENT MANAGEMENT',
          items: [
            { id: 'events', label: 'Events', icon: CalendarDays, badge: events.value.length },
          ]
        },
        {
          title: 'ATTENDANCE',
          items: [
            { id: 'attendance', label: 'All Attendance', icon: Activity, badge: liveAttendanceCount.value },
            { id: 'attendance_archive', label: 'Archive', icon: Archive, badge: archivedEvents.value.length },
          ]
        },
        {
          title: 'TICKETING',
          items: [
            { id: 'tickets', label: 'Ticket Registry', icon: TicketIcon, badge: tickets.value.length },
          ]
        },
        {
          title: 'PEOPLE',
          items: [
            { id: 'users', label: 'User Accounts', icon: users.value.length > 0 ? Users : Users, badge: users.value.length },
          ]
        },
        {
          title: 'REPORTS',
          items: [
            { id: 'reports', label: 'Reports & Excel Export', icon: FileSpreadsheet, badge: logs.value.length },
          ]
        }
      ];

    case 'cashier':
      return [
        {
          title: 'TICKETING',
          items: [
            { id: 'cashier_pos', label: 'Ticket POS', icon: CreditCard },
            { id: 'qr_print_queue', label: 'QR Print Queue', icon: Tag, badge: pendingQrTickets.value.length },
            { id: 'tickets', label: 'Ticket Registry', icon: TicketIcon, badge: tickets.value.length },
          ]
        },
        {
          title: 'EVENTS',
          items: [
            { id: 'calendar', label: 'Event Schedule', icon: CalendarDays, badge: events.value.length },
          ]
        }
      ];

    case 'event_staff':
      return [
        {
          title: 'EVENT OPERATIONS',
          items: [
            { id: 'staff_dashboard', label: 'Entrance Scanner', icon: QrCode },
            { id: 'staff_logs', label: 'Recent Scans', icon: Clock, badge: staffEventLogsCount.value },
            { id: 'staff_lookup', label: 'Ticket Lookup', icon: Search },
            { id: 'calendar', label: 'Event Schedule', icon: CalendarDays, badge: events.value.length },
          ]
        }
      ];

    case 'teacher':
      return [
        {
          title: 'ACADEMIC ATTENDANCE',
          items: [
            { id: 'teacher_dashboard', label: 'Faculty Dashboard', icon: LayoutDashboard, badge: teacherLivePresentCount.value },
            { id: 'teacher_attendance', label: "Assigned Students' Attendance", icon: GraduationCap, badge: teacherAssignedStudents.value.length },
            { id: 'student_roster_import', label: 'Import Class Roster (.xlsx)', icon: FileSpreadsheet },
            { id: 'reports', label: 'Attendance Reports & Export', icon: FileSpreadsheet },
            { id: 'calendar', label: 'Event Schedule', icon: CalendarDays, badge: events.value.length },
          ]
        }
      ];

    case 'student':
      return [
        {
          title: 'MY EVENTS',
          items: [
            {
              id: 'calendar',
              label: 'Events Calendar',
              icon: CalendarDays,
              badge: events.value.length
            },
            {
              id: 'student_dashboard',
              label: 'My Passes & Attendance',
              icon: QrCode,
              badge: tickets.value.filter(t => t.currentAttendeeId === currentUser.value?.id || (currentUser.value?.studentId && t.attendeeStudentId === currentUser.value?.studentId)).length
            },
          ]
        }
      ];

    default:
      return [
        {
          items: [
            { id: 'dashboard', label: 'Dashboard', icon: LayoutDashboard }
          ]
        }
      ];
  }
});

// Flat items for collapsed rail mode
const flatNavItems = computed<NavItem[]>(() => {
  return navGroups.value.flatMap(g => g.items);
});

const selectTab = (tabId: string) => {
  emit('select-tab', tabId);
  emit('close-mobile');
};
</script>
