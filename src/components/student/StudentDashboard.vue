<template>
  <!-- 1. EXCLUSIVE MANDATORY FIRST-LOGIN SETUP VIEW (Active until password is saved) -->
  <div v-if="currentUser?.mustChangePassword" class="max-w-xl mx-auto py-6 sm:py-10 px-2 sm:px-4">
    <div class="bg-white dark:bg-[#1e1e1e] border border-amber-200/90 dark:border-amber-800/80 rounded-3xl p-6 sm:p-8 shadow-xl space-y-6">
      <div class="flex items-center gap-4">
        <div class="p-3 bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-2xl shadow-xs">
          <KeyRound class="w-7 h-7" />
        </div>
        <div>
          <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300 border border-amber-300 dark:border-amber-700">
            First-Login Activation Required
          </span>
          <h2 class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">
            Set Your Permanent Password
          </h2>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Welcome, <strong>{{ currentUser.name }}</strong> (Student ID: {{ currentUser.studentId }})
          </p>
        </div>
      </div>

      <div class="p-4 bg-amber-50/80 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-2xl text-xs text-amber-800 dark:text-amber-300 space-y-1.5 leading-relaxed">
        <p class="font-bold flex items-center gap-1.5 text-amber-900 dark:text-amber-200">
          <KeyRound class="w-4 h-4 text-amber-600 dark:text-amber-400" />
          Mandatory Password Change
        </p>
        <p class="text-[11px] text-amber-700 dark:text-amber-300">
          Your account was automatically created during ticket purchase using your <strong>official surname</strong> as your initial temporary password.
        </p>
        <p class="text-[11px] text-amber-700 dark:text-amber-300">
          To protect your digital passes and attendance records, you must create a secure permanent password before accessing portal features.
        </p>
      </div>

      <div v-if="passError" class="p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs">
        {{ passError }}
      </div>
      <div v-if="passSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-700 dark:text-emerald-300 text-xs font-bold">
        {{ passSuccess }}
      </div>

      <form @submit.prevent="handleChangePassword" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">New Permanent Password *</label>
          <div class="relative">
            <input
              v-model="newPassword"
              :type="showNewPassword ? 'text' : 'password'"
              required
              minlength="4"
              placeholder="Enter new permanent password"
              class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#181818] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
            />
            <button
              type="button"
              @click="showNewPassword = !showNewPassword"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
              aria-label="Toggle password visibility"
            >
              <EyeOff v-if="showNewPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
          <div class="relative">
            <input
              v-model="confirmNewPassword"
              :type="showConfirmPassword ? 'text' : 'password'"
              required
              minlength="4"
              placeholder="Confirm new permanent password"
              class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#181818] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
            />
            <button
              type="button"
              @click="showConfirmPassword = !showConfirmPassword"
              class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
              aria-label="Toggle password visibility"
            >
              <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
        </div>

        <button
          type="submit"
          :disabled="!newPassword.trim() || !confirmNewPassword.trim()"
          class="w-full py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer shadow-md disabled:opacity-50"
        >
          Save Password & Activate Portal Access
        </button>
      </form>
    </div>
  </div>

  <!-- 2. FULL STUDENT DASHBOARD (Rendered once account is activated) -->
  <div v-else class="space-y-6 sm:space-y-8">
    <!-- Student Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-slate-50/80 dark:bg-[#1e1e1e] border border-slate-200/70 dark:border-[#333333] p-5 sm:p-6 shadow-xs dark:shadow-md transition-colors">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-5">
        <div class="flex items-center gap-4 sm:gap-4.5">
          <UserAvatar :src="currentUser?.photoUrl" :alt="currentUser?.name" size="xl" shape="rounded" />
          <div class="space-y-0.5">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-100/80 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-300/60 dark:border-emerald-500/30">
                Official Student Account
              </span>
              <span class="text-xs text-blue-600 dark:text-blue-400 font-mono font-bold">
                Student ID: {{ currentUser?.studentId || '2026-00125' }}
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">
              {{ currentUser?.name }}
            </h1>
            <p class="text-xs text-slate-600 dark:text-[#b3b3b3] font-medium flex items-center gap-1.5 flex-wrap">
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ currentUser?.course || 'BSIT' }}</span>
              <span>•</span>
              <span>{{ currentUser?.yearLevel || '3rd Year' }}</span>
              <span>•</span>
              <span>Section {{ currentUser?.section || '11M1' }}</span>
              <span>•</span>
              <span class="font-mono text-slate-500 dark:text-[#888888]">{{ currentUser?.email }}</span>
            </p>
            <div class="pt-0.5 flex items-center gap-1.5 text-[11px] text-slate-400 dark:text-slate-500">
              <Clock class="w-3.5 h-3.5 text-blue-500" />
              <span>Attendance status updated: <strong class="text-slate-600 dark:text-slate-400 font-mono">{{ lastUpdatedTime }} (PHT)</strong></span>
            </div>
          </div>
        </div>

        <!-- Metric Badges -->
        <div class="flex items-center gap-2.5 flex-wrap">
          <div class="p-3 bg-white dark:bg-[#242424] border border-slate-200/70 dark:border-[#333333] rounded-2xl text-center min-w-[105px] shadow-xs">
            <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block tracking-wider">Issued Passes</span>
            <span class="text-xl font-bold text-[#1E3A5F] dark:text-blue-400">{{ myTickets.length }}</span>
          </div>
          <div class="p-3 bg-white dark:bg-[#242424] border border-slate-200/70 dark:border-[#333333] rounded-2xl text-center min-w-[105px] shadow-xs">
            <span class="text-[10px] uppercase font-bold text-slate-400 dark:text-slate-500 block tracking-wider">Attended</span>
            <span class="text-xl font-bold text-[#15803D] dark:text-emerald-400">{{ attendedTickets.length }}</span>
          </div>
        </div>
      </div>
    </div>

    <!-- Temporary Password Security Notice -->
    <div
      v-if="currentUser?.mustChangePassword"
      class="p-4 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800 rounded-3xl flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs shadow-xs"
    >
      <div class="flex items-center gap-3">
        <div class="p-2.5 bg-amber-100 dark:bg-amber-900/60 text-amber-700 dark:text-amber-300 rounded-2xl">
          <KeyRound class="w-5 h-5" />
        </div>
        <div>
          <h4 class="font-bold text-amber-900 dark:text-amber-200 text-xs">First-Time Login Security Activation</h4>
          <p class="text-[11px] text-amber-700 dark:text-amber-300 mt-0.5">
            You signed in using an initial temporary password. Please set a permanent password to secure your account.
          </p>
        </div>
      </div>
      <button
        @click="isChangePassModalOpen = true"
        class="px-4 py-2 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold transition whitespace-nowrap cursor-pointer"
      >
        Set Permanent Password
      </button>
    </div>

    <!-- Navigation Tabs -->
    <div class="flex items-center justify-between gap-4 border-b border-slate-200 dark:border-[#333333] pb-3 overflow-x-auto">
      <div class="flex items-center gap-2">
        <button
          v-for="tab in tabs"
          :key="tab.id"
          @click="activeSubTab = tab.id"
          class="flex items-center gap-2 px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap"
          :class="activeSubTab === tab.id 
            ? 'bg-[#1E3A5F] text-white shadow-xs font-bold' 
            : 'bg-white dark:bg-[#1e1e1e] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white border border-slate-200 dark:border-[#333333]'"
        >
          <component :is="tab.icon" class="w-4 h-4" />
          <span>{{ tab.label }}</span>
          <span
            v-if="tab.badge !== undefined"
            class="text-[10px] font-mono px-2 py-0.5 rounded-full"
            :class="activeSubTab === tab.id ? 'bg-white/20 text-white' : 'bg-slate-100 dark:bg-[#2c2c2c] text-slate-600 dark:text-[#b3b3b3]'"
          >
            {{ tab.badge }}
          </span>
        </button>
      </div>

      <!-- Quick Action to Browse Events -->
      <button
        @click="emit('navigate', 'calendar')"
        class="hidden sm:flex items-center gap-1.5 px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#242424] dark:hover:bg-[#2c2c2c] text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-[#333333] transition cursor-pointer"
      >
        <CalendarDays class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
        <span>Events Calendar &rarr;</span>
      </button>
    </div>

    <!-- TAB 1: My Digital Passes & QR Wallet -->
    <div v-if="activeSubTab === 'wallet'" class="space-y-5">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div>
          <h2 class="text-base sm:text-lg font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <QrCode class="w-4.5 h-4.5 text-blue-600 dark:text-blue-400" />
            <span>My Digital Passes & QR Passes</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-0.5">
            Officially issued event admission tickets. Present your high-contrast QR code to Event Staff at the venue entrance.
          </p>
        </div>

        <!-- Filter Pill for tickets -->
        <div class="flex items-center gap-1 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-1 rounded-xl text-xs shadow-xs self-start sm:self-auto">
          <button
            v-for="f in (['all', 'active', 'used'] as const)"
            :key="f"
            @click="ticketFilter = f"
            class="px-2.5 py-1 rounded-lg font-bold capitalize transition cursor-pointer text-xs"
            :class="ticketFilter === f 
              ? 'bg-blue-600 text-white shadow-xs' 
              : 'text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white'"
          >
            {{ f === 'active' ? 'Issued' : f }}
          </button>
        </div>
      </div>

      <!-- Digital Ticket Cards Grid -->
      <div v-if="filteredMyTickets.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <DigitalTicketCard
          v-for="t in filteredMyTickets"
          :key="t.id"
          :ticket="t"
          :event="getEventForTicket(t.eventId)"
        />
      </div>

      <!-- Empty State -->
      <div v-else class="py-8 px-6 sm:py-10 sm:px-8 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200/80 dark:border-[#333333] text-center space-y-3.5 shadow-xs">
        <div class="w-12 h-12 mx-auto rounded-2xl bg-blue-50 dark:bg-blue-900/20 border border-blue-100 dark:border-blue-800/30 flex items-center justify-center text-blue-600 dark:text-blue-400">
          <TicketIcon class="w-6 h-6" />
        </div>
        <div class="space-y-1 max-w-sm mx-auto">
          <h3 class="text-sm sm:text-base font-extrabold text-slate-900 dark:text-white">
            You do not have any official event passes yet.
          </h3>
          <p class="text-xs text-slate-500 dark:text-[#b3b3b3] leading-relaxed">
            Official tickets and digital passes are issued through the NCST Cashier Desk. Browse upcoming campus events on the calendar.
          </p>
        </div>
        <button
          @click="emit('navigate', 'calendar')"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer inline-flex items-center gap-2"
        >
          <CalendarDays class="w-3.5 h-3.5" />
          <span>Explore Events</span>
        </button>
      </div>
    </div>

    <!-- TAB 2: Attendance History -->
    <div v-else-if="activeSubTab === 'history'" class="space-y-6">
      <div class="flex items-center justify-between">
        <div>
          <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
            <Receipt class="w-5 h-5 text-blue-600 dark:text-blue-400" />
            <span>Personal Attendance History</span>
          </h2>
          <p class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-0.5">
            Verified entrance attendance records for your officially issued event passes.
          </p>
        </div>
      </div>

      <!-- Attendance Table -->
      <div class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl overflow-hidden shadow-sm dark:shadow-md">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase font-mono text-[10px] tracking-wider border-b border-slate-200 dark:border-[#333333]">
              <tr>
                <th class="py-3.5 px-4 font-bold">Event Name</th>
                <th class="py-3.5 px-4 font-bold">Venue</th>
                <th class="py-3.5 px-4 font-bold">Event Date</th>
                <th class="py-3.5 px-4 font-bold">Ticket Number</th>
                <th class="py-3.5 px-4 font-bold">Attendance Status</th>
                <th class="py-3.5 px-4 font-bold">Recorded Time</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c] font-medium">
              <tr 
                v-for="rec in myAttendanceRecords" 
                :key="rec.id" 
                class="hover:bg-slate-50/80 dark:hover:bg-[#242424]/80 transition"
              >
                <!-- Event Name -->
                <td class="py-3.5 px-4 font-extrabold text-slate-900 dark:text-white max-w-[200px] truncate">
                  {{ rec.eventName }}
                </td>

                <!-- Venue -->
                <td class="py-3.5 px-4 text-slate-600 dark:text-slate-300">
                  <span class="flex items-center gap-1.5">
                    <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                    <span class="truncate">{{ rec.eventVenue }}</span>
                  </span>
                </td>

                <!-- Event Date -->
                <td class="py-3.5 px-4 font-semibold text-slate-700 dark:text-slate-300">
                  {{ formatPhilippineDate(rec.eventDate) }}
                </td>

                <!-- Ticket Number -->
                <td class="py-3.5 px-4 font-mono font-bold text-blue-600 dark:text-blue-400">
                  {{ rec.ticketNumber }}
                </td>

                <!-- Attendance Status -->
                <td class="py-3.5 px-4">
                  <StatusBadge type="attendance" :status="rec.attendanceStatus" />
                </td>

                <!-- Recorded Scan Time -->
                <td class="py-3.5 px-4 font-mono text-xs">
                  <span v-if="rec.timeInFormatted" class="font-bold text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5">
                    <Clock class="w-3.5 h-3.5" />
                    Entered: {{ rec.timeInFormatted }}
                  </span>
                  <span v-else class="text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-500"></span>
                    NOT YET ENTERED
                  </span>
                </td>
              </tr>

              <!-- Empty Table State -->
              <tr v-if="myAttendanceRecords.length === 0">
                <td colspan="6" class="text-center py-8 px-4 text-slate-400 dark:text-slate-500 space-y-1.5">
                  <Receipt class="w-7 h-7 mx-auto opacity-40" />
                  <p class="text-xs font-semibold">No attendance records have been recorded yet.</p>
                  <p class="text-[11px] text-slate-400">Once your official pass is scanned at the event entrance, your attendance record will appear here.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- Pass Policy & Help Section -->
    <div class="p-4 rounded-2xl bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200/50 dark:border-blue-800/40 text-xs shadow-xs">
      <h4 class="font-extrabold text-blue-900 dark:text-blue-200 text-xs uppercase tracking-wider mb-1 flex items-center gap-1.5">
        <Info class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
        How Your Event Pass Works
      </h4>
      <p class="text-slate-600 dark:text-slate-300 text-xs leading-relaxed">
        Your event pass is officially issued through the NCST ticketing process. Present the QR code on your pass to authorized Event Staff for attendance verification. Your pass is personal and non-transferable.
      </p>
    </div>

    <!-- Mandatory First-Time Password Setup / Change Modal -->
    <Modal
      :is-open="isChangePassModalOpen || !!currentUser?.mustChangePassword"
      @close="!currentUser?.mustChangePassword ? (isChangePassModalOpen = false) : null"
      title="Welcome to NCST Student Portal"
      subtitle="Please set your secure permanent password to continue."
      max-width="md"
    >
      <div class="space-y-4">
        <!-- Notice banner -->
        <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800 rounded-xl text-xs text-amber-800 dark:text-amber-300 space-y-1">
          <p class="font-bold flex items-center gap-1.5">
            <KeyRound class="w-4 h-4 text-amber-600 dark:text-amber-400" />
            Mandatory First-Login Password Setup
          </p>
          <p class="text-[11px] text-amber-700 dark:text-amber-400 leading-relaxed">
            Your account was initialized using your official surname as your temporary password. Please create your secure permanent password to access your digital passes and attendance records.
          </p>
        </div>

        <div v-if="passError" class="p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs">
          {{ passError }}
        </div>
        <div v-if="passSuccess" class="p-3 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-800 rounded-xl text-emerald-700 dark:text-emerald-300 text-xs font-bold">
          {{ passSuccess }}
        </div>

        <form @submit.prevent="handleChangePassword" class="space-y-3.5">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">New Permanent Password *</label>
            <div class="relative">
              <input
                v-model="newPassword"
                :type="showNewPassword ? 'text' : 'password'"
                required
                placeholder="••••••••"
                class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
              />
              <button
                type="button"
                @click="showNewPassword = !showNewPassword"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
                aria-label="Toggle password visibility"
              >
                <EyeOff v-if="showNewPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">Confirm New Password *</label>
            <div class="relative">
              <input
                v-model="confirmNewPassword"
                :type="showConfirmPassword ? 'text' : 'password'"
                required
                placeholder="••••••••"
                class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
              />
              <button
                type="button"
                @click="showConfirmPassword = !showConfirmPassword"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
                aria-label="Toggle password visibility"
              >
                <EyeOff v-if="showConfirmPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>
          <div class="flex items-center justify-end gap-2 pt-3 border-t border-slate-200 dark:border-[#333333]">
            <button
              v-if="!currentUser?.mustChangePassword"
              type="button"
              @click="isChangePassModalOpen = false"
              class="px-4 py-2.5 bg-slate-100 dark:bg-[#2c2c2c] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-200 dark:hover:bg-[#383838] transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="!newPassword.trim() || !confirmNewPassword.trim()"
              class="w-full sm:w-auto px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition cursor-pointer shadow-md disabled:opacity-50"
            >
              Set Permanent Password & Enter Portal
            </button>
          </div>
        </form>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  QrCode,
  CalendarDays,
  MapPin,
  Clock,
  Receipt,
  Info,
  Ticket as TicketIcon,
  KeyRound,
  Eye,
  EyeOff
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem } from '../../types';
import StatusBadge from '../common/StatusBadge.vue';
import UserAvatar from '../common/UserAvatar.vue';
import DigitalTicketCard from '../tickets/DigitalTicketCard.vue';
import Modal from '../common/Modal.vue';
import { formatPhilippineDate, formatPhilippineTime, formatPhilippineFull } from '../../utils/philippineTime';

const emit = defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const lastUpdatedTime = computed(() => formatPhilippineTime(new Date()));

const { currentUser, tickets, events, logs, updateUserPassword } = useAppStore();

const isChangePassModalOpen = ref(false);
const newPassword = ref('');
const confirmNewPassword = ref('');
const showNewPassword = ref(false);
const showConfirmPassword = ref(false);
const passError = ref('');
const passSuccess = ref('');

const handleChangePassword = () => {
  passError.value = '';
  passSuccess.value = '';
  if (!currentUser.value) return;

  const userSurname = (currentUser.value.lastName || (currentUser.value.name ? currentUser.value.name.trim().split(/\s+/).pop() : '') || '').toLowerCase();
  if (userSurname && newPassword.value.trim().toLowerCase() === userSurname) {
    passError.value = 'New password cannot be your surname. Please choose a secure custom password.';
    return;
  }
  if (newPassword.value === 'ncst123' || newPassword.value === 'password123') {
    passError.value = 'New password cannot be a generic default password. Please choose a new secure password.';
    return;
  }
  if (newPassword.value !== confirmNewPassword.value) {
    passError.value = 'Passwords do not match.';
    return;
  }
  if (newPassword.value.length < 4) {
    passError.value = 'Password must be at least 4 characters long.';
    return;
  }

  const res = updateUserPassword(currentUser.value.id, newPassword.value);
  if (res.success) {
    passSuccess.value = 'Password updated successfully! Welcome to your Student Portal.';
    newPassword.value = '';
    confirmNewPassword.value = '';
    setTimeout(() => {
      isChangePassModalOpen.value = false;
      passSuccess.value = '';
    }, 1200);
  } else {
    passError.value = res.message || 'Failed to update password.';
  }
};

const activeSubTab = ref<'wallet' | 'history'>('wallet');
const ticketFilter = ref<'all' | 'active' | 'used'>('all');

// Student's own officially issued tickets (Securely derived from authenticated session)
const myTickets = computed(() => {
  if (!currentUser.value) return [];
  const currentId = currentUser.value.id;
  const currentStudentId = currentUser.value.studentId?.trim().toLowerCase();
  return tickets.value.filter(t => {
    if (t.currentAttendeeId === currentId || t.originalOwnerId === currentId) return true;
    if (currentStudentId && t.attendeeStudentId && t.attendeeStudentId.trim().toLowerCase() === currentStudentId) return true;
    return false;
  });
});

const activeTickets = computed(() => 
  myTickets.value.filter(t => (t.ticketStatus === 'active' || t.ticketStatus === 'ISSUED') && t.attendanceStatus !== 'present')
);

const attendedTickets = computed(() => 
  myTickets.value.filter(t => t.attendanceStatus === 'present')
);

const filteredMyTickets = computed(() => {
  if (ticketFilter.value === 'all') return myTickets.value;
  if (ticketFilter.value === 'active') {
    return myTickets.value.filter(t => (t.ticketStatus === 'active' || t.ticketStatus === 'ISSUED') && t.attendanceStatus !== 'present');
  }
  if (ticketFilter.value === 'used') {
    return myTickets.value.filter(t => t.attendanceStatus === 'present' || t.ticketStatus === 'used' || t.ticketStatus === 'USED');
  }
  return myTickets.value.filter(t => t.ticketStatus === ticketFilter.value);
});

const tabs = computed(() => [
  { id: 'wallet' as const, label: 'My Digital Passes & QR', icon: QrCode, badge: myTickets.value.length },
  { id: 'history' as const, label: 'Attendance History', icon: Receipt, badge: myAttendanceRecords.value.length },
]);

const getEventForTicket = (eventId: string): EventItem => {
  return events.value.find(e => e.id === eventId) || {
    id: eventId,
    title: 'School Event',
    code: 'EVT-SCH',
    description: '',
    category: 'Institutional',
    venue: 'Campus Main Grounds',
    date: '2026-09-29',
    startTime: '08:00 AM',
    endTime: '05:00 PM',
    capacity: 1000,
    ticketPrice: 0,
    status: 'ongoing',
    bannerUrl: '',
    organizer: 'Student Affairs'
  };
};

// Student's own attendance history records
const myAttendanceRecords = computed(() => {
  if (!currentUser.value) return [];
  const currentId = currentUser.value.id;
  const currentStudentId = currentUser.value.studentId;

  return myTickets.value.map(ticket => {
    const event = getEventForTicket(ticket.eventId);
    // Find matching attendance log
    const log = logs.value.find(l => 
      (l.ticketId === ticket.id || (ticket.ticketNumber && l.ticketNumber === ticket.ticketNumber)) &&
      l.status === 'valid'
    );
    const isPresent = ticket.attendanceStatus === 'present' || !!log;
    const recordedTimestamp = ticket.timeIn || log?.timestamp;

    return {
      id: ticket.id,
      ticketNumber: ticket.ticketNumber || ticket.id,
      eventId: ticket.eventId,
      eventName: event?.title || ticket.eventTitle || 'Campus Event',
      eventDate: event?.date || ticket.purchaseDate,
      eventVenue: event?.venue || 'Campus Grounds',
      ticketStatus: ticket.ticketStatus,
      attendanceStatus: isPresent ? 'present' : 'not_entered',
      recordedTime: recordedTimestamp ? formatPhilippineFull(recordedTimestamp) : null,
      timeInFormatted: recordedTimestamp ? formatPhilippineTime(recordedTimestamp) : null,
    };
  });
});
</script>
