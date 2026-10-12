<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- ==================== 0. MODULE NAVIGATION TABS (ACTIVE vs ARCHIVE) ==================== -->
    <div class="flex items-center justify-between gap-4 p-1.5 bg-slate-200/80 dark:bg-[#1a1a1a] rounded-2xl border border-slate-300/60 dark:border-[#2e2e2e]">
      <div class="flex items-center gap-1.5 w-full sm:w-auto">
        <!-- Tab 1: Active Attendance -->
        <button
          @click="setSubTab('active')"
          class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-black transition-all cursor-pointer select-none"
          :class="[
            activeSubTab === 'active'
              ? 'bg-white dark:bg-[#282828] text-slate-900 dark:text-white shadow-sm shadow-black/5 dark:shadow-black/20'
              : 'text-slate-600 dark:text-[#a0a0a0] hover:text-slate-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-[#222222]'
          ]"
        >
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500"></span>
          </span>
          <span>Active Attendance</span>
          <span
            class="px-1.5 py-0.5 rounded-md text-[10px] font-extrabold"
            :class="activeSubTab === 'active' ? 'bg-emerald-100 dark:bg-emerald-950/80 text-emerald-700 dark:text-emerald-300' : 'bg-slate-300/60 dark:bg-[#333] text-slate-600 dark:text-slate-400'"
          >
            {{ activePresentCount }}
          </span>
        </button>

        <!-- Tab 2: Attendance Archive -->
        <button
          @click="setSubTab('archive')"
          class="flex-1 sm:flex-initial flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl text-xs font-bold transition-all cursor-pointer select-none"
          :class="[
            activeSubTab === 'archive'
              ? 'bg-white dark:bg-[#282828] text-slate-900 dark:text-white shadow-sm shadow-black/5 dark:shadow-black/20'
              : 'text-slate-600 dark:text-[#a0a0a0] hover:text-slate-900 dark:hover:text-white hover:bg-white/40 dark:hover:bg-[#222222]'
          ]"
        >
          <Archive class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>Attendance Archive</span>
          <span
            class="px-1.5 py-0.5 rounded-md text-[10px] font-bold"
            :class="activeSubTab === 'archive' ? 'bg-slate-200 dark:bg-[#383838] text-slate-900 dark:text-white' : 'bg-slate-300/60 dark:bg-[#333] text-slate-600 dark:text-slate-400'"
          >
            {{ archivedEvents.length }}
          </span>
        </button>
      </div>

      <!-- Quick Summary Sub-label on larger screens -->
      <div class="hidden md:flex items-center gap-2 px-3 text-[11px] font-bold text-slate-500 dark:text-[#888888]">
        <span v-if="activeSubTab === 'active'">Live operational check-ins</span>
        <span v-else>Historical completed event records</span>
      </div>
    </div>

    <!-- ==================== TAB CONTENT: ARCHIVE VIEW ==================== -->
    <AttendanceArchiveView
      v-if="activeSubTab === 'archive'"
      :initial-event-id="archiveTargetEventId"
      @switch-to-active="setSubTab('active')"
    />

    <!-- ==================== TAB CONTENT: ACTIVE ATTENDANCE VIEW ==================== -->
    <div v-else class="space-y-6">
      <!-- ==================== 1. PAGE HEADER & EVENT CONTEXT ==================== -->
      <div class="rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs transition-colors">
        <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
          <!-- Title & Event Context -->
          <div class="space-y-1.5 min-w-0">
            <div class="flex items-center gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
                <span>Active Attendance</span>
              </h1>
            </div>

            <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
              Live operational monitoring and entrance check-ins for active campus events
            </p>

            <!-- Current Selected Event Detail Strip -->
            <div v-if="currentEvent" class="flex flex-wrap items-center gap-x-4 gap-y-1 text-xs text-slate-700 dark:text-slate-300 pt-1 font-semibold">
              <span class="text-blue-600 dark:text-blue-400 font-extrabold">
                {{ currentEvent.title }}
              </span>
              <span class="text-slate-400 dark:text-slate-600">•</span>
              <span class="flex items-center gap-1 font-medium text-slate-600 dark:text-[#a0a0a0]">
                <CalendarDays class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                {{ currentEvent.date ? formatPhilippineDate(currentEvent.date) : '---' }}
              </span>
              <span class="text-slate-400 dark:text-slate-600">•</span>
              <span class="flex items-center gap-1 font-medium text-slate-600 dark:text-[#a0a0a0]">
                <MapPin class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                {{ currentEvent.venue || 'NCST Campus' }}
              </span>
            </div>
            <div v-else class="text-xs text-amber-600 dark:text-amber-400 font-semibold pt-1">
              No active event selected. Please select an active event below.
            </div>
          </div>

          <!-- Event Selector & Actions -->
          <div class="flex items-center gap-2.5 shrink-0 pt-2 lg:pt-0 border-t lg:border-t-0 border-slate-100 dark:border-[#2a2a2a]">
            <!-- Switch Active Event Dropdown (Excludes Archived/Completed) -->
            <div class="w-48 sm:w-56">
              <select
                :value="currentEvent?.id"
                @change="handleSelectEvent"
                class="w-full px-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option v-if="activeEvents.length === 0" value="" disabled>No active events</option>
                <option v-for="ev in activeEvents" :key="ev.id" :value="ev.id">
                  {{ ev.title }} ({{ ev.status === 'ongoing' ? 'LIVE' : 'Upcoming' }})
                </option>
              </select>
            </div>

            <button
              @click="handleExportExcel"
              class="flex items-center gap-1.5 px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
              title="Export Active Attendance to Excel"
            >
              <FileSpreadsheet class="w-4 h-4" />
              <span class="hidden sm:inline">Export Excel</span>
            </button>

            <!-- Complete & Move Event to Archive Button -->
            <button
              v-if="currentEvent"
              @click="handleArchiveCurrentEvent"
              class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#252525] dark:hover:bg-[#2c2c2c] text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold shadow-xs transition cursor-pointer"
              title="Mark event as completed and move records to Attendance Archive"
            >
              <Archive class="w-4 h-4 text-slate-500 dark:text-slate-400" />
              <span class="hidden sm:inline">Archive Event</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Empty State when no active events remain -->
      <div v-if="activeEvents.length === 0" class="p-8 sm:p-12 text-center rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs space-y-4">
        <div class="w-16 h-16 rounded-2xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333] text-slate-600 dark:text-slate-400 flex items-center justify-center mx-auto shadow-sm">
          <Archive class="w-8 h-8" />
        </div>
        <div class="space-y-1.5 max-w-md mx-auto">
          <h3 class="text-base sm:text-lg font-bold text-slate-900 dark:text-white">
            All Campus Events Are Completed
          </h3>
          <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
            There are currently no active or ongoing events. All completed event attendance records, scan logs, and rosters have been moved to the Attendance Archive.
          </p>
        </div>
        <button
          @click="setSubTab('archive')"
          class="btn-primary inline-flex items-center gap-2"
        >
          <Archive class="w-4 h-4" />
          <span>View Attendance Archive</span>
        </button>
      </div>

      <!-- Active Event Details (Visible when active event exists) -->
      <template v-else>
    <!-- ==================== 2. REAL-TIME ATTENDANCE STATISTICS ==================== -->
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3 sm:gap-4">
      <!-- 1. Total Attendance -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-[#E2E8F0] dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#0F172A] dark:text-white block">
            Total Attendance
          </span>
          <div class="flex items-baseline justify-between mt-2">
            <div class="flex items-baseline gap-2">
              <p class="text-2xl sm:text-3xl font-black text-[#1E3A5F] dark:text-blue-400">
                {{ presentCount }}
              </p>
              <span class="text-xs font-bold text-[#64748B] dark:text-slate-400">
                {{ attendanceRate }}%
              </span>
            </div>
            <UserCheck class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 shrink-0" />
          </div>
        </div>
        <p class="text-xs text-[#64748B] dark:text-slate-400 mt-2 truncate">
          Validated entry passes
        </p>
      </div>

      <!-- 2. Students Present -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-[#E2E8F0] dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#0F172A] dark:text-white block">
            Students Present
          </span>
          <div class="flex items-baseline justify-between mt-2">
            <p class="text-2xl sm:text-3xl font-black text-[#1E3A5F] dark:text-blue-400">
              {{ studentsPresentCount }}
            </p>
            <GraduationCap class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 shrink-0" />
          </div>
        </div>
        <p class="text-xs text-[#64748B] dark:text-slate-400 mt-2 truncate">
          Enrolled NCST students
        </p>
      </div>

      <!-- 3. Outsiders Present -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-[#E2E8F0] dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#0F172A] dark:text-white block">
            Outsiders Present
          </span>
          <div class="flex items-baseline justify-between mt-2">
            <p class="text-2xl sm:text-3xl font-black text-[#1E3A5F] dark:text-blue-400">
              {{ outsidersPresentCount }}
            </p>
            <Users class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 shrink-0" />
          </div>
        </div>
        <p class="text-xs text-[#64748B] dark:text-slate-400 mt-2 truncate">
          Guest & visitor check-ins
        </p>
      </div>

      <!-- 4. Not Yet Recorded -->
      <div class="p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-[#E2E8F0] dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
        <div>
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#0F172A] dark:text-white block">
            Not Yet Recorded
          </span>
          <div class="flex items-baseline justify-between mt-2">
            <p class="text-2xl sm:text-3xl font-black text-[#1E3A5F] dark:text-blue-400">
              {{ notEnteredCount }}
            </p>
            <Clock class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 shrink-0" />
          </div>
        </div>
        <p class="text-xs text-[#64748B] dark:text-slate-400 mt-2 truncate">
          Issued tickets pending entry
        </p>
      </div>

      <!-- 5. Invalid Scans / Blocked (Clickable) -->
      <button
        type="button"
        @click="showInvalidModal = true"
        class="w-full text-left p-4 sm:p-5 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-[#E2E8F0] dark:border-[#2e2e2e] hover:border-slate-300 dark:hover:border-slate-700 shadow-xs flex flex-col justify-between transition cursor-pointer group"
      >
        <div class="w-full">
          <span class="text-[11px] font-bold uppercase tracking-wider text-[#0F172A] dark:text-white block">
            Invalid / Blocked
          </span>
          <div class="flex items-baseline justify-between mt-2">
            <p class="text-2xl sm:text-3xl font-black text-[#1E3A5F] dark:text-blue-400">
              {{ invalidAttemptsCount + duplicateAttemptsCount }}
            </p>
            <AlertTriangle class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400 shrink-0 group-hover:scale-110 transition-transform" />
          </div>
        </div>
        <p class="text-xs text-[#64748B] dark:text-slate-400 mt-2 group-hover:text-[#1E3A5F] dark:group-hover:text-blue-400 transition-colors inline-flex items-center gap-1 truncate">
          <span>Click to inspect audit logs</span>
          <span aria-hidden="true">→</span>
        </p>
      </button>
    </div>

    <!-- ==================== 3. SEARCH & ATTENDANCE FILTERS ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-5 sm:p-6 shadow-xs space-y-4 transition-colors">
      <!-- Search & Filters Toolbar (Always Open for Admin) -->
      <div class="space-y-3">
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-2.5">
          <!-- Search input -->
          <div class="relative flex-1">
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
            <input
              type="text"
              v-model="searchQuery"
              placeholder="Search by student name, ID number, or ticket code..."
              class="w-full pl-9 pr-3 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
            />
          </div>

          <!-- Reset button (visible when filters/search active) -->
          <div class="flex items-center gap-2 self-end sm:self-auto">
            <button
              v-if="hasActiveFilters"
              @click="resetFilters"
              class="text-xs font-bold text-slate-600 hover:text-rose-500 dark:text-slate-400 dark:hover:text-rose-400 transition cursor-pointer flex items-center gap-1.5 px-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl shadow-2xs"
              title="Reset all filters"
            >
              <RotateCcw class="w-3.5 h-3.5" />
              <span>Reset Filters</span>
            </button>
          </div>
        </div>

        <!-- Always-Open Filters Grid -->
        <div
          class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-2.5 p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] transition-all"
        >
          <!-- 1. Attendee Type -->
          <div>
            <label class="block text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mb-1">Attendee Type</label>
            <select
              v-model="filterType"
              class="w-full px-2.5 py-1.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Types</option>
              <option value="STUDENT">Students Only</option>
              <option value="OUTSIDER">Outsiders Only</option>
            </select>
          </div>

          <!-- 2. Course -->
          <div>
            <label class="block text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mb-1">Course</label>
            <select
              v-model="filterCourse"
              class="w-full px-2.5 py-1.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Courses</option>
              <option v-for="c in availableCourses" :key="c" :value="c">{{ c }}</option>
            </select>
          </div>

          <!-- 3. Year Level -->
          <div>
            <label class="block text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mb-1">Year Level</label>
            <select
              v-model="filterYearLevel"
              class="w-full px-2.5 py-1.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Years</option>
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
            </select>
          </div>

          <!-- 4. Section -->
          <div>
            <label class="block text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mb-1">Section</label>
            <select
              v-model="filterSection"
              class="w-full px-2.5 py-1.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Sections</option>
              <option v-for="sec in availableSections" :key="sec" :value="sec">Section {{ sec }}</option>
            </select>
          </div>

          <!-- 5. Status -->
          <div>
            <label class="block text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 mb-1">Status</label>
            <select
              v-model="filterStatus"
              class="w-full px-2.5 py-1.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#3a3a3a] rounded-lg text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="all">All Statuses</option>
              <option value="present">Present</option>
              <option value="not_entered">Not Yet Recorded</option>
              <option value="already_used">Already Used</option>
              <option value="invalid">Invalid / Blocked</option>
            </select>
          </div>
        </div>
      </div>

      <!-- ==================== 4. FULL ATTENDANCE TABLE ==================== -->
      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#2e2e2e]">
        <table class="w-full text-left text-xs border-collapse">
          <thead>
            <tr class="bg-slate-50 dark:bg-[#222222] text-slate-600 dark:text-[#a0a0a0] uppercase text-[10px] font-extrabold border-b border-slate-200 dark:border-[#2e2e2e] tracking-wider">
              <th class="py-3 px-3.5">Time</th>
              <th class="py-3 px-3.5">Attendee</th>
              <th class="py-3 px-3.5">ID / Ticket</th>
              <th class="py-3 px-3.5">Type</th>
              <th class="py-3 px-3.5">Course</th>
              <th class="py-3 px-3.5">Year</th>
              <th class="py-3 px-3.5">Section</th>
              <th class="py-3 px-3.5">Status</th>
              <th class="py-3 px-3.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
            <tr
              v-for="item in filteredLiveAttendance"
              :key="item.id"
              @click="openAttendeeDetails(item)"
              class="hover:bg-slate-50 dark:hover:bg-[#222222] transition cursor-pointer group"
            >
              <!-- 1. Time -->
              <td class="py-3 px-3.5 font-mono font-bold text-slate-900 dark:text-white whitespace-nowrap">
                {{ item.timeFormatted }}
              </td>

              <!-- 2. Attendee Name -->
              <td class="py-3 px-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                <div class="flex items-center gap-2">
                  <span class="truncate max-w-[170px]">{{ item.name }}</span>
                </div>
              </td>

              <!-- 3. ID / Ticket Number -->
              <td class="py-3 px-3.5 font-mono text-slate-600 dark:text-slate-400 whitespace-nowrap">
                <span v-if="item.studentId" class="text-blue-600 dark:text-blue-400 font-bold">
                  {{ item.studentId }}
                </span>
                <span v-else class="text-slate-500 dark:text-slate-400">
                  {{ item.ticketNumber }}
                </span>
              </td>

              <!-- 4. Type Badge -->
              <td class="py-3 px-3.5 whitespace-nowrap">
                <span
                  class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider inline-block"
                  :class="item.attendeeType === 'OUTSIDER'
                    ? 'bg-slate-100 dark:bg-[#252525] text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-[#3a3a3a]'
                    : 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60'"
                >
                  {{ item.attendeeType }}
                </span>
              </td>

              <!-- 5. Course -->
              <td class="py-3 px-3.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                <span v-if="item.attendeeType === 'STUDENT' && item.course" class="font-semibold">
                  {{ item.course }}
                </span>
                <span v-else class="text-slate-400 dark:text-slate-600">—</span>
              </td>

              <!-- 6. Year -->
              <td class="py-3 px-3.5 text-slate-600 dark:text-[#a0a0a0] whitespace-nowrap">
                <span v-if="item.attendeeType === 'STUDENT' && item.yearLevel">
                  {{ item.yearLevel }}
                </span>
                <span v-else class="text-slate-400 dark:text-slate-600">—</span>
              </td>

              <!-- 7. Section -->
              <td class="py-3 px-3.5 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                <span v-if="item.attendeeType === 'STUDENT' && item.section" class="font-semibold">
                  {{ item.section }}
                </span>
                <span v-else class="text-slate-400 dark:text-slate-600">—</span>
              </td>

              <!-- 8. Status Badge -->
              <td class="py-3 px-3.5 whitespace-nowrap">
                <StatusBadge
                  type="attendance"
                  :status="getAttendanceBadgeStatus(item.status)"
                />
              </td>

              <!-- 9. Inspection Details Button -->
              <td class="py-3 px-3.5 text-right whitespace-nowrap">
                <button
                  type="button"
                  @click.stop="openAttendeeDetails(item)"
                  class="p-1 text-slate-400 group-hover:text-blue-600 dark:group-hover:text-blue-400 rounded-lg hover:bg-slate-100 dark:hover:bg-[#2c2c2c] transition"
                  title="Inspect Full Details"
                >
                  <Eye class="w-3.5 h-3.5" />
                </button>
              </td>
            </tr>

            <tr v-if="filteredLiveAttendance.length === 0">
              <td colspan="9" class="text-center py-12 text-slate-400 dark:text-slate-500">
                <Clock class="w-8 h-8 mx-auto mb-2 opacity-40" />
                <p class="font-bold">No real-time attendance records found matching filters.</p>
                <p class="text-[11px] text-slate-500">Live ticket scans from Event Staff will automatically appear here.</p>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
    </template>

    <!-- ==================== 5. MODALS (PROGRESSIVE DISCLOSURE) ==================== -->

    <!-- Attendee Details Inspection Modal -->
    <Modal
      :is-open="!!selectedAttendee"
      @close="selectedAttendee = null"
      title="Attendee Attendance Record"
      subtitle="Complete verified check-in details, scanner staff, and ticket metadata"
      max-width="md"
    >
      <div v-if="selectedAttendee" class="space-y-4">
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] space-y-3">
          <div class="flex items-center justify-between">
            <div>
              <h4 class="text-base font-extrabold text-slate-900 dark:text-white">{{ selectedAttendee.name }}</h4>
              <span
                class="px-2 py-0.5 rounded text-[10px] font-black uppercase tracking-wider inline-block mt-1"
                :class="selectedAttendee.attendeeType === 'OUTSIDER'
                  ? 'bg-slate-100 dark:bg-[#252525] text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-[#3a3a3a]'
                  : 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800/60'"
              >
                {{ selectedAttendee.attendeeType }}
              </span>
            </div>
            <span
              class="px-2.5 py-1 rounded text-xs font-black uppercase"
              :class="selectedAttendee.status === 'PRESENT' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800 dark:bg-rose-950 dark:text-rose-300'"
            >
              {{ selectedAttendee.status }}
            </span>
          </div>

          <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-[#333333] text-xs">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Student ID / Reference</span>
              <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
                {{ selectedAttendee.studentId || selectedAttendee.ticketNumber || 'N/A' }}
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Ticket Serial</span>
              <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                {{ selectedAttendee.ticketNumber || selectedAttendee.ticketId }}
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Course & Section</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200">
                {{ selectedAttendee.course ? `${selectedAttendee.course} • ${selectedAttendee.yearLevel || ''} • Sec ${selectedAttendee.section || ''}` : 'General Guest' }}
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Event Venue</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200 truncate block">
                {{ currentEvent?.venue || 'Campus Venue' }}
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Scan Timestamp</span>
              <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                {{ selectedAttendee.timeFormatted }}
              </span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Scanned By Staff</span>
              <span class="font-semibold text-slate-800 dark:text-slate-200">
                {{ selectedAttendee.scannedBy || 'Event Staff Scanner' }}
              </span>
            </div>
          </div>
        </div>

        <div class="flex justify-end">
          <button
            @click="selectedAttendee = null"
            class="px-4 py-2 bg-slate-100 dark:bg-[#282828] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-200 dark:hover:bg-[#333333] transition cursor-pointer"
          >
            Close
          </button>
        </div>
      </div>
    </Modal>

    <!-- Invalid & Blocked Scans Audit Modal -->
    <Modal
      :is-open="showInvalidModal"
      @close="showInvalidModal = false"
      title="Blocked & Invalid Scan Audit Logs"
      subtitle="Chronological record of duplicate, wrong-event, or void scan attempts"
      max-width="2xl"
    >
      <div class="space-y-4">
        <div class="max-h-80 overflow-y-auto rounded-2xl border border-slate-200 dark:border-[#333333]">
          <table class="w-full text-left text-xs border-collapse">
            <thead class="sticky top-0 bg-slate-100 dark:bg-[#252525] text-[10px] font-black uppercase text-slate-500 tracking-wider">
              <tr>
                <th class="py-2.5 px-3">Time</th>
                <th class="py-2.5 px-3">Ticket / Attendee</th>
                <th class="py-2.5 px-3">Verified By</th>
                <th class="py-2.5 px-3">Reason / Block Detail</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
              <tr
                v-for="log in deniedLogs"
                :key="log.id"
                class="hover:bg-slate-50 dark:hover:bg-[#222222]"
              >
                <td class="py-2.5 px-3 font-mono font-bold text-slate-800 dark:text-slate-200 whitespace-nowrap">
                  {{ log.timeIn || new Date(log.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) }}
                </td>
                <td class="py-2.5 px-3 font-bold text-slate-900 dark:text-white">
                  {{ log.attendeeName }}
                  <span class="block text-[10px] font-mono text-slate-400">{{ log.ticketNumber || log.ticketId }}</span>
                </td>
                <td class="py-2.5 px-3 text-slate-600 dark:text-slate-400">
                  {{ log.verifiedByName || 'Event Staff' }}
                </td>
                <td class="py-2.5 px-3 text-rose-600 dark:text-rose-400 font-bold">
                  {{ log.denialReason || 'Access Denied' }}
                </td>
              </tr>
              <tr v-if="deniedLogs.length === 0">
                <td colspan="4" class="text-center py-6 text-slate-400">
                  No invalid or duplicate scan attempts recorded for this event.
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex justify-end">
          <button
            @click="showInvalidModal = false"
            class="px-4 py-2 bg-slate-100 dark:bg-[#282828] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold hover:bg-slate-200 dark:hover:bg-[#333333] transition cursor-pointer"
          >
            Close Audit Logs
          </button>
        </div>
      </div>
    </Modal>

    <!-- Modern Event Archive Confirmation Modal -->
    <Modal
      :is-open="showArchiveConfirmModal"
      @close="showArchiveConfirmModal = false"
      title="Archive Completed Event"
      subtitle="Transition live operational records to permanent Historical Archive"
      max-width="md"
    >
      <div v-if="currentEvent" class="space-y-4">
        <div class="flex items-start gap-3.5 p-4 rounded-2xl bg-indigo-50/80 dark:bg-indigo-950/40 border border-indigo-200 dark:border-indigo-800/60">
          <div class="w-10 h-10 rounded-xl bg-indigo-600 text-white flex items-center justify-center shrink-0 shadow-md shadow-indigo-600/20">
            <Archive class="w-5 h-5" />
          </div>
          <div class="space-y-1 min-w-0">
            <h4 class="text-sm font-extrabold text-slate-900 dark:text-white truncate">
              Mark "{{ currentEvent.title }}" as Completed?
            </h4>
            <p class="text-xs text-slate-600 dark:text-slate-300 leading-relaxed">
              Are you sure you want to mark this event as <strong class="text-indigo-600 dark:text-indigo-400 font-bold">COMPLETED</strong> and move it to the Attendance Archive?
            </p>
          </div>
        </div>

        <!-- Event Summary Stats Preview -->
        <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] space-y-2 text-xs">
          <div class="flex items-center justify-between font-semibold">
            <span class="text-slate-500 dark:text-slate-400">Total Valid Attendance:</span>
            <span class="font-mono font-bold text-slate-900 dark:text-white">{{ presentCount }} attendees</span>
          </div>
          <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400">
            <span>Breakdown:</span>
            <span class="font-medium text-slate-700 dark:text-slate-300">{{ studentsPresentCount }} Students • {{ outsidersPresentCount }} Outsiders</span>
          </div>
          <div class="pt-2 border-t border-slate-200 dark:border-[#2e2e2e] text-[11px] text-emerald-600 dark:text-emerald-400 flex items-center gap-1.5 font-semibold">
            <span>🔒 All student, outsider, and scan logs will be preserved permanently in the Archive.</span>
          </div>
        </div>

        <!-- Confirmation Actions -->
        <div class="flex items-center justify-end gap-2 pt-2">
          <button
            type="button"
            @click="showArchiveConfirmModal = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#282828] dark:hover:bg-[#333333] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="confirmArchiveCurrentEvent"
            class="flex items-center gap-1.5 px-4 py-2 bg-indigo-600 hover:bg-indigo-700 text-white rounded-xl text-xs font-bold shadow-md shadow-indigo-600/20 transition cursor-pointer"
          >
            <Archive class="w-4 h-4" />
            <span>Confirm & Move to Archive</span>
          </button>
        </div>
      </div>
    </Modal>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
  MapPin, CalendarDays, FileSpreadsheet, GraduationCap, Users, UserCheck,
  Search, RotateCcw, AlertTriangle, Filter, ChevronDown, Eye, Clock,
  Archive, Activity
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { formatPhilippineDate } from '../../utils/philippineTime';
import { exportTicketsToExcel } from '../../utils/excelExport';
import { AttendeeType } from '../../types';
import Modal from '../common/Modal.vue';
import StatusBadge from '../common/StatusBadge.vue';
import AttendanceArchiveView from './AttendanceArchiveView.vue';

const props = withDefaults(
  defineProps<{
    initialTab?: 'active' | 'archive';
  }>(),
  {
    initialTab: 'active'
  }
);

const emit = defineEmits<{
  (e: 'switch-tab', tabId: string): void;
}>();

const activeSubTab = ref<'active' | 'archive'>(props.initialTab);

watch(
  () => props.initialTab,
  (newVal) => {
    if (newVal) {
      activeSubTab.value = newVal;
    }
  }
);

const archiveTargetEventId = ref<string | null>(null);

const setSubTab = (tab: 'active' | 'archive') => {
  activeSubTab.value = tab;
  if (tab === 'active') {
    archiveTargetEventId.value = null;
  }
  emit('switch-tab', tab === 'archive' ? 'attendance_archive' : 'attendance');
};

const { currentEvent, setCurrentEvent, events, activeEvents, archivedEvents, tickets, logs, archiveEvent, studentRecords, users } = useAppStore();

// Auto-select first active event if currentEvent is completed/archived or undefined
onMounted(() => {
  if (!currentEvent.value || currentEvent.value.status === 'completed' || currentEvent.value.status === 'archived') {
    if (activeEvents.value.length > 0) {
      setCurrentEvent(activeEvents.value[0]);
    }
  }
});

// UI State
const showInvalidModal = ref(false);
const showArchiveConfirmModal = ref(false);
const selectedAttendee = ref<UnifiedLiveAttendanceItem | null>(null);

// Filter states
const filterType = ref<'all' | 'STUDENT' | 'OUTSIDER'>('all');
const filterCourse = ref('all');
const filterYearLevel = ref('all');
const filterSection = ref('all');
const filterStatus = ref<'all' | 'present' | 'not_entered' | 'already_used' | 'invalid'>('all');
const searchQuery = ref('');

const handleSelectEvent = (e: Event) => {
  const target = e.target as HTMLSelectElement;
  const ev = activeEvents.value.find(item => item.id === target.value) || events.value.find(item => item.id === target.value);
  if (ev) setCurrentEvent(ev);
};

const openAttendeeDetails = (item: UnifiedLiveAttendanceItem) => {
  selectedAttendee.value = item;
};

// Summary count across active events
const activePresentCount = computed(() => {
  const activeIds = new Set(activeEvents.value.map(e => e.id));
  return tickets.value.filter(t => activeIds.has(t.eventId) && t.attendanceStatus === 'present').length;
});

// Derived metrics
const eventTickets = computed(() => tickets.value.filter(t => t.eventId === currentEvent.value?.id));
const presentCount = computed(() => liveAttendanceRoster.value.filter(i => i.status === 'PRESENT').length);
const notEnteredCount = computed(() => liveAttendanceRoster.value.filter(i => i.status === 'NOT YET ENTERED').length);

const studentsPresentCount = computed(() => {
  return liveAttendanceRoster.value.filter(i => i.status === 'PRESENT' && (i.attendeeType || 'STUDENT') === 'STUDENT').length;
});

const outsidersPresentCount = computed(() => {
  return liveAttendanceRoster.value.filter(i => i.status === 'PRESENT' && i.attendeeType === 'OUTSIDER').length;
});

const attendanceRate = computed(() => {
  if (liveAttendanceRoster.value.length === 0) return 0;
  return Math.round((presentCount.value / liveAttendanceRoster.value.length) * 100);
});

const eventLogs = computed(() => logs.value.filter(l => l.eventId === currentEvent.value?.id || l.eventId === 'unknown'));
const deniedLogs = computed(() => eventLogs.value.filter(l => l.status === 'denied'));
const duplicateAttemptsCount = computed(() => eventLogs.value.filter(l => l.denialReason && l.denialReason.includes('ALREADY USED')).length);
const invalidAttemptsCount = computed(() => eventLogs.value.filter(l => l.status === 'denied' && (!l.denialReason || !l.denialReason.includes('ALREADY USED'))).length);

// Dynamic Options
const availableCourses = computed(() => {
  const set = new Set<string>();
  liveAttendanceRoster.value.forEach(t => {
    if (t.course) set.add(t.course);
  });
  if (set.size === 0) return ['BSIT', 'BSCS', 'BSCpE', 'BSBA', 'BSED', 'BSHM'];
  return Array.from(set).sort();
});

const availableSections = computed(() => {
  const set = new Set<string>();
  liveAttendanceRoster.value.forEach(t => {
    if (t.section) set.add(t.section);
  });
  if (set.size === 0) return ['11M1', '11M2', '12M1', '21M1', '21M2', '31M1', '31A1', '31A2', '41M1', '41A1'];
  return Array.from(set).sort();
});

const activeFilterCount = computed(() => {
  let count = 0;
  if (filterType.value !== 'all') count++;
  if (filterCourse.value !== 'all') count++;
  if (filterYearLevel.value !== 'all') count++;
  if (filterSection.value !== 'all') count++;
  if (filterStatus.value !== 'all') count++;
  return count;
});

const hasActiveFilters = computed(() => {
  return activeFilterCount.value > 0 || searchQuery.value.trim() !== '';
});

const resetFilters = () => {
  filterType.value = 'all';
  filterCourse.value = 'all';
  filterYearLevel.value = 'all';
  filterSection.value = 'all';
  filterStatus.value = 'all';
  searchQuery.value = '';
};

// Real-Time Attendance Unified Record Structure
export type LiveAttendanceStatus = 'PRESENT' | 'NOT YET ENTERED' | 'ALREADY USED' | 'INVALID';

export interface UnifiedLiveAttendanceItem {
  id: string;
  timeFormatted: string;
  name: string;
  studentId?: string;
  ticketNumber?: string;
  ticketId: string;
  attendeeType: AttendeeType;
  course?: string;
  yearLevel?: string;
  section?: string;
  status: LiveAttendanceStatus;
  timestamp: string;
  scannedBy?: string;
}

const getAttendanceBadgeStatus = (status: LiveAttendanceStatus): string => {
  switch (status) {
    case 'PRESENT':
      return 'present';
    case 'ALREADY USED':
      return 'already_used';
    case 'INVALID':
      return 'invalid';
    case 'NOT YET ENTERED':
    default:
      return 'not_entered';
  }
};

// Deduplicated authoritative live attendance roster for current event
const liveAttendanceRoster = computed<UnifiedLiveAttendanceItem[]>(() => {
  const attendeeMap = new Map<string, UnifiedLiveAttendanceItem>();

  // 1. Seed attendee records from event tickets (base roster)
  eventTickets.value.forEach(t => {
    const rawStudentId = (t.attendeeStudentId || '').trim();
    const type: AttendeeType = t.attendeeType || (rawStudentId ? 'STUDENT' : 'OUTSIDER');
    const studentInfo = type === 'STUDENT' && rawStudentId
      ? (studentRecords.value?.find(s => s.studentId?.toLowerCase().trim() === rawStudentId.toLowerCase()) ||
         users.value?.find(u => u.studentId?.toLowerCase().trim() === rawStudentId.toLowerCase()))
      : null;
    const isPresent = t.attendanceStatus === 'present';

    // Unique key per attendee: student ID for students, ticket number/id for outsiders
    const key = (type === 'STUDENT' && rawStudentId)
      ? `student:${rawStudentId.toLowerCase()}`
      : `ticket:${(t.ticketNumber || t.id).toLowerCase().trim()}`;

    attendeeMap.set(key, {
      id: `tix-${t.id}`,
      timeFormatted: isPresent && t.timeIn
        ? new Date(t.timeIn).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })
        : '---',
      name: t.currentAttendeeName || t.originalOwnerName || studentInfo?.name || 'Attendee',
      studentId: type === 'STUDENT' ? (rawStudentId || undefined) : undefined,
      ticketNumber: t.ticketNumber || t.id,
      ticketId: t.id,
      attendeeType: type,
      course: type === 'STUDENT' ? (t.course || studentInfo?.course) : undefined,
      yearLevel: type === 'STUDENT' ? (t.yearLevel || studentInfo?.yearLevel) : undefined,
      section: type === 'STUDENT' ? (t.section || studentInfo?.section) : undefined,
      status: isPresent ? 'PRESENT' : 'NOT YET ENTERED',
      timestamp: t.timeIn || t.purchaseDate,
      scannedBy: t.lastScannedBy || 'Event Staff Scanner',
    });
  });

  // 2. Overlay live check-in scan logs (chronological check-ins and denials) without duplicating rows
  eventLogs.value.forEach(l => {
    let statusText: LiveAttendanceStatus = 'PRESENT';
    if (l.status === 'denied') {
      if (l.denialReason?.includes('ALREADY USED')) {
        statusText = 'ALREADY USED';
      } else {
        statusText = 'INVALID';
      }
    }

    const rawStudentId = (l.studentId || '').trim();
    const type: AttendeeType = l.attendeeType || (rawStudentId ? 'STUDENT' : 'OUTSIDER');
    const studentInfo = type === 'STUDENT' && rawStudentId
      ? (studentRecords.value?.find(s => s.studentId?.toLowerCase().trim() === rawStudentId.toLowerCase()) ||
         users.value?.find(u => u.studentId?.toLowerCase().trim() === rawStudentId.toLowerCase()))
      : null;

    const key = (type === 'STUDENT' && rawStudentId)
      ? `student:${rawStudentId.toLowerCase()}`
      : `ticket:${(l.ticketNumber || l.ticketId || l.id).toLowerCase().trim()}`;

    const existing = attendeeMap.get(key);

    const logFormattedTime = l.timeIn || (l.timestamp ? new Date(l.timestamp).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' }) : '---');

    const logItem: UnifiedLiveAttendanceItem = {
      id: l.id,
      timeFormatted: logFormattedTime,
      name: l.attendeeName || studentInfo?.name || existing?.name || 'Guest / Attendee',
      studentId: type === 'STUDENT' ? (rawStudentId || existing?.studentId) : undefined,
      ticketNumber: l.ticketNumber || l.ticketId || existing?.ticketNumber,
      ticketId: l.ticketId || existing?.ticketId || l.id,
      attendeeType: type,
      course: type === 'STUDENT' ? (l.course || studentInfo?.course || existing?.course) : undefined,
      yearLevel: type === 'STUDENT' ? (l.yearLevel || studentInfo?.yearLevel || existing?.yearLevel) : undefined,
      section: type === 'STUDENT' ? (l.section || studentInfo?.section || existing?.section) : undefined,
      status: statusText,
      timestamp: l.timestamp,
      scannedBy: l.verifiedByName || 'Event Staff Scanner',
    };

    if (!existing) {
      attendeeMap.set(key, logItem);
    } else {
      // Prioritize PRESENT status over NOT YET ENTERED / denials
      if (statusText === 'PRESENT') {
        attendeeMap.set(key, {
          ...existing,
          ...logItem,
          status: 'PRESENT',
          timeFormatted: logFormattedTime !== '---' ? logFormattedTime : existing.timeFormatted,
        });
      } else if (existing.status !== 'PRESENT') {
        attendeeMap.set(key, {
          ...existing,
          ...logItem,
        });
      }
    }
  });

  return Array.from(attendeeMap.values());
});

const filteredLiveAttendance = computed(() => {
  return liveAttendanceRoster.value.filter(item => {
    if (filterType.value !== 'all' && item.attendeeType !== filterType.value) return false;
    if (filterCourse.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.course !== filterCourse.value)) return false;
    if (filterYearLevel.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.yearLevel !== filterYearLevel.value)) return false;
    if (filterSection.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.section !== filterSection.value)) return false;

    if (filterStatus.value === 'present' && item.status !== 'PRESENT') return false;
    if (filterStatus.value === 'not_entered' && item.status !== 'NOT YET ENTERED') return false;
    if (filterStatus.value === 'already_used' && item.status !== 'ALREADY USED') return false;
    if (filterStatus.value === 'invalid' && item.status !== 'INVALID') return false;

    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase().trim();
      const matchName = (item.name || '').toLowerCase().includes(q);
      const matchId = (item.studentId || '').toLowerCase().includes(q);
      const matchTicket = (item.ticketNumber || item.ticketId || '').toLowerCase().includes(q);
      const matchCourse = (item.course || '').toLowerCase().includes(q);
      if (!matchName && !matchId && !matchTicket && !matchCourse) return false;
    }

    return true;
  });
});

const handleExportExcel = () => {
  exportTicketsToExcel(eventTickets.value, events.value, `${currentEvent.value?.code || 'Event'}_RealTime_Attendance.xlsx`);
};

const handleArchiveCurrentEvent = () => {
  if (!currentEvent.value) return;
  showArchiveConfirmModal.value = true;
};

const confirmArchiveCurrentEvent = () => {
  if (!currentEvent.value) return;
  const evId = currentEvent.value.id;
  archiveTargetEventId.value = evId;
  archiveEvent(evId);
  showArchiveConfirmModal.value = false;
  // Switch to Archive view to inspect the historical record
  setSubTab('archive');
};
</script>
