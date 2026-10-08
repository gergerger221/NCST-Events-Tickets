<template>
  <Modal
    :is-open="isOpen"
    @close="$emit('close')"
    title="OCR Physical Ticket Scanner"
    subtitle="Optical Character Recognition for printed physical tickets & offline passes"
    max-width="3xl"
  >
    <div class="space-y-6">
      <div class="p-3.5 bg-blue-50 dark:bg-blue-950/40 border border-blue-200 dark:border-blue-800/60 rounded-xl text-xs text-blue-800 dark:text-blue-200">
        <Sparkles class="w-4 h-4 inline mr-1.5 text-blue-600 dark:text-blue-400 -mt-0.5" />
        Capture or upload an image of a physical printed ticket for document pre-fill. <strong>Note:</strong> Under NCST institutional security policy, only an official QR code scan can authorize venue entry and record attendance.
      </div>

      <!-- Action Controls -->
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <button
          @click="fileInputRef?.click()"
          class="flex items-center justify-center gap-2 p-3 bg-slate-50 dark:bg-[#1e1e1e] hover:bg-slate-100 dark:hover:bg-[#2c2c2c] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-slate-800 dark:text-white transition cursor-pointer shadow-sm"
        >
          <Upload class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          Upload Ticket Image
        </button>
        <input
          ref="fileInputRef"
          type="file"
          accept="image/*"
          class="hidden"
          @change="handleFileUpload"
        />

        <button
          v-for="(preset, idx) in sampleTicketPresets"
          :key="idx"
          @click="loadPreset(preset)"
          class="flex items-center justify-center gap-2 p-3 bg-slate-50 dark:bg-[#1e1e1e] hover:bg-slate-100 dark:hover:bg-[#2c2c2c] border border-slate-200 dark:border-[#3a3a3a] rounded-xl text-xs font-semibold text-blue-700 dark:text-blue-300 transition cursor-pointer text-left shadow-sm"
        >
          <TicketIcon class="w-4 h-4 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
          <span class="truncate">{{ preset.label }}</span>
        </button>
      </div>

      <!-- Processing State -->
      <div v-if="isProcessing" class="p-6 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl flex flex-col items-center justify-center text-center">
        <Loader2 class="w-8 h-8 text-blue-600 dark:text-blue-400 animate-spin mb-2" />
        <p class="text-sm font-bold text-slate-900 dark:text-white">Scanning Ticket with OCR Engine...</p>
        <p class="text-xs text-slate-500 dark:text-[#b3b3b3] mt-1">Analyzing optical characters and extracting attendee data</p>
      </div>

      <!-- Preview & Editable Verification Form -->
      <div v-if="imageSrc && !isProcessing" class="grid grid-cols-1 md:grid-cols-2 gap-6 p-5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl shadow-sm">
        <!-- Left: Image Preview -->
        <div class="space-y-3">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
              <Eye class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
              Ticket Image
            </span>
            <span class="text-[10px] bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 px-2 py-0.5 rounded font-semibold border border-emerald-200 dark:border-emerald-500/30">
              Captured
            </span>
          </div>
          <div class="rounded-xl overflow-hidden border border-slate-200 dark:border-[#3a3a3a] bg-white dark:bg-[#121212] aspect-video flex items-center justify-center p-2">
            <img :src="imageSrc" alt="Scanned Physical Ticket" class="max-h-full object-contain rounded-lg" />
          </div>

          <div v-if="rawText" class="p-3 bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-xl">
            <span class="text-[11px] font-bold text-slate-600 dark:text-slate-400 uppercase block mb-1">Raw OCR Output</span>
            <pre class="text-[11px] font-mono text-slate-700 dark:text-slate-300 whitespace-pre-wrap max-h-20 overflow-y-auto">{{ rawText }}</pre>
          </div>
        </div>

        <!-- Right: Verification Form -->
        <div class="space-y-3.5">
          <div class="flex items-center justify-between">
            <span class="text-xs font-bold text-slate-700 dark:text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
              <UserCheck class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" />
              Verification & Registration
            </span>
            <span class="text-[10px] text-amber-700 dark:text-amber-300 bg-amber-100 dark:bg-amber-500/10 px-2 py-0.5 rounded border border-amber-200 dark:border-amber-500/20">
              Verify before saving
            </span>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">
              Extracted Attendee Name *
            </label>
            <input
              type="text"
              v-model="extractedName"
              class="w-full bg-white dark:bg-[#242424] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-bold text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              placeholder="Attendee Full Name"
            />
          </div>

          <div class="grid grid-cols-2 gap-3">
            <div>
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Physical Ticket No.</label>
              <input
                type="text"
                v-model="extractedTicketNo"
                class="w-full bg-white dark:bg-[#242424] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs font-mono text-blue-600 dark:text-blue-400 focus:outline-none focus:border-blue-500"
              />
            </div>
            <div>
              <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Fee / Price (PHP)</label>
              <input
                type="number"
                v-model.number="price"
                class="w-full bg-white dark:bg-[#242424] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white focus:outline-none focus:border-blue-500"
              />
            </div>
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Affiliation / Department</label>
            <input
              type="text"
              v-model="extractedDepartment"
              class="w-full bg-white dark:bg-[#242424] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-3 py-2 text-xs text-slate-800 dark:text-slate-200 focus:outline-none focus:border-blue-500"
              placeholder="e.g. College of Computer Studies"
            />
          </div>

          <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Target Event</label>
            <input
              type="text"
              :value="extractedEvent || currentEvent?.title"
              readonly
              class="w-full bg-slate-100 dark:bg-[#242424]/60 border border-slate-200 dark:border-[#333333] rounded-xl px-3 py-2 text-xs text-slate-500 dark:text-[#b3b3b3] cursor-not-allowed"
            />
          </div>

          <div class="pt-2">
            <button
              type="button"
              @click="handleVerifyAndSave"
              :disabled="!extractedName.trim()"
              class="w-full py-2.5 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white rounded-xl text-xs font-bold transition shadow-md flex items-center justify-center gap-2 cursor-pointer"
            >
              <CheckCircle class="w-4 h-4" />
              Register Physical Ticket Record (Requires QR Scan for Entry)
            </button>
            <p class="text-[10px] text-slate-500 dark:text-slate-400 text-center mt-2">
              Printed text alone does not authorize entry. The attendee must scan their official QR code at the entrance scanner.
            </p>
          </div>
        </div>
      </div>

      <!-- Success Alert -->
      <div v-if="successMessage" class="p-4 bg-emerald-50 dark:bg-emerald-950/60 border border-emerald-200 dark:border-emerald-500/80 rounded-2xl flex items-center gap-3 text-emerald-800 dark:text-emerald-300 text-xs font-semibold animate-pulse">
        <CheckCircle class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" />
        <span>{{ successMessage }}</span>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { createWorker } from 'tesseract.js';
import { FileText, Camera, Upload, CheckCircle, Loader2, Eye, Sparkles, Ticket as TicketIcon, UserCheck } from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import Modal from '../common/Modal.vue';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { currentEvent, createPhysicalTicket, validateAndRecordScan } = useAppStore();

const imageSrc = ref<string | null>(null);
const isProcessing = ref(false);
const rawText = ref('');
const extractedTicketNo = ref('');
const extractedName = ref('');
const extractedEvent = ref('');
const extractedDepartment = ref('College Department');
const price = ref(0);
const successMessage = ref<string | null>(null);
const fileInputRef = ref<HTMLInputElement | null>(null);

const sampleTicketPresets = [
  {
    label: 'Sample Printed Pass (Christian David)',
    name: 'Christian David',
    ticketNo: 'PHYS-2026-90812',
    event: 'School Foundation Day 2026',
    dept: 'College of Engineering & Architecture',
    price: 0,
    imageSvg: `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="600" height="280" viewBox="0 0 600 280"><rect width="600" height="280" fill="%230f172a" rx="16"/><rect x="10" y="10" width="580" height="260" fill="none" stroke="%236366f1" stroke-dasharray="6,6" stroke-width="2" rx="12"/><text x="40" y="55" fill="%23a5b4fc" font-family="sans-serif" font-weight="bold" font-size="20">OFFICIAL PHYSICAL PASS</text><text x="40" y="90" fill="%23ffffff" font-family="sans-serif" font-weight="bold" font-size="22">TICKET NO: PHYS-2026-90812</text><text x="40" y="130" fill="%23cbd5e1" font-family="sans-serif" font-size="18">NAME: Christian David</text><text x="40" y="165" fill="%23cbd5e1" font-family="sans-serif" font-size="18">EVENT: School Foundation Day 2026</text><text x="40" y="200" fill="%2394a3b8" font-family="sans-serif" font-size="15">DEPT: College of Engineering</text><text x="40" y="235" fill="%2334d399" font-family="sans-serif" font-weight="bold" font-size="14">STATUS: AUTHORIZED ENTRANCE</text></svg>`,
  },
  {
    label: 'Sample Summit Pass (Maria Santos)',
    name: 'Maria Cristina Santos',
    ticketNo: 'PHYS-2026-77341',
    event: 'Integ 2: IT Summit & Hackathon Expo',
    dept: 'College of Computer Studies',
    price: 150,
    imageSvg: `data:image/svg+xml;utf8,<svg xmlns="http://www.w3.org/2000/svg" width="600" height="280" viewBox="0 0 600 280"><rect width="600" height="280" fill="%231e1b4b" rx="16"/><rect x="10" y="10" width="580" height="260" fill="none" stroke="%23ec4899" stroke-dasharray="6,6" stroke-width="2" rx="12"/><text x="40" y="55" fill="%23f472b6" font-family="sans-serif" font-weight="bold" font-size="20">CONFERENCE PASS (PHYSICAL)</text><text x="40" y="90" fill="%23ffffff" font-family="sans-serif" font-weight="bold" font-size="22">TICKET NO: PHYS-2026-77341</text><text x="40" y="130" fill="%23cbd5e1" font-family="sans-serif" font-size="18">NAME: Maria Cristina Santos</text><text x="40" y="165" fill="%23cbd5e1" font-family="sans-serif" font-size="18">EVENT: Integ 2: IT Summit</text><text x="40" y="200" fill="%2394a3b8" font-family="sans-serif" font-size="15">DEPT: College of Computer Studies</text><text x="40" y="235" fill="%2338bdf8" font-family="sans-serif" font-weight="bold" font-size="14">FEE: PHP 150.00 PAID</text></svg>`,
  },
];

const handleFileUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (file) {
    const reader = new FileReader();
    reader.onload = () => {
      imageSrc.value = reader.result as string;
      runOCR(reader.result as string);
    };
    reader.readAsDataURL(file);
  }
};

const loadPreset = (preset: typeof sampleTicketPresets[0]) => {
  imageSrc.value = preset.imageSvg;
  extractedName.value = preset.name;
  extractedTicketNo.value = preset.ticketNo;
  extractedEvent.value = preset.event;
  extractedDepartment.value = preset.dept;
  price.value = preset.price;
  rawText.value = `TICKET NO: ${preset.ticketNo}\nNAME: ${preset.name}\nEVENT: ${preset.event}\nDEPT: ${preset.dept}\nSTATUS: VERIFIED`;
};

const runOCR = async (input: string) => {
  isProcessing.value = true;
  rawText.value = '';

  try {
    // If input is an SVG data URI with embedded text, extract it directly offline
    if (input.startsWith('data:image/svg+xml')) {
      const decoded = decodeURIComponent(input);
      const ticketMatch = decoded.match(/TICKET NO:\s*([A-Z0-9-]+)/i);
      const nameMatch = decoded.match(/NAME:\s*([A-Za-z\s]+)/i);
      const eventMatch = decoded.match(/EVENT:\s*([A-Za-z0-9:\s]+)/i);
      const deptMatch = decoded.match(/DEPT:\s*([A-Za-z\s]+)/i);

      if (ticketMatch || nameMatch) {
        if (ticketMatch) extractedTicketNo.value = ticketMatch[1].trim();
        if (nameMatch) extractedName.value = nameMatch[1].trim();
        if (eventMatch) extractedEvent.value = eventMatch[1].trim();
        if (deptMatch) extractedDepartment.value = deptMatch[1].trim();
        rawText.value = `[OFFLINE VECTOR PARSE]\nTicket: ${extractedTicketNo.value}\nName: ${extractedName.value}`;
        return;
      }
    }

    // Attempt local/cached Tesseract worker with 4-second timeout to prevent offline hang
    const workerPromise = createWorker('eng');
    const timeoutPromise = new Promise<never>((_, reject) =>
      setTimeout(() => reject(new Error('OCR offline timeout')), 4000)
    );

    const worker = await Promise.race([workerPromise, timeoutPromise]);
    const ret = await worker.recognize(input);
    const text = ret.data.text;
    rawText.value = text;

    const lines = text.split('\n').map(l => l.trim()).filter(Boolean);
    let foundName = '';
    let foundTicket = '';
    let foundEvent = currentEvent.value?.title || '';
    let foundDept = 'College Department';

    lines.forEach(line => {
      const lower = line.toLowerCase();
      if (lower.includes('ticket') || lower.includes('no:') || lower.includes('id:')) {
        const match = line.replace(/ticket\s*(no|id)?:?/i, '').trim();
        if (match) foundTicket = match;
      } else if (lower.includes('name:') || lower.includes('attendee:')) {
        const match = line.replace(/(name|attendee):?/i, '').trim();
        if (match) foundName = match;
      } else if (lower.includes('event:')) {
        const match = line.replace(/event:?/i, '').trim();
        if (match) foundEvent = match;
      } else if (lower.includes('dept:') || lower.includes('department:')) {
        const match = line.replace(/(dept|department):?/i, '').trim();
        if (match) foundDept = match;
      }
    });

    if (!foundName && lines.length > 0) foundName = lines[0].slice(0, 30);
    if (!foundTicket) foundTicket = `PHYS-${Math.floor(10000 + Math.random() * 90000)}`;

    extractedName.value = foundName;
    extractedTicketNo.value = foundTicket;
    extractedEvent.value = foundEvent;
    extractedDepartment.value = foundDept;

    await worker.terminate();
  } catch (_err) {
    // Offline fallback: populate template for manual verification
    if (!extractedTicketNo.value) {
      extractedTicketNo.value = `PHYS-${Math.floor(10000 + Math.random() * 90000)}`;
    }
    if (!extractedName.value) {
      extractedName.value = 'Physical Ticket Attendee';
    }
    rawText.value = `[Offline Mode] OCR engine offline or model not cached. Template pre-populated for manual verification.`;
  } finally {
    isProcessing.value = false;
  }
};

const handleVerifyAndSave = () => {
  if (!extractedName.value.trim()) return;

  const newTicket = createPhysicalTicket(
    currentEvent.value?.id || 'evt-fnd-2026',
    extractedName.value.trim(),
    extractedDepartment.value.trim(),
    undefined,
    price.value,
    rawText.value || `Ticket: ${extractedTicketNo.value}`
  );

  successMessage.value = `Physical Pass ${newTicket.id} registered for "${extractedName.value}". Official QR scan is required at the entrance to record PRESENT.`;

  setTimeout(() => {
    successMessage.value = null;
    emit('close');
  }, 2200);
};
</script>
