<template>
  <div class="space-y-6 max-w-4xl mx-auto">
    <!-- ==================== 1. HEADER & OPERATOR CONTEXT ==================== -->
    <div class="relative overflow-hidden rounded-3xl bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] p-5 sm:p-7 shadow-xs transition-colors">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-start gap-3.5">
          <div class="w-12 h-12 rounded-2xl bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-800 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shrink-0">
            <TicketIcon class="w-6 h-6" />
          </div>
          <div class="space-y-1">
            <div class="flex items-center gap-2 flex-wrap">
              <span class="text-[10px] font-black uppercase tracking-wider px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800">
                Ticket POS Station
              </span>
              <span class="text-xs text-slate-500 dark:text-[#a0a0a0]">
                Operator: <strong class="text-slate-900 dark:text-white">{{ currentUser?.name || 'Cashier Desk' }}</strong>
              </span>
            </div>
            <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
              Event Ticket POS & Pass Generator
            </h1>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
              Register an attendee, record payment with exact change calculation, and generate a printable QR ticket.
            </p>
          </div>
        </div>

        <!-- Quick Actions & Stats -->
        <div class="flex items-center gap-2 shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-slate-100 dark:border-[#2a2a2a] flex-wrap">
          <button
            @click="$emit('navigate', 'qr_print_queue')"
            class="flex items-center gap-1.5 px-3.5 py-2 bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 text-blue-700 dark:text-blue-300 border border-blue-200 dark:border-blue-800 rounded-xl text-xs font-bold transition cursor-pointer"
            title="Open Dynamic QR Label Print Queue"
          >
            <Printer class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
            <span>QR Print Queue</span>
            <span v-if="pendingQrTickets.length > 0" class="px-1.5 py-0.2 rounded-full bg-amber-500 text-white text-[9px] font-black">
              {{ pendingQrTickets.length }}
            </span>
          </button>
          <button
            @click="$emit('navigate', 'tickets')"
            class="flex items-center gap-1.5 px-3.5 py-2 bg-slate-100 dark:bg-[#252525] hover:bg-slate-200 dark:hover:bg-[#2e2e2e] text-slate-700 dark:text-slate-200 rounded-xl text-xs font-bold transition cursor-pointer"
            title="Open Issued Tickets Registry"
          >
            <TicketIcon class="w-3.5 h-3.5 text-slate-500 dark:text-slate-400" />
            <span>Issued Registry →</span>
          </button>
          <div class="px-3 py-1.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-center">
            <span class="text-[9px] text-slate-400 uppercase font-extrabold block">Today's Sales</span>
            <span class="text-sm font-black text-emerald-600 dark:text-emerald-400">{{ cashierIssuedCount }} passes</span>
          </div>
        </div>
      </div>
    </div>

    <!-- ==================== 2. SINGLE-PURPOSE TRANSACTION FORM ==================== -->
    <div class="bg-white dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#2e2e2e] rounded-3xl p-6 sm:p-8 shadow-xs space-y-6 transition-colors">
      
      <!-- ==================== STEP 1: ATTENDEE INFORMATION ==================== -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-[#2a2a2a] pb-3">
          <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] flex items-center justify-center font-black">1</span>
            <span>Step 1 — Attendee Information</span>
          </h2>
          <span class="text-[11px] font-semibold text-slate-400">
            Select type & specify details
          </span>
        </div>

        <!-- Attendee Type Toggle -->
        <div class="grid grid-cols-2 gap-3">
          <button
            type="button"
            @click="setAttendeeType('student')"
            class="py-3 px-4 rounded-2xl border text-xs font-extrabold transition flex items-center justify-center gap-2 cursor-pointer"
            :class="attendeeType === 'student'
              ? 'bg-blue-50 dark:bg-blue-950/60 border-blue-500 text-blue-700 dark:text-blue-300 ring-2 ring-blue-500/20'
              : 'bg-slate-50 dark:bg-[#222222] border-slate-200 dark:border-[#333333] text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-[#444444]'"
          >
            <GraduationCap class="w-4 h-4" />
            <span>Enrolled Student</span>
          </button>
          <button
            type="button"
            @click="setAttendeeType('outsider')"
            class="py-3 px-4 rounded-2xl border text-xs font-extrabold transition flex items-center justify-center gap-2 cursor-pointer"
            :class="attendeeType === 'outsider'
              ? 'bg-purple-50 dark:bg-purple-950/60 border-purple-500 text-purple-700 dark:text-purple-300 ring-2 ring-purple-500/20'
              : 'bg-slate-50 dark:bg-[#222222] border-slate-200 dark:border-[#333333] text-slate-600 dark:text-slate-400 hover:border-slate-300 dark:hover:border-[#444444]'"
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
              v-model="searchQuery"
              @input="handleSearchInput"
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
                  {{ s.course || 'BSIT' }} • {{ s.yearLevel || '3rd Year' }} • {{ s.section || 'M1' }}
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
                <p v-if="studentIdValidation.isValid" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
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

          <!-- 2. FULL NAME -->
          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500">
                Full Name *
              </label>
              <span v-if="attendeeType === 'student' && isAuthoritativeStudentMatch" class="text-[9px] font-black uppercase text-emerald-600 dark:text-emerald-400 bg-emerald-50 dark:bg-emerald-950/60 px-1.5 py-0.5 rounded border border-emerald-200 dark:border-emerald-800">
                🔒 Authoritative Record
              </span>
            </div>
            <input
              type="text"
              v-model="formName"
              @blur="touchField('name')"
              @input="touchField('name')"
              :readonly="attendeeType === 'student' && isAuthoritativeStudentMatch"
              :placeholder="attendeeType === 'student' ? 'Juan Dela Cruz' : 'Guest Attendee Name'"
              class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none transition-colors"
              :class="[
                attendeeType === 'student' && isAuthoritativeStudentMatch ? 'bg-slate-50 dark:bg-[#202020] text-slate-700 dark:text-slate-300 cursor-not-allowed' : '',
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
                <span>{{ attendeeType === 'student' && isAuthoritativeStudentMatch ? 'Verified from StudentRecord roster' : 'Valid Attendee Name' }}</span>
              </p>
              <p v-else-if="touched.name" class="text-[11px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                <AlertCircle class="w-3 h-3 shrink-0" />
                <span>{{ nameValidation.error }}</span>
              </p>
            </div>
            <p v-else class="text-[10px] text-slate-400 mt-1">
              Letters, spaces, hyphens, apostrophes (no numbers)
            </p>
          </div>

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
                  placeholder="e.g. A1, A2, B1, B2..."
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
                  <option value="A1">Section A1</option>
                  <option value="A2">Section A2</option>
                  <option value="B1">Section B1</option>
                  <option value="B2">Section B2</option>
                  <option value="C1">Section C1</option>
                  <option value="C2">Section C2</option>
                  <option value="D1">Section D1</option>
                  <option value="D2">Section D2</option>
                  <option value="M1">Section M1 (Morning)</option>
                  <option value="M2">Section M2 (Morning)</option>
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

          <!-- OUTSIDER ONLY: Phone, Email -->
          <template v-else>
            <!-- Contact Number (Phone Validation) -->
            <div>
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Philippine Mobile Number
              </label>
              <input
                type="text"
                v-model="formGuestPhone"
                @blur="touchField('guestPhone')"
                @input="touchField('guestPhone')"
                placeholder="e.g. 09171234567"
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-mono font-semibold text-slate-900 dark:text-white focus:outline-none transition-colors"
                :class="[
                  touched.guestPhone && formGuestPhone && !phoneValidation.isValid
                    ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                    : touched.guestPhone && formGuestPhone && phoneValidation.isValid
                    ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                    : 'border-slate-200 dark:border-[#333333] focus:border-purple-500'
                ]"
              />
              <div v-if="formGuestPhone" class="mt-1">
                <p v-if="phoneValidation.isValid" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Valid Philippine Mobile (09XX)</span>
                </p>
                <p v-else class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ phoneValidation.error }}</span>
                </p>
              </div>
              <p v-else class="text-[10px] text-slate-400 mt-1">11 digits starting with 09 (e.g. 09171234567)</p>
            </div>

            <!-- Email (Email Validation) -->
            <div class="sm:col-span-2">
              <label class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-400 dark:text-slate-500 mb-1">
                Guest Email Address (Optional)
              </label>
              <input
                type="email"
                v-model="formGuestEmail"
                @blur="touchField('guestEmail')"
                @input="touchField('guestEmail')"
                placeholder="e.g. guest.attendee@gmail.com"
                class="w-full px-3 py-2 bg-white dark:bg-[#1a1a1a] border rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none transition-colors"
                :class="[
                  touched.guestEmail && formGuestEmail && !emailValidation.isValid
                    ? 'border-rose-500 dark:border-rose-500 bg-rose-50/20'
                    : touched.guestEmail && formGuestEmail && emailValidation.isValid
                    ? 'border-emerald-500 dark:border-emerald-500 bg-emerald-50/20'
                    : 'border-slate-200 dark:border-[#333333] focus:border-purple-500'
                ]"
              />
              <div v-if="formGuestEmail" class="mt-1">
                <p v-if="emailValidation.isValid" class="text-[10px] text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-1">
                  <CheckCircle2 class="w-3 h-3 shrink-0" />
                  <span>Valid Email Format</span>
                </p>
                <p v-else class="text-[10px] text-rose-600 dark:text-rose-400 font-semibold flex items-center gap-1">
                  <AlertCircle class="w-3 h-3 shrink-0" />
                  <span>{{ emailValidation.error }}</span>
                </p>
              </div>
            </div>
          </template>

        </div>
      </div>

      <!-- ==================== STEP 2: EVENT & CASH PAYMENT DETAILS ==================== -->
      <div class="space-y-4 pt-4 border-t border-slate-100 dark:border-[#2a2a2a]">
        <div class="flex items-center justify-between border-b border-slate-100 dark:border-[#2a2a2a] pb-3">
          <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-tight flex items-center gap-2">
            <span class="w-5 h-5 rounded-full bg-blue-600 text-white text-[10px] flex items-center justify-center font-black">2</span>
            <span>Step 2 — Event & Cash Payment Details</span>
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
              :disabled="!events || events.length === 0"
              class="w-full px-3 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 disabled:opacity-60 disabled:cursor-not-allowed cursor-pointer"
            >
              <option v-if="!events || events.length === 0" value="" disabled selected>
                No Events Available
              </option>
              <option v-for="ev in events" :key="ev.id" :value="ev.id">
                {{ ev.title }} ({{ ev.code }})
              </option>
            </select>
          </div>

          <!-- Admission Type (Authoritatively derived from Event Data) -->
          <div>
            <label class="block text-xs font-extrabold text-slate-700 dark:text-slate-300 mb-1">
              Admission Fee / Rule *
            </label>
            <div class="px-3 py-2.5 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-xl text-xs font-bold flex items-center justify-between">
              <span :class="isEventPaid ? 'text-emerald-700 dark:text-emerald-400 font-black' : 'text-purple-700 dark:text-purple-400 font-black'">
                {{ isEventPaid ? `PAID ADMISSION — ₱${(selectedEvent?.ticketPrice || 0)}.00` : 'FREE ADMISSION (Official Pass)' }}
              </span>
              <span
                class="text-[10px] uppercase px-2 py-0.5 rounded font-black"
                :class="isEventPaid ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-800 dark:text-emerald-300' : 'bg-purple-100 dark:bg-purple-950 text-purple-800 dark:text-purple-300'"
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
                {{ selectedEvent.date }} • {{ selectedEvent.venue }}
              </p>
            </div>
            <div class="text-right">
              <span class="text-[10px] uppercase font-extrabold text-slate-400 block">Amount Due</span>
              <span class="text-2xl font-black text-slate-900 dark:text-white">
                {{ isEventPaid ? `₱${(selectedEvent.ticketPrice || 0)}.00` : 'FREE' }}
              </span>
            </div>
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
                    class="px-2.5 py-1 bg-slate-100 dark:bg-[#2a2a2a] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 rounded-lg text-[10px] font-bold transition cursor-pointer"
                  >
                    Exact (₱{{ selectedEvent?.ticketPrice || 0 }})
                  </button>
                  <button
                    v-for="amt in quickCashOptions"
                    :key="amt"
                    type="button"
                    @click="setCashAmount(amt)"
                    class="px-2.5 py-1 bg-slate-100 dark:bg-[#2a2a2a] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-700 dark:text-slate-200 rounded-lg text-[10px] font-bold transition cursor-pointer"
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
      @close="lastIssuedTicket = null"
      title="✅ Ticket Issued Successfully"
      subtitle="NCST Official Workflow: Pass record created. Print dynamic QR label to attach to pre-printed ticket stock."
      max-width="md"
    >
      <div v-if="lastIssuedTicket" class="space-y-4">
        <!-- Success Summary Card -->
        <div class="p-4 rounded-2xl bg-emerald-50 dark:bg-emerald-950/50 border border-emerald-200 dark:border-emerald-800 space-y-3 text-xs">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-2">
              <span class="w-7 h-7 rounded-full bg-emerald-600 text-white flex items-center justify-center font-black text-xs">✓</span>
              <div>
                <span class="text-[10px] font-extrabold uppercase text-emerald-800 dark:text-emerald-300 block">Ticket Number</span>
                <span class="font-mono font-black text-slate-900 dark:text-white text-sm">
                  {{ lastIssuedTicket.ticketNumber || lastIssuedTicket.id }}
                </span>
              </div>
            </div>
            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase bg-amber-100 dark:bg-amber-900/60 text-amber-900 dark:text-amber-200 border border-amber-300 dark:border-amber-700">
              QR: PENDING PRINT
            </span>
          </div>

          <div class="grid grid-cols-2 gap-2.5 pt-2 border-t border-emerald-200 dark:border-emerald-800/60">
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Attendee</span>
              <span class="font-bold text-slate-900 dark:text-white">{{ lastIssuedTicket.currentAttendeeName }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Type</span>
              <span class="font-bold text-blue-600 dark:text-blue-400">{{ lastIssuedTicket.attendeeType || 'STUDENT' }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Event</span>
              <span class="font-bold text-slate-800 dark:text-slate-200 truncate block">{{ selectedEvent?.title || 'Event Pass' }}</span>
            </div>
            <div>
              <span class="text-[10px] uppercase font-bold text-slate-400 block">Payment</span>
              <span class="font-bold text-emerald-600 dark:text-emerald-400 capitalize">{{ lastIssuedTicket.paymentStatus }}</span>
            </div>
          </div>

          <div class="pt-2 border-t border-emerald-200/60 dark:border-emerald-800/40 text-[10px] text-emerald-800 dark:text-emerald-300 font-bold text-center">
            NCST Event Pass Successfully Issued
          </div>
        </div>

          <!-- Primary Actions: Print QR Label, Open Print Queue, Next Sale -->
        <div class="space-y-2">
          <!-- Primary Action: Print QR Label -->
          <button
            type="button"
            @click="handlePrintSingleQrLabel(lastIssuedTicket)"
            class="w-full py-3 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-black uppercase tracking-wider rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
          >
            <Printer class="w-4 h-4" />
            <span>Print QR Label</span>
          </button>

          <!-- Secondary Action: Open Print Queue -->
          <button
            type="button"
            @click="handleGoToPrintQueue"
            class="w-full py-2.5 px-3 bg-slate-100 dark:bg-[#282828] hover:bg-slate-200 dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition flex items-center justify-center gap-1.5 cursor-pointer"
          >
            <Printer class="w-4 h-4 text-slate-500" />
            <span>Open Print Queue →</span>
          </button>

          <!-- Next Sale Button -->
          <button
            type="button"
            @click="handleResetForm"
            class="w-full py-2.5 px-4 bg-emerald-50 dark:bg-emerald-950/40 hover:bg-emerald-100 dark:hover:bg-emerald-900/60 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-800 text-xs font-black uppercase rounded-xl transition cursor-pointer"
          >
            + Next Sale
          </button>
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
import { ref, reactive, computed, watch } from 'vue';
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
  ShieldCheck
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

const { currentUser, events, currentEvent, users, studentRecords, transactions, pendingQrTickets, purchaseTicket, registerStudent } = useAppStore();

const attendeeType = ref<'student' | 'outsider'>('student');
const searchQuery = ref('');
const isSearching = ref(false);

const formStudentId = ref('');
const formName = ref('');
const formCourse = ref('BSIT');
const formYearLevel = ref('3rd Year');
const formSection = ref('M1');
const formGuestPhone = ref('');
const formGuestEmail = ref('');
const formCashReceived = ref('');

const selectedEventId = ref(currentEvent.value?.id || events.value[0]?.id || '');
const lastIssuedTicket = ref<Ticket | null>(null);
const isSubmitting = ref(false);

// Touch tracking for instant & blur inline feedback
const touched = reactive({
  studentId: false,
  name: false,
  course: false,
  yearLevel: false,
  section: false,
  guestPhone: false,
  guestEmail: false,
  cashReceived: false,
});

const touchField = (field: keyof typeof touched) => {
  touched[field] = true;
};

const selectedEvent = computed<EventItem | null>(() => {
  return events.value.find(e => e.id === selectedEventId.value) || events.value[0] || null;
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

// Authoritative Student Record Match
const matchedStudentRecord = computed(() => {
  if (attendeeType.value !== 'student' || !formStudentId.value.trim()) return null;
  const clean = formStudentId.value.trim().toLowerCase();
  return studentRecords.value.find(r => r.studentId && r.studentId.trim().toLowerCase() === clean) ||
         users.value.find(u => u.role === 'student' && u.studentId && u.studentId.trim().toLowerCase() === clean) ||
         null;
});

const isAuthoritativeStudentMatch = computed(() => !!matchedStudentRecord.value);

watch(matchedStudentRecord, rec => {
  if (rec && attendeeType.value === 'student') {
    formName.value = rec.name;
    formCourse.value = rec.course || 'BSIT';
    formYearLevel.value = rec.yearLevel || '3rd Year';
    formSection.value = rec.section || 'M1';
  }
});

// ==================== FIELD-LEVEL VALIDATION COMPUTEDS ====================
const studentIdValidation = computed(() => validateStudentId(formStudentId.value));
const nameValidation = computed(() => validateAttendeeName(formName.value));
const yearLevelValidation = computed(() => validateYearLevel(formYearLevel.value));
const sectionValidation = computed(() => validateSection(formSection.value));
const guestRefValidation = computed(() => validateGuestReference(formStudentId.value));
const phoneValidation = computed(() => validatePhoneNumber(formGuestPhone.value, false));
const emailValidation = computed(() => validateEmail(formGuestEmail.value, false));

const cashValidation = computed(() => {
  const price = isEventPaid.value ? (selectedEvent.value?.ticketPrice ?? 0) : 0;
  return validateCashPayment(formCashReceived.value, price);
});

// Comprehensive Form Validity
const isFormValid = computed(() => {
  // Common Name check
  if (!nameValidation.value.isValid) return false;

  // Mode-specific checks
  if (attendeeType.value === 'student') {
    if (!studentIdValidation.value.isValid) return false;
    if (!yearLevelValidation.value.isValid) return false;
    if (!sectionValidation.value.isValid) return false;
    if (!formCourse.value.trim()) return false;
  } else {
    if (!guestRefValidation.value.isValid) return false;
    if (formGuestPhone.value && !phoneValidation.value.isValid) return false;
    if (formGuestEmail.value && !emailValidation.value.isValid) return false;
  }

  // Payment check for paid events
  if (isEventPaid.value && !cashValidation.value.isValid) {
    return false;
  }

  return true;
});

// Validation helper explanation for disabled button
const formValidationHint = computed(() => {
  if (!formName.value.trim()) return '⚠️ Attendee Full Name is required.';
  if (!nameValidation.value.isValid) return `⚠️ ${nameValidation.value.error}`;
  
  if (attendeeType.value === 'student') {
    if (!formStudentId.value.trim()) return '⚠️ Student ID Number is required (e.g. 2026-00125).';
    if (!studentIdValidation.value.isValid) return `⚠️ ${studentIdValidation.value.error}`;
    if (!sectionValidation.value.isValid) return `⚠️ ${sectionValidation.value.error}`;
    if (!formCourse.value.trim()) return '⚠️ Course / Program is required.';
  } else {
    if (!guestRefValidation.value.isValid) return `⚠️ ${guestRefValidation.value.error}`;
    if (formGuestPhone.value && !phoneValidation.value.isValid) return `⚠️ ${phoneValidation.value.error}`;
    if (formGuestEmail.value && !emailValidation.value.isValid) return `⚠️ ${emailValidation.value.error}`;
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
    formYearLevel.value = '3rd Year';
    formSection.value = 'M1';
    if (formStudentId.value.startsWith('GUEST-')) formStudentId.value = '';
  } else {
    formStudentId.value = generateUniqueGuestReference();
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
  formGuestPhone.value = '';
  formGuestEmail.value = '';
  formCashReceived.value = '';
  
  // Reset touched flags
  Object.keys(touched).forEach(k => {
    (touched as any)[k] = false;
  });

  if (attendeeType.value === 'outsider') {
    formStudentId.value = generateUniqueGuestReference();
  } else {
    formStudentId.value = '';
    formSection.value = 'M1';
  }
};

const cashierIssuedCount = computed(() => {
  return transactions.value.filter(t => t.cashierName && t.cashierName === currentUser.value?.name).length ||
         transactions.value.length;
});

const suggestedStudents = computed(() => {
  if (!searchQuery.value.trim()) return [];
  const q = searchQuery.value.toLowerCase().trim();
  const seenIds = new Set<string>();
  const results: { id: string; name: string; studentId: string; course: string; yearLevel: string; section: string }[] = [];

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
          course: r.course || 'BSIT',
          yearLevel: r.yearLevel || '3rd Year',
          section: r.section || 'M1',
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
          course: u.course || 'BSIT',
          yearLevel: u.yearLevel || '3rd Year',
          section: u.section || 'M1',
        });
      }
    }
  });

  return results.slice(0, 6);
});

const handleSearchInput = () => {
  isSearching.value = true;
  const q = searchQuery.value.trim().toLowerCase();
  const exactRec = studentRecords.value.find(r => r.studentId && r.studentId.toLowerCase() === q);
  if (exactRec) {
    selectStudent({
      name: exactRec.name,
      studentId: exactRec.studentId,
      course: exactRec.course || 'BSIT',
      yearLevel: exactRec.yearLevel || '3rd Year',
      section: exactRec.section || 'M1',
    });
    return;
  }
  const exact = users.value.find(u => u.role === 'student' && u.studentId && u.studentId.toLowerCase() === q);
  if (exact) {
    selectStudent({
      name: exact.name,
      studentId: exact.studentId || '',
      course: exact.course || 'BSIT',
      yearLevel: exact.yearLevel || '3rd Year',
      section: exact.section || 'M1',
    });
  }
};

const selectStudent = (student: { name: string; studentId: string; course: string; yearLevel: string; section: string }) => {
  formStudentId.value = student.studentId || '';
  formName.value = student.name || '';
  formCourse.value = student.course || 'BSIT';
  formYearLevel.value = student.yearLevel || '3rd Year';
  formSection.value = student.section || 'M1';
  searchQuery.value = `${student.name} (${student.studentId || ''})`;
  isSearching.value = false;
  
  // Mark touched
  touched.studentId = true;
  touched.name = true;
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
    const contactInfo = isOutsider ? (formGuestPhone.value.trim() || undefined) : undefined;
    const emailInfo = isOutsider ? (formGuestEmail.value.trim() || undefined) : undefined;

    // 1. If enrolled student, register/sync in student database
    if (!isOutsider && finalId) {
      registerStudent({
        name: formName.value.trim(),
        studentId: finalId,
        course: finalCourse || 'BSIT',
        yearLevel: finalYear || '3rd Year',
        section: finalSection || 'M1',
      });
    }

    // 2. Authoritative Price from Event Data
    const price = isEventPaid.value ? (selectedEvent.value?.ticketPrice ?? 0) : 0;

    // 3. Generate unique Ticket Number & QR Code in database with Cash Validation & Auto-Account Creation
    const res = purchaseTicket(
      selectedEventId.value,
      undefined,
      undefined,
      currentUser.value?.name || 'Cashier Desk',
      {
        studentId: finalId,
        name: formName.value.trim(),
        course: finalCourse,
        yearLevel: finalYear,
        section: finalSection,
        attendeeType: isOutsider ? 'OUTSIDER' : 'STUDENT',
        contactNumber: contactInfo,
        email: emailInfo,
        cashReceived: isEventPaid.value ? formCashReceived.value : undefined,
      },
      price
    );

    if (res.success && res.ticket) {
      lastIssuedTicket.value = res.ticket;
    } else if (!res.success && res.message) {
      alert(res.message);
    }
  } finally {
    isSubmitting.value = false;
  }
};

const isQrLabelModalOpen = ref(false);
const ticketForQrLabel = ref<Ticket | null>(null);

const handlePrintSingleQrLabel = (ticket: Ticket) => {
  ticketForQrLabel.value = ticket;
  isQrLabelModalOpen.value = true;
};

const handleGoToPrintQueue = () => {
  lastIssuedTicket.value = null;
  emit('navigate', 'qr_print_queue');
};
</script>
