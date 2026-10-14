<template>
  <div class="space-y-6 relative">
    <!-- Modern Floating Toast Notification -->
    <Teleport to="body">
      <transition
        enter-active-class="transform ease-out duration-300 transition"
        enter-from-class="translate-y-2 opacity-0 sm:translate-y-0 sm:translate-x-2"
        enter-to-class="translate-y-0 opacity-100 sm:translate-x-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100"
        leave-to-class="opacity-0"
      >
        <div
          v-if="toastNotification"
          class="fixed bottom-6 right-6 z-50 max-w-md w-full shadow-2xl rounded-2xl p-4 flex items-start gap-3 backdrop-blur-xl border pointer-events-auto"
          :class="toastNotification.type === 'success' ? 'bg-emerald-900/95 text-emerald-100 border-emerald-500/50 shadow-emerald-950/50' : 'bg-rose-900/95 text-rose-100 border-rose-500/50 shadow-rose-950/50'"
        >
          <CheckCircle2 v-if="toastNotification.type === 'success'" class="w-5 h-5 shrink-0 text-emerald-400 mt-0.5" />
          <AlertCircle v-else class="w-5 h-5 shrink-0 text-rose-400 mt-0.5" />
          <div class="flex-1 min-w-0">
            <h4 class="text-xs font-black uppercase tracking-wider text-white">
              {{ toastNotification.title }}
            </h4>
            <p class="text-xs opacity-90 mt-0.5 leading-relaxed font-medium">
              {{ toastNotification.message }}
            </p>
          </div>
          <button
            type="button"
            @click="toastNotification = null"
            class="text-white/60 hover:text-white text-sm p-1 rounded-lg hover:bg-white/10 transition cursor-pointer"
          >
            ✕
          </button>
        </div>
      </transition>
    </Teleport>

    <!-- Header Banner -->
    <div class="relative overflow-hidden rounded-3xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] p-6 sm:p-8 shadow-sm dark:shadow-md transition-colors">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-6">
        <div class="flex items-start gap-4">
          <div class="w-14 h-14 rounded-2xl bg-emerald-50 dark:bg-emerald-500/10 border border-emerald-200 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 flex items-center justify-center flex-shrink-0 shadow-inner">
            <FileSpreadsheet class="w-7 h-7" />
          </div>
          <div>
            <div class="flex items-center gap-2 mb-1">
              <span class="text-[11px] font-bold uppercase tracking-wider px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-200 dark:border-emerald-500/30">
                Faculty Roster Portal
              </span>
              <span class="text-xs text-slate-500 dark:text-[#b3b3b3] font-medium">
                {{ currentUser?.department || 'College Faculty' }}
              </span>
            </div>
            <h1 class="text-2xl sm:text-3xl font-extrabold text-slate-900 dark:text-white tracking-tight">
              Student List & Excel Import
            </h1>
            <p class="text-xs sm:text-sm text-slate-600 dark:text-[#b3b3b3] mt-1">
              View registered students, manage section rosters, and import student data from Excel spreadsheets (.xlsx, .xls, .csv).
            </p>
          </div>
        </div>

        <!-- Download Template Button -->
        <div class="flex items-center gap-3 shrink-0">
          <button
            type="button"
            @click="handleDownloadTemplate"
            class="flex items-center gap-2 px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer"
          >
            <Download class="w-4 h-4" />
            <span>Download Excel Template (.xlsx)</span>
          </button>
        </div>
      </div>
    </div>

    <!-- Overview Stats Cards -->
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
      <div class="p-4 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between text-slate-500 dark:text-[#b3b3b3]">
          <span class="text-[10px] font-bold uppercase tracking-wider">Enrolled Students</span>
          <UsersIcon class="w-4 h-4 text-slate-400" />
        </div>
        <p class="text-2xl font-black text-slate-900 dark:text-white mt-2">{{ allStudents.length }}</p>
        <span class="text-[10px] text-slate-400">Total in institutional roster</span>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between text-blue-600 dark:text-blue-400">
          <span class="text-[10px] font-bold uppercase tracking-wider">Active Courses</span>
          <BookOpen class="w-4 h-4" />
        </div>
        <p class="text-2xl font-black text-blue-600 dark:text-blue-400 mt-2">{{ uniqueCoursesCount }}</p>
        <span class="text-[10px] text-slate-400">BSIT, BSCS, BSCpE & more</span>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-[10px] font-bold uppercase tracking-wider">Active Sections</span>
          <Layers class="w-4 h-4 text-blue-600 dark:text-blue-400" />
        </div>
        <p class="text-2xl font-bold text-slate-900 dark:text-white mt-2">{{ uniqueSectionsCount }}</p>
        <span class="text-[10px] text-slate-400">Morning, Afternoon & Evening</span>
      </div>

      <div class="p-4 rounded-2xl bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] shadow-sm flex flex-col justify-between">
        <div class="flex items-center justify-between text-slate-500 dark:text-slate-400">
          <span class="text-[10px] font-bold uppercase tracking-wider">Current Event</span>
          <Calendar class="w-4 h-4 text-blue-600 dark:text-blue-400" />
        </div>
        <p class="text-sm font-bold text-slate-900 dark:text-white mt-2 truncate">{{ currentEvent?.title || 'No Active Event' }}</p>
        <span class="text-[10px] text-slate-500 dark:text-slate-400 font-semibold">{{ currentEvent?.venue || 'Campus Venue' }}</span>
      </div>
    </div>

    <!-- Excel Upload & Configuration Panel -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
      <!-- File Dropzone (Col span 2) -->
      <div class="lg:col-span-2 space-y-4">
        <div class="p-6 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
              <Upload class="w-4 h-4 text-emerald-500" />
              Upload Class Excel File
            </h3>
            <span class="text-[11px] text-slate-400 font-medium">Accepts .xlsx, .xls, .csv</span>
          </div>

          <!-- Dropzone -->
          <div
            @dragover.prevent="isDragging = true"
            @dragleave.prevent="isDragging = false"
            @drop.prevent="handleFileDrop"
            @click="triggerFileInput"
            class="border-2 border-dashed rounded-2xl p-8 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-3"
            :class="isDragging ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/30 ring-4 ring-emerald-500/20' : 'border-slate-300 dark:border-[#3a3a3a] bg-slate-50/50 dark:bg-[#181818] hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/20'"
          >
            <input
              ref="fileInputRef"
              type="file"
              accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
              class="hidden"
              @change="handleFileInputChange"
            />
            <div class="w-16 h-16 rounded-2xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-inner">
              <FileSpreadsheet class="w-8 h-8" />
            </div>
            <div>
              <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
                Click to browse or drag & drop Excel roster spreadsheet
              </p>
              <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-1">
                Strictly accepts <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">.xlsx, .xls, .csv</strong> files only
              </p>
            </div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200/80 dark:bg-[#282828] text-slate-600 dark:text-slate-300 text-[11px] font-medium">
              <CheckCircle2 class="w-3.5 h-3.5 text-emerald-500" />
              <span>Auto-detects Student Name & ID Number columns</span>
            </div>
          </div>

          <!-- File Info Banner if loaded -->
          <div v-if="parsedFileName" class="p-3 rounded-2xl bg-slate-100 dark:bg-[#252525] border border-slate-200 dark:border-[#333] flex items-center justify-between text-xs">
            <div class="flex items-center gap-2.5">
              <FileSpreadsheet class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
              <span class="font-bold text-slate-800 dark:text-slate-200">{{ parsedFileName }}</span>
              <span class="px-2 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/50 text-emerald-800 dark:text-emerald-300 text-[10px] font-bold">
                {{ previewRecords.length }} Rows Ready
              </span>
            </div>
            <button
              type="button"
              @click="clearLoadedFile"
              class="text-slate-400 hover:text-rose-500 text-xs font-semibold transition cursor-pointer"
            >
              Remove
            </button>
          </div>

          <!-- Error Alert -->
          <div v-if="errorMessage" class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 flex items-start gap-2.5 text-xs text-rose-700 dark:text-rose-300">
            <AlertCircle class="w-4 h-4 shrink-0 text-rose-500 mt-0.5" />
            <div>
              <strong class="font-bold block">File Upload Error</strong>
              <span>{{ errorMessage }}</span>
            </div>
          </div>

          <!-- Success Alert -->
          <div v-if="successMessage" class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 flex items-center gap-2.5 text-xs text-emerald-700 dark:text-emerald-300">
            <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-500" />
            <span>{{ successMessage }}</span>
          </div>
        </div>
      </div>

      <!-- Batch Default Settings (Col span 1) -->
      <div class="space-y-4">
        <div class="p-6 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm space-y-4">
          <div class="flex items-center justify-between">
            <h3 class="text-sm font-bold text-slate-900 dark:text-white uppercase tracking-wider flex items-center gap-2">
              <SlidersHorizontal class="w-4 h-4 text-emerald-500" />
              Fallback Academic Values
            </h3>
          </div>
          <p class="text-xs text-slate-500 dark:text-[#a0a0a0]">
            Values applied to imported students when course, year, or section is omitted in the Excel columns.
          </p>

          <div class="space-y-3 pt-1">
            <!-- Default Course -->
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1 uppercase">Default Course / Program</label>
              <input
                type="text"
                list="excel-view-courses"
                v-model="defaultCourse"
                placeholder="e.g. BSIT"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              />
              <datalist id="excel-view-courses">
                <option value="BSIT">BSIT - Bachelor of Science in Information Technology</option>
                <option value="BSCS">BSCS - Bachelor of Science in Computer Science</option>
                <option value="BSCpE">BSCpE - Bachelor of Science in Computer Engineering</option>
                <option value="BSBA">BSBA - Bachelor of Science in Business Administration</option>
                <option value="BSED">BSED - Bachelor of Secondary Education</option>
                <option value="BSHM">BSHM - Bachelor of Science in Hospitality Management</option>
                <option value="BSCRIM">BSCRIM - Bachelor of Science in Criminology</option>
                <option value="BSA">BSA - Bachelor of Science in Accountancy</option>
                <option value="BSN">BSN - Bachelor of Science in Nursing</option>
                <option value="BSTM">BSTM - Bachelor of Science in Tourism Management</option>
              </datalist>
            </div>

            <!-- Default Year Level -->
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1 uppercase">Default Year Level</label>
              <select
                v-model="defaultYearLevel"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer"
              >
                <option value="1st Year">1st Year</option>
                <option value="2nd Year">2nd Year</option>
                <option value="3rd Year">3rd Year</option>
                <option value="4th Year">4th Year</option>
              </select>
            </div>

            <!-- Default Section -->
            <div>
              <label class="block text-[11px] font-bold text-slate-700 dark:text-slate-300 mb-1 uppercase">Default Section</label>
              <input
                type="text"
                list="excel-view-sections"
                v-model="defaultSection"
                placeholder="e.g. 11M1, 21M1, 31A1"
                class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
              />
              <datalist id="excel-view-sections">
                <option v-for="sec in availableSections" :key="sec" :value="sec">{{ sec }}</option>
              </datalist>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- PREVIEW TABLE (When file is loaded) -->
    <div v-if="previewRecords.length > 0" class="space-y-4 p-6 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm">
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div>
          <div class="flex items-center gap-2">
            <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
              Parsed Excel Records Preview
            </h3>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-xs font-black">
              {{ sortedPreviewRecords.length }} Students Ready
            </span>
          </div>
          <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
            Click column headers to sort by Year Level, Section, or Name before saving into the database.
          </p>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="clearLoadedFile"
            class="px-3 py-2 rounded-xl text-xs font-semibold text-slate-600 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#252525] transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="button"
            @click="handleConfirmImport"
            :disabled="isProcessing"
            class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer"
          >
            <Check class="w-4 h-4" />
            <span>{{ isProcessing ? 'Saving to Database...' : `Confirm & Import ${previewRecords.length} Students` }}</span>
          </button>
        </div>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#333333]">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#a0a0a0] uppercase tracking-wider text-[10px] font-bold border-b border-slate-200 dark:border-[#333333] select-none">
            <tr>
              <th class="px-4 py-3">#</th>
              <th @click="setPreviewSort('name')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Student Name</span>
                  <component :is="getPreviewSortIcon('name')" class="w-3.5 h-3.5" :class="previewSortBy === 'name' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setPreviewSort('studentId')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Student ID</span>
                  <component :is="getPreviewSortIcon('studentId')" class="w-3.5 h-3.5" :class="previewSortBy === 'studentId' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setPreviewSort('department')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Department</span>
                  <component :is="getPreviewSortIcon('department')" class="w-3.5 h-3.5" :class="previewSortBy === 'department' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setPreviewSort('course')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Course</span>
                  <component :is="getPreviewSortIcon('course')" class="w-3.5 h-3.5" :class="previewSortBy === 'course' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setPreviewSort('yearLevel')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Year Level</span>
                  <component :is="getPreviewSortIcon('yearLevel')" class="w-3.5 h-3.5" :class="previewSortBy === 'yearLevel' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setPreviewSort('section')" class="px-4 py-3 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Section</span>
                  <component :is="getPreviewSortIcon('section')" class="w-3.5 h-3.5" :class="previewSortBy === 'section' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th class="px-4 py-3">Email Address</th>
              <th class="px-4 py-3">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
            <tr
              v-for="(rec, idx) in sortedPreviewRecords"
              :key="idx"
              class="hover:bg-slate-50 dark:hover:bg-[#222222] transition"
            >
              <td class="px-4 py-3 font-mono text-slate-400">{{ idx + 1 }}</td>
              <td class="px-4 py-3 font-bold text-slate-900 dark:text-white whitespace-nowrap">{{ rec.name }}</td>
              <td class="px-4 py-3 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">{{ rec.studentId }}</td>
              <td class="px-4 py-3 text-slate-700 dark:text-slate-300 whitespace-nowrap font-medium">{{ rec.department || getDepartmentFromCourse(rec.course) }}</td>
              <td class="px-4 py-3 text-slate-800 dark:text-slate-200 whitespace-nowrap">{{ rec.course || defaultCourse }}</td>
              <td class="px-4 py-3 text-slate-600 dark:text-[#a0a0a0] whitespace-nowrap">{{ rec.yearLevel || defaultYearLevel }}</td>
              <td class="px-4 py-3 text-slate-800 dark:text-slate-200 font-semibold whitespace-nowrap">{{ rec.section || defaultSection }}</td>
              <td class="px-4 py-3 text-slate-500 dark:text-slate-400 font-mono text-[11px] whitespace-nowrap">{{ rec.email || `${rec.studentId?.toLowerCase()}@student.ncst.edu.ph` }}</td>
              <td class="px-4 py-3">
                <button
                  type="button"
                  @click="removePreviewRecord(idx)"
                  class="p-1 text-slate-400 hover:text-rose-500 rounded-md transition cursor-pointer"
                  title="Remove from import list"
                >
                  <Trash2 class="w-4 h-4" />
                </button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- CURRENT ENROLLED ROSTER DIRECTORY -->
    <div class="space-y-4 p-6 bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl shadow-sm">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <h3 class="text-base font-bold text-slate-900 dark:text-white tracking-tight">
            {{ isTeacher ? 'Authorized Class Student Roster' : 'Institutional Student Roster Directory' }}
          </h3>
          <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-0.5">
            {{ isTeacher ? `${filteredEnrolledStudents.length} of ${allStudents.length} authorized students across ${teacherAssignedClasses.length} assigned classes` : `${filteredEnrolledStudents.length} of ${allStudents.length} registered students in master database` }} (Sorted by {{ rosterSortBy === 'yearLevel' ? 'Year Level (1st-4th)' : rosterSortBy === 'section' ? 'Section (M1-E4)' : rosterSortBy }})
          </p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
          <!-- Year Level Filter -->
          <select
            v-model="rosterYearFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer"
          >
            <option value="all">All Year Levels</option>
            <option value="1st Year">1st Year</option>
            <option value="2nd Year">2nd Year</option>
            <option value="3rd Year">3rd Year</option>
            <option value="4th Year">4th Year</option>
          </select>

          <!-- Section Filter -->
          <select
            v-model="rosterSectionFilter"
            class="px-3 py-2 bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-800 dark:text-slate-200 focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer"
          >
            <option value="all">All Sections</option>
            <option v-for="sec in availableSections" :key="sec" :value="sec">Section {{ sec }}</option>
          </select>

          <!-- Search Input -->
          <div class="relative">
            <input
              type="text"
              v-model="rosterSearch"
              placeholder="Search name, ID or section..."
              class="w-52 px-3.5 py-2 pl-9 bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
            <Search class="w-4 h-4 text-slate-400 absolute left-3 top-2.5" />
          </div>
        </div>
      </div>

      <!-- Quick Sort Tabs for Directory -->
      <div class="flex flex-wrap items-center gap-1.5 p-2 rounded-2xl bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] text-xs">
        <span class="text-[10px] font-bold text-slate-400 uppercase tracking-wider px-1">Sort Directory By:</span>
        <button
          type="button"
          @click="setRosterSort('department')"
          class="px-2.5 py-1 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1"
          :class="rosterSortBy === 'department' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]'"
        >
          <span>Department / College</span>
          <component :is="rosterSortDir === 'asc' ? ArrowUp : ArrowDown" v-if="rosterSortBy === 'department'" class="w-3 h-3" />
        </button>
        <button
          type="button"
          @click="setRosterSort('yearLevel')"
          class="px-2.5 py-1 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1"
          :class="rosterSortBy === 'yearLevel' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]'"
        >
          <span>Year Level (1st - 4th)</span>
          <component :is="rosterSortDir === 'asc' ? ArrowUp : ArrowDown" v-if="rosterSortBy === 'yearLevel'" class="w-3 h-3" />
        </button>
        <button
          type="button"
          @click="setRosterSort('section')"
          class="px-2.5 py-1 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1"
          :class="rosterSortBy === 'section' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]'"
        >
          <span>Section (M1 - E4)</span>
          <component :is="rosterSortDir === 'asc' ? ArrowUp : ArrowDown" v-if="rosterSortBy === 'section'" class="w-3 h-3" />
        </button>
        <button
          type="button"
          @click="setRosterSort('name')"
          class="px-2.5 py-1 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1"
          :class="rosterSortBy === 'name' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]'"
        >
          <span>Student Name</span>
          <component :is="rosterSortDir === 'asc' ? ArrowUp : ArrowDown" v-if="rosterSortBy === 'name'" class="w-3 h-3" />
        </button>
        <button
          type="button"
          @click="setRosterSort('studentId')"
          class="px-2.5 py-1 rounded-xl text-xs font-semibold transition cursor-pointer flex items-center gap-1"
          :class="rosterSortBy === 'studentId' ? 'bg-emerald-600 text-white' : 'bg-white dark:bg-[#1a1a1a] text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333]'"
        >
          <span>Student ID</span>
          <component :is="rosterSortDir === 'asc' ? ArrowUp : ArrowDown" v-if="rosterSortBy === 'studentId'" class="w-3 h-3" />
        </button>
      </div>

      <div class="overflow-x-auto rounded-2xl border border-slate-200 dark:border-[#333333]">
        <table class="w-full text-left text-xs">
          <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#a0a0a0] uppercase tracking-wider text-[10px] font-bold border-b border-slate-200 dark:border-[#333333] select-none">
            <tr>
              <th @click="setRosterSort('name')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Student Name</span>
                  <component :is="getRosterSortIcon('name')" class="w-3.5 h-3.5" :class="rosterSortBy === 'name' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setRosterSort('studentId')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Student ID</span>
                  <component :is="getRosterSortIcon('studentId')" class="w-3.5 h-3.5" :class="rosterSortBy === 'studentId' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setRosterSort('department')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Department</span>
                  <component :is="getRosterSortIcon('department')" class="w-3.5 h-3.5" :class="rosterSortBy === 'department' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setRosterSort('course')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Course</span>
                  <component :is="getRosterSortIcon('course')" class="w-3.5 h-3.5" :class="rosterSortBy === 'course' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setRosterSort('yearLevel')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Year Level</span>
                  <component :is="getRosterSortIcon('yearLevel')" class="w-3.5 h-3.5" :class="rosterSortBy === 'yearLevel' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th @click="setRosterSort('section')" class="px-5 py-3.5 cursor-pointer hover:bg-slate-100 dark:hover:bg-[#2a2a2a]">
                <div class="flex items-center gap-1.5">
                  <span>Section</span>
                  <component :is="getRosterSortIcon('section')" class="w-3.5 h-3.5" :class="rosterSortBy === 'section' ? 'text-emerald-500' : 'text-slate-400 opacity-60'" />
                </div>
              </th>
              <th class="px-5 py-3.5">Email</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
            <tr
              v-for="student in filteredEnrolledStudents"
              :key="student.id"
              class="hover:bg-slate-50 dark:hover:bg-[#222222] transition"
            >
              <td class="px-5 py-3.5 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                <div class="flex items-center gap-2.5">
                  <UserAvatar :src="student.photoUrl" :alt="student.name" size="xs" shape="circle" />
                  <span>{{ student.name }}</span>
                </div>
              </td>
              <td class="px-5 py-3.5 font-mono font-bold text-blue-600 dark:text-blue-400 whitespace-nowrap">
                {{ student.studentId || 'N/A' }}
              </td>
              <td class="px-5 py-3.5 text-slate-700 dark:text-slate-300 font-medium whitespace-nowrap">
                {{ student.department || getDepartmentFromCourse(student.course) }}
              </td>
              <td class="px-5 py-3.5 text-slate-800 dark:text-slate-200 whitespace-nowrap">
                <span class="px-2 py-0.5 rounded bg-blue-50 dark:bg-blue-900/20 text-blue-700 dark:text-blue-300 font-mono text-[11px] border border-blue-200 dark:border-blue-800/40">
                  {{ student.course || 'BSIT' }}
                </span>
              </td>
              <td class="px-5 py-3.5 text-slate-600 dark:text-[#a0a0a0] whitespace-nowrap">
                {{ student.yearLevel || '3rd Year' }}
              </td>
              <td class="px-5 py-3.5 text-slate-800 dark:text-slate-200 font-semibold whitespace-nowrap">
                <span class="px-2 py-0.5 rounded bg-slate-100 dark:bg-[#2a2a2a] border border-slate-200 dark:border-[#3a3a3a]">
                  {{ student.section || '11M1' }}
                </span>
              </td>
              <td class="px-5 py-3.5 font-mono text-slate-500 dark:text-slate-400 text-[11px] whitespace-nowrap">
                {{ student.email }}
              </td>
            </tr>

            <tr v-if="filteredEnrolledStudents.length === 0">
              <td colspan="7" class="text-center py-10 text-slate-400 dark:text-slate-500">
                No enrolled students found matching search criteria.
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import {
  FileSpreadsheet, Download, Upload, SlidersHorizontal, Users as UsersIcon,
  BookOpen, Layers, Calendar, Check, Trash2, AlertCircle, CheckCircle2, Search,
  ArrowUpDown, ArrowUp, ArrowDown
} from 'lucide-vue-next';
import UserAvatar from '../common/UserAvatar.vue';
import { useAppStore } from '../../composables/useAppStore';
import { downloadStudentImportTemplate } from '../../utils/excelExport';
import { getLastName, getFirstName } from '../../utils/nameUtils';
import { StudentRecord } from '../../types';

const { currentUser, currentEvent, users, studentRecords, importStudents } = useAppStore();

const isTeacher = computed(() => currentUser.value?.role === 'teacher');
const teacherAssignedClasses = computed(() => currentUser.value?.assignedClasses || []);

const allStudents = computed<StudentRecord[]>(() => {
  const records = studentRecords.value || [];
  if (isTeacher.value) {
    const assigned = teacherAssignedClasses.value;
    if (assigned.length === 0) return [];
    return records.filter(s => {
      const sCourse = (s.course || '').trim().toUpperCase();
      const sSection = (s.section || '').trim().toUpperCase();
      return assigned.some(
        a => (a.course || '').trim().toUpperCase() === sCourse &&
             (a.section || '').trim().toUpperCase() === sSection
      );
    });
  }
  return records;
});

const availableSections = computed(() => {
  const set = new Set(allStudents.value.map(s => s.section).filter(Boolean) as string[]);
  return Array.from(set).sort();
});

const uniqueCoursesCount = computed(() => {
  const set = new Set(allStudents.value.map(s => s.course).filter(Boolean));
  return set.size || 1;
});

const uniqueSectionsCount = computed(() => {
  const set = new Set(allStudents.value.map(s => s.section).filter(Boolean));
  return set.size || 1;
});

const defaultCourse = ref('BSIT');
const defaultYearLevel = ref('3rd Year');
const defaultSection = ref('31M1');

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const parsedFileName = ref('');
const isProcessing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');
const rosterSearch = ref('');
const rosterYearFilter = ref('all');
const rosterSectionFilter = ref('all');

const toastNotification = ref<{ title: string; message: string; type: 'success' | 'error' } | null>(null);
let toastTimer: any = null;

const showToast = (title: string, message: string, type: 'success' | 'error' = 'success') => {
  if (toastTimer) clearTimeout(toastTimer);
  toastNotification.value = { title, message, type };
  toastTimer = setTimeout(() => {
    toastNotification.value = null;
  }, 4500);
};

// Sorting Helpers
const parseYearLevelRank = (yr?: string): number => {
  if (!yr) return 99;
  const lower = yr.toLowerCase();
  if (lower.includes('1st') || lower.includes('1')) return 1;
  if (lower.includes('2nd') || lower.includes('2')) return 2;
  if (lower.includes('3rd') || lower.includes('3')) return 3;
  if (lower.includes('4th') || lower.includes('4')) return 4;
  if (lower.includes('5th') || lower.includes('5')) return 5;
  return 90;
};

const parseSectionRank = (sec?: string): { shift: number; num: number; raw: string } => {
  if (!sec) return { shift: 99, num: 99, raw: '' };
  const s = sec.trim().toUpperCase();
  let shift = 50;
  if (s.startsWith('M')) shift = 1; // Morning
  else if (s.startsWith('A')) shift = 2; // Afternoon
  else if (s.startsWith('E')) shift = 3; // Evening

  const matchNum = s.match(/\d+/);
  const num = matchNum ? parseInt(matchNum[0], 10) : 0;
  return { shift, num, raw: s };
};

// Department helper
const getDepartmentFromCourse = (course?: string, customDept?: string): string => {
  if (customDept && customDept.trim()) return customDept.trim();
  if (!course) return 'College of Computer Studies';
  const c = course.trim().toUpperCase();
  if (c.includes('IT') || c.includes('CS') || c.includes('IS') || c.includes('COMP')) {
    return 'College of Computer Studies';
  }
  if (c.includes('CPE') || c.includes('ENG') || c.includes('EE') || c.includes('ECE') || c.includes('CE') || c.includes('ME')) {
    return 'College of Engineering';
  }
  if (c.includes('BA') || c.includes('BSA') || c.includes('ACT') || c.includes('MGT') || c.includes('FIN') || c.includes('ACC')) {
    return 'College of Business & Accountancy';
  }
  if (c.includes('ED') || c.includes('EDUC')) {
    return 'College of Education';
  }
  if (c.includes('HM') || c.includes('TM') || c.includes('HOSP') || c.includes('TOUR')) {
    return 'College of Hospitality & Tourism';
  }
  if (c.includes('CRIM') || c.includes('CRIMINOLOGY') || c.includes('CJ')) {
    return 'College of Criminology';
  }
  if (c.includes('NURS') || c.includes('BSN') || c.includes('MED')) {
    return 'College of Nursing';
  }
  return 'College of Computer Studies';
};

// Preview Table Sorting
type PreviewSortField = 'department' | 'name' | 'studentId' | 'course' | 'yearLevel' | 'section';
const previewSortBy = ref<PreviewSortField>('department');
const previewSortDir = ref<'asc' | 'desc'>('asc');

const setPreviewSort = (col: PreviewSortField) => {
  if (previewSortBy.value === col) {
    previewSortDir.value = previewSortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    previewSortBy.value = col;
    previewSortDir.value = 'asc';
  }
};

const getPreviewSortIcon = (col: PreviewSortField) => {
  if (previewSortBy.value !== col) return ArrowUpDown;
  return previewSortDir.value === 'asc' ? ArrowUp : ArrowDown;
};

// Directory Table Sorting
type RosterSortField = 'department' | 'name' | 'studentId' | 'course' | 'yearLevel' | 'section';
const rosterSortBy = ref<RosterSortField>('department');
const rosterSortDir = ref<'asc' | 'desc'>('asc');

const setRosterSort = (col: RosterSortField) => {
  if (rosterSortBy.value === col) {
    rosterSortDir.value = rosterSortDir.value === 'asc' ? 'desc' : 'asc';
  } else {
    rosterSortBy.value = col;
    rosterSortDir.value = 'asc';
  }
};

const getRosterSortIcon = (col: RosterSortField) => {
  if (rosterSortBy.value !== col) return ArrowUpDown;
  return rosterSortDir.value === 'asc' ? ArrowUp : ArrowDown;
};

interface ParsedStudentItem {
  name: string;
  studentId: string;
  course?: string;
  department?: string;
  yearLevel?: string;
  section?: string;
  email?: string;
}

const previewRecords = ref<ParsedStudentItem[]>([]);

const sortedPreviewRecords = computed(() => {
  const list = [...previewRecords.value];
  return list.sort((a, b) => {
    let res = 0;
    if (previewSortBy.value === 'department') {
      const deptA = a.department || getDepartmentFromCourse(a.course);
      const deptB = b.department || getDepartmentFromCourse(b.course);
      if (deptA !== deptB) res = deptA.localeCompare(deptB);
      else {
        const yrA = parseYearLevelRank(a.yearLevel || defaultYearLevel.value);
        const yrB = parseYearLevelRank(b.yearLevel || defaultYearLevel.value);
        if (yrA !== yrB) res = yrA - yrB;
        else {
          const secA = parseSectionRank(a.section || defaultSection.value);
          const secB = parseSectionRank(b.section || defaultSection.value);
          if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
          else if (secA.num !== secB.num) res = secA.num - secB.num;
          else res = (a.name || '').localeCompare(b.name || '');
        }
      }
    } else if (previewSortBy.value === 'yearLevel') {
      const yrA = parseYearLevelRank(a.yearLevel || defaultYearLevel.value);
      const yrB = parseYearLevelRank(b.yearLevel || defaultYearLevel.value);
      if (yrA !== yrB) res = yrA - yrB;
      else {
        const secA = parseSectionRank(a.section || defaultSection.value);
        const secB = parseSectionRank(b.section || defaultSection.value);
        if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
        else if (secA.num !== secB.num) res = secA.num - secB.num;
        else res = (a.name || '').localeCompare(b.name || '');
      }
    } else if (previewSortBy.value === 'section') {
      const secA = parseSectionRank(a.section || defaultSection.value);
      const secB = parseSectionRank(b.section || defaultSection.value);
      if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
      else if (secA.num !== secB.num) res = secA.num - secB.num;
      else {
        const yrA = parseYearLevelRank(a.yearLevel || defaultYearLevel.value);
        const yrB = parseYearLevelRank(b.yearLevel || defaultYearLevel.value);
        if (yrA !== yrB) res = yrA - yrB;
        else res = (a.name || '').localeCompare(b.name || '');
      }
    } else if (previewSortBy.value === 'name') {
      res = (a.name || '').localeCompare(b.name || '');
    } else if (previewSortBy.value === 'studentId') {
      res = (a.studentId || '').localeCompare(b.studentId || '', undefined, { numeric: true });
    } else if (previewSortBy.value === 'course') {
      res = (a.course || defaultCourse.value).localeCompare(b.course || defaultCourse.value);
    }
    return previewSortDir.value === 'asc' ? res : -res;
  });
});

const filteredEnrolledStudents = computed(() => {
  let list = allStudents.value;

  if (rosterYearFilter.value !== 'all') {
    list = list.filter(s => s.yearLevel === rosterYearFilter.value);
  }

  if (rosterSectionFilter.value !== 'all') {
    list = list.filter(s => s.section === rosterSectionFilter.value);
  }

  if (rosterSearch.value.trim()) {
    const q = rosterSearch.value.toLowerCase().trim();
    list = list.filter(s => {
      const dept = s.department || getDepartmentFromCourse(s.course);
      return (
        (s.name || '').toLowerCase().includes(q) ||
        (s.studentId || '').toLowerCase().includes(q) ||
        (s.course || '').toLowerCase().includes(q) ||
        dept.toLowerCase().includes(q) ||
        (s.section || '').toLowerCase().includes(q) ||
        (s.email || '').toLowerCase().includes(q)
      );
    });
  }

  return [...list].sort((a, b) => {
    let res = 0;
    if (rosterSortBy.value === 'department') {
      const deptA = a.department || getDepartmentFromCourse(a.course);
      const deptB = b.department || getDepartmentFromCourse(b.course);
      if (deptA !== deptB) res = deptA.localeCompare(deptB);
      else {
        const yrA = parseYearLevelRank(a.yearLevel);
        const yrB = parseYearLevelRank(b.yearLevel);
        if (yrA !== yrB) res = yrA - yrB;
        else {
          const secA = parseSectionRank(a.section);
          const secB = parseSectionRank(b.section);
          if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
          else if (secA.num !== secB.num) res = secA.num - secB.num;
          else res = (a.name || '').localeCompare(b.name || '');
        }
      }
    } else if (rosterSortBy.value === 'yearLevel') {
      const yrA = parseYearLevelRank(a.yearLevel);
      const yrB = parseYearLevelRank(b.yearLevel);
      if (yrA !== yrB) res = yrA - yrB;
      else {
        const secA = parseSectionRank(a.section);
        const secB = parseSectionRank(b.section);
        if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
        else if (secA.num !== secB.num) res = secA.num - secB.num;
        else res = (a.name || '').localeCompare(b.name || '');
      }
    } else if (rosterSortBy.value === 'section') {
      const secA = parseSectionRank(a.section);
      const secB = parseSectionRank(b.section);
      if (secA.shift !== secB.shift) res = secA.shift - secB.shift;
      else if (secA.num !== secB.num) res = secA.num - secB.num;
      else {
        const yrA = parseYearLevelRank(a.yearLevel);
        const yrB = parseYearLevelRank(b.yearLevel);
        if (yrA !== yrB) res = yrA - yrB;
        else res = (a.name || '').localeCompare(b.name || '');
      }
    } else if (rosterSortBy.value === 'name') {
      const lastA = getLastName(a);
      const lastB = getLastName(b);
      const lastCmp = lastA.localeCompare(lastB);
      if (lastCmp !== 0) res = lastCmp;
      else res = getFirstName(a).localeCompare(getFirstName(b));
    } else if (rosterSortBy.value === 'studentId') {
      res = (a.studentId || '').localeCompare(b.studentId || '', undefined, { numeric: true });
    } else if (rosterSortBy.value === 'course') {
      res = (a.course || '').localeCompare(b.course || '');
    }
    return rosterSortDir.value === 'asc' ? res : -res;
  });
});

const triggerFileInput = () => {
  fileInputRef.value?.click();
};

const clearLoadedFile = () => {
  previewRecords.value = [];
  parsedFileName.value = '';
  errorMessage.value = '';
  successMessage.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
};

const handleFileInputChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files[0]) {
    parseFile(target.files[0]);
  }
};

const handleFileDrop = (e: DragEvent) => {
  isDragging.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files[0]) {
    parseFile(e.dataTransfer.files[0]);
  }
};

const parseFile = (file: File) => {
  errorMessage.value = '';
  successMessage.value = '';
  previewRecords.value = [];

  const ext = file.name.split('.').pop()?.toLowerCase();
  const validExtensions = ['xlsx', 'xls', 'csv'];

  if (!ext || !validExtensions.includes(ext)) {
    errorMessage.value = `Access Denied: "${file.name}" is not an Excel file. Only Excel spreadsheets (.xlsx, .xls) and CSV (.csv) files are permitted for student roster import.`;
    parsedFileName.value = '';
    if (fileInputRef.value) fileInputRef.value.value = '';
    return;
  }

  parsedFileName.value = file.name;
  const reader = new FileReader();

  reader.onload = async (evt) => {
    try {
      const XLSX = await import('xlsx');
      const data = evt.target?.result;
      const workbook = XLSX.read(data, { type: 'binary' });
      const firstSheetName = workbook.SheetNames[0];
      if (!firstSheetName) {
        errorMessage.value = 'The uploaded Excel file contains no worksheets.';
        return;
      }

      const sheet = workbook.Sheets[firstSheetName];
      const json: any[] = XLSX.utils.sheet_to_json(sheet, { defval: '' });

      if (!json || json.length === 0) {
        errorMessage.value = 'The uploaded Excel spreadsheet contains no data rows.';
        return;
      }

      const parsed: ParsedStudentItem[] = [];

      json.forEach((row, idx) => {
        let rawName = '';
        let rawId = '';
        let rawCourse = '';
        let rawDept = '';
        let rawYear = '';
        let rawSection = '';
        let rawEmail = '';

        Object.keys(row).forEach((k) => {
          const keyLower = k.trim().toLowerCase();
          const val = String(row[k] || '').trim();

          if (
            keyLower.includes('name') ||
            keyLower.includes('student name') ||
            keyLower.includes('full name') ||
            keyLower.includes('attendee') ||
            keyLower === 'student'
          ) {
            if (!rawName) rawName = val;
          } else if (
            keyLower.includes('id') ||
            keyLower.includes('number') ||
            keyLower.includes('student no') ||
            keyLower.includes('stud no') ||
            keyLower === 'id' ||
            keyLower === 'no' ||
            keyLower === 'student_id'
          ) {
            if (!rawId) rawId = val;
          } else if (keyLower.includes('course') || keyLower.includes('program') || keyLower.includes('degree')) {
            if (!rawCourse) rawCourse = val;
          } else if (keyLower.includes('dept') || keyLower.includes('department') || keyLower.includes('college')) {
            if (!rawDept) rawDept = val;
          } else if (keyLower.includes('year') || keyLower.includes('level')) {
            if (!rawYear) rawYear = val;
          } else if (keyLower.includes('section') || keyLower.includes('sec')) {
            if (!rawSection) rawSection = val;
          } else if (keyLower.includes('email') || keyLower.includes('mail')) {
            if (!rawEmail) rawEmail = val;
          }
        });

        // Fallback for unlabeled columns
        if (!rawName && !rawId) {
          const values = Object.values(row).map(v => String(v).trim()).filter(Boolean);
          if (values.length >= 2) {
            if (/^[0-9A-Za-z-]+$/.test(values[0]) && values[0].length >= 4) {
              rawId = values[0];
              rawName = values[1];
            } else {
              rawName = values[0];
              rawId = values[1];
            }
            if (values[2]) rawCourse = values[2];
            if (values[3]) rawSection = values[3];
          } else if (values.length === 1) {
            rawName = values[0];
            rawId = `2024-${String(1000 + idx)}`;
          }
        }

        if (rawName || rawId) {
          const effectiveCourse = rawCourse || defaultCourse.value;
          parsed.push({
            name: rawName || `Student ${rawId}`,
            studentId: rawId || `2024-${String(1000 + idx)}`,
            course: effectiveCourse,
            department: rawDept || getDepartmentFromCourse(effectiveCourse),
            yearLevel: rawYear || defaultYearLevel.value,
            section: rawSection || defaultSection.value,
            email: rawEmail,
          });
        }
      });

      if (parsed.length === 0) {
        errorMessage.value = 'Could not detect student names or numbers in the Excel sheet. Please verify column headers or use our sample template.';
        return;
      }

      previewRecords.value = parsed;
      successMessage.value = `Successfully parsed ${parsed.length} student records from "${file.name}". Click "Confirm & Import" below to commit.`;
    } catch (err: any) {
      errorMessage.value = `Failed to parse Excel file: ${err?.message || 'Invalid or corrupted spreadsheet format.'}`;
    }
  };

  reader.readAsBinaryString(file);
};

const handleDownloadTemplate = () => {
  downloadStudentImportTemplate();
};

const removePreviewRecord = (idx: number) => {
  previewRecords.value.splice(idx, 1);
};

const handleConfirmImport = () => {
  if (previewRecords.value.length === 0) return;
  isProcessing.value = true;
  errorMessage.value = '';

  try {
    const totalToImport = previewRecords.value.length;
    const res = importStudents(previewRecords.value);
    isProcessing.value = false;
    successMessage.value = res.message;
    
    showToast(
      'Roster Import Successful!',
      res.message,
      'success'
    );

    previewRecords.value = [];
    parsedFileName.value = '';
    if (fileInputRef.value) fileInputRef.value.value = '';
  } catch (err: any) {
    isProcessing.value = false;
    const errText = `Error importing records: ${err?.message || 'Unknown error'}`;
    errorMessage.value = errText;
    showToast('Import Failed', errText, 'error');
  }
};
</script>
