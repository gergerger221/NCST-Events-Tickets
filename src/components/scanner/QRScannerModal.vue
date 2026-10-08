<template>
  <div>
    <Modal
      :is-open="isOpen"
      @close="$emit('close')"
      title="Entrance QR Scanner"
      :subtitle="`Active Event: ${currentEvent?.title || 'None'} (${currentEvent?.code || ''})`"
      max-width="xl"
    >
      <div class="space-y-3.5">
        <!-- 1. Top Utility Header: Network Resilience & Audio/Haptic Controls -->
        <div class="flex flex-wrap items-center justify-between gap-2 p-2.5 bg-slate-50 dark:bg-[#1a1a1a] border border-slate-200 dark:border-[#333] rounded-2xl text-xs">
          <!-- Network Status & Offline Queue Pill -->
          <div class="flex items-center gap-2">
            <div
              v-if="isOnline"
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 font-bold text-[11px]"
            >
              <Wifi class="w-3.5 h-3.5" />
              <span>Online • Real-time Sync</span>
            </div>
            <div
              v-else
              class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-700 dark:text-amber-300 font-bold text-[11px]"
            >
              <WifiOff class="w-3.5 h-3.5" />
              <span>Offline ({{ queuedCount }} Queued)</span>
            </div>

            <!-- Manual Sync Button if pending scans exist -->
            <button
              v-if="hasPendingScans && isOnline"
              type="button"
              @click="triggerManualSync"
              :disabled="isSyncing"
              class="inline-flex items-center gap-1 px-2 py-0.5 rounded-lg bg-blue-600 hover:bg-blue-700 text-white font-bold text-[10px] transition cursor-pointer disabled:opacity-50"
            >
              <RotateCw class="w-3 h-3" :class="{ 'animate-spin': isSyncing }" />
              <span>{{ isSyncing ? 'Syncing...' : 'Sync Now' }}</span>
            </button>
          </div>

          <!-- Rapid Mode & Feedback Toggles -->
          <div class="flex items-center gap-1.5">
            <!-- Continuous Rapid Mode Toggle -->
            <button
              type="button"
              @click="toggleContinuousMode"
              class="px-2.5 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1.5 border cursor-pointer"
              :class="continuousScanMode
                ? 'bg-blue-600 text-white border-blue-600 shadow-xs'
                : 'bg-white dark:bg-[#252525] text-slate-700 dark:text-slate-300 border-slate-300 dark:border-[#3a3a3a] hover:bg-slate-100 dark:hover:bg-[#303030]'"
              :title="continuousScanMode ? 'Rapid Scan active: auto-resumes next scan' : 'Single Scan mode'"
            >
              <Zap class="w-3.5 h-3.5" :class="continuousScanMode ? 'text-amber-300 fill-amber-300' : 'text-slate-400'" />
              <span>{{ continuousScanMode ? 'Rapid Scan' : 'Standard' }}</span>
            </button>

            <!-- Sound Toggle -->
            <button
              type="button"
              @click="toggleSound"
              class="p-1.5 rounded-lg text-[11px] font-bold border transition cursor-pointer"
              :class="sounds.soundEnabled.value
                ? 'bg-white dark:bg-[#252525] text-blue-600 dark:text-blue-400 border-slate-300 dark:border-[#3a3a3a] hover:bg-slate-100'
                : 'bg-slate-100 dark:bg-[#202020] text-slate-400 border-slate-200 dark:border-[#333] hover:text-slate-600'"
              :title="sounds.soundEnabled.value ? 'Mute Scan Sounds' : 'Unmute Scan Sounds'"
            >
              <Volume2 v-if="sounds.soundEnabled.value" class="w-3.5 h-3.5" />
              <VolumeX v-else class="w-3.5 h-3.5" />
            </button>

            <!-- Haptic Vibration Toggle -->
            <button
              type="button"
              @click="toggleHaptics"
              class="p-1.5 rounded-lg text-[11px] font-bold border transition cursor-pointer"
              :class="sounds.hapticsEnabled.value
                ? 'bg-white dark:bg-[#252525] text-purple-600 dark:text-purple-400 border-slate-300 dark:border-[#3a3a3a] hover:bg-slate-100'
                : 'bg-slate-100 dark:bg-[#202020] text-slate-400 border-slate-200 dark:border-[#333] hover:text-slate-600'"
              :title="sounds.hapticsEnabled.value ? 'Disable Vibration' : 'Enable Mobile Vibration'"
            >
              <Vibrate v-if="sounds.hapticsEnabled.value" class="w-3.5 h-3.5" />
              <Smartphone v-else class="w-3.5 h-3.5" />
            </button>
          </div>
        </div>

        <!-- 2. Live Camera Viewfinder & Device Controls -->
        <div class="flex flex-col items-center space-y-2.5">
          <!-- Camera Device Selector & Flip Toolbar -->
          <div class="w-full flex items-center justify-between gap-2 px-1 text-xs">
            <div class="flex items-center gap-2">
              <span class="text-[11px] text-slate-500 dark:text-slate-400 font-bold">Lens:</span>
              <select
                v-if="availableCameras.length > 1"
                v-model="selectedCameraId"
                @change="handleCameraSelectChange"
                class="bg-white dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] rounded-lg px-2 py-1 text-[11px] text-slate-800 dark:text-slate-200 focus:outline-none cursor-pointer max-w-[145px] truncate"
              >
                <option v-for="cam in availableCameras" :key="cam.id" :value="cam.id">
                  {{ cam.label || `Camera ${cam.id.slice(0, 4)}` }}
                </option>
              </select>
              <span v-else class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span>
                <span>{{ facingMode === 'environment' ? 'Rear Camera' : 'Front Camera' }}</span>
              </span>
            </div>

            <!-- Quick Flip Camera Button & Flashlight Toggle -->
            <div class="flex items-center gap-1.5">
              <!-- Torch / Flashlight Toggle (if supported) -->
              <button
                v-if="hasTorch"
                type="button"
                @click="toggleTorch"
                class="px-2 py-1 rounded-lg text-[11px] font-bold transition flex items-center gap-1 border cursor-pointer"
                :class="isTorchOn
                  ? 'bg-amber-400 text-slate-900 border-amber-500 shadow-xs'
                  : 'bg-slate-100 hover:bg-slate-200 dark:bg-[#2a2a2a] text-slate-700 dark:text-slate-200 border-slate-300 dark:border-[#404040]'"
                :title="isTorchOn ? 'Turn Flashlight Off' : 'Turn Flashlight On'"
              >
                <Flashlight class="w-3.5 h-3.5" :class="{ 'fill-slate-900': isTorchOn }" />
                <span>{{ isTorchOn ? 'Flash ON' : 'Flash' }}</span>
              </button>

              <button
                type="button"
                @click="toggleCameraFacing"
                class="px-2.5 py-1 bg-slate-100 hover:bg-slate-200 dark:bg-[#2a2a2a] dark:hover:bg-[#333] text-slate-700 dark:text-slate-200 rounded-lg text-[11px] font-bold transition flex items-center gap-1.5 border border-slate-300/80 dark:border-[#404040] cursor-pointer"
                :title="`Switch to ${facingMode === 'environment' ? 'Front' : 'Rear'} Camera`"
              >
                <RotateCw class="w-3.5 h-3.5 text-blue-500" />
                <span>{{ facingMode === 'environment' ? 'Flip to Front' : 'Flip to Rear' }}</span>
              </button>
            </div>

            <!-- Hidden File Input for Emergency Fallback -->
            <input
              type="file"
              ref="fileInputRef"
              accept="image/*"
              @change="handleFileUpload"
              class="hidden"
            />
          </div>

          <!-- Professional Scanner Viewport Container -->
          <div class="relative w-full max-w-sm aspect-square bg-slate-950 rounded-3xl overflow-hidden border border-slate-700/60 shadow-2xl flex items-center justify-center">
            <!-- html5-qrcode video container -->
            <div id="vue-interactive-qr-reader-container" class="w-full h-full" />

            <!-- Active Viewfinder Shading & Scanning Frame Overlay -->
            <div
              v-if="cameraActive"
              class="pointer-events-none absolute inset-0 flex items-center justify-center z-10"
            >
              <!-- Central Scanning Target Area (Responsive Box) -->
              <div
                class="relative w-[230px] h-[230px] rounded-2xl transition-all duration-300 scanner-cutout-frame"
                :class="[
                  qrDetected
                    ? 'border-2 border-emerald-400 shadow-[0_0_25px_rgba(16,185,129,0.6)]'
                    : 'border border-blue-400/30'
                ]"
              >
                <!-- 4 Strong Corner Brackets -->
                <div
                  class="scanner-corner absolute -top-1 -left-1 w-7 h-7 border-t-3 border-l-3 rounded-tl-xl transition-colors duration-200"
                  :class="qrDetected ? 'border-emerald-400' : 'border-blue-500 dark:border-blue-400'"
                />
                <div
                  class="scanner-corner absolute -top-1 -right-1 w-7 h-7 border-t-3 border-r-3 rounded-tr-xl transition-colors duration-200"
                  :class="qrDetected ? 'border-emerald-400' : 'border-blue-500 dark:border-blue-400'"
                />
                <div
                  class="scanner-corner absolute -bottom-1 -left-1 w-7 h-7 border-b-3 border-l-3 rounded-bl-xl transition-colors duration-200"
                  :class="qrDetected ? 'border-emerald-400' : 'border-blue-500 dark:border-blue-400'"
                />
                <div
                  class="scanner-corner absolute -bottom-1 -right-1 w-7 h-7 border-b-3 border-r-3 rounded-br-xl transition-colors duration-200"
                  :class="qrDetected ? 'border-emerald-400' : 'border-blue-500 dark:border-blue-400'"
                />

                <!-- Smooth Volumetric Scanning Sweep Beam -->
                <div
                  v-if="!isScanningPaused && !qrDetected"
                  class="scanner-sweep-beam"
                />

                <!-- Instant Green Flash Glow upon QR Detection -->
                <div
                  v-if="qrDetected"
                  class="absolute inset-0 bg-emerald-400/20 rounded-2xl animate-ping opacity-75"
                />
              </div>
            </div>

            <!-- Camera Error State with Mobile Guidance -->
            <div v-if="cameraError" class="absolute inset-0 bg-slate-900/95 dark:bg-[#121212]/95 flex flex-col items-center justify-center p-6 text-center z-20">
              <AlertTriangle class="w-10 h-10 text-amber-400 mb-2" />
              <p class="text-xs font-bold text-slate-100 leading-snug max-w-xs">{{ cameraError }}</p>
              <p class="text-[11px] text-slate-400 mt-1 max-w-xs leading-tight">
                Tip: If permissions were blocked, tap the lock/settings icon next to the URL in your browser and select "Allow Camera".
              </p>
              <div class="flex items-center gap-2 mt-4">
                <button
                  type="button"
                  @click="startCamera"
                  class="px-3.5 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition shadow-md cursor-pointer flex items-center gap-1.5"
                >
                  <RotateCw class="w-3.5 h-3.5" />
                  <span>Retry Camera</span>
                </button>
                <button
                  type="button"
                  @click="fileInputRef?.click()"
                  class="px-3.5 py-2 bg-slate-800 hover:bg-slate-700 text-white rounded-xl text-xs font-bold transition border border-slate-700 cursor-pointer flex items-center gap-1.5"
                >
                  <Upload class="w-3.5 h-3.5 text-blue-400" />
                  <span>Upload Image</span>
                </button>
              </div>
            </div>
          </div>

          <!-- Live Scanning Status Indicator -->
          <div class="flex items-center justify-center min-h-[26px]">
            <transition name="status-fade" mode="out-in">
              <div
                v-if="qrDetected"
                key="detected"
                class="inline-flex items-center gap-2 px-3.5 py-0.5 rounded-full bg-emerald-500/15 border border-emerald-500/30 text-emerald-600 dark:text-emerald-400 text-xs font-black tracking-wider animate-pulse"
              >
                <CheckCircle2 class="w-4 h-4 text-emerald-500 shrink-0" />
                <span>QR DETECTED</span>
              </div>
              <div
                v-else-if="cameraActive && !isScanningPaused"
                key="scanning"
                class="inline-flex items-center gap-2 px-3.5 py-0.5 rounded-full bg-slate-100 dark:bg-[#252525] border border-slate-300 dark:border-[#3a3a3a] text-slate-700 dark:text-slate-300 text-[11px] font-bold tracking-wider uppercase shadow-xs"
              >
                <span class="w-2 h-2 rounded-full bg-blue-500 animate-pulse"></span>
                <span>ALIGN QR CODE INSIDE FRAME</span>
              </div>
              <div
                v-else
                key="ready"
                class="text-[11px] text-slate-400 font-medium"
              >
                Position QR pass or sticker inside the frame
              </div>
            </transition>
          </div>

          <!-- 3. Damaged QR Code / Manual ID Entry Accordion Fallback -->
          <div class="w-full max-w-sm pt-1">
            <div class="p-2.5 bg-slate-50 dark:bg-[#1f1f1f] border border-slate-200 dark:border-[#333] rounded-2xl">
              <button
                type="button"
                @click="showManualEntry = !showManualEntry"
                class="w-full flex items-center justify-between text-[11px] font-bold text-slate-700 dark:text-slate-300 hover:text-blue-600 dark:hover:text-blue-400 cursor-pointer"
              >
                <span class="flex items-center gap-1.5">
                  <Hash class="w-3.5 h-3.5 text-blue-500" />
                  <span>Damaged QR? Manual Ticket / Student ID Entry</span>
                </span>
                <ChevronDown class="w-3.5 h-3.5 transition-transform" :class="{ 'rotate-180': showManualEntry }" />
              </button>

              <div v-if="showManualEntry" class="mt-2.5 pt-2 border-t border-slate-200 dark:border-[#2f2f2f] flex gap-2">
                <input
                  type="text"
                  v-model="manualCode"
                  @keyup.enter="handleManualSubmit"
                  placeholder="e.g. TIX-00125 or 2026-00125"
                  class="flex-1 bg-white dark:bg-[#141414] border border-slate-300 dark:border-[#3a3a3a] rounded-xl px-2.5 py-1.5 text-xs text-slate-800 dark:text-slate-100 focus:outline-none focus:border-blue-500 font-mono"
                />
                <button
                  type="button"
                  @click="handleManualSubmit"
                  :disabled="!manualCode.trim()"
                  class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold transition cursor-pointer disabled:opacity-50"
                >
                  Verify
                </button>
              </div>
            </div>
          </div>

          <!-- Secondary Image Upload Fallback -->
          <div class="pt-0.5 flex items-center justify-center gap-1.5 text-xs text-slate-500 dark:text-slate-400">
            <span>Camera issue?</span>
            <button
              type="button"
              @click="fileInputRef?.click()"
              class="font-bold text-blue-600 dark:text-blue-400 hover:text-blue-700 dark:hover:text-blue-300 hover:underline cursor-pointer flex items-center gap-1"
            >
              <Upload class="w-3.5 h-3.5" />
              <span>Upload QR Image</span>
            </button>
          </div>

          <!-- Upload Error Feedback Banner -->
          <div v-if="uploadError" class="w-full max-w-sm p-2 rounded-xl bg-amber-500/10 border border-amber-500/30 text-amber-600 dark:text-amber-400 text-xs font-medium flex items-center justify-center gap-2">
            <AlertTriangle class="w-4 h-4 shrink-0" />
            <span>{{ uploadError }}</span>
          </div>

          <!-- Hidden Dedicated Container for Offline File QR Decoding -->
          <div id="vue-qr-image-decoder-container" class="hidden"></div>
        </div>
      </div>
    </Modal>

    <!-- Instant Validation Result Modal -->
    <ScanResultModal
      :result="scanResult"
      :is-open="showResultModal"
      @close="closeResultModal"
      @scan-next="handleScanNext"
    />
  </div>
</template>

<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick } from 'vue';
import { Html5Qrcode, Html5QrcodeSupportedFormats } from 'html5-qrcode';
import {
  Upload,
  AlertTriangle,
  RotateCw,
  CheckCircle2,
  Wifi,
  WifiOff,
  Zap,
  Volume2,
  VolumeX,
  Vibrate,
  Smartphone,
  Flashlight,
  Hash,
  ChevronDown
} from 'lucide-vue-next';
import { useAppStore } from '../../composables/useAppStore';
import { useOfflineScanner } from '../../composables/useOfflineScanner';
import { sounds } from '../../utils/audio';
import { ScanValidationResult } from '../../types';
import Modal from '../common/Modal.vue';
import ScanResultModal from './ScanResultModal.vue';

const props = defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { currentEvent, validateAndRecordScan } = useAppStore();
const {
  isOnline,
  isSyncing,
  queuedCount,
  hasPendingScans,
  continuousScanMode,
  toggleContinuousMode,
  flushQueue
} = useOfflineScanner();

const cameraActive = ref(false);
const cameraError = ref<string | null>(null);
const uploadError = ref<string | null>(null);
const isScanningPaused = ref(false);
const qrDetected = ref(false);
const availableCameras = ref<Array<{ id: string; label: string }>>([]);
const selectedCameraId = ref<string>('');
const facingMode = ref<'environment' | 'user'>('environment');
const fileInputRef = ref<HTMLInputElement | null>(null);

const hasTorch = ref(false);
const isTorchOn = ref(false);
const showManualEntry = ref(false);
const manualCode = ref('');

const scanResult = ref<ScanValidationResult | null>(null);
const showResultModal = ref(false);

let html5QrCode: Html5Qrcode | null = null;

const toggleSound = () => {
  sounds.toggleSound();
};

const toggleHaptics = () => {
  sounds.toggleHaptics();
};

const triggerManualSync = async () => {
  await flushQueue();
};

const checkTorchSupport = () => {
  try {
    const videoElem = document.querySelector('#vue-interactive-qr-reader-container video') as HTMLVideoElement;
    if (videoElem && videoElem.srcObject) {
      const stream = videoElem.srcObject as MediaStream;
      const track = stream.getVideoTracks()[0];
      if (track && typeof track.getCapabilities === 'function') {
        const capabilities: any = track.getCapabilities();
        if (capabilities && capabilities.torch) {
          hasTorch.value = true;
          return;
        }
      }
    }
  } catch {}
  hasTorch.value = false;
  isTorchOn.value = false;
};

const toggleTorch = async () => {
  try {
    const videoElem = document.querySelector('#vue-interactive-qr-reader-container video') as HTMLVideoElement;
    if (videoElem && videoElem.srcObject) {
      const stream = videoElem.srcObject as MediaStream;
      const track = stream.getVideoTracks()[0];
      isTorchOn.value = !isTorchOn.value;
      await track.applyConstraints({
        advanced: [{ torch: isTorchOn.value } as any]
      });
    }
  } catch {
    isTorchOn.value = false;
  }
};

const startCamera = async () => {
  cameraError.value = null;
  uploadError.value = null;
  qrDetected.value = false;
  hasTorch.value = false;
  isTorchOn.value = false;
  await nextTick();

  try {
    if (html5QrCode) {
      try {
        if (html5QrCode.isScanning) {
          await html5QrCode.stop();
        }
      } catch {}
      html5QrCode = null;
    }

    html5QrCode = new Html5Qrcode('vue-interactive-qr-reader-container', {
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true,
      },
      formatsToSupport: [
        Html5QrcodeSupportedFormats.QR_CODE,
        Html5QrcodeSupportedFormats.CODE_128,
        Html5QrcodeSupportedFormats.CODE_39,
      ],
      verbose: false,
    });

    let cameraTarget: any = { facingMode: facingMode.value };

    try {
      const devices = await Html5Qrcode.getCameras();
      if (devices && devices.length > 0) {
        availableCameras.value = devices;
        if (selectedCameraId.value) {
          const deviceMatch = devices.find(d => d.id === selectedCameraId.value);
          if (deviceMatch) {
            cameraTarget = selectedCameraId.value;
          }
        } else {
          const backCam = devices.find(d => {
            const label = d.label.toLowerCase();
            return label.includes('back') || label.includes('environment') || label.includes('rear') || label.includes('facing back');
          });
          if (backCam && facingMode.value === 'environment') {
            selectedCameraId.value = backCam.id;
            cameraTarget = backCam.id;
          } else {
            const frontCam = devices.find(d => {
              const label = d.label.toLowerCase();
              return label.includes('front') || label.includes('user') || label.includes('facing front');
            });
            if (frontCam && facingMode.value === 'user') {
              selectedCameraId.value = frontCam.id;
              cameraTarget = frontCam.id;
            } else {
              selectedCameraId.value = devices[0].id;
              cameraTarget = devices[0].id;
            }
          }
        }
      }
    } catch {
      cameraTarget = { facingMode: facingMode.value };
    }

    const qrboxFunction = (viewfinderWidth: number, viewfinderHeight: number) => {
      const minEdge = Math.min(viewfinderWidth, viewfinderHeight);
      const edgeSize = Math.max(180, Math.floor(minEdge * 0.75));
      return { width: edgeSize, height: edgeSize };
    };

    await html5QrCode.start(
      cameraTarget,
      {
        fps: 22,
        qrbox: qrboxFunction,
        aspectRatio: 1.0,
        disableFlip: false,
      },
      (decodedText) => {
        if (!isScanningPaused.value && !qrDetected.value) {
          handleScannedCode(decodedText);
        }
      },
      () => {}
    );

    cameraActive.value = true;
    setTimeout(() => {
      checkTorchSupport();
    }, 400);
  } catch (err: any) {
    cameraError.value = err?.message || 'Failed to initialize camera. Please grant camera permission.';
    cameraActive.value = false;
  }
};

const stopCamera = async () => {
  if (html5QrCode) {
    try {
      if (html5QrCode.isScanning) {
        await html5QrCode.stop();
      }
      html5QrCode.clear();
    } catch {}
    html5QrCode = null;
  }
  cameraActive.value = false;
  qrDetected.value = false;
  hasTorch.value = false;
  isTorchOn.value = false;
};

const handleCameraSelectChange = async () => {
  await stopCamera();
  await startCamera();
};

const toggleCameraFacing = async () => {
  await stopCamera();
  facingMode.value = facingMode.value === 'environment' ? 'user' : 'environment';
  
  if (availableCameras.value.length > 0) {
    if (facingMode.value === 'environment') {
      const backCam = availableCameras.value.find(d => {
        const label = d.label.toLowerCase();
        return label.includes('back') || label.includes('environment') || label.includes('rear');
      });
      selectedCameraId.value = backCam ? backCam.id : availableCameras.value[0].id;
    } else {
      const frontCam = availableCameras.value.find(d => {
        const label = d.label.toLowerCase();
        return label.includes('front') || label.includes('user');
      });
      selectedCameraId.value = frontCam ? frontCam.id : availableCameras.value[0].id;
    }
  } else {
    selectedCameraId.value = '';
  }

  await startCamera();
};

const handleFileUpload = async (e: Event) => {
  const target = e.target as HTMLInputElement;
  const file = target.files?.[0];
  if (!file) return;

  uploadError.value = null;
  try {
    const decoderScanner = new Html5Qrcode('vue-qr-image-decoder-container', {
      experimentalFeatures: {
        useBarCodeDetectorIfSupported: true,
      },
      verbose: false,
    });
    const decodedText = await decoderScanner.scanFile(file, true);
    try {
      decoderScanner.clear();
    } catch {}
    handleScannedCode(decodedText);
  } catch {
    uploadError.value = 'No valid QR code detected in the uploaded image. Please ensure the QR is clear.';
    setTimeout(() => {
      uploadError.value = null;
    }, 5000);
  } finally {
    if (target) target.value = '';
  }
};

const handleManualSubmit = () => {
  const code = manualCode.value.trim();
  if (!code) return;
  manualCode.value = '';
  showManualEntry.value = false;
  handleScannedCode(code);
};

const handleScannedCode = (decodedText: string) => {
  qrDetected.value = true;
  isScanningPaused.value = true;

  setTimeout(() => {
    const result = validateAndRecordScan(decodedText.trim());
    scanResult.value = result;
    showResultModal.value = true;
  }, 200);
};

const closeResultModal = () => {
  showResultModal.value = false;
  qrDetected.value = false;
  isScanningPaused.value = false;
};

const handleScanNext = () => {
  showResultModal.value = false;
  qrDetected.value = false;
  isScanningPaused.value = false;
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      isScanningPaused.value = false;
      qrDetected.value = false;
      setTimeout(() => {
        startCamera();
      }, 100);
    } else {
      stopCamera();
    }
  },
  { immediate: true }
);

onBeforeUnmount(() => {
  stopCamera();
});
</script>

<style scoped>
/* ==================== PROFESSIONAL SCANNER VIEWPORT STYLING ==================== */
.scanner-cutout-frame {
  box-shadow: 0 0 0 9999px rgba(10, 15, 29, 0.52);
}

@keyframes corner-pulse {
  0%, 100% {
    opacity: 0.85;
    filter: drop-shadow(0 0 3px rgba(59, 130, 246, 0.4));
  }
  50% {
    opacity: 1;
    filter: drop-shadow(0 0 7px rgba(59, 130, 246, 0.75));
  }
}

.scanner-corner {
  animation: corner-pulse 2.2s ease-in-out infinite;
}

@keyframes scan-sweep-motion {
  0% {
    top: -5%;
    opacity: 0;
  }
  15% {
    opacity: 1;
  }
  85% {
    opacity: 1;
  }
  100% {
    top: 80%;
    opacity: 0;
  }
}

.scanner-sweep-beam {
  position: absolute;
  left: 0;
  right: 0;
  height: 52px;
  background: linear-gradient(
    180deg,
    rgba(59, 130, 246, 0) 0%,
    rgba(59, 130, 246, 0.08) 50%,
    rgba(96, 165, 250, 0.28) 90%,
    rgba(147, 197, 253, 0.85) 100%
  );
  border-bottom: 2px solid rgba(147, 197, 253, 0.95);
  box-shadow: 0 4px 12px rgba(59, 130, 246, 0.4);
  animation: scan-sweep-motion 2.6s ease-in-out infinite;
  pointer-events: none;
  z-index: 10;
}

@media (prefers-reduced-motion: reduce) {
  .scanner-sweep-beam {
    display: none !important;
  }
  .scanner-corner {
    animation: none !important;
    opacity: 1 !important;
    filter: none !important;
  }
}

.status-fade-enter-active,
.status-fade-leave-active {
  transition: opacity 0.15s ease, transform 0.15s ease;
}
.status-fade-enter-from,
.status-fade-leave-to {
  opacity: 0;
  transform: translateY(2px);
}
</style>
