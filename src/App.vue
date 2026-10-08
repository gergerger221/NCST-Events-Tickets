<template>
  <!-- 1. Unauthenticated Login & Registration Screen -->
  <LoginView v-if="!currentUser" />

  <!-- 2. Authenticated Application Workspace -->
  <div v-else class="min-h-screen bg-slate-100 dark:bg-[#121212] text-slate-900 dark:text-slate-100 flex flex-col w-full transition-colors duration-200">
    <!-- Global Offline Resilience Banner -->
    <div
      v-if="!isOnline"
      class="w-full bg-amber-500 text-slate-950 px-4 py-2 text-xs font-black flex items-center justify-center gap-2 shadow-sm shrink-0 z-30"
    >
      <WifiOff class="w-4 h-4 shrink-0" />
      <span>Offline Mode — Scans and changes are preserved locally and will auto-sync with MySQL once reconnected.</span>
    </div>

    <!-- Main Navbar with Hamburger Toggle -->
    <Navbar @toggle-sidebar="handleToggleSidebar" />

    <!-- Dual Layout: Full Width Sidebar Navigation + Main Screen Content -->
    <div class="flex-1 flex w-full">
      <!-- Sidebar Navigation Dock -->
      <NavigationSidebar
        :active-tab="activeTab"
        :is-mobile-open="isMobileNavOpen"
        :is-desktop-collapsed="isDesktopCollapsed"
        @select-tab="activeTab = $event"
        @close-mobile="isMobileNavOpen = false"
        @toggle-collapse="isDesktopCollapsed = !isDesktopCollapsed"
        @open-qr-scanner="showQRScanner = true"
        @open-ocr-scanner="showOCRScanner = true"
      />

      <!-- Backdrop for mobile drawer -->
      <div
        v-if="isMobileNavOpen"
        @click="isMobileNavOpen = false"
        @touchmove.prevent
        class="fixed inset-0 bg-slate-900/60 dark:bg-black/75 backdrop-blur-sm z-40 md:hidden"
      />

      <!-- Main Content Area -->
      <div class="flex-1 min-w-0 flex flex-col w-full transition-all duration-300">
        <!-- Error Boundary Fallback View -->
        <div
          v-if="hasComponentError"
          class="flex-1 flex items-center justify-center p-6"
        >
          <div class="p-8 max-w-md w-full text-center bg-white dark:bg-[#1e1e1e] border border-rose-300 dark:border-rose-900 rounded-3xl shadow-xl space-y-4">
            <AlertTriangle class="w-12 h-12 text-rose-500 mx-auto" />
            <div>
              <h2 class="text-lg font-black text-slate-900 dark:text-white">Workspace Render Notice</h2>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                {{ componentErrorMessage || 'A transient error occurred in this view.' }}
              </p>
            </div>
            <button
              type="button"
              @click="handleRecoverFromError"
              class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-sm cursor-pointer"
            >
              Reload Workspace
            </button>
          </div>
        </div>

        <!-- Main Screen View -->
        <main v-else class="flex-1 px-4 sm:px-8 py-6 w-full">
          <TemporaryAccountView v-if="currentUser?.role === 'outsider' || isOutsiderUser(currentUser)" />
          <StaffDashboard v-else-if="activeTab === 'staff_dashboard'" @navigate="activeTab = $event" />
          <StaffScanLogsView v-else-if="activeTab === 'staff_logs'" @navigate="activeTab = $event" />
          <StaffTicketLookupView v-else-if="activeTab === 'staff_lookup'" @navigate="activeTab = $event" />
          <TeacherDashboard v-else-if="activeTab === 'teacher_dashboard'" @navigate="activeTab = $event" />
          <TeacherAssignedAttendanceView v-else-if="activeTab === 'teacher_attendance'" @navigate="activeTab = $event" />
          <StudentRosterImportView v-else-if="activeTab === 'student_roster_import'" />
          <StudentDashboard v-else-if="activeTab === 'student_dashboard'" @navigate="activeTab = $event" />
          <CashierDashboard v-else-if="activeTab === 'cashier_pos'" @navigate="activeTab = $event" />
          <QRPrintQueue v-else-if="activeTab === 'qr_print_queue'" />
          <EventManager v-else-if="activeTab === 'events'" @navigate="activeTab = $event" />
          <TicketManager v-else-if="activeTab === 'tickets'" />
          <UserManagement v-else-if="activeTab === 'users'" />
          <ReportsView v-else-if="activeTab === 'reports'" />
          <EventsCalendarView v-else-if="activeTab === 'calendar'" @navigate="activeTab = $event" />
          <RealTimeAttendanceView
            v-else-if="activeTab === 'attendance' || activeTab === 'attendance_archive'"
            :initial-tab="activeTab === 'attendance_archive' ? 'archive' : 'active'"
            @switch-tab="activeTab = $event"
          />
          <AdminDashboard v-else @navigate="activeTab = $event" />
        </main>
      </div>
    </div>

    <!-- Modals triggered from sidebar -->
    <QRScannerModal :is-open="showQRScanner" @close="showQRScanner = false" />
    <OCRScannerModal :is-open="showOCRScanner" @close="showOCRScanner = false" />

    <!-- Centered Upcoming Event Reminder for Students -->
    <UpcomingEventReminderModal
      v-if="currentUser?.role === 'student'"
      @view-event="handleReminderViewEvent"
    />

    <!-- Global Floating Toast Notification Container -->
    <ToastContainer />
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onErrorCaptured } from 'vue';
import { useAppStore } from './composables/useAppStore';
import { useOfflineScanner } from './composables/useOfflineScanner';
import { WifiOff, AlertTriangle } from 'lucide-vue-next';
import LoginView from './components/auth/LoginView.vue';
import Navbar from './components/Navbar.vue';
import NavigationSidebar from './components/NavigationSidebar.vue';
import AdminDashboard from './components/admin/AdminDashboard.vue';
import RealTimeAttendanceView from './components/admin/RealTimeAttendanceView.vue';
import CashierDashboard from './components/cashier/CashierDashboard.vue';
import QRPrintQueue from './components/cashier/QRPrintQueue.vue';
import EventManager from './components/admin/EventManager.vue';
import TicketManager from './components/admin/TicketManager.vue';
import UserManagement from './components/admin/UserManagement.vue';
import TeacherDashboard from './components/teacher/TeacherDashboard.vue';
import TeacherAssignedAttendanceView from './components/teacher/TeacherAssignedAttendanceView.vue';
import StaffDashboard from './components/staff/StaffDashboard.vue';
import StaffScanLogsView from './components/staff/StaffScanLogsView.vue';
import StaffTicketLookupView from './components/staff/StaffTicketLookupView.vue';
import StudentRosterImportView from './components/teacher/StudentRosterImportView.vue';
import StudentDashboard from './components/student/StudentDashboard.vue';
import ReportsView from './components/reports/ReportsView.vue';
import EventsCalendarView from './components/calendar/EventsCalendarView.vue';
import QRScannerModal from './components/scanner/QRScannerModal.vue';
import OCRScannerModal from './components/scanner/OCRScannerModal.vue';
import UpcomingEventReminderModal from './components/student/UpcomingEventReminderModal.vue';
import TemporaryAccountView from './components/temporary/TemporaryAccountView.vue';
import ToastContainer from './components/common/ToastContainer.vue';
import { isOutsiderUser } from './utils/philippineTime';

const { currentUser, currentEvent } = useAppStore();
const { isOnline } = useOfflineScanner();

const activeTab = ref('dashboard');
const isMobileNavOpen = ref(false);
const isDesktopCollapsed = ref(false);
const showQRScanner = ref(false);
const showOCRScanner = ref(false);

// Global Error Boundary
const hasComponentError = ref(false);
const componentErrorMessage = ref('');

onErrorCaptured((err) => {
  hasComponentError.value = true;
  componentErrorMessage.value = err instanceof Error ? err.message : String(err);
  return false;
});

const handleRecoverFromError = () => {
  hasComponentError.value = false;
  window.location.reload();
};

const handleReminderViewEvent = () => {
  activeTab.value = 'calendar';
};

const handleToggleSidebar = () => {
  if (window.innerWidth < 768) {
    isMobileNavOpen.value = !isMobileNavOpen.value;
  } else {
    isDesktopCollapsed.value = !isDesktopCollapsed.value;
  }
};

const defaultTab = computed(() => {
  if (!currentUser.value) return 'dashboard';
  switch (currentUser.value.role) {
    case 'event_staff': return 'staff_dashboard';
    case 'cashier': return 'cashier_pos';
    case 'teacher': return 'teacher_dashboard';
    case 'student': return 'calendar';
    default: return 'dashboard';
  }
});

watch(isMobileNavOpen, (isOpen) => {
  if (isOpen) {
    document.body.style.overflow = 'hidden';
  } else {
    document.body.style.overflow = '';
  }
});

// Direct URL Hash and Tab Access Guard (Role-Based Authorization Guard)
const roleAllowedTabs: Record<string, string[]> = {
  admin: ['dashboard', 'events', 'attendance', 'attendance_archive', 'tickets', 'users', 'reports', 'calendar'],
  cashier: ['cashier_pos', 'qr_print_queue', 'tickets', 'calendar'],
  event_staff: ['staff_dashboard', 'staff_logs', 'staff_lookup', 'calendar'],
  teacher: ['teacher_dashboard', 'teacher_attendance', 'student_roster_import', 'reports', 'calendar'],
  student: ['calendar', 'student_dashboard'],
};

const enforceRouteGuards = () => {
  const role = currentUser.value?.role || 'student';
  const allowed = roleAllowedTabs[role] || ['student_dashboard', 'calendar'];

  if (!allowed.includes(activeTab.value)) {
    activeTab.value = defaultTab.value;
  }
};

watch(
  () => [activeTab.value, currentUser.value?.role],
  () => {
    enforceRouteGuards();
  }
);

window.addEventListener('hashchange', enforceRouteGuards);

watch(
  () => currentUser.value?.role,
  (newRole, oldRole) => {
    if (!newRole || newRole === oldRole) return;
    if (newRole === 'event_staff') activeTab.value = 'staff_dashboard';
    else if (newRole === 'cashier') activeTab.value = 'cashier_pos';
    else if (newRole === 'teacher') activeTab.value = 'teacher_dashboard';
    else if (newRole === 'student') activeTab.value = 'student_dashboard';
    else activeTab.value = 'dashboard';
    enforceRouteGuards();
  },
  { immediate: true }
);
</script>
