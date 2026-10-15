<template>
  <Modal
    :is-open="isOpen"
    @close="handleClose"
    title="Import Class List & Generate Attendance Sheet"
    subtitle="Upload an Excel spreadsheet (.xlsx, .xls, .csv) to verify attendance against official event check-in records"
    max-width="5xl"
  >
    <div class="space-y-5 print:hidden">
      <!-- 1. Header Information Context -->
      <div class="p-4 rounded-2xl bg-blue-50/80 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/80 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-3">
          <div class="w-10 h-10 rounded-xl bg-blue-600 text-white flex items-center justify-center shrink-0 shadow-sm">
            <CalendarDays class="w-5 h-5" />
          </div>
          <div>
            <span class="text-[10px] font-black uppercase tracking-wider text-blue-600 dark:text-blue-400 block">
              Event Monitoring Target
            </span>
            <p class="text-sm font-extrabold text-slate-900 dark:text-white">
              {{ targetEvent?.title || 'No Active Event Selected' }}
            </p>
            <p class="text-[11px] text-slate-500 dark:text-slate-400">
              {{ targetEvent?.date ? targetEvent.date : 'Upcoming' }} • {{ targetEvent?.venue || 'Campus Venue' }}
              <span v-if="targetEvent?.academicYear"> • A.Y. {{ targetEvent.academicYear }} ({{ targetEvent.semester || '1st Sem' }})</span>
            </p>
          </div>
        </div>

        <div class="flex items-center gap-2 self-end sm:self-auto flex-wrap">
          <div class="px-3 py-1.5 rounded-xl bg-white dark:bg-[#1a1a1a] border border-blue-200 dark:border-blue-900 text-right">
            <span class="text-[9px] font-bold text-slate-400 uppercase block">Active Class Scope</span>
            <span class="text-xs font-black text-blue-700 dark:text-blue-300">{{ classLabel }}</span>
          </div>

          <button
            type="button"
            @click="handleDownloadTemplate"
            class="flex items-center gap-1.5 px-3 py-2 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-xs hover:shadow-md transition cursor-pointer"
            title="Download an official Excel template (.xlsx) with the required column headers"
          >
            <Download class="w-3.5 h-3.5" />
            <span>Sample Template (.xlsx)</span>
          </button>
        </div>
      </div>

      <!-- 2. File Upload Dropzone (When no file is uploaded or to replace file) -->
      <div v-if="rawImportRows.length === 0" class="space-y-4">
        <div
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleFileDrop"
          @click="triggerFileInput"
          class="border-2 border-dashed rounded-3xl p-8 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-3"
          :class="isDragging
            ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/30 ring-4 ring-emerald-500/20'
            : 'border-slate-300 dark:border-[#3a3a3a] bg-slate-50/60 dark:bg-[#1a1a1a] hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/20'"
        >
          <input
            ref="fileInputRef"
            type="file"
            accept=".xlsx,.xls,.csv,application/vnd.openxmlformats-officedocument.spreadsheetml.sheet,application/vnd.ms-excel,text/csv"
            class="hidden"
            @change="handleFileInputChange"
          />
          <div class="w-14 h-14 rounded-2xl bg-emerald-100 dark:bg-emerald-500/20 text-emerald-600 dark:text-emerald-400 flex items-center justify-center shadow-inner">
            <FileSpreadsheet class="w-7 h-7" />
          </div>
          <div>
            <p class="text-sm font-bold text-slate-800 dark:text-slate-200">
              Click to browse or drag & drop class list spreadsheet
            </p>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-1">
              Supports <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">.xlsx, .xls, .csv</strong> files (Max 10 MB)
            </p>
          </div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200/80 dark:bg-[#282828] text-slate-600 dark:text-slate-300 text-[11px] font-medium">
            <Upload class="w-3.5 h-3.5 text-slate-400" />
            <span>Required Column: <strong>Student ID</strong> (e.g. 2024-58566) • Optional: Name, Course, Year, Section</span>
          </div>
        </div>

        <!-- Error Message on File Ingestion -->
        <div v-if="fileError" class="p-4 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-900/60 flex items-start gap-3 text-xs text-rose-700 dark:text-rose-300">
          <AlertCircle class="w-5 h-5 shrink-0 text-rose-500 mt-0.5" />
          <div class="space-y-0.5">
            <strong class="font-bold block">Spreadsheet Validation Error</strong>
            <span>{{ fileError }}</span>
          </div>
        </div>
      </div>

      <!-- 3. Validation & Reconciliation Preview (When file is parsed) -->
      <div v-else class="space-y-4">
        <!-- File Name & Quick Stats Bar -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 p-3.5 bg-slate-100 dark:bg-[#242424] rounded-2xl border border-slate-200 dark:border-[#383838]">
          <div class="flex items-center gap-2.5">
            <FileSpreadsheet class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
            <span class="text-xs font-mono font-bold text-slate-900 dark:text-white">{{ uploadedFileName }}</span>
            <span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-slate-200 dark:bg-[#333] text-slate-700 dark:text-slate-300">
              {{ rawImportRows.length }} rows parsed
            </span>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="clearFile"
              class="px-2.5 py-1 text-xs font-semibold text-slate-600 dark:text-slate-300 hover:text-rose-600 dark:hover:text-rose-400 rounded-lg hover:bg-slate-200/60 dark:hover:bg-[#333] transition cursor-pointer"
            >
              Upload Different File
            </button>
          </div>
        </div>

        <!-- Validation & Attendance Metric Cards -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
          <!-- Present Count -->
          <div class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/30 border border-emerald-200 dark:border-emerald-900/60 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-extrabold uppercase text-emerald-800 dark:text-emerald-300">Present</span>
              <CheckCircle2 class="w-4 h-4 text-emerald-600" />
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
              <span class="text-2xl font-black text-emerald-700 dark:text-emerald-400">{{ metrics.present }}</span>
              <span class="text-[11px] text-emerald-600/80 font-semibold">/ {{ metrics.total }}</span>
            </div>
            <span class="text-[10px] text-emerald-700 dark:text-emerald-400 mt-0.5 font-medium">
              {{ metrics.total > 0 ? Math.round((metrics.present / metrics.total) * 100) : 0 }}% Attendance Rate
            </span>
          </div>

          <!-- Absent / Not Entered -->
          <div class="p-3.5 rounded-2xl bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333] flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-extrabold uppercase text-slate-600 dark:text-slate-400">
                {{ isEventClosed ? 'Absent' : 'Not Yet Entered' }}
              </span>
              <Clock class="w-4 h-4 text-slate-400" />
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
              <span class="text-2xl font-black text-slate-800 dark:text-slate-200">{{ metrics.absent }}</span>
              <span class="text-[11px] text-slate-400 font-semibold">students</span>
            </div>
            <span class="text-[10px] text-slate-500 mt-0.5 font-medium">No verified scan logged</span>
          </div>

          <!-- Unmatched / Not in Roster -->
          <div class="p-3.5 rounded-2xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-900/60 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-extrabold uppercase text-amber-800 dark:text-amber-300">Not in Roster</span>
              <HelpCircle class="w-4 h-4 text-amber-600" />
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
              <span class="text-2xl font-black text-amber-700 dark:text-amber-400">{{ metrics.unmatched }}</span>
              <span class="text-[11px] text-amber-600/80 font-semibold">unmatched</span>
            </div>
            <span class="text-[10px] text-amber-700 dark:text-amber-400 mt-0.5 font-medium">ID missing in school roster</span>
          </div>

          <!-- Spreadsheet Errors / Duplicates -->
          <div class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/30 border border-rose-200 dark:border-rose-900/60 flex flex-col justify-between">
            <div class="flex items-center justify-between">
              <span class="text-[10px] font-extrabold uppercase text-rose-800 dark:text-rose-300">File Warnings</span>
              <AlertTriangle class="w-4 h-4 text-rose-600" />
            </div>
            <div class="mt-2 flex items-baseline gap-1.5">
              <span class="text-2xl font-black text-rose-700 dark:text-rose-400">{{ metrics.invalid + metrics.duplicates }}</span>
              <span class="text-[11px] text-rose-600/80 font-semibold">rows</span>
            </div>
            <span class="text-[10px] text-rose-700 dark:text-rose-400 mt-0.5 font-medium">
              {{ metrics.duplicates > 0 ? `${metrics.duplicates} duplicates detected` : 'Blank / malformed IDs' }}
            </span>
          </div>
        </div>

        <!-- Scope Warning Banner (If Any Students Fall Outside Teacher's Assigned Section Scope) -->
        <div v-if="metrics.unauthorized > 0" class="p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-xl flex items-center gap-2 text-xs text-amber-800 dark:text-amber-300 font-semibold">
          <AlertCircle class="w-4 h-4 shrink-0 text-amber-600" />
          <span>{{ metrics.unauthorized }} student(s) in this class list belong to a different section or course than your authorized class scope. These are flagged for your review.</span>
        </div>

        <!-- Controls: Filter Tabs, Sort Options, Search Input -->
        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
          <!-- Filter Tabs -->
          <div class="flex items-center gap-1 bg-slate-100 dark:bg-[#252525] p-1 rounded-xl border border-slate-200 dark:border-[#383838] overflow-x-auto">
            <button
              type="button"
              @click="activeFilter = 'all'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeFilter === 'all' ? 'bg-white dark:bg-[#333] text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
            >
              All ({{ reconciledList.length }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'present'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeFilter === 'present' ? 'bg-white dark:bg-[#333] text-emerald-700 dark:text-emerald-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-emerald-600'"
            >
              Present ({{ metrics.present }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'absent'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeFilter === 'absent' ? 'bg-white dark:bg-[#333] text-slate-900 dark:text-white shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-slate-900'"
            >
              Absent ({{ metrics.absent }})
            </button>
            <button
              type="button"
              @click="activeFilter = 'flagged'"
              class="px-3 py-1.5 rounded-lg text-xs font-bold transition cursor-pointer"
              :class="activeFilter === 'flagged' ? 'bg-white dark:bg-[#333] text-rose-700 dark:text-rose-400 shadow-xs' : 'text-slate-600 dark:text-slate-400 hover:text-rose-600'"
            >
              Warnings ({{ metrics.unmatched + metrics.invalid + metrics.duplicates + metrics.unauthorized }})
            </button>
          </div>

          <!-- Sorting & Search -->
          <div class="flex items-center gap-2">
            <!-- Sort Selector -->
            <div class="flex items-center gap-1.5">
              <ArrowUpDown class="w-3.5 h-3.5 text-slate-400 shrink-0" />
              <select
                v-model="sortCriteria"
                class="px-2.5 py-1.5 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#383838] rounded-xl text-xs font-bold text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500 cursor-pointer"
              >
                <option value="name_asc">Student Name (A–Z)</option>
                <option value="name_desc">Student Name (Z–A)</option>
                <option value="id_asc">Student ID (Lowest first)</option>
                <option value="status_present">Present Students First</option>
                <option value="status_absent">Not Yet Entered / Absent First</option>
              </select>
            </div>

            <!-- Search Field -->
            <div class="relative min-w-[160px]">
              <Search class="w-3.5 h-3.5 absolute left-3 top-1/2 -translate-y-1/2 text-slate-400" />
              <input
                type="text"
                v-model="searchQuery"
                placeholder="Search student or ID..."
                class="w-full pl-8 pr-3 py-1.5 bg-white dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#383838] rounded-xl text-xs font-medium text-slate-900 dark:text-white placeholder:text-slate-400 focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>
        </div>

        <!-- Reconciled Attendance Sheet Table -->
        <div class="max-h-[360px] overflow-y-auto rounded-2xl border border-slate-200 dark:border-[#333333] bg-white dark:bg-[#1a1a1a]">
          <table class="w-full text-left text-xs">
            <thead class="sticky top-0 bg-slate-50 dark:bg-[#242424] text-slate-500 dark:text-[#a0a0a0] text-[10px] font-black uppercase tracking-wider border-b border-slate-200 dark:border-[#333333] shadow-xs">
              <tr>
                <th class="px-3 py-2.5 w-10 text-center">#</th>
                <th class="px-3 py-2.5">Student ID</th>
                <th class="px-3.5 py-2.5">Student Full Name</th>
                <th class="px-3 py-2.5">Course & Section</th>
                <th class="px-3 py-2.5">Attendance Status</th>
                <th class="px-3 py-2.5">Time In (PHT)</th>
                <th class="px-3 py-2.5">Roster Status</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
              <tr
                v-for="(row, idx) in displayedList"
                :key="row.studentId + '-' + idx"
                class="hover:bg-slate-50 dark:hover:bg-[#222222] transition"
                :class="{
                  'bg-rose-50/30 dark:bg-rose-950/20': row.errors.length > 0,
                  'bg-amber-50/20 dark:bg-amber-950/10': !row.isAuthorized && row.errors.length === 0,
                }"
              >
                <!-- Sequential Row Number for Printing/Reporting -->
                <td class="px-3 py-2 font-mono text-center text-slate-400 font-bold">
                  {{ idx + 1 }}
                </td>

                <!-- Student ID -->
                <td class="px-3 py-2 font-mono font-bold whitespace-nowrap" :class="row.studentId === 'N/A' || row.isDuplicate ? 'text-rose-600 dark:text-rose-400' : 'text-blue-600 dark:text-blue-400'">
                  {{ row.studentId }}
                  <span v-if="row.isDuplicate" class="text-[9px] uppercase px-1 py-0.2 rounded bg-rose-100 text-rose-700 ml-1">Duplicate</span>
                </td>

                <!-- Student Full Name -->
                <td class="px-3.5 py-2 font-bold text-slate-900 dark:text-white whitespace-nowrap">
                  {{ row.name }}
                </td>

                <!-- Course & Section -->
                <td class="px-3 py-2 text-slate-700 dark:text-slate-300 whitespace-nowrap">
                  {{ row.course }} — {{ row.section }}
                  <span v-if="row.yearLevel && row.yearLevel !== 'N/A'" class="text-[10px] text-slate-400"> ({{ row.yearLevel }})</span>
                </td>

                <!-- Attendance Status -->
                <td class="px-3 py-2 whitespace-nowrap">
                  <span
                    v-if="row.isPresent"
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-100 dark:bg-emerald-950/80 text-emerald-800 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800"
                  >
                    <CheckCircle2 class="w-3 h-3 text-emerald-600 dark:text-emerald-400" />
                    <span>PRESENT</span>
                  </span>
                  <span
                    v-else-if="row.attendanceStatus === 'not_found'"
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-100 dark:bg-amber-950/80 text-amber-800 dark:text-amber-300 border border-amber-300 dark:border-amber-800"
                  >
                    <HelpCircle class="w-3 h-3 text-amber-600 dark:text-amber-400" />
                    <span>NOT IN ROSTER</span>
                  </span>
                  <span
                    v-else-if="row.attendanceStatus === 'absent'"
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-100 dark:bg-rose-950/80 text-rose-800 dark:text-rose-300 border border-rose-300 dark:border-rose-800"
                  >
                    <XCircle class="w-3 h-3 text-rose-600 dark:text-rose-400" />
                    <span>ABSENT</span>
                  </span>
                  <span
                    v-else
                    class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-100 dark:bg-[#333] text-slate-700 dark:text-slate-300 border border-slate-300 dark:border-[#444]"
                  >
                    <Clock class="w-3 h-3 text-slate-400" />
                    <span>NOT ENTERED</span>
                  </span>
                </td>

                <!-- Time In (PHT) -->
                <td class="px-3 py-2 font-mono font-bold whitespace-nowrap" :class="row.isPresent ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-400'">
                  {{ row.timeIn || '—' }}
                </td>

                <!-- Roster & Scope Status -->
                <td class="px-3 py-2 text-[11px] whitespace-nowrap">
                  <div class="flex items-center gap-1.5 flex-wrap">
                    <span v-if="row.inRoster" class="text-emerald-600 dark:text-emerald-400 font-semibold flex items-center gap-0.5">
                      <Check class="w-3 h-3" />
                      <span>Roster Match</span>
                    </span>
                    <span v-else class="text-amber-600 dark:text-amber-400 font-semibold">
                      Unregistered
                    </span>

                    <span v-if="!row.isAuthorized" class="text-rose-600 dark:text-rose-400 font-bold px-1.5 py-0.2 rounded bg-rose-100 dark:bg-rose-950/80">
                      Out of Scope
                    </span>
                  </div>
                </td>
              </tr>

              <tr v-if="displayedList.length === 0">
                <td colspan="7" class="text-center py-10 text-slate-400 dark:text-slate-500">
                  <Users class="w-7 h-7 mx-auto mb-1.5 opacity-40" />
                  <p class="font-bold text-xs text-slate-700 dark:text-slate-300">No matching student records</p>
                  <p class="text-[10px] text-slate-500">Try adjusting your search query or filter tab.</p>
                </td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="p-3 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333] rounded-xl flex items-center justify-between text-xs text-slate-500 dark:text-slate-400 font-medium">
          <div class="flex items-center gap-1.5">
            <ShieldCheck class="w-4 h-4 text-emerald-500" />
            <span>Read-only report: Uploading this spreadsheet does not modify student rosters, user accounts, or scan logs.</span>
          </div>
          <span class="font-mono text-[11px]">Formula Injection Protected</span>
        </div>
      </div>

      <!-- Action Footer -->
      <div class="flex flex-col-reverse sm:flex-row sm:items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-[#333333]">
        <button
          type="button"
          @click="handleClose"
          class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828] transition cursor-pointer"
        >
          Close
        </button>

        <div v-if="reconciledList.length > 0" class="flex items-center gap-2.5 flex-wrap">
          <!-- Print Report Button (Ctrl + P) -->
          <button
            type="button"
            @click="triggerPrint"
            class="flex items-center gap-1.5 px-4 py-2.5 bg-slate-800 hover:bg-slate-900 text-white rounded-xl text-xs font-bold shadow-xs hover:shadow-md transition cursor-pointer"
            title="Open browser print dialog for an official printed attendance sheet"
          >
            <Printer class="w-4 h-4" />
            <span>Print Attendance Sheet (Ctrl+P)</span>
          </button>

          <!-- Export Reconciled Excel Button -->
          <button
            type="button"
            @click="handleExportReconciledExcel"
            class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer"
            title="Download sorted attendance reconciliation sheet as an Excel spreadsheet (.xlsx)"
          >
            <Download class="w-4 h-4" />
            <span>Export Report (.xlsx)</span>
          </button>
        </div>
      </div>
    </div>

    <!-- ==================== DEDICATED PRINT VIEW (VISIBLE ONLY WHEN PRINTING) ==================== -->
    <div class="hidden print:block text-black p-4 space-y-4 font-sans">
      <!-- Institutional Print Header -->
      <div class="border-b-2 border-black pb-3 text-center space-y-1">
        <h2 class="text-lg font-black uppercase tracking-wider">National College of Science and Technology</h2>
        <p class="text-xs uppercase font-bold tracking-widest text-gray-700">Official Class Attendance Reconciliation Report</p>
        <div class="text-[11px] grid grid-cols-2 text-left pt-2 border-t border-gray-300 mt-2">
          <div>
            <p><strong>Event:</strong> {{ targetEvent?.title || 'Event Attendance' }}</p>
            <p><strong>Date & Venue:</strong> {{ targetEvent?.date }} • {{ targetEvent?.venue || 'Campus' }}</p>
            <p><strong>Academic Period:</strong> A.Y. {{ targetEvent?.academicYear || '2026-2027' }} {{ targetEvent?.semester || '1st Semester' }}</p>
          </div>
          <div class="text-right">
            <p><strong>Faculty:</strong> {{ professorName }}</p>
            <p><strong>Class Scope:</strong> {{ classLabel }}</p>
            <p><strong>Generated Date:</strong> {{ currentFormattedDate }}</p>
          </div>
        </div>
      </div>

      <!-- Print Summary Stats -->
      <div class="flex items-center justify-between text-xs py-1 border-b border-gray-300">
        <span><strong>Total Students in Class List:</strong> {{ metrics.total }}</span>
        <span><strong>Present:</strong> {{ metrics.present }} ({{ metrics.total > 0 ? Math.round((metrics.present / metrics.total) * 100) : 0 }}%)</span>
        <span><strong>Absent / Not Entered:</strong> {{ metrics.absent }}</span>
        <span><strong>Unregistered / Not in Roster:</strong> {{ metrics.unmatched }}</span>
      </div>

      <!-- Print Table with Repeated Headers -->
      <table class="w-full text-left text-[11px] border-collapse border border-black">
        <thead>
          <tr class="bg-gray-100 border-b border-black text-[10px] uppercase font-bold">
            <th class="p-1.5 border border-black text-center w-8">#</th>
            <th class="p-1.5 border border-black w-24">Student ID</th>
            <th class="p-1.5 border border-black">Student Full Name</th>
            <th class="p-1.5 border border-black w-28">Course & Section</th>
            <th class="p-1.5 border border-black w-24 text-center">Status</th>
            <th class="p-1.5 border border-black w-20 text-center">Time In</th>
            <th class="p-1.5 border border-black w-28">Remarks</th>
          </tr>
        </thead>
        <tbody>
          <tr v-for="(row, idx) in displayedList" :key="idx" class="border-b border-gray-300">
            <td class="p-1.5 border border-black text-center font-mono font-bold">{{ idx + 1 }}</td>
            <td class="p-1.5 border border-black font-mono font-bold">{{ row.studentId }}</td>
            <td class="p-1.5 border border-black font-bold">{{ row.name }}</td>
            <td class="p-1.5 border border-black">{{ row.course }} — {{ row.section }}</td>
            <td class="p-1.5 border border-black text-center font-black uppercase text-[10px]">
              {{ row.isPresent ? 'PRESENT' : (row.attendanceStatus === 'not_found' ? 'NOT FOUND' : (row.attendanceStatus === 'absent' ? 'ABSENT' : 'NOT ENTERED')) }}
            </td>
            <td class="p-1.5 border border-black text-center font-mono">{{ row.timeIn || '—' }}</td>
            <td class="p-1.5 border border-black text-[10px] text-gray-700">
              {{ row.isPresent ? 'Verified scan' : (row.attendanceStatus === 'not_found' ? 'Not in school roster' : (!row.isAuthorized ? 'Outside section scope' : 'No entrance scan')) }}
            </td>
          </tr>
        </tbody>
      </table>

      <!-- Signatures Footer -->
      <div class="pt-8 grid grid-cols-2 gap-12 text-xs">
        <div>
          <div class="border-b border-black w-48 mb-1"></div>
          <p class="font-bold">{{ professorName }}</p>
          <p class="text-gray-600 text-[10px]">Faculty / Professor In-Charge</p>
        </div>
        <div class="text-right flex flex-col items-end">
          <div class="border-b border-black w-48 mb-1"></div>
          <p class="font-bold">Academic Department Head</p>
          <p class="text-gray-600 text-[10px]">College of Computer Studies</p>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  FileSpreadsheet, Upload, Download, Printer, CheckCircle2,
  AlertCircle, AlertTriangle, Clock, HelpCircle, XCircle,
  CalendarDays, ArrowUpDown, Search, ShieldCheck, Check, Users
} from 'lucide-vue-next';
import Modal from '../common/Modal.vue';
import { useAppStore } from '../../composables/useAppStore';
import { EventItem, StudentRecord, User, Ticket } from '../../types';
import { matchTeacherClassAssignment } from '../../utils/academicMasterData';
import { formatPhilippineTime } from '../../utils/philippineTime';
import {
  downloadClassListTemplate,
  exportReconciledClassAttendanceToExcel,
  ReconciledAttendanceExportItem,
  sanitizeFormulaInjection
} from '../../utils/excelExport';
import { apiUrl } from '../../utils/apiUrl';

const props = defineProps<{
  isOpen: boolean;
  targetEvent: EventItem | null;
  classLabel?: string;
  selectedClassKey?: string;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { currentUser, studentRecords, users, tickets } = useAppStore();

const getAuthHeaders = (): Record<string, string> => {
  const headers: Record<string, string> = {
    'Content-Type': 'application/json',
  };
  const token = localStorage.getItem('integ2_vue_session_token_v10') || (currentUser.value ? `sess_${currentUser.value.role}_${currentUser.value.id}` : '');
  if (token) {
    headers['Authorization'] = `Bearer ${token}`;
    headers['x-session-token'] = token;
  }
  if (currentUser.value?.id) {
    headers['x-user-id'] = currentUser.value.id;
  }
  return headers;
};

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const uploadedFileName = ref('');
const fileError = ref('');

// Raw parsed rows from the spreadsheet
interface RawImportRow {
  studentId: string;
  name: string;
  course: string;
  yearLevel: string;
  section: string;
}
const rawImportRows = ref<RawImportRow[]>([]);

// Reconciled Row
interface ReconciledRow extends ReconciledAttendanceExportItem {
  errors: string[];
  isDuplicate: boolean;
}
const reconciledList = ref<ReconciledRow[]>([]);

// Filters & Sorting state
const activeFilter = ref<'all' | 'present' | 'absent' | 'flagged'>('all');
const sortCriteria = ref<'name_asc' | 'name_desc' | 'id_asc' | 'id_desc' | 'status_present' | 'status_absent'>('name_asc');
const searchQuery = ref('');

const professorName = computed(() => currentUser.value?.name || 'Faculty Professor');
const classLabel = computed(() => props.classLabel || 'All Assigned Classes');

const currentFormattedDate = computed(() => {
  return new Date().toLocaleDateString('en-US', {
    year: 'numeric',
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit'
  });
});

const isEventClosed = computed(() => {
  if (!props.targetEvent) return false;
  const s = (props.targetEvent.status || '').toLowerCase();
  return s === 'completed' || s === 'archived' || s === 'cancelled';
});

// Summary Metrics
const metrics = computed(() => {
  let present = 0;
  let absent = 0;
  let notEntered = 0;
  let unmatched = 0;
  let unauthorized = 0;
  let duplicates = 0;
  let invalid = 0;

  reconciledList.value.forEach(row => {
    if (row.isDuplicate) duplicates++;
    if (row.errors.some(e => e.includes('Missing Student ID'))) invalid++;
    if (!row.isAuthorized) unauthorized++;

    if (row.isPresent) {
      present++;
    } else if (row.attendanceStatus === 'not_found') {
      unmatched++;
    } else if (isEventClosed.value) {
      absent++;
    } else {
      notEntered++;
    }
  });

  return {
    total: reconciledList.value.length,
    present,
    absent: isEventClosed.value ? absent : notEntered,
    unmatched,
    unauthorized,
    duplicates,
    invalid,
  };
});

// Filtered & Sorted Display List
const displayedList = computed(() => {
  let list = [...reconciledList.value];

  // 1. Status Filter
  if (activeFilter.value === 'present') {
    list = list.filter(r => r.isPresent);
  } else if (activeFilter.value === 'absent') {
    list = list.filter(r => !r.isPresent && r.attendanceStatus !== 'not_found');
  } else if (activeFilter.value === 'flagged') {
    list = list.filter(r => !r.isAuthorized || !r.inRoster || r.errors.length > 0);
  }

  // 2. Search Query
  if (searchQuery.value.trim()) {
    const q = searchQuery.value.toLowerCase().trim();
    list = list.filter(r =>
      (r.name && r.name.toLowerCase().includes(q)) ||
      (r.studentId && r.studentId.toLowerCase().includes(q)) ||
      (r.section && r.section.toLowerCase().includes(q))
    );
  }

  // 3. Sorting
  list.sort((a, b) => {
    switch (sortCriteria.value) {
      case 'name_desc':
        return b.name.localeCompare(a.name, undefined, { sensitivity: 'base' });
      case 'id_asc':
        return a.studentId.localeCompare(b.studentId, undefined, { numeric: true });
      case 'id_desc':
        return b.studentId.localeCompare(a.studentId, undefined, { numeric: true });
      case 'status_present':
        if (a.isPresent === b.isPresent) return a.name.localeCompare(b.name);
        return a.isPresent ? -1 : 1;
      case 'status_absent':
        if (a.isPresent === b.isPresent) return a.name.localeCompare(b.name);
        return a.isPresent ? 1 : -1;
      case 'name_asc':
      default:
        return a.name.localeCompare(b.name, undefined, { sensitivity: 'base' });
    }
  });

  return list;
});

const triggerFileInput = () => {
  fileInputRef.value?.click();
};

const handleFileInputChange = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (target.files && target.files.length > 0) {
    processSpreadsheetFile(target.files[0]);
  }
};

const handleFileDrop = (e: DragEvent) => {
  isDragging.value = false;
  if (e.dataTransfer?.files && e.dataTransfer.files.length > 0) {
    processSpreadsheetFile(e.dataTransfer.files[0]);
  }
};

// Process and parse spreadsheet file
const processSpreadsheetFile = async (file: File) => {
  fileError.value = '';

  // 1. File Extension Validation
  const validExtensions = ['.xlsx', '.xls', '.csv'];
  const fileName = file.name.toLowerCase();
  const isValidExt = validExtensions.some(ext => fileName.endsWith(ext));
  if (!isValidExt) {
    fileError.value = `Invalid file format: "${file.name}". Please upload a Microsoft Excel (.xlsx, .xls) or CSV file.`;
    return;
  }

  // 2. File Size Validation (10 MB maximum)
  const maxBytes = 10 * 1024 * 1024;
  if (file.size > maxBytes) {
    fileError.value = `File size (${(file.size / (1024 * 1024)).toFixed(1)} MB) exceeds maximum allowed limit of 10 MB.`;
    return;
  }

  uploadedFileName.value = file.name;

  try {
    const XLSX = await import('xlsx');
    const arrayBuffer = await file.arrayBuffer();
    const workbook = XLSX.read(arrayBuffer, { type: 'array' });

    if (!workbook.SheetNames || workbook.SheetNames.length === 0) {
      fileError.value = 'The uploaded workbook does not contain any sheets.';
      return;
    }

    const firstSheetName = workbook.SheetNames[0];
    const worksheet = workbook.Sheets[firstSheetName];
    const rawData = XLSX.utils.sheet_to_json<Record<string, any>>(worksheet, { defval: '' });

    if (!rawData || rawData.length === 0) {
      fileError.value = 'The spreadsheet contains no data rows.';
      return;
    }

    // Inspect headers
    const headers = Object.keys(rawData[0]);
    const findHeaderKey = (candidates: string[]) => {
      return headers.find(h => {
        const clean = h.trim().toLowerCase().replace(/[^a-z0-9]/g, '');
        return candidates.some(c => clean === c.toLowerCase().replace(/[^a-z0-9]/g, ''));
      });
    };

    const idKey = findHeaderKey(['student id', 'studentid', 'student_id', 'id number', 'id no', 'student no', 'id']);
    const nameKey = findHeaderKey(['student name', 'student full name', 'full name', 'name', 'student']);
    const courseKey = findHeaderKey(['course', 'program', 'degree']);
    const yearKey = findHeaderKey(['year level', 'yearlevel', 'year', 'level']);
    const sectionKey = findHeaderKey(['section', 'class section', 'sec']);

    if (!idKey && !nameKey) {
      fileError.value = 'Spreadsheet is missing required "Student ID" or "Student Name" columns. Please verify column headers or use the sample template.';
      return;
    }

    const parsed: RawImportRow[] = rawData.map(row => {
      const studentId = idKey ? String(row[idKey] || '').trim() : '';
      const name = nameKey ? String(row[nameKey] || '').trim() : '';
      const course = courseKey ? String(row[courseKey] || '').trim() : '';
      const yearLevel = yearKey ? String(row[yearKey] || '').trim() : '';
      const section = sectionKey ? String(row[sectionKey] || '').trim() : '';

      return {
        studentId,
        name,
        course,
        yearLevel,
        section,
      };
    });

    rawImportRows.value = parsed;
    await reconcileClassAttendance(parsed);
  } catch (err: any) {
    fileError.value = 'Failed to parse spreadsheet: ' + (err.message || 'File may be corrupted or password-protected.');
  }
};

// Reconcile parsed student rows against authoritative roster & tickets
const reconcileClassAttendance = async (rows: RawImportRow[]) => {
  const eventId = props.targetEvent?.id || '';

  // Try Server-Side Reconciliation if online
  if (eventId && (typeof navigator === 'undefined' || navigator.onLine)) {
    try {
      const res = await fetch(apiUrl('/api/attendance/reconcile-class-list'), {
        method: 'POST',
        headers: {
          ...getAuthHeaders(),
          'Content-Type': 'application/json',
        },
        body: JSON.stringify({
          eventId,
          students: rows,
          classScope: props.selectedClassKey,
        }),
      });

      const data = await res.json();
      if (res.ok && data.success && Array.isArray(data.results)) {
        reconciledList.value = data.results.map((r: any, idx: number) => ({
          rowNumber: idx + 1,
          studentId: r.studentId,
          name: r.name,
          course: r.course,
          yearLevel: r.yearLevel,
          section: r.section,
          isPresent: !!r.isPresent,
          attendanceStatus: r.attendanceStatus,
          timeIn: r.timeIn ? (r.timeIn.includes('T') ? formatPhilippineTime(r.timeIn) : r.timeIn) : null,
          inRoster: !!r.inRoster,
          isAuthorized: r.isAuthorized !== false,
          ticketNumber: r.ticketNumber,
          errors: r.errors || [],
          isDuplicate: !!r.isDuplicate,
        }));
        return;
      }
    } catch {
      // Fallback to local store reconciliation below
    }
  }

  // Local Authoritative Reconciliation Engine (Fallback & Instant Reactivity)
  const assigned = currentUser.value?.assignedClasses || [];
  const eventTickets = tickets.value.filter(t => t.eventId === eventId && t.ticketStatus !== 'CANCELLED');
  const tixMap = new Map<string, Ticket>();
  eventTickets.forEach(t => {
    if (t.attendeeStudentId) {
      tixMap.set(t.attendeeStudentId.replace(/\s+/g, '').toUpperCase(), t);
    }
  });

  const rosterMap = new Map<string, StudentRecord | User>();
  studentRecords.value.forEach(r => {
    if (r.studentId) rosterMap.set(r.studentId.replace(/\s+/g, '').toUpperCase(), r);
  });
  users.value.forEach(u => {
    if (u.role === 'student' && u.studentId) {
      const key = u.studentId.replace(/\s+/g, '').toUpperCase();
      if (!rosterMap.has(key)) rosterMap.set(key, u);
    }
  });

  const seenIds = new Set<string>();
  const reconciled: ReconciledRow[] = [];

  rows.forEach((row, idx) => {
    const rawId = row.studentId.trim();
    const cleanId = rawId.replace(/\s+/g, '').toUpperCase();
    const errors: string[] = [];
    let isDuplicate = false;

    if (!cleanId) {
      errors.push('Missing Student ID');
    } else if (seenIds.has(cleanId)) {
      errors.push('Duplicate Student ID in file');
      isDuplicate = true;
    } else {
      seenIds.add(cleanId);
    }

    const rosterRecord = cleanId ? rosterMap.get(cleanId) : undefined;
    const inRoster = !!rosterRecord;

    const finalName = rosterRecord?.name || row.name || 'Unnamed Student';
    const finalCourse = rosterRecord?.course || row.course || 'BSIT';
    const finalYear = rosterRecord?.yearLevel || row.yearLevel || '1st Year';
    const finalSection = rosterRecord?.section || row.section || '11M1';

    // Scope check: Does student match teacher's assigned classes?
    let isAuthorized = true;
    if (assigned.length > 0) {
      isAuthorized = assigned.some(cls => matchTeacherClassAssignment(cls, {
        course: finalCourse,
        section: finalSection,
        yearLevel: finalYear,
      }));
      if (!isAuthorized) {
        errors.push('Outside assigned class scope');
      }
    }

    // Attendance check
    const ticket = cleanId ? tixMap.get(cleanId) : undefined;
    const isPresent = ticket?.attendanceStatus === 'present';
    const timeIn = ticket?.timeIn ? formatPhilippineTime(ticket.timeIn) : null;

    let attendanceStatus = 'absent';
    if (isPresent) {
      attendanceStatus = 'present';
    } else if (!inRoster) {
      attendanceStatus = 'not_found';
    } else if (isEventClosed.value) {
      attendanceStatus = 'absent';
    } else {
      attendanceStatus = 'not_entered';
    }

    reconciled.push({
      rowNumber: idx + 1,
      studentId: cleanId || 'N/A',
      name: finalName,
      course: finalCourse,
      yearLevel: finalYear,
      section: finalSection,
      isPresent,
      attendanceStatus,
      timeIn,
      inRoster,
      isAuthorized,
      ticketNumber: ticket?.ticketNumber,
      errors,
      isDuplicate,
    });
  });

  reconciledList.value = reconciled;
};

const clearFile = () => {
  rawImportRows.value = [];
  reconciledList.value = [];
  uploadedFileName.value = '';
  fileError.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
};

const handleDownloadTemplate = () => {
  downloadClassListTemplate('NCST_Class_List_Sample.xlsx');
};

const triggerPrint = () => {
  window.print();
};

const handleExportReconciledExcel = () => {
  const safeTitle = props.targetEvent?.title.replace(/[^a-zA-Z0-9_-]/g, '_') || 'Event';
  const classSuffix = (props.classLabel || 'Class').replace(/[^a-zA-Z0-9_-]/g, '_');
  const fileName = `${safeTitle}_${classSuffix}_Attendance_Sheet.xlsx`;

  exportReconciledClassAttendanceToExcel(
    displayedList.value,
    props.targetEvent,
    props.classLabel || 'Class',
    professorName.value,
    fileName
  );
};

const handleClose = () => {
  clearFile();
  emit('close');
};

watch(() => props.isOpen, isOpen => {
  if (!isOpen) {
    clearFile();
  }
});
</script>

<style scoped>
@media print {
  @page {
    size: landscape;
    margin: 1.2cm;
  }
  thead {
    display: table-header-group;
  }
  tr {
    page-break-inside: avoid;
  }
}
</style>
