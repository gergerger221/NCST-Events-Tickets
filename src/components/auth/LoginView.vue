<template>
  <div class="min-h-screen w-full bg-slate-100 dark:bg-[#121212] flex items-center justify-center p-4 sm:p-6 relative overflow-hidden transition-colors duration-200">
    <!-- Top-Right Theme Toggle Floating Button -->
    <div class="absolute top-4 right-4 z-20">
      <ThemeToggle variant="pill" :show-label="true" />
    </div>

    <!-- Ambient Subtle Background Glows -->
    <div class="absolute top-1/4 -left-32 w-96 h-96 bg-blue-500/10 dark:bg-blue-600/10 rounded-full blur-3xl pointer-events-none" />
    <div class="absolute bottom-1/4 -right-32 w-96 h-96 bg-indigo-500/10 dark:bg-indigo-600/10 rounded-full blur-3xl pointer-events-none" />

    <div class="w-full max-w-md relative z-10">
      <!-- App Header & Logo -->
      <div class="text-center mb-6 flex flex-col items-center">
        <img
          src="/ncst-logo.png"
          alt="NCST Seal"
          class="w-20 h-20 sm:w-24 sm:h-24 object-contain rounded-full shadow-lg bg-white p-1 mb-3 ring-4 ring-blue-500/20"
        />
        <h1 class="text-2xl sm:text-3xl font-black text-slate-900 dark:text-white tracking-tight">
          NCST <span class="text-[#1E3A5F] dark:text-blue-400">Events & Tickets</span>
        </h1>
        <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-1 font-medium">
          National College of Science & Technology
        </p>
      </div>

      <!-- Auth Card -->
      <div class="bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 sm:p-8 shadow-xl shadow-slate-200/50 dark:shadow-none transition-colors">
        <!-- Tab Selector: Sign In vs Student Registration -->
        <div class="flex items-center p-1 bg-slate-100 dark:bg-[#1e1e1e] rounded-2xl border border-slate-200 dark:border-[#333333] mb-6 transition-colors">
          <button
            @click="activeTab = 'login'; errorMessage = ''; successMessage = ''"
            class="flex-1 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer text-center"
            :class="activeTab === 'login' ? 'bg-[#1E3A5F] text-white shadow-xs' : 'text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white'"
          >
            Sign In
          </button>
          <button
            @click="activeTab = 'register'; errorMessage = ''; successMessage = ''"
            class="flex-1 py-2 text-xs font-bold rounded-xl transition-all cursor-pointer text-center"
            :class="activeTab === 'register' ? 'bg-[#1E3A5F] text-white shadow-xs' : 'text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white'"
          >
            Student Registration
          </button>
        </div>

        <!-- Feedback Alert Messages -->
        <div
          v-if="errorMessage"
          class="mb-5 p-3.5 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-500/40 rounded-2xl text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2.5"
        >
          <AlertCircle class="w-4 h-4 text-rose-600 dark:text-rose-400 flex-shrink-0" />
          <span>{{ errorMessage }}</span>
        </div>

        <div
          v-if="successMessage"
          class="mb-5 p-3.5 bg-emerald-50 dark:bg-emerald-950/70 border border-emerald-200 dark:border-emerald-500/40 rounded-2xl text-emerald-700 dark:text-emerald-300 text-xs flex items-center gap-2.5"
        >
          <CheckCircle2 class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          <span>{{ successMessage }}</span>
        </div>

        <!-- 1. Sign In Form -->
        <form v-if="activeTab === 'login'" @submit.prevent="handleLogin" class="space-y-4">
          <div class="mb-2">
            <h2 class="text-base font-bold text-slate-900 dark:text-white">Welcome Back</h2>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1.5">
              ID Number
            </label>
            <div class="relative">
              <UserIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="loginIdentifier"
                type="text"
                required
                placeholder="Enter your ID"
                class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
              />
            </div>
          </div>

          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="text-xs font-bold text-slate-700 dark:text-slate-300">
                Password
              </label>
            </div>
            <div class="relative">
              <Lock class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="loginPassword"
                :type="showPassword ? 'text' : 'password'"
                required
                placeholder="Enter your password"
                class="w-full pl-10 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition"
              />
              <button
                type="button"
                @click="showPassword = !showPassword"
                class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
              >
                <EyeOff v-if="showPassword" class="w-4 h-4" />
                <Eye v-else class="w-4 h-4" />
              </button>
            </div>
          </div>

          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer">
              <input
                type="checkbox"
                v-model="rememberMe"
                class="rounded border-slate-300 dark:border-[#3a3a3a] bg-slate-50 dark:bg-[#1e1e1e] text-blue-600 focus:ring-blue-500/20"
              />
              <span class="text-xs text-slate-600 dark:text-[#b3b3b3]">Remember session</span>
            </label>

            <button
              type="button"
              @click="openForgotPassword"
              class="text-xs text-blue-600 dark:text-blue-400 hover:underline cursor-pointer font-medium"
            >
              Forgot password?
            </button>
          </div>

          <button
            type="submit"
            :disabled="isLoading"
            class="w-full py-3 px-4 bg-[#1E3A5F] hover:bg-[#152A45] text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60"
          >
            <span v-if="isLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <LogIn v-else class="w-4 h-4" />
            <span>{{ isLoading ? 'Signing In...' : 'Sign In' }}</span>
          </button>

          <!-- Separate Outsider / Guest Ticket Retrieval Link -->
          <div class="pt-3 border-t border-slate-200 dark:border-[#333333] text-center">
            <p class="text-[11px] text-slate-500 dark:text-[#888888] mb-2">Guest attendee or looking for your event pass?</p>
            <button
              type="button"
              @click="openGuestLookupModal"
              class="w-full py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#1e1e1e] dark:hover:bg-[#2c2c2c] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl border border-slate-200 dark:border-[#383838] transition flex items-center justify-center gap-2 cursor-pointer shadow-sm hover:border-blue-500/40"
            >
              <Ticket class="w-4 h-4 text-blue-600 dark:text-blue-400" />
              <span>Find My Ticket</span>
            </button>
          </div>

          <!-- First-Time Student Notice Card -->
          <div class="mt-4 p-3 bg-blue-50/70 dark:bg-blue-950/40 border border-blue-200/80 dark:border-blue-800/50 rounded-2xl text-left space-y-1">
            <div class="flex items-center gap-1.5 text-xs font-bold text-blue-900 dark:text-blue-200">
              <KeyRound class="w-4 h-4 text-blue-600 dark:text-blue-400 shrink-0" />
              <span>First-Time Student Login</span>
            </div>
            <p class="text-[10.5px] text-blue-700/90 dark:text-blue-300/90 leading-relaxed">
              If your student account was created when you purchased a ticket at the Cashier, sign in using your Student ID and your official surname as your temporary password. You must create a new password before accessing the student portal.
            </p>
          </div>
        </form>

        <!-- 2. Register Form: Authoritative Student Record Lookup with Manual Fallback (Pending Verification) -->
        <form v-else @submit.prevent="handleRegister" class="space-y-4">
          <!-- Outsider / Guest Guidance Card -->
          <div class="p-3 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333] rounded-2xl flex items-start gap-2.5 text-left">
            <UserCheck class="w-4 h-4 text-slate-500 dark:text-slate-400 shrink-0 mt-0.5" />
            <div>
              <p class="text-xs font-bold text-slate-800 dark:text-slate-200">Are you an Outsider / Guest?</p>
              <p class="text-[10px] text-slate-600 dark:text-slate-400 mt-0.5 leading-relaxed">
                Outsiders and guests do not need a student portal account. Guest passes are purchased directly at the NCST Cashier booth.
              </p>
            </div>
          </div>

          <!-- Student ID Number Input with Search Action -->
          <div>
            <div class="flex items-center justify-between mb-1.5">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                Student ID Number
              </label>
              <span class="text-[10px] text-slate-400 font-mono">Format: YYYY-XXXXX</span>
            </div>
            <div class="flex gap-2">
              <div class="relative flex-1">
                <UserIcon class="w-4 h-4 text-slate-400 dark:text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  v-model="regStudentId"
                  @input="onStudentIdInput"
                  @keydown.enter.prevent="triggerManualLookup"
                  :readonly="lookupState === 'verified' || isManualMode"
                  type="text"
                  required
                  placeholder="e.g. 2024-58566"
                  class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl text-xs font-mono font-bold text-slate-900 dark:text-white placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none transition"
                  :class="{
                    'border-emerald-500 focus:ring-1 focus:ring-emerald-500/20': lookupState === 'verified' && !isManualMode,
                    'border-rose-400 focus:ring-1 focus:ring-rose-400/20': lookupState === 'not_found' && !isManualMode,
                    'border-amber-400 focus:ring-1 focus:ring-amber-400/20': lookupState === 'existing' || isManualMode,
                    'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500 focus:ring-1 focus:ring-blue-500': lookupState === 'idle' || lookupState === 'checking',
                  }"
                />
              </div>
              <button
                v-if="lookupState !== 'verified' && !isManualMode"
                type="button"
                @click="triggerManualLookup"
                :disabled="lookupState === 'checking' || !regStudentId.trim()"
                class="px-4 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition flex items-center gap-1.5 cursor-pointer disabled:opacity-50 shrink-0 shadow-sm"
              >
                <span v-if="lookupState === 'checking'" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
                <Search v-else class="w-3.5 h-3.5" />
                <span>{{ lookupState === 'checking' ? 'Searching...' : 'Search' }}</span>
              </button>
              <button
                v-else
                type="button"
                @click="resetStudentLookup"
                class="px-3 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-[#2a2a2a] dark:hover:bg-[#333] text-slate-600 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer shrink-0"
                title="Change Student ID"
              >
                Change ID
              </button>
            </div>
            <p v-if="lookupState === 'idle' && !isManualMode" class="text-[10px] text-slate-400 dark:text-slate-500 mt-1.5">
              We'll use your Student ID to automatically find your official NCST student record.
            </p>
          </div>

          <!-- Lookup State: Checking Spinner -->
          <div v-if="lookupState === 'checking'" class="p-3 bg-slate-100 dark:bg-[#252525] rounded-2xl flex items-center gap-2.5 text-xs text-slate-600 dark:text-slate-300">
            <span class="w-3.5 h-3.5 border-2 border-blue-500 border-t-transparent rounded-full animate-spin shrink-0" />
            <span>Searching official NCST institutional roster...</span>
          </div>

          <!-- Lookup State: Existing Account Alert -->
          <div v-if="lookupState === 'existing'" class="p-3.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 rounded-2xl text-xs text-amber-800 dark:text-amber-300 flex items-start justify-between gap-3">
            <div class="flex items-start gap-2">
              <AlertCircle class="w-4 h-4 text-amber-500 shrink-0 mt-0.5" />
              <div>
                <p class="font-bold">Account Already Exists</p>
                <p class="text-[11px] text-amber-700 dark:text-amber-400 mt-0.5 leading-relaxed">
                  An account already exists for this Student ID. Please sign in instead.
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="activeTab = 'login'; loginIdentifier = regStudentId"
              class="px-2.5 py-1 bg-amber-600 hover:bg-amber-700 text-white rounded-lg text-xs font-bold shrink-0 transition cursor-pointer shadow-sm"
            >
              Sign In
            </button>
          </div>

          <!-- Lookup State: Not Found Alert (with Manual Registration Option) -->
          <div v-if="lookupState === 'not_found' && !isManualMode" class="p-3.5 bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 rounded-2xl text-xs text-rose-700 dark:text-rose-300 space-y-2.5">
            <div class="flex items-start gap-2.5">
              <AlertCircle class="w-4 h-4 text-rose-500 shrink-0 mt-0.5" />
              <div class="space-y-0.5">
                <p class="font-bold">Student Record Not Found</p>
                <p class="text-[11px] text-rose-600 dark:text-rose-400 leading-relaxed">
                  We couldn't find this Student ID in the current NCST student registry. If you are an NCST student, you may continue by providing your information manually.
                </p>
              </div>
            </div>
            <button
              type="button"
              @click="enableManualRegistration"
              class="w-full py-2 px-3 bg-rose-600 hover:bg-rose-700 text-white rounded-xl text-xs font-bold transition flex items-center justify-center gap-1.5 cursor-pointer shadow-sm"
            >
              <UserPlus class="w-3.5 h-3.5" />
              <span>Continue with Manual Student Registration</span>
            </button>
          </div>

          <!-- A. Verified Authoritative Student Record (Read-Only Form) -->
          <div v-if="lookupState === 'verified' && verifiedStudent && !isManualMode" class="space-y-3.5 pt-1 animate-fade-in">
            <div class="flex items-center justify-between pb-1 border-b border-emerald-200 dark:border-emerald-800/40">
              <span class="flex items-center gap-1.5 text-xs font-bold text-emerald-700 dark:text-emerald-400">
                <CheckCircle2 class="w-4 h-4" />
                <span>Official NCST Student Record Found</span>
              </span>
              <span class="text-[10px] font-bold uppercase tracking-wider text-emerald-600 dark:text-emerald-400 bg-emerald-100 dark:bg-emerald-950/60 px-2 py-0.5 rounded">
                Verified Record
              </span>
            </div>

            <!-- Student Name (Read-Only) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Student Name
              </label>
              <input
                type="text"
                readonly
                :value="verifiedStudent.name"
                class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold text-slate-900 dark:text-white cursor-not-allowed select-none"
              />
            </div>

            <!-- Department (Read-Only) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Department
              </label>
              <input
                type="text"
                readonly
                :value="departmentDisplayName"
                class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold text-slate-900 dark:text-white cursor-not-allowed select-none"
              />
            </div>

            <!-- Course (Read-Only) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Course
              </label>
              <input
                type="text"
                readonly
                :value="verifiedStudent.course"
                class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold text-blue-600 dark:text-blue-400 cursor-not-allowed select-none"
              />
            </div>

            <!-- Year Level & Section (Grid) -->
            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Year Level
                </label>
                <input
                  type="text"
                  readonly
                  :value="verifiedStudent.yearLevel"
                  class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold text-slate-900 dark:text-white cursor-not-allowed select-none"
                />
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Section
                </label>
                <input
                  type="text"
                  readonly
                  :value="verifiedStudent.section"
                  class="w-full px-3.5 py-2.5 bg-slate-100 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-xl text-xs font-bold font-mono text-indigo-600 dark:text-indigo-400 cursor-not-allowed select-none"
                />
              </div>
            </div>

            <!-- Password & Confirm Password -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-[#333]">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Password *
                </label>
                <div class="relative">
                  <input
                    v-model="regPassword"
                    :type="showRegPassword ? 'text' : 'password'"
                    required
                    placeholder="Enter password"
                    class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
                  />
                  <button
                    type="button"
                    @click="showRegPassword = !showRegPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 transition cursor-pointer"
                  >
                    <EyeOff v-if="showRegPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Confirm Password *
                </label>
                <div class="relative">
                  <input
                    v-model="regConfirmPassword"
                    :type="showRegConfirmPassword ? 'text' : 'password'"
                    required
                    placeholder="Confirm password"
                    class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
                  />
                  <button
                    type="button"
                    @click="showRegConfirmPassword = !showRegConfirmPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 transition cursor-pointer"
                  >
                    <EyeOff v-if="showRegConfirmPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Submit Button -->
            <button
              type="submit"
              :disabled="isLoading || !regPassword.trim() || !regConfirmPassword.trim()"
              class="w-full mt-2 py-3 px-4 bg-[#1E3A5F] hover:bg-[#152A45] text-white text-xs font-bold rounded-xl shadow-xs transition-all flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="isLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <UserCheck v-else class="w-4 h-4" />
              <span>{{ isLoading ? 'Creating Student Account...' : 'Create Student Account' }}</span>
            </button>
          </div>

          <!-- B. Manual Student Registration Form (Pending Verification Mode) -->
          <div v-if="isManualMode" class="space-y-3.5 pt-1 animate-fade-in">
            <!-- Pending Verification Banner -->
            <div class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-900/50 rounded-2xl text-xs text-amber-800 dark:text-amber-300 space-y-1">
              <div class="flex items-center gap-1.5 font-bold">
                <ShieldAlert class="w-4 h-4 text-amber-600 dark:text-amber-400" />
                <span>Manual Registration — Pending Verification</span>
              </div>
              <p class="text-[10px] text-amber-700 dark:text-amber-400/90 leading-relaxed">
                Your account will be created with <strong>PENDING VERIFICATION</strong> status until verified by an Administrator against institutional records.
              </p>
            </div>

            <!-- Student Name (Manual Entry) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Full Student Name *
              </label>
              <input
                v-model="manualName"
                type="text"
                required
                placeholder="e.g. Juan Dela Cruz"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
              />
            </div>

            <!-- Department (Select Dropdown) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Department *
              </label>
              <select
                v-model="manualDepartment"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition cursor-pointer"
              >
                <option v-for="dept in NCST_DEPARTMENTS" :key="dept.code" :value="dept.code">
                  {{ dept.code }} - {{ dept.name }}
                </option>
              </select>
            </div>

            <!-- Course (Select Dropdown) -->
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Course *
              </label>
              <select
                v-model="manualCourse"
                class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-bold text-blue-600 dark:text-blue-400 focus:outline-none focus:border-blue-500 transition cursor-pointer"
              >
                <option v-for="c in availableCourses" :key="c.code" :value="c.code">
                  {{ c.code }} - {{ c.name }}
                </option>
              </select>
            </div>

            <!-- Searchable Section Input with Autocomplete Suggestions -->
            <div class="relative min-w-0" ref="sectionAutocompleteRef">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Section *
              </label>

              <div class="relative">
                <input
                  ref="sectionInputRef"
                  v-model="manualSection"
                  @input="onSectionInput"
                  @focus="onSectionInput"
                  @blur="onSectionBlur"
                  @keydown="onSectionKeyDown"
                  type="text"
                  required
                  autocomplete="off"
                  placeholder="Type section code..."
                  class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border rounded-xl text-xs font-mono font-bold text-slate-900 dark:text-white placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none transition uppercase"
                  :class="{
                    'border-rose-400 focus:border-rose-500 focus:ring-1 focus:ring-rose-500/20': !isSectionSuggestionsOpen && sectionErrorMessage && sectionTouched,
                    'border-emerald-500 focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500/20': isSectionValid,
                    'border-slate-300 dark:border-[#3a3a3a] focus:border-blue-500 focus:ring-1 focus:ring-blue-500': isSectionSuggestionsOpen || (!sectionErrorMessage && !isSectionValid),
                  }"
                />
              </div>

              <!-- Autocomplete Dropdown List directly below input (Shown ONLY when matching sections exist) -->
              <div
                v-if="isSectionSuggestionsOpen && filteredSectionSuggestions.length > 0"
                class="absolute top-full left-0 right-0 z-50 mt-1 bg-white dark:bg-[#222222] border border-slate-200 dark:border-[#3a3a3a] rounded-xl shadow-xl max-h-48 overflow-y-auto p-1 space-y-0.5 custom-scrollbar animate-fade-in"
              >
                <button
                  v-for="(suggestion, idx) in filteredSectionSuggestions"
                  :key="suggestion"
                  type="button"
                  @mousedown.prevent="selectSection(suggestion)"
                  class="w-full px-3 py-1.5 rounded-lg text-left text-xs font-mono font-bold transition cursor-pointer flex items-center justify-between"
                  :class="[
                    highlightedSectionIndex === idx || manualSection.trim().toUpperCase() === suggestion
                      ? 'bg-blue-600 text-white shadow-xs'
                      : 'text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#2e2e2e]'
                  ]"
                >
                  <span>{{ suggestion }}</span>
                  <span
                    class="text-[10px] font-normal opacity-75"
                    :class="highlightedSectionIndex === idx || manualSection.trim().toUpperCase() === suggestion ? 'text-blue-100' : 'text-slate-400 dark:text-slate-500'"
                  >
                    Section
                  </span>
                </button>
              </div>

              <!-- Message A: No matching sections found (when typing with 0 matches) -->
              <p v-if="hasNoMatches && isSectionSuggestionsOpen" class="text-[10px] text-amber-600 dark:text-amber-400 font-medium mt-1">
                No matching sections found.
              </p>

              <!-- Message B: Invalid Section or Required error (only when blurred or submitted) -->
              <p v-else-if="!isSectionSuggestionsOpen && sectionErrorMessage && sectionTouched" class="text-[10px] text-rose-500 font-medium mt-1">
                {{ sectionErrorMessage }}
              </p>
            </div>

            <!-- Password & Confirm Password -->
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-[#333]">
              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Password *
                </label>
                <div class="relative">
                  <input
                    v-model="regPassword"
                    :type="showRegPassword ? 'text' : 'password'"
                    required
                    placeholder="Enter password"
                    class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
                  />
                  <button
                    type="button"
                    @click="showRegPassword = !showRegPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 transition cursor-pointer"
                  >
                    <EyeOff v-if="showRegPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>

              <div>
                <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                  Confirm Password *
                </label>
                <div class="relative">
                  <input
                    v-model="regConfirmPassword"
                    :type="showRegConfirmPassword ? 'text' : 'password'"
                    required
                    placeholder="Confirm password"
                    class="w-full pl-3.5 pr-10 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
                  />
                  <button
                    type="button"
                    @click="showRegConfirmPassword = !showRegConfirmPassword"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 transition cursor-pointer"
                  >
                    <EyeOff v-if="showRegConfirmPassword" class="w-4 h-4" />
                    <Eye v-else class="w-4 h-4" />
                  </button>
                </div>
              </div>
            </div>

            <!-- Submit Button (Manual Pending Verification) -->
            <button
              type="submit"
              :disabled="isLoading || !manualName.trim() || !regPassword.trim() || !regConfirmPassword.trim()"
              class="w-full mt-2 py-3 px-4 bg-amber-600 hover:bg-amber-700 text-white text-xs font-extrabold rounded-xl shadow-md transition-all transform hover:scale-[1.01] active:scale-[0.99] flex items-center justify-center gap-2 cursor-pointer disabled:opacity-60 disabled:cursor-not-allowed"
            >
              <span v-if="isLoading" class="w-4 h-4 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <UserPlus v-else class="w-4 h-4" />
              <span>{{ isLoading ? 'Creating Pending Account...' : 'Create Student Account (Pending Verification)' }}</span>
            </button>
          </div>
        </form>

        <!-- Controlled Demo Accounts Accordion (Defense Demonstration Environment Only - Excluded in Production) -->
        <div v-if="isDemoMode" class="mt-6 pt-5 border-t border-slate-200 dark:border-[#333333]">
          <div class="mb-2.5 px-3 py-2 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/50 rounded-xl flex items-center gap-2 text-[10.5px] text-amber-800 dark:text-amber-300">
            <ShieldAlert class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
            <span><strong>Controlled Demo Mode:</strong> Demonstration auto-fill is active for evaluation only. Excluded from production.</span>
          </div>

          <button
            type="button"
            @click="showCredentialHints = !showCredentialHints"
            class="w-full flex items-center justify-between text-[11px] font-bold text-slate-500 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white transition cursor-pointer"
          >
            <span class="flex items-center gap-1.5">
              <KeyRound class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
              <span>Available Demo Accounts (Defense Evaluation)</span>
            </span>
            <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showCredentialHints }" />
          </button>

          <div v-if="showCredentialHints" class="mt-3 space-y-2 text-xs">
            <div
              v-for="acc in testAccounts"
              :key="acc.identifier"
              @click="fillCredentials(acc.identifier, 'password123')"
              class="p-2.5 rounded-xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] hover:border-blue-500/50 hover:bg-slate-100 dark:hover:bg-[#2c2c2c] transition cursor-pointer flex items-center justify-between group"
            >
              <div class="min-w-0">
                <p class="font-bold text-slate-900 dark:text-white group-hover:text-blue-600 dark:group-hover:text-blue-400 truncate">
                  {{ acc.name }}
                </p>
                <p class="text-[10px] text-slate-500 dark:text-[#b3b3b3] truncate font-mono">
                  ID: {{ acc.idNumber }} • {{ acc.identifier }}
                </p>
              </div>
              <div class="flex items-center gap-2 flex-shrink-0">
                <span class="text-[9px] uppercase font-mono px-2 py-0.5 rounded font-bold" :class="acc.roleClass">
                  {{ acc.role }}
                </span>
                <span class="text-[10px] text-blue-600 dark:text-blue-400 font-semibold group-hover:underline">Use &rarr;</span>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Verified Forgot Password Modal -->
    <div
      v-if="isForgotPasswordOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
    >
      <div class="bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-3xl w-full max-w-md p-6 shadow-2xl relative transition-all">
        <!-- Close Button -->
        <button
          type="button"
          @click="closeForgotPassword"
          class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 cursor-pointer transition p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-[#333333]"
        >
          <X class="w-5 h-5" />
        </button>

        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
          <div class="p-2.5 rounded-2xl bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
            <KeyRound class="w-6 h-6" />
          </div>
          <div>
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
              Reset Your Password
            </h3>
            <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
              Verify your student identity to recover your account
            </p>
          </div>
        </div>

        <!-- Alert in Modal -->
        <div
          v-if="fpError"
          class="mb-4 p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-500/40 rounded-xl text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 flex-shrink-0" />
          <span>{{ fpError }}</span>
        </div>

        <!-- Info / Success in Modal -->
        <div
          v-if="fpInfoMessage"
          class="mb-4 p-3 bg-blue-50 dark:bg-blue-950/70 border border-blue-200 dark:border-blue-500/40 rounded-xl text-blue-700 dark:text-blue-300 text-xs flex items-center gap-2"
        >
          <CheckCircle2 class="w-4 h-4 flex-shrink-0" />
          <span>{{ fpInfoMessage }}</span>
        </div>

        <!-- Employee Notice / Block Warning -->
        <div class="mb-4 p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/40 rounded-xl text-[11px] text-amber-800 dark:text-amber-300">
          <p class="font-bold flex items-center gap-1.5">
            <ShieldAlert class="w-4 h-4 text-amber-600 dark:text-amber-400" />
            <span>Institutional Policy Notice:</span>
          </p>
          <p class="text-[10px] text-amber-700 dark:text-amber-400/90 mt-1 leading-relaxed">
            Faculty, Event Staff, Cashier, and Admin accounts cannot be reset self-service for security compliance. Please contact the NCST IT Center for Admin-assisted password reset.
          </p>
        </div>

        <!-- Password Reset Form -->
        <form @submit.prevent="handleUnifiedResetSubmit" class="space-y-3.5">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
              Student ID Number *
            </label>
            <input
              v-model="fpStudentId"
              type="text"
              required
              placeholder="e.g. 2026-00125"
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-mono text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
            />
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
              Institutional Email Address *
            </label>
            <input
              v-model="fpEmail"
              type="email"
              required
              placeholder="e.g. juan.delacruz@student.ncst.edu.ph"
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
            />
          </div>

          <div>
            <div class="flex items-center justify-between mb-1">
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300">
                6-Digit Verification Code *
              </label>
              <button
                type="button"
                @click="handleRequestCode"
                :disabled="fpLoading || !fpStudentId.trim() || !fpEmail.trim()"
                class="text-[11px] font-bold text-blue-600 dark:text-blue-400 hover:underline cursor-pointer disabled:opacity-40 disabled:no-underline"
              >
                {{ codeSent ? 'Resend Code' : 'Send Code to Email' }}
              </button>
            </div>
            <input
              v-model="fpVerificationCode"
              type="text"
              maxlength="6"
              placeholder="Enter 6-digit code (or click Send Code)"
              class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-center text-sm font-mono tracking-widest font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
            />
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              A one-time verification code is sent to your registered email to prove account ownership.
            </p>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 pt-1">
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                New Password *
              </label>
              <div class="relative">
                <input
                  v-model="fpNewPassword"
                  :type="showFpNewPassword ? 'text' : 'password'"
                  required
                  placeholder="••••••••"
                  class="w-full pl-3.5 pr-10 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
                />
                <button
                  type="button"
                  @click="showFpNewPassword = !showFpNewPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
                >
                  <EyeOff v-if="showFpNewPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
            <div>
              <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
                Confirm Password *
              </label>
              <div class="relative">
                <input
                  v-model="fpConfirmPassword"
                  :type="showFpConfirmPassword ? 'text' : 'password'"
                  required
                  placeholder="••••••••"
                  class="w-full pl-3.5 pr-10 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 transition"
                />
                <button
                  type="button"
                  @click="showFpConfirmPassword = !showFpConfirmPassword"
                  class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 transition cursor-pointer"
                >
                  <EyeOff v-if="showFpConfirmPassword" class="w-4 h-4" />
                  <Eye v-else class="w-4 h-4" />
                </button>
              </div>
            </div>
          </div>

          <div class="flex items-center gap-2 pt-3 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="button"
              @click="closeForgotPassword"
              class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#333333] dark:hover:bg-[#3d3d3d] text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="fpLoading || !fpStudentId.trim() || !fpNewPassword.trim()"
              class="flex-1 py-2.5 px-4 bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-extrabold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="fpLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <CheckCircle2 v-else class="w-3.5 h-3.5" />
              <span>Verify & Reset</span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- 3. Separate Outsider / Guest Ticket Retrieval Modal -->
    <div
      v-if="isGuestLookupOpen"
      class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
    >
      <div class="bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-3xl w-full max-w-lg p-6 shadow-2xl relative transition-all max-h-[90vh] overflow-y-auto">
        <!-- Close Button -->
        <button
          type="button"
          @click="closeGuestLookupModal"
          class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 cursor-pointer transition p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-[#333333]"
        >
          <X class="w-5 h-5" />
        </button>

        <!-- Header -->
        <div class="flex items-center gap-3 mb-5">
          <div class="p-2.5 rounded-2xl bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
            <Ticket class="w-6 h-6" />
          </div>
          <div>
            <h3 class="text-lg font-extrabold text-slate-900 dark:text-white">
              Find My Ticket
            </h3>
            <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
              Retrieve your admission pass using your ticket Control Number
            </p>
          </div>
        </div>

        <!-- Alert in Modal -->
        <div
          v-if="guestLookupError"
          class="mb-4 p-3 bg-rose-50 dark:bg-rose-950/70 border border-rose-200 dark:border-rose-500/40 rounded-xl text-rose-700 dark:text-rose-300 text-xs flex items-center gap-2"
        >
          <AlertCircle class="w-4 h-4 flex-shrink-0" />
          <span>{{ guestLookupError }}</span>
        </div>

        <!-- Flow A: Search Form (When ticket has not been retrieved yet) -->
        <form v-if="!guestTicketData" @submit.prevent="handleGuestLookup" class="space-y-4">
          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
              Ticket Control Number *
            </label>
            <input
              v-model="guestControlNumber"
              type="text"
              required
              placeholder="e.g. TKT-2026-00123 or Control Number"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs font-mono font-bold text-slate-900 dark:text-white uppercase placeholder:font-normal placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
            />
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              Found on your cashier receipt or ticket confirmation slip.
            </p>
          </div>

          <div>
            <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
              Contact Number (Required Verification) *
            </label>
            <input
              v-model="guestContactNumber"
              type="text"
              required
              placeholder="e.g. 09171234567"
              class="w-full px-3.5 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
            />
            <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
              Required: Must match the mobile number registered during ticket purchase for ownership verification.
            </p>
          </div>

          <div class="p-3 bg-slate-50 dark:bg-[#1e1e1e] rounded-xl border border-slate-200 dark:border-[#333] text-[11px] text-slate-600 dark:text-slate-400 leading-relaxed">
            Guest ticket retrieval displays only the verified event pass details. It does not grant Student Portal access.
          </div>

          <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="button"
              @click="closeGuestLookupModal"
              class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#333333] dark:hover:bg-[#3d3d3d] text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Cancel
            </button>
            <button
              type="submit"
              :disabled="guestLookupLoading || !guestControlNumber.trim()"
              class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
            >
              <span v-if="guestLookupLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
              <Search v-else class="w-3.5 h-3.5" />
              <span>Retrieve Ticket</span>
            </button>
          </div>
        </form>

        <!-- Flow B: Retrieved Guest Ticket Pass Display -->
        <div v-else class="space-y-4 animate-fade-in">
          <!-- Ticket Pass Container -->
          <div class="border-2 border-dashed border-blue-300 dark:border-blue-700/60 bg-blue-50/40 dark:bg-blue-950/20 rounded-2xl p-4 sm:p-5 text-left relative overflow-hidden">
            <div class="flex items-start justify-between gap-2 border-b border-blue-200/60 dark:border-blue-800/40 pb-3 mb-3">
              <div>
                <span class="text-[10px] font-bold uppercase tracking-wider text-blue-600 dark:text-blue-400 bg-blue-100 dark:bg-blue-900/60 px-2 py-0.5 rounded">
                  Guest Admission Pass
                </span>
                <h4 class="text-base font-black text-slate-900 dark:text-white mt-1">
                  {{ guestTicketData.eventTitle }}
                </h4>
              </div>
              <div class="text-right">
                <span class="text-[10px] font-mono font-bold text-slate-500 dark:text-slate-400 block">CONTROL NO.</span>
                <span class="text-xs font-mono font-black text-blue-700 dark:text-blue-300">
                  {{ guestTicketData.controlNumber }}
                </span>
              </div>
            </div>

            <!-- Ticket Details Grid -->
            <div class="grid grid-cols-2 gap-3 text-xs mb-3">
              <div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Attendee</span>
                <span class="font-bold text-slate-900 dark:text-white">{{ guestTicketData.attendeeName }}</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Ticket Status</span>
                <span class="font-bold text-emerald-600 dark:text-emerald-400 uppercase text-[11px]">{{ guestTicketData.ticketStatus }}</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Date</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ guestTicketData.date || 'Event Day' }}</span>
              </div>
              <div>
                <span class="text-[10px] text-slate-500 dark:text-slate-400 block">Venue</span>
                <span class="font-medium text-slate-800 dark:text-slate-200">{{ guestTicketData.venue }}</span>
              </div>
            </div>

            <!-- QR Code Token Card -->
            <div class="bg-white dark:bg-[#1a1a1a] rounded-xl p-3 border border-slate-200 dark:border-[#333] flex flex-col items-center justify-center text-center">
              <div class="w-32 h-32 bg-slate-50 dark:bg-[#222] border-2 border-slate-300 dark:border-[#444] rounded-lg flex flex-col items-center justify-center p-2 mb-2">
                <QrCode class="w-20 h-20 text-slate-800 dark:text-slate-200" />
                <span class="text-[9px] font-mono text-slate-500 dark:text-slate-400 mt-1">Official Gate Scan</span>
              </div>
              <p class="text-[10px] font-mono font-bold text-slate-700 dark:text-slate-300">
                TOKEN: {{ guestTicketData.qrToken }}
              </p>
              <p class="text-[9px] text-slate-500 dark:text-slate-400 mt-0.5">
                Present this QR code or Control Number at the entrance scanner
              </p>
            </div>
          </div>

          <div class="p-2.5 bg-amber-50 dark:bg-amber-950/40 border border-amber-200 dark:border-amber-800/40 rounded-xl text-[10px] text-amber-800 dark:text-amber-300">
            <strong>Security Notice:</strong> This admission pass is for venue gate check-in only. Outsiders do not require or hold portal account credentials.
          </div>

          <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-[#333333]">
            <button
              type="button"
              @click="guestTicketData = null"
              class="flex-1 py-2.5 px-3 bg-slate-100 hover:bg-slate-200 dark:bg-[#333333] dark:hover:bg-[#3d3d3d] text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition cursor-pointer"
            >
              Lookup Another
            </button>
            <button
              type="button"
              @click="closeGuestLookupModal"
              class="flex-1 py-2.5 px-3 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-md transition cursor-pointer"
            >
              Done
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch, onMounted, onUnmounted } from 'vue';
import {
  User as UserIcon,
  Lock,
  Eye,
  EyeOff,
  LogIn,
  AlertCircle,
  CheckCircle2,
  UserCheck,
  UserPlus,
  KeyRound,
  ChevronDown,
  ShieldAlert,
  X,
  Search,
  Ticket,
  QrCode,
  Mail,
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { apiUrl } from '../../utils/apiUrl';
import ThemeToggle from '../common/ThemeToggle.vue';
import { UserRole, StudentRecord } from '../../types';
import {
  NCST_DEPARTMENTS,
  isValidNCSTSection,
  getCoursesForDepartment,
  deriveYearLevelFromSection,
} from '../../utils/academicMasterData';

const {
  login,
  register,
  resetPassword,
  lookupStudentRecord,
  lookupGuestTicket,
  availableSections,
} = useAppStore();

const activeTab = ref<'login' | 'register'>('login');
const loginIdentifier = ref('');
const loginPassword = ref('');
const showPassword = ref(false);
const showRegPassword = ref(false);
const showRegConfirmPassword = ref(false);
const rememberMe = ref(true);
const isLoading = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const showCredentialHints = ref(false);

// Defense Demonstration Mode Guard
// Demo account auto-fill buttons are permanently removed from production builds.
// In non-production environments, demo accounts are only visible if explicitly enabled via VITE_ENABLE_DEMO_ACCOUNTS=true.
const isDemoMode = computed(() => {
  return !import.meta.env.PROD && import.meta.env.VITE_ENABLE_DEMO_ACCOUNTS === 'true';
});

// Authoritative Student Registration & Manual Fallback State
const regStudentId = ref('');
const regPassword = ref('');
const regConfirmPassword = ref('');
const lookupState = ref<'idle' | 'checking' | 'verified' | 'not_found' | 'existing'>('idle');
const lookupMessage = ref('');
const verifiedStudent = ref<StudentRecord | null>(null);
let lookupDebounceTimer: any = null;

// Manual Registration Fallback State
const isManualMode = ref(false);
const manualName = ref('');
const manualDepartment = ref('CSD');
const manualCourse = ref('BSIT');
const manualSection = ref('');
const isSectionSuggestionsOpen = ref(false);
const highlightedSectionIndex = ref(-1);
const sectionInputRef = ref<HTMLInputElement | null>(null);
const sectionAutocompleteRef = ref<HTMLElement | null>(null);
const sectionTouched = ref(false);

const filteredSectionSuggestions = computed(() => {
  const query = manualSection.value.trim().toLowerCase();
  if (!query) return [];

  const source = availableSections.value || [];

  // Match actual NCST sections starting with query, then containing query
  const startsWithMatches = source.filter(s => s.toLowerCase().startsWith(query));
  const includesMatches = source.filter(s => !s.toLowerCase().startsWith(query) && s.toLowerCase().includes(query));

  return [...startsWithMatches, ...includesMatches];
});

const hasNoMatches = computed(() => {
  const query = manualSection.value.trim();
  return query.length > 0 && filteredSectionSuggestions.value.length === 0;
});

const isSectionValid = computed(() => {
  const val = manualSection.value.trim().toUpperCase();
  if (!val) return false;
  return (availableSections.value || []).includes(val);
});

const sectionErrorMessage = computed(() => {
  const val = manualSection.value.trim().toUpperCase();
  if (!val && !sectionTouched.value) return '';
  if (!val && sectionTouched.value) return 'Section is required.';
  if (!isSectionValid.value) {
    return 'Please select a valid section code.';
  }
  return '';
});

const selectSection = (sectionValue: string) => {
  manualSection.value = sectionValue.trim().toUpperCase();
  isSectionSuggestionsOpen.value = false;
  highlightedSectionIndex.value = -1;
  sectionTouched.value = true;
};

const onSectionInput = () => {
  highlightedSectionIndex.value = 0;
  isSectionSuggestionsOpen.value = manualSection.value.trim().length > 0;
};

const onSectionBlur = () => {
  sectionTouched.value = true;
  setTimeout(() => {
    isSectionSuggestionsOpen.value = false;
  }, 180);
};

const onSectionKeyDown = (e: KeyboardEvent) => {
  const suggestions = filteredSectionSuggestions.value;
  if (!isSectionSuggestionsOpen.value || suggestions.length === 0) {
    if (e.key === 'ArrowDown' && manualSection.value.trim()) {
      isSectionSuggestionsOpen.value = true;
      highlightedSectionIndex.value = 0;
      e.preventDefault();
    }
    return;
  }

  if (e.key === 'ArrowDown') {
    e.preventDefault();
    highlightedSectionIndex.value = (highlightedSectionIndex.value + 1) % suggestions.length;
  } else if (e.key === 'ArrowUp') {
    e.preventDefault();
    highlightedSectionIndex.value = (highlightedSectionIndex.value - 1 + suggestions.length) % suggestions.length;
  } else if (e.key === 'Enter') {
    if (highlightedSectionIndex.value >= 0 && highlightedSectionIndex.value < suggestions.length) {
      e.preventDefault();
      selectSection(suggestions[highlightedSectionIndex.value]);
    }
  } else if (e.key === 'Escape') {
    isSectionSuggestionsOpen.value = false;
    highlightedSectionIndex.value = -1;
  }
};

const handleClickOutsideSection = (e: MouseEvent) => {
  if (sectionAutocompleteRef.value && !sectionAutocompleteRef.value.contains(e.target as Node)) {
    isSectionSuggestionsOpen.value = false;
  }
};

const availableCourses = computed(() => {
  return getCoursesForDepartment(manualDepartment.value);
});

watch(manualDepartment, (newDept) => {
  const courses = getCoursesForDepartment(newDept);
  if (courses.length > 0 && !courses.some(c => c.code === manualCourse.value)) {
    manualCourse.value = courses[0].code;
  }
});

const enableManualRegistration = () => {
  isManualMode.value = true;
  manualName.value = '';
  manualDepartment.value = 'CSD';
  manualCourse.value = 'BSIT';
  manualSection.value = '';
  isSectionSuggestionsOpen.value = false;
  sectionTouched.value = false;
  regPassword.value = '';
  regConfirmPassword.value = '';
  errorMessage.value = '';
};

const departmentDisplayName = computed(() => {
  if (!verifiedStudent.value) return '';
  const dept = (verifiedStudent.value.department || 'CSD').trim().toUpperCase();
  if (dept === 'CSD' || dept.includes('COMPUT')) return 'College of Computing Studies';
  if (dept === 'ENG' || dept === 'CEA' || dept.includes('ENGINEER')) return 'College of Engineering & Architecture';
  if (dept === 'CBA' || dept.includes('BUSINESS')) return 'College of Business Administration';
  if (dept === 'GENED' || dept.includes('GENERAL')) return 'General Education Department';
  if (dept === 'EDUC' || dept.includes('EDUCATION')) return 'College of Education';
  if (dept === 'CCJ' || dept.includes('CRIM')) return 'College of Criminology & Justice';
  if (dept === 'CHTM' || dept.includes('HOSPITALITY')) return 'College of Hospitality & Tourism Management';
  return verifiedStudent.value.department || 'College of Computing Studies';
});

const resetStudentLookup = () => {
  regStudentId.value = '';
  lookupState.value = 'idle';
  verifiedStudent.value = null;
  lookupMessage.value = '';
  isManualMode.value = false;
  manualName.value = '';
  regPassword.value = '';
  regConfirmPassword.value = '';
  errorMessage.value = '';
};

const performLookup = async (idToLookup: string) => {
  if (lookupDebounceTimer) clearTimeout(lookupDebounceTimer);
  const raw = idToLookup.trim();
  if (!raw) {
    lookupState.value = 'idle';
    verifiedStudent.value = null;
    lookupMessage.value = '';
    return;
  }

  errorMessage.value = '';
  lookupState.value = 'checking';
  isManualMode.value = false;

  try {
    const res = await lookupStudentRecord(raw);
    if (res.hasExistingAccount) {
      lookupState.value = 'existing';
      verifiedStudent.value = null;
      lookupMessage.value = 'An account already exists for this Student ID. Please sign in instead.';
    } else if (res.found && res.student) {
      lookupState.value = 'verified';
      verifiedStudent.value = res.student;
      lookupMessage.value = '';
    } else {
      lookupState.value = 'not_found';
      verifiedStudent.value = null;
      lookupMessage.value = 'Student ID not found in institutional registry.';
    }
  } catch {
    lookupState.value = 'not_found';
    verifiedStudent.value = null;
    lookupMessage.value = 'Student ID not found in institutional registry.';
  }
};

const triggerManualLookup = () => {
  if (lookupDebounceTimer) clearTimeout(lookupDebounceTimer);
  const raw = regStudentId.value.trim();
  if (!raw) return;
  performLookup(raw);
};

const onStudentIdInput = () => {
  if (lookupDebounceTimer) {
    clearTimeout(lookupDebounceTimer);
  }

  const raw = regStudentId.value.trim();
  errorMessage.value = '';

  if (!raw) {
    lookupState.value = 'idle';
    verifiedStudent.value = null;
    lookupMessage.value = '';
    isManualMode.value = false;
    return;
  }

  // Auto-search if complete pattern
  if (raw.length >= 8 || /^\d{4}-\d+$/.test(raw)) {
    lookupDebounceTimer = setTimeout(() => {
      performLookup(raw);
    }, 300);
  } else {
    lookupState.value = 'idle';
    verifiedStudent.value = null;
    lookupMessage.value = '';
  }
};

const onStudentIdBlur = () => {
  if (lookupDebounceTimer) clearTimeout(lookupDebounceTimer);
  const raw = regStudentId.value.trim();
  if (raw && lookupState.value === 'idle' && !isManualMode.value) {
    performLookup(raw);
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutsideSection);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutsideSection);
  if (lookupDebounceTimer) clearTimeout(lookupDebounceTimer);
});

// Verified Forgot Password State (With Email Verification Proof)
const isForgotPasswordOpen = ref(false);
const codeSent = ref(false);
const fpStudentId = ref('');
const fpEmail = ref('');
const fpVerificationCode = ref('');
const fpNewPassword = ref('');
const fpConfirmPassword = ref('');
const showFpNewPassword = ref(false);
const showFpConfirmPassword = ref(false);
const fpError = ref('');
const fpInfoMessage = ref('');
const fpLoading = ref(false);

const openForgotPassword = () => {
  isForgotPasswordOpen.value = true;
  codeSent.value = false;
  fpStudentId.value = '';
  fpEmail.value = loginIdentifier.value.includes('@') ? loginIdentifier.value : '';
  fpVerificationCode.value = '';
  fpNewPassword.value = '';
  fpConfirmPassword.value = '';
  showFpNewPassword.value = false;
  showFpConfirmPassword.value = false;
  fpError.value = '';
  fpInfoMessage.value = '';
};

const closeForgotPassword = () => {
  isForgotPasswordOpen.value = false;
};

const handleRequestCode = async () => {
  fpError.value = '';
  fpInfoMessage.value = '';

  if (!fpStudentId.value.trim()) {
    fpError.value = 'Student ID Number is required.';
    return;
  }
  if (!fpEmail.value.trim() || !fpEmail.value.includes('@')) {
    fpError.value = 'Valid Institutional Email Address is required.';
    return;
  }

  fpLoading.value = true;
  try {
    const res = await fetch(apiUrl('/api/auth/reset-password'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        action: 'request_code',
        studentId: fpStudentId.value.trim(),
        email: fpEmail.value.trim(),
      }),
    });
    const data = await res.json().catch(() => ({}));
    fpLoading.value = false;

    if (res.ok && data.success) {
      codeSent.value = true;
      fpInfoMessage.value = data.message || 'Verification code sent to your email!';
      if (data.dev_verification_code) {
        fpVerificationCode.value = data.dev_verification_code;
      }
    } else {
      fpError.value = data.error || 'Failed to request verification code. Please check your Student ID and Email.';
    }
  } catch {
    fpLoading.value = false;
    fpError.value = 'Network error: Unable to contact server for verification code.';
  }
};

const handleVerifyAndReset = async () => {
  fpError.value = '';

  if (!fpVerificationCode.value.trim()) {
    fpError.value = 'Please enter the 6-digit verification code.';
    return;
  }
  if (fpNewPassword.value !== fpConfirmPassword.value) {
    fpError.value = 'Passwords do not match. Please re-enter.';
    return;
  }
  if (fpNewPassword.value.length < 4) {
    fpError.value = 'Password must be at least 4 characters long.';
    return;
  }

  fpLoading.value = true;
  try {
    const res = await fetch(apiUrl('/api/auth/reset-password'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        studentId: fpStudentId.value.trim(),
        email: fpEmail.value.trim(),
        verificationCode: fpVerificationCode.value.trim(),
        newPassword: fpNewPassword.value,
      }),
    });
    const data = await res.json().catch(() => ({}));
    fpLoading.value = false;

    if (res.ok && data.success) {
      // Synchronize in local store
      resetPassword(fpStudentId.value.trim(), fpNewPassword.value);
      closeForgotPassword();
      activeTab.value = 'login';
      loginIdentifier.value = fpStudentId.value.trim();
      loginPassword.value = fpNewPassword.value;
      successMessage.value = data.message || 'Password successfully reset!';
      errorMessage.value = '';
    } else {
      fpError.value = data.error || 'Invalid or expired verification code. Please request a new code.';
    }
  } catch {
    fpLoading.value = false;
    fpError.value = 'Network error: Unable to complete password reset.';
  }
};

const handleUnifiedResetSubmit = async () => {
  fpError.value = '';
  fpInfoMessage.value = '';

  if (!fpStudentId.value.trim()) {
    fpError.value = 'Student ID Number is required.';
    return;
  }
  if (!fpEmail.value.trim() || !fpEmail.value.includes('@')) {
    fpError.value = 'Valid Institutional Email Address is required.';
    return;
  }

  if (!fpVerificationCode.value.trim()) {
    await handleRequestCode();
    if (!fpError.value) {
      fpInfoMessage.value = 'Verification code sent to your email! Please enter the code and click Verify & Reset to finish.';
    }
    return;
  }

  await handleVerifyAndReset();
};

// Outsider / Guest Ticket Retrieval State
const isGuestLookupOpen = ref(false);
const guestControlNumber = ref('');
const guestContactNumber = ref('');
const guestLookupLoading = ref(false);
const guestLookupError = ref('');
const guestTicketData = ref<any | null>(null);

const openGuestLookupModal = () => {
  isGuestLookupOpen.value = true;
  guestControlNumber.value = '';
  guestContactNumber.value = '';
  guestLookupLoading.value = false;
  guestLookupError.value = '';
  guestTicketData.value = null;
};

const closeGuestLookupModal = () => {
  isGuestLookupOpen.value = false;
  guestTicketData.value = null;
  guestLookupError.value = '';
};

const handleGuestLookup = async () => {
  guestLookupError.value = '';
  if (!guestControlNumber.value.trim()) {
    guestLookupError.value = 'Please enter your ticket Control Number.';
    return;
  }

  if (!guestContactNumber.value.trim()) {
    guestLookupError.value = 'Please enter your registered Contact Number for ownership verification.';
    return;
  }

  guestLookupLoading.value = true;
  try {
    const res = await lookupGuestTicket(guestControlNumber.value.trim(), guestContactNumber.value.trim());
    guestLookupLoading.value = false;
    if (res.success && res.ticket) {
      guestTicketData.value = res.ticket;
    } else {
      guestTicketData.value = null;
      guestLookupError.value = (res as any).error || res.message || 'No matching ticket found for this Control Number.';
    }
  } catch (err: any) {
    guestLookupLoading.value = false;
    guestLookupError.value = err?.message || 'Failed to retrieve ticket. Please check the Control Number.';
  }
};

const testAccounts = [
  {
    role: 'Admin',
    name: 'Engr. Mark Santos',
    idNumber: 'EMP-00101',
    identifier: 'EMP-00101',
    roleClass: 'bg-purple-100 dark:bg-purple-950/40 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-500/30'
  },
  {
    role: 'Event Staff',
    name: 'Alex Rivera',
    idNumber: 'EMP-00103',
    identifier: 'EMP-00103',
    roleClass: 'bg-indigo-100 dark:bg-indigo-950/40 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-500/30'
  },
  {
    role: 'Cashier',
    name: 'Carla Gomez',
    idNumber: 'EMP-00102',
    identifier: 'EMP-00102',
    roleClass: 'bg-amber-100 dark:bg-amber-950/40 text-amber-700 dark:text-amber-300 border border-amber-300 dark:border-amber-500/30'
  },
  {
    role: 'Teacher',
    name: 'Prof. Ramon Reyes',
    idNumber: 'EMP-00125',
    identifier: 'EMP-00125',
    roleClass: 'bg-cyan-100 dark:bg-cyan-950/40 text-cyan-700 dark:text-cyan-300 border border-cyan-300 dark:border-cyan-500/30'
  },
  {
    role: 'Student',
    name: 'Juan Dela Cruz',
    idNumber: '2026-00125',
    identifier: '2026-00125',
    roleClass: 'bg-emerald-100 dark:bg-emerald-950/40 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-500/30'
  },
];

const fillCredentials = (identifier: string, pass: string) => {
  if (!isDemoMode.value) return;
  activeTab.value = 'login';
  loginIdentifier.value = identifier;
  loginPassword.value = pass;
  errorMessage.value = '';
};

const handleLogin = async () => {
  errorMessage.value = '';
  successMessage.value = '';
  isLoading.value = true;

  try {
    const result = await login(loginIdentifier.value, loginPassword.value);
    isLoading.value = false;

    if (!result.success) {
      errorMessage.value = result.message || 'Invalid credentials.';
    }
  } catch (err: any) {
    isLoading.value = false;
    errorMessage.value = err?.message || 'Failed to sign in. Please try again.';
  }
};

const handleRegister = async () => {
  errorMessage.value = '';
  successMessage.value = '';

  if (!isManualMode.value && (lookupState.value !== 'verified' || !verifiedStudent.value)) {
    errorMessage.value = 'Please search your Student ID to retrieve your official record, or continue with manual registration.';
    return;
  }

  if (isManualMode.value && !manualName.value.trim()) {
    errorMessage.value = 'Please provide your full Student Name for manual registration.';
    return;
  }

  if (regPassword.value !== regConfirmPassword.value) {
    errorMessage.value = 'Passwords do not match. Please re-enter.';
    return;
  }

  if (regPassword.value.length < 4) {
    errorMessage.value = 'Password must be at least 4 characters long.';
    return;
  }

  isLoading.value = true;

  try {
    let result;
    const cleanId = regStudentId.value.trim();

    if (isManualMode.value) {
      if (!manualName.value.trim()) {
        errorMessage.value = 'Please provide your full Student Name for manual registration.';
        isLoading.value = false;
        return;
      }

      if (!isSectionValid.value) {
        sectionTouched.value = true;
        errorMessage.value = 'Please select a valid section.';
        isLoading.value = false;
        return;
      }

      result = await register({
        name: manualName.value.trim(),
        email: `${cleanId.toLowerCase()}@student.ncst.edu.ph`,
        role: 'student',
        studentId: cleanId,
        department: manualDepartment.value,
        course: manualCourse.value,
        yearLevel: deriveYearLevelFromSection(manualSection.value) || '1st Year',
        section: manualSection.value.trim().toUpperCase(),
        password: regPassword.value,
      });
    } else {
      const student = verifiedStudent.value!;
      result = await register({
        name: student.name,
        email: student.email || `${student.studentId.toLowerCase()}@student.ncst.edu.ph`,
        role: 'student',
        studentId: student.studentId,
        department: student.department || 'CSD',
        course: student.course,
        yearLevel: student.yearLevel,
        section: student.section,
        password: regPassword.value,
      });
    }

    isLoading.value = false;

    if (!result.success) {
      if (result.conflict?.isInactive) {
        errorMessage.value = `⚠️ ACCOUNT ALREADY EXISTS (INACTIVE): An inactive account already belongs to this identity. Please contact the Administrator to reactivate your existing account.`;
      } else {
        errorMessage.value = result.message;
      }
    } else {
      activeTab.value = 'login';
      loginIdentifier.value = cleanId;
      loginPassword.value = regPassword.value;
      if (isManualMode.value) {
        successMessage.value = `Student account created for ${manualName.value.trim()}! Status: PENDING VERIFICATION. You can now sign in.`;
      } else {
        successMessage.value = `Account created successfully for ${verifiedStudent.value?.name || cleanId}! You can now sign in.`;
      }
      resetStudentLookup();
    }
  } catch (err: any) {
    isLoading.value = false;
    errorMessage.value = err?.message || 'Registration failed. Please try again.';
  }
};
</script>
