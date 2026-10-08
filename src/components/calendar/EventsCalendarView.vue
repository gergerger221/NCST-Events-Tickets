<template>
  <div class="space-y-6">
    <!-- Top Header Banner with Live Clock -->
    <div class="relative overflow-hidden rounded-3xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-6 sm:p-8 shadow-sm dark:shadow-md transition-colors">
      <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6">
        <div>
          <h1 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-3">
            <Calendar class="w-7 h-7 sm:w-8 sm:h-8 text-blue-600 dark:text-blue-400 flex-shrink-0" />
            <span>Campus Events Calendar</span>
          </h1>
          <p class="text-xs sm:text-sm text-slate-600 dark:text-[#b3b3b3] mt-1 max-w-2xl">
            Live schedule of university activities, cultural fests, sports tourneys, and academic summits.
          </p>
        </div>

        <!-- Live Digital Clock Widget -->
        <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] p-4 rounded-2xl shadow-sm">
          <div class="flex items-center gap-3">
            <div class="w-12 h-12 rounded-xl bg-blue-50 dark:bg-blue-900/30 border border-blue-200 dark:border-blue-800/50 flex items-center justify-center text-blue-600 dark:text-blue-400 flex-shrink-0">
              <Clock class="w-6 h-6 text-blue-600 dark:text-blue-400" />
            </div>
            <div>
              <div class="flex items-center gap-1.5">
                <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase tracking-wider">Current Time</span>
              </div>
              <div class="text-xl sm:text-2xl font-black font-mono text-slate-900 dark:text-white tracking-tight">
                {{ timeString }}
              </div>
              <div class="text-xs text-slate-500 dark:text-[#b3b3b3] font-medium">
                {{ fullDateString }}
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Calendar Controls, View Toggle & Category Filters -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-4 sm:p-5 rounded-2xl shadow-sm">
      <!-- Left: Month Navigation & Today Button -->
      <div class="flex flex-wrap items-center gap-2 sm:gap-3">
        <div class="flex items-center gap-1 bg-slate-100 dark:bg-[#242424] p-1 rounded-xl border border-slate-200 dark:border-[#333333]">
          <button
            @click="prevMonth"
            class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white dark:hover:bg-[#2e2e2e] rounded-lg transition cursor-pointer"
            title="Previous Month"
          >
            <ChevronLeft class="w-4 h-4" />
          </button>
          <span class="px-3 text-xs sm:text-sm font-bold text-slate-900 dark:text-white min-w-[130px] text-center">
            {{ monthNames[currentMonth] }} {{ currentYear }}
          </span>
          <button
            @click="nextMonth"
            class="p-2 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white hover:bg-white dark:hover:bg-[#2e2e2e] rounded-lg transition cursor-pointer"
            title="Next Month"
          >
            <ChevronRight class="w-4 h-4" />
          </button>
        </div>

        <button
          @click="jumpToToday"
          class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#242424] dark:hover:bg-[#2c2c2c] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-[#333333] transition cursor-pointer flex items-center gap-1.5"
        >
          <RotateCcw class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
          <span>Today</span>
        </button>

        <!-- Quick Jump to Year -->
        <select
          v-model="currentYear"
          class="px-3 py-2 bg-slate-100 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer"
        >
          <option :value="2025">2025</option>
          <option :value="2026">2026</option>
          <option :value="2027">2027</option>
        </select>
      </div>

      <!-- Right: View Mode Toggle & Search -->
      <div class="flex flex-wrap items-center gap-2.5">
        <div class="relative min-w-[180px] flex-1 sm:flex-initial">
          <Search class="w-3.5 h-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search events, venues..."
            class="w-full pl-8 pr-3 py-2 bg-slate-100 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
          />
        </div>

        <!-- View Mode: Month vs Agenda List -->
        <div class="flex items-center bg-slate-100 dark:bg-[#242424] p-1 rounded-xl border border-slate-200 dark:border-[#333333]">
          <button
            @click="viewMode = 'month'"
            class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
            :class="viewMode === 'month' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            <Grid class="w-3.5 h-3.5" />
            <span>Month</span>
          </button>
          <button
            @click="viewMode = 'agenda'"
            class="px-3 py-1.5 rounded-lg text-xs font-bold transition flex items-center gap-1.5 cursor-pointer"
            :class="viewMode === 'agenda' ? 'bg-blue-600 text-white shadow-sm' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            <List class="w-3.5 h-3.5" />
            <span>Timeline</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Category Filter Pills -->
    <div class="flex items-center gap-2 overflow-x-auto pb-1 text-xs">
      <span class="text-slate-400 font-bold uppercase text-[10px] tracking-wider shrink-0 mr-1">Filter:</span>
      <button
        v-for="cat in (['All', 'Foundation', 'Concert', 'Battle of the bands', 'Institutional', 'Academic', 'Technology', 'Sports', 'Cultural'] as const)"
        :key="cat"
        @click="selectedCategory = cat"
        class="px-3 py-1.5 rounded-xl font-bold transition cursor-pointer shrink-0"
        :class="selectedCategory === cat ? 'bg-blue-600 text-white shadow-sm' : 'bg-white dark:bg-[#1e1e1e] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333] hover:border-slate-400'"
      >
        {{ cat }}
      </button>
    </div>

    <!-- ==================== VIEW 1: MONTHLY CALENDAR GRID ==================== -->
    <div v-if="viewMode === 'month'" class="grid grid-cols-1 lg:grid-cols-12 gap-6">
      <!-- Calendar Grid Panel (8 Columns) -->
      <div class="lg:col-span-8 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-4 sm:p-6 shadow-sm dark:shadow-md space-y-4">
        <!-- Day of Week Header (Sun to Sat) -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2 text-center">
          <div
            v-for="dayName in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
            :key="dayName"
            class="py-2 text-[11px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500"
          >
            {{ dayName }}
          </div>
        </div>

        <!-- Month Day Cells -->
        <div class="grid grid-cols-7 gap-1 sm:gap-2">
          <div
            v-for="(cell, idx) in calendarCells"
            :key="idx"
            @click="selectDate(cell.dateStr)"
            class="min-h-[85px] sm:min-h-[105px] p-2 rounded-2xl border transition-all duration-150 cursor-pointer flex flex-col justify-between relative group"
            :class="[
              !cell.isCurrentMonth
                ? 'bg-slate-50/50 dark:bg-[#181818]/50 border-slate-100 dark:border-[#222222] opacity-40'
                : selectedDateStr === cell.dateStr
                ? 'bg-blue-50/60 dark:bg-blue-950/30 border-blue-500 ring-2 ring-blue-500/20 shadow-md'
                : cell.isToday
                ? 'bg-amber-50/60 dark:bg-amber-950/20 border-amber-400 dark:border-amber-600'
                : 'bg-white dark:bg-[#222222] border-slate-200/80 dark:border-[#2e2e2e] hover:border-blue-400 dark:hover:border-blue-500'
            ]"
          >
            <!-- Cell Top Header: Day Number & Today indicator -->
            <div class="flex items-center justify-between">
              <span
                class="text-xs sm:text-sm font-extrabold"
                :class="[
                  cell.isToday
                    ? 'w-6 h-6 rounded-full bg-amber-500 text-white flex items-center justify-center shadow-xs'
                    : selectedDateStr === cell.dateStr
                    ? 'text-blue-600 dark:text-blue-400 font-black'
                    : cell.isCurrentMonth
                    ? 'text-slate-800 dark:text-slate-200'
                    : 'text-slate-400 dark:text-slate-600'
                ]"
              >
                {{ cell.dayNumber }}
              </span>

              <span
                v-if="cell.isToday"
                class="text-[9px] font-bold uppercase px-1 py-0.2 bg-amber-500/20 text-amber-600 dark:text-amber-400 rounded"
              >
                TODAY
              </span>
            </div>

            <!-- Event Pills / Dots in Cell -->
            <div class="space-y-1 mt-1 overflow-hidden">
              <div
                v-for="ev in cell.events.slice(0, 2)"
                :key="ev.id"
                class="px-1.5 py-0.5 rounded-md text-[10px] font-bold truncate flex items-center gap-1 shadow-2xs"
                :class="getEventCategoryBadgeClass(ev.category)"
                :title="`${ev.title} (${ev.startTime} - ${ev.endTime})`"
              >
                <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :class="getEventStatusDotClass(ev.status)" />
                <span class="truncate">{{ ev.title }}</span>
              </div>

              <!-- More count if > 2 events -->
              <span
                v-if="cell.events.length > 2"
                class="text-[9px] font-bold text-blue-600 dark:text-blue-400 block px-1"
              >
                +{{ cell.events.length - 2 }} more
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Selected Day Event Inspector & Contextual Actions (4 Columns) -->
      <div class="lg:col-span-4 space-y-4">
        <div class="bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-5 sm:p-6 shadow-sm dark:shadow-md space-y-5">
          <!-- Day Summary Header -->
          <div class="border-b border-slate-200 dark:border-[#333333] pb-4">
            <div class="flex items-center justify-between">
              <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">Date Schedule</span>
              <span
                v-if="isTodayPHT(selectedDateStr)"
                class="text-[10px] font-extrabold uppercase px-2 py-0.5 rounded-full bg-amber-500 text-white flex items-center gap-1"
              >
                <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping" />
                <span>TODAY</span>
              </span>
            </div>
            <h3 class="text-xl font-extrabold text-slate-900 dark:text-white mt-1">
              {{ formatPhilippineDate(selectedDateStr) }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
              {{ selectedDateEvents.length }} event{{ selectedDateEvents.length === 1 ? '' : 's' }} scheduled for this date
            </p>
          </div>

          <!-- List of events on this selected date -->
          <div v-if="selectedDateEvents.length > 0" class="space-y-4">
            <div
              v-for="ev in selectedDateEvents"
              :key="ev.id"
              class="p-4 rounded-2xl border bg-slate-50/70 dark:bg-[#242424]/70 border-slate-200 dark:border-[#333333] space-y-3 transition hover:shadow-md"
            >
              <!-- Event Cover Thumbnail & Title Header -->
              <div class="flex items-start gap-3">
                <EventCoverImage
                  :src="ev.bannerUrl"
                  :alt="`${ev.title} event cover`"
                  :title="ev.title"
                  :category="ev.category"
                  :code="ev.code"
                  size="sm"
                  rounded="xl"
                />

                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-full" :class="getEventCategoryBadgeClass(ev.category)">
                      {{ ev.category }}
                    </span>
                    <span
                      class="text-[9px] font-bold uppercase px-1.5 py-0.5 rounded"
                      :class="{
                        'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': ev.status === 'ongoing',
                        'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300': ev.status === 'upcoming',
                        'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300': ev.status === 'completed',
                        'bg-rose-100 text-rose-700 dark:bg-rose-950/60 dark:text-rose-300': ev.status === 'cancelled',
                      }"
                    >
                      {{ ev.status }}
                    </span>
                  </div>
                  <h4 class="text-xs sm:text-sm font-extrabold text-slate-900 dark:text-white mt-1 leading-snug truncate">
                    {{ ev.title }}
                  </h4>
                  <p class="text-[11px] font-mono text-blue-600 dark:text-blue-400 font-bold mt-0.5">{{ ev.code }}</p>
                </div>
              </div>

              <!-- Time & Venue -->
              <div class="space-y-1 text-xs text-slate-600 dark:text-[#b3b3b3]">
                <div class="flex items-center gap-2">
                  <Clock class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
                  <span class="font-semibold">{{ ev.startTime }} - {{ ev.endTime }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <MapPin class="w-3.5 h-3.5 text-slate-400 shrink-0" />
                  <span class="truncate">{{ ev.venue }}</span>
                </div>
                <div class="flex items-center gap-2">
                  <TicketIcon class="w-3.5 h-3.5 text-purple-600 dark:text-purple-400 shrink-0" />
                  <span>{{ ev.ticketPrice > 0 ? `₱${ev.ticketPrice}.00 admission` : 'Free admission' }}</span>
                </div>
              </div>

              <!-- Hierarchical Context Actions -->
              <div class="pt-2 border-t border-slate-200 dark:border-[#3a3a3a] flex flex-wrap items-center gap-2">
                <button
                  @click="openEventDetails(ev)"
                  class="py-2 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-[#282828] dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer flex items-center justify-center gap-1"
                  title="View Event Details"
                >
                  <Info class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                  <span>Info</span>
                </button>
                <!-- Admin action: Select active event or edit -->
                <button
                  v-if="currentUser?.role === 'admin'"
                  @click="handleSelectActiveEvent(ev)"
                  class="flex-1 py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer text-center"
                >
                  {{ currentEvent?.id === ev.id ? 'Active Event Selected' : 'Set as Active Event' }}
                </button>

                <!-- Teacher action: Navigate to student roster -->
                <button
                  v-else-if="currentUser?.role === 'teacher'"
                  @click="handleTeacherViewRoster(ev)"
                  class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer text-center flex items-center justify-center gap-1.5"
                >
                  <Users class="w-3.5 h-3.5" />
                  <span>View Student Roster</span>
                </button>

                <!-- Cashier action: Open POS for event -->
                <button
                  v-else-if="currentUser?.role === 'cashier'"
                  @click="handleCashierOpenPOS(ev)"
                  class="flex-1 py-2 px-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer text-center flex items-center justify-center gap-1.5"
                >
                  <CreditCard class="w-3.5 h-3.5" />
                  <span>Issue Tickets (POS)</span>
                </button>

                <!-- Student action: View Event Details -->
                <button
                  v-else-if="currentUser?.role === 'student'"
                  @click="openEventDetails(ev)"
                  class="flex-1 py-2 px-3 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer text-center flex items-center justify-center gap-1.5"
                >
                  <Eye class="w-3.5 h-3.5" />
                  <span>View Event &rarr;</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Empty State when no events on selected date -->
          <div v-else class="text-center py-8 space-y-2 text-slate-400 dark:text-slate-500">
            <Calendar class="w-10 h-10 mx-auto opacity-40" />
            <p class="text-xs font-semibold">No campus activities on this date.</p>
            <p class="text-[11px]">Select another highlighted date on the calendar.</p>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== VIEW 2: TIMELINE / AGENDA LIST VIEW ==================== -->
    <div v-else class="space-y-4">
      <div v-if="filteredEventsTimeline.length > 0" class="space-y-4">
        <div
          v-for="ev in filteredEventsTimeline"
          :key="ev.id"
          class="p-4 sm:p-5 rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-4 sm:gap-5 transition hover:border-blue-500 hover:shadow-md cursor-pointer group"
          @click="openEventDetails(ev)"
        >
          <!-- Left: Compact Cover Image & Event Specifications -->
          <div class="flex flex-col sm:flex-row items-start sm:items-center gap-4 flex-1 min-w-0">
            <!-- 1. Event Cover Image (16:9 Landscape Ratio • Fixed Dimensions) -->
            <EventCoverImage
              :src="ev.bannerUrl"
              :alt="`${ev.title} event cover`"
              :title="ev.title"
              :category="ev.category"
              :code="ev.code"
              size="md"
              rounded="2xl"
            />

            <!-- 2. Content Area (Primary Information Outside Image) -->
            <div class="space-y-1.5 flex-1 min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full" :class="getEventCategoryBadgeClass(ev.category)">
                  {{ ev.category }}
                </span>
                <span
                  v-if="isTodayPHT(ev.date)"
                  class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full bg-amber-500 text-white flex items-center gap-1"
                >
                  <span class="w-1.5 h-1.5 rounded-full bg-white animate-ping" />
                  <span>TODAY</span>
                </span>
                <span
                  class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-md"
                  :class="{
                    'bg-emerald-100 text-emerald-700 dark:bg-emerald-950/60 dark:text-emerald-300': ev.status === 'ongoing',
                    'bg-blue-100 text-blue-700 dark:bg-blue-950/60 dark:text-blue-300': ev.status === 'upcoming',
                    'bg-slate-200 text-slate-700 dark:bg-slate-800 dark:text-slate-300': ev.status === 'completed',
                  }"
                >
                  {{ ev.status }}
                </span>
                <span class="text-xs font-mono text-slate-400 dark:text-slate-500 font-semibold">
                  {{ ev.code }}
                </span>
              </div>

              <!-- Event Title -->
              <h3 class="text-base sm:text-lg font-extrabold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 transition-colors leading-snug">
                {{ ev.title }}
              </h3>

              <p v-if="ev.description" class="text-xs text-slate-600 dark:text-slate-300 line-clamp-2">
                {{ ev.description }}
              </p>

              <!-- Date, Time & Venue Hierarchy -->
              <div class="text-xs text-slate-500 dark:text-[#b3b3b3] flex flex-wrap items-center gap-x-4 gap-y-1 pt-0.5">
                <span class="flex items-center gap-1 text-blue-600 dark:text-blue-400 font-semibold">
                  <Clock class="w-3.5 h-3.5" />
                  {{ formatPhilippineDate(ev.date) }} • {{ ev.startTime }} - {{ ev.endTime }}
                </span>
                <span class="flex items-center gap-1">
                  <MapPin class="w-3.5 h-3.5 text-slate-400" />
                  {{ ev.venue }}
                </span>
                <span class="flex items-center gap-1">
                  <UserCheck class="w-3.5 h-3.5 text-slate-400" />
                  {{ ev.organizer }}
                </span>
              </div>
            </div>
          </div>

          <!-- Right: Pricing & User Actions -->
          <div class="flex items-center gap-3 self-end md:self-center shrink-0 pt-2 md:pt-0 border-t md:border-t-0 border-slate-100 dark:border-[#2a2a2a] w-full md:w-auto justify-between md:justify-end" @click.stop>
            <div class="text-left md:text-right">
              <span class="text-xs font-black text-slate-900 dark:text-white block">
                {{ ev.ticketPrice > 0 ? `PHP ${ev.ticketPrice}.00` : 'FREE ADMISSION' }}
              </span>
            </div>

            <div class="flex items-center gap-2">
              <button
                @click="openEventDetails(ev)"
                class="px-3.5 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#282828] dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 text-xs font-bold rounded-xl transition cursor-pointer flex items-center gap-1.5"
                title="View Full Event Details"
              >
                <Info class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
                <span>Event Info</span>
              </button>

              <button
                v-if="currentUser?.role === 'admin'"
                @click="handleSelectActiveEvent(ev)"
                class="px-3.5 py-2 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
                :class="currentEvent?.id === ev.id ? 'bg-emerald-600 hover:bg-emerald-700' : 'bg-blue-600 hover:bg-blue-700'"
              >
                {{ currentEvent?.id === ev.id ? 'Active' : 'Set Active' }}
              </button>
              <button
                v-else-if="currentUser?.role === 'teacher'"
                @click="handleTeacherViewRoster(ev)"
                class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5"
              >
                <Users class="w-3.5 h-3.5" />
                <span>Roster</span>
              </button>
              <button
                v-else-if="currentUser?.role === 'cashier'"
                @click="handleCashierOpenPOS(ev)"
                class="px-3.5 py-2 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5"
              >
                <CreditCard class="w-3.5 h-3.5" />
                <span>POS</span>
              </button>
              <button
                v-else-if="currentUser?.role === 'student'"
                @click="openEventDetails(ev)"
                class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer flex items-center gap-1.5"
              >
                <Eye class="w-3.5 h-3.5" />
                <span>View Event &rarr;</span>
              </button>
            </div>
          </div>
        </div>
      </div>

      <div v-else class="p-12 text-center rounded-3xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] space-y-3">
        <Search class="w-10 h-10 text-slate-400 mx-auto" />
        <h3 class="text-base font-bold text-slate-900 dark:text-white">No Matching Events Found</h3>
        <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">Try clearing search keywords or selecting "All" categories.</p>
      </div>
    </div>

    <!-- Full Event Specifications & Information Modal for All Users (Section 10: Event Details Large Cover) -->
    <Modal
      :is-open="!!selectedDetailEvent"
      @close="selectedDetailEvent = null"
      :title="selectedDetailEvent?.title || 'Event Information'"
      :subtitle="`${selectedDetailEvent?.code || ''} • ${selectedDetailEvent?.category || ''}`"
      max-width="2xl"
    >
      <div v-if="selectedDetailEvent" class="space-y-4">
        <!-- Prominent Large 16:9 Event Cover Image Header Banner -->
        <div class="relative w-full aspect-[16/9] rounded-2xl overflow-hidden border border-slate-200/70 dark:border-slate-800/60 shadow-xs">
          <EventCoverImage
            :src="selectedDetailEvent.bannerUrl"
            :alt="`${selectedDetailEvent.title} large cover`"
            :title="selectedDetailEvent.title"
            :category="selectedDetailEvent.category"
            :code="selectedDetailEvent.code"
            size="full"
            rounded="none"
          />

          <!-- Gradient overlay on cover -->
          <div class="absolute inset-0 bg-gradient-to-t from-black/85 via-black/35 to-transparent flex flex-col justify-end p-4 sm:p-5 text-white">
            <div class="flex items-center gap-2 mb-1.5 flex-wrap">
              <span class="text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full bg-blue-600/90 backdrop-blur-md text-white border border-blue-400/30">
                {{ selectedDetailEvent.category }}
              </span>
              <span
                class="text-[10px] font-bold uppercase px-2.5 py-0.5 rounded-full backdrop-blur-md"
                :class="{
                  'bg-emerald-600/90 text-white': selectedDetailEvent.status === 'ongoing',
                  'bg-blue-600/90 text-white': selectedDetailEvent.status === 'upcoming',
                  'bg-slate-700/90 text-slate-200': selectedDetailEvent.status === 'completed',
                  'bg-rose-600/90 text-white': selectedDetailEvent.status === 'cancelled',
                }"
              >
                {{ selectedDetailEvent.status }}
              </span>
            </div>

            <h3 class="text-base sm:text-lg font-black text-white leading-snug drop-shadow-sm">
              {{ selectedDetailEvent.title }}
            </h3>
            <p class="text-xs text-slate-200 mt-0.5 flex items-center gap-2 drop-shadow-xs">
              <span>{{ formatPhilippineDate(selectedDetailEvent.date) }}</span>
              <span>•</span>
              <span>{{ selectedDetailEvent.venue }}</span>
            </p>
          </div>
        </div>

        <!-- Event Code & Admission Bar -->
        <div class="flex flex-wrap items-center justify-between gap-2 p-3 rounded-2xl bg-slate-50/70 dark:bg-[#1a1a1a] border border-slate-200/60 dark:border-slate-800/60">
          <div class="flex items-center gap-2">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Event Code:</span>
            <span class="font-mono font-bold text-blue-600 dark:text-blue-400 text-xs px-2 py-0.5 bg-blue-50/80 dark:bg-blue-900/30 rounded-md border border-blue-200/60 dark:border-blue-800/40">
              {{ selectedDetailEvent.code }}
            </span>
          </div>

          <div class="text-right">
            <span
              class="text-xs sm:text-sm font-extrabold"
              :class="selectedDetailEvent.ticketPrice > 0 ? 'text-slate-900 dark:text-white' : 'text-emerald-600 dark:text-emerald-400'"
            >
              {{ selectedDetailEvent.ticketPrice > 0 ? `PHP ${selectedDetailEvent.ticketPrice}.00` : 'FREE ADMISSION' }}
            </span>
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block text-[10px] font-bold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
            Event Overview
          </label>
          <p class="text-xs sm:text-sm text-slate-700 dark:text-slate-300 leading-relaxed bg-slate-50/60 dark:bg-[#181818] p-3.5 rounded-2xl border border-slate-200/60 dark:border-slate-800/60">
            {{ selectedDetailEvent.description || 'No detailed description provided for this campus event.' }}
          </p>
        </div>

        <!-- Key Specifications Grid (Balanced 2-Column Grid) -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          <!-- Date & Schedule -->
          <div class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-[#181818] border border-slate-200/60 dark:border-slate-800/60 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Schedule</span>
            <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
              <Clock class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
              <span>{{ formatPhilippineDate(selectedDetailEvent.date) }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-6">{{ selectedDetailEvent.startTime }} - {{ selectedDetailEvent.endTime }}</p>
          </div>

          <!-- Venue & Event Location -->
          <div class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-[#181818] border border-slate-200/60 dark:border-slate-800/60 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Venue Location</span>
            <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
              <MapPin class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" />
              <span class="truncate">{{ selectedDetailEvent.venue }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-6 truncate">Campus Location</p>
          </div>

          <!-- Organizer -->
          <div v-if="selectedDetailEvent.organizer" class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-[#181818] border border-slate-200/60 dark:border-slate-800/60 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Organizer / Host</span>
            <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
              <UserCheck class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" />
              <span class="truncate">{{ selectedDetailEvent.organizer }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-6">Official NCST Sanctioned</p>
          </div>

          <!-- Admission Type -->
          <div class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-[#181818] border border-slate-200/60 dark:border-slate-800/60 space-y-1">
            <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider block">Admission Type</span>
            <div class="flex items-center gap-2 font-bold text-slate-900 dark:text-white">
              <TicketIcon class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0" />
              <span>{{ selectedDetailEvent.ticketPrice > 0 ? `Paid Pass (PHP ${selectedDetailEvent.ticketPrice}.00)` : 'Free Admission' }}</span>
            </div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 pl-6">NCST Cashier Issuance</p>
          </div>
        </div>

        <!-- Event Entry Requirements & Guidelines -->
        <div class="p-3.5 rounded-2xl bg-slate-50/60 dark:bg-[#181818] border border-slate-200/60 dark:border-slate-800/60 space-y-2">
          <div class="flex items-center gap-2 text-slate-700 dark:text-slate-300 font-bold text-xs uppercase tracking-wider">
            <ShieldCheck class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
            <span>Event Entry Guidelines</span>
          </div>
          <ul class="text-xs text-slate-600 dark:text-slate-400 space-y-1 pl-5 list-disc leading-relaxed">
            <li>Official NCST Student ID is required for identity verification at the entrance.</li>
            <li>Adhere strictly to university dress code and campus code of conduct.</li>
            <li>Venue entry is subject to official campus admission guidelines.</li>
            <li>Official issued passes with dynamic QR codes are scanned and verified by authorized Event Staff.</li>
          </ul>
        </div>

        <!-- Ticket Information & Cashier Workflow Notice -->
        <div
          class="p-3.5 rounded-2xl transition-colors"
          :class="selectedDetailEvent.ticketPrice > 0 
            ? 'bg-slate-50/80 dark:bg-[#1a1a1a] border border-slate-200/70 dark:border-slate-800/60' 
            : 'bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-100 dark:border-emerald-900/30'"
        >
          <div class="flex items-start gap-3">
            <div
              class="p-2 rounded-xl shrink-0"
              :class="selectedDetailEvent.ticketPrice > 0 
                ? 'bg-blue-50 dark:bg-blue-950/50 text-blue-700 dark:text-blue-300' 
                : 'bg-emerald-100/80 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300'"
            >
              <TicketIcon class="w-4 h-4" />
            </div>
            <div class="space-y-0.5 flex-1">
              <span
                class="text-xs font-bold block"
                :class="selectedDetailEvent.ticketPrice > 0 ? 'text-slate-900 dark:text-white' : 'text-emerald-900 dark:text-emerald-200'"
              >
                {{ selectedDetailEvent.ticketPrice > 0 ? `Ticket Price: ₱${selectedDetailEvent.ticketPrice}.00` : 'Free Admission' }}
              </span>
              <p
                class="text-xs leading-relaxed"
                :class="selectedDetailEvent.ticketPrice > 0 ? 'text-slate-600 dark:text-slate-400' : 'text-emerald-800/90 dark:text-emerald-300/80'"
              >
                <template v-if="selectedDetailEvent.ticketPrice > 0">
                  Tickets are issued through the designated NCST cashier/ticketing process. Visit the Cashier Desk to obtain an official ticket.
                </template>
                <template v-else>
                  Free Admission. Official pass issuance may still be required and is subject to school procedures.
                </template>
              </p>
            </div>
          </div>
        </div>

        <!-- Actions -->
        <div class="flex items-center justify-between gap-3 pt-3 border-t border-slate-200/70 dark:border-slate-800/60">
          <button
            @click="selectedDetailEvent = null"
            class="px-4 py-2 rounded-xl text-xs font-bold text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-[#242424] hover:bg-slate-200 dark:hover:bg-[#2e2e2e] transition cursor-pointer flex items-center gap-1.5"
          >
            <ArrowLeft class="w-3.5 h-3.5" />
            <span>Back to Events</span>
          </button>

          <!-- Contextual Action inside Modal for Staff/Admin/Teacher (No purchase button for Students) -->
          <div class="flex items-center gap-2">
            <button
              v-if="currentUser?.role === 'admin'"
              @click="handleSelectActiveEvent(selectedDetailEvent); selectedDetailEvent = null;"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
            >
              {{ currentEvent?.id === selectedDetailEvent.id ? 'Active Event Selected' : 'Set as System Active Event' }}
            </button>
            <button
              v-else-if="currentUser?.role === 'teacher'"
              @click="handleTeacherViewRoster(selectedDetailEvent); selectedDetailEvent = null;"
              class="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
            >
              <Users class="w-3.5 h-3.5" />
              <span>Open Student Roster</span>
            </button>
            <button
              v-else-if="currentUser?.role === 'cashier'"
              @click="handleCashierOpenPOS(selectedDetailEvent); selectedDetailEvent = null;"
              class="flex items-center gap-1.5 px-4 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer"
            >
              <CreditCard class="w-3.5 h-3.5" />
              <span>Open Ticket POS</span>
            </button>
          </div>
        </div>
      </div>
    </Modal>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  Calendar,
  Clock,
  MapPin,
  ChevronLeft,
  ChevronRight,
  RotateCcw,
  Search,
  Grid,
  List,
  Users,
  CreditCard,
  Ticket as TicketIcon,
  UserCheck,
  Info,
  Eye,
  ShieldCheck,
  ArrowLeft
} from 'lucide-vue-next';
import Modal from '../common/Modal.vue';
import EventCoverImage from '../common/EventCoverImage.vue';
import { useAppStore } from '../../composables/useAppStore';
import {
  usePhilippineClock,
  getPhilippineParts,
  formatPhilippineDate,
  isTodayPHT
} from '../../utils/philippineTime';
import { EventItem } from '../../types';

const emit = defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const { events, currentEvent, setCurrentEvent, currentUser, tickets } = useAppStore();
const { timeString, fullDateString, todayIso } = usePhilippineClock();

// Calendar state in Philippine Timezone
const nowParts = getPhilippineParts(new Date());
const currentYear = ref(nowParts.year);
const currentMonth = ref(nowParts.month); // 0-indexed
const selectedDateStr = ref(todayIso.value);
const viewMode = ref<'month' | 'agenda'>('month');
const selectedCategory = ref<string>('All');
const searchQuery = ref('');

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

// Detail Modal state
const selectedDetailEvent = ref<EventItem | null>(null);
const openEventDetails = (ev: EventItem) => {
  selectedDetailEvent.value = ev;
};

const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value--;
  } else {
    currentMonth.value--;
  }
};

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value++;
  } else {
    currentMonth.value++;
  }
};

const jumpToToday = () => {
  const parts = getPhilippineParts(new Date());
  currentYear.value = parts.year;
  currentMonth.value = parts.month;
  selectedDateStr.value = todayIso.value;
};

const selectDate = (dateStr: string) => {
  selectedDateStr.value = dateStr;
};

// Compute calendar cells for the active month
interface CalendarCell {
  dateStr: string;
  dayNumber: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  events: EventItem[];
}

const calendarCells = computed<CalendarCell[]>(() => {
  const year = currentYear.value;
  const month = currentMonth.value;

  // First day of month
  const firstDay = new Date(year, month, 1);
  const startingDayOfWeek = firstDay.getDay(); // 0 (Sun) to 6 (Sat)

  // Total days in current month
  const daysInMonth = new Date(year, month + 1, 0).getDate();
  // Total days in previous month
  const daysInPrevMonth = new Date(year, month, 0).getDate();

  const cells: CalendarCell[] = [];

  // Filter events matching active category filter
  const activeEvents = events.value.filter(ev => {
    if (selectedCategory.value !== 'All' && ev.category !== selectedCategory.value) return false;
    if (searchQuery.value.trim()) {
      const q = searchQuery.value.toLowerCase();
      const matchTitle = ev.title.toLowerCase().includes(q);
      const matchVenue = ev.venue.toLowerCase().includes(q);
      const matchCode = ev.code.toLowerCase().includes(q);
      if (!matchTitle && !matchVenue && !matchCode) return false;
    }
    return true;
  });

  // Previous month trailing days
  for (let i = startingDayOfWeek - 1; i >= 0; i--) {
    const day = daysInPrevMonth - i;
    const prevMonthIdx = month === 0 ? 11 : month - 1;
    const prevYear = month === 0 ? year - 1 : year;
    const mStr = String(prevMonthIdx + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${prevYear}-${mStr}-${dStr}`;

    const cellEvents = activeEvents.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: false,
      isToday: isTodayPHT(dateStr),
      events: cellEvents,
    });
  }

  // Current month days
  for (let day = 1; day <= daysInMonth; day++) {
    const mStr = String(month + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${year}-${mStr}-${dStr}`;

    const cellEvents = activeEvents.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: true,
      isToday: isTodayPHT(dateStr),
      events: cellEvents,
    });
  }

  // Next month leading days (fill up to 35 or 42 grid cells)
  const remaining = (7 - (cells.length % 7)) % 7;
  for (let day = 1; day <= remaining; day++) {
    const nextMonthIdx = month === 11 ? 0 : month + 1;
    const nextYear = month === 11 ? year + 1 : year;
    const mStr = String(nextMonthIdx + 1).padStart(2, '0');
    const dStr = String(day).padStart(2, '0');
    const dateStr = `${nextYear}-${mStr}-${dStr}`;

    const cellEvents = activeEvents.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: day,
      isCurrentMonth: false,
      isToday: isTodayPHT(dateStr),
      events: cellEvents,
    });
  }

  return cells;
});

// Events on selected day
const selectedDateEvents = computed(() => {
  return events.value.filter(e => {
    if (e.date !== selectedDateStr.value) return false;
    if (selectedCategory.value !== 'All' && e.category !== selectedCategory.value) return false;
    return true;
  });
});

// Timeline view filtered events sorted by date
const filteredEventsTimeline = computed(() => {
  return events.value
    .filter(e => {
      if (selectedCategory.value !== 'All' && e.category !== selectedCategory.value) return false;
      if (searchQuery.value.trim()) {
        const q = searchQuery.value.toLowerCase();
        return e.title.toLowerCase().includes(q) || e.venue.toLowerCase().includes(q) || e.code.toLowerCase().includes(q);
      }
      return true;
    })
    .sort((a, b) => a.date.localeCompare(b.date));
});

const getEventMonthShort = (dateStr: string) => {
  if (!dateStr) return '---';
  const parts = dateStr.split('-');
  const mIdx = parseInt(parts[1], 10) - 1;
  return monthNames[mIdx]?.slice(0, 3).toUpperCase() || 'EVT';
};

const getEventDay = (dateStr: string) => {
  if (!dateStr) return '01';
  return dateStr.split('-')[2] || '01';
};

const getEventCategoryBadgeClass = (category: string) => {
  switch (category) {
    case 'Foundation':
      return 'bg-amber-100 text-amber-900 dark:bg-amber-950/70 dark:text-amber-300 border border-amber-300 dark:border-amber-700/50';
    case 'Concert':
      return 'bg-fuchsia-100 text-fuchsia-900 dark:bg-fuchsia-950/70 dark:text-fuchsia-300 border border-fuchsia-300 dark:border-fuchsia-700/50';
    case 'Battle of the bands':
      return 'bg-red-100 text-red-900 dark:bg-red-950/70 dark:text-red-300 border border-red-300 dark:border-red-700/50';
    case 'Academic':
      return 'bg-blue-100 text-blue-800 dark:bg-blue-950/60 dark:text-blue-300 border border-blue-200 dark:border-blue-800/40';
    case 'Cultural':
      return 'bg-purple-100 text-purple-800 dark:bg-purple-950/60 dark:text-purple-300 border border-purple-200 dark:border-purple-800/40';
    case 'Sports':
      return 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950/60 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800/40';
    case 'Technology':
      return 'bg-cyan-100 text-cyan-800 dark:bg-cyan-950/60 dark:text-cyan-300 border border-cyan-200 dark:border-cyan-800/40';
    case 'Institutional':
      return 'bg-rose-100 text-rose-800 dark:bg-rose-950/60 dark:text-rose-300 border border-rose-200 dark:border-rose-800/40';
    default:
      return 'bg-slate-100 text-slate-800 dark:bg-slate-800 dark:text-slate-300';
  }
};

const getEventStatusDotClass = (status: string) => {
  switch (status) {
    case 'ongoing': return 'bg-emerald-500 animate-pulse';
    case 'upcoming': return 'bg-blue-500';
    case 'completed': return 'bg-slate-400';
    case 'cancelled': return 'bg-rose-500';
    default: return 'bg-slate-400';
  }
};

// Hierarchy contextual handlers
const handleSelectActiveEvent = (ev: EventItem) => {
  setCurrentEvent(ev);
};

const handleTeacherViewRoster = (ev: EventItem) => {
  setCurrentEvent(ev);
  emit('navigate', 'student_roster_import');
};

const handleCashierOpenPOS = (ev: EventItem) => {
  setCurrentEvent(ev);
  emit('navigate', 'cashier_pos');
};
</script>
