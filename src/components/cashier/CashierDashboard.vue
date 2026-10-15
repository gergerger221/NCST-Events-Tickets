<template>
  <div class="space-y-6 max-w-5xl mx-auto">
    <!-- ==================== 1. HEADER & OPERATOR CONTEXT ==================== -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-7 shadow-xs transition-colors space-y-5">
      
      <!-- Top Row: Station Badge, Operator Info & Secondary Navigation -->
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-slate-100 dark:border-[#282828] pb-3.5">
        <div class="flex items-center gap-3 flex-wrap">
          <div class="w-8 h-8 rounded-xl bg-[#1E3A5F]/10 dark:bg-blue-500/20 text-[#1E3A5F] dark:text-blue-400 flex items-center justify-center shrink-0">
            <TicketIcon class="w-4 h-4" />
          </div>
          <span class="text-[10px] font-black uppercase tracking-wider px-2.5 py-1 rounded-full bg-slate-100 dark:bg-[#252525] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]">
            Ticket POS Station
          </span>
          <div class="flex items-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
            <span>Operator: <strong class="text-slate-900 dark:text-white font-bold">{{ currentUser?.name || 'Cashier Desk' }}</strong></span>
          </div>
        </div>

        <!-- Quick Secondary Links -->
        <div class="flex items-center gap-2 flex-wrap">
          <button
            type="button"
            @click="$emit('navigate', 'qr_print_queue')"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 rounded-xl text-xs font-bold transition cursor-pointer"
            title="Open Dynamic QR Label Print Queue"
          >
            <Printer class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
            <span>QR Print Queue</span>
            <span v-if="pendingQrTickets.length > 0" class="px-1.5 py-0.2 rounded-full bg-amber-500 text-white text-[9px] font-black">
              {{ pendingQrTickets.length }}
            </span>
          </button>

          <button
            type="button"
            @click="$emit('navigate', 'tickets')"
            class="flex items-center gap-1.5 px-3 py-1.5 bg-slate-100 dark:bg-[#252525] hover:bg-slate-200 dark:hover:bg-[#2e2e2e] text-slate-700 dark:text-slate-200 border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold transition cursor-pointer"
            title="Open Issued Tickets Registry"
          >
            <TicketIcon class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" />
            <span>Ticket Registry →</span>
          </button>
        </div>
      </div>

      <!-- Main Header Row: Spacious Title & Live Stat Badges -->
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div class="space-y-1">
          <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight leading-snug">
            Event Ticket POS & Pass Generator
          </h1>
          <p class="text-xs text-slate-500 dark:text-[#a0a0a0] max-w-xl">
            Register attendees, record payments with exact change calculation, and issue verified QR admission passes.
          </p>
        </div>
      </div>
    </div>

    <!-- ==================== 2. SINGLE-PURPOSE TRANSACTION FORM ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-6 transition-colors">
      
      <!-- ==================== STEP 1: ATTENDEE INFORMATION ==================== -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-[#282828] pb-3">
          <h2 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2.5">
            <span class="w-6 h-6 rounded-lg bg-[#1E3A5F]/10 dark:bg-blue-500/20 text-[#1E3A5F] dark:text-blue-400 text-xs flex items-center justify-center font-black">01</span>
            <span>Attendee Information</span>
          </h2>
          <span class="text-[11px] font-semibold text-slate-400">
            Select attendee classification & verify details
          </span>
        </div>

        <!-- Attendee Type Segmented Control -->
        <div class="p-1 bg-slate-100 dark:bg-[#141414] rounded-2xl border border-slate-200 dark:border-[#2a2a2a] grid grid-cols-2 gap-1.5">
          <button
            type="button"
            @click="setAttendeeType('student')"
            class="py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
            :class="attendeeType === 'student'
              ? 'bg-white dark:bg-[#252525] text-[#1E3A5F] dark:text-white shadow-xs font-black'
              : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
          >
            <GraduationCap class="w-4 h-4" />
            <span>Enrolled Student</span>
          </button>
          <button
            type="button"
            @click="setAttendeeType('outsider')"
            class="py-2.5 px-4 rounded-xl text-xs font-bold transition-all flex items-center justify-center gap-2 cursor-pointer"
            :class="attendeeType === 'outsider'
              ? 'bg-white dark:bg-[#252525] text-[#1E3A5F] dark:text-white shadow-xs font-black'
              : 'text-slate-500 dark:text-slate-400 hover:text-slate-800 dark:hover:text-slate-200'"
          >
            <Users class="w-4 h-4" />
            <span>Outsider / Guest</span>
          </button>
        </div>

        <!-- Student Search & Auto-Fill (Enrolled Student Only) -->
        <div v-if="attendeeType === 'student'" class="space-y-2 relative">
          <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300">
            Search Student ID or Full Name
          </label>
          <div class="relative">
            <Search class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              type="text"
              ref="searchInputRef"
              v-model="searchQuery"
              @input="handleSearchInput"
              @keydown.enter.prevent="handleQuickSubmit"
              placeholder="Search Student ID (e.g. 2026-00125) or Name (e.g. Juan)..."
              class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-medium text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
            />
          </div>

          <!-- Auto-Suggest Dropdown -->
          <div
            v-if="suggestedStudents.length > 0 && isSearching"
            class="absolute z-20 left-0 right-0 mt-1 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#3a3a3a] rounded-2xl shadow-xl max-h-48 overflow-y-auto divide-y divide-slate-100 dark:divide-[#333333]"
          >
            <div
              v-for="s in suggestedStudents"
              :key="s.id"
              @click="selectStudent(s)"
              class="p-3 hover:bg-slate-50 dark:hover:bg-[#2c2c2c] cursor-pointer flex items-center justify-between text-xs transition"
            >
              <div>
                <p class="font-bold text-slate-900 dark:text-white">{{ s.name }}</p>
                <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3]">
                  {{ s.course || 'BSIT' }} • {{ s.yearLevel || '3rd Year' }} • {{ s.section || '11M1' }}
                </p>
              </div>
              <span class="font-mono text-xs font-bold text-blue-600 dark:text-blue-400 px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/30">
                {{ s.studentId || 'N/A' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Form Fields Grid -->
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 p-4 bg-slate-50 dark:bg-[#222222] rounded-2xl border border-slate-200 dark:border-[#2e2e2e]">
          
          <!-- 1. STUDENT ID (Student Mode) / GUEST REFERENCE (Guest Mode) -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                {{ attendeeType === 'student' ? 'Student ID Number *' : 'Guest Reference Code *' }}
              </label>
              <button
                v-if="attendeeType === 'outsider'"
                type="button"
                @click="regenerateGuestRef"
                class="text-[10px] font-bold text-purple-600 dark:text-purple-400 hover:underline flex items-center gap-1 cursor-pointer"
                title="Generate new unique guest code"
              >
                <RotateCw class="w-2.5 h-2.5" />
                <span>Auto-Generate</span>
              </button>
            </div>

            <div class="relative">
              <input
                type="text"
                v-model="formStudentId"
                @blur="touchField('studentId')"
                @input="touchField('studentId')"
                @keydown.enter.prevent="handleQuickSubmit"
                :placeholder="attendeeType === 'student' ? '2026-00125' : 'GUEST-1234'"
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-mono font-bold focus:outline-none transition-colors"
                :class="[
                  attendeeType === 'student' ? 'text-blue-600 dark:text-blue-400' : 'text-purple-600 dark:text-purple-400',
                  touched.studentId && (attendeeType === 'student' ? !studentIdValidation.isValid : !guestRefValidation.isValid)
                    ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                    : touched.studentId && (attendeeType === 'student' ? studentIdValidation.isValid : guestRefValidation.isValid)
                    ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                    : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                ]"
                required
              />
            </div>

            <!-- Inline Feedback for Student ID / Guest Ref -->
            <div v-if="touched.studentId || formStudentId" class="mt-1 min-h-[16px]">
              <template v-if="attendeeType === 'student'">
                <p v-if="isLookingUpStudent" class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold flex items-center gap-1">
                  <RotateCw class="w-3 h-3 shrink-0 animate-spin" />
                  <span>Checking authoritative student registry...</span>
                </p>
                <div v-else-if="matchedStudentRecord" class="space-y-0.5">
                  <p v-if="matchedStudentRecord.accountStatus === 'active'" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>🟢 Registered Portal Account (Active) — Roster Verified</span>
                  </p>
                  <p v-else-if="matchedStudentRecord.accountStatus === 'pending_activation'" class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>🟡 Registered (Pending Activation) — Ticket Eligible</span>
                  </p>
                  <p v-else-if="matchedStudentRecord.accountStatus === 'unregistered'" class="text-[11px] text-blue-600 dark:text-blue-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>🔵 Official Institutional Roster (Unregistered Account) — Ticket Eligible</span>
                  </p>
                  <p v-else-if="matchedStudentRecord.verificationStatus === 'pending'" class="text-[11px] text-orange-600 dark:text-orange-400 font-semibold flex items-center gap-1">
                    <AlertCircle class="w-3 h-3 shrink-0" />
                    <span>🟠 Self-Registered Account (Pending Official Roster Verification)</span>
                  </p>
                  <p v-else class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>Authoritative Student Record Verified</span>
                  </p>
                </div>
                <p v-else-if="studentLookupError" class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>⚠️ {{ studentLookupError }}</span>
                </p>
                <p v-else-if="studentIdValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Valid NCST Student ID format</span>
                </p>
                <p v-else-if="touched.studentId" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ studentIdValidation.error }}</span>
                </p>
              </template>
              <template v-else>
                <p v-if="guestRefValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Unique Guest Reference</span>
                </p>
                <p v-else-if="touched.studentId" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ guestRefValidation.error }}</span>
                </p>
              </template>
            </div>
            <p v-else class="text-[10px] text-slate-400 mt-1">
              {{ attendeeType === 'student' ? 'Format: YYYY-XXXXX (e.g. 2026-00125)' : 'System auto-generated code' }}
            </p>
          </div>

          <!-- 2. NAME FIELDS -->
          <!-- STUDENT: Full Name + Official Surname -->
          <template v-if="attendeeType === 'student'">
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                  Full Name *
                </label>
                <span v-if="isAuthoritativeStudentMatch" class="text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                  🔒 Authoritative Record
                </span>
              </div>
              <input
                type="text"
                v-model="formName"
                @blur="touchField('name')"
                @input="touchField('name')"
                :readonly="isAuthoritativeStudentMatch"
                placeholder="Juan Dela Cruz"
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none transition-colors"
                :class="[
                  isAuthoritativeStudentMatch ? 'bg-slate-50 dark:bg-[#202020] text-slate-700 dark:text-slate-300 cursor-not-allowed' : '',
                  touched.name && !nameValidation.isValid
                    ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                    : touched.name && nameValidation.isValid
                    ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                    : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                ]"
                required
              />

              <!-- Inline Feedback for Name -->
              <div v-if="touched.name || formName" class="mt-1 min-h-[16px]">
                <p v-if="nameValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>{{ isAuthoritativeStudentMatch ? 'Full name for identification (StudentRecord)' : 'Valid Attendee Name' }}</span>
                </p>
                <p v-else-if="touched.name" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ nameValidation.error }}</span>
                </p>
              </div>
              <p v-else class="text-[10px] text-slate-400 mt-1">
                Letters, spaces, hyphens, apostrophes (preserves suffixes like Jr., Sr., II, III)
              </p>
            </div>

            <!-- OFFICIAL SURNAME (STUDENT ONLY - SEPARATE AUTHORITATIVE FIELD) -->
            <div>
              <div class="flex items-center justify-between mb-1">
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                  Last Name / Surname *
                </label>
                <span v-if="isAuthoritativeStudentMatch && hasAuthoritativeSurname" class="text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                  🔒 Official Surname
                </span>
                <span v-else-if="isAuthoritativeStudentMatch && !hasAuthoritativeSurname" class="text-[9px] font-black uppercase text-amber-600 dark:text-amber-400 bg-amber-50 dark:bg-amber-950/60 px-1.5 py-0.5 rounded border border-amber-200 dark:border-amber-800">
                  ⚠️ Missing in Roster
                </span>
              </div>
              <input
                type="text"
                v-model="formLastName"
                @blur="touchField('lastName')"
                @input="touchField('lastName')"
                :readonly="isAuthoritativeStudentMatch && hasAuthoritativeSurname"
                placeholder="e.g. Dela Cruz, Santos, De Los Santos"
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none transition-colors"
                :class="[
                  isAuthoritativeStudentMatch && hasAuthoritativeSurname ? 'bg-slate-50 dark:bg-[#202020] text-slate-700 dark:text-slate-300 cursor-not-allowed' : '',
                  isAuthoritativeStudentMatch && !hasAuthoritativeSurname ? 'border-amber-400 dark:border-amber-500 bg-amber-50/20' : '',
                  touched.lastName && !lastNameValidation.isValid
                    ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                    : touched.lastName && lastNameValidation.isValid && !(isAuthoritativeStudentMatch && !hasAuthoritativeSurname)
                    ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                    : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                ]"
                required
              />

              <!-- Inline Feedback for Last Name / Surname -->
              <div class="mt-1 min-h-[16px]">
                <p v-if="isAuthoritativeStudentMatch && hasAuthoritativeSurname" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Official surname retrieved from authoritative StudentRecord</span>
                </p>
                <p v-else-if="isAuthoritativeStudentMatch && !hasAuthoritativeSurname" class="text-[11px] text-amber-600 dark:text-amber-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>Authoritative surname is missing from roster. Please input manually (no guessing).</span>
                </p>
                <p v-else-if="touched.lastName && lastNameValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Valid Last Name / Surname</span>
                </p>
                <p v-else-if="touched.lastName && !lastNameValidation.isValid" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ lastNameValidation.error }}</span>
                </p>
                <p v-else class="text-[10px] text-slate-400">
                  Official family name (supports multi-word surnames e.g. Dela Cruz)
                </p>
              </div>
            </div>
          </template>

          <!-- OUTSIDER: First Name + Last Name / Surname in 2 columns -->
          <template v-else>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
              <!-- First Name -->
              <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                  First Name *
                </label>
                <input
                  type="text"
                  v-model="formFirstName"
                  @blur="touchField('firstName')"
                  @input="touchField('firstName')"
                  placeholder="e.g. Maria"
                  class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none transition-colors"
                  :class="[
                    touched.firstName && !firstNameValidation.isValid
                      ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                      : touched.firstName && firstNameValidation.isValid
                      ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                      : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                  ]"
                  required
                />
                <div class="mt-1 min-h-[16px]">
                  <p v-if="touched.firstName && firstNameValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>Valid First Name</span>
                  </p>
                  <p v-else-if="touched.firstName && !firstNameValidation.isValid" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                    <AlertCircle class="w-3 h-3 shrink-0" />
                    <span>{{ firstNameValidation.error }}</span>
                  </p>
                  <p v-else class="text-[10px] text-slate-400">
                    Given name of attendee
                  </p>
                </div>
              </div>

              <!-- Last Name / Surname -->
              <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                  Last Name / Surname *
                </label>
                <input
                  type="text"
                  v-model="formLastName"
                  @blur="touchField('lastName')"
                  @input="touchField('lastName')"
                  placeholder="e.g. Santos, Dela Cruz"
                  class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none transition-colors"
                  :class="[
                    touched.lastName && !lastNameValidation.isValid
                      ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                      : touched.lastName && lastNameValidation.isValid
                      ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                      : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                  ]"
                  required
                />
                <div class="mt-1 min-h-[16px]">
                  <p v-if="touched.lastName && lastNameValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3 h-3 shrink-0" />
                    <span>Valid Last Name / Surname</span>
                  </p>
                  <p v-else-if="touched.lastName && !lastNameValidation.isValid" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                    <AlertCircle class="w-3 h-3 shrink-0" />
                    <span>{{ lastNameValidation.error }}</span>
                  </p>
                  <p v-else class="text-[10px] text-slate-400">
                    Family name (used as initial password)
                  </p>
                </div>
              </div>
            </div>
          </template>

          <!-- STUDENT ONLY: Course, Year Level, Section -->
          <template v-if="attendeeType === 'student'">
            <!-- Course / Program -->
            <div>
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Course / Program *
              </label>
              <input
                type="text"
                list="cashier-course-presets"
                v-model="formCourse"
                @blur="touchField('course')"
                :readonly="isAuthoritativeStudentMatch"
                placeholder="e.g. BSIT, BSCS, BSCpE, BSBA..."
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 uppercase placeholder:normal-case"
                :class="{ 'bg-slate-50 dark:bg-[#202020] text-slate-700 dark:text-slate-300 cursor-not-allowed': isAuthoritativeStudentMatch }"
                required
              />
              <datalist id="cashier-course-presets">
                <option value="BSIT">BS Information Technology (BSIT)</option>
                <option value="BSCS">BS Computer Science (BSCS)</option>
                <option value="BSCpE">BS Computer Engineering (BSCpE)</option>
                <option value="BSBA">BS Business Administration (BSBA)</option>
                <option value="BSED">Bachelor of Secondary Education (BSED)</option>
                <option value="BSHM">BS Hospitality Management (BSHM)</option>
                <option value="BSCRIM">BS Criminology (BSCRIM)</option>
                <option value="BSA">BS Accountancy (BSA)</option>
                <option value="BSN">BS Nursing (BSN)</option>
                <option value="BSTM">BS Tourism Management (BSTM)</option>
              </datalist>
              <p class="text-[10px] text-slate-400 mt-1">Institutional academic program</p>
            </div>

            <!-- Year Level & Section (Grid) -->
            <div class="grid grid-cols-2 gap-2">
              <!-- Year Level (Fixed Select) -->
              <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                  Year Level *
                </label>
                <select
                  v-model="formYearLevel"
                  @change="touchField('yearLevel')"
                  :disabled="isAuthoritativeStudentMatch"
                  class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer disabled:bg-slate-50 dark:disabled:bg-[#202020] disabled:cursor-not-allowed"
                >
                  <option value="1st Year">1st Year</option>
                  <option value="2nd Year">2nd Year</option>
                  <option value="3rd Year">3rd Year</option>
                  <option value="4th Year">4th Year</option>
                </select>
                <div v-if="yearLevelValidation.isValid" class="mt-1">
                  <p class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                    <CheckCircle2 class="w-2.5 h-2.5" />
                    <span>Selected</span>
                  </p>
                </div>
              </div>

              <!-- Section (Alphanumeric NCST Pattern) -->
              <div>
                <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                  Section *
                </label>
                <input
                  type="text"
                  list="cashier-section-presets"
                  v-model="formSection"
                  @blur="touchField('section')"
                  @input="touchField('section')"
                  :readonly="isAuthoritativeStudentMatch"
                  placeholder="e.g. 11M1, 21M1, 31A1..."
                  class="w-full px-2.5 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none uppercase placeholder:normal-case transition-colors"
                  :class="[
                    isAuthoritativeStudentMatch ? 'bg-slate-50 dark:bg-[#202020] text-slate-700 dark:text-slate-300 cursor-not-allowed' : '',
                    touched.section && !sectionValidation.isValid
                      ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                      : touched.section && sectionValidation.isValid
                      ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                      : 'border-slate-200 dark:border-[#333333] focus:border-blue-500'
                  ]"
                  required
                />
                <datalist id="cashier-section-presets">
                  <option v-for="sec in (availableSections || ['11M1', '11M2', '12M1', '21M1', '21M2', '31M1', '31A1', '41M1'])" :key="sec" :value="sec">
                    Section {{ sec }}
                  </option>
                </datalist>

                <!-- Section Feedback -->
                <div v-if="touched.section || formSection" class="mt-1">
                  <p v-if="sectionValidation.isValid" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                    <CheckCircle2 class="w-2.5 h-2.5 shrink-0" />
                    <span>Valid Section</span>
                  </p>
                  <p v-else-if="touched.section" class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-0.5">
                    <AlertCircle class="w-2.5 h-2.5 shrink-0" />
                    <span>{{ sectionValidation.error }}</span>
                  </p>
                </div>
              </div>
            </div>
          </template>



        </div>
      </div>

      <!-- ==================== STEP 2: EVENT & CASH PAYMENT DETAILS ==================== -->
      <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-[#282828]">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-[#282828] pb-3">
          <h2 class="text-xs sm:text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2.5">
            <span class="w-6 h-6 rounded-lg bg-[#1E3A5F]/10 dark:bg-blue-500/20 text-[#1E3A5F] dark:text-blue-400 text-xs flex items-center justify-center font-black">02</span>
            <span>Event & Cash Payment Details</span>
          </h2>
          <span class="text-[11px] font-semibold text-slate-400">
            Select event, input cash, calculate change
          </span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
          <!-- Target Event -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1">
              Target Event *
            </label>
            <select
              v-model="selectedEventId"
              :disabled="!eligibleEvents || eligibleEvents.length === 0"
              class="w-full px-3 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
            >
              <option v-if="!eligibleEvents || eligibleEvents.length === 0" value="" disabled selected>
                No Active / Eligible Events Available
              </option>
              <option v-for="ev in eligibleEvents" :key="ev.id" :value="ev.id">
                {{ ev.title }} ({{ ev.code }}) • A.Y. {{ ev.academicYear || '2026-2027' }} {{ ev.semester || '1st Sem' }}
              </option>
            </select>
          </div>

          <!-- Admission Type (Authoritatively derived from Event Data) -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1">
              Admission Fee / Rule *
            </label>
            <div class="px-3 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold flex items-center justify-between">
              <span :class="isEventPaid ? 'text-[#15803D] dark:text-emerald-400 font-bold' : 'text-[#475569] dark:text-slate-300 font-bold'">
                {{ isEventPaid ? `PAID ADMISSION — ₱${(selectedEvent?.ticketPrice || 0)}.00` : 'FREE ADMISSION (Official Pass)' }}
              </span>
              <span
                class="text-[10px] uppercase px-2 py-0.5 rounded font-bold"
                :class="isEventPaid ? 'bg-emerald-100 dark:bg-emerald-950 text-[#15803D] dark:text-emerald-300' : 'bg-slate-100 dark:bg-[#252525] text-[#475569] dark:text-slate-300 border border-slate-200 dark:border-[#383838]'"
              >
                {{ isEventPaid ? 'CASHIER POS' : 'COMPLIMENTARY' }}
              </span>
            </div>
          </div>
        </div>

        <!-- Total Due & Cashier Payment Flow (Rule 4: Cash Received & Change Calculation) -->
        <div v-if="selectedEvent" class="p-5 rounded-3xl bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#2e2e2e] space-y-4">
          
          <!-- Event Header Summary -->
          <div class="flex items-center justify-between">
            <div>
              <p class="font-bold text-slate-900 dark:text-white text-sm">{{ selectedEvent.title }}</p>
              <p class="text-slate-500 dark:text-[#a0a0a0] text-[11px] font-medium mt-0.5">
                {{ selectedEvent.date }} • {{ selectedEvent.venue }} • <span class="font-bold text-blue-600 dark:text-blue-400">A.Y. {{ selectedEvent.academicYear || '2026-2027' }} {{ selectedEvent.semester || '1st Semester' }}</span>
              </p>
            </div>
            <div class="text-right">
              <span class="text-[10px] uppercase font-extrabold text-slate-400 block">Amount Due</span>
              <span class="text-2xl font-black text-slate-900 dark:text-white">
                {{ isEventPaid ? `₱${(selectedEvent.ticketPrice || 0)}.00` : 'FREE' }}
              </span>
            </div>
          </div>

          <!-- Stock Depleted Warning Banner -->
          <div v-if="isStockDepleted" class="p-3 bg-rose-50 dark:bg-rose-950/50 border border-rose-300 dark:border-rose-800 rounded-2xl flex items-center gap-2.5 text-xs text-rose-700 dark:text-rose-300 font-bold">
            <AlertCircle class="w-4 h-4 shrink-0 text-rose-600 dark:text-rose-400" />
            <span>Physical blank ticket stock is depleted for this event. Ticket issuance is paused until additional physical tickets are restocked.</span>
          </div>
          <div v-else-if="selectedEventStock && selectedEventStock.availableQuantity <= 20" class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-2xl flex items-center gap-2 text-xs text-amber-800 dark:text-amber-300 font-semibold">
            <AlertCircle class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <span>Low physical ticket stock: Only {{ selectedEventStock.availableQuantity }} blank physical passes remaining across all cashier stations.</span>
          </div>

          <!-- Cash Payment Input & Preset Buttons (For Paid Admission) -->
          <div v-if="isEventPaid" class="pt-4 border-t border-slate-200 dark:border-[#2e2e2e] space-y-3">
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 items-start">
              <!-- Cash Received Input -->
              <div>
                <label class="block text-[11px] font-extrabold uppercase tracking-wider text-slate-700 dark:text-slate-300 mb-1.5 flex items-center justify-between">
                  <span>Cash Received (₱) *</span>
                  <span class="text-[10px] text-slate-400 font-medium">Philippine Peso</span>
                </label>
                <div class="relative">
                  <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-sm font-black text-slate-400">₱</span>
                  <input
                    type="text"
                    inputmode="decimal"
                    v-model="formCashReceived"
                    @blur="touchField('cashReceived')"
                    @input="touchField('cashReceived')"
                    @keydown.enter.prevent="handleQuickSubmit"
                    placeholder="e.g. 500.00"
                    class="w-full pl-8 pr-4 py-2.5 bg-white dark:bg-[#1a1a1a] border rounded-xl text-sm font-mono font-black text-slate-900 dark:text-white focus:outline-none transition-colors"
                    :class="[
                      touched.cashReceived && !cashValidation.isValid
                        ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20 text-rose-600'
                        : touched.cashReceived && cashValidation.isValid
                        ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                        : 'border-slate-200 dark:border-[#333333] focus:border-emerald-500'
                    ]"
                  />
                </div>

                <!-- Quick Presets -->
                <div class="flex items-center gap-1.5 mt-2 flex-wrap">
                  <button
                    type="button"
                    @click="setCashAmount(selectedEvent?.ticketPrice || 0)"
                    class="min-h-[38px] px-3 py-1.5 bg-slate-100 dark:bg-[#2a2a2a] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
                  >
                    Exact (₱{{ selectedEvent?.ticketPrice || 0 }})
                  </button>
                  <button
                    v-for="amt in quickCashOptions"
                    :key="amt"
                    type="button"
                    @click="setCashAmount(amt)"
                    class="min-h-[38px] px-3 py-1.5 bg-slate-100 dark:bg-[#2a2a2a] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
                  >
                    ₱{{ amt }}
                  </button>
                </div>

                <!-- Inline Cash Feedback -->
                <div class="mt-1.5 min-h-[16px]">
                  <p v-if="cashValidation.isValid && formCashReceived" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                    <CheckCircle2 class="w-3.5 h-3.5 shrink-0" />
                    <span>Sufficient payment recorded</span>
                  </p>
                  <p v-else-if="touched.cashReceived && cashValidation.error" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                    <AlertCircle class="w-3.5 h-3.5 shrink-0" />
                    <span>{{ cashValidation.error }}</span>
                  </p>
                </div>
              </div>

              <!-- Real-time Change Display Card -->
              <div class="p-4 rounded-2xl border transition-all"
                :class="cashValidation.isValid && formCashReceived
                  ? 'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-300 dark:border-emerald-800'
                  : 'bg-slate-100 dark:bg-[#1a1a1a] border-slate-200 dark:border-[#2e2e2e]'"
              >
                <div class="flex items-center justify-between">
                  <span class="text-[10px] font-black uppercase tracking-wider text-slate-500 dark:text-slate-400">
                    Change to Return
                  </span>
                  <span
                    v-if="cashValidation.isValid && formCashReceived"
                    class="px-2 py-0.5 rounded-full text-[9px] font-black uppercase bg-emerald-200 dark:bg-emerald-900 text-emerald-800 dark:text-emerald-200"
                  >
                    {{ cashValidation.change === 0 ? 'Exact Payment' : 'Ready' }}
                  </span>
                </div>

                <div class="mt-1 flex items-baseline gap-1">
                  <span class="text-sm font-bold text-slate-400">₱</span>
                  <span class="text-2xl sm:text-3xl font-black font-mono"
                    :class="cashValidation.isValid && formCashReceived
                      ? 'text-emerald-700 dark:text-emerald-400'
                      : 'text-slate-400 dark:text-slate-600'"
                  >
                    {{ cashValidation.isValid && formCashReceived ? cashValidation.change.toFixed(2) : '0.00' }}
                  </span>
                </div>

                <p class="text-[10px] text-slate-500 dark:text-slate-400 mt-1 font-medium">
                  {{ cashValidation.isValid && formCashReceived
                    ? `Received ₱${cashValidation.cashReceivedNum.toFixed(2)} - Due ₱${(selectedEvent?.ticketPrice || 0).toFixed(2)}`
                    : 'Enter cash received to automatically calculate change' }}
                </p>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- ==================== STEP 3: GENERATE TICKET (CTA) ==================== -->
      <div class="pt-2 border-t border-slate-100 dark:border-[#2a2a2a] space-y-2">
        <button
          type="button"
          @click="handleGenerateTicket"
          :disabled="!isFormValid || isSubmitting"
          class="w-full py-4 px-6 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-40 disabled:cursor-not-allowed text-white text-xs sm:text-sm font-black uppercase tracking-wider rounded-2xl shadow-md hover:shadow-lg transition-all flex items-center justify-center gap-2.5 cursor-pointer"
        >
          <QrCode class="w-5 h-5" />
          <span>Confirm Purchase & Generate Ticket</span>
        </button>

        <!-- Dynamic Validation Warning Notice if Form Incomplete -->
        <p v-if="!isFormValid" class="text-[11px] text-center text-amber-600 dark:text-amber-400 font-medium">
          {{ formValidationHint }}
        </p>
        <p v-else class="text-[10px] text-center text-slate-400 dark:text-slate-500">
          * Admission passes are final, non-refundable, and locked to the registered attendee.
        </p>
      </div>
    </div>

    <!-- ==================== 3. SUCCESS STATE CONFIRMATION MODAL ==================== -->
    <Modal
      :is-open="!!lastIssuedTicket"
      @close="handleSuccessModalClose"
      title="Ticket Issued Successfully"
      subtitle="Pass record created and ready for QR sticker printing."
      max-width="md"
    >
      <div v-if="lastIssuedTicket" class="space-y-3.5">
        <!-- Main Ticket Details Card -->
        <div class="p-4 rounded-2xl bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] space-y-3 text-xs">
          <!-- Top Row: Ticket Number & QR Status -->
          <div class="flex items-center justify-between gap-2 pb-2.5 border-b border-slate-200 dark:border-[#2e2e2e]">
            <div class="flex items-center gap-2">
              <span class="w-6 h-6 rounded-full bg-emerald-500/15 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-black text-xs shrink-0">
                ✓
              </span>
              <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-slate-500 dark:text-slate-400 block">Ticket No.</span>
                <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
                  {{ lastIssuedTicket.ticketNumber || lastIssuedTicket.id }}
                </span>
              </div>
            </div>
            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-50 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300 border border-amber-200 dark:border-amber-800 shrink-0">
              Pending Print
            </span>
          </div>

          <!-- Middle Details Grid -->
          <div class="grid grid-cols-2 gap-x-4 gap-y-2.5">
            <div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 block">Attendee</span>
              <span class="font-bold text-slate-900 dark:text-white text-xs block truncate">{{ lastIssuedTicket.currentAttendeeName }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 block">Admission Type</span>
              <span class="font-bold text-xs" :class="lastIssuedTicket.attendeeType === 'OUTSIDER' ? 'text-purple-600 dark:text-purple-400' : 'text-blue-600 dark:text-blue-400'">
                {{ lastIssuedTicket.attendeeType === 'OUTSIDER' ? 'Guest / Outsider' : 'NCST Student' }}
              </span>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 block">Event</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 text-xs block leading-tight">{{ selectedEvent?.title || 'Event Pass' }}</span>
            </div>
            <div class="col-span-2 sm:col-span-1">
              <span class="text-[10px] uppercase font-bold tracking-wider text-slate-400 dark:text-slate-500 block">Payment</span>
              <span class="font-bold text-emerald-600 dark:text-emerald-400 text-xs capitalize flex items-center gap-1">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 inline-block"></span>
                <span>{{ lastIssuedTicket.paymentStatus === 'free' ? 'Free Admission' : (lastIssuedTicket.price ? `₱${lastIssuedTicket.price}.00 Paid` : 'Paid') }}</span>
              </span>
            </div>
          </div>
        </div>

        <!-- Student Portal Account Notification Status Card (When Account Was Provisioned) -->
        <div v-if="accountCreationInfo?.isStudent && accountCreationInfo?.created" class="p-3.5 rounded-xl bg-blue-50/70 dark:bg-blue-950/30 border border-blue-200/80 dark:border-blue-900/60 space-y-2 text-xs text-left">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-bold text-blue-900 dark:text-blue-200 flex items-center gap-1.5">
              <KeyRound class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400 shrink-0" />
              <span>Student Portal Account Created</span>
            </span>
            <span class="font-mono text-[11px] font-bold text-blue-700 dark:text-blue-300">
              ID: {{ accountCreationInfo.studentId }}
            </span>
          </div>

          <p class="text-[10.5px] text-blue-800/80 dark:text-blue-300/80 leading-relaxed">
            Activation link and instructions dispatched to student's verified institutional email.
          </p>

          <div class="pt-1.5 border-t border-blue-200/60 dark:border-blue-800/40 flex flex-wrap items-center justify-between gap-2 text-[10.5px]">
            <span class="text-slate-500 dark:text-slate-400 flex items-center gap-1">
              <Mail class="w-3 h-3 text-slate-400" />
              <span>Notification:</span>
            </span>
            <span class="font-semibold flex items-center gap-1" :class="getStatusBadgeClass(accountCreationInfo.notifications?.email?.status)">
              <component :is="getStatusIcon(accountCreationInfo.notifications?.email?.status)" class="w-3 h-3" />
              <span>{{ accountCreationInfo.notifications?.email?.details || 'Queued for dispatch.' }}</span>
            </span>
          </div>
        </div>

        <!-- Outsider Account Confirmation Card (On-Screen Credentials Only) -->
        <div v-if="accountCreationInfo?.isOutsider && accountCreationInfo?.created" class="p-3.5 rounded-xl bg-amber-50/70 dark:bg-amber-950/30 border border-amber-200/80 dark:border-amber-900/60 space-y-2.5 text-xs text-left">
          <div class="flex items-center justify-between gap-2">
            <span class="text-[11px] font-bold text-amber-900 dark:text-amber-200 flex items-center gap-1.5">
              <Users class="w-3.5 h-3.5 text-amber-600 dark:text-amber-400 shrink-0" />
              <span>Guest Account Generated</span>
            </span>
            <span v-if="accountCreationInfo.outsiderAccount?.expirationDate" class="text-[10px] text-amber-700 dark:text-amber-300">
              Expires: {{ accountCreationInfo.outsiderAccount.expirationDate }}
            </span>
          </div>

          <div class="p-2.5 bg-white dark:bg-[#1a1a1a] rounded-lg border border-amber-200/80 dark:border-amber-900/40 grid grid-cols-2 gap-2 text-[11px]">
            <div>
              <span class="text-slate-400 text-[10px] block">Login ID (Guest Code):</span>
              <span class="font-mono font-bold text-slate-900 dark:text-white select-all">
                {{ accountCreationInfo.outsiderAccount?.accountId }}
              </span>
            </div>
            <div v-if="accountCreationInfo.outsiderAccount?.initialPassword">
              <span class="text-slate-400 text-[10px] block">Initial Password (Surname):</span>
              <span class="font-mono font-bold text-amber-600 dark:text-amber-400 select-all">
                {{ accountCreationInfo.outsiderAccount?.initialPassword }}
              </span>
            </div>
          </div>
        </div>

        <!-- Action Button Row: 2 Primary side-by-side buttons + subtle text link -->
        <div class="pt-2 space-y-2.5">
          <div class="flex items-center gap-2.5">
            <!-- Primary Action: Print QR Label -->
            <button
              type="button"
              @click="handlePrintSingleQrLabel(lastIssuedTicket)"
              class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold rounded-xl shadow-xs transition flex items-center justify-center gap-2 cursor-pointer"
            >
              <Printer class="w-4 h-4" />
              <span>Print QR Label</span>
            </button>

            <!-- Next Sale Button (Side-by-side) -->
            <button
              type="button"
              @click="handleSuccessModalClose"
              class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#282828] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer"
            >
              <span>+ Next Sale</span>
            </button>
          </div>

          <!-- Secondary subtle link to Print Queue -->
          <div class="text-center">
            <button
              type="button"
              @click="handleGoToPrintQueue"
              class="text-xs text-slate-500 hover:text-slate-800 dark:text-slate-400 dark:hover:text-white transition inline-flex items-center gap-1 cursor-pointer"
            >
              <span>Open Print Queue</span>
              <span>→</span>
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- Dynamic QR Label Print Modal -->
    <QRLabelPrintModal
      v-if="ticketForQrLabel"
      :is-open="isQrLabelModalOpen"
      :ticket="ticketForQrLabel"
      @close="isQrLabelModalOpen = false"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, watch, onMounted, onUnmounted, nextTick } from 'vue';
import {
  Ticket as TicketIcon,
  Search,
  QrCode,
  Printer,
  Users,
  GraduationCap,
  CheckCircle2,
  AlertCircle,
  RotateCw,
  ShieldCheck,
  KeyRound,
  Smartphone,
  Mail,
  Clock
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { Ticket, EventItem } from '../../types';
import QRLabelPrintModal from '../tickets/QRLabelPrintModal.vue';
import Modal from '../common/Modal.vue';
import {
  validateStudentId,
  validateYearLevel,
  validateSection,
  validateAttendeeName,
  validatePhoneNumber,
  validateEmail,
  validateCashPayment,
  validateGuestReference,
  generateUniqueGuestReference,
} from '../../utils/posValidation';

const emit = defineEmits<{
  (e: 'navigate', tabId: string): void;
}>();

const {
  currentUser,
  events,
  currentEvent,
  users,
  studentRecords,
  tickets,
  transactions,
  pendingQrTickets,
  ticketStock,
  purchaseTicket,
  registerStudent,
  lookupStudentRecord,
  availableSections,
  academicPeriods,
  activeAcademicPeriod
} = useAppStore();



const attendeeType = ref<'student' | 'outsider'>('student');
const searchInputRef = ref<HTMLInputElement | null>(null);
const searchQuery = ref('');
const isSearching = ref(false);

const formStudentId = ref('');
const formName = ref('');
const formFirstName = ref('');
const formLastName = ref('');
const formCourse = ref('BSIT');
const formYearLevel = ref('1st Year');
const formSection = ref('11M1');
const formCashReceived = ref('');

// Authoritative Eligible Events (exclude archived periods and cancelled/completed events)
const eligibleEvents = computed<EventItem[]>(() => {
  return events.value.filter(ev => {
    if (ev.status === 'cancelled' || ev.status === 'completed') return false;
    if (ev.academicYear && ev.semester) {
      const period = academicPeriods.value.find(p => p.academicYear === ev.academicYear && p.semester === ev.semester);
      if (period && period.status === 'archived') {
        return false;
      }
    }
    return true;
  });
});

const selectedEventId = ref(
  (eligibleEvents.value.some(e => e.id === currentEvent.value?.id) ? currentEvent.value?.id : eligibleEvents.value[0]?.id) || ''
);

const lastIssuedTicket = ref<Ticket | null>(null);
const accountCreationInfo = ref<{
  isStudent: boolean;
  isOutsider: boolean;
  created: boolean;
  studentId?: string;
  notifications?: {
    sms?: { status: string; details: string; recipient?: string };
    email?: { status: string; details: string; recipient?: string };
  };
  outsiderAccount?: {
    created: boolean;
    accountId?: string;
    initialPassword?: string;
    instructions?: string;
    expirationDate?: string;
  };
} | null>(null);
const isSubmitting = ref(false);

const getStatusBadgeClass = (status?: string) => {
  switch (status) {
    case 'submitted':
    case 'delivered':
      return 'text-emerald-700 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/60 px-2 py-0.5 rounded-full';
    case 'queued':
    case 'pending':
      return 'text-amber-700 dark:text-amber-400 bg-amber-100 dark:bg-amber-950/60 px-2 py-0.5 rounded-full';
    case 'unavailable':
      return 'text-slate-500 dark:text-slate-400 bg-slate-100 dark:bg-[#282828] px-2 py-0.5 rounded-full';
    case 'failed':
    default:
      return 'text-rose-700 dark:text-rose-400 bg-rose-100 dark:bg-rose-950/60 px-2 py-0.5 rounded-full';
  }
};

const getStatusIcon = (status?: string) => {
  switch (status) {
    case 'submitted':
    case 'delivered':
      return CheckCircle2;
    case 'queued':
    case 'pending':
      return Clock;
    case 'unavailable':
    case 'failed':
    default:
      return AlertCircle;
  }
};

// Touch tracking for instant & blur inline feedback
const touched = reactive({
  studentId: false,
  name: false,
  firstName: false,
  lastName: false,
  course: false,
  yearLevel: false,
  section: false,
  cashReceived: false,
});

const touchField = (field: keyof typeof touched) => {
  touched[field] = true;
};

const selectedEvent = computed<EventItem | null>(() => {
  return eligibleEvents.value.find(e => e.id === selectedEventId.value) || eligibleEvents.value[0] || null;
});

const selectedEventStock = computed(() => {
  if (!ticketStock.value || ticketStock.value.length === 0) return null;
  return ticketStock.value.find(s => s.eventId === selectedEvent.value?.id) ||
         ticketStock.value.find(s => s.id === 'stock-global-passes') ||
         null;
});

const isStockDepleted = computed(() => {
  if (!selectedEventStock.value) return false;
  return selectedEventStock.value.availableQuantity <= 0;
});

const isEventPaid = computed(() => (selectedEvent.value?.ticketPrice ?? 0) > 0);

// Quick cash suggestion buttons based on event price
const quickCashOptions = computed(() => {
  const price = selectedEvent.value?.ticketPrice ?? 0;
  const standardOptions = [100, 200, 500, 1000];
  return standardOptions.filter(amt => amt > price);
});

const setCashAmount = (amount: number) => {
  formCashReceived.value = amount.toString();
  touched.cashReceived = true;
};

// Authoritative Student Record Match & Server Lookup Integration
const serverStudentRecord = ref<any>(null);
const isLookingUpStudent = ref(false);
const studentLookupError = ref('');

const matchedStudentRecord = computed(() => {
  if (attendeeType.value !== 'student' || !formStudentId.value.trim()) return null;
  const clean = formStudentId.value.trim().toLowerCase();
  return studentRecords.value.find(r => r.studentId && r.studentId.trim().toLowerCase() === clean) ||
         users.value.find(u => u.role === 'student' && u.studentId && u.studentId.trim().toLowerCase() === clean) ||
         (serverStudentRecord.value && serverStudentRecord.value.studentId && serverStudentRecord.value.studentId.trim().toLowerCase() === clean ? serverStudentRecord.value : null) ||
         null;
});

const isAuthoritativeStudentMatch = computed(() => !!matchedStudentRecord.value);

const hasAuthoritativeSurname = computed(() => {
  if (!matchedStudentRecord.value) return false;
  const sName = matchedStudentRecord.value.lastName || (matchedStudentRecord.value as any).last_name;
  return !!(sName && sName.trim());
});

let studentLookupDebounce: any = null;
const performStudentLookup = async (id: string) => {
  const clean = id.trim();
  if (!clean || attendeeType.value !== 'student') {
    serverStudentRecord.value = null;
    studentLookupError.value = '';
    return;
  }
  
  if (!validateStudentId(clean).isValid) {
    serverStudentRecord.value = null;
    return;
  }

  isLookingUpStudent.value = true;
  studentLookupError.value = '';
  try {
    const res = await lookupStudentRecord(clean);
    if (res && res.found && res.student) {
      serverStudentRecord.value = res.student;
      formName.value = res.student.name;
      formLastName.value = (res.student.lastName || (res.student as any).last_name || '').trim();
      formCourse.value = res.student.course || 'BSIT';
      formYearLevel.value = res.student.yearLevel || '1st Year';
      formSection.value = res.student.section || '11M1';
      studentLookupError.value = '';
    } else {
      serverStudentRecord.value = null;
      studentLookupError.value = res?.message || 'Student ID not found in official institutional roster.';
    }
  } catch {
    serverStudentRecord.value = null;
  } finally {
    isLookingUpStudent.value = false;
  }
};

watch(formStudentId, (newId) => {
  if (studentLookupDebounce) clearTimeout(studentLookupDebounce);
  if (!newId || attendeeType.value !== 'student') {
    serverStudentRecord.value = null;
    studentLookupError.value = '';
    return;
  }
  studentLookupDebounce = setTimeout(() => {
    performStudentLookup(newId);
  }, 350);
});

watch(matchedStudentRecord, rec => {
  if (rec && attendeeType.value === 'student') {
    formName.value = rec.name;
    // Set official surname directly from authoritative StudentRecord without blind splitting:
    formLastName.value = (rec.lastName || (rec as any).last_name || '').trim();
    formCourse.value = rec.course || 'BSIT';
    formYearLevel.value = rec.yearLevel || '1st Year';
    formSection.value = rec.section || '11M1';
  }
});

// ==================== FIELD-LEVEL VALIDATION COMPUTEDS ====================
const studentIdValidation = computed(() => validateStudentId(formStudentId.value));
const nameValidation = computed(() => validateAttendeeName(formName.value));
const firstNameValidation = computed(() => {
  if (attendeeType.value !== 'outsider') {
    return { isValid: true, error: null };
  }
  const val = formFirstName.value.trim();
  if (!val) {
    return { isValid: false, error: 'First Name is required.' };
  }
  if (val.length < 2) {
    return { isValid: false, error: 'First Name must be at least 2 characters.' };
  }
  if (/\d/.test(val)) {
    return { isValid: false, error: 'First Name cannot contain numbers.' };
  }
  if (!/^[a-zA-ZñÑ\s\-'.]+$/.test(val)) {
    return { isValid: false, error: 'First Name contains invalid characters.' };
  }
  return { isValid: true, error: null };
});
const lastNameValidation = computed(() => {
  const val = formLastName.value.trim();
  if (!val) {
    return { isValid: false, error: 'Last Name / Surname is required.' };
  }
  if (val.length < 2) {
    return { isValid: false, error: 'Last Name / Surname must be at least 2 characters.' };
  }
  if (/\d/.test(val)) {
    return { isValid: false, error: 'Last Name / Surname cannot contain numbers.' };
  }
  if (!/^[a-zA-ZñÑ\s\-'.]+$/.test(val)) {
    return { isValid: false, error: 'Last Name / Surname contains invalid characters.' };
  }
  return { isValid: true, error: null };
});
const yearLevelValidation = computed(() => validateYearLevel(formYearLevel.value));
const sectionValidation = computed(() => validateSection(formSection.value));
const guestRefValidation = computed(() => validateGuestReference(formStudentId.value));

const cashValidation = computed(() => {
  const price = isEventPaid.value ? (selectedEvent.value?.ticketPrice ?? 0) : 0;
  return validateCashPayment(formCashReceived.value, price);
});

// Comprehensive Form Validity
const isFormValid = computed(() => {
  // Physical stock depletion guard
  if (isStockDepleted.value) {
    return false;
  }

  // Mode-specific checks
  if (attendeeType.value === 'student') {
    if (!nameValidation.value.isValid) return false;
    if (!studentIdValidation.value.isValid) return false;
    if (!lastNameValidation.value.isValid) return false;
    if (!yearLevelValidation.value.isValid) return false;
    if (!sectionValidation.value.isValid) return false;
    if (!formCourse.value.trim()) return false;
  } else {
    if (!guestRefValidation.value.isValid) return false;
    if (!firstNameValidation.value.isValid) return false;
    if (!lastNameValidation.value.isValid) return false;
  }

  // Payment check for paid events
  if (isEventPaid.value && !cashValidation.value.isValid) {
    return false;
  }

  return true;
});

// Validation helper explanation for disabled button
const formValidationHint = computed(() => {
  if (isStockDepleted.value) {
    return '⚠️ Physical ticket stock is depleted for this event. No blank tickets available.';
  }
  
  if (attendeeType.value === 'student') {
    if (!formName.value.trim()) return '⚠️ Student Full Name is required.';
    if (!nameValidation.value.isValid) return `⚠️ ${nameValidation.value.error}`;
    if (!formStudentId.value.trim()) return '⚠️ Student ID Number is required (e.g. 2026-00125).';
    if (!studentIdValidation.value.isValid) return `⚠️ ${studentIdValidation.value.error}`;
    if (!formLastName.value.trim()) return '⚠️ Last Name / Surname is required.';
    if (!lastNameValidation.value.isValid) return `⚠️ ${lastNameValidation.value.error}`;
    if (!sectionValidation.value.isValid) return `⚠️ ${sectionValidation.value.error}`;
    if (!formCourse.value.trim()) return '⚠️ Course / Program is required.';
  } else {
    if (!guestRefValidation.value.isValid) return `⚠️ ${guestRefValidation.value.error}`;
    if (!formFirstName.value.trim()) return '⚠️ Guest First Name is required.';
    if (!firstNameValidation.value.isValid) return `⚠️ ${firstNameValidation.value.error}`;
    if (!formLastName.value.trim()) return '⚠️ Guest Last Name / Surname is required.';
    if (!lastNameValidation.value.isValid) return `⚠️ ${lastNameValidation.value.error}`;
  }

  if (isEventPaid.value && !cashValidation.value.isValid) {
    return `⚠️ ${cashValidation.value.error || 'Please enter sufficient cash received.'}`;
  }

  return '';
});

// ==================== ACTIONS & EVENT HANDLERS ====================
const setAttendeeType = (type: 'student' | 'outsider') => {
  attendeeType.value = type;
  if (type === 'student') {
    formCourse.value = 'BSIT';
    formYearLevel.value = '1st Year';
    formSection.value = '11M1';
    formFirstName.value = '';
    formLastName.value = '';
    if (formStudentId.value.startsWith('GUEST-')) formStudentId.value = '';
  } else {
    formStudentId.value = generateUniqueGuestReference();
    formFirstName.value = '';
    formLastName.value = '';
    formCourse.value = 'Guest / Outsider';
    formYearLevel.value = 'General Public';
    formSection.value = 'Outsider';
  }
};

const regenerateGuestRef = () => {
  formStudentId.value = generateUniqueGuestReference();
  touched.studentId = true;
};

const handleResetForm = () => {
  lastIssuedTicket.value = null;
  searchQuery.value = '';
  formName.value = '';
  formFirstName.value = '';
  formLastName.value = '';
  formCashReceived.value = '';
  
  // Reset touched flags
  Object.keys(touched).forEach(k => {
    (touched as any)[k] = false;
  });

  if (attendeeType.value === 'outsider') {
    formStudentId.value = generateUniqueGuestReference();
  } else {
    formStudentId.value = '';
    formSection.value = '11M1';
  }
};

const suggestedStudents = computed(() => {
  if (!searchQuery.value.trim()) return [];
  const q = searchQuery.value.toLowerCase().trim();
  const seenIds = new Set<string>();
  const results: { id: string; name: string; studentId: string; lastName?: string; course: string; yearLevel: string; section: string }[] = [];

  // 1. Authoritative Student Records from Enrollment Roster
  studentRecords.value.forEach(r => {
    if (r.studentId && ((r.studentId.toLowerCase().includes(q)) || (r.name && r.name.toLowerCase().includes(q)))) {
      const cleanId = r.studentId.toLowerCase();
      if (!seenIds.has(cleanId)) {
        seenIds.add(cleanId);
        results.push({
          id: r.id,
          name: r.name,
          studentId: r.studentId,
          lastName: r.lastName || (r as any).last_name || '',
          course: r.course || 'BSIT',
          yearLevel: r.yearLevel || '1st Year',
          section: r.section || '11M1',
        });
      }
    }
  });

  // 2. Student accounts in users repository
  users.value.forEach(u => {
    if (u.role === 'student' && u.studentId && ((u.studentId.toLowerCase().includes(q)) || (u.name && u.name.toLowerCase().includes(q)))) {
      const cleanId = u.studentId.toLowerCase();
      if (!seenIds.has(cleanId)) {
        seenIds.add(cleanId);
        results.push({
          id: u.id,
          name: u.name,
          studentId: u.studentId,
          lastName: u.lastName || (u as any).last_name || '',
          course: u.course || 'BSIT',
          yearLevel: u.yearLevel || '1st Year',
          section: u.section || '11M1',
        });
      }
    }
  });

  return results.slice(0, 6);
});

const handleSearchInput = async () => {
  isSearching.value = true;
  const q = searchQuery.value.trim().toLowerCase();
  const exactRec = studentRecords.value.find(r => r.studentId && r.studentId.toLowerCase() === q);
  if (exactRec) {
    selectStudent({
      name: exactRec.name,
      studentId: exactRec.studentId,
      lastName: exactRec.lastName || (exactRec as any).last_name || '',
      course: exactRec.course || 'BSIT',
      yearLevel: exactRec.yearLevel || '1st Year',
      section: exactRec.section || '11M1',
    });
    return;
  }
  const exact = users.value.find(u => u.role === 'student' && u.studentId && u.studentId.toLowerCase() === q);
  if (exact) {
    selectStudent({
      name: exact.name,
      studentId: exact.studentId || '',
      lastName: exact.lastName || (exact as any).last_name || '',
      course: exact.course || 'BSIT',
      yearLevel: exact.yearLevel || '1st Year',
      section: exact.section || '11M1',
    });
    return;
  }

  // Also query server for exact student ID if not found in memory
  if (/^\d{4}-\d{4,5}$/.test(q)) {
    try {
      const res = await lookupStudentRecord(q);
      if (res && res.found && res.student) {
        serverStudentRecord.value = res.student;
        selectStudent({
          name: res.student.name,
          studentId: res.student.studentId,
          lastName: res.student.lastName || (res.student as any).last_name || '',
          course: res.student.course || 'BSIT',
          yearLevel: res.student.yearLevel || '1st Year',
          section: res.student.section || '11M1',
        });
      }
    } catch {}
  }
};

const selectStudent = (student: { name: string; studentId: string; lastName?: string; course: string; yearLevel: string; section: string }) => {
  formStudentId.value = student.studentId || '';
  formName.value = student.name || '';
  formLastName.value = (student.lastName || '').trim();
  formCourse.value = student.course || 'BSIT';
  formYearLevel.value = student.yearLevel || '1st Year';
  formSection.value = student.section || '11M1';
  searchQuery.value = `${student.name} (${student.studentId || ''})`;
  isSearching.value = false;
  
  // Mark touched
  touched.studentId = true;
  touched.name = true;
  touched.lastName = true;
  touched.section = true;
};

const handleGenerateTicket = async () => {
  if (isSubmitting.value || !isFormValid.value) return;

  isSubmitting.value = true;
  try {
    const isOutsider = attendeeType.value === 'outsider';
    const finalId = isOutsider ? undefined : formStudentId.value.trim();
    const finalCourse = isOutsider ? undefined : formCourse.value.trim();
    const finalYear = isOutsider ? undefined : formYearLevel.value.trim();
    const finalSection = isOutsider ? undefined : formSection.value.trim();
    const finalFirstName = isOutsider ? formFirstName.value.trim() : undefined;
    const finalLastName = formLastName.value.trim();
    const finalFullName = isOutsider ? `${finalFirstName} ${finalLastName}` : formName.value.trim();

    // 1. If enrolled student, register/sync in student database
    if (!isOutsider && finalId) {
      registerStudent({
        name: finalFullName,
        studentId: finalId,
        lastName: finalLastName,
        course: finalCourse || 'BSIT',
        yearLevel: finalYear || '1st Year',
        section: finalSection || '11M1',
      });
    }

    // 2. Authoritative Price from Event Data
    const price = isEventPaid.value ? (selectedEvent.value?.ticketPrice ?? 0) : 0;

    // 3. Generate unique Ticket Number & QR Code in database with Cash Validation & Auto-Account Creation
    const res = await purchaseTicket(
      selectedEventId.value,
      undefined,
      undefined,
      currentUser.value?.name || 'Cashier Desk',
      {
        studentId: isOutsider ? formStudentId.value.trim() : finalId,
        guestReference: isOutsider ? formStudentId.value.trim() : undefined,
        name: finalFullName,
        firstName: finalFirstName,
        lastName: finalLastName,
        course: finalCourse,
        yearLevel: finalYear,
        section: finalSection,
        attendeeType: isOutsider ? 'OUTSIDER' : 'STUDENT',
        cashReceived: isEventPaid.value ? formCashReceived.value : undefined,
      },
      price
    );

    if (res.success && res.ticket) {
      lastIssuedTicket.value = res.ticket;
      accountCreationInfo.value = {
        isStudent: !isOutsider,
        isOutsider: isOutsider,
        created: isOutsider ? !!res.outsiderAccount?.created : !!res.accountCreated,
        studentId: !isOutsider ? formStudentId.value.trim() : undefined,
        notifications: res.notifications,
        outsiderAccount: res.outsiderAccount,
      };
    } else if (!res.success && res.message) {
      alert(res.message);
    }
  } finally {
    isSubmitting.value = false;
  }
};

const isQrLabelModalOpen = ref(false);
const ticketForQrLabel = ref<Ticket | null>(null);

const handleQuickSubmit = () => {
  if (isFormValid.value) {
    handleGenerateTicket();
  }
};

const resetForm = () => {
  searchQuery.value = '';
  formStudentId.value = '';
  formName.value = '';
  formFirstName.value = '';
  formLastName.value = '';
  formCashReceived.value = '';
  Object.keys(touched).forEach(k => {
    touched[k as keyof typeof touched] = false;
  });
};

const handleSuccessModalClose = () => {
  lastIssuedTicket.value = null;
  accountCreationInfo.value = null;
  resetForm();
  nextTick(() => {
    searchInputRef.value?.focus();
  });
};

onMounted(() => {
  searchInputRef.value?.focus();
});

const handlePrintSingleQrLabel = (ticket: Ticket) => {
  ticketForQrLabel.value = ticket;
  isQrLabelModalOpen.value = true;
};

const handleGoToPrintQueue = () => {
  lastIssuedTicket.value = null;
  accountCreationInfo.value = null;
  resetForm();
  emit('navigate', 'qr_print_queue');
};
</script>
