<template>
  <Modal
    :is-open="isOpen"
    @close="$emit('close')"
    title="Test Scanner on Mobile Phone"
    subtitle="Scan the QR code with your phone camera to open the app directly on your mobile device"
    max-width="md"
  >
    <div class="space-y-5 text-center">
      <!-- QR Code Container -->
      <div class="flex flex-col items-center justify-center p-4 bg-white dark:bg-[#181818] rounded-2xl border border-slate-200 dark:border-[#333333] shadow-inner">
        <canvas ref="qrCanvas" class="rounded-xl shadow-md max-w-[200px] aspect-square"></canvas>
        <span class="mt-3 font-mono text-xs font-bold text-blue-600 dark:text-blue-400 bg-blue-50 dark:bg-blue-950/40 px-3 py-1.5 rounded-lg border border-blue-200 dark:border-blue-900/50 break-all select-all">
          {{ networkUrl }}
        </span>
      </div>

      <!-- Instructions -->
      <div class="text-left space-y-3 p-4 bg-slate-50 dark:bg-[#222222] rounded-2xl border border-slate-200 dark:border-[#333333] text-xs">
        <h4 class="font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
          <Smartphone class="w-4 h-4 text-blue-600 dark:text-blue-400" />
          <span>Instant Mobile Scanner Access:</span>
        </h4>
        <ol class="list-decimal list-inside space-y-1.5 text-slate-600 dark:text-[#b3b3b3]">
          <li>Open the Cloudflare link or scan the QR code above with your phone camera.</li>
          <li>Works seamlessly over any Wi-Fi or Mobile Data (4G/5G) with trusted SSL security.</li>
          <li>Switch to <strong>Teacher</strong> or <strong>Admin</strong> and tap <strong>"Scan Ticket"</strong> to scan student QR passes in real time!</li>
        </ol>
      </div>

      <div class="flex justify-end pt-2">
        <button
          @click="$emit('close')"
          class="px-5 py-2.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow cursor-pointer w-full"
        >
          Got It, Start Testing
        </button>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, watch, nextTick } from 'vue';
import { Smartphone } from 'lucide-vue-next';
import QRCode from 'qrcode';
import Modal from './Modal.vue';

const props = defineProps<{
  isOpen: boolean;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const getMobileUrl = () => {
  if (typeof window !== 'undefined' && window.location.origin && !window.location.origin.includes('localhost') && !window.location.origin.includes('127.0.0.1')) {
    return window.location.origin;
  }
  return 'https://192.168.27.247:5173';
};

const networkUrl = ref(getMobileUrl());

const renderQR = async () => {
  await nextTick();
  networkUrl.value = getMobileUrl();
  if (qrCanvas.value) {
    QRCode.toCanvas(qrCanvas.value, networkUrl.value, {
      width: 200,
      margin: 2,
      color: {
        dark: '#000000',
        light: '#ffffff'
      }
    });
  }
};

watch(() => props.isOpen, (newVal) => {
  if (newVal) {
    renderQR();
  }
});
</script>
