<template>
  <div v-if="currentUser?.role !== 'admin'" class="p-8 max-w-2xl mx-auto text-center space-y-4 bg-white dark:bg-[#1a1a1a] rounded-3xl border border-rose-200 dark:border-rose-900/50 shadow-sm my-8">
    <div class="w-14 h-14 mx-auto rounded-2xl bg-rose-50 dark:bg-rose-950/60 text-rose-600 dark:text-rose-400 flex items-center justify-center">
      <AlertCircle class="w-7 h-7" />
    </div>
    <h2 class="text-xl font-black text-slate-900 dark:text-white">403 Forbidden: Administrator Access Required</h2>
    <p class="text-xs text-slate-600 dark:text-slate-400">
      Only Administrators are authorized to create, edit, or manage campus events.
    </p>
    <div class="pt-2">
      <button
        @click="$emit('navigate', currentUser?.role === 'cashier' ? 'cashier_pos' : (currentUser?.role === 'event_staff' ? 'staff_dashboard' : 'calendar'))"
        class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer"
      >
        Return to Authorized Dashboard
      </button>
    </div>
  </div>

  <div v-else class="space-y-6 max-w-7xl mx-auto">
    <!-- ==================== 1. PAGE HEADER & VIEW SWITCHER ==================== -->
    <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs transition-colors">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <!-- Title & Subtitle -->
        <div class="space-y-1 min-w-0">
          <div class="flex items-center gap-2.5">
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              Events
            </h1>
            <span class="px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60">
              {{ events.length }} {{ events.length === 1 ? 'Event' : 'Events' }}
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-slate-400">
            Manage schedules, event details, tickets, staff assignments, and event configuration.
          </p>
        </div>

        <!-- Controls: View Switcher + Create Event Button -->
        <div class="flex flex-wrap items-center gap-3 shrink-0">
          <!-- View Switcher [ List ] [ Calendar ] -->
          <div class="flex items-center bg-slate-100 dark:bg-[#252525] p-1 rounded-xl border border-slate-200 dark:border-[#333333]">
            <button
              @click="activeView = 'list'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeView === 'list'
                ? 'bg-white dark:bg-[#1a1a1a] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            >
              <LayoutList class="w-3.5 h-3.5" />
              <span>List</span>
            </button>
            <button
              @click="activeView = 'calendar'"
              class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeView === 'calendar'
                ? 'bg-white dark:bg-[#1a1a1a] text-blue-600 dark:text-blue-400 shadow-xs'
                : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
            >
              <CalendarDays class="w-3.5 h-3.5" />
              <span>Calendar</span>
            </button>
          </div>

          <!-- Academic Configuration Action Button -->
          <button
            @click="isAcademicPeriodModalOpen = true"
            class="group flex items-center gap-2.5 px-3 py-1.5 bg-white dark:bg-[#202020] hover:bg-blue-50/80 dark:hover:bg-blue-950/40 text-slate-800 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-300 dark:border-[#383838] hover:border-blue-500 dark:hover:border-blue-500 shadow-xs hover:shadow-sm transition-all duration-150 cursor-pointer"
            title="Click to configure Academic Periods and switch active semester"
          >
            <div class="p-1 rounded-lg bg-blue-100 dark:bg-blue-950 text-blue-600 dark:text-blue-400 group-hover:scale-105 transition-transform">
              <GraduationCap class="w-4 h-4" />
            </div>
            <div class="flex flex-col items-start text-left leading-tight">
              <span class="text-[8.5px] uppercase font-black text-slate-400 dark:text-slate-500 tracking-wider">
                Academic Period
              </span>
              <span class="text-xs font-extrabold text-slate-900 dark:text-white">
                {{ activeAcademicPeriod.academicYear }} • {{ activeAcademicPeriod.semester }}
              </span>
            </div>
            <span class="ml-1 flex items-center gap-1 px-2 py-1 rounded-lg text-[10px] font-black uppercase tracking-wider bg-blue-600 hover:bg-blue-700 text-white shadow-2xs group-hover:shadow-xs transition">
              Manage
              <SlidersHorizontal class="w-2.5 h-2.5" />
            </span>
          </button>

          <!-- Primary Action: Create Event -->
          <button
            @click="openCreateModal"
            class="flex items-center gap-2 px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs sm:text-sm font-bold shadow-xs transition cursor-pointer"
          >
            <Plus class="w-4 h-4" />
            <span>Create Event</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== 2. LIST VIEW ==================== -->
    <div v-if="activeView === 'list'" class="space-y-5">
      <!-- Search and Filter Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-3.5 sm:p-4 rounded-2xl shadow-xs">
        <!-- Search Input -->
        <div class="relative flex-1 min-w-[200px]">
          <Search class="w-4 h-4 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input
            type="text"
            v-model="searchQuery"
            placeholder="Search events by title, venue, or code..."
            class="w-full pl-9 pr-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
          />
        </div>

        <!-- Filters -->
        <div class="flex flex-wrap items-center gap-2">
          <!-- Academic Period Filter -->
          <select
            v-model="selectedAcademicPeriodFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All Academic Periods</option>
            <option v-for="p in academicPeriods" :key="p.id" :value="p.id">
              {{ p.academicYear }} {{ p.semester }} ({{ p.status }})
            </option>
          </select>

          <!-- Status Filter -->
          <select
            v-model="selectedStatusFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All Statuses</option>
            <option value="upcoming">Upcoming</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>

          <!-- Category Filter -->
          <select
            v-model="selectedCategoryFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="all">All Categories</option>
            <option value="Institutional">Institutional</option>
            <option value="Foundation">Foundation</option>
            <option value="Concert">Concert</option>
            <option value="Battle of the bands">Battle of the bands</option>
            <option value="Academic">Academic</option>
            <option value="Technology">Technology</option>
            <option value="Sports">Sports</option>
            <option value="Cultural">Cultural</option>
          </select>
        </div>
      </div>

      <!-- Events Grid / Cards -->
      <div v-if="filteredEvents.length > 0" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-5">
        <div
          v-for="ev in filteredEvents"
          :key="ev.id"
          class="rounded-2xl overflow-hidden bg-white dark:bg-[#1a1a1a] border transition-all duration-200 flex flex-col justify-between shadow-xs hover:border-slate-300 dark:hover:border-slate-600"
          :class="[
            currentEvent?.id === ev.id
              ? 'border-blue-500 ring-2 ring-blue-500/20'
              : 'border-slate-200 dark:border-[#2e2e2e]'
          ]"
        >
          <div>
            <!-- Banner Image & Badges -->
            <div class="relative h-40 w-full overflow-hidden bg-slate-900">
              <EventCoverImage
                :src="ev.bannerUrl"
                :alt="`${ev.title} event cover`"
                :title="ev.title"
                :category="ev.category"
                :code="ev.code"
                size="full"
                rounded="none"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-black/70 via-transparent to-transparent pointer-events-none" />

              <!-- Category, Period & Status -->
              <div class="absolute top-3 left-3 flex items-center gap-1.5 flex-wrap">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-slate-900/80 text-blue-300 border border-slate-700">
                  {{ ev.category }}
                </span>
                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-900/80 text-amber-300 border border-amber-600/50 flex items-center gap-1">
                  <GraduationCap class="w-2.5 h-2.5" />
                  <span>{{ ev.academicYear || '2026-2027' }} • {{ ev.semester || '1st Semester' }}</span>
                </span>
                <span
                  class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full"
                  :class="[
                    ev.status === 'ongoing' ? 'bg-emerald-600 text-white' :
                    ev.status === 'upcoming' ? 'bg-blue-600 text-white' :
                    ev.status === 'cancelled' ? 'bg-rose-600 text-white' :
                    'bg-slate-700 text-slate-300'
                  ]"
                >
                  {{ ev.status }}
                </span>
              </div>

              <!-- Code -->
              <div class="absolute top-3 right-3 font-mono text-[10px] font-bold px-2 py-0.5 bg-slate-900/80 rounded-md text-white border border-slate-700">
                {{ ev.code }}
              </div>

              <!-- Active Event Tag Overlay -->
              <div v-if="currentEvent?.id === ev.id" class="absolute bottom-2 left-3 flex items-center gap-1 px-2 py-0.5 rounded-md bg-emerald-500 text-white text-[10px] font-black uppercase tracking-wider shadow-xs">
                <Check class="w-3 h-3" />
                <span>Active Event</span>
              </div>
            </div>

            <!-- Content -->
            <div class="p-5 space-y-3">
              <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight line-clamp-1">
                  {{ ev.title }}
                </h3>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 line-clamp-2">
                  {{ ev.description || 'No description provided.' }}
                </p>
              </div>

              <div class="space-y-1.5 text-xs text-slate-600 dark:text-slate-300 pt-1 border-t border-slate-100 dark:border-[#262626]">
                <div class="flex items-center gap-2">
                  <CalendarDays class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
                  <span>{{ formatPhilippineDate(ev.date) }} <span class="text-slate-400">({{ ev.startTime }} - {{ ev.endTime }})</span></span>
                </div>
                <div class="flex items-center gap-2">
                  <MapPin class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
                  <span class="truncate">{{ ev.venue }}</span>
                </div>
                <div class="flex items-center justify-between pt-2 text-[11px] text-slate-500 dark:text-slate-400">
                  <span>Price: <strong class="text-emerald-600 dark:text-emerald-400 font-bold">{{ ev.ticketPrice > 0 ? `PHP ${ev.ticketPrice}` : 'Free' }}</strong></span>
                  <span>Tickets: <strong class="text-slate-900 dark:text-white font-bold">{{ getEventTicketCount(ev.id) }}</strong></span>
                </div>
              </div>
            </div>
          </div>

          <!-- Card Actions Footer -->
          <div class="p-4 bg-slate-50 dark:bg-[#222222] border-t border-slate-100 dark:border-[#262626] flex items-center justify-between gap-2">
            <button
              @click="openDetailsModal(ev)"
              class="px-3 py-1.5 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 text-blue-600 dark:text-blue-400 rounded-xl text-xs font-bold transition cursor-pointer"
            >
              View Details
            </button>

            <div class="flex items-center gap-1.5">
              <button
                v-if="currentEvent?.id !== ev.id"
                @click="setCurrentEvent(ev)"
                class="px-2.5 py-1.5 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#2c2c2c] rounded-lg transition cursor-pointer"
                title="Set as Active Event"
              >
                Set Active
              </button>
              <button
                @click="openEditModal(ev)"
                class="p-1.5 text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white hover:bg-slate-200 dark:hover:bg-[#2c2c2c] rounded-lg transition cursor-pointer"
                title="Edit Event"
              >
                <Edit2 class="w-4 h-4" />
              </button>
              <button
                @click="handleDelete(ev.id, ev.title)"
                class="p-1.5 text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 hover:bg-rose-50 dark:hover:bg-rose-950/50 rounded-lg transition cursor-pointer"
                title="Delete Event"
              >
                <Trash2 class="w-4 h-4" />
              </button>
            </div>
          </div>
        </div>
      </div>

      <!-- Empty State -->
      <div v-else class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-12 text-center text-slate-400 dark:text-slate-500">
        <Calendar class="w-8 h-8 mx-auto mb-2 opacity-40" />
        <p class="font-bold text-sm text-slate-700 dark:text-slate-300">No events matched your search.</p>
        <p class="text-xs mt-1">Try adjusting your filters or click "+ Create Event" to add one.</p>
      </div>
    </div>

    <!-- ==================== 3. CALENDAR VIEW ==================== -->
    <div v-else-if="activeView === 'calendar'" class="space-y-6">
      <!-- Calendar Controls Bar -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-4 sm:p-5 rounded-2xl shadow-xs">
        <!-- Month Switcher -->
        <div class="flex items-center gap-2">
          <div class="flex items-center gap-1 bg-slate-100 dark:bg-[#252525] p-1 rounded-xl border border-slate-200 dark:border-[#333333]">
            <button
              @click="prevMonth"
              class="p-1.5 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white rounded-lg transition cursor-pointer"
              title="Previous Month"
            >
              <ChevronLeft class="w-4 h-4" />
            </button>
            <span class="px-3 text-xs sm:text-sm font-bold text-slate-900 dark:text-white min-w-[130px] text-center">
              {{ monthNames[currentMonth] }} {{ currentYear }}
            </span>
            <button
              @click="nextMonth"
              class="p-1.5 text-slate-600 dark:text-slate-300 hover:text-slate-900 dark:hover:text-white rounded-lg transition cursor-pointer"
              title="Next Month"
            >
              <ChevronRight class="w-4 h-4" />
            </button>
          </div>

          <button
            @click="jumpToToday"
            class="px-3 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#252525] dark:hover:bg-[#2e2e2e] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold border border-slate-200 dark:border-[#333333] transition cursor-pointer"
          >
            Today
          </button>
        </div>

        <!-- Year Selector & Search -->
        <div class="flex items-center gap-2">
          <select
            v-model="currentYear"
            class="px-3 py-2 bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer"
          >
            <option :value="2025">2025</option>
            <option :value="2026">2026</option>
            <option :value="2027">2027</option>
          </select>
        </div>
      </div>

      <!-- Main Calendar Grid -->
      <div class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs space-y-3">
        <!-- Weekday Header -->
        <div class="grid grid-cols-7 gap-1 text-center border-b border-slate-100 dark:border-[#262626] pb-2.5">
          <div
            v-for="day in ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat']"
            :key="day"
            class="text-xs font-extrabold uppercase text-slate-400 dark:text-slate-500"
          >
            {{ day }}
          </div>
        </div>

        <!-- Days Grid -->
        <div class="grid grid-cols-7 gap-1.5 sm:gap-2">
          <div
            v-for="(cell, idx) in calendarCells"
            :key="idx"
            @click="handleSelectDate(cell.dateStr)"
            class="min-h-[75px] sm:min-h-[90px] p-2 rounded-xl border flex flex-col justify-between transition-all cursor-pointer relative"
            :class="[
              !cell.isCurrentMonth
                ? 'bg-slate-50/50 dark:bg-[#141414]/50 border-slate-100 dark:border-[#202020] text-slate-300 dark:text-slate-700'
                : selectedDateStr === cell.dateStr
                ? 'bg-blue-50/70 dark:bg-blue-950/40 border-blue-400 dark:border-blue-700'
                : cell.isToday
                ? 'bg-amber-50/60 dark:bg-amber-950/30 border-amber-300 dark:border-amber-800'
                : 'bg-white dark:bg-[#1e1e1e] border-slate-200 dark:border-[#2c2c2c] hover:border-slate-300 dark:hover:border-slate-600'
            ]"
          >
            <!-- Day number and Today badge -->
            <div class="flex items-center justify-between">
              <span
                class="text-xs font-bold"
                :class="cell.isToday ? 'text-amber-600 dark:text-amber-400 font-black' : 'text-slate-700 dark:text-slate-300'"
              >
                {{ cell.dayNumber }}
              </span>
              <span v-if="cell.isToday" class="text-[9px] font-bold uppercase text-amber-600 dark:text-amber-400 font-mono">
                Today
              </span>
            </div>

            <!-- Event chips on this date -->
            <div class="space-y-1 mt-1">
              <div
                v-for="ev in cell.dayEvents.slice(0, 2)"
                :key="ev.id"
                @click.stop="openDetailsModal(ev)"
                class="px-1.5 py-0.5 rounded text-[10px] font-bold truncate bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300 hover:opacity-80 transition"
                :title="ev.title"
              >
                {{ ev.title }}
              </div>
              <span v-if="cell.dayEvents.length > 2" class="text-[9px] text-slate-400 dark:text-slate-500 font-semibold block">
                +{{ cell.dayEvents.length - 2 }} more
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Events on Selected Date Drawer -->
      <div v-if="selectedDateStr" class="rounded-2xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-6 shadow-xs space-y-4">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">
              Events on {{ formatPhilippineDate(selectedDateStr) }}
            </h3>
            <p class="text-xs text-slate-500 dark:text-slate-400">
              {{ selectedDayEvents.length }} {{ selectedDayEvents.length === 1 ? 'event scheduled' : 'events scheduled' }}
            </p>
          </div>

          <button
            @click="openCreateModalForDate(selectedDateStr)"
            class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer flex items-center gap-1"
          >
            <Plus class="w-3.5 h-3.5" />
            <span>Create Event on This Date</span>
          </button>
        </div>

        <div v-if="selectedDayEvents.length > 0" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
          <div
            v-for="ev in selectedDayEvents"
            :key="ev.id"
            class="p-4 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] space-y-2.5 flex flex-col justify-between"
          >
            <div>
              <div class="flex items-center justify-between">
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/40 text-blue-700 dark:text-blue-300">
                  {{ ev.category }}
                </span>
                <span class="font-mono text-[10px] font-bold text-slate-400">{{ ev.code }}</span>
              </div>
              <h4 class="font-bold text-sm text-slate-900 dark:text-white mt-1.5">{{ ev.title }}</h4>
              <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">{{ ev.venue }} • {{ ev.startTime }}</p>
            </div>

            <div class="flex items-center justify-end gap-2 pt-2 border-t border-slate-200 dark:border-[#2e2e2e]">
              <button
                @click="openDetailsModal(ev)"
                class="px-2.5 py-1 text-xs font-bold text-blue-600 dark:text-blue-400 hover:bg-blue-50 dark:hover:bg-blue-950/40 rounded-lg transition"
              >
                View
              </button>
              <button
                @click="openEditModal(ev)"
                class="px-2.5 py-1 text-xs font-bold text-slate-600 dark:text-slate-300 hover:bg-slate-200 dark:hover:bg-[#2e2e2e] rounded-lg transition"
              >
                Edit
              </button>
            </div>
          </div>
        </div>

        <div v-else class="py-6 text-center text-slate-400 dark:text-slate-500">
          <p class="text-xs">No events scheduled on this date.</p>
        </div>
      </div>
    </div>

    <!-- ==================== 4. EVENT DETAILS MODAL / DRAWER ==================== -->
    <Modal
      :is-open="isDetailsModalOpen"
      @close="isDetailsModalOpen = false"
      :title="selectedEventForDetails?.title || 'Event Details'"
      :subtitle="`${selectedEventForDetails?.code || ''} • ${selectedEventForDetails?.venue || ''}`"
      max-width="3xl"
    >
      <div v-if="selectedEventForDetails" class="space-y-5">
        <!-- Event Quick Banner Preview -->
        <!-- Event Banner Cover Image -->
        <div class="relative h-44 w-full rounded-2xl overflow-hidden bg-slate-900 border border-slate-200 dark:border-[#333333] shadow-sm">
          <EventCoverImage
            :src="selectedEventForDetails.bannerUrl"
            :alt="`${selectedEventForDetails.title} cover`"
            :title="selectedEventForDetails.title"
            :category="selectedEventForDetails.category"
            :code="selectedEventForDetails.code"
            size="full"
            rounded="none"
          />
          <div class="absolute inset-0 bg-gradient-to-t from-black/80 via-black/20 to-transparent flex items-end p-4">
            <div class="flex items-center justify-between w-full">
              <div>
                <span class="text-[10px] font-bold uppercase px-2 py-0.5 rounded-full bg-blue-600 text-white">
                  {{ selectedEventForDetails.category }}
                </span>
                <h3 class="text-lg font-black text-white mt-1">{{ selectedEventForDetails.title }}</h3>
              </div>
              <button
                v-if="currentEvent?.id !== selectedEventForDetails.id"
                @click="setCurrentEvent(selectedEventForDetails)"
                class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-bold rounded-xl shadow-xs transition cursor-pointer"
              >
                Set as Active Event
              </button>
              <span v-else class="px-3 py-1.5 bg-emerald-500/90 text-white text-xs font-black rounded-xl uppercase">
                Currently Active
              </span>
            </div>
          </div>
        </div>

        <!-- Tabs Switcher -->
        <div class="flex items-center gap-1.5 border-b border-slate-200 dark:border-[#2e2e2e] pb-1 overflow-x-auto">
          <button
            v-for="tab in ['overview', 'tickets', 'staff'] as const"
            :key="tab"
            @click="detailsActiveTab = tab"
            class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer capitalize whitespace-nowrap"
            :class="detailsActiveTab === tab
              ? 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-800/60'
              : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
          >
            {{ tab }}
          </button>
        </div>

        <!-- Tab 1: Overview -->
        <div v-if="detailsActiveTab === 'overview'" class="space-y-4 text-xs">
          <p class="text-slate-600 dark:text-slate-300">
            {{ selectedEventForDetails.description || 'No detailed description.' }}
          </p>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2">
            <div class="p-3 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] space-y-1">
              <span class="text-[10px] uppercase font-bold text-slate-400">Date & Time</span>
              <p class="font-bold text-slate-900 dark:text-white">
                {{ formatPhilippineDate(selectedEventForDetails.date) }}
              </p>
              <p class="text-slate-500 dark:text-slate-400">
                {{ selectedEventForDetails.startTime }} - {{ selectedEventForDetails.endTime }}
              </p>
            </div>

            <div class="p-3 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] space-y-1">
              <span class="text-[10px] uppercase font-bold text-slate-400">Venue Location</span>
              <p class="font-bold text-slate-900 dark:text-white">{{ selectedEventForDetails.venue }}</p>
            </div>
          </div>
        </div>

        <!-- Tab 2: Tickets -->
        <div v-if="detailsActiveTab === 'tickets'" class="space-y-4">
          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] text-center">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Ticket Price</span>
              <p class="text-xl font-black text-emerald-600 dark:text-emerald-400 mt-1">
                {{ selectedEventForDetails.ticketPrice > 0 ? `PHP ${selectedEventForDetails.ticketPrice}` : 'Free Pass' }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] text-center">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Tickets Issued</span>
              <p class="text-xl font-black text-slate-900 dark:text-white mt-1">
                {{ getEventTicketCount(selectedEventForDetails.id) }}
              </p>
            </div>
            <div class="p-4 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] text-center">
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Present Checked-in</span>
              <p class="text-xl font-black text-blue-600 dark:text-blue-400 mt-1">
                {{ getEventPresentCount(selectedEventForDetails.id) }}
              </p>
            </div>
          </div>

          <div class="flex justify-end pt-2">
            <button
              @click="$emit('navigate', 'tickets'); isDetailsModalOpen = false;"
              class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition"
            >
              Open Ticket Registry &rarr;
            </button>
          </div>
        </div>

        <!-- Tab 3: Staff Assignment -->
        <div v-if="detailsActiveTab === 'staff'" class="space-y-4">
          <div class="flex items-center justify-between">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">
              Event Staff Assigned to this Event
            </h4>
            <span class="text-[11px] text-slate-500">
              Only assigned staff can scan entry tickets
            </span>
          </div>

          <div class="space-y-2 max-h-60 overflow-y-auto">
            <div
              v-for="st in staffUsers"
              :key="st.id"
              class="p-3 rounded-xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] flex items-center justify-between gap-3"
            >
              <div>
                <p class="text-xs font-bold text-slate-900 dark:text-white">{{ st.name }}</p>
                <p class="text-[11px] text-slate-500 font-mono">{{ st.email }}</p>
              </div>

              <button
                @click="toggleStaffAssignment(st, selectedEventForDetails.id)"
                class="px-3 py-1 rounded-lg text-xs font-bold transition cursor-pointer"
                :class="isStaffAssigned(st, selectedEventForDetails.id)
                  ? 'bg-emerald-50 dark:bg-emerald-950/60 text-emerald-600 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800'
                  : 'bg-slate-200 dark:bg-[#333333] text-slate-700 dark:text-slate-300 hover:bg-slate-300'"
              >
                {{ isStaffAssigned(st, selectedEventForDetails.id) ? 'Assigned' : 'Assign Staff' }}
              </button>
            </div>

            <div v-if="staffUsers.length === 0" class="py-4 text-center text-xs text-slate-400">
              No Event Staff accounts found.
            </div>
          </div>
        </div>
      </div>
    </Modal>

    <!-- ==================== 5. CREATE / EDIT EVENT MODAL ==================== -->
    <Modal
      :is-open="isModalOpen"
      @close="isModalOpen = false"
      :title="editingEvent ? 'Edit Event Details' : 'Create New Event'"
      subtitle="Configure event metadata, ticketing price, schedule, and venue parameters"
      max-width="3xl"
    >
      <form @submit.prevent="handleSubmit" class="space-y-4 text-xs" novalidate>
        
        <!-- Top Compact Validation Summary Alert -->
        <div
          v-if="hasAttemptedSubmit && validationResult.errorCount > 0"
          class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-800/80 rounded-2xl flex items-start gap-3 text-rose-800 dark:text-rose-300 animate-in fade-in duration-200"
        >
          <AlertCircle class="w-5 h-5 text-rose-600 dark:text-rose-400 shrink-0 mt-0.5" />
          <div class="space-y-1 flex-1">
            <p class="font-extrabold text-rose-900 dark:text-rose-200 text-xs">
              {{ validationResult.errorSummary }}
            </p>
            <ul class="list-disc list-inside text-[11px] text-rose-700 dark:text-rose-300 space-y-0.5">
              <li v-for="(msg, field) in validationResult.errors" :key="field">
                {{ msg }}
              </li>
            </ul>
          </div>
        </div>

        <!-- Similar Event Duplicate Warning Banner -->
        <div
          v-if="validationResult.warnings.duplicate"
          class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/70 rounded-2xl flex items-start gap-2.5 text-amber-800 dark:text-amber-300 text-xs"
        >
          <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0 mt-0.5" />
          <div class="space-y-0.5 flex-1">
            <span class="font-bold text-amber-900 dark:text-amber-200">Duplicate Notice:</span>
            <p class="text-[11px] leading-relaxed">{{ validationResult.warnings.duplicate }}</p>
          </div>
        </div>

        <!-- 1. Event Name & Code -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div class="sm:col-span-2">
            <div class="flex items-center justify-between mb-1">
              <label class="block font-bold text-slate-700 dark:text-slate-300">
                Event Name <span class="text-rose-500">*</span>
              </label>
              <span class="text-[10px] font-mono text-slate-400">
                {{ (title || '').length }}/150 chars
              </span>
            </div>
            <input
              type="text"
              v-model="title"
              @blur="touchField('title')"
              placeholder="e.g. NCST Institutional Foundation Week 2026"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white transition focus:outline-none"
              :class="[
                showFieldError('title')
                  ? 'border-rose-500 ring-1 ring-rose-500/20 bg-rose-50/30 dark:bg-rose-950/20'
                  : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
              ]"
            />
            <p v-if="showFieldError('title')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
              <AlertCircle class="w-3 h-3" />
              <span>{{ getFieldError('title') }}</span>
            </p>
          </div>

          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
              Event Code
            </label>
            <input
              type="text"
              v-model="code"
              placeholder="EVT-2026-001"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-mono font-bold text-blue-600 dark:text-blue-400 focus:outline-none focus:border-blue-500"
            />
          </div>
        </div>

        <!-- Description -->
        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
            Description <span class="text-[10px] text-slate-400 font-normal">(Optional)</span>
          </label>
          <textarea
            rows="2"
            v-model="description"
            placeholder="Brief summary of activities, guidelines, and attendee instructions..."
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 resize-none"
          />
        </div>

        <!-- Authoritative Academic Scoping -->
        <div class="p-3.5 bg-blue-50/50 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-900/40 rounded-2xl space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <GraduationCap class="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <span class="font-bold text-slate-800 dark:text-slate-200 text-xs">
                Academic Scoping (Authoritative)
              </span>
            </div>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded bg-blue-100 dark:bg-blue-900/60 text-blue-700 dark:text-blue-300">
              Active: {{ activeAcademicPeriod.academicYear }} {{ activeAcademicPeriod.semester }}
            </span>
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 text-[11px] mb-1">
                Academic Year <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="selectedAcademicYear"
                class="w-full bg-white dark:bg-[#1a1a1a] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option v-for="yr in availableAcademicYears" :key="yr" :value="yr">{{ yr }}</option>
              </select>
            </div>
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 text-[11px] mb-1">
                Semester <span class="text-rose-500">*</span>
              </label>
              <select
                v-model="selectedSemester"
                class="w-full bg-white dark:bg-[#1a1a1a] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option value="1st Semester">1st Semester</option>
                <option value="2nd Semester">2nd Semester</option>
              </select>
            </div>
          </div>
          <p class="text-[10px] text-slate-500 dark:text-slate-400">
            Tickets, transactions, and attendance logs strictly inherit their academic association from this event record.
          </p>
        </div>

        <!-- 2. Category & 8. Venue -->
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <div>
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
              Category <span class="text-rose-500">*</span>
            </label>
            <select
              v-model="category"
              @change="touchField('category')"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-semibold transition focus:outline-none cursor-pointer"
              :class="[
                showFieldError('category')
                  ? 'border-rose-500 ring-1 ring-rose-500/20'
                  : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
              ]"
            >
              <option value="" disabled>Select Category...</option>
              <option value="Institutional">Institutional</option>
              <option value="Foundation">Foundation</option>
              <option value="Concert">Concert</option>
              <option value="Battle of the bands">Battle of the bands</option>
              <option value="Academic">Academic</option>
              <option value="Technology">Technology</option>
              <option value="Sports">Sports</option>
              <option value="Cultural">Cultural</option>
            </select>
            <p v-if="showFieldError('category')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
              <AlertCircle class="w-3 h-3" />
              <span>{{ getFieldError('category') }}</span>
            </p>
          </div>

          <div class="sm:col-span-2">
            <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
              Venue Location <span class="text-rose-500">*</span>
            </label>
            <input
              type="text"
              v-model="venue"
              @blur="touchField('venue')"
              placeholder="e.g. NCST Gymnasium & Activity Center"
              class="w-full bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white font-medium transition focus:outline-none"
              :class="[
                showFieldError('venue')
                  ? 'border-rose-500 ring-1 ring-rose-500/20 bg-rose-50/30 dark:bg-rose-950/20'
                  : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
              ]"
            />
            <p v-if="showFieldError('venue')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
              <AlertCircle class="w-3 h-3" />
              <span>{{ getFieldError('venue') }}</span>
            </p>
          </div>
        </div>

        <!-- 3, 4, 5, 6, 7. Schedule: Date, Start Time & End Time (Controlled Pickers) -->
        <div class="p-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-3">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <Calendar class="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <span class="font-bold text-slate-800 dark:text-slate-200">
                Event Schedule & Operating Hours
              </span>
            </div>
            <span class="text-[10px] font-bold text-slate-500 dark:text-slate-400 font-mono">
              PHT (UTC+8)
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
            <!-- 3. Event Date -->
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Event Date <span class="text-rose-500">*</span>
              </label>
              <input
                type="date"
                v-model="date"
                :min="editingEvent ? undefined : todayPstDate"
                @change="touchField('date')"
                class="w-full bg-white dark:bg-[#1a1a1a] border rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white transition focus:outline-none"
                :class="[
                  showFieldError('date')
                    ? 'border-rose-500 ring-1 ring-rose-500/20'
                    : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                ]"
              />
              <p v-if="showFieldError('date')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                <span>{{ getFieldError('date') }}</span>
              </p>
            </div>

            <!-- 4. Start Time (Controlled Time Picker) -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block font-semibold text-slate-700 dark:text-slate-300">
                  Start Time <span class="text-rose-500">*</span>
                </label>
                <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 font-mono">
                  {{ displayStartTimeFormatted }}
                </span>
              </div>
              <input
                type="time"
                v-model="startTimeInput"
                @input="handleStartTimeChange"
                class="w-full bg-white dark:bg-[#1a1a1a] border rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white transition focus:outline-none cursor-pointer"
                :class="[
                  showFieldError('startTime')
                    ? 'border-rose-500 ring-1 ring-rose-500/20'
                    : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                ]"
              />
              <p v-if="showFieldError('startTime')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                <span>{{ getFieldError('startTime') }}</span>
              </p>
            </div>

            <!-- 5 & 6. End Time (Controlled Time Picker) -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block font-semibold text-slate-700 dark:text-slate-300">
                  End Time <span class="text-rose-500">*</span>
                </label>
                <span class="text-[10px] font-bold text-blue-600 dark:text-blue-400 font-mono">
                  {{ displayEndTimeFormatted }}
                </span>
              </div>
              <input
                type="time"
                v-model="endTimeInput"
                @input="handleEndTimeChange"
                class="w-full bg-white dark:bg-[#1a1a1a] border rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white transition focus:outline-none cursor-pointer"
                :class="[
                  showFieldError('endTime')
                    ? 'border-rose-500 ring-1 ring-rose-500/20'
                    : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                ]"
              />
              <p v-if="showFieldError('endTime')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                <span>{{ getFieldError('endTime') }}</span>
              </p>
            </div>
          </div>

          <!-- Quick Time Presets -->
          <div class="flex flex-wrap items-center gap-1.5 pt-1 text-[10px] text-slate-500 dark:text-slate-400">
            <span class="font-bold">Quick Presets:</span>
            <button
              type="button"
              @click="applyTimePreset('08:00', '17:00')"
              class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-[#2c2c2c] hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900/40 dark:hover:text-blue-300 font-mono font-medium transition cursor-pointer"
            >
              8:00 AM - 5:00 PM (Full Day)
            </button>
            <button
              type="button"
              @click="applyTimePreset('08:00', '12:00')"
              class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-[#2c2c2c] hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900/40 dark:hover:text-blue-300 font-mono font-medium transition cursor-pointer"
            >
              8:00 AM - 12:00 PM (Morning)
            </button>
            <button
              type="button"
              @click="applyTimePreset('13:00', '17:00')"
              class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-[#2c2c2c] hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900/40 dark:hover:text-blue-300 font-mono font-medium transition cursor-pointer"
            >
              1:00 PM - 5:00 PM (Afternoon)
            </button>
            <button
              type="button"
              @click="applyTimePreset('17:00', '21:00')"
              class="px-2 py-0.5 rounded-md bg-slate-200 dark:bg-[#2c2c2c] hover:bg-blue-100 hover:text-blue-700 dark:hover:bg-blue-900/40 dark:hover:text-blue-300 font-mono font-medium transition cursor-pointer"
            >
              5:00 PM - 9:00 PM (Evening)
            </button>
          </div>
        </div>

        <!-- 13. Event Status -->
        <div>
          <label class="block font-bold text-slate-700 dark:text-slate-300 mb-1">
            Event Status
          </label>
          <select
            v-model="status"
            class="w-full bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
          >
            <option value="upcoming">Upcoming</option>
            <option value="ongoing">Ongoing</option>
            <option value="completed">Completed</option>
            <option value="cancelled">Cancelled</option>
          </select>
        </div>

        <!-- 10 & 11. Ticket Pricing & Free Admission Policy -->
        <div class="p-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-3">
          <div class="flex items-center justify-between">
            <span class="font-bold text-slate-800 dark:text-slate-200">
              Admission & Ticket Pricing Policy
            </span>
            <!-- Admission Toggle -->
            <div class="flex items-center bg-slate-200 dark:bg-[#2c2c2c] p-0.5 rounded-lg text-[11px] font-bold">
              <button
                type="button"
                @click="setFreeAdmission(true)"
                class="px-2.5 py-1 rounded-md transition cursor-pointer"
                :class="isFreeAdmission ? 'bg-emerald-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              >
                Free Pass
              </button>
              <button
                type="button"
                @click="setFreeAdmission(false)"
                class="px-2.5 py-1 rounded-md transition cursor-pointer"
                :class="!isFreeAdmission ? 'bg-blue-600 text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900 dark:hover:text-white'"
              >
                Paid Ticket
              </button>
            </div>
          </div>

          <div v-if="isFreeAdmission" class="flex items-center gap-2 p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-800/60 text-emerald-800 dark:text-emerald-300">
            <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
            <div class="text-[11px]">
              <strong>Free Admission Enabled:</strong> Ticket price is automatically locked to <strong>₱0.00</strong> for all attendees.
            </div>
          </div>

          <div v-else class="grid grid-cols-1 sm:grid-cols-2 gap-3 items-start">
            <div>
              <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
                Ticket Price (Philippine Peso ₱) <span class="text-rose-500">*</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 font-bold text-slate-500 dark:text-slate-400">
                  ₱
                </span>
                <input
                  type="number"
                  v-model.number="ticketPrice"
                  min="0"
                  step="any"
                  @blur="touchField('ticketPrice')"
                  placeholder="e.g. 100"
                  class="w-full pl-8 pr-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white transition focus:outline-none"
                  :class="[
                    showFieldError('ticketPrice')
                      ? 'border-rose-500 ring-1 ring-rose-500/20'
                      : 'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500'
                  ]"
                />
              </div>
              <p v-if="showFieldError('ticketPrice')" class="mt-1 text-[11px] font-semibold text-rose-600 dark:text-rose-400 flex items-center gap-1">
                <AlertCircle class="w-3 h-3" />
                <span>{{ getFieldError('ticketPrice') }}</span>
              </p>
            </div>

            <div class="p-2.5 rounded-xl bg-slate-100 dark:bg-[#252525] text-[11px] text-slate-600 dark:text-slate-400 space-y-0.5">
              <span class="font-bold text-slate-800 dark:text-slate-200">Price Note:</span>
              <p>Purchases can be made via Cashier POS or online registration passes.</p>
            </div>
          </div>
        </div>

        <!-- 15. Staff Assignment Section -->
        <div class="p-3.5 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-2.5">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2 font-bold text-slate-800 dark:text-slate-200">
              <Users class="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <span>Assigned Event Staff (Entrance Scanner & Check-in)</span>
            </div>
            <span class="text-[10px] text-slate-400">
              {{ assignedStaffIds.length }} assigned
            </span>
          </div>

          <div v-if="staffUsers.length > 0" class="grid grid-cols-1 sm:grid-cols-2 gap-2 max-h-36 overflow-y-auto pr-1">
            <label
              v-for="st in staffUsers"
              :key="st.id"
              class="flex items-center justify-between p-2 rounded-xl border transition cursor-pointer"
              :class="[
                assignedStaffIds.includes(st.id)
                  ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-300 dark:border-blue-800'
                  : 'bg-white dark:bg-[#1a1a1a] border-slate-200 dark:border-[#2c2c2c] hover:border-slate-300'
              ]"
            >
              <div class="flex items-center gap-2 min-w-0">
                <input
                  type="checkbox"
                  :value="st.id"
                  v-model="assignedStaffIds"
                  class="rounded text-blue-600 focus:ring-blue-500 shrink-0"
                />
                <div class="truncate">
                  <p class="font-bold text-slate-900 dark:text-white truncate">{{ st.name }}</p>
                  <p class="text-[10px] text-slate-400 truncate">{{ st.email }}</p>
                </div>
              </div>
              <span
                class="text-[9px] font-bold px-1.5 py-0.5 rounded shrink-0 uppercase"
                :class="st.isActive !== false ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300' : 'bg-rose-100 text-rose-800'"
              >
                {{ st.isActive !== false ? 'Active' : 'Inactive' }}
              </span>
            </label>
          </div>
          <div v-else class="text-[11px] text-slate-400 text-center py-2">
            No Event Staff accounts found in the system.
          </div>
        </div>

        <!-- Event Cover Image (Optional) -->
        <div class="space-y-3 p-4 bg-slate-50 dark:bg-[#1a1a1a] rounded-2xl border border-slate-200 dark:border-[#333333]">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-black uppercase tracking-tight text-slate-900 dark:text-white">
                Event Cover Image / Banner <span class="text-slate-400 font-normal lowercase">(optional)</span>
              </label>
              <p class="text-[11px] text-slate-500 dark:text-[#a0a0a0]">
                Upload landscape poster artwork (16:9 recommended • JPG, PNG, WEBP • Max 5MB).
              </p>
            </div>
            <span class="text-[10px] text-blue-600 dark:text-blue-400 font-bold px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-950/60 border border-blue-200 dark:border-blue-800">
              16:9 Ratio
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
            <!-- Upload Box -->
            <div class="sm:col-span-7">
              <label
                class="flex flex-col items-center justify-center border-2 border-dashed border-blue-300 dark:border-blue-700/60 hover:border-blue-500 rounded-2xl p-4 bg-white/70 dark:bg-[#222222] cursor-pointer transition text-center group"
              >
                <Upload class="w-6 h-6 text-blue-600 dark:text-blue-400 mb-1.5 group-hover:scale-110 transition-transform" />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                  {{ bannerUrl ? 'Replace Cover Image' : 'Click or Drag to Upload Cover' }}
                </span>
                <span class="text-[10px] text-slate-400 mt-0.5">JPG, JPEG, PNG, WEBP up to 5MB</span>
                <input
                  type="file"
                  accept="image/png, image/jpeg, image/jpg, image/webp"
                  @change="handleBannerFileUpload"
                  class="hidden"
                />
              </label>

              <div v-if="bannerUploadError" class="mt-2 text-[11px] text-rose-600 dark:text-rose-400 font-bold flex items-center gap-1">
                <AlertCircle class="w-3.5 h-3.5" />
                <span>{{ bannerUploadError }}</span>
              </div>
            </div>

            <!-- Visual Preview Card with Fallback -->
            <div class="sm:col-span-5 flex flex-col items-center">
              <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider block mb-1 self-start">
                Preview Appearance:
              </span>
              <div class="relative w-full aspect-[16/9] rounded-2xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-900 shadow-sm group">
                <img
                  v-if="bannerUrl"
                  :src="bannerUrl"
                  alt="Event Cover Preview"
                  class="w-full h-full object-cover"
                />
                <div v-else class="w-full h-full flex flex-col items-center justify-center p-3 text-center bg-gradient-to-br from-slate-900 via-blue-950 to-indigo-950">
                  <Calendar class="w-6 h-6 text-blue-300/70 mb-1" />
                  <span class="text-[10px] font-bold text-slate-300 uppercase tracking-wider">NCST Event Placeholder</span>
                  <span class="text-[9px] text-slate-400">Clean fallback if no image is uploaded</span>
                </div>

                <!-- Overlay Delete button if image present -->
                <button
                  v-if="bannerUrl"
                  type="button"
                  @click="bannerUrl = ''"
                  class="absolute top-2 right-2 p-1.5 bg-black/70 hover:bg-rose-600 text-white rounded-lg transition cursor-pointer shadow-md"
                  title="Remove uploaded image"
                >
                  <X class="w-3.5 h-3.5" />
                </button>
              </div>
            </div>
          </div>
        </div>

        <!-- Physical Ticket Pass Template Design (Default Blue & Gold vs Custom Artwork) -->
        <div class="space-y-3 p-4 bg-slate-50 dark:bg-[#1a1a1a] rounded-2xl border border-slate-200 dark:border-[#333333]">
          <div class="flex items-center justify-between">
            <div>
              <label class="block text-xs font-black uppercase tracking-tight text-slate-900 dark:text-white">
                Physical Ticket Template / Artwork <span class="text-slate-400 font-normal lowercase">(optional)</span>
              </label>
              <p class="text-[11px] text-slate-500 dark:text-[#a0a0a0]">
                Built-in Blue & Yellow NCST template is used by default. Upload custom artwork to replace background (JPG, PNG, WEBP • Max 5MB).
              </p>
            </div>
            <span
              class="text-[10px] font-bold px-2 py-0.5 rounded border"
              :class="ticketDesignUrl ? 'bg-purple-50 dark:bg-purple-950/60 text-purple-600 dark:text-purple-400 border-purple-200 dark:border-purple-800' : 'bg-blue-50 dark:bg-blue-950/60 text-blue-600 dark:text-blue-400 border-blue-200 dark:border-blue-800'"
            >
              {{ ticketDesignUrl ? 'Custom Template Active' : 'NCST Default Active' }}
            </span>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-12 gap-4 items-center">
            <!-- Upload Box -->
            <div class="sm:col-span-7 space-y-2">
              <label
                class="flex flex-col items-center justify-center border-2 border-dashed border-purple-300 dark:border-purple-700/60 hover:border-purple-500 rounded-2xl p-4 bg-white/70 dark:bg-[#222222] cursor-pointer transition text-center group"
              >
                <Upload class="w-6 h-6 text-purple-600 dark:text-purple-400 mb-1.5 group-hover:scale-110 transition-transform" />
                <span class="text-xs font-bold text-slate-800 dark:text-slate-200">
                  {{ ticketDesignUrl ? 'Replace Custom Ticket Design' : 'Upload Custom Ticket Design' }}
                </span>
                <span class="text-[10px] text-slate-400 mt-0.5">JPG, JPEG, PNG, WEBP up to 5MB</span>
                <input
                  type="file"
                  accept="image/png, image/jpeg, image/jpg, image/webp"
                  @change="handleTicketDesignUpload"
                  class="hidden"
                />
              </label>

              <div v-if="ticketDesignUrl" class="flex items-center justify-between px-2">
                <span class="text-[11px] text-slate-500">Custom background applied.</span>
                <button
                  type="button"
                  @click="ticketDesignUrl = ''"
                  class="text-xs text-rose-600 hover:text-rose-700 font-bold hover:underline cursor-pointer"
                >
                  Reset to NCST Blue & Yellow Default
                </button>
              </div>

              <div v-if="ticketDesignUploadError" class="mt-2 text-[11px] text-rose-600 dark:text-rose-400 font-bold flex items-center gap-1">
                <AlertCircle class="w-3.5 h-3.5" />
                <span>{{ ticketDesignUploadError }}</span>
              </div>
            </div>

            <!-- Visual Preview Card & Repositioning Controls -->
            <div class="sm:col-span-5 flex flex-col items-center">
              <div class="w-full flex items-center justify-between mb-1">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider">
                  Ticket Artwork Preview
                </span>
                <span v-if="ticketDesignUrl" class="text-[9.5px] text-purple-600 dark:text-purple-400 font-bold">
                  Y-Offset: {{ ticketDesignOffsetY }}%
                </span>
              </div>

              <!-- Interactive Draggable Preview Container -->
              <div
                class="relative w-full aspect-[2.7/1] rounded-xl overflow-hidden border border-slate-300 dark:border-slate-700 bg-slate-900 shadow-sm group select-none"
                :class="{ 'cursor-grab active:cursor-grabbing ring-2 ring-purple-500': ticketDesignUrl }"
                @mousedown="startPreviewDrag"
              >
                <img
                  v-if="ticketDesignUrl"
                  :src="ticketDesignUrl"
                  alt="Custom Ticket Design Preview"
                  class="w-full h-full object-cover pointer-events-none transition-[object-position] duration-75"
                  :style="{ objectPosition: `center ${ticketDesignOffsetY}%` }"
                  draggable="false"
                />
                <!-- Built-In NCST Default Ticket Preview when empty -->
                <div v-else class="w-full h-full flex flex-col items-center justify-center p-2 text-center bg-gradient-to-r from-[#091738] via-[#0e2759] to-[#18396f] text-white relative">
                  <div class="absolute -right-6 -top-6 w-20 h-20 bg-amber-400/15 rounded-full blur-lg" />
                  <span class="text-[9px] font-black uppercase text-amber-300 tracking-wider">NCST Blue & Yellow Default</span>
                  <span class="text-[8px] text-slate-200 mt-0.5">Built-in official university pass</span>
                </div>

                <!-- Subtle Drag Hint Badge -->
                <div
                  v-if="ticketDesignUrl"
                  class="absolute bottom-1.5 left-2 px-1.5 py-0.5 rounded bg-black/60 backdrop-blur-xs text-[8.5px] text-white/90 font-medium flex items-center gap-1 pointer-events-none opacity-80 group-hover:opacity-100 transition"
                >
                  <span>↕ Drag to adjust framing</span>
                </div>

                <!-- Overlay Delete / Reset button if image present -->
                <button
                  v-if="ticketDesignUrl"
                  type="button"
                  @click.stop="ticketDesignUrl = ''"
                  class="absolute top-1.5 right-1.5 p-1 bg-black/70 hover:bg-rose-600 text-white rounded-md transition cursor-pointer shadow-md z-10"
                  title="Reset to NCST Default"
                >
                  <X class="w-3 h-3" />
                </button>
              </div>

              <!-- Quick Reposition Slider & Preset Buttons -->
              <div v-if="ticketDesignUrl" class="w-full mt-2 space-y-1.5 bg-white/60 dark:bg-[#202020] p-2 rounded-xl border border-slate-200 dark:border-slate-800">
                <div class="flex items-center gap-2">
                  <span class="text-[9px] font-bold text-slate-500 uppercase">Top</span>
                  <input
                    v-model.number="ticketDesignOffsetY"
                    type="range"
                    min="0"
                    max="100"
                    step="1"
                    class="flex-1 h-1.5 bg-slate-200 dark:bg-slate-700 rounded-lg appearance-none cursor-pointer accent-purple-600"
                  />
                  <span class="text-[9px] font-bold text-slate-500 uppercase">Bottom</span>
                </div>
                <div class="flex items-center justify-between gap-1 pt-0.5">
                  <button
                    type="button"
                    @click="ticketDesignOffsetY = 0"
                    class="text-[9px] px-1.5 py-0.5 rounded bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-950/40 text-slate-700 dark:text-slate-300 font-medium transition cursor-pointer"
                  >
                    Top (0%)
                  </button>
                  <button
                    type="button"
                    @click="ticketDesignOffsetY = 20"
                    class="text-[9px] px-1.5 py-0.5 rounded bg-purple-50 hover:bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 font-bold border border-purple-200 dark:border-purple-800 transition cursor-pointer"
                  >
                    Faces (20%)
                  </button>
                  <button
                    type="button"
                    @click="ticketDesignOffsetY = 50"
                    class="text-[9px] px-1.5 py-0.5 rounded bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-950/40 text-slate-700 dark:text-slate-300 font-medium transition cursor-pointer"
                  >
                    Center (50%)
                  </button>
                  <button
                    type="button"
                    @click="ticketDesignOffsetY = 80"
                    class="text-[9px] px-1.5 py-0.5 rounded bg-slate-100 hover:bg-purple-100 dark:bg-slate-800 dark:hover:bg-purple-950/40 text-slate-700 dark:text-slate-300 font-medium transition cursor-pointer"
                  >
                    Lower (80%)
                  </button>
                </div>
              </div>
            </div>
          </div>
        </div>

        <!-- Modal Footer Actions -->
        <div class="pt-3 flex items-center justify-end gap-3 border-t border-slate-200 dark:border-[#333333]">
          <button
            type="button"
            @click="isModalOpen = false"
            class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-700 dark:text-slate-300 font-semibold rounded-xl transition cursor-pointer"
          >
            Cancel
          </button>
          
          <button
            type="submit"
            class="px-5 py-2 rounded-xl font-bold shadow-md transition flex items-center gap-2 cursor-pointer bg-blue-600 hover:bg-blue-700 active:scale-95 text-white"
          >
            <Check class="w-4 h-4" />
            <span>{{ editingEvent ? 'Save Changes' : 'Create Event' }}</span>
          </button>
        </div>
      </form>
    </Modal>

    <!-- Delete Confirmation Modal -->
    <ConfirmModal
      :is-open="!!eventToDelete"
      :title="deleteError ? 'Deletion Notice' : 'Delete Event'"
      :message="deleteError ? deleteError : `Are you sure you want to delete event &quot;${eventToDelete?.title}&quot;? If the event has existing ticket sales or attendance logs, historical records will be safely preserved for auditing while the event is removed from active listings.`"
      :confirm-text="deleteError ? 'Retry Deletion' : (isDeleting ? 'Deleting...' : 'Delete Event')"
      :variant="deleteError ? 'warning' : 'danger'"
      @confirm="confirmDeleteEvent"
      @cancel="() => { if (!isDeleting) { eventToDelete = null; deleteError = ''; } }"
    />

    <!-- Academic Period Management Modal (Admin Authoritative Control) -->
    <AcademicPeriodModal
      :is-open="isAcademicPeriodModalOpen"
      @close="isAcademicPeriodModalOpen = false"
    />


  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Plus, Edit2, Trash2, MapPin, Upload, X,
  LayoutList, CalendarDays, Search, Check, Calendar, ChevronLeft, ChevronRight,
  AlertCircle, AlertTriangle, CheckCircle2, Clock, Users, DollarSign, Tag, GraduationCap,
  SlidersHorizontal
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem, EventStatus, User, AcademicPeriod } from '../../types';
import { formatPhilippineDate, getPhilippineDateString } from '../../utils/philippineTime';
import {
  validateEvent,
  normalizeTimeToDisplay,
  timeDisplayToInputTime,
  inputTimeToDisplay,
  EventFormData,
  ValidationResult
} from '../../utils/eventValidation';
import Modal from '../common/Modal.vue';
import ConfirmModal from '../common/ConfirmModal.vue';
import EventCoverImage from '../common/EventCoverImage.vue';
import AcademicPeriodModal from './AcademicPeriodModal.vue';
import { apiUrl } from '../../utils/apiUrl';

defineEmits<{
  (e: 'navigate', tab: string): void;
}>();

const {
  currentUser,
  events,
  createEvent,
  updateEvent,
  deleteEvent,
  currentEvent,
  setCurrentEvent,
  tickets,
  users,
  updateUser,
  academicPeriods,
  activeAcademicPeriod
} = useAppStore();

// View switcher state
const activeView = ref<'list' | 'calendar'>('list');

// Search & Filter state
const searchQuery = ref('');
const selectedStatusFilter = ref<string>('all');
const selectedCategoryFilter = ref<string>('all');
const selectedAcademicPeriodFilter = ref<string>('all');

// Academic Period Management Modal State
const isAcademicPeriodModalOpen = ref(false);

// Form Academic Scoping
const selectedAcademicYear = ref<string>(activeAcademicPeriod.value?.academicYear || '2026-2027');
const selectedSemester = ref<string>(activeAcademicPeriod.value?.semester || '1st Semester');
const availableAcademicYears = computed(() => {
  const years = new Set<string>();
  academicPeriods.value.forEach(p => years.add(p.academicYear));
  years.add('2025-2026');
  years.add('2026-2027');
  years.add('2027-2028');
  return Array.from(years).sort();
});

// Modals state
const isModalOpen = ref(false);
const isDetailsModalOpen = ref(false);
const editingEvent = ref<EventItem | null>(null);
const selectedEventForDetails = ref<EventItem | null>(null);
const detailsActiveTab = ref<'overview' | 'tickets' | 'staff'>('overview');
const eventToDelete = ref<{ id: string; title: string } | null>(null);

// Form state
const title = ref('');
const code = ref('');
const description = ref('');
const category = ref<string>('Institutional');
const venue = ref('NCST Gymnasium');
const date = ref(getPhilippineDateString(new Date()));
const startTimeInput = ref('08:00');
const endTimeInput = ref('17:00');
const capacity = ref<number | string>(600);
const ticketPrice = ref<number | string>(0);
const isFreeAdmission = ref(true);
const status = ref<EventStatus>('upcoming');
const bannerUrl = ref('');
const bannerUploadError = ref('');
const ticketDesignUrl = ref('');
const ticketDesignUploadError = ref('');
const ticketDesignOffsetY = ref<number>(20);
const isDraggingTicketPreview = ref(false);
const dragStartY = ref(0);
const dragStartOffsetY = ref(20);
const assignedStaffIds = ref<string[]>([]);

const startPreviewDrag = (e: MouseEvent) => {
  if (!ticketDesignUrl.value) return;
  isDraggingTicketPreview.value = true;
  dragStartY.value = e.clientY;
  dragStartOffsetY.value = ticketDesignOffsetY.value;

  const onMouseMove = (ev: MouseEvent) => {
    if (!isDraggingTicketPreview.value) return;
    const deltaY = ev.clientY - dragStartY.value;
    // dragging up raises offset, dragging down lowers offset
    const newOffset = Math.min(100, Math.max(0, Math.round(dragStartOffsetY.value - deltaY * 0.5)));
    ticketDesignOffsetY.value = newOffset;
  };

  const onMouseUp = () => {
    isDraggingTicketPreview.value = false;
    window.removeEventListener('mousemove', onMouseMove);
    window.removeEventListener('mouseup', onMouseUp);
  };

  window.addEventListener('mousemove', onMouseMove);
  window.addEventListener('mouseup', onMouseUp);
};

// Form validation tracking
const touchedFields = ref<Record<string, boolean>>({});
const hasAttemptedSubmit = ref(false);

const todayPstDate = computed(() => getPhilippineDateString(new Date()));

const touchField = (field: string) => {
  touchedFields.value[field] = true;
};

// Form Data Object for Validation
const currentFormData = computed<EventFormData>(() => ({
  id: editingEvent.value?.id,
  title: title.value,
  code: code.value,
  description: description.value,
  category: category.value,
  venue: venue.value,
  date: date.value,
  startTime: inputTimeToDisplay(startTimeInput.value),
  endTime: inputTimeToDisplay(endTimeInput.value),
  capacity: capacity.value,
  ticketPrice: isFreeAdmission.value ? 0 : ticketPrice.value,
  isFreeAdmission: isFreeAdmission.value,
  status: status.value,
  bannerUrl: bannerUrl.value,
  ticketDesignUrl: ticketDesignUrl.value,
  ticketDesignOffsetY: ticketDesignOffsetY.value,
  assignedStaffIds: assignedStaffIds.value,
}));

// Real-time Master Validation Result
const validationResult = computed<ValidationResult>(() => {
  return validateEvent(currentFormData.value, {
    isEditing: !!editingEvent.value,
    existingEvents: events.value,
    staffUsers: users.value.filter(u => u.role === 'event_staff'),
  });
});

const showFieldError = (field: string): boolean => {
  return (hasAttemptedSubmit.value || !!touchedFields.value[field]) && !!validationResult.value.errors[field];
};

const getFieldError = (field: string): string => {
  return validationResult.value.errors[field] || '';
};

// Formatted display times
const displayStartTimeFormatted = computed(() => {
  return inputTimeToDisplay(startTimeInput.value) || '08:00 AM';
});

const displayEndTimeFormatted = computed(() => {
  return inputTimeToDisplay(endTimeInput.value) || '05:00 PM';
});

const handleStartTimeChange = () => {
  touchField('startTime');
};

const handleEndTimeChange = () => {
  touchField('endTime');
};

const applyTimePreset = (start24: string, end24: string) => {
  startTimeInput.value = start24;
  endTimeInput.value = end24;
  touchField('startTime');
  touchField('endTime');
};

const setFreeAdmission = (free: boolean) => {
  isFreeAdmission.value = free;
  if (free) {
    ticketPrice.value = 0;
  } else if (Number(ticketPrice.value) === 0) {
    ticketPrice.value = 100;
  }
};

// Calendar Month State
const currentMonth = ref(new Date().getMonth());
const currentYear = ref(new Date().getFullYear());
const selectedDateStr = ref(getPhilippineDateString(new Date()));

const monthNames = [
  'January', 'February', 'March', 'April', 'May', 'June',
  'July', 'August', 'September', 'October', 'November', 'December'
];

// Computed Filtered Events
const filteredEvents = computed(() => {
  return events.value.filter(ev => {
    // Search
    const q = searchQuery.value.toLowerCase().trim();
    if (q) {
      const matchTitle = ev.title.toLowerCase().includes(q);
      const matchVenue = ev.venue.toLowerCase().includes(q);
      const matchCode = ev.code.toLowerCase().includes(q);
      if (!matchTitle && !matchVenue && !matchCode) return false;
    }

    // Status
    if (selectedStatusFilter.value !== 'all') {
      if (ev.status !== selectedStatusFilter.value) return false;
    }

    // Category
    if (selectedCategoryFilter.value !== 'all') {
      if (ev.category !== selectedCategoryFilter.value) return false;
    }

    // Academic Period
    if (selectedAcademicPeriodFilter.value !== 'all') {
      const targetPeriod = academicPeriods.value.find(p => p.id === selectedAcademicPeriodFilter.value);
      if (targetPeriod) {
        if (ev.academicYear !== targetPeriod.academicYear || ev.semester !== targetPeriod.semester) {
          return false;
        }
      }
    }

    return true;
  });
});

// Staff Users for Staff Assignment Tab
const staffUsers = computed(() => {
  return users.value.filter(u => u.role === 'event_staff');
});

const isStaffAssigned = (staff: User, eventId: string): boolean => {
  return Array.isArray(staff.assignedEventIds) && staff.assignedEventIds.includes(eventId);
};

const toggleStaffAssignment = (staff: User, eventId: string) => {
  const currentAssigned = Array.isArray(staff.assignedEventIds) ? [...staff.assignedEventIds] : [];
  if (currentAssigned.includes(eventId)) {
    const updated = currentAssigned.filter(id => id !== eventId);
    updateUser(staff.id, { assignedEventIds: updated });
  } else {
    currentAssigned.push(eventId);
    updateUser(staff.id, { assignedEventIds: currentAssigned });
  }
};

// Ticket counts helpers
const getEventTicketCount = (eventId: string) => {
  return tickets.value.filter(t => t.eventId === eventId).length;
};

const getEventPresentCount = (eventId: string) => {
  return tickets.value.filter(t => t.eventId === eventId && t.attendanceStatus === 'present').length;
};

// Calendar calculations
interface CalendarCell {
  dateStr: string;
  dayNumber: number;
  isCurrentMonth: boolean;
  isToday: boolean;
  dayEvents: EventItem[];
}

const calendarCells = computed<CalendarCell[]>(() => {
  const cells: CalendarCell[] = [];
  const todayStr = getPhilippineDateString(new Date());

  const firstDayOfWeek = new Date(currentYear.value, currentMonth.value, 1).getDay();
  const daysInMonth = new Date(currentYear.value, currentMonth.value + 1, 0).getDate();
  const daysInPrevMonth = new Date(currentYear.value, currentMonth.value, 0).getDate();

  // Prev month filler
  for (let i = firstDayOfWeek - 1; i >= 0; i--) {
    const dayNum = daysInPrevMonth - i;
    const prevM = currentMonth.value === 0 ? 11 : currentMonth.value - 1;
    const prevY = currentMonth.value === 0 ? currentYear.value - 1 : currentYear.value;
    const dateStr = `${prevY}-${String(prevM + 1).padStart(2, '0')}-${String(dayNum).padStart(2, '0')}`;
    const dayEvents = events.value.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: dayNum,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      dayEvents,
    });
  }

  // Current month
  for (let d = 1; d <= daysInMonth; d++) {
    const dateStr = `${currentYear.value}-${String(currentMonth.value + 1).padStart(2, '0')}-${String(d).padStart(2, '0')}`;
    const dayEvents = events.value.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: d,
      isCurrentMonth: true,
      isToday: dateStr === todayStr,
      dayEvents,
    });
  }

  // Next month filler (up to 42 cells total)
  const remaining = 42 - cells.length;
  for (let n = 1; n <= remaining; n++) {
    const nextM = currentMonth.value === 11 ? 0 : currentMonth.value + 1;
    const nextY = currentMonth.value === 11 ? currentYear.value + 1 : currentYear.value;
    const dateStr = `${nextY}-${String(nextM + 1).padStart(2, '0')}-${String(n).padStart(2, '0')}`;
    const dayEvents = events.value.filter(e => e.date === dateStr);
    cells.push({
      dateStr,
      dayNumber: n,
      isCurrentMonth: false,
      isToday: dateStr === todayStr,
      dayEvents,
    });
  }

  return cells;
});

const selectedDayEvents = computed(() => {
  if (!selectedDateStr.value) return [];
  return events.value.filter(e => e.date === selectedDateStr.value);
});

const handleSelectDate = (dateStr: string) => {
  selectedDateStr.value = dateStr;
};

const prevMonth = () => {
  if (currentMonth.value === 0) {
    currentMonth.value = 11;
    currentYear.value -= 1;
  } else {
    currentMonth.value -= 1;
  }
};

const nextMonth = () => {
  if (currentMonth.value === 11) {
    currentMonth.value = 0;
    currentYear.value += 1;
  } else {
    currentMonth.value += 1;
  }
};

const jumpToToday = () => {
  const now = new Date();
  currentMonth.value = now.getMonth();
  currentYear.value = now.getFullYear();
  selectedDateStr.value = getPhilippineDateString(now);
};

// Details Modal
const openDetailsModal = (ev: EventItem) => {
  selectedEventForDetails.value = ev;
  detailsActiveTab.value = 'overview';
  isDetailsModalOpen.value = true;
};

// Creation / Edit modal handlers
const openCreateModal = () => {
  editingEvent.value = null;
  touchedFields.value = {};
  hasAttemptedSubmit.value = false;

  title.value = '';
  code.value = `EVT-${new Date().getFullYear()}-${Math.floor(100 + Math.random() * 900)}`;
  description.value = '';
  category.value = 'Institutional';
  venue.value = 'NCST Gymnasium';
  date.value = getPhilippineDateString(new Date());
  startTimeInput.value = '08:00';
  endTimeInput.value = '17:00';
  capacity.value = 600;
  ticketPrice.value = 0;
  isFreeAdmission.value = true;
  status.value = 'upcoming';
  bannerUrl.value = 'data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="800" height="400" viewBox="0 0 800 400"><defs><linearGradient id="bg" x1="0%" y1="0%" x2="100%" y2="100%"><stop offset="0%" stop-color="%23061530"/><stop offset="50%" stop-color="%230f3066"/><stop offset="85%" stop-color="%231a4a9c"/><stop offset="100%" stop-color="%23d97706"/></linearGradient><linearGradient id="goldRibbon" x1="0%" y1="0%" x2="100%" y2="0%"><stop offset="0%" stop-color="%23f59e0b"/><stop offset="50%" stop-color="%23fbbf24"/><stop offset="100%" stop-color="%23fde68a"/></linearGradient></defs><rect width="800" height="400" fill="url(%23bg)"/><circle cx="710" cy="80" r="160" fill="%23fbbf24" opacity="0.18"/><path d="M0,320 Q220,240 460,305 T800,255 L800,400 L0,400 Z" fill="%23d97706" opacity="0.45"/><path d="M0,345 Q260,275 520,325 T800,285 L800,400 L0,400 Z" fill="%23081b3d" opacity="0.88"/><path d="M0,322 Q220,242 460,307 T800,257" fill="none" stroke="url(%23goldRibbon)" stroke-width="4" opacity="0.95"/><rect x="40" y="152" width="136" height="24" rx="6" fill="%23f59e0b" opacity="0.22"/><text x="48" y="169" fill="%23fde047" font-family="system-ui,sans-serif" font-weight="800" font-size="12" letter-spacing="1">NCST EVENT</text><text x="40" y="215" fill="%23ffffff" font-family="system-ui,sans-serif" font-weight="900" font-size="36" letter-spacing="-0.5">CAMPUS EVENT</text><text x="40" y="255" fill="%23fde047" font-family="system-ui,sans-serif" font-weight="700" font-size="17">Official National College of Science and Technology Event</text></svg>';
  ticketDesignUrl.value = '';
  ticketDesignOffsetY.value = 20;

  // Default: assign active staff accounts if any
  assignedStaffIds.value = users.value
    .filter(u => u.role === 'event_staff' && u.isActive !== false)
    .map(u => u.id);

  selectedAcademicYear.value = activeAcademicPeriod.value?.academicYear || '2026-2027';
  selectedSemester.value = activeAcademicPeriod.value?.semester || '1st Semester';

  isModalOpen.value = true;
};

const openCreateModalForDate = (dateStr: string) => {
  openCreateModal();
  date.value = dateStr;
};

const openEditModal = (ev: EventItem) => {
  editingEvent.value = ev;
  touchedFields.value = {};
  hasAttemptedSubmit.value = false;

  title.value = ev.title;
  code.value = ev.code;
  description.value = ev.description;
  category.value = ev.category;
  venue.value = ev.venue;
  date.value = ev.date;
  startTimeInput.value = timeDisplayToInputTime(ev.startTime);
  endTimeInput.value = timeDisplayToInputTime(ev.endTime);
  capacity.value = ev.capacity;
  ticketPrice.value = ev.ticketPrice;
  isFreeAdmission.value = Number(ev.ticketPrice) === 0;
  status.value = ev.status;
  bannerUrl.value = ev.bannerUrl;
  ticketDesignUrl.value = ev.ticketDesignUrl || '';
  ticketDesignOffsetY.value = ev.ticketDesignOffsetY ?? 20;

  selectedAcademicYear.value = ev.academicYear || activeAcademicPeriod.value?.academicYear || '2026-2027';
  selectedSemester.value = ev.semester || activeAcademicPeriod.value?.semester || '1st Semester';

  // Load existing assigned staff for this event
  assignedStaffIds.value = users.value
    .filter(u => u.role === 'event_staff' && isStaffAssigned(u, ev.id))
    .map(u => u.id);

  isModalOpen.value = true;
};

const handleBannerFileUpload = (e: Event) => {
  bannerUploadError.value = '';
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) return;
  const file = target.files[0];
  
  // Validate format
  const validFormats = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validFormats.includes(file.type.toLowerCase())) {
    bannerUploadError.value = 'Invalid image format. Please select a JPG, JPEG, PNG, or WEBP file.';
    target.value = '';
    return;
  }

  // Validate size (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    bannerUploadError.value = 'File size exceeds 5MB limit. Please upload an image under 5MB.';
    target.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = (event) => {
    if (event.target?.result) {
      bannerUrl.value = event.target.result as string;
      bannerUploadError.value = '';
    }
  };
  reader.readAsDataURL(file);
};

const handleTicketDesignUpload = (e: Event) => {
  ticketDesignUploadError.value = '';
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0) return;
  const file = target.files[0];
  
  // Validate format (images only)
  const validFormats = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp'];
  if (!validFormats.includes(file.type.toLowerCase())) {
    ticketDesignUploadError.value = 'Invalid image format. Please upload a JPG, JPEG, PNG, or WEBP file.';
    target.value = '';
    return;
  }

  // Validate size (max 5MB)
  if (file.size > 5 * 1024 * 1024) {
    ticketDesignUploadError.value = 'File size exceeds 5MB limit. Please upload an image under 5MB.';
    target.value = '';
    return;
  }

  const reader = new FileReader();
  reader.onload = (event) => {
    if (event.target?.result) {
      ticketDesignUrl.value = event.target.result as string;
      ticketDesignUploadError.value = '';
    }
  };
  reader.readAsDataURL(file);
};

const handleSubmit = async () => {
  hasAttemptedSubmit.value = true;

  // Mark all fields touched
  ['title', 'category', 'venue', 'date', 'startTime', 'endTime', 'ticketPrice'].forEach(k => {
    touchedFields.value[k] = true;
  });

  if (!validationResult.value.isValid) {
    return;
  }

  const finalStartTime = inputTimeToDisplay(startTimeInput.value);
  const finalEndTime = inputTimeToDisplay(endTimeInput.value);
  const finalPrice = isFreeAdmission.value ? 0 : Number(ticketPrice.value) || 0;
  const finalCapacity = Number(capacity.value) || 500;

  // Attempt backend API validation & sync
  try {
    const payload = {
      id: editingEvent.value?.id,
      title: title.value.trim(),
      code: code.value.trim(),
      description: description.value.trim(),
      category: category.value,
      venue: venue.value.trim(),
      date: date.value.trim(),
      startTime: finalStartTime,
      endTime: finalEndTime,
      capacity: finalCapacity,
      ticketPrice: finalPrice,
      isFreeAdmission: isFreeAdmission.value,
      status: status.value,
      academicYear: selectedAcademicYear.value,
      semester: selectedSemester.value,
      organizer: 'Office of Student Affairs',
      bannerUrl: bannerUrl.value,
      ticketDesignUrl: ticketDesignUrl.value || undefined,
      assignedStaffIds: assignedStaffIds.value,
    };

    // Call server validation endpoint if available
    await fetch(apiUrl('/api/validate-event'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify(payload)
    }).catch(() => null);
  } catch {}

  if (editingEvent.value) {
    const res = updateEvent(
      editingEvent.value.id,
      {
        title: title.value.trim(),
        code: code.value.trim(),
        description: description.value.trim(),
        category: category.value,
        venue: venue.value.trim(),
        date: date.value.trim(),
        startTime: finalStartTime,
        endTime: finalEndTime,
        capacity: finalCapacity,
        ticketPrice: finalPrice,
        status: status.value,
        academicYear: selectedAcademicYear.value,
        semester: selectedSemester.value,
        bannerUrl: bannerUrl.value,
        ticketDesignUrl: ticketDesignUrl.value || undefined,
        ticketDesignOffsetY: ticketDesignOffsetY.value,
      },
      assignedStaffIds.value
    );

    if (res.success) {
      isModalOpen.value = false;
      hasAttemptedSubmit.value = false;
    }
  } else {
    const res = createEvent(
      {
        title: title.value.trim(),
        code: code.value.trim(),
        description: description.value.trim(),
        category: category.value,
        venue: venue.value.trim(),
        date: date.value.trim(),
        startTime: finalStartTime,
        endTime: finalEndTime,
        capacity: finalCapacity,
        ticketPrice: finalPrice,
        status: status.value,
        academicYear: selectedAcademicYear.value,
        semester: selectedSemester.value,
        organizer: 'Office of Student Affairs',
        bannerUrl: bannerUrl.value,
        ticketDesignUrl: ticketDesignUrl.value || undefined,
        ticketDesignOffsetY: ticketDesignOffsetY.value,
      },
      assignedStaffIds.value
    );

    if (res.success) {
      if (res.event) {
        setCurrentEvent(res.event);
      }
      isModalOpen.value = false;
      hasAttemptedSubmit.value = false;
    }
  }
};

const isDeleting = ref(false);
const deleteError = ref('');

const handleDelete = (id: string, evTitle: string) => {
  eventToDelete.value = { id, title: evTitle };
  deleteError.value = '';
};

const confirmDeleteEvent = async () => {
  if (!eventToDelete.value || isDeleting.value) return;
  isDeleting.value = true;
  deleteError.value = '';
  try {
    const res = await deleteEvent(eventToDelete.value.id);
    if (res.success) {
      eventToDelete.value = null;
      deleteError.value = '';
    } else {
      deleteError.value = res.message || 'Failed to delete event.';
    }
  } catch (err: any) {
    deleteError.value = err?.message || 'Network error: Failed to communicate with server.';
  } finally {
    isDeleting.value = false;
  }
};
</script>
