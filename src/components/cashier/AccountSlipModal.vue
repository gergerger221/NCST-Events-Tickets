<template>
  <Modal
    :is-open="isOpen"
    @close="$emit('close')"
    title="Official Student Account Slip"
    subtitle="NCST Student Portal First-Time Credentials"
    max-width="sm"
  >
    <div class="space-y-4">
      <!-- Printable Slip Card -->
      <div id="account-slip-printable" class="p-6 bg-white dark:bg-[#1a1a1a] rounded-2xl border-2 border-dashed border-slate-300 dark:border-[#444444] text-center space-y-4 shadow-xs">
        <!-- Header -->
        <div class="space-y-1">
          <div class="flex items-center justify-center gap-2">
            <img src="/ncst-logo.png" alt="NCST" class="w-8 h-8 object-contain rounded-full bg-white p-0.5" />
            <h2 class="text-sm font-black text-slate-900 dark:text-white uppercase tracking-wider">
              NCST STUDENT PORTAL
            </h2>
          </div>
          <p class="text-[10px] font-bold text-slate-500 dark:text-[#888888] uppercase tracking-wider">
            Student Account Slip
          </p>
        </div>

        <div class="py-2 border-t border-b border-slate-200 dark:border-[#333333] space-y-2.5">
          <!-- Student ID -->
          <div>
            <span class="text-[10px] font-bold text-slate-400 dark:text-slate-500 uppercase block">Student ID</span>
            <span class="text-sm font-mono font-black text-slate-900 dark:text-white">
              {{ slipData.studentId }}
            </span>
          </div>

          <!-- Temporary Password -->
          <div class="p-3 bg-amber-50 dark:bg-amber-950/50 rounded-xl border border-amber-200 dark:border-amber-900/60">
            <span class="text-[10px] font-extrabold uppercase text-amber-800 dark:text-amber-300 block mb-1">
              Temporary Password
            </span>
            <div class="text-xl font-mono font-black text-amber-600 dark:text-amber-400 tracking-wider select-all">
              {{ slipData.temporaryPassword }}
            </div>
          </div>
        </div>

        <!-- Instructions -->
        <div class="space-y-1.5 text-left">
          <p class="text-[11px] text-slate-700 dark:text-slate-300 font-medium leading-relaxed">
            Log in using these credentials. You will be required to create a new password on your first login.
          </p>
          <p class="text-[9px] text-slate-400 dark:text-slate-500 leading-normal">
            * Keep this slip secure. This temporary password will be permanently replaced upon your first successful sign in.
          </p>
        </div>
      </div>

      <!-- Modal Action Buttons -->
      <div class="flex items-center gap-2 pt-2 border-t border-slate-200 dark:border-[#333333]">
        <button
          type="button"
          @click="handlePrintSlip"
          class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-black uppercase tracking-wider transition flex items-center justify-center gap-2 cursor-pointer shadow-sm"
        >
          <Printer class="w-4 h-4" />
          <span>Print Account Slip</span>
        </button>
        <button
          type="button"
          @click="$emit('close')"
          class="py-2.5 px-4 bg-slate-100 dark:bg-[#2c2c2c] hover:bg-slate-200 dark:hover:bg-[#383838] text-slate-700 dark:text-slate-300 rounded-xl text-xs font-bold transition cursor-pointer"
        >
          Close
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { Printer } from 'lucide-vue-next';
import Modal from '../common/Modal.vue';
import { AccountSlipData } from '../../types';

const props = defineProps<{
  isOpen: boolean;
  slipData: AccountSlipData;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const handlePrintSlip = () => {
  const printContent = document.getElementById('account-slip-printable');
  if (!printContent) return;

  const printWindow = window.open('', '_blank', 'width=400,height=500');
  if (!printWindow) {
    window.print();
    return;
  }

  printWindow.document.write(`
    <!DOCTYPE html>
    <html>
      <head>
        <title>NCST Student Account Slip - ${props.slipData.studentId}</title>
        <style>
          @page {
            size: 80mm auto;
            margin: 4mm;
          }
          body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            margin: 0;
            padding: 10px;
            color: #000;
            background: #fff;
            font-size: 11px;
            line-height: 1.4;
          }
          .slip-container {
            border: 1px dashed #444;
            padding: 14px;
            text-align: center;
            border-radius: 8px;
          }
          .header {
            font-weight: 900;
            font-size: 13px;
            letter-spacing: 1px;
            margin-bottom: 2px;
          }
          .subheader {
            font-size: 9px;
            font-weight: bold;
            color: #555;
            text-transform: uppercase;
            margin-bottom: 10px;
          }
          .divider {
            border-top: 1px solid #ccc;
            margin: 8px 0;
          }
          .label {
            font-size: 9px;
            text-transform: uppercase;
            color: #666;
            font-weight: bold;
          }
          .student-id {
            font-family: monospace;
            font-size: 13px;
            font-weight: bold;
            margin-top: 2px;
            margin-bottom: 8px;
          }
          .code-box {
            border: 1px solid #000;
            padding: 8px;
            background: #f8f8f8;
            margin: 8px 0;
            border-radius: 4px;
          }
          .code {
            font-family: monospace;
            font-size: 17px;
            font-weight: 900;
            letter-spacing: 1px;
          }
          .instructions {
            text-align: left;
            font-size: 10px;
            margin-top: 8px;
          }
          .notice {
            font-size: 8px;
            color: #666;
            margin-top: 6px;
            text-align: left;
          }
        </style>
      </head>
      <body>
        <div class="slip-container">
          <div class="header">NCST STUDENT PORTAL</div>
          <div class="subheader">Student Account Slip</div>
          <div class="divider"></div>
          <div class="label">Student ID</div>
          <div class="student-id">${props.slipData.studentId}</div>
          <div class="code-box">
            <div class="label">Temporary Password</div>
            <div class="code">${props.slipData.temporaryPassword}</div>
          </div>
          <div class="divider"></div>
          <div class="instructions">
            Log in using these credentials. You will be required to create a new password on your first login.
          </div>
          <div class="notice">
            * Keep this slip secure. This temporary password will be permanently replaced upon your first successful sign in.
          </div>
        </div>
      </body>
    </html>
  `);

  printWindow.document.close();
  printWindow.focus();
  setTimeout(() => {
    printWindow.print();
    printWindow.close();
  }, 250);
};
</script>
