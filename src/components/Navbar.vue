<template>
  <header class="sticky top-0 z-40 w-full bg-white/90 dark:bg-[#1e1e1e]/95 backdrop-blur-xl border-b border-slate-200 dark:border-[#333333] shadow-sm dark:shadow-md transition-colors duration-200">
    <div class="w-full px-4 sm:px-8">
      <div class="flex items-center justify-between h-16 gap-4">
        <!-- Left: Universal Hamburger Menu Button & App Logo -->
        <div class="flex items-center gap-3">
          <button
            @click="$emit('toggle-sidebar')"
            class="flex items-center justify-center p-2.5 text-slate-700 hover:text-slate-900 dark:text-slate-300 dark:hover:text-white bg-slate-100 hover:bg-slate-200/80 dark:bg-[#242424] dark:hover:bg-[#2c2c2c] rounded-xl border border-slate-200 dark:border-[#3a3a3a] shadow-sm transition-all cursor-pointer group"
            title="Toggle Navigation Menu (Sidebar)"
          >
            <Menu class="w-5 h-5 group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors" />
          </button>

          <div class="flex items-center gap-2.5">
            <img
              src="/ncst-logo.png"
              alt="NCST Seal"
              class="w-8 h-8 sm:w-9 sm:h-9 object-contain rounded-full shadow-xs bg-white p-0.5 shrink-0"
            />
            <div>
              <div class="flex items-center gap-2">
                <span class="font-black text-base sm:text-lg tracking-tight text-slate-900 dark:text-white">
                  NCST <span class="text-[#1E3A5F] dark:text-blue-400">Events & Tickets</span>
                </span>
              </div>
              <p class="text-[10px] text-slate-500 dark:text-[#b3b3b3] -mt-0.5 hidden sm:block font-medium">
                National College of Science & Technology
              </p>
            </div>
          </div>
        </div>

        <!-- Event Dropdown Picker (Hidden for Students, active for Admin/Cashier/Staff) -->
        <div
          v-if="showEventDropdown"
          class="hidden lg:flex items-center gap-2 bg-slate-100 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl px-3 py-1.5 max-w-sm"
        >
          <Calendar class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 flex-shrink-0" />
          <select
            :value="currentEvent?.id || ''"
            @change="handleEventSelect"
            :disabled="!events || events.length === 0"
            class="bg-transparent text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer truncate disabled:cursor-not-allowed disabled:text-slate-400 dark:disabled:text-[#777777]"
          >
            <option
              v-if="!events || events.length === 0"
              value=""
              disabled
              selected
              class="bg-white dark:bg-[#1e1e1e] text-slate-500 dark:text-[#888888]"
            >
              No Events
            </option>
            <option
              v-for="ev in events"
              :key="ev.id"
              :value="ev.id"
              class="bg-white dark:bg-[#1e1e1e] text-slate-900 dark:text-white"
            >
              {{ ev.title }}
            </option>
          </select>
        </div>

        <!-- Academic Period Quick Switcher / Indicator (Admin Navigation) -->
        <button
          v-if="currentUser?.role === 'admin' && activeAcademicPeriod"
          @click="showAcademicPeriodModal = true"
          class="hidden sm:flex items-center gap-2 bg-slate-100/90 dark:bg-[#242424]/90 hover:bg-blue-50/90 dark:hover:bg-blue-950/40 border border-slate-200/80 dark:border-[#333333] hover:border-blue-400 dark:hover:border-blue-500 rounded-xl px-3 py-1.5 transition-all cursor-pointer text-xs group"
          title="Authoritative Academic Period: Click to manage and switch active semester"
        >
          <GraduationCap class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 group-hover:scale-110 transition-transform shrink-0" />
          <div class="flex flex-col items-start leading-tight text-left">
            <span class="text-[8.5px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider">Academic Period</span>
            <span class="font-bold text-slate-800 dark:text-slate-200 truncate max-w-[140px]">
              {{ activeAcademicPeriod.academicYear }} • {{ activeAcademicPeriod.semester }}
            </span>
          </div>
          <span class="flex items-center gap-1 px-1.5 py-0.5 rounded-md text-[9px] font-black uppercase tracking-wider bg-blue-600 text-white group-hover:bg-blue-700 transition-colors shadow-2xs">
            Manage
            <SlidersHorizontal class="w-2.5 h-2.5" />
          </span>
        </button>

        <!-- Live Clock Navbar Indicator (PHT) -->
        <div class="hidden md:flex items-center gap-1.5 bg-slate-100/90 dark:bg-[#242424]/90 border border-slate-200/80 dark:border-[#333333] rounded-xl px-3 py-1 text-xs">
          <Clock class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400 shrink-0" />
          <span class="font-mono font-bold text-slate-800 dark:text-slate-200">{{ timeString }}</span>
          <span class="text-[10px] font-bold text-slate-400 uppercase">PHT</span>
        </div>

        <!-- Right: Actions, Theme Switcher & Account Switcher -->
        <div class="flex items-center gap-2 sm:gap-2.5">
          <!-- Theme Switcher in Navbar -->
          <ThemeToggle variant="compact" />

          <!-- Quick QR Scanner for Event Staff only -->
          <div v-if="canScan" class="flex items-center gap-1.5">
            <button
              @click="showQRScanner = true"
              class="flex items-center gap-2 px-3 py-2 bg-[#1E3A5F] hover:bg-[#152A45] text-white text-xs font-bold rounded-xl shadow-xs transition-all cursor-pointer"
              title="Scan Attendee QR Ticket"
            >
              <QrCode class="w-4 h-4 text-white flex-shrink-0" />
              <span class="font-bold">Scan <span class="hidden sm:inline">Ticket</span></span>
            </button>
          </div>

          <!-- Profile Chip & Menu -->
          <button
            v-if="currentUser"
            @click="showProfileModal = true"
            class="flex items-center gap-2.5 pl-2.5 pr-2 py-1.5 border-l border-slate-200 dark:border-[#333333] hover:bg-slate-100 dark:hover:bg-[#242424] rounded-xl transition cursor-pointer"
            title="View Account Profile"
          >
            <UserAvatar :src="currentUser.photoUrl" :alt="currentUser.name" size="sm" shape="circle" class="shrink-0" />
            <div class="hidden md:flex flex-col items-start justify-center text-left leading-tight gap-1">
              <span class="text-xs font-bold text-slate-900 dark:text-white truncate max-w-[140px] block">
                {{ currentUser.name }}
              </span>
              <StatusBadge type="role" :status="currentUser.role" size="sm" />
            </div>
          </button>
        </div>
      </div>
    </div>
  </header>

  <!-- QR Scanner Modal -->
  <QRScannerModal :is-open="showQRScanner" @close="showQRScanner = false" />

  <!-- OCR Scanner Modal -->
  <OCRScannerModal :is-open="showOCRScanner" @close="showOCRScanner = false" />

  <!-- Mobile Connect Modal -->
  <MobileConnectModal :is-open="showMobileConnectModal" @close="showMobileConnectModal = false" />

  <!-- User Profile & Sign Out Modal -->
  <UserProfileModal :is-open="showProfileModal" @close="showProfileModal = false" />

  <!-- Academic Period Management Modal (Admin Navigation) -->
  <AcademicPeriodModal
    :is-open="showAcademicPeriodModal"
    @close="showAcademicPeriodModal = false"
  />
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Calendar,
  FileText,
  Menu,
  QrCode,
  Smartphone,
  Clock,
  GraduationCap,
  SlidersHorizontal
} from 'lucide-vue-next';
import { useAppStore } from '../composables/useAppStore';
import { usePhilippineClock } from '../utils/philippineTime';
import StatusBadge from './common/StatusBadge.vue';
import UserAvatar from './common/UserAvatar.vue';
import ThemeToggle from './common/ThemeToggle.vue';
import QRScannerModal from './scanner/QRScannerModal.vue';
import OCRScannerModal from './scanner/OCRScannerModal.vue';
import MobileConnectModal from './common/MobileConnectModal.vue';
import UserProfileModal from './UserProfileModal.vue';
import AcademicPeriodModal from './admin/AcademicPeriodModal.vue';

defineEmits<{
  (e: 'toggle-sidebar'): void;
}>();

const { currentUser, events, currentEvent, setCurrentEvent, activeAcademicPeriod } = useAppStore();
const { timeString } = usePhilippineClock();

const showQRScanner = ref(false);
const showOCRScanner = ref(false);
const showMobileConnectModal = ref(false);
const showProfileModal = ref(false);
const showAcademicPeriodModal = ref(false);

const canScan = computed(() => currentUser.value && currentUser.value.role === 'event_staff');
const showEventDropdown = computed(() => currentUser.value && currentUser.value.role !== 'student' && currentUser.value.role !== 'cashier');

const handleEventSelect = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = events.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};
</script>
