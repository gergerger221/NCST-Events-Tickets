<template>
  <div class="space-y-6 max-w-7xl mx-auto">
    <!-- ==================== 1. ARCHIVE LANDING / EVENT LIST ==================== -->
    <div>
      <!-- Archive Page Header -->
      <div class="rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs transition-colors mb-6">
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
          <div class="space-y-1 min-w-0">
            <div class="flex items-center gap-2.5 flex-wrap">
              <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight flex items-center gap-2.5">
                <Archive class="w-6 h-6 text-indigo-600 dark:text-indigo-400" />
                <span>Attendance Archive</span>
              </h1>
              <span class="px-2.5 py-0.5 rounded-full text-[11px] font-bold bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                Historical Records
              </span>
            </div>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
              Permanent attendance logs and historical turnout records for completed and archived campus events
            </p>
          </div>

          <div class="flex items-center gap-2 self-start sm:self-auto">
            <button
              @click="$emit('switch-to-active')"
              class="px-4 py-2 bg-slate-100 dark:bg-[#252525] hover:bg-slate-200 dark:hover:bg-[#303030] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1.5"
            >
              <Activity class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
              <span>Switch to Active Live View</span>
            </button>
          </div>
        </div>
      </div>

      <!-- Archive Summary Metric Counters -->
      <div class="grid grid-cols-2 gap-3 sm:gap-4 mb-6" :class="isTeacher ? 'sm:grid-cols-3' : 'sm:grid-cols-4'">
        <div class="p-4 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs">
          <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
            Archived Events
          </span>
          <p class="text-2xl font-black text-slate-900 dark:text-white mt-1">
            {{ completedEventsList.length }}
          </p>
          <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Completed event records</p>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs">
          <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
            Total Attendees Archived
          </span>
          <p class="text-2xl font-black text-indigo-600 dark:text-indigo-400 mt-1">
            {{ totalHistoricalPresent }}
          </p>
          <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Historical validated entries</p>
        </div>

        <div class="p-4 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs">
          <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
            Students Recorded
          </span>
          <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-1">
            {{ totalHistoricalStudents }}
          </p>
          <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">{{ isTeacher ? 'Assigned student entries' : 'Verified NCST students' }}</p>
        </div>

        <div v-if="!isTeacher" class="p-4 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs">
          <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
            Outsiders Recorded
          </span>
          <p class="text-2xl font-black text-purple-600 dark:text-purple-400 mt-1">
            {{ totalHistoricalOutsiders }}
          </p>
          <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Guest & outsider visitors</p>
        </div>
      </div>

      <!-- Search & Filters for Archived Events -->
      <div class="flex flex-col sm:flex-row items-stretch sm:items-center justify-between gap-3 mb-5">
        <div class="relative flex-1">
          <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
          <input
            type="text"
            v-model="eventSearchQuery"
            placeholder="Search completed events by title, code, venue..."
            class="w-full pl-10 pr-4 py-2.5 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 shadow-xs"
          />
        </div>

        <div class="flex items-center gap-2 overflow-x-auto">
          <select
            v-model="selectedCategory"
            class="px-3 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 shadow-xs cursor-pointer"
          >
            <option value="all">All Categories</option>
            <option value="Institutional">Institutional</option>
            <option value="Cultural">Cultural</option>
            <option value="Academic">Academic</option>
            <option value="Technology">Technology</option>
            <option value="Sports">Sports</option>
          </select>
        </div>
      </div>

      <!-- Completed Events Table / Catalog -->
      <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-2xl overflow-hidden shadow-xs transition-colors">
        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border-collapse">
            <thead>
              <tr class="bg-slate-50 dark:bg-[#222222] text-slate-500 dark:text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200 dark:border-[#2e2e2e] tracking-wider">
                <th class="py-3 px-4">Completed Event</th>
                <th class="py-3 px-4">Date</th>
                <th class="py-3 px-4">Venue</th>
                <th class="py-3 px-4 text-center">Tickets</th>
                <th class="py-3 px-4 text-center">Present</th>
                <th class="py-3 px-4 text-center">Students</th>
                <th v-if="!isTeacher" class="py-3 px-4 text-center">Outsiders</th>
                <th class="py-3 px-4 text-center">Status</th>
                <th class="py-3 px-4 text-right">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
              <tr
                v-for="ev in filteredCompletedEvents"
                :key="ev.id"
                class="hover:bg-slate-50/80 dark:hover:bg-[#222222] transition group"
              >
                <!-- Event Title & Code -->
                <td class="py-3.5 px-4 whitespace-nowrap">
                  <div class="space-y-0.5">
                    <p class="font-bold text-slate-900 dark:text-white group-hover:text-indigo-600 dark:group-hover:text-indigo-400 transition-colors">
                      {{ ev.title }}
                    </p>
                    <div class="flex items-center gap-1.5">
                      <span class="text-[10px] font-mono font-bold text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-[#282828] px-1.5 py-0.2 rounded">
                        {{ ev.code }}
                      </span>
                      <span class="text-[10px] font-bold uppercase px-1.5 py-0.2 rounded bg-indigo-50 dark:bg-indigo-950/60 text-indigo-700 dark:text-indigo-300">
                        {{ ev.category }}
                      </span>
                    </div>
                  </div>
                </td>

                <!-- Date & Schedule -->
                <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                  <div class="space-y-0.5">
                    <span class="font-semibold block">{{ formatPhilippineDate(ev.date) }}</span>
                    <span class="text-[11px] text-slate-400 dark:text-slate-500">{{ ev.startTime }} - {{ ev.endTime }}</span>
                  </div>
                </td>

                <!-- Venue -->
                <td class="py-3.5 px-4 whitespace-nowrap text-slate-600 dark:text-slate-300">
                  <span class="truncate max-w-[180px] block font-medium">{{ ev.venue }}</span>
                </td>

                <!-- Tickets Issued -->
                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                  <span class="font-mono font-bold text-slate-800 dark:text-slate-200">
                    {{ getEventTicketsCount(ev.id).toLocaleString() }}
                  </span>
                </td>

                <!-- Present Turnout -->
                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                  <div class="space-y-0.5">
                    <span class="font-mono font-extrabold text-emerald-600 dark:text-emerald-400 text-sm">
                      {{ getEventPresentCount(ev.id).toLocaleString() }}
                    </span>
                    <span class="text-[10px] text-slate-400 block font-semibold">
                      {{ getEventAttendanceRate(ev.id) }}% turnout
                    </span>
                  </div>
                </td>

                <!-- Students Breakdown -->
                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                  <span class="px-2 py-0.5 bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 rounded font-bold text-[11px]">
                    {{ getEventStudentPresentCount(ev.id).toLocaleString() }}
                  </span>
                </td>

                <!-- Outsiders Breakdown (Admin Only) -->
                <td v-if="!isTeacher" class="py-3.5 px-4 text-center whitespace-nowrap">
                  <span class="px-2 py-0.5 bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 rounded font-bold text-[11px]">
                    {{ getEventOutsiderPresentCount(ev.id).toLocaleString() }}
                  </span>
                </td>

                <!-- Status -->
                <td class="py-3.5 px-4 text-center whitespace-nowrap">
                  <span class="px-2.5 py-0.5 rounded-full text-[10px] font-extrabold uppercase bg-slate-100 dark:bg-[#282828] text-slate-600 dark:text-slate-400 border border-slate-300 dark:border-slate-700">
                    ARCHIVED
                  </span>
                </td>

                <!-- Actions: View Attendance Button -->
                <td class="py-3.5 px-4 text-right whitespace-nowrap">
                  <button
                    @click="openEventAttendanceModal(ev)"
                    class="px-3.5 py-1.5 rounded-xl border border-indigo-300 dark:border-indigo-700/80 bg-indigo-50/50 hover:bg-indigo-100 dark:bg-indigo-950/30 dark:hover:bg-indigo-900/50 text-indigo-700 dark:text-indigo-300 font-bold text-xs inline-flex items-center gap-1.5 transition-all shadow-2xs hover:shadow-xs cursor-pointer"
                    title="Open full historical attendance records modal"
                  >
                    <span>View Attendance</span>
                    <ChevronRight class="w-3.5 h-3.5 text-indigo-500 dark:text-indigo-400" />
                  </button>
                </td>
              </tr>

              <!-- Empty state -->
              <tr v-if="filteredCompletedEvents.length === 0">
                <td :colspan="isTeacher ? 8 : 9" class="py-12 text-center text-slate-400 dark:text-slate-500">
                  <Archive class="w-8 h-8 mx-auto mb-2 opacity-40" />
                  <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No completed events found</p>
                  <p class="text-xs mt-0.5">Events become archived here once they reach completed status.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ==================== 2. LARGE ATTENDANCE ARCHIVE MODAL (80-90% VIEWPORT) ==================== -->
    <Teleport to="body">
      <div
        v-if="selectedEvent"
        class="fixed inset-0 z-50 flex items-center justify-center p-2 sm:p-4 md:p-6 overflow-hidden modal-overlay"
      >
        <!-- Backdrop with Blur -->
        <div
          class="fixed inset-0 bg-slate-950/75 backdrop-blur-sm transition-opacity duration-200"
          @click="closeEventAttendanceModal"
        />

        <!-- Large Modal Container (80-90% of Viewport Width) -->
        <div
          class="relative w-full md:w-[90vw] max-w-7xl h-full sm:h-[90vh] max-h-[92vh] bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-2xl sm:rounded-3xl shadow-2xl overflow-hidden flex flex-col z-10 transition-all transform animate-in fade-in zoom-in-95 duration-150"
          @click.stop
        >
          <!-- 1. Modal Header (Event Title, Date, Venue, Status, and Top Actions) -->
          <div class="px-5 sm:px-6 py-4 border-b border-slate-200 dark:border-[#2e2e2e] bg-slate-50/90 dark:bg-[#202020] flex flex-col md:flex-row md:items-center justify-between gap-3 shrink-0">
            <div class="space-y-1 min-w-0">
              <div class="flex items-center gap-2.5 flex-wrap">
                <h2 class="text-lg sm:text-xl font-black text-slate-900 dark:text-white tracking-tight truncate">
                  {{ selectedEvent.title }}
                </h2>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase bg-indigo-50 dark:bg-indigo-950/70 text-indigo-700 dark:text-indigo-300 border border-indigo-200 dark:border-indigo-800">
                  ARCHIVED
                </span>
                <span class="px-2 py-0.5 rounded-full text-[10px] font-bold bg-slate-200/80 dark:bg-[#2a2a2a] text-slate-700 dark:text-slate-300">
                  🔒 Read-Only Historical
                </span>
              </div>
              <p class="text-xs text-slate-500 dark:text-slate-400 font-medium flex items-center gap-2 flex-wrap">
                <span>{{ formatPhilippineDate(selectedEvent.date) }}</span>
                <span>•</span>
                <span>{{ selectedEvent.venue || 'NCST Gymnasium' }}</span>
                <span>•</span>
                <span class="font-mono text-slate-600 dark:text-slate-300">Event Code: {{ selectedEvent.code }}</span>
              </p>
            </div>

            <!-- Action Controls & Close Button -->
            <div class="flex items-center gap-2 shrink-0">
              <!-- Export Attendance (All Matching Records) -->
              <button
                @click="handleExportAttendance"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
                title="Export all filtered historical attendance records to Excel"
              >
                <FileSpreadsheet class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">Export Attendance</span>
              </button>

              <!-- Print Attendance (Clean Printable View) -->
              <button
                @click="handlePrintReport"
                class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-200 dark:bg-[#2c2c2c] hover:bg-slate-300 dark:hover:bg-[#383838] text-slate-800 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
                title="Print multi-page official attendance roster"
              >
                <Printer class="w-3.5 h-3.5" />
                <span class="hidden sm:inline">Print</span>
              </button>

              <!-- Close Button × -->
              <button
                @click="closeEventAttendanceModal"
                class="p-1.5 text-slate-400 hover:text-slate-700 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-[#2c2c2c] rounded-xl transition cursor-pointer ml-1"
                title="Close modal"
              >
                <X class="w-5 h-5" />
              </button>
            </div>
          </div>

          <!-- 2. Modal Body (Summary Cards, Filters, and Table) -->
          <div class="flex-1 overflow-y-auto p-4 sm:p-6 space-y-4 custom-scrollbar">
            <!-- Modal Summary Metric Counters (Compact Row) -->
            <div class="grid grid-cols-2 gap-3" :class="isTeacher ? 'sm:grid-cols-3' : 'sm:grid-cols-4'">
              <!-- Total Attendance -->
              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e]">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
                  Total Attendance
                </span>
                <div class="flex items-baseline justify-between mt-1">
                  <p class="text-xl font-black text-slate-900 dark:text-white">
                    {{ currentEventPresentCount.toLocaleString() }}
                  </p>
                  <span class="text-[11px] font-bold text-emerald-600 dark:text-emerald-400">
                    {{ currentEventAttendanceRate }}%
                  </span>
                </div>
                <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Validated entries</p>
              </div>

              <!-- Students -->
              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e]">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
                  Students
                </span>
                <p class="text-xl font-black text-blue-600 dark:text-blue-400 mt-1">
                  {{ currentEventStudentPresentCount.toLocaleString() }}
                </p>
                <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">{{ isTeacher ? 'Assigned students' : 'NCST students present' }}</p>
              </div>

              <!-- Outsiders (Hidden for Teachers) -->
              <div v-if="!isTeacher" class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e]">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
                  Outsiders
                </span>
                <p class="text-xl font-black text-purple-600 dark:text-purple-400 mt-1">
                  {{ currentEventOutsiderPresentCount.toLocaleString() }}
                </p>
                <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Guest & outsider visitors</p>
              </div>

              <!-- Tickets Issued -->
              <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e]">
                <span class="text-[10px] font-extrabold uppercase text-slate-400 dark:text-slate-500 tracking-wider block">
                  Tickets Issued
                </span>
                <p class="text-xl font-black text-slate-900 dark:text-white mt-1">
                  {{ currentEventTickets.length.toLocaleString() }}
                </p>
                <p class="text-[10px] text-slate-500 dark:text-[#888888] mt-0.5">Total tickets registered</p>
              </div>
            </div>

            <!-- Search & Compact Filter Row -->
            <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] space-y-3">
              <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-6 gap-2.5">
                <!-- Search input -->
                <div class="relative lg:col-span-2">
                  <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2 pointer-events-none" />
                  <input
                    type="text"
                    v-model="attendeeSearchQuery"
                    placeholder="Search attendance by ID, name, or ticket..."
                    class="w-full pl-9 pr-3 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-indigo-500 transition"
                  />
                </div>

                <!-- Attendee Type Filter (Admin only) -->
                <div v-if="!isTeacher">
                  <select
                    v-model="filterAttendeeType"
                    class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="all">All Types</option>
                    <option value="STUDENT">Students Only</option>
                    <option value="OUTSIDER">Outsiders Only</option>
                  </select>
                </div>

                <!-- Course Filter (Student Records) -->
                <div>
                  <select
                    v-model="filterCourse"
                    class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="all">All Courses</option>
                    <option v-for="c in availableCourses" :key="c" :value="c">{{ c }}</option>
                  </select>
                </div>

                <!-- Year Level Filter (Student Records) -->
                <div>
                  <select
                    v-model="filterYearLevel"
                    class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="all">All Years</option>
                    <option value="1st Year">1st Year</option>
                    <option value="2nd Year">2nd Year</option>
                    <option value="3rd Year">3rd Year</option>
                    <option value="4th Year">4th Year</option>
                  </select>
                </div>

                <!-- Section Filter (Student Records) -->
                <div>
                  <select
                    v-model="filterSection"
                    class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="all">All Sections</option>
                    <option v-for="sec in availableSections" :key="sec" :value="sec">Section {{ sec }}</option>
                  </select>
                </div>

                <!-- Status Filter -->
                <div>
                  <select
                    v-model="filterStatus"
                    class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-indigo-500 cursor-pointer"
                  >
                    <option value="all">All Status</option>
                    <option value="present">Present</option>
                    <option value="not_entered">Not Yet Entered</option>
                  </select>
                </div>
              </div>

              <!-- Filter feedback & Reset Button -->
              <div v-if="hasActiveFilters" class="flex items-center justify-between pt-2 border-t border-slate-200/80 dark:border-[#2a2a2a] text-xs">
                <span class="text-slate-500 dark:text-slate-400 font-medium">
                  Showing {{ filteredHistoricalAttendees.length }} of {{ currentEventTickets.length }} records matching filter
                </span>
                <button
                  @click="resetAttendeeFilters"
                  class="text-indigo-600 dark:text-indigo-400 hover:underline font-bold cursor-pointer flex items-center gap-1"
                >
                  <RotateCcw class="w-3 h-3" />
                  <span>Reset Filters</span>
                </button>
              </div>
            </div>

            <!-- Historical Attendance Table -->
            <div class="bg-white dark:bg-[#1c1c1c] border border-slate-200 dark:border-[#2e2e2e] rounded-2xl overflow-hidden shadow-xs">
              <div class="overflow-x-auto">
                <table class="w-full text-left text-xs border-collapse">
                  <thead>
                    <tr class="bg-slate-50 dark:bg-[#222222] text-slate-500 dark:text-slate-400 uppercase text-[10px] font-bold border-b border-slate-200 dark:border-[#2e2e2e] tracking-wider">
                      <th class="py-3 px-3.5">Time</th>
                      <th class="py-3 px-3.5">Attendee</th>
                      <th class="py-3 px-3.5">ID / Ticket</th>
                      <th class="py-3 px-3.5">Type</th>
                      <th class="py-3 px-3.5">Course</th>
                      <th class="py-3 px-3.5">Year</th>
                      <th class="py-3 px-3.5">Section</th>
                      <th class="py-3 px-3.5 text-center">Status</th>
                    </tr>
                  </thead>
                  <tbody class="divide-y divide-slate-100 dark:divide-[#262626]">
                    <tr
                      v-for="item in paginatedAttendees"
                      :key="item.id"
                      class="hover:bg-slate-50/80 dark:hover:bg-[#222222] transition"
                    >
                      <!-- Time -->
                      <td class="py-3 px-3.5 whitespace-nowrap font-mono text-[11px] text-slate-600 dark:text-slate-300 font-bold">
                        {{ item.timeFormatted }}
                      </td>

                      <!-- Attendee Name -->
                      <td class="py-3 px-3.5 whitespace-nowrap">
                        <div class="flex items-center gap-2">
                          <UserAvatar :alt="item.name" size="xs" shape="rounded" />
                          <span class="font-bold text-slate-900 dark:text-white">{{ item.name }}</span>
                        </div>
                      </td>

                      <!-- ID / Ticket -->
                      <td class="py-3 px-3.5 whitespace-nowrap font-mono text-[11px]">
                        <span v-if="item.studentId" class="font-bold text-blue-600 dark:text-blue-400 block">
                          {{ item.studentId }}
                        </span>
                        <span v-else-if="item.contactNumber" class="text-purple-600 dark:text-purple-400 block font-semibold">
                          {{ item.contactNumber }}
                        </span>
                        <span class="text-slate-400 text-[10px] block">
                          {{ item.ticketNumber }}
                        </span>
                      </td>

                      <!-- Type -->
                      <td class="py-3 px-3.5 whitespace-nowrap">
                        <span
                          class="px-2 py-0.5 rounded text-[10px] font-bold uppercase"
                          :class="item.attendeeType === 'STUDENT'
                            ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800'
                            : 'bg-purple-50 dark:bg-purple-950/60 text-purple-700 dark:text-purple-300 border border-purple-200 dark:border-purple-800'"
                        >
                          {{ item.attendeeType }}
                        </span>
                      </td>

                      <!-- Course (— for Outsiders) -->
                      <td class="py-3 px-3.5 whitespace-nowrap font-medium text-slate-700 dark:text-slate-300">
                        {{ item.attendeeType === 'STUDENT' ? (item.course || '—') : '—' }}
                      </td>

                      <!-- Year (— for Outsiders) -->
                      <td class="py-3 px-3.5 whitespace-nowrap text-slate-600 dark:text-slate-400">
                        {{ item.attendeeType === 'STUDENT' ? (item.yearLevel || '—') : '—' }}
                      </td>

                      <!-- Section (— for Outsiders) -->
                      <td class="py-3 px-3.5 whitespace-nowrap text-slate-600 dark:text-slate-400">
                        {{ item.attendeeType === 'STUDENT' ? (item.section ? `Sec ${item.section}` : '—') : '—' }}
                      </td>

                      <!-- Status -->
                      <td class="py-3 px-3.5 text-center whitespace-nowrap">
                        <span
                          class="px-2.5 py-0.5 rounded text-[10px] font-extrabold uppercase inline-block"
                          :class="item.status === 'PRESENT'
                            ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/60'
                            : 'bg-slate-100 dark:bg-[#282828] text-slate-500 dark:text-slate-400 border border-slate-300 dark:border-slate-700'"
                        >
                          {{ item.status }}
                        </span>
                      </td>
                    </tr>

                    <!-- Empty state -->
                    <tr v-if="filteredHistoricalAttendees.length === 0">
                      <td colspan="8" class="py-10 text-center text-slate-400 dark:text-slate-500">
                        <Users class="w-7 h-7 mx-auto mb-1.5 opacity-40" />
                        <p class="font-bold text-xs text-slate-700 dark:text-slate-300">
                          {{ isTeacher ? 'No matching student found' : 'No attendance records match your search or filter' }}
                        </p>
                        <p class="text-[11px] mt-0.5">
                          {{ isTeacher ? 'Only students assigned to your authorized classes are accessible.' : 'Try adjusting your search keyword or resetting filters.' }}
                        </p>
                      </td>
                    </tr>
                  </tbody>
                </table>
              </div>
            </div>
          </div>

          <!-- 3. Modal Pagination Footer -->
          <div class="px-5 sm:px-6 py-3 border-t border-slate-200 dark:border-[#2e2e2e] bg-slate-50/90 dark:bg-[#202020] flex flex-col sm:flex-row items-center justify-between gap-3 text-xs shrink-0">
            <!-- Records Count & Rows Per Page -->
            <div class="flex items-center gap-3 text-slate-500 dark:text-slate-400">
              <span class="font-semibold text-slate-700 dark:text-slate-300">
                Showing {{ filteredHistoricalAttendees.length === 0 ? 0 : startRowIndex + 1 }}–{{ endRowIndex }} of {{ filteredHistoricalAttendees.length }} records
              </span>
              <div class="flex items-center gap-1.5">
                <span class="text-[11px]">Rows:</span>
                <select
                  v-model.number="itemsPerPage"
                  class="px-2 py-1 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-lg text-xs font-bold text-slate-800 dark:text-slate-200 cursor-pointer"
                >
                  <option :value="25">25</option>
                  <option :value="50">50</option>
                  <option :value="100">100</option>
                </select>
              </div>
            </div>

            <!-- Page Navigation Controls (Previous | 1 2 3 ... 17 | Next) -->
            <div class="flex items-center gap-1">
              <button
                @click="currentPage--"
                :disabled="currentPage <= 1"
                class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-[#333333] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828] disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer"
              >
                Previous
              </button>

              <!-- Dynamic Numbered Page Buttons with Ellipsis -->
              <div class="flex items-center gap-1 px-1">
                <template v-for="(p, i) in displayedPages" :key="i">
                  <span v-if="p === '...'" class="px-1 text-slate-400 font-bold">...</span>
                  <button
                    v-else
                    @click="currentPage = Number(p)"
                    class="w-7 h-7 rounded-lg text-xs font-bold transition flex items-center justify-center cursor-pointer"
                    :class="currentPage === p
                      ? 'bg-indigo-600 text-white shadow-xs'
                      : 'border border-slate-200 dark:border-[#333333] text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828]'"
                  >
                    {{ p }}
                  </button>
                </template>
              </div>

              <button
                @click="currentPage++"
                :disabled="currentPage >= totalPages"
                class="px-2.5 py-1.5 rounded-lg border border-slate-200 dark:border-[#333333] font-bold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828] disabled:opacity-30 disabled:cursor-not-allowed transition cursor-pointer"
              >
                Next
              </button>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Archive,
  Activity,
  Search,
  ChevronRight,
  FileSpreadsheet,
  Users,
  Printer,
  X,
  RotateCcw
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem, AttendeeType } from '../../types';
import { formatPhilippineDate, formatPhilippineTime } from '../../utils/philippineTime';
import {
  exportTicketsToExcel,
  exportStudentAttendanceOnlyToExcel
} from '../../utils/excelExport';
import UserAvatar from '../common/UserAvatar.vue';

const props = defineProps<{
  initialEventId?: string | null;
}>();

const emit = defineEmits(['switch-to-active']);

const { currentUser, events, tickets, logs } = useAppStore();

const isTeacher = computed(() => currentUser.value?.role === 'teacher');

// Teacher Assigned Classes (Faculty Role Isolation)
const teacherAssignedClasses = computed(() => {
  return currentUser.value?.assignedClasses || [];
});

// Selected Event for Modal Inspector
const selectedEvent = ref<EventItem | null>(null);

// Catalog Filter State
const eventSearchQuery = ref('');
const selectedCategory = ref('all');

// Pagination State (Default 50 per requirement)
const currentPage = ref(1);
const itemsPerPage = ref(50);

// Attendee Modal Drill-down Filter State
const attendeeSearchQuery = ref('');
const filterAttendeeType = ref<'all' | 'STUDENT' | 'OUTSIDER'>('all');
const filterCourse = ref('all');
const filterYearLevel = ref('all');
const filterSection = ref('all');
const filterStatus = ref<'all' | 'present' | 'not_entered'>('all');

const resetAttendeeFilters = () => {
  attendeeSearchQuery.value = '';
  filterAttendeeType.value = 'all';
  filterCourse.value = 'all';
  filterYearLevel.value = 'all';
  filterSection.value = 'all';
  filterStatus.value = 'all';
  currentPage.value = 1;
};

// Modal Open & Close Handlers
const openEventAttendanceModal = (ev: EventItem) => {
  selectedEvent.value = ev;
  currentPage.value = 1;
  resetAttendeeFilters();
};

const closeEventAttendanceModal = () => {
  selectedEvent.value = null;
};

// Reactively watch incoming initialEventId prop (e.g. from Active Attendance archive transition)
watch(
  () => [props.initialEventId, events.value],
  ([evId]) => {
    if (evId && typeof evId === 'string') {
      const match = events.value.find(e => e.id === evId);
      if (match) {
        selectedEvent.value = match;
        currentPage.value = 1;
        resetAttendeeFilters();
      }
    }
  },
  { immediate: true }
);

// Completed / Archived Events List (Landing Page)
const completedEventsList = computed(() => {
  return events.value.filter(e => e.status === 'completed' || e.status === 'archived');
});

const filteredCompletedEvents = computed(() => {
  return completedEventsList.value.filter(e => {
    if (selectedCategory.value !== 'all' && e.category !== selectedCategory.value) {
      return false;
    }
    if (eventSearchQuery.value.trim()) {
      const q = eventSearchQuery.value.toLowerCase().trim();
      const matchTitle = (e.title || '').toLowerCase().includes(q);
      const matchCode = (e.code || '').toLowerCase().includes(q);
      const matchVenue = (e.venue || '').toLowerCase().includes(q);
      if (!matchTitle && !matchCode && !matchVenue) return false;
    }
    return true;
  });
});

// Summary Aggregates for All Completed Events
const totalHistoricalPresent = computed(() => {
  const completedIds = new Set(completedEventsList.value.map(e => e.id));
  return tickets.value.filter(t => completedIds.has(t.eventId) && t.attendanceStatus === 'present').length;
});

const totalHistoricalStudents = computed(() => {
  const completedIds = new Set(completedEventsList.value.map(e => e.id));
  return tickets.value.filter(t => completedIds.has(t.eventId) && t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length;
});

const totalHistoricalOutsiders = computed(() => {
  if (isTeacher.value) return 0;
  const completedIds = new Set(completedEventsList.value.map(e => e.id));
  return tickets.value.filter(t => completedIds.has(t.eventId) && t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length;
});

// Helper to filter tickets authorized for current user
const getAuthorizedTicketsForEvent = (eventId: string) => {
  const eventTix = tickets.value.filter(t => t.eventId === eventId);
  if (!isTeacher.value) return eventTix;
  return eventTix.filter(t => {
    if (t.attendeeType === 'OUTSIDER') return false;
    const tCourse = (t.course || '').trim().toLowerCase();
    const tSection = (t.section || '').trim().toUpperCase();
    return teacherAssignedClasses.value.some(
      ac => ac.course.trim().toLowerCase() === tCourse && ac.section.trim().toUpperCase() === tSection
    );
  });
};

// Landing Page Event Metric Helpers
const getEventTicketsCount = (eventId: string) => {
  return getAuthorizedTicketsForEvent(eventId).length;
};

const getEventPresentCount = (eventId: string) => {
  return getAuthorizedTicketsForEvent(eventId).filter(t => t.attendanceStatus === 'present').length;
};

const getEventStudentPresentCount = (eventId: string) => {
  return getAuthorizedTicketsForEvent(eventId).filter(t => t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length;
};

const getEventOutsiderPresentCount = (eventId: string) => {
  if (isTeacher.value) return 0;
  return getAuthorizedTicketsForEvent(eventId).filter(t => t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length;
};

const getEventAttendanceRate = (eventId: string) => {
  const total = getEventTicketsCount(eventId);
  if (total === 0) return 0;
  return Math.round((getEventPresentCount(eventId) / total) * 100);
};

// Selected Event Data Aggregates for Modal
const currentEventTickets = computed(() => {
  if (!selectedEvent.value) return [];
  return getAuthorizedTicketsForEvent(selectedEvent.value.id);
});

const currentEventPresentCount = computed(() => {
  return currentEventTickets.value.filter(t => t.attendanceStatus === 'present').length;
});

const currentEventStudentPresentCount = computed(() => {
  return currentEventTickets.value.filter(t => t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length;
});

const currentEventOutsiderPresentCount = computed(() => {
  if (isTeacher.value) return 0;
  return currentEventTickets.value.filter(t => t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length;
});

const currentEventAttendanceRate = computed(() => {
  if (currentEventTickets.value.length === 0) return 0;
  return Math.round((currentEventPresentCount.value / currentEventTickets.value.length) * 100);
});

// Dynamic Options for Modal Filters
const availableCourses = computed(() => {
  const set = new Set<string>();
  currentEventTickets.value.forEach(t => {
    if (t.course) set.add(t.course);
  });
  if (set.size === 0) return ['BSIT', 'BSCS', 'BSCpE', 'BSBA', 'BSED', 'BSHM'];
  return Array.from(set).sort();
});

const availableSections = computed(() => {
  const set = new Set<string>();
  currentEventTickets.value.forEach(t => {
    if (t.section) set.add(t.section);
  });
  if (set.size === 0) return ['A', 'B', 'C', 'M1', 'A1', 'E1'];
  return Array.from(set).sort();
});

const hasActiveFilters = computed(() => {
  return (
    attendeeSearchQuery.value.trim() !== '' ||
    filterAttendeeType.value !== 'all' ||
    filterCourse.value !== 'all' ||
    filterYearLevel.value !== 'all' ||
    filterSection.value !== 'all' ||
    filterStatus.value !== 'all'
  );
});

// Unified Historical Attendee Item Interface
interface HistoricalAttendeeItem {
  id: string;
  timeFormatted: string;
  name: string;
  studentId?: string;
  contactNumber?: string;
  ticketNumber: string;
  attendeeType: AttendeeType;
  course?: string;
  yearLevel?: string;
  section?: string;
  status: 'PRESENT' | 'NOT YET ENTERED';
}

// Filtered Historical Attendees for the Selected Event
const filteredHistoricalAttendees = computed(() => {
  const list: HistoricalAttendeeItem[] = currentEventTickets.value.map(t => {
    const isPresent = t.attendanceStatus === 'present';
    const type: AttendeeType = t.attendeeType || (t.attendeeStudentId ? 'STUDENT' : 'OUTSIDER');

    return {
      id: t.id,
      timeFormatted: isPresent && t.timeIn ? formatPhilippineTime(t.timeIn) : '---',
      name: t.currentAttendeeName || t.originalOwnerName || 'Attendee',
      studentId: type === 'STUDENT' ? t.attendeeStudentId : undefined,
      contactNumber: type === 'OUTSIDER' ? t.contactNumber : undefined,
      ticketNumber: t.ticketNumber || t.id,
      attendeeType: type,
      course: type === 'STUDENT' ? t.course : undefined,
      yearLevel: type === 'STUDENT' ? t.yearLevel : undefined,
      section: type === 'STUDENT' ? t.section : undefined,
      status: isPresent ? 'PRESENT' : 'NOT YET ENTERED',
    };
  });

  return list.filter(item => {
    if (filterAttendeeType.value !== 'all' && item.attendeeType !== filterAttendeeType.value) return false;
    if (filterCourse.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.course !== filterCourse.value)) return false;
    if (filterYearLevel.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.yearLevel !== filterYearLevel.value)) return false;
    if (filterSection.value !== 'all' && (item.attendeeType !== 'STUDENT' || item.section !== filterSection.value)) return false;

    if (filterStatus.value === 'present' && item.status !== 'PRESENT') return false;
    if (filterStatus.value === 'not_entered' && item.status !== 'NOT YET ENTERED') return false;

    if (attendeeSearchQuery.value.trim()) {
      const q = attendeeSearchQuery.value.toLowerCase().trim();
      const matchName = (item.name || '').toLowerCase().includes(q);
      const matchId = (item.studentId || item.contactNumber || '').toLowerCase().includes(q);
      const matchTicket = (item.ticketNumber || '').toLowerCase().includes(q);
      const matchCourse = (item.course || '').toLowerCase().includes(q);
      if (!matchName && !matchId && !matchTicket && !matchCourse) return false;
    }

    return true;
  });
});

// Pagination Calculations (Default 50)
const totalPages = computed(() => Math.max(1, Math.ceil(filteredHistoricalAttendees.value.length / itemsPerPage.value)));
const startRowIndex = computed(() => (currentPage.value - 1) * itemsPerPage.value);
const endRowIndex = computed(() => Math.min(startRowIndex.value + itemsPerPage.value, filteredHistoricalAttendees.value.length));

const paginatedAttendees = computed(() => {
  return filteredHistoricalAttendees.value.slice(startRowIndex.value, endRowIndex.value);
});

// Smart Displayed Pages with Ellipsis (e.g. 1 2 3 ... 17)
const displayedPages = computed(() => {
  const total = totalPages.value;
  const current = currentPage.value;
  if (total <= 7) {
    return Array.from({ length: total }, (_, i) => i + 1);
  }
  const pages: (number | string)[] = [];
  if (current <= 3) {
    pages.push(1, 2, 3, 4, '...', total);
  } else if (current >= total - 2) {
    pages.push(1, '...', total - 3, total - 2, total - 1, total);
  } else {
    pages.push(1, '...', current - 1, current, current + 1, '...', total);
  }
  return pages;
});

// Export Attendance Handler (Exports ALL Matching Records for Selected Event)
const handleExportAttendance = () => {
  if (!selectedEvent.value) return;

  // Find the full raw tickets for all filtered items
  const matchingTicketIds = new Set(filteredHistoricalAttendees.value.map(i => i.id));
  const ticketsToExport = currentEventTickets.value.filter(t => matchingTicketIds.has(t.id));

  const fileName = `${selectedEvent.value.code || 'Event'}_Archived_Attendance_${new Date().toISOString().slice(0, 10)}.xlsx`;

  if (isTeacher.value) {
    exportStudentAttendanceOnlyToExcel(ticketsToExport, events.value, fileName);
  } else {
    exportTicketsToExcel(ticketsToExport, events.value, fileName);
  }
};

// Print Official Attendance Sheet for Selected Event
const handlePrintReport = () => {
  if (!selectedEvent.value) return;
  window.print();
};
</script>
