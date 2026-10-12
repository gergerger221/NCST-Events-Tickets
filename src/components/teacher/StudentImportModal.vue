<template>
  <Modal
    :is-open="isOpen"
    @close="handleClose"
    title="Import Student Roster from Excel"
    subtitle="Upload an Excel spreadsheet (.xlsx, .xls, .csv) with student names and ID numbers"
    max-width="3xl"
  >
    <div class="space-y-5">
      <!-- Excel File Dropzone -->
      <div class="space-y-3">
        <div
          @dragover.prevent="isDragging = true"
          @dragleave.prevent="isDragging = false"
          @drop.prevent="handleFileDrop"
          @click="triggerFileInput"
          class="border-2 border-dashed rounded-3xl p-8 text-center cursor-pointer transition-all flex flex-col items-center justify-center gap-3"
          :class="isDragging ? 'border-emerald-500 bg-emerald-50/60 dark:bg-emerald-950/30 ring-4 ring-emerald-500/20' : 'border-slate-300 dark:border-[#3a3a3a] bg-slate-50/60 dark:bg-[#1a1a1a] hover:border-emerald-500 dark:hover:border-emerald-500 hover:bg-emerald-50/20'"
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
              Click to browse or drag & drop Excel spreadsheet
            </p>
            <p class="text-xs text-slate-500 dark:text-[#a0a0a0] mt-1">
              Strictly accepts <strong class="text-emerald-600 dark:text-emerald-400 font-semibold">.xlsx, .xls, .csv</strong> files only
            </p>
          </div>
          <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-slate-200/80 dark:bg-[#282828] text-slate-600 dark:text-slate-300 text-[11px] font-medium">
            <Upload class="w-3.5 h-3.5 text-slate-400" />
            <span>Supported: Microsoft Excel (.xlsx, .xls) & CSV</span>
          </div>
        </div>

        <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2 pt-1">
          <button
            type="button"
            @click="handleDownloadTemplate"
            class="inline-flex items-center gap-1.5 text-xs font-bold text-emerald-600 dark:text-emerald-400 hover:text-emerald-700 dark:hover:text-emerald-300 hover:underline cursor-pointer"
          >
            <Download class="w-4 h-4" />
            <span>Download Sample Excel Template (.xlsx)</span>
          </button>
          <span v-if="parsedFileName" class="text-xs font-mono text-slate-700 dark:text-slate-300 bg-slate-100 dark:bg-[#252525] px-2.5 py-1 rounded-lg border border-slate-200 dark:border-[#333]">
            File: <strong>{{ parsedFileName }}</strong>
          </span>
        </div>
      </div>

      <!-- Batch Default Academic Settings -->
      <div class="p-4 bg-slate-50 dark:bg-[#222222] border border-slate-200 dark:border-[#333333] rounded-2xl">
        <div class="flex items-center justify-between mb-2.5">
          <span class="text-[11px] font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
            <SlidersHorizontal class="w-3.5 h-3.5 text-emerald-500" />
            Default Academic Values (Applied to rows where column is empty)
          </span>
          <span class="text-[10px] text-slate-400">Excel values take priority</span>
        </div>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
          <!-- Default Course -->
          <div>
            <label class="block text-[10px] font-bold text-slate-500 dark:text-[#a0a0a0] mb-1 uppercase">Default Course / Program</label>
            <input
              type="text"
              list="excel-import-course-options"
              v-model="defaultCourse"
              placeholder="e.g. BSIT"
              class="w-full px-3 py-1.5 bg-white dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
            <datalist id="excel-import-course-options">
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
            <label class="block text-[10px] font-bold text-slate-500 dark:text-[#a0a0a0] mb-1 uppercase">Default Year Level</label>
            <select
              v-model="defaultYearLevel"
              class="w-full px-3 py-1.5 bg-white dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500 cursor-pointer"
            >
              <option value="1st Year">1st Year</option>
              <option value="2nd Year">2nd Year</option>
              <option value="3rd Year">3rd Year</option>
              <option value="4th Year">4th Year</option>
            </select>
          </div>

          <!-- Default Section -->
          <div>
            <label class="block text-[10px] font-bold text-slate-500 dark:text-[#a0a0a0] mb-1 uppercase">Default Section</label>
            <input
              type="text"
              list="excel-import-section-options"
              v-model="defaultSection"
              placeholder="e.g. 11M1, 21M1, 31A1"
              class="w-full px-3 py-1.5 bg-white dark:bg-[#181818] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-emerald-500/20 focus:border-emerald-500"
            />
            <datalist id="excel-import-section-options">
              <option v-for="sec in availableSections" :key="sec" :value="sec">{{ sec }}</option>
            </datalist>
          </div>
        </div>
      </div>

      <!-- Error / Rejection Alert -->
      <div v-if="errorMessage" class="p-3.5 rounded-2xl bg-rose-50 dark:bg-rose-950/40 border border-rose-200 dark:border-rose-900/50 flex items-start gap-2.5 text-xs text-rose-700 dark:text-rose-300">
        <AlertCircle class="w-4 h-4 shrink-0 text-rose-500 mt-0.5" />
        <div>
          <strong class="font-bold block">File Upload Error</strong>
          <span>{{ errorMessage }}</span>
        </div>
      </div>

      <!-- Success Notification Alert -->
      <div v-if="successMessage" class="p-3.5 rounded-2xl bg-emerald-50 dark:bg-emerald-950/40 border border-emerald-200 dark:border-emerald-900/50 flex items-center gap-2.5 text-xs text-emerald-700 dark:text-emerald-300">
        <CheckCircle2 class="w-4 h-4 shrink-0 text-emerald-500" />
        <span>{{ successMessage }}</span>
      </div>

      <!-- PREVIEW TABLE -->
      <div v-if="previewRecords.length > 0" class="space-y-3 pt-2 border-t border-slate-200 dark:border-[#333333]">
        <div class="flex items-center justify-between">
          <div class="flex items-center gap-2">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white uppercase tracking-wider">
              Excel Data Ready for Import
            </h4>
            <span class="px-2.5 py-0.5 rounded-full bg-emerald-100 dark:bg-emerald-900/40 text-emerald-700 dark:text-emerald-300 text-[10px] font-black">
              {{ previewRecords.length }} Students Detected
            </span>
          </div>

          <button
            type="button"
            @click="clearPreview"
            class="text-[11px] text-slate-500 hover:text-rose-600 dark:text-slate-400 dark:hover:text-rose-400 transition cursor-pointer"
          >
            Clear File
          </button>
        </div>

        <div class="max-h-60 overflow-y-auto rounded-2xl border border-slate-200 dark:border-[#333333] bg-white dark:bg-[#1a1a1a]">
          <table class="w-full text-left text-xs">
            <thead class="sticky top-0 bg-slate-50 dark:bg-[#242424] text-slate-500 dark:text-[#a0a0a0] text-[10px] font-bold uppercase tracking-wider border-b border-slate-200 dark:border-[#333333]">
              <tr>
                <th class="px-3.5 py-2">#</th>
                <th class="px-3.5 py-2">Student Name</th>
                <th class="px-3.5 py-2">Student ID</th>
                <th class="px-3.5 py-2">Course</th>
                <th class="px-3.5 py-2">Year Level</th>
                <th class="px-3.5 py-2">Section</th>
                <th class="px-3.5 py-2">Action</th>
              </tr>
            </thead>
            <tbody class="divide-y divide-slate-100 dark:divide-[#282828]">
              <tr
                v-for="(rec, idx) in previewRecords"
                :key="idx"
                class="hover:bg-slate-50 dark:hover:bg-[#222222] transition"
              >
                <td class="px-3.5 py-2 font-mono text-slate-400">{{ idx + 1 }}</td>
                <td class="px-3.5 py-2 font-bold text-slate-900 dark:text-white">{{ rec.name }}</td>
                <td class="px-3.5 py-2 font-mono font-bold text-blue-600 dark:text-blue-400">{{ rec.studentId }}</td>
                <td class="px-3.5 py-2 text-slate-700 dark:text-slate-300">{{ rec.course || defaultCourse }}</td>
                <td class="px-3.5 py-2 text-slate-600 dark:text-[#a0a0a0]">{{ rec.yearLevel || defaultYearLevel }}</td>
                <td class="px-3.5 py-2 text-slate-700 dark:text-slate-300 font-semibold">{{ rec.section || defaultSection }}</td>
                <td class="px-3.5 py-2">
                  <button
                    type="button"
                    @click="removePreviewRecord(idx)"
                    class="p-1 text-slate-400 hover:text-rose-500 rounded-md transition cursor-pointer"
                    title="Remove row"
                  >
                    <Trash2 class="w-3.5 h-3.5" />
                  </button>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="flex items-center justify-end gap-3 pt-3 border-t border-slate-200 dark:border-[#333333]">
        <button
          type="button"
          @click="handleClose"
          class="px-4 py-2.5 rounded-xl text-xs font-semibold text-slate-700 dark:text-slate-300 hover:bg-slate-100 dark:hover:bg-[#282828] transition cursor-pointer"
        >
          Cancel
        </button>
        <button
          type="button"
          @click="handleConfirmImport"
          :disabled="previewRecords.length === 0 || isProcessing"
          class="flex items-center gap-2 px-5 py-2.5 bg-emerald-600 hover:bg-emerald-700 disabled:opacity-50 disabled:cursor-not-allowed text-white rounded-xl text-xs font-bold shadow-md shadow-emerald-500/20 transition cursor-pointer"
        >
          <Check class="w-4 h-4" />
          <span>{{ isProcessing ? 'Importing...' : `Confirm & Import ${previewRecords.length} Students` }}</span>
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import {
  FileSpreadsheet, SlidersHorizontal,
  Upload, Download, Trash2, Check, AlertCircle, CheckCircle2
} from 'lucide-vue-next';
import * as XLSX from 'xlsx';
import Modal from '../common/Modal.vue';
import { useAppStore } from '../../composables/useAppStore';
import { downloadStudentImportTemplate } from '../../utils/excelExport';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
  (e: 'imported', data: { total: number; added: number; updated: number; message: string; importedClasses: any[] }): void;
}>();

const { importStudents, availableSections } = useAppStore();

const defaultCourse = ref('BSIT');
const defaultYearLevel = ref('3rd Year');
const defaultSection = ref('31M1');

const fileInputRef = ref<HTMLInputElement | null>(null);
const isDragging = ref(false);
const parsedFileName = ref('');
const isProcessing = ref(false);
const errorMessage = ref('');
const successMessage = ref('');

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

const clearPreview = () => {
  previewRecords.value = [];
  parsedFileName.value = '';
  errorMessage.value = '';
  successMessage.value = '';
  if (fileInputRef.value) fileInputRef.value.value = '';
};

const handleClose = () => {
  clearPreview();
  emit('close');
};

const triggerFileInput = () => {
  fileInputRef.value?.click();
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

  // Strictly check that only Excel / CSV files are uploaded
  if (!ext || !validExtensions.includes(ext)) {
    errorMessage.value = `Access Denied: "${file.name}" is not an Excel file. Only Excel spreadsheets (.xlsx, .xls) and CSV (.csv) files are permitted for student roster import.`;
    parsedFileName.value = '';
    if (fileInputRef.value) fileInputRef.value.value = '';
    return;
  }

  parsedFileName.value = file.name;
  const reader = new FileReader();

  reader.onload = (evt) => {
    try {
      const data = evt.target?.result;
      const workbook = XLSX.read(data, { type: 'binary' });
      const firstSheetName = workbook.SheetNames[0];
      if (!firstSheetName) {
        errorMessage.value = 'The uploaded Excel file has no worksheets.';
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
          parsed.push({
            name: rawName || `Student ${rawId}`,
            studentId: rawId || `2024-${String(1000 + idx)}`,
            course: rawCourse || defaultCourse.value,
            department: rawDept || undefined,
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
      successMessage.value = `Successfully parsed ${parsed.length} student records from "${file.name}".`;
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
    const res = importStudents(previewRecords.value);
    isProcessing.value = false;
    emit('imported', res);
    handleClose();
  } catch (err: any) {
    isProcessing.value = false;
    errorMessage.value = `Error importing records: ${err?.message || 'Unknown error'}`;
  }
};
</script>
