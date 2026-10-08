<template>
  <div class="space-y-6">
    <!-- Top Header -->
    <div class="no-print flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div>
        <h2 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white tracking-tight">
          Attendance & Ticketing Reports Center
        </h2>
        <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
          Generate printable school administration reports or export categorized data to Microsoft Excel.
        </p>
      </div>

      <div class="flex flex-wrap items-center gap-2">
        <button
          @click="handlePrint"
          class="flex items-center gap-2 px-3.5 py-2.5 bg-slate-100 hover:bg-slate-200 dark:bg-[#242424] dark:hover:bg-[#2c2c2c] text-slate-800 dark:text-white rounded-xl text-xs font-bold border border-slate-300 dark:border-[#3a3a3a] shadow-sm transition cursor-pointer"
        >
          <Printer class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          <span>Print Official Report</span>
        </button>
        <button
          @click="handleExportStudentExcel"
          class="flex items-center gap-1.5 px-3.5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-md transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4" />
          <span>{{ isTeacher ? 'Export My Students (.xlsx)' : 'Export Students (.xlsx)' }}</span>
        </button>
        <button
          v-if="!isTeacher"
          @click="handleExportOutsiderExcel"
          class="flex items-center gap-1.5 px-3.5 py-2.5 bg-amber-600 hover:bg-amber-700 text-white rounded-xl text-xs font-bold shadow-md transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4" />
          <span>Export Outsiders (.xlsx)</span>
        </button>
        <button
          v-if="!isTeacher"
          @click="handleExportExcel"
          class="flex items-center gap-1.5 px-3.5 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-xs font-bold shadow-md transition cursor-pointer"
        >
          <FileSpreadsheet class="w-4 h-4" />
          <span>Export Master Workbook</span>
        </button>
      </div>
    </div>

    <!-- Report Filter Tabs -->
    <div class="no-print flex items-center gap-2 overflow-x-auto pb-1">
      <button
        v-for="tab in reportTabs"
        :key="tab.id"
        @click="reportType = tab.id as any"
        class="px-3.5 py-2 rounded-xl text-xs font-bold transition cursor-pointer whitespace-nowrap"
        :class="reportType === tab.id ? 'bg-blue-600 text-white shadow-md' : 'bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white hover:bg-slate-50 dark:hover:bg-[#2c2c2c]'"
      >
        {{ tab.label }} ({{ tab.count }})
      </button>
    </div>

    <!-- Printable Report Sheet -->
    <div class="print-page bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-3xl p-6 sm:p-8 shadow-sm dark:shadow-md transition-colors">
      <!-- Header -->
      <div class="text-center pb-6 border-b border-slate-200 dark:border-[#333333] space-y-1">
        <div class="flex items-center justify-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs uppercase tracking-widest">
          <Building2 class="w-4 h-4" />
          Integrative Programming Technologies 2 (Integ 2)
        </div>
        <h1 class="text-xl sm:text-2xl font-black text-slate-900 dark:text-white uppercase tracking-tight">
          Official Event Attendance & Security Audit Report
        </h1>
        <p class="text-xs text-slate-600 dark:text-[#b3b3b3]">
          Event: <strong class="text-slate-900 dark:text-white">{{ currentEvent?.title || 'No Events' }}</strong>
          <span v-if="currentEvent?.code"> ({{ currentEvent.code }})</span>
          <span v-if="currentEvent?.venue"> • Venue: {{ currentEvent.venue }}</span>
        </p>
        <p class="text-[11px] text-slate-400 dark:text-slate-500 font-mono">
          Report Generated on: {{ formatPhilippineFull(new Date()) }} • Report Category: {{ reportType.toUpperCase() }}
        </p>
      </div>

      <!-- Metric Ribbon (Section 20: Reporting Metrics) -->
      <div class="grid grid-cols-2 sm:grid-cols-5 gap-3 my-6">
        <div class="p-3 bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center">
          <span class="text-[9px] text-slate-500 dark:text-[#b3b3b3] uppercase font-bold block">Tickets Issued</span>
          <span class="text-lg font-black text-slate-900 dark:text-white">{{ totalCount }}</span>
        </div>
        <div class="p-3 bg-amber-50/60 dark:bg-amber-950/20 border border-amber-200 dark:border-amber-800/60 rounded-xl text-center">
          <span class="text-[9px] text-amber-700 dark:text-amber-400 uppercase font-bold block">QR Labels Pending</span>
          <span class="text-lg font-black text-amber-600 dark:text-amber-400">{{ qrPendingCount }}</span>
        </div>
        <div class="p-3 bg-blue-50/60 dark:bg-blue-950/20 border border-blue-200 dark:border-blue-800/60 rounded-xl text-center">
          <span class="text-[9px] text-blue-700 dark:text-blue-400 uppercase font-bold block">QR Labels Printed</span>
          <span class="text-lg font-black text-blue-600 dark:text-blue-400">{{ qrPrintedCount }}</span>
        </div>
        <div class="p-3 bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/60 rounded-xl text-center">
          <span class="text-[9px] text-emerald-700 dark:text-emerald-400 uppercase font-bold block">Tickets Used (Scanned)</span>
          <span class="text-lg font-black text-emerald-600 dark:text-emerald-400">{{ presentCount }}</span>
        </div>
        <div class="p-3 bg-slate-50 dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl text-center col-span-2 sm:col-span-1">
          <span class="text-[9px] text-slate-500 dark:text-[#b3b3b3] uppercase font-bold block">Not Yet Entered</span>
          <span class="text-lg font-black text-slate-600 dark:text-slate-300">{{ notYetScannedCount }}</span>
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
            <tr v-for="(l, idx) in logs" :key="l.id" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
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
              <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ new Date(l.timestamp).toLocaleString() }}</td>
              <td class="p-2.5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ l.verifiedByName }}</td>
            </tr>
            <tr v-if="logs.length === 0">
              <td colspan="9" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No scan activity logged yet.</td>
            </tr>
          </tbody>
        </table>
      </div>

      <!-- Purchase Transactions -->
      <div v-else-if="reportType === 'transactions'" class="overflow-x-auto">
        <table class="w-full text-left text-xs border border-slate-200 dark:border-[#333333]">
          <thead class="bg-slate-50 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] uppercase text-[10px]">
            <tr>
              <th class="p-2.5 border border-slate-200 dark:border-[#333333]">#</th>
              <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Transaction ID</th>
              <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Purchaser</th>
              <th class="p-2.5 border border-slate-200 dark:border-[#333333]">Student ID</th>
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
            <tr v-for="(txn, idx) in eventTransactions" :key="txn.id" class="hover:bg-slate-50 dark:hover:bg-[#242424]">
              <td class="p-2.5 text-slate-400 border border-slate-200 dark:border-[#333333]">{{ idx + 1 }}</td>
              <td class="p-2.5 font-mono font-bold text-amber-600 dark:text-amber-400 border border-slate-200 dark:border-[#333333]">{{ txn.id }}</td>
              <td class="p-2.5 font-bold text-slate-900 dark:text-white border border-slate-200 dark:border-[#333333]">{{ txn.userName }}</td>
              <td class="p-2.5 font-mono text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ txn.studentId || 'N/A' }}</td>
              <td class="p-2.5 font-mono text-[11px] text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ txn.course ? `${txn.course} ${txn.section ? 'Sec ' + txn.section : ''}` : '---' }}</td>
              <td class="p-2.5 text-slate-700 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ txn.eventTitle }}</td>
              <td class="p-2.5 font-mono font-bold text-blue-600 dark:text-blue-400 border border-slate-200 dark:border-[#333333]">{{ txn.ticketNumber || txn.ticketId }}</td>
              <td class="p-2.5 font-bold text-emerald-600 dark:text-emerald-400 border border-slate-200 dark:border-[#333333]">
                {{ txn.amount === 0 ? 'FREE' : '₱' + txn.amount.toFixed(2) }}
              </td>
              <td class="p-2.5 border border-slate-200 dark:border-[#333333]">
                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase" :class="txn.paymentStatus === 'paid' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-800' : 'bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300'">
                  {{ txn.paymentStatus }}
                </span>
              </td>
              <td class="p-2.5 text-slate-500 dark:text-slate-400 border border-slate-200 dark:border-[#333333]">{{ txn.cashierName || 'Online Portal' }}</td>
              <td class="p-2.5 font-mono text-slate-600 dark:text-slate-300 border border-slate-200 dark:border-[#333333]">{{ new Date(txn.purchaseDate).toLocaleString() }}</td>
            </tr>
            <tr v-if="eventTransactions.length === 0">
              <td colspan="11" class="p-6 text-center text-slate-400 dark:text-slate-500 border border-slate-200 dark:border-[#333333]">No ticket purchase transactions found.</td>
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
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Printer, FileSpreadsheet, Building2 } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import {
  exportTicketsToExcel,
  exportStudentAttendanceOnlyToExcel,
  exportOutsiderAttendanceOnlyToExcel,
  exportAttendanceLogsToExcel,
  exportTransactionsToExcel
} from '../../utils/excelExport';
import { formatPhilippineFull, formatPhilippineTime, formatPhilippineDate } from '../../utils/philippineTime';

const { currentUser, tickets, events, currentEvent, logs, transactions, users } = useAppStore();

const isTeacher = computed(() => currentUser.value?.role === 'teacher');

// Teacher Assigned Classes
const teacherAssignedClasses = computed(() => {
  return currentUser.value?.assignedClasses || [];
});

// Authorized tickets pool based on role
const authorizedTickets = computed(() => {
  const eventTix = tickets.value.filter(t => t.eventId === currentEvent.value?.id && t.ticketStatus !== 'CANCELLED');
  if (!isTeacher.value) return eventTix;

  // Strict Teacher Scope: Students in assigned classes only, never Outsiders
  return eventTix.filter(t => {
    if (t.attendeeType === 'OUTSIDER') return false;
    const tCourse = (t.course || '').trim().toLowerCase();
    const tSection = (t.section || '').trim().toUpperCase();
    return teacherAssignedClasses.value.some(
      ac => ac.course.trim().toLowerCase() === tCourse && ac.section.trim().toUpperCase() === tSection
    );
  });
});

const reportType = ref<'all' | 'students' | 'outsiders' | 'present' | 'absent' | 'transactions' | 'audit_logs'>('all');

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
        return ticket.attendanceStatus === 'not_entered' && ticket.ticketStatus === 'ISSUED';
      case 'all':
      default:
        return true;
    }
  });
});

const eventTransactions = computed(() => {
  if (isTeacher.value) return [];
  return transactions.value.filter(t => !currentEvent.value || t.eventId === currentEvent.value.id);
});

const totalCount = computed(() => authorizedTickets.value.length);
const presentCount = computed(() => authorizedTickets.value.filter(t => t.attendanceStatus === 'present').length);
const studentPresentCount = computed(() => authorizedTickets.value.filter(t => t.attendanceStatus === 'present' && (t.attendeeType || 'STUDENT') === 'STUDENT').length);
const outsiderPresentCount = computed(() => isTeacher.value ? 0 : authorizedTickets.value.filter(t => t.attendanceStatus === 'present' && t.attendeeType === 'OUTSIDER').length);
const studentsTotalCount = computed(() => authorizedTickets.value.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT').length);
const outsidersTotalCount = computed(() => isTeacher.value ? 0 : authorizedTickets.value.filter(t => t.attendeeType === 'OUTSIDER').length);

const qrPendingCount = computed(() => authorizedTickets.value.filter(t => t.qrPrintStatus === 'PENDING' || !t.qrPrintStatus).length);
const qrPrintedCount = computed(() => authorizedTickets.value.filter(t => t.qrPrintStatus === 'PRINTED' || t.qrPrintStatus === 'ATTACHED').length);
const notYetScannedCount = computed(() => authorizedTickets.value.filter(t => t.attendanceStatus === 'not_entered').length);

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
    { id: 'absent', label: 'Unentered / Absent', count: Math.max(0, totalCount.value - presentCount.value) },
    { id: 'transactions', label: 'Purchase Transactions', count: eventTransactions.value.length },
    { id: 'audit_logs', label: 'Scan Audit Logs', count: logs.value.length },
  ];
});

const handleExportExcel = () => {
  if (reportType.value === 'audit_logs') {
    exportAttendanceLogsToExcel(logs.value, `${currentEvent.value?.code || 'EVENT'}_Scan_Audit_Logs.xlsx`);
  } else if (reportType.value === 'transactions') {
    exportTransactionsToExcel(eventTransactions.value, `${currentEvent.value?.code || 'EVENT'}_Purchase_Transactions.xlsx`);
  } else if (reportType.value === 'students' || isTeacher.value) {
    exportStudentAttendanceOnlyToExcel(reportData.value, events.value, `${currentEvent.value?.code || 'EVENT'}_${currentUser.value?.name || 'Teacher'}_Student_Attendance.xlsx`);
  } else if (reportType.value === 'outsiders') {
    exportOutsiderAttendanceOnlyToExcel(reportData.value, events.value, `${currentEvent.value?.code || 'EVENT'}_Outsider_Attendance.xlsx`);
  } else {
    exportTicketsToExcel(
      reportData.value,
      events.value,
      `${currentEvent.value?.code || 'EVENT'}_Master_Report.xlsx`
    );
  }
};

const handleExportStudentExcel = () => {
  exportStudentAttendanceOnlyToExcel(
    authorizedTickets.value.filter(t => (t.attendeeType || 'STUDENT') === 'STUDENT'),
    events.value,
    `${currentEvent.value?.code || 'EVENT'}_${currentUser.value?.name || 'Faculty'}_Student_Attendance.xlsx`
  );
};

const handleExportOutsiderExcel = () => {
  if (isTeacher.value) return;
  exportOutsiderAttendanceOnlyToExcel(
    tickets.value.filter(t => t.eventId === currentEvent.value?.id && t.attendeeType === 'OUTSIDER'),
    events.value,
    `${currentEvent.value?.code || 'EVENT'}_Outsider_Attendance.xlsx`
  );
};

const handlePrint = () => {
  window.print();
};
</script>
