<template>
  <div>
    <Modal
      :is-open="isOpen"
      @close="$emit('close')"
      max-width="4xl"
      title="Pre-Printed NCST Event Pass Template"
      subtitle="Generate pre-printed physical event passes"
    >
      <div class="space-y-6">
        <!-- Bulk Print Quantity Controls (no-print) -->
        <div class="no-print flex flex-wrap items-center justify-between gap-4 p-4 bg-slate-50 dark:bg-[#202020] border border-slate-200 dark:border-[#333333] rounded-2xl text-xs">
          <div class="flex flex-wrap items-center gap-3">
            <label class="font-extrabold text-slate-700 dark:text-slate-300">
              Stock Sheet Copies:
            </label>
            <select
              v-model="presetSelection"
              class="px-3 py-1.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-bold text-slate-800 dark:text-white focus:outline-none focus:border-purple-500 cursor-pointer"
            >
              <option value="10">10 Passes</option>
              <option value="25">25 Passes</option>
              <option value="50">50 Passes</option>
              <option value="100">100 Passes</option>
              <option value="custom">Custom...</option>
            </select>

            <!-- Custom Numeric Input -->
            <div v-if="isCustom" class="flex items-center gap-2">
              <input
                type="number"
                v-model.number="customCount"
                placeholder="e.g. 250"
                min="1"
                max="1000"
                step="1"
                class="w-28 px-3 py-1.5 bg-white dark:bg-[#2a2a2a] border border-slate-300 dark:border-[#444444] rounded-xl font-mono font-bold text-slate-800 dark:text-white focus:outline-none focus:border-purple-500 text-center"
              />
              <span class="text-[11px] text-slate-500 dark:text-slate-400 font-semibold">copies</span>
            </div>
          </div>

          <div class="flex items-center gap-3">
            <label class="font-extrabold text-slate-700 dark:text-slate-300">
              Active Event:
            </label>
            <span class="font-bold text-purple-600 dark:text-purple-400">
              {{ selectedEvent?.title || 'Campus Event' }}
            </span>
          </div>
        </div>

        <!-- Validation Error Message -->
        <div v-if="validationError" class="no-print p-3 bg-rose-50 dark:bg-rose-950/40 border border-rose-300 dark:border-rose-800 rounded-xl text-rose-700 dark:text-rose-300 text-xs font-bold flex items-center gap-2">
          <AlertCircle class="w-4 h-4 shrink-0 text-rose-600 dark:text-rose-400" />
          <span>{{ validationError }}</span>
        </div>

        <!-- Large Batch Warning -->
        <div v-else-if="isLargeBatchWarning" class="no-print p-3 bg-amber-50 dark:bg-amber-950/40 border border-amber-300 dark:border-amber-800 rounded-xl text-amber-800 dark:text-amber-300 text-xs font-bold flex items-center gap-2">
          <AlertTriangle class="w-4 h-4 shrink-0 text-amber-600 dark:text-amber-400" />
          <span>Large Batch Notice: Generating {{ effectiveBulkCount }} ticket sheets may take a few moments for the browser print system to render.</span>
        </div>

        <!-- ==================== IN-MODAL PREVIEW ==================== -->
        <div class="no-print w-full max-h-[480px] overflow-y-auto p-4 bg-slate-100 dark:bg-[#151515] rounded-2xl border border-slate-200 dark:border-[#2e2e2e]">
          <!-- Preview Counter Header -->
          <div v-if="effectiveBulkCount > 0" class="mb-3 flex items-center justify-between text-[11px] text-slate-500 dark:text-slate-400 px-1">
            <span class="font-bold">
              Pass Preview: <span class="text-purple-600 dark:text-purple-400">{{ previewCount }} shown</span>
              <span v-if="effectiveBulkCount > previewCount"> (all {{ effectiveBulkCount }} will print)</span>
            </span>
            <span class="font-mono text-[10px]">Sequential Control Numbers NCST-2026-00001 to NCST-2026-{{ String(effectiveBulkCount).padStart(5, '0') }}</span>
          </div>

          <div v-if="effectiveBulkCount > 0" class="space-y-6">
            <div
              v-for="index in previewCount"
              :key="index"
              class="relative w-[780px] sm:w-[840px] h-[310px] mx-auto bg-gradient-to-r from-[#091738] via-[#0e2759] to-[#18396f] text-white rounded-3xl shadow-2xl overflow-hidden border border-amber-400/25 flex select-none shrink-0"
            >
              <!-- 1. LEFT: Original White Textured Visual Divider Strip (Design Element Only) -->
              <div class="w-[210px] sm:w-[220px] bg-gradient-to-b from-slate-50 via-slate-100 to-slate-200 text-slate-900 p-4 flex flex-col justify-between items-center text-center shrink-0 border-r border-slate-300 shadow-inner relative z-10">
                <div class="flex flex-col items-center space-y-1.5 w-full">
                  <img
                    src="/ncst-logo.png"
                    alt="NCST Seal"
                    class="w-12 h-12 object-contain drop-shadow-sm rounded-full bg-white p-0.5 border border-slate-300"
                  />
                  <div>
                    <h5 class="text-[11px] font-black tracking-widest text-slate-900 uppercase font-serif leading-tight">
                      NCST
                    </h5>
                    <p class="text-[7.5px] text-slate-500 tracking-wider uppercase font-semibold">
                      National College of Science & Technology
                    </p>
                  </div>
                </div>

                <!-- Single Visible Prominent Control Number Box -->
                <div class="w-full py-2 px-2 bg-white rounded-xl border border-slate-300 shadow-xs text-center">
                  <div class="flex items-center justify-center gap-1">
                    <span class="text-[8px] font-black uppercase text-slate-600 tracking-wider">CTRL</span>
                    <span class="text-[8.5px] font-black text-rose-600">№</span>
                  </div>
                  <span class="font-mono font-black text-rose-600 text-xs sm:text-sm tracking-wider block mt-0.5">
                    NCST-2026-{{ String(index).padStart(5, '0') }}
                  </span>
                </div>

                <div class="w-full border-t border-slate-300/80 pt-1.5 space-y-0.5">
                  <p class="text-[8.5px] font-black tracking-widest text-amber-600 uppercase">
                    LIVE @ NCST
                  </p>
                  <div class="text-[7.5px] text-slate-500 font-medium tracking-wide">
                    Official Physical Pass
                  </div>
                </div>
              </div>

              <!-- 2. RIGHT / MAIN BODY: Full-Bleed Admin Ticket Template & Details -->
              <div class="flex-1 p-5 flex flex-col justify-between relative overflow-hidden">
                <!-- Admin Uploaded / Selected Ticket Artwork Background -->
                <template v-if="selectedEvent?.ticketDesignUrl || selectedEvent?.bannerUrl">
                  <img
                    :src="selectedEvent?.ticketDesignUrl || selectedEvent?.bannerUrl"
                    alt="Admin Ticket Artwork"
                    class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-85 transition-[object-position] duration-200"
                    :style="{ objectPosition: `center ${selectedEvent?.ticketDesignOffsetY ?? 20}%` }"
                  />
                  <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/30 to-slate-950/65 pointer-events-none" />
                </template>

                <!-- Built-In Default Subtle Blue-and-Yellow NCST-style Ticket Template (Fallback) -->
                <template v-else>
                  <div class="absolute inset-0 bg-gradient-to-br from-[#091738] via-[#0e2759] to-[#16356b] pointer-events-none" />
                  <div class="absolute -right-12 -top-12 w-80 h-80 bg-amber-400/12 rounded-full blur-3xl pointer-events-none" />
                  <div class="absolute -left-10 -bottom-10 w-72 h-72 bg-blue-600/15 rounded-full blur-2xl pointer-events-none" />
                  <div class="absolute inset-0 bg-[radial-gradient(#fbbf24_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none" />
                  <div class="absolute -right-20 -bottom-20 w-96 h-96 border-[40px] border-amber-400/5 rounded-full pointer-events-none" />
                </template>

                <!-- Main Header: School Name on Left -->
                <div class="flex items-start justify-between gap-3 relative z-10">
                  <div>
                    <h4 class="text-[10.5px] sm:text-[11.5px] font-black tracking-widest text-amber-300 uppercase leading-none font-serif drop-shadow-xs">
                      National College of Science & Technology
                    </h4>
                    <p class="text-[8px] sm:text-[8.5px] text-slate-200 mt-0.5 tracking-wide drop-shadow-xs">
                      Amafel Building, Aguinaldo Highway, Dasmariñas City, Cavite
                    </p>
                  </div>
                </div>

                <!-- Middle Section: Main Human-Readable Details on Left & Clean QR on Right -->
                <div class="my-auto py-1 flex items-center justify-between gap-4 relative z-10">
                  <div class="space-y-1.5 max-w-[390px]">
                    <!-- Event Name & Clean LIVE @ NCST Yellow Tag -->
                    <div>
                      <span class="text-[7.5px] uppercase font-bold text-amber-300/90 tracking-wider block drop-shadow-xs">EVENT</span>
                      <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-snug drop-shadow-sm line-clamp-2">
                        {{ selectedEvent?.title || 'Campus Event' }}
                      </h2>
                      <p class="text-[11px] sm:text-xs font-black tracking-widest text-amber-300 uppercase drop-shadow-md mt-0.5">
                        LIVE @ NCST
                      </p>
                    </div>

                    <!-- Date & Time + Venue Grid -->
                    <div class="grid grid-cols-2 gap-3 pt-1">
                      <div>
                        <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">DATE & TIME</span>
                        <p class="text-[9.5px] text-slate-100 font-bold flex items-center gap-1 mt-0.5 drop-shadow-xs">
                          <Calendar class="w-3 h-3 text-amber-400 shrink-0" />
                          <span>{{ selectedEvent?.date ? formatPhilippineDate(selectedEvent.date) : 'November 5, 2026' }} · {{ selectedEvent?.startTime || '6:00 PM' }}</span>
                        </p>
                      </div>
                      <div>
                        <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">VENUE</span>
                        <p class="text-[9.5px] text-amber-300 font-bold truncate mt-0.5 flex items-center gap-1 drop-shadow-xs">
                          <MapPin class="w-3 h-3 text-amber-400 shrink-0" />
                          <span>{{ selectedEvent?.venue || 'NCST Gymnasium' }}</span>
                        </p>
                      </div>
                    </div>
                  </div>

                  <!-- Right: Standalone Clean Official QR Placeholder Box (50mm × 50mm Physical Sticker Target) -->
                  <div class="qr-target-container bg-white text-slate-900 p-2 sm:p-2.5 rounded-2xl shadow-2xl border-2 border-dashed border-amber-400/80 flex flex-col items-center justify-between shrink-0 w-[148px] min-w-[148px] h-[165px] text-center select-none">
                    <span class="text-[7.5px] sm:text-[8px] font-black uppercase text-slate-800 tracking-wider block">
                      OFFICIAL QR CODE
                    </span>
                    <div class="qr-sticker-placement-box w-[128px] h-[128px] border-2 border-dashed border-slate-400 rounded-xl flex flex-col items-center justify-center bg-slate-50 p-1.5">
                      <QrCode class="w-9 h-9 text-blue-800 mb-0.5 shrink-0" />
                      <span class="text-[7.5px] font-extrabold text-slate-600 uppercase tracking-tight">
                        QR STICKER
                      </span>
                      <span class="text-[7px] text-slate-400 font-mono font-bold">
                        50mm × 50mm
                      </span>
                    </div>
                  </div>
                </div>
              </div>
            </div>
          </div>

          <div v-else class="py-12 text-center text-slate-400 text-xs">
            Please enter a valid number of copies to view preview.
          </div>
        </div>

        <!-- Modal Footer / Actions -->
        <div class="no-print flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-[#333333]">
          <div class="text-xs text-slate-500 dark:text-[#b3b3b3]">
            Print Ready: <strong class="text-slate-800 dark:text-slate-200">{{ effectiveBulkCount }} Physical Pass Sheet(s)</strong>
          </div>

          <div class="flex items-center gap-2">
            <button
              type="button"
              @click="$emit('close')"
              class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
            >
              Close
            </button>

            <button
              type="button"
              :disabled="isPrintDisabled"
              @click="handlePrintStock"
              class="flex items-center gap-2 px-5 py-2.5 rounded-xl bg-[#1E3A5F] hover:bg-[#152A45] disabled:opacity-50 disabled:cursor-not-allowed text-white text-xs font-bold shadow-xs transition cursor-pointer"
            >
              <Printer class="w-4 h-4" />
              <span>Print {{ effectiveBulkCount > 0 ? effectiveBulkCount : '' }} Pre-Printed Event Passes</span>
            </button>
          </div>
        </div>
      </div>
    </Modal>

    <!-- ==================== DEDICATED BULK PRINT CONTAINER TELEPORTED TO BODY ==================== -->
    <Teleport to="body">
      <div
        v-if="isOpen && effectiveBulkCount > 0"
        id="bulk-preprinted-tickets-sheet"
        class="hidden"
      >
        <div class="space-y-6">
          <div
            v-for="index in effectiveBulkCount"
            :key="'print-bulk-' + index"
            class="bulk-ticket-print-card relative w-[780px] sm:w-[840px] h-[310px] mx-auto bg-gradient-to-r from-[#091738] via-[#0e2759] to-[#18396f] text-white rounded-3xl border border-amber-400/25 flex select-none shrink-0 overflow-hidden"
          >
            <!-- 1. LEFT: Original White Textured Visual Divider Strip -->
            <div class="w-[210px] sm:w-[220px] bg-gradient-to-b from-slate-50 via-slate-100 to-slate-200 text-slate-900 p-4 flex flex-col justify-between items-center text-center shrink-0 border-r border-slate-300 shadow-inner relative z-10">
              <div class="flex flex-col items-center space-y-1.5 w-full">
                <img
                  src="/ncst-logo.png"
                  alt="NCST Seal"
                  class="w-12 h-12 object-contain drop-shadow-sm rounded-full bg-white p-0.5 border border-slate-300"
                />
                <div>
                  <h5 class="text-[11px] font-black tracking-widest text-slate-900 uppercase font-serif leading-tight">
                    NCST
                  </h5>
                  <p class="text-[7.5px] text-slate-500 tracking-wider uppercase font-semibold">
                    National College of Science & Technology
                  </p>
                </div>
              </div>

              <!-- Single Visible Prominent Control Number Box -->
              <div class="w-full py-2 px-2 bg-white rounded-xl border border-slate-300 shadow-xs text-center">
                <div class="flex items-center justify-center gap-1">
                  <span class="text-[8px] font-black uppercase text-slate-600 tracking-wider">CTRL</span>
                  <span class="text-[8.5px] font-black text-rose-600">№</span>
                </div>
                <span class="font-mono font-black text-rose-600 text-xs sm:text-sm tracking-wider block mt-0.5">
                  NCST-2026-{{ String(index).padStart(5, '0') }}
                </span>
              </div>

              <div class="w-full border-t border-slate-300/80 pt-1.5 space-y-0.5">
                <div class="inline-flex items-center justify-center px-1.5 py-0.5 rounded bg-amber-400 text-slate-950 text-[8px] font-black uppercase tracking-wider mb-0.5 shadow-xs">
                  LIVE @ NCST
                </div>
                <div class="text-[7.5px] text-slate-500 font-medium tracking-wide">
                  Official Physical Pass
                </div>
              </div>
            </div>

            <!-- 2. RIGHT / MAIN BODY: Full-Bleed Admin Ticket Template & Details -->
            <div class="flex-1 p-5 flex flex-col justify-between relative overflow-hidden">
              <template v-if="selectedEvent?.ticketDesignUrl || selectedEvent?.bannerUrl">
                <img
                  :src="selectedEvent?.ticketDesignUrl || selectedEvent?.bannerUrl"
                  alt="Admin Ticket Artwork"
                  class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-85"
                  :style="{ objectPosition: `center ${selectedEvent?.ticketDesignOffsetY ?? 20}%` }"
                />
                <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/30 to-slate-950/65 pointer-events-none" />
              </template>

              <!-- Built-In Default Subtle Blue-and-Yellow NCST-style Ticket Template -->
              <template v-else>
                <div class="absolute inset-0 bg-gradient-to-br from-[#091738] via-[#0e2759] to-[#16356b] pointer-events-none" />
                <div class="absolute -right-12 -top-12 w-80 h-80 bg-amber-400/12 rounded-full blur-3xl pointer-events-none" />
                <div class="absolute -left-10 -bottom-10 w-72 h-72 bg-blue-600/15 rounded-full blur-2xl pointer-events-none" />
                <div class="absolute inset-0 bg-[radial-gradient(#fbbf24_1px,transparent_1px)] [background-size:24px_24px] opacity-10 pointer-events-none" />
                <div class="absolute -right-20 -bottom-20 w-96 h-96 border-[40px] border-amber-400/5 rounded-full pointer-events-none" />
              </template>

              <!-- Main Header: School Name on Left -->
              <div class="flex items-start justify-between gap-3 relative z-10">
                <div>
                  <h4 class="text-[10.5px] sm:text-[11.5px] font-black tracking-widest text-amber-300 uppercase leading-none font-serif drop-shadow-xs">
                    National College of Science & Technology
                  </h4>
                  <p class="text-[8px] sm:text-[8.5px] text-slate-200 mt-0.5 tracking-wide drop-shadow-xs">
                    Amafel Building, Aguinaldo Highway, Dasmariñas City, Cavite
                  </p>
                </div>
              </div>

              <!-- Middle Section: Main Human-Readable Details on Left & Clean QR on Right -->
              <div class="my-auto py-1 flex items-center justify-between gap-4 relative z-10">
                <div class="space-y-1.5 max-w-[390px]">
                  <!-- Event Name & LIVE @ NCST Tag -->
                  <div>
                    <div class="flex items-center gap-2 mb-0.5">
                      <span class="text-[7.5px] uppercase font-bold text-amber-300/90 tracking-wider block drop-shadow-xs">EVENT</span>
                      <span class="text-[8px] font-black tracking-widest uppercase px-1.5 py-0.5 rounded bg-amber-400 text-slate-950 shadow-xs flex items-center gap-1">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-600 animate-pulse"></span>
                        <span>LIVE @ NCST</span>
                      </span>
                    </div>
                    <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-snug drop-shadow-sm line-clamp-2">
                      {{ selectedEvent?.title || 'Campus Event' }}
                    </h2>
                  </div>

                  <div class="grid grid-cols-2 gap-3 pt-1">
                    <div>
                      <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">DATE & TIME</span>
                      <p class="text-[9.5px] text-slate-100 font-bold flex items-center gap-1 mt-0.5 drop-shadow-xs">
                        <Calendar class="w-3 h-3 text-amber-400 shrink-0" />
                        <span>{{ selectedEvent?.date ? formatPhilippineDate(selectedEvent.date) : 'November 5, 2026' }} · {{ selectedEvent?.startTime || '6:00 PM' }}</span>
                      </p>
                    </div>
                    <div>
                      <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">VENUE</span>
                      <p class="text-[9.5px] text-amber-300 font-bold truncate mt-0.5 flex items-center gap-1 drop-shadow-xs">
                        <MapPin class="w-3 h-3 text-amber-400 shrink-0" />
                        <span>{{ selectedEvent?.venue || 'NCST Gymnasium' }}</span>
                      </p>
                    </div>
                  </div>
                </div>

                <!-- Right: Standalone Clean Official QR Placeholder Box (50mm × 50mm Physical Sticker Target) -->
                <div class="qr-target-container bg-white text-slate-900 p-2 sm:p-2.5 rounded-2xl shadow-2xl border-2 border-dashed border-amber-400/80 flex flex-col items-center justify-between shrink-0 w-[148px] min-w-[148px] h-[165px] text-center select-none">
                  <span class="text-[7.5px] sm:text-[8px] font-black uppercase text-slate-800 tracking-wider block">
                    OFFICIAL QR CODE
                  </span>
                  <div class="qr-sticker-placement-box w-[128px] h-[128px] border-2 border-dashed border-slate-400 rounded-xl flex flex-col items-center justify-center bg-slate-50 p-1.5">
                    <QrCode class="w-9 h-9 text-blue-800 mb-0.5 shrink-0" />
                    <span class="text-[7.5px] font-extrabold text-slate-600 uppercase tracking-tight">
                      QR STICKER
                    </span>
                    <span class="text-[7px] text-slate-400 font-mono font-bold">
                      50mm × 50mm
                    </span>
                  </div>
                </div>
              </div>
            </div>
          </div>
        </div>
      </div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { Calendar, Printer, QrCode, AlertCircle, AlertTriangle } from 'lucide-vue-next';
import { EventItem } from '../../types';
import { formatPhilippineDate } from '../../utils/philippineTime';
import Modal from '../common/Modal.vue';

defineProps<{
  isOpen: boolean;
  selectedEvent?: EventItem | null;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const presetSelection = ref<'10' | '25' | '50' | '100' | 'custom'>('50');
const customCount = ref<number | string>(250);

const isCustom = computed(() => presetSelection.value === 'custom');

const validationError = computed<string | null>(() => {
  if (!isCustom.value) return null;
  const raw = customCount.value;
  if (raw === '' || raw === null || raw === undefined) {
    return 'Please enter the number of copies.';
  }
  const val = Number(raw);
  if (isNaN(val) || !Number.isInteger(val) || val <= 0) {
    return 'Please enter a valid positive whole number (e.g., 250).';
  }
  if (val > 1000) {
    return 'Maximum batch limit is 1,000 copies per print job to ensure browser stability.';
  }
  return null;
});

const effectiveBulkCount = computed<number>(() => {
  if (!isCustom.value) {
    return parseInt(presetSelection.value, 10) || 50;
  }
  const val = Number(customCount.value);
  if (isNaN(val) || !Number.isInteger(val) || val <= 0) {
    return 0;
  }
  return Math.min(val, 1000);
});

const isLargeBatchWarning = computed(() => {
  return effectiveBulkCount.value > 300 && !validationError.value;
});

const isPrintDisabled = computed(() => {
  return effectiveBulkCount.value <= 0 || validationError.value !== null;
});

// Smooth in-modal rendering (shows up to 10 sample passes in preview)
const previewCount = computed(() => Math.min(effectiveBulkCount.value, 10));

const handlePrintStock = () => {
  if (isPrintDisabled.value) return;
  document.body.classList.add('is-printing-bulk-stock');
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      document.body.classList.remove('is-printing-bulk-stock');
    }, 400);
  }, 50);
};
</script>

<style scoped>
.bulk-ticket-print-card {
  page-break-after: always;
  break-after: page;
  margin: 0 auto !important;
}

@media print {
  .bulk-ticket-print-card {
    width: 11.5cm !important;
    min-width: 11.5cm !important;
    max-width: 11.5cm !important;
    height: 4.2cm !important;
    min-height: 4.2cm !important;
    max-height: 4.2cm !important;
    box-sizing: border-box !important;
    border-radius: 0 !important;
    overflow: hidden !important;
  }

  .qr-target-container {
    width: 3.4cm !important;
    min-width: 3.4cm !important;
    max-width: 3.4cm !important;
    height: 3.8cm !important;
    min-height: 3.8cm !important;
    padding: 1.5mm !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
  }

  .qr-sticker-placement-box {
    width: 3.0cm !important;
    height: 3.0cm !important;
    min-width: 3.0cm !important;
    min-height: 3.0cm !important;
    box-sizing: border-box !important;
    border: 1.5pt dashed #475569 !important;
    background: #f8fafc !important;
  }
}
</style>
