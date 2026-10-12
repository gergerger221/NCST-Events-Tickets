<template>
  <!-- Academic Period Management Modal (Authoritative Admin Control) -->
  <Modal
    :is-open="isOpen"
    title="Academic Configuration & Semester Management"
    max-width="3xl"
    @close="$emit('close')"
  >
    <div class="space-y-6">
      <!-- Notice Banner -->
      <div class="p-4 rounded-2xl bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 flex items-start gap-3">
        <GraduationCap class="w-5 h-5 text-blue-600 dark:text-blue-400 shrink-0 mt-0.5" />
        <div class="text-xs space-y-1">
          <p class="font-bold text-slate-900 dark:text-white">
            Authoritative Academic Year and Semester Scoping
          </p>
          <p class="text-slate-600 dark:text-slate-300 leading-relaxed">
            Active Period: <strong class="text-blue-700 dark:text-blue-300">{{ activeAcademicPeriod?.academicYear || '2026-2027' }} • {{ activeAcademicPeriod?.semester || '1st Semester' }}</strong>.
            Only one semester may be active at a time. All events, tickets, and attendance records derive their semester strictly from the authoritative event configuration.
          </p>
        </div>
      </div>

      <!-- Academic Periods Table / Cards -->
      <div class="space-y-3">
        <div class="flex items-center justify-between">
          <h3 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
            Configured Academic Periods
          </h3>
          <span class="text-[10px] text-slate-400 font-bold">
            {{ academicPeriods.length }} periods recorded
          </span>
        </div>

        <div class="border border-slate-200 dark:border-[#333] rounded-2xl overflow-hidden divide-y divide-slate-100 dark:divide-[#2e2e2e]">
          <div
            v-for="p in academicPeriods"
            :key="p.id"
            class="p-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-white dark:bg-[#1f1f1f] transition"
            :class="p.status === 'active' ? 'bg-blue-50/40 dark:bg-blue-950/20' : ''"
          >
            <div class="space-y-1">
              <div class="flex items-center gap-2">
                <span class="text-sm font-black text-slate-900 dark:text-white">
                  A.Y. {{ p.academicYear }} — {{ p.semester }}
                </span>
                <!-- Status Badge -->
                <span
                  class="text-[10px] font-black uppercase px-2 py-0.5 rounded-full"
                  :class="[
                    p.status === 'active' ? 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-700' :
                    p.status === 'archived' ? 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300 border border-amber-300 dark:border-amber-700' :
                    'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400 border border-slate-200 dark:border-slate-700'
                  ]"
                >
                  {{ p.status === 'active' ? 'ACTIVE (Current)' : p.status.toUpperCase() }}
                </span>
              </div>
              <p class="text-[11px] text-slate-500 dark:text-slate-400">
                Duration: {{ p.startDate }} to {{ p.endDate }}
              </p>
            </div>

            <!-- Actions -->
            <div class="flex items-center gap-2 shrink-0">
              <button
                v-if="p.status === 'inactive'"
                @click="promptActivateSemester(p)"
                class="px-3.5 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-xs cursor-pointer flex items-center gap-1.5"
              >
                <CheckCircle2 class="w-3.5 h-3.5" />
                <span>Activate Semester</span>
              </button>
              <span
                v-else-if="p.status === 'active'"
                class="text-xs font-bold text-emerald-600 dark:text-emerald-400 px-3 py-1.5 bg-emerald-50 dark:bg-emerald-950/60 rounded-xl border border-emerald-200 dark:border-emerald-800 flex items-center gap-1"
              >
                <Check class="w-3.5 h-3.5" />
                <span>Authoritative Active</span>
              </span>
              <span
                v-else
                class="text-xs text-amber-700 dark:text-amber-400 px-3 py-1.5 bg-amber-50 dark:bg-amber-950/40 rounded-xl border border-amber-200 dark:border-amber-900 flex items-center gap-1"
              >
                <span>Historical Archive</span>
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Add / Configure Period Form -->
      <div class="p-4 rounded-2xl bg-slate-50 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] space-y-3">
        <h4 class="text-xs font-black uppercase tracking-wider text-slate-900 dark:text-white">
          Configure Academic Period
        </h4>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Academic Year (YYYY-YYYY)
            </label>
            <input
              type="text"
              v-model="periodFormAcadYear"
              placeholder="e.g. 2026-2027"
              class="w-full bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#383838] rounded-xl px-3 py-2 text-xs font-mono font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Semester
            </label>
            <select
              v-model="periodFormSemester"
              class="w-full bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#383838] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            >
              <option value="1st Semester">1st Semester</option>
              <option value="2nd Semester">2nd Semester</option>
            </select>
          </div>
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
              Start Date
            </label>
            <input
              type="date"
              v-model="periodFormStartDate"
              class="w-full bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#383838] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            />
          </div>
          <div>
            <label class="block font-semibold text-slate-700 dark:text-slate-300 mb-1">
              End Date
            </label>
            <input
              type="date"
              v-model="periodFormEndDate"
              class="w-full bg-white dark:bg-[#202020] border border-slate-300 dark:border-[#383838] rounded-xl px-3 py-2 text-xs font-semibold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500 cursor-pointer"
            />
          </div>
        </div>

        <div v-if="periodFormError" class="p-2.5 rounded-xl bg-rose-50 dark:bg-rose-950/50 text-rose-700 dark:text-rose-300 text-xs font-semibold flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0" />
          <span>{{ periodFormError }}</span>
        </div>

        <div v-if="periodFormSuccess" class="p-2.5 rounded-xl bg-emerald-50 dark:bg-emerald-950/50 text-emerald-700 dark:text-emerald-300 text-xs font-semibold flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 shrink-0" />
          <span>{{ periodFormSuccess }}</span>
        </div>

        <div class="flex justify-end pt-1">
          <button
            @click="handleSavePeriodForm"
            :disabled="isSubmittingPeriod"
            class="px-4 py-2 bg-slate-900 hover:bg-black dark:bg-blue-600 dark:hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer disabled:opacity-50"
          >
            {{ isSubmittingPeriod ? 'Saving...' : 'Save Academic Period' }}
          </button>
        </div>
      </div>

      <div class="pt-2 flex justify-end">
        <button
          @click="$emit('close')"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#2b2b2b] dark:hover:bg-[#333] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>

  <!-- Semester Transition Confirmation Modal (Strict Administrative Authorization) -->
  <Modal
    :is-open="!!periodToActivate"
    title="Confirm Semester Transition"
    max-width="lg"
    @close="periodToActivate = null"
  >
    <div v-if="periodToActivate" class="space-y-4">
      <div class="p-4 rounded-2xl bg-amber-50 dark:bg-amber-950/50 border border-amber-300 dark:border-amber-800 text-amber-900 dark:text-amber-200 text-xs space-y-2">
        <div class="flex items-center gap-2 font-black">
          <AlertTriangle class="w-4 h-4 text-amber-600 dark:text-amber-400 shrink-0" />
          <span>AUTHORITATIVE SEMESTER ACTIVATION</span>
        </div>
        <p class="leading-relaxed">
          You are about to activate <strong>{{ periodToActivate.academicYear }} {{ periodToActivate.semester }}</strong> as the authoritative active academic period.
        </p>
        <ul class="list-disc pl-4 space-y-1 text-[11px]">
          <li>The currently active period (<strong>{{ activeAcademicPeriod?.academicYear }} {{ activeAcademicPeriod?.semester }}</strong>) will be transitioned to <strong>ARCHIVED</strong>.</li>
          <li>All existing events, tickets, transactions, and attendance records will remain strictly bound to their original academic period.</li>
          <li>No records will be silently reassigned or deleted.</li>
          <li>This action will be permanently recorded in the system audit logs.</li>
        </ul>
      </div>

      <div class="flex justify-end gap-2 pt-2">
        <button
          @click="periodToActivate = null"
          class="px-4 py-2 bg-slate-100 hover:bg-slate-200 dark:bg-[#2e2e2e] dark:hover:bg-[#383838] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold cursor-pointer"
        >
          Cancel
        </button>
        <button
          @click="confirmActivateSemester"
          :disabled="isActivatingSemester"
          class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-xs transition cursor-pointer disabled:opacity-50 flex items-center gap-2"
        >
          <span v-if="isActivatingSemester" class="animate-spin">⏳</span>
          <span>{{ isActivatingSemester ? 'Activating...' : 'Confirm Activation' }}</span>
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import {
  GraduationCap,
  CheckCircle2,
  Check,
  AlertCircle,
  AlertTriangle
} from 'lucide-vue-next';
import Modal from '../common/Modal.vue';
import { useAppStore } from '../../composables/useAppStore';
import { AcademicPeriod } from '../../types';

defineProps<{
  isOpen: boolean;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const {
  academicPeriods,
  activeAcademicPeriod,
  createAcademicPeriod,
  activateAcademicPeriod
} = useAppStore();

const periodToActivate = ref<AcademicPeriod | null>(null);
const isActivatingSemester = ref(false);
const isSubmittingPeriod = ref(false);
const periodFormAcadYear = ref(activeAcademicPeriod.value?.academicYear || '2026-2027');
const periodFormSemester = ref<'1st Semester' | '2nd Semester'>('2nd Semester');
const periodFormStartDate = ref('2027-01-05');
const periodFormEndDate = ref('2027-05-31');
const periodFormError = ref('');
const periodFormSuccess = ref('');

const promptActivateSemester = (period: AcademicPeriod) => {
  periodToActivate.value = period;
};

const confirmActivateSemester = async () => {
  if (!periodToActivate.value) return;
  isActivatingSemester.value = true;
  try {
    const res = await activateAcademicPeriod(periodToActivate.value.id);
    if (res.success) {
      periodToActivate.value = null;
      periodFormSuccess.value = `Successfully activated ${res.activePeriod?.academicYear || ''} ${res.activePeriod?.semester || ''}!`;
    } else {
      alert(res.message || (res as any).error || 'Failed to activate academic period');
    }
  } catch (err: any) {
    alert(err?.message || 'Error activating academic period');
  } finally {
    isActivatingSemester.value = false;
  }
};

const handleSavePeriodForm = async () => {
  periodFormError.value = '';
  periodFormSuccess.value = '';

  if (!periodFormAcadYear.value || !/^\d{4}-\d{4}$/.test(periodFormAcadYear.value.trim())) {
    periodFormError.value = 'Academic Year must follow format YYYY-YYYY (e.g. 2026-2027)';
    return;
  }
  if (!periodFormStartDate.value || !periodFormEndDate.value) {
    periodFormError.value = 'Start date and end date are required';
    return;
  }
  if (periodFormStartDate.value >= periodFormEndDate.value) {
    periodFormError.value = 'Start date must be before end date';
    return;
  }

  isSubmittingPeriod.value = true;
  try {
    const res = await createAcademicPeriod({
      academicYear: periodFormAcadYear.value.trim(),
      semester: periodFormSemester.value,
      startDate: periodFormStartDate.value,
      endDate: periodFormEndDate.value,
      status: 'inactive'
    });
    if (res.success) {
      periodFormSuccess.value = 'Academic period saved successfully as inactive.';
    } else {
      periodFormError.value = res.error || (res as any).message || 'Failed to create academic period.';
    }
  } catch (err: any) {
    periodFormError.value = err?.message || 'Error creating academic period';
  } finally {
    isSubmittingPeriod.value = false;
  }
};
</script>
