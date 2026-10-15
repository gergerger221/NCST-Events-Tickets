<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <div class="flex items-center gap-2 mb-1">
          <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-slate-100 dark:bg-[#252525] text-[#475569] dark:text-slate-300 border border-slate-200 dark:border-[#333333]">
            {{ isAdmin ? 'Institutional Administration' : 'Faculty Reporting' }}
          </span>
          <span v-if="isAdmin && activeSection === 'sales'" class="text-[10px] font-bold text-emerald-700 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 px-2 py-0.5 rounded-full flex items-center gap-1">
            <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
            Authoritative Financial Sales
          </span>
        </div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
          Attendance & Ticketing Reports Center
        </h2>
        <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
          Authoritative event attendance audit trail, ticket sales summaries, collected revenue, and Microsoft Excel exports.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="handlePrint"
          class="flex items-center gap-2 px-3.5 py-2.5 bg-white dark:bg-[#202020] hover:bg-slate-50 dark:hover:bg-[#282828] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold border border-slate-200 dark:border-[#3a3a3a] shadow-xs transition cursor-pointer"
        >
          <Printer class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>Print Report</span>
        </button>
        <button
          @click="handleExportStudentExcel"
          class="flex items-center gap-1.5 px-3.5 py-2.5 bg-white dark:bg-[#202020] hover:bg-slate-50 dark:hover:bg-[#282828] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold border border-slate-200 dark:border-[#3a3a3a] shadow-xs transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>{{ isTeacher ? 'Export My Students (.xlsx)' : 'Export Students (.xlsx)' }}</span>
        </button>
        <button
          v-if="!isTeacher"
          @click="handleExportOutsiderExcel"
          class="flex items-center gap-1.5 px-3.5 py-2.5 bg-white dark:bg-[#202020] hover:bg-slate-50 dark:hover:bg-[#282828] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-semibold border border-slate-200 dark:border-[#3a3a3a] shadow-xs transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4 text-slate-500 dark:text-slate-400" />
          <span>Export Outsiders (.xlsx)</span>
        </button>
        <button
          v-if="!isTeacher"
          @click="handleExportExcel"
          class="flex items-center gap-1.5 px-4 py-2.5 bg-[#1E3A5F] hover:bg-[#162a45] active:scale-[0.99] text-white rounded-xl text-xs font-bold shadow-sm transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4 text-white" />
          <span>Export Master Workbook (.xlsx)</span>
        </button>
      </div>
    </div>

    <!-- Section Switcher: Attendance Reports vs Ticket Sales & Revenue (Admin Only) -->
    <div v-if="isAdmin" class="no-print flex items-center justify-between p-1.5 bg-slate-100 dark:bg-[#181818] rounded-2xl border border-slate-200 dark:border-[#2a2a2a]">
      <div class="flex items-center gap-1.5 w-full sm:w-auto">
        <button
          @click="activeSection = 'attendance'"
          class="flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition flex-1 sm:flex-initial cursor-pointer"
          :class="activeSection === 'attendance'
            ? 'bg-white dark:bg-[#242424] text-slate-900 dark:text-white shadow-xs border border-slate-200 dark:border-[#383838]'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          <Users class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          <span>Attendance & Gate Reports</span>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-slate-100 dark:bg-[#2e2e2e] text-slate-600 dark:text-slate-300 font-mono">
            {{ totalCount }}
          </span>
        </button>
        <button
          @click="activeSection = 'sales'"
          class="flex items-center justify-center gap-2 px-4 py-2 rounded-xl text-xs font-bold transition flex-1 sm:flex-initial cursor-pointer"
          :class="activeSection === 'sales'
            ? 'bg-white dark:bg-[#242424] text-slate-900 dark:text-white shadow-xs border border-slate-200 dark:border-[#383838]'
            : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
        >
          <CreditCard class="w-4 h-4 text-[#1E3A5F] dark:text-blue-400" />
          <span>Ticket Sales & Revenue</span>
          <span class="text-[10px] px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 font-mono font-bold">
            ₱{{ formatCurrency(totalCollectedSales) }}
          </span>
        </button>
      </div>

      <div class="hidden md:flex items-center gap-2 text-[11px] text-slate-500 dark:text-slate-400 px-3">
        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
        <span>Authoritative POS Sync Active</span>
      </div>
    </div>

    <!-- Multi-Criteria Filter Controls Toolbar -->
    <div class="no-print space-y-3 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-4 rounded-2xl shadow-xs">
      <div class="flex items-center justify-between pb-2 border-b border-slate-100 dark:border-[#2a2a2a]">
        <div class="flex items-center gap-2 text-xs font-black uppercase tracking-wider text-slate-700 dark:text-slate-300">
          <Filter class="w-3.5 h-3.5 text-[#1E3A5F] dark:text-blue-400" />
          <span>Authoritative Reporting Scope & Filters</span>
        </div>
        <button
          v-if="hasActiveSecondaryFilters"
          @click="resetSecondaryFilters"
          class="flex items-center gap-1 text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer"
        >
          <RotateCcw class="w-3 h-3" />
          <span>Reset Filters</span>
        </button>
      </div>

      <!-- Filter Controls Grid -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3">
        <!-- 1. Academic Period -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Academic Period:</label>
          <select
            v-model="selectedPeriodId"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All Academic Periods</option>
            <option v-for="p in academicPeriods" :key="p.id" :value="p.id">
              A.Y. {{ p.academicYear }} — {{ p.semester }} ({{ p.status }})
            </option>
          </select>
        </div>

        <!-- 2. Event Scope -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Event Scope:</label>
          <select
            v-model="selectedEventId"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All Events in Scope ({{ periodScopedEvents.length }})</option>
            <option v-for="ev in periodScopedEvents" :key="ev.id" :value="ev.id">
              {{ ev.title }} ({{ ev.code }}) • {{ (ev.ticketPrice ?? 0) > 0 ? `₱${ev.ticketPrice}` : 'Free' }}
            </option>
          </select>
        </div>

        <!-- 3. Attendee Type Filter -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Attendee Type:</label>
          <select
            v-model="selectedAttendeeType"
            :disabled="isTeacher"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer disabled:opacity-60"
          >
            <option value="all">All Attendee Types</option>
            <option value="STUDENT">Students Only</option>
            <option v-if="!isTeacher" value="OUTSIDER">Outsiders / Guests Only</option>
          </select>
        </div>

        <!-- 4. Course Filter -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Course (Students):</label>
          <select
            v-model="selectedCourse"
            :disabled="selectedAttendeeType === 'OUTSIDER'"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer disabled:opacity-50"
          >
            <option value="all">All Academic Courses</option>
            <option v-for="c in availableCourses" :key="c" :value="c">{{ c }}</option>
          </select>
        </div>

        <!-- 5. Year Level Filter -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Year Level (Students):</label>
          <select
            v-model="selectedYearLevel"
            :disabled="selectedAttendeeType === 'OUTSIDER'"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer disabled:opacity-50"
          >
            <option value="all">All Year Levels</option>
            <option v-for="y in availableYearLevels" :key="y" :value="y">{{ y }}</option>
          </select>
        </div>

        <!-- 6. Section Filter -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Section (NCST Code):</label>
          <select
            v-model="selectedSection"
            :disabled="selectedAttendeeType === 'OUTSIDER'"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer disabled:opacity-50"
          >
            <option value="all">All Valid Sections</option>
            <option v-for="sec in availableSections" :key="sec" :value="sec">{{ sec }}</option>
          </select>
        </div>

        <!-- 7. Date From -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Transaction Date From:</label>
          <input
            type="date"
            v-model="selectedDateFrom"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          />
        </div>

        <!-- 8. Date To -->
        <div class="space-y-1">
          <label class="text-[11px] font-bold text-slate-600 dark:text-slate-400 block">Transaction Date To:</label>
          <input
            type="date"
            v-model="selectedDateTo"
            class="w-full px-3 py-1.5 bg-slate-50 dark:bg-[#252525] border border-slate-200 dark:border-[#383838] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          />
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECTION A: TICKET SALES & REVENUE (Admin Only)                            -->
    <!-- ========================================================================= -->
    <div v-if="isAdmin && activeSection === 'sales'" class="space-y-6">
      <!-- 6 AUTHORITATIVE SALES METRIC CARDS -->
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        <!-- 1. Total Paid Tickets Sold -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Total Paid Tickets Sold
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <TicketIcon class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                {{ totalPaidSold }}
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">tickets</span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              Student ({{ paidStudentSold }}) + Outsider ({{ paidOutsiderSold }})
            </p>
          </div>
        </div>

        <!-- 2. Paid Student Tickets Sold -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Paid Student Tickets Sold
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <GraduationCap class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                {{ paidStudentSold }}
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">passes</span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              NCST Enrolled Students • ₱{{ formatCurrency(studentCollectedSales) }}
            </p>
          </div>
        </div>

        <!-- 3. Paid Outsider Tickets Sold -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Paid Outsider Tickets Sold
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <Users class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                {{ paidOutsiderSold }}
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">passes</span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              Guest & Public Passes • ₱{{ formatCurrency(outsiderCollectedSales) }}
            </p>
          </div>
        </div>

        <!-- 4. Free Tickets Issued -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Free Tickets Issued
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <Tag class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                {{ freeIssued }}
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">free passes</span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              Complimentary (Student: {{ freeStudentIssued }}, Guest: {{ freeOutsiderIssued }})
            </p>
          </div>
        </div>

        <!-- 5. Total Valid Passes -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Total Valid Passes
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <ShieldCheck class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                {{ totalValidPasses }}
              </span>
              <span class="text-xs text-[#64748B] dark:text-slate-400 font-medium">active passes</span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              Active in circulation (excludes void & cancelled)
            </p>
          </div>
        </div>

        <!-- 6. Total Collected Sales (₱) -->
        <div class="p-5 rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] shadow-xs flex flex-col justify-between transition-colors">
          <div class="flex items-center justify-between">
            <span class="text-[10px] font-black uppercase tracking-wider text-[#475569] dark:text-slate-400 block">
              Total Collected Sales
            </span>
            <div class="w-10 h-10 rounded-xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
              <CreditCard class="w-5 h-5 text-[#1E3A5F] dark:text-blue-400" />
            </div>
          </div>
          <div class="mt-3">
            <div class="flex items-baseline gap-2">
              <span class="text-3xl font-black text-[#1E3A5F] dark:text-blue-400 font-mono tracking-tight">
                ₱{{ formatCurrency(totalCollectedSales) }}
              </span>
            </div>
            <p class="text-xs text-[#64748B] dark:text-slate-400 mt-1 font-medium">
              Confirmed Cash & Online Collections
            </p>
          </div>
        </div>
      </div>

      <!-- Financial Sub-Tabs -->
      <div class="no-print flex items-center gap-2 overflow-x-auto pb-1">
        <button
          v-for="stab in salesSubTabs"
          :key="stab.id"
          @click="salesSubTab = stab.id as any"
          class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap"
          :class="salesSubTab === stab.id
            ? 'bg-[#1E3A5F] text-white shadow-md'
            : 'bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-[#2c2c2c]'"
        >
          {{ stab.label }} ({{ stab.count }})
        </button>
      </div>

      <!-- Financial Views: 1. Sales by Event -->
      <div v-if="salesSubTab === 'events'" class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 shadow-xs overflow-hidden">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-[#333333] mb-4">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Ticket Sales Breakdown by Event</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Authoritative collected revenue, paid ticket volume, and passes per event.</p>
          </div>
          <span class="text-xs font-bold text-slate-600 dark:text-slate-300 font-mono">
            {{ salesByEventList.length }} Scoped Events
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
              <tr>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Event Code</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Event Title</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Academic Period</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Ticket Price</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Paid Student</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Paid Outsider</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Total Paid</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Free Passes</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Total Valid</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-right">Collected Revenue</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c]">
              <tr v-for="(ev, idx) in salesByEventList" :key="ev.eventId" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
                <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
                <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ ev.eventCode }}</td>
                <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ ev.eventTitle }}</td>
                <td class="p-2.5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ ev.academicYear }} {{ ev.semester }}</td>
                <td class="p-2.5 font-semibold border border-slate-200 dark:border-[#333333]">
                  {{ ev.eventPrice > 0 ? `₱${formatCurrency(ev.eventPrice)}` : 'FREE' }}
                </td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ ev.paidStudentTickets }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ ev.paidOutsiderTickets }}</td>
                <td class="p-2.5 font-mono font-bold text-center border border-slate-200 dark:border-[#333333]">{{ ev.totalPaidTickets }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333] text-slate-500">{{ ev.freeTicketsIssued }}</td>
                <td class="p-2.5 font-mono font-bold text-center border border-slate-200 dark:border-[#333333]">{{ ev.totalValidPasses }}</td>
                <td class="p-2.5 font-mono font-black text-right text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-[#333333]">
                  ₱{{ formatCurrency(ev.totalCollectedSales) }}
                </td>
              </tr>
              <tr v-if="salesByEventList.length === 0">
                <td colspan="11" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No event sales found matching current filters.</td>
              </tr>
            </tbody>
            <tfoot v-if="salesByEventList.length > 0" class="bg-slate-50 dark:bg-[#242424] font-bold text-slate-900 dark:text-white">
              <tr>
                <td colspan="5" class="p-2.5 text-right uppercase text-[10px] tracking-wider border border-slate-200 dark:border-[#333333]">Reconciled Event Totals:</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ paidStudentSold }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ paidOutsiderSold }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ totalPaidSold }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ freeIssued }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ totalValidPasses }}</td>
                <td class="p-2.5 font-mono text-right text-emerald-600 dark:text-emerald-400 text-sm font-black border border-slate-200 dark:border-[#333333]">
                  ₱{{ formatCurrency(totalCollectedSales) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Financial Views: 2. Student Sales by Section -->
      <div v-else-if="salesSubTab === 'sections'" class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 shadow-xs overflow-hidden">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-[#333333] mb-4">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Student Ticket Sales by Academic Section</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Enrolled student ticket sales partitioned by course, year level, and official NCST section code (Outsiders excluded).</p>
          </div>
          <span class="text-xs font-bold text-slate-600 dark:text-slate-300 font-mono">
            {{ studentSalesBySectionList.length }} Sections
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
              <tr>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Course</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Year Level</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Section (NCST Format)</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Paid Student Tickets</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Free Student Tickets</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-center">Total Passes</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333] text-right">Collected Sales</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c]">
              <tr v-for="(sec, idx) in studentSalesBySectionList" :key="sec.key" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
                <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
                <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ sec.course }}</td>
                <td class="p-2.5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ sec.yearLevel }}</td>
                <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ sec.section }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ sec.paidCount }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333] text-slate-500">{{ sec.freeCount }}</td>
                <td class="p-2.5 font-mono font-bold text-center border border-slate-200 dark:border-[#333333]">{{ sec.totalPasses }}</td>
                <td class="p-2.5 font-mono font-black text-right text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-[#333333]">
                  ₱{{ formatCurrency(sec.revenue) }}
                </td>
              </tr>
              <tr v-if="studentSalesBySectionList.length === 0">
                <td colspan="8" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No student section sales found matching current filters.</td>
              </tr>
            </tbody>
            <tfoot v-if="studentSalesBySectionList.length > 0" class="bg-slate-50 dark:bg-[#242424] font-bold text-slate-900 dark:text-white">
              <tr>
                <td colspan="4" class="p-2.5 text-right uppercase text-[10px] tracking-wider border border-slate-200 dark:border-[#333333]">Student Section Totals:</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ paidStudentSold }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ freeStudentIssued }}</td>
                <td class="p-2.5 font-mono text-center border border-slate-200 dark:border-[#333333]">{{ paidStudentSold + freeStudentIssued }}</td>
                <td class="p-2.5 font-mono text-right text-emerald-600 dark:text-emerald-400 text-sm font-black border border-slate-200 dark:border-[#333333]">
                  ₱{{ formatCurrency(studentCollectedSales) }}
                </td>
              </tr>
            </tfoot>
          </table>
        </div>
      </div>

      <!-- Financial Views: 3. Purchase Transactions -->
      <div v-else-if="salesSubTab === 'transactions'" class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 shadow-xs overflow-hidden">
        <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-[#333333] mb-4">
          <div>
            <h3 class="text-base font-black text-slate-900 dark:text-white">Ticket Purchase & Issuance Audit Register</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">Authoritative audit ledger of individual ticket transactions within current scope.</p>
          </div>
          <span class="text-xs font-bold text-slate-600 dark:text-slate-300 font-mono">
            {{ scopedTransactions.length }} Transactions
          </span>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
              <tr>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Transaction ID</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Purchaser</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Type</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">ID / Contact</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Course & Sec</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Event Title</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Ticket No.</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Amount</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Status</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Cashier</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Purchase Date</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c]">
              <tr v-for="(txn, idx) in scopedTransactions" :key="txn.id" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
                <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
                <td class="p-2.5 font-mono font-bold text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-[#333333]">{{ txn.id }}</td>
                <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ txn.userName }}</td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                    :class="(txn.attendeeType || 'STUDENT') === 'OUTSIDER'
                      ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300'
                      : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-300'"
                  >
                    {{ txn.attendeeType || 'STUDENT' }}
                  </span>
                </td>
                <td class="p-2.5 font-mono text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ txn.studentId || txn.contactNumber || '—' }}</td>
                <td class="p-2.5 font-mono text-[11px] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ txn.course ? `${txn.course} ${txn.section ? 'Sec ' + txn.section : ''}` : '—' }}</td>
                <td class="p-2.5 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ txn.eventTitle }}</td>
                <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ txn.ticketNumber || txn.ticketId }}</td>
                <td class="p-2.5 font-mono font-black text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-[#333333]">
                  {{ Number(txn.amount) === 0 ? 'FREE' : '₱' + formatCurrency(txn.amount) }}
                </td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="txn.paymentStatus === 'paid' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'">
                    {{ txn.paymentStatus }}
                  </span>
                </td>
                <td class="p-2.5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ txn.cashierName || 'Cashier POS' }}</td>
                <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ formatPhilippineFull(txn.purchaseDate) }}</td>
              </tr>
              <tr v-if="scopedTransactions.length === 0">
                <td colspan="12" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No ticket purchase transactions found.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>

    <!-- ========================================================================= -->
    <!-- SECTION B: ATTENDANCE & GATE REPORTS (All Authorized Roles)               -->
    <!-- ========================================================================= -->
    <div v-else class="space-y-6">
      <!-- Report Filter Tabs -->
      <div class="no-print flex items-center gap-2 overflow-x-auto pb-1">
        <button
          v-for="tab in reportTabs"
          :key="tab.id"
          @click="reportType = tab.id as any"
          class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap"
          :class="reportType === tab.id ? 'bg-[#1E3A5F] text-white shadow-md' : 'bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-[#2c2c2c]'"
        >
          {{ tab.label }} ({{ tab.count }})
        </button>
      </div>

      <!-- Printable Report Sheet -->
      <div class="print-page bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 sm:p-8 shadow-sm dark:shadow-md transition-colors">
        <!-- Header -->
        <div class="text-center pb-6 border-b border-slate-200 dark:border-[#333333] space-y-1">
          <div class="flex items-center justify-center gap-2 text-[#1E3A5F] dark:text-blue-400 font-bold text-xs uppercase tracking-widest">
            <Building2 class="w-4 h-4" />
            National College of Science and Technology
          </div>
          <h1 class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white uppercase tracking-tight">
            Official Event Attendance & Security Audit Report
          </h1>
          <p class="text-xs text-[#475569] dark:text-slate-400">
            Academic Period: <strong class="text-[#0F172A] dark:text-white font-bold">{{ displayAcademicPeriodText }}</strong> •
            Event: <strong class="text-[#0F172A] dark:text-white font-bold">{{ displayEventText }}</strong>
            <span v-if="displayEventVenue"> • Venue: <strong class="text-[#0F172A] dark:text-white font-bold">{{ displayEventVenue }}</strong></span>
          </p>
          <p class="text-[11px] text-[#475569] dark:text-slate-400 font-mono">
            Report Generated on: <strong class="text-[#0F172A] dark:text-white font-semibold">{{ formatPhilippineFull(new Date()) }}</strong> • Report Category: <strong class="text-[#0F172A] dark:text-white font-semibold">{{ reportType.toUpperCase() }}</strong>
          </p>
        </div>

        <!-- Metric Ribbon (Attendance Turnout & Gate Entry) -->
        <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 sm:gap-4 my-6">
          <div class="p-3.5 sm:p-4 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center shadow-xs transition-colors">
            <span class="text-[10px] font-bold text-[#475569] dark:text-slate-400 uppercase tracking-wider block mb-1">Tickets Issued</span>
            <span class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white font-mono block">{{ totalCount }}</span>
          </div>
          <div class="p-3.5 sm:p-4 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center shadow-xs transition-colors">
            <span class="text-[10px] font-bold text-[#475569] dark:text-slate-400 uppercase tracking-wider block mb-1">QR Labels Pending</span>
            <span class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white font-mono block">{{ qrPendingCount }}</span>
          </div>
          <div class="p-3.5 sm:p-4 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center shadow-xs transition-colors">
            <span class="text-[10px] font-bold text-[#475569] dark:text-slate-400 uppercase tracking-wider block mb-1">QR Labels Printed</span>
            <span class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white font-mono block">{{ qrPrintedCount }}</span>
          </div>
          <div class="p-3.5 sm:p-4 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center shadow-xs transition-colors">
            <span class="text-[10px] font-bold text-[#475569] dark:text-slate-400 uppercase tracking-wider block mb-1">Tickets Used (Scanned)</span>
            <span class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white font-mono block">{{ presentCount }}</span>
          </div>
          <div class="p-3.5 sm:p-4 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center col-span-2 sm:col-span-1 shadow-xs transition-colors">
            <span class="text-[10px] font-bold text-[#475569] dark:text-slate-400 uppercase tracking-wider block mb-1">Not Yet Entered</span>
            <span class="text-xl sm:text-2xl font-black text-[#0F172A] dark:text-white font-mono block">{{ notYetScannedCount }}</span>
          </div>
        </div>

        <!-- Data Tables -->
        <!-- Scan Audit Logs -->
        <div v-if="reportType === 'audit_logs'" class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
              <tr>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Ticket No.</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Type</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Attendee Name</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Student ID</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Course & Sec</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Status</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Timestamp</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Verified By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c]">
              <tr v-for="(l, idx) in scopedLogs" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
                <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
                <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ l.ticketNumber || l.ticketId }}</td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span
                    class="px-2 py-0.5 rounded text-[10px] font-black uppercase"
                    :class="l.attendeeType === 'OUTSIDER' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950/60 dark:text-amber-300' : 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300'"
                  >
                    {{ l.attendeeType || (l.studentId ? 'STUDENT' : 'OUTSIDER') }}
                  </span>
                </td>
                <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ l.attendeeName }}</td>
                <td class="p-2.5 font-mono text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ l.studentId || '—' }}</td>
                <td class="p-2.5 font-mono text-[11px] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ l.course ? `${l.course} ${l.yearLevel ? l.yearLevel : ''} ${l.section ? 'Sec ' + l.section : ''}` : '—' }}</td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span :class="l.status === 'valid' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-rose-600 dark:text-rose-400 font-bold'">
                    {{ l.status.toUpperCase() }}
                  </span>
                </td>
                <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ formatPhilippineFull(l.timestamp) }}</td>
                <td class="p-2.5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ l.verifiedByName }}</td>
              </tr>
              <tr v-if="scopedLogs.length === 0">
                <td colspan="9" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No scan activity logged yet.</td>
              </tr>
            </tbody>
          </table>
        </div>

        <!-- Tickets & Attendance List (Student / Outsider / All) -->
        <div v-else class="overflow-x-auto">
          <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
            <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
              <tr>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Ticket No.</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Attendee Name</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Type</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Student ID / Contact</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Course</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Year / Sec</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Attendance</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Scan Date</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Scan Time</th>
                <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Verified By</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#2c2c2c]">
              <tr v-for="(t, idx) in reportData" :key="t.id" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
                <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
                <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ t.ticketNumber || t.id }}</td>
                <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ t.currentAttendeeName }}</td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span
                    class="px-2 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider"
                    :class="t.attendeeType === 'OUTSIDER'
                      ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800'
                      : 'bg-blue-100 dark:bg-blue-950/60 text-blue-700 dark:text-blue-400 border border-blue-300 dark:border-blue-800'"
                  >
                    {{ t.attendeeType || 'STUDENT' }}
                  </span>
                </td>
                <td class="p-2.5 font-mono text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">
                  {{ t.attendeeStudentId || t.contactNumber || '—' }}
                </td>
                <td class="p-2.5 font-semibold text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">
                  {{ t.attendeeType === 'OUTSIDER' ? '—' : (t.course || 'BSIT') }}
                </td>
                <td class="p-2.5 font-mono text-[11px] text-slate-600 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">
                  {{ t.attendeeType === 'OUTSIDER' ? '—' : `${t.yearLevel || '3rd Year'} / ${t.section || 'A'}` }}
                </td>
                <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                  <span :class="t.attendanceStatus === 'present' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : 'text-slate-500 font-bold'">
                    {{ t.attendanceStatus === 'present' ? 'PRESENT' : 'NOT ENTERED' }}
                  </span>
                </td>
                <td class="p-2.5 text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">
                  {{ t.timeIn ? formatPhilippineDate(t.timeIn) : '—' }}
                </td>
                <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">
                  {{ t.timeIn ? formatPhilippineTime(t.timeIn) : '—' }}
                </td>
                <td class="p-2.5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ t.lastScannedBy || '—' }}</td>
              </tr>
              <tr v-if="reportData.length === 0">
                <td colspan="11" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No records found for current filter.</td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import {
  Printer,
  FileSpreadsheet,
  Building2,
  GraduationCap,
  CreditCard,
  Ticket as TicketIcon,
  Users,
  Tag,
  ShieldCheck,
  Filter,
  RotateCcw
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import {
  exportTicketsToExcel,
  exportStudentAttendanceOnlyToExcel,
  exportOutsiderAttendanceOnlyToExcel,
  exportAttendanceLogsToExcel,
  exportTransactionsToExcel,
  exportAdminReportsMasterWorkbookToExcel
} from '../../utils/excelExport';
import { formatPhilippineFull, formatPhilippineTime, formatPhilippineDate } from '../../utils/philippineTime';

const {
  currentUser,
  tickets,
  events,
  currentEvent,
  logs,
  transactions,
  users,
  studentRecords,
  academicPeriods,
  activeAcademicPeriod,
  fetchTicketSalesSummary
} = useAppStore();

const isAdmin = computed(() => currentUser.value?.role === 'admin');
const isTeacher = computed(() => currentUser.value?.role === 'teacher');

// Active Section: 'attendance' | 'sales'
const activeSection = ref<'attendance' | 'sales'>('attendance');

// Scoping & Multi-Criteria Filtering State
const selectedPeriodId = ref<string>(activeAcademicPeriod.value?.id || 'all');
const selectedEventId = ref<string>('all');
const selectedAttendeeType = ref<'all' | 'STUDENT' | 'OUTSIDER'>('all');
const selectedCourse = ref<string>('all');
const selectedYearLevel = ref<string>('all');
const selectedSection = ref<string>('all');
const selectedDateFrom = ref<string>('');
const selectedDateTo = ref<string>('');

const salesSubTab = ref<'events' | 'sections' | 'transactions'>('events');

const currentPeriod = computed(() => {
  if (selectedPeriodId.value === 'all') return null;
  return academicPeriods.value.find(p => p.id === selectedPeriodId.value) || null;
});

const periodScopedEvents = computed(() => {
  if (!currentPeriod.value) return events.value;
  return events.value.filter(e => e.academicYear === currentPeriod.value?.academicYear && e.semester === currentPeriod.value?.semester);
});

const selectedEvent = computed(() => {
  if (selectedEventId.value !== 'all') {
    return events.value.find(e => e.id === selectedEventId.value) || null;
  }
  return null;
});

const displayAcademicPeriodText = computed(() => {
  if (currentPeriod.value) {
    return `A.Y. ${currentPeriod.value.academicYear} • ${currentPeriod.value.semester}`;
  }
  if (selectedEvent.value) {
    return `A.Y. ${selectedEvent.value.academicYear || '2026-2027'} • ${selectedEvent.value.semester || '1st Semester'}`;
  }
  return 'All Academic Periods';
});

const displayEventText = computed(() => {
  if (selectedEvent.value) {
    return `${selectedEvent.value.title} (${selectedEvent.value.code})`;
  }
  if (currentPeriod.value) {
    return `All Events in A.Y. ${currentPeriod.value.academicYear} ${currentPeriod.value.semester}`;
  }
  return currentEvent.value?.title || 'All Events Across All Periods';
});

const displayEventVenue = computed(() => {
  if (selectedEvent.value) return selectedEvent.value.venue;
  return currentEvent.value?.venue || '';
});

// Teacher Assigned Classes
const teacherAssignedClasses = computed(() => {
  return currentUser.value?.assignedClasses || [];
});

// Dynamic Available Filter Options
const availableCourses = computed(() => {
  const set = new Set<string>();
  studentRecords.value.forEach(sr => {
    if (sr.course) set.add(sr.course.trim().toUpperCase());
  });
  tickets.value.forEach(t => {
    if (t.course && (t.attendeeType || 'STUDENT') === 'STUDENT') set.add(t.course.trim().toUpperCase());
  });
  return Array.from(set).sort();
});

const availableYearLevels = computed(() => {
  return ['1st Year', '2nd Year', '3rd Year', '4th Year'];
});

const availableSections = computed(() => {
  const set = new Set<string>();
  studentRecords.value.forEach(sr => {
    if (sr.section) set.add(sr.section.trim().toUpperCase());
  });
  tickets.value.forEach(t => {
    if (t.section && (t.attendeeType || 'STUDENT') === 'STUDENT') set.add(t.section.trim().toUpperCase());
  });
  return Array.from(set).sort();
});

const hasActiveSecondaryFilters = computed(() => {
  return (
    selectedAttendeeType.value !== 'all' ||
    selectedCourse.value !== 'all' ||
    selectedYearLevel.value !== 'all' ||
    selectedSection.value !== 'all' ||
    Boolean(selectedDateFrom.value) ||
    Boolean(selectedDateTo.value)
  );
});

const resetSecondaryFilters = () => {
  selectedAttendeeType.value = 'all';
  selectedCourse.value = 'all';
  selectedYearLevel.value = 'all';
  selectedSection.value = 'all';
  selectedDateFrom.value = '';
  selectedDateTo.value = '';
};

// Filtered Pool of Tickets (authoritative scoping)
const authorizedTickets = computed(() => {
  let pool = tickets.value.filter(t => {
    const status = (t.ticketStatus || '').toUpperCase();
    return status !== 'CANCELLED';
  });

  // Strict Academic Period Scoping
  if (currentPeriod.value) {
    pool = pool.filter(t => {
      const ev = events.value.find(e => e.id === t.eventId);
      const ayr = t.academicYear || ev?.academicYear;
      const sem = t.semester || ev?.semester;
      return ayr === currentPeriod.value?.academicYear && sem === currentPeriod.value?.semester;
    });
  }

  // Event Scoping
  if (selectedEventId.value !== 'all') {
    pool = pool.filter(t => t.eventId === selectedEventId.value);
  }

  // Attendee Type Scoping
  if (selectedAttendeeType.value !== 'all') {
    pool = pool.filter(t => (t.attendeeType || 'STUDENT').toUpperCase() === selectedAttendeeType.value);
  }

  // Course Scoping (for students)
  if (selectedCourse.value !== 'all') {
    pool = pool.filter(t => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT' && (t.course || '').toUpperCase() === selectedCourse.value.toUpperCase());
  }

  // Year Level Scoping (for students)
  if (selectedYearLevel.value !== 'all') {
    pool = pool.filter(t => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT' && (t.yearLevel || '').toLowerCase() === selectedYearLevel.value.toLowerCase());
  }

  // Section Scoping (for students)
  if (selectedSection.value !== 'all') {
    pool = pool.filter(t => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT' && (t.section || '').toUpperCase() === selectedSection.value.toUpperCase());
  }

  // Date Range Scoping
  if (selectedDateFrom.value) {
    pool = pool.filter(t => {
      const pDate = t.purchaseDate ? t.purchaseDate.slice(0, 10) : '';
      return pDate ? pDate >= selectedDateFrom.value : true;
    });
  }
  if (selectedDateTo.value) {
    pool = pool.filter(t => {
      const pDate = t.purchaseDate ? t.purchaseDate.slice(0, 10) : '';
      return pDate ? pDate <= selectedDateTo.value : true;
    });
  }

  if (!isTeacher.value) return pool;

  // Strict Teacher Scope: Students in assigned classes only, never Outsiders
  return pool.filter(t => {
    if (t.attendeeType === 'OUTSIDER') return false;
    const tCourse = (t.course || '').trim().toLowerCase();
    const tSection = (t.section || '').trim().toUpperCase();
    return teacherAssignedClasses.value.some(
      ac => ac.course.trim().toLowerCase() === tCourse && ac.section.trim().toUpperCase() === tSection
    );
  });
});

// Authoritative Valid Tickets for Financial Sales (Excludes VOID, CANCELLED, REFUNDED; Includes USED)
const validSalesTickets = computed(() => {
  return authorizedTickets.value.filter(t => {
    const status = (t.ticketStatus || '').toUpperCase();
    if (status === 'VOID' || status === 'CANCELLED' || status === 'REFUNDED') return false;
    const payStatus = (t.paymentStatus || '').toLowerCase();
    return payStatus === 'paid' || payStatus === 'free';
  });
});

// 6 AUTHORITATIVE FINANCIAL METRICS
const isPaidTicket = (t: any) => (t.paymentStatus || 'paid').toLowerCase() === 'paid';
const isFreeTicket = (t: any) => (t.paymentStatus || '').toLowerCase() === 'free';
const isStudentAttendee = (t: any) => (t.attendeeType || 'STUDENT').toUpperCase() === 'STUDENT';
const isOutsiderAttendee = (t: any) => (t.attendeeType || '').toUpperCase() === 'OUTSIDER';

const paidStudentSold = computed(() => validSalesTickets.value.filter(t => isStudentAttendee(t) && isPaidTicket(t)).length);
const paidOutsiderSold = computed(() => validSalesTickets.value.filter(t => isOutsiderAttendee(t) && isPaidTicket(t)).length);
const totalPaidSold = computed(() => paidStudentSold.value + paidOutsiderSold.value);

const freeStudentIssued = computed(() => validSalesTickets.value.filter(t => isStudentAttendee(t) && isFreeTicket(t)).length);
const freeOutsiderIssued = computed(() => validSalesTickets.value.filter(t => isOutsiderAttendee(t) && isFreeTicket(t)).length);
const freeIssued = computed(() => freeStudentIssued.value + freeOutsiderIssued.value);

const totalValidPasses = computed(() => totalPaidSold.value + freeIssued.value);
const totalCollectedSales = computed(() => {
  return validSalesTickets.value.reduce((sum, t) => sum + (isPaidTicket(t) ? (Number(t.price) || 0) : 0), 0);
});
const studentCollectedSales = computed(() => {
  return validSalesTickets.value.filter(t => isStudentAttendee(t) && isPaidTicket(t)).reduce((sum, t) => sum + (Number(t.price) || 0), 0);
});
const outsiderCollectedSales = computed(() => {
  return validSalesTickets.value.filter(t => isOutsiderAttendee(t) && isPaidTicket(t)).reduce((sum, t) => sum + (Number(t.price) || 0), 0);
});

// Sales Breakdown per Event
const salesByEventList = computed(() => {
  const scopedEvents = selectedEventId.value !== 'all'
    ? events.value.filter(e => e.id === selectedEventId.value)
    : periodScopedEvents.value;

  return scopedEvents.map(ev => {
    const evTix = validSalesTickets.value.filter(t => t.eventId === ev.id);
    const evPaidStudent = evTix.filter(t => isStudentAttendee(t) && isPaidTicket(t)).length;
    const evPaidOutsider = evTix.filter(t => isOutsiderAttendee(t) && isPaidTicket(t)).length;
    const evTotalPaid = evPaidStudent + evPaidOutsider;
    const evFree = evTix.filter(t => isFreeTicket(t)).length;
    const evTotalValid = evTotalPaid + evFree;
    const evRev = evTix.reduce((sum, t) => sum + (isPaidTicket(t) ? (Number(t.price) || 0) : 0), 0);

    return {
      eventId: ev.id,
      eventCode: ev.code || 'NCST',
      eventTitle: ev.title,
      academicYear: ev.academicYear || '2026-2027',
      semester: ev.semester || '1st Semester',
      eventPrice: ev.ticketPrice || 0,
      paidStudentTickets: evPaidStudent,
      paidOutsiderTickets: evPaidOutsider,
      totalPaidTickets: evTotalPaid,
      freeTicketsIssued: evFree,
      totalValidPasses: evTotalValid,
      totalCollectedSales: evRev,
    };
  });
});

// Student Sales Breakdown by Section (strictly students, outsiders excluded)
const studentSalesBySectionList = computed(() => {
  const studentValid = validSalesTickets.value.filter(t => isStudentAttendee(t));
  const map = new Map<string, {
    key: string;
    course: string;
    yearLevel: string;
    section: string;
    paidCount: number;
    freeCount: number;
    totalPasses: number;
    revenue: number;
  }>();

  studentValid.forEach(t => {
    const course = (t.course || 'Unassigned').trim().toUpperCase();
    const yearLevel = (t.yearLevel || 'N/A').trim();
    const section = (t.section || 'Unassigned').trim().toUpperCase();
    const key = `${course}___${yearLevel}___${section}`;

    const cur = map.get(key) || {
      key,
      course,
      yearLevel,
      section,
      paidCount: 0,
      freeCount: 0,
      totalPasses: 0,
      revenue: 0
    };

    if (isPaidTicket(t)) {
      cur.paidCount += 1;
      cur.revenue += Number(t.price) || 0;
    } else {
      cur.freeCount += 1;
    }
    cur.totalPasses += 1;
    map.set(key, cur);
  });

  return Array.from(map.values()).sort(
    (a, b) => a.course.localeCompare(b.course) || a.section.localeCompare(b.section)
  );
});

// Scoped Purchase Transactions for Admin
const scopedTransactions = computed(() => {
  if (isTeacher.value) return [];
  let pool = transactions.value;

  if (currentPeriod.value) {
    pool = pool.filter(tx => {
      const ev = events.value.find(e => e.id === tx.eventId);
      const ayr = tx.academicYear || ev?.academicYear;
      const sem = tx.semester || ev?.semester;
      return ayr === currentPeriod.value?.academicYear && sem === currentPeriod.value?.semester;
    });
  }

  if (selectedEventId.value !== 'all') {
    pool = pool.filter(tx => tx.eventId === selectedEventId.value);
  }

  if (selectedAttendeeType.value !== 'all') {
    pool = pool.filter(tx => (tx.attendeeType || 'STUDENT').toUpperCase() === selectedAttendeeType.value);
  }

  if (selectedCourse.value !== 'all') {
    pool = pool.filter(tx => (tx.course || '').toUpperCase() === selectedCourse.value.toUpperCase());
  }

  if (selectedYearLevel.value !== 'all') {
    pool = pool.filter(tx => (tx.yearLevel || '').toLowerCase() === selectedYearLevel.value.toLowerCase());
  }

  if (selectedSection.value !== 'all') {
    pool = pool.filter(tx => (tx.section || '').toUpperCase() === selectedSection.value.toUpperCase());
  }

  if (selectedDateFrom.value) {
    pool = pool.filter(tx => {
      const pDate = tx.purchaseDate ? tx.purchaseDate.slice(0, 10) : '';
      return pDate ? pDate >= selectedDateFrom.value : true;
    });
  }

  if (selectedDateTo.value) {
    pool = pool.filter(tx => {
      const pDate = tx.purchaseDate ? tx.purchaseDate.slice(0, 10) : '';
      return pDate ? pDate <= selectedDateTo.value : true;
    });
  }

  return pool;
});

// Attendance Report Data
const reportType = ref<'all' | 'students' | 'outsiders' | 'present' | 'absent' | 'audit_logs'>('all');

const reportData = computed(() => {
  return authorizedTickets.value.filter(ticket => {
    switch (reportType.value) {
      case 'students':
        return (ticket.attendeeType || 'STUDENT') === 'STUDENT';
      case 'outsiders':
        return !isTeacher.value && ticket.attendeeType === 'OUTSIDER';
      case 'present':
        return ticket.attendanceStatus === 'present';
      case 'absent':
        return ticket.attendanceStatus === 'not_entered';
      case 'all':
      default:
        return true;
    }
  });
});

const scopedLogs = computed(() => {
  let pool = logs.value;

  if (currentPeriod.value) {
    pool = pool.filter(l => {
      const ev = events.value.find(e => e.id === l.eventId);
      const ayr = l.academicYear || ev?.academicYear;
      const sem = l.semester || ev?.semester;
      return ayr === currentPeriod.value?.academicYear && sem === currentPeriod.value?.semester;
    });
  }

  if (selectedEventId.value !== 'all') {
    pool = pool.filter(l => l.eventId === selectedEventId.value);
  }

  return pool;
});

// Attendance Counts
const totalCount = computed(() => authorizedTickets.value.length);
const presentCount = computed(() => authorizedTickets.value.filter(t => t.attendanceStatus === 'present').length);
const qrPendingCount = computed(() => authorizedTickets.value.filter(t => t.qrPrintStatus === 'PENDING' || !t.qrPrintStatus).length);
const qrPrintedCount = computed(() => authorizedTickets.value.filter(t => t.qrPrintStatus === 'PRINTED' || t.qrPrintStatus === 'ATTACHED').length);
const notYetScannedCount = computed(() => authorizedTickets.value.filter(t => t.attendanceStatus === 'not_entered').length);
const studentsTotalCount = computed(() => authorizedTickets.value.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT').length);
const outsidersTotalCount = computed(() => isTeacher.value ? 0 : authorizedTickets.value.filter(t => t.attendeeType === 'OUTSIDER').length);

const reportTabs = computed(() => {
  if (isTeacher.value) {
    return [
      { id: 'all', label: 'All Assigned Students', count: totalCount.value },
      { id: 'present', label: 'Present Students', count: presentCount.value },
      { id: 'absent', label: 'Not Yet Entered', count: notYetScannedCount.value },
    ];
  }

  return [
    { id: 'all', label: 'All Tickets', count: totalCount.value },
    { id: 'students', label: 'Student Attendance', count: studentsTotalCount.value },
    { id: 'outsiders', label: 'Outsider Attendance', count: outsidersTotalCount.value },
    { id: 'present', label: 'Present Attendees', count: presentCount.value },
    { id: 'absent', label: 'Unentered / Absent', count: notYetScannedCount.value },
    { id: 'audit_logs', label: 'Scan Audit Logs', count: scopedLogs.value.length },
  ];
});

const salesSubTabs = computed(() => [
  { id: 'events', label: 'Sales by Event', count: salesByEventList.value.length },
  { id: 'sections', label: 'Student Sales by Section', count: studentSalesBySectionList.value.length },
  { id: 'transactions', label: 'Purchase Transactions', count: scopedTransactions.value.length },
]);

const formatCurrency = (val: any) => {
  return Number(val || 0).toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
};

// Excel Export Handler
const handleExportExcel = () => {
  const periodTag = currentPeriod.value ? `${currentPeriod.value.academicYear}_${currentPeriod.value.semester.replace(/\s+/g, '_')}` : 'All_Periods';

  if (activeSection.value === 'attendance') {
    if (reportType.value === 'audit_logs') {
      exportAttendanceLogsToExcel(scopedLogs.value, `${periodTag}_Scan_Audit_Logs.xlsx`);
      return;
    } else if (reportType.value === 'students' || isTeacher.value) {
      exportStudentAttendanceOnlyToExcel(reportData.value, events.value, `${periodTag}_${currentUser.value?.name || 'Teacher'}_Student_Attendance.xlsx`);
      return;
    } else if (reportType.value === 'outsiders') {
      exportOutsiderAttendanceOnlyToExcel(reportData.value, events.value, `${periodTag}_Outsider_Attendance.xlsx`);
      return;
    }
  } else if (activeSection.value === 'sales' && salesSubTab.value === 'transactions') {
    exportTransactionsToExcel(scopedTransactions.value, `${periodTag}_Purchase_Transactions.xlsx`);
    return;
  }

  // Authoritative Master Multi-Sheet Workbook with Attendance AND Revenue
  exportAdminReportsMasterWorkbookToExcel({
    tickets: authorizedTickets.value,
    events: events.value,
    transactions: scopedTransactions.value,
    academicPeriodText: displayAcademicPeriodText.value,
    eventScopeText: displayEventText.value,
    filterScopeText: `Attendee Type: ${selectedAttendeeType.value} | Course: ${selectedCourse.value} | Section: ${selectedSection.value}`,
    fileName: `${periodTag}_Attendance_And_Sales_Master_Report.xlsx`,
    salesSummary: {
      paidStudentTicketsSold: paidStudentSold.value,
      paidOutsiderTicketsSold: paidOutsiderSold.value,
      totalPaidTicketsSold: totalPaidSold.value,
      freeTicketsIssued: freeIssued.value,
      freeStudentTickets: freeStudentIssued.value,
      freeOutsiderTickets: freeOutsiderIssued.value,
      totalValidTicketsIssued: totalValidPasses.value,
      totalCollectedSales: totalCollectedSales.value,
      totalStudentTickets: paidStudentSold.value + freeStudentIssued.value,
      totalOutsiderTickets: paidOutsiderSold.value + freeOutsiderIssued.value,
    }
  });
};

const handleExportStudentExcel = () => {
  const periodTag = currentPeriod.value ? `${currentPeriod.value.academicYear}_${currentPeriod.value.semester.replace(/\s+/g, '_')}` : 'All_Periods';
  exportStudentAttendanceOnlyToExcel(
    authorizedTickets.value.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT'),
    events.value,
    `${periodTag}_${currentUser.value?.name || 'Faculty'}_Student_Attendance.xlsx`
  );
};

const handleExportOutsiderExcel = () => {
  if (isTeacher.value) return;
  const periodTag = currentPeriod.value ? `${currentPeriod.value.academicYear}_${currentPeriod.value.semester.replace(/\s+/g, '_')}` : 'All_Periods';
  exportOutsiderAttendanceOnlyToExcel(
    authorizedTickets.value.filter(t => t.attendeeType === 'OUTSIDER'),
    events.value,
    `${periodTag}_Outsider_Attendance.xlsx`
  );
};

const handlePrint = () => {
  window.print();
};

onMounted(() => {
  if (isAdmin.value) {
    fetchTicketSalesSummary(selectedEventId.value !== 'all' ? selectedEventId.value : undefined);
  }
});
</script>
