<template>
  <Modal :is-open="isOpen" @close="$emit('close')" max-width="4xl" title="Official Event Ticket & Pass">
    <div v-if="ticket" class="space-y-6">
      <!-- Printable Landscape Ticket Container (Single Continuous Pass) -->
      <div class="w-full overflow-x-auto pb-2">
        <div
          id="printable-ticket-badge"
          class="relative w-[780px] sm:w-[840px] h-[310px] mx-auto bg-gradient-to-r from-[#091738] via-[#0e2759] to-[#18396f] text-white rounded-3xl shadow-2xl overflow-hidden border border-amber-400/25 flex select-none shrink-0"
        >
          <!-- 1. LEFT: Original White Textured Visual Divider Strip (Design Element Only — Not a Stub) -->
          <div class="w-[210px] sm:w-[220px] bg-gradient-to-b from-slate-50 via-slate-100 to-slate-200 text-slate-900 p-4 flex flex-col justify-between items-center text-center shrink-0 border-r border-slate-300 shadow-inner relative z-10">
            <!-- Branding Header -->
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
                {{ ticket.ticketNumber || 'NCST-2026-00125' }}
              </span>
            </div>

            <!-- Attendee Quick Info on Textured Strip -->
            <div class="w-full text-[9px] text-slate-600 border-t border-slate-300/80 pt-1.5 space-y-0.5">
              <p class="text-[8.5px] font-black tracking-widest text-amber-600 uppercase">
                LIVE @ NCST
              </p>
              <p class="font-bold text-slate-900 truncate text-[10px]">{{ ticket.currentAttendeeName }}</p>
              <p class="font-mono text-blue-700 font-bold text-[9px]">ID: {{ ticket.attendeeStudentId || '2026-00125' }}</p>
            </div>
          </div>

          <!-- 2. RIGHT / MAIN BODY: Full-Bleed Admin Ticket Template & Details (No Covering Panels) -->
          <div class="flex-1 p-5 flex flex-col justify-between relative overflow-hidden">
            <!-- Admin Uploaded / Selected Ticket Artwork Background (100% Full-Bleed across Main Body) -->
            <template v-if="event?.ticketDesignUrl || event?.bannerUrl">
              <img
                :src="event?.ticketDesignUrl || event?.bannerUrl"
                alt="Admin Ticket Artwork"
                class="absolute inset-0 w-full h-full object-cover pointer-events-none opacity-85 transition-[object-position] duration-200"
                :style="{ objectPosition: `center ${event?.ticketDesignOffsetY ?? 20}%` }"
              />
              <div class="absolute inset-0 bg-gradient-to-t from-slate-950/85 via-slate-950/30 to-slate-950/65 pointer-events-none" />
            </template>

            <!-- Built-In Default Subtle Blue-and-Yellow NCST-style Ticket Template (Fallback when no custom design uploaded) -->
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
              <!-- Left Information Details Stack -->
              <div class="space-y-1.5 max-w-[390px]">
                <!-- Event Name & Clean LIVE @ NCST Yellow Tag -->
                <div>
                  <span class="text-[7.5px] uppercase font-bold text-amber-300/90 tracking-wider block drop-shadow-xs">EVENT</span>
                  <h2 class="text-xl sm:text-2xl font-black text-white tracking-tight leading-snug drop-shadow-sm line-clamp-2">
                    {{ event?.title || ticket.eventTitle || 'Campus Event' }}
                  </h2>
                  <p class="text-[11px] sm:text-xs font-black tracking-widest text-amber-300 uppercase drop-shadow-md mt-0.5">
                    LIVE @ NCST
                  </p>
                </div>

                <!-- Date & Time + Venue Grid -->
                <div class="grid grid-cols-2 gap-3">
                  <div>
                    <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">DATE & TIME</span>
                    <p class="text-[9.5px] text-slate-100 font-bold flex items-center gap-1 mt-0.5 drop-shadow-xs">
                      <Calendar class="w-3 h-3 text-amber-400 shrink-0" />
                      <span>{{ event?.date ? formatPhilippineDate(event.date) : 'November 5, 2026' }} · {{ event?.startTime || '6:00 PM' }}</span>
                    </p>
                  </div>
                  <div>
                    <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">VENUE</span>
                    <p class="text-[9.5px] text-amber-300 font-bold truncate mt-0.5 flex items-center gap-1 drop-shadow-xs">
                      <MapPin class="w-3 h-3 text-amber-400 shrink-0" />
                      <span>{{ event?.venue || 'NCST Gymnasium' }}</span>
                    </p>
                  </div>
                </div>

                <!-- Attendee & Student ID Grid -->
                <div class="grid grid-cols-2 gap-3 pt-0.5">
                  <div>
                    <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">ATTENDEE</span>
                    <p class="text-[10.5px] text-white font-extrabold truncate mt-0.5 drop-shadow-xs">
                      {{ ticket.currentAttendeeName }}
                    </p>
                    <p class="text-[8px] text-slate-300 truncate">
                      {{ ticket.course || 'BSIT' }} · {{ ticket.yearLevel || '3rd Year' }} · Sec {{ ticket.section || 'A' }}
                    </p>
                  </div>
                  <div>
                    <span class="text-[7.5px] uppercase font-black text-amber-300/90 tracking-widest block drop-shadow-xs">STUDENT ID</span>
                    <p class="text-[10.5px] text-amber-300 font-mono font-bold truncate mt-0.5 drop-shadow-xs">
                      {{ ticket.attendeeStudentId || '2026-00125' }}
                    </p>
                    <p class="text-[8px] text-emerald-300 font-bold uppercase">
                      {{ ticket.price > 0 ? `₱${ticket.price}.00 (PAID)` : 'FREE PASS' }}
                    </p>
                  </div>
                </div>
              </div>

              <!-- Right: Standalone Clean Official QR Box (50mm × 50mm Physical QR Label Target) -->
              <div class="qr-target-container bg-white text-slate-900 p-2 sm:p-2.5 rounded-2xl shadow-2xl border-2 border-amber-400/80 flex flex-col items-center justify-between shrink-0 w-[148px] min-w-[148px] h-[165px] text-center select-none">
                <span class="text-[7.5px] sm:text-[8px] font-black uppercase text-slate-800 tracking-wider block">
                  OFFICIAL QR CODE
                </span>
                <div class="qr-sticker-placement-box w-[128px] h-[128px] rounded-xl flex flex-col items-center justify-center bg-white p-1">
                  <img v-if="qrUrl" :src="qrUrl" alt="Ticket QR" class="w-full h-full object-contain aspect-square" />
                  <div v-else class="w-full h-full flex items-center justify-center text-[9px] text-slate-400">Loading QR...</div>
                </div>
              </div>
            </div>

            <!-- Footer Strip (Subtle & Clean) -->
            <div class="pt-1.5 border-t border-white/20 flex items-center justify-end text-[8px] text-slate-300 relative z-10">
              <span class="px-2 py-0.5 rounded-full text-[7.5px] font-black uppercase tracking-wider" :class="ticket.ticketStatus === 'used' ? 'bg-blue-500/30 text-blue-300' : 'bg-emerald-500/30 text-emerald-300'">
                {{ ticket.ticketStatus }}
              </span>
            </div>
          </div>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="no-print flex flex-wrap items-center justify-between gap-3 pt-3 border-t border-slate-200 dark:border-[#333333]">
        <div class="text-xs text-slate-500 dark:text-[#b3b3b3]">
          Format: <strong class="text-slate-800 dark:text-slate-200">Official Event Pass</strong>
        </div>

        <div class="flex items-center gap-2">
          <button
            type="button"
            @click="$emit('close')"
            class="px-4 py-2.5 rounded-xl bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold transition cursor-pointer"
          >
            Close
          </button>

          <!-- Cashier-only printing actions -->
          <template v-if="isCashier">
            <button
              type="button"
              @click="handleDownload"
              class="flex items-center gap-1.5 px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-900 dark:bg-[#333333] dark:hover:bg-[#404040] text-white text-xs font-bold shadow transition cursor-pointer"
            >
              <Download class="w-4 h-4" />
              <span>Download PNG Pass</span>
            </button>

            <button
              type="button"
              @click="handlePrint"
              class="flex items-center gap-1.5 px-5 py-2.5 rounded-xl bg-blue-600 hover:bg-blue-700 text-white text-xs font-bold shadow-md transition cursor-pointer"
            >
              <Printer class="w-4 h-4" />
              <span>Print Official Ticket</span>
            </button>
          </template>

          <template v-else>
            <div class="flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-amber-50 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-800/80 text-amber-800 dark:text-amber-300 text-xs font-bold">
              <Lock class="w-3.5 h-3.5" />
              <span>Physical Ticket Printing Restricted to University Cashier</span>
            </div>
          </template>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import QRCode from 'qrcode';
import { Calendar, MapPin, Printer, Download, Lock } from 'lucide-vue-next';
import { Ticket, EventItem } from '../../types';
import { formatPhilippineDate } from '../../utils/philippineTime';
import { useAppStore } from '../../composables/useAppStore';
import Modal from '../common/Modal.vue';

const { currentUser } = useAppStore();
const isCashier = computed(() => currentUser.value?.role === 'cashier');

const props = defineProps<{
  isOpen: boolean;
  ticket: Ticket | null;
  event?: EventItem;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const qrUrl = ref<string>('');

const serialNumber = computed(() => {
  if (!props.ticket) return '0534';
  const num = props.ticket.ticketNumber || props.ticket.id;
  const match = num.match(/\d+/);
  if (match) {
    return match[0].padStart(4, '0');
  }
  return '0534';
});

const generateQR = async () => {
  if (!props.ticket) return;
  const token = props.ticket.ticketNumber || props.ticket.qrToken || props.ticket.id;
  try {
    qrUrl.value = await QRCode.toDataURL(token, {
      errorCorrectionLevel: 'M',
      margin: 1,
      width: 320,
      color: {
        dark: '#000000',
        light: '#ffffff',
      },
    });
  } catch (err) {
    console.error('QR code generation failed:', err);
  }
};

onMounted(() => {
  if (props.ticket) generateQR();
});

watch(() => props.ticket, () => {
  if (props.ticket) generateQR();
}, { deep: true, immediate: true });

const handlePrint = () => {
  if (!isCashier.value) {
    alert('Unauthorized: Physical ticket printing is restricted to the University Cashier only.');
    return;
  }
  document.body.classList.add('is-printing-ticket');
  setTimeout(() => {
    window.print();
    setTimeout(() => {
      document.body.classList.remove('is-printing-ticket');
    }, 400);
  }, 50);
};

const handleDownload = async () => {
  if (!isCashier.value) {
    alert('Unauthorized: Physical ticket pass download is restricted to the University Cashier only.');
    return;
  }
  if (!props.ticket) return;

  const token = props.ticket.ticketNumber || props.ticket.qrToken || props.ticket.id;
  let currentQrData = qrUrl.value;
  if (!currentQrData) {
    try {
      currentQrData = await QRCode.toDataURL(token, {
        errorCorrectionLevel: 'M',
        margin: 1,
        width: 360,
        color: { dark: '#000000', light: '#ffffff' },
      });
      qrUrl.value = currentQrData;
    } catch {}
  }

  const canvas = document.createElement('canvas');
  canvas.width = 1680;
  canvas.height = 620;
  const ctx = canvas.getContext('2d');
  if (!ctx) return;

  const renderCanvasContent = (bgImg?: HTMLImageElement) => {
    // 1. Background Gradient / Custom Artwork (Subtle blue-to-yellow treatment)
    const grad = ctx.createLinearGradient(0, 0, 1680, 620);
    grad.addColorStop(0, '#091738');
    grad.addColorStop(0.5, '#0e2759');
    grad.addColorStop(0.85, '#16356b');
    grad.addColorStop(1, '#1b3b68');
    ctx.fillStyle = grad;
    ctx.roundRect(0, 0, 1680, 620, 30);
    ctx.fill();

    // Subtle ambient warm yellow/amber glow in upper-right when using default template
    if (!bgImg) {
      const yellowGlow = ctx.createRadialGradient(1500, 80, 20, 1500, 80, 500);
      yellowGlow.addColorStop(0, 'rgba(245, 158, 11, 0.14)');
      yellowGlow.addColorStop(1, 'rgba(245, 158, 11, 0)');
      ctx.fillStyle = yellowGlow;
      ctx.beginPath();
      ctx.roundRect(420, 0, 1260, 620, [0, 30, 30, 0]);
      ctx.fill();
    }

    // Draw full-bleed custom background image across right body if provided
    if (bgImg) {
      ctx.save();
      ctx.beginPath();
      ctx.roundRect(420, 0, 1260, 620, [0, 30, 30, 0]);
      ctx.clip();
      ctx.globalAlpha = 0.85;
      ctx.drawImage(bgImg, 420, 0, 1260, 620);
      ctx.restore();

      const overGrad = ctx.createLinearGradient(420, 0, 420, 620);
      overGrad.addColorStop(0, 'rgba(15, 23, 42, 0.7)');
      overGrad.addColorStop(0.5, 'rgba(15, 23, 42, 0.3)');
      overGrad.addColorStop(1, 'rgba(15, 23, 42, 0.85)');
      ctx.fillStyle = overGrad;
      ctx.beginPath();
      ctx.roundRect(420, 0, 1260, 620, [0, 30, 30, 0]);
      ctx.fill();
    }

    // 2. Left Textured Section (Divider & Branding)
    const leftGrad = ctx.createLinearGradient(0, 0, 420, 620);
    leftGrad.addColorStop(0, '#f8fafc');
    leftGrad.addColorStop(0.5, '#f1f5f9');
    leftGrad.addColorStop(1, '#e2e8f0');
    ctx.fillStyle = leftGrad;
    ctx.beginPath();
    ctx.roundRect(0, 0, 420, 620, [30, 0, 0, 30]);
    ctx.fill();

    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 26px serif';
    ctx.fillText('NCST', 175, 140);
    ctx.fillStyle = '#64748b';
    ctx.font = 'bold 14px sans-serif';
    ctx.fillText('DASMARIÑAS, CAVITE', 120, 170);

    // Prominent Control Number Box in Left Section
    ctx.fillStyle = '#ffffff';
    ctx.roundRect(40, 240, 340, 110, 16);
    ctx.fill();
    ctx.strokeStyle = '#cbd5e1';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = '#64748b';
    ctx.font = 'bold 15px sans-serif';
    ctx.fillText('CONTROL NO.', 150, 275);

    ctx.fillStyle = '#e11d48';
    ctx.font = 'bold 24px monospace';
    ctx.fillText(props.ticket!.ticketNumber || 'NCST-2026-00125', 75, 320);

    ctx.fillStyle = '#0f172a';
    ctx.font = 'bold 18px sans-serif';
    ctx.fillText(props.ticket!.currentAttendeeName, 60, 480);
    ctx.fillStyle = '#1d4ed8';
    ctx.font = 'bold 16px monospace';
    ctx.fillText(`ID: ${props.ticket!.attendeeStudentId || '2026-00125'}`, 60, 510);

    // 3. Middle / Right Main Information Area (Header on Left)
    ctx.fillStyle = '#f59e0b';
    ctx.font = 'bold 20px serif';
    ctx.fillText('NATIONAL COLLEGE OF SCIENCE & TECHNOLOGY', 470, 70);

    ctx.fillStyle = '#cbd5e1';
    ctx.font = '14px sans-serif';
    ctx.fillText('Amafel Building, Aguinaldo Highway, Dasmariñas City, Cavite', 470, 95);

    // Event Name
    ctx.fillStyle = '#fcd34d';
    ctx.font = 'bold 14px sans-serif';
    ctx.fillText('EVENT', 470, 160);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 40px sans-serif';
    const title = props.event?.title || props.ticket!.eventTitle || 'Campus Event';
    ctx.fillText(title, 470, 205);

    // Date & Time + Venue
    ctx.fillStyle = '#fcd34d';
    ctx.font = 'bold 13px sans-serif';
    ctx.fillText('DATE & TIME', 470, 285);
    ctx.fillText('VENUE', 840, 285);

    ctx.fillStyle = '#f8fafc';
    ctx.font = 'bold 18px sans-serif';
    const eventDate = props.event?.date ? new Date(props.event.date).toLocaleDateString([], { month: 'long', day: 'numeric', year: 'numeric' }) : 'November 5, 2026';
    ctx.fillText(`${eventDate} · ${props.event?.startTime || '6:00 PM'}`, 470, 315);
    ctx.fillText(props.event?.venue || 'NCST Gymnasium', 840, 315);

    // Attendee & Student ID
    ctx.fillStyle = '#fcd34d';
    ctx.font = 'bold 13px sans-serif';
    ctx.fillText('ATTENDEE', 470, 375);
    ctx.fillText('STUDENT ID', 840, 375);

    ctx.fillStyle = '#ffffff';
    ctx.font = 'bold 20px sans-serif';
    ctx.fillText(props.ticket!.currentAttendeeName, 470, 405);
    ctx.fillStyle = '#fde047';
    ctx.font = 'bold 20px monospace';
    ctx.fillText(props.ticket!.attendeeStudentId || '2026-00125', 840, 405);

    // Footer Strip
    ctx.fillStyle = '#cbd5e1';
    ctx.font = '14px sans-serif';
    ctx.fillText(`${props.ticket!.course || 'BSIT'} - ${props.ticket!.yearLevel || '3rd Year'} - Sec ${props.ticket!.section || 'A'}`, 470, 565);

    // 4. Standalone QR Code Area (Nothing above QR)
    ctx.fillStyle = '#ffffff';
    ctx.roundRect(1400, 150, 230, 245, 20);
    ctx.fill();
    ctx.strokeStyle = '#f59e0b';
    ctx.lineWidth = 2;
    ctx.stroke();

    ctx.fillStyle = '#334155';
    ctx.font = 'bold 13px sans-serif';
    ctx.fillText('OFFICIAL QR CODE', 1450, 180);

    if (currentQrData) {
      const qrImg = new Image();
      qrImg.onload = () => {
        ctx.drawImage(qrImg, 1420, 195, 190, 190);

        const link = document.createElement('a');
        link.href = canvas.toDataURL('image/png');
        link.download = `NCST_Ticket_${props.ticket?.ticketNumber || serialNumber.value}.png`;
        link.click();
      };
      qrImg.src = currentQrData;
    } else {
      const link = document.createElement('a');
      link.href = canvas.toDataURL('image/png');
      link.download = `NCST_Ticket_${props.ticket?.ticketNumber || serialNumber.value}.png`;
      link.click();
    }
  };

  const ticketArtworkUrl = props.event?.ticketDesignUrl || props.event?.bannerUrl;
  if (ticketArtworkUrl) {
    const bg = new Image();
    bg.crossOrigin = 'anonymous';
    bg.onload = () => renderCanvasContent(bg);
    bg.onerror = () => renderCanvasContent();
    bg.src = ticketArtworkUrl;
  } else {
    renderCanvasContent();
  }
};
</script>

<style scoped>
@media print {
  .qr-target-container {
    width: 60mm !important;
    min-width: 60mm !important;
    height: 65mm !important;
    min-height: 65mm !important;
    padding: 2.5mm !important;
    box-sizing: border-box !important;
    background: #ffffff !important;
  }

  .qr-sticker-placement-box {
    width: 53mm !important;
    height: 53mm !important;
    min-width: 53mm !important;
    min-height: 53mm !important;
    box-sizing: border-box !important;
  }
}
</style>
