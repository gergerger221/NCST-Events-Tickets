<template>
  <div
    v-if="isOpen"
    class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/60 backdrop-blur-sm animate-fade-in"
    @click.self="$emit('close')"
  >
    <div
      class="bg-white dark:bg-[#242424] border border-slate-200 dark:border-[#333333] rounded-3xl w-full max-w-lg p-6 shadow-2xl relative transition-all max-h-[90vh] overflow-y-auto"
    >
      <!-- Close Button -->
      <button
        type="button"
        @click="$emit('close')"
        class="absolute top-5 right-5 text-slate-400 hover:text-slate-600 dark:text-slate-500 dark:hover:text-slate-300 cursor-pointer transition p-1.5 rounded-full hover:bg-slate-100 dark:hover:bg-[#333333]"
        title="Close Tester"
      >
        <X class="w-5 h-5" />
      </button>

      <!-- Header -->
      <div class="flex items-center gap-3 mb-5">
        <div class="p-2.5 rounded-2xl bg-blue-100 dark:bg-blue-950/50 text-blue-600 dark:text-blue-400 border border-blue-200 dark:border-blue-900/50">
          <Server class="w-6 h-6" />
        </div>
        <div>
          <h3 class="text-lg font-extrabold text-slate-900 dark:text-white flex items-center gap-2">
            <span>SMTP Connection Tester</span>
            <span
              v-if="config"
              class="text-[10px] font-mono px-2 py-0.5 rounded-full font-bold uppercase tracking-wider"
              :class="config.is_mock ? 'bg-amber-100 dark:bg-amber-950/60 text-amber-700 dark:text-amber-300' : 'bg-emerald-100 dark:bg-emerald-950/60 text-emerald-700 dark:text-emerald-300'"
            >
              {{ config.is_mock ? 'Simulation Mode' : 'Live SMTP' }}
            </span>
          </h3>
          <p class="text-xs text-slate-500 dark:text-[#b3b3b3]">
            Verify outbound email delivery and diagnose SMTP host credentials
          </p>
        </div>
      </div>

      <!-- Config Summary Card -->
      <div class="mb-5 p-3.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-2xl space-y-2 text-xs">
        <div class="flex items-center justify-between pb-2 border-b border-slate-200/60 dark:border-[#2d2d2d]">
          <span class="font-bold text-slate-700 dark:text-slate-300 flex items-center gap-1.5">
            <Cpu class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
            <span>Active Engine:</span>
          </span>
          <span class="font-mono font-bold text-blue-600 dark:text-blue-400">
            {{ config?.mailer_engine || 'Detecting...' }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2 pt-1 text-[11px]">
          <div>
            <span class="text-slate-400 dark:text-slate-500">Host:</span>
            <span class="ml-1.5 font-mono font-bold text-slate-800 dark:text-slate-200 truncate inline-block max-w-[150px] align-bottom">
              {{ config?.host || '(loading)' }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 dark:text-slate-500">Port / Security:</span>
            <span class="ml-1.5 font-mono font-bold text-slate-800 dark:text-slate-200">
              {{ config?.port || 587 }} ({{ config?.encryption?.toUpperCase() || 'TLS' }})
            </span>
          </div>
          <div>
            <span class="text-slate-400 dark:text-slate-500">From:</span>
            <span class="ml-1.5 font-mono text-slate-800 dark:text-slate-200 truncate inline-block max-w-[150px] align-bottom">
              {{ config?.from_address || '(default)' }}
            </span>
          </div>
          <div>
            <span class="text-slate-400 dark:text-slate-500">Auth:</span>
            <span
              class="ml-1.5 font-bold"
              :class="config?.has_credentials ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-600 dark:text-amber-400'"
            >
              {{ config?.has_credentials ? `Configured (${config?.username_masked})` : 'No credentials' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Test Form -->
      <form @submit.prevent="runSmtpTest" class="space-y-4">
        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            Recipient Test Email Address *
          </label>
          <div class="relative">
            <Mail class="w-4 h-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="recipientEmail"
              type="email"
              required
              placeholder="e.g. your-email@gmail.com"
              class="w-full pl-10 pr-4 py-2.5 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
            />
          </div>
          <p class="text-[10px] text-slate-400 dark:text-slate-500 mt-1">
            A diagnostic test message will be sent to this email address to verify outbound delivery.
          </p>
        </div>

        <div>
          <label class="block text-xs font-bold text-slate-700 dark:text-slate-300 mb-1">
            Test Subject <span class="font-normal text-slate-400">(Optional)</span>
          </label>
          <input
            v-model="customSubject"
            type="text"
            placeholder="e.g. NCST SMTP Connectivity Check"
            class="w-full px-3.5 py-2 bg-slate-50 dark:bg-[#1e1e1e] border border-slate-300 dark:border-[#3a3a3a] rounded-xl text-xs text-slate-900 dark:text-white placeholder:text-slate-400 dark:placeholder:text-slate-600 focus:outline-none focus:border-blue-500 transition"
          />
        </div>

        <!-- Result Banner -->
        <div
          v-if="testResult"
          class="p-3.5 rounded-2xl border text-xs space-y-1.5 transition-all animate-fade-in"
          :class="{
            'bg-emerald-50 dark:bg-emerald-950/40 border-emerald-200 dark:border-emerald-800/50 text-emerald-800 dark:text-emerald-300': testResult.success && testResult.status === 'submitted',
            'bg-amber-50 dark:bg-amber-950/40 border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-300': testResult.success && testResult.status === 'simulated',
            'bg-rose-50 dark:bg-rose-950/50 border-rose-200 dark:border-rose-800/50 text-rose-800 dark:text-rose-300': !testResult.success
          }"
        >
          <div class="flex items-center justify-between font-bold">
            <span class="flex items-center gap-1.5">
              <CheckCircle2 v-if="testResult.success && testResult.status === 'submitted'" class="w-4 h-4 text-emerald-600 dark:text-emerald-400" />
              <AlertCircle v-else-if="testResult.success && testResult.status === 'simulated'" class="w-4 h-4 text-amber-600 dark:text-amber-400" />
              <XCircle v-else class="w-4 h-4 text-rose-600 dark:text-rose-400" />
              <span>
                {{ testResult.success && testResult.status === 'submitted' ? 'Delivery Successful!' : (testResult.status === 'simulated' ? 'Simulated Dispatch (Mock Mode)' : 'SMTP Delivery Failed') }}
              </span>
            </span>
            <span v-if="testResult.latency_ms" class="text-[10px] font-mono opacity-80">
              {{ testResult.latency_ms }} ms
            </span>
          </div>
          <p class="text-[11px] leading-relaxed break-words font-mono">
            {{ testResult.details }}
          </p>

          <div v-if="!testResult.success" class="mt-2 pt-2 border-t border-rose-200 dark:border-rose-800/40 text-[10.5px] text-rose-700 dark:text-rose-400 space-y-1">
            <p class="font-bold flex items-center gap-1">
              <HelpCircle class="w-3.5 h-3.5" />
              <span>Troubleshooting Tips:</span>
            </p>
            <ul class="list-disc list-inside space-y-0.5 text-[10px]">
              <li><strong>Gmail:</strong> Generate a 16-character <em>App Password</em> in your Google Account security settings. Do not use your personal password.</li>
              <li><strong>Port / Encryption:</strong> Port 587 requires <code>SMTP_ENCRYPTION=tls</code>; Port 465 requires <code>SMTP_ENCRYPTION=ssl</code>.</li>
              <li><strong>Firewall:</strong> Ensure outgoing TCP port 587/465 is not blocked on your local network or XAMPP.</li>
            </ul>
          </div>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center gap-2 pt-3 border-t border-slate-200 dark:border-[#333333]">
          <button
            type="button"
            @click="$emit('close')"
            class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#333333] dark:hover:bg-[#3d3d3d] text-slate-700 dark:text-slate-300 text-xs font-bold rounded-xl transition cursor-pointer"
          >
            Cancel
          </button>
          <button
            type="submit"
            :disabled="isLoading || !recipientEmail.trim()"
            class="flex-1 py-2.5 px-4 bg-blue-600 hover:bg-blue-700 text-white text-xs font-extrabold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer disabled:opacity-50"
          >
            <span v-if="isLoading" class="w-3.5 h-3.5 border-2 border-white/30 border-t-white rounded-full animate-spin" />
            <Send v-else class="w-3.5 h-3.5" />
            <span>{{ isLoading ? 'Transmitting...' : 'Send Test Email' }}</span>
          </button>
        </div>
      </form>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, watch } from 'vue';
import {
  X,
  Server,
  Mail,
  Send,
  Cpu,
  CheckCircle2,
  AlertCircle,
  XCircle,
  HelpCircle,
} from 'lucide-vue-next';
import { apiUrl } from '../../utils/apiUrl';

const props = defineProps<{
  isOpen: boolean;
}>();

defineEmits<{
  (e: 'close'): void;
}>();

const config = ref<{
  driver: string;
  host: string;
  port: number;
  encryption: string;
  from_address: string;
  from_name: string;
  reply_to: string;
  has_credentials: boolean;
  username_masked: string;
  mailer_engine: string;
  is_mock: boolean;
  environment: string;
} | null>(null);

const recipientEmail = ref('');
const customSubject = ref('');
const isLoading = ref(false);
const testResult = ref<{
  success: boolean;
  status: string;
  details: string;
  latency_ms?: number;
} | null>(null);

const fetchConfig = async () => {
  try {
    const res = await fetch(apiUrl('/api/auth/smtp-test'));
    const data = await res.json().catch(() => ({}));
    if (data.config) {
      config.value = data.config;
    }
  } catch {
    // Non-fatal, config will remain null or fallback
  }
};

const runSmtpTest = async () => {
  if (!recipientEmail.value.trim() || !recipientEmail.value.includes('@')) {
    return;
  }

  isLoading.value = true;
  testResult.value = null;

  try {
    const res = await fetch(apiUrl('/api/auth/smtp-test'), {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({
        recipient: recipientEmail.value.trim(),
        subject: customSubject.value.trim() || undefined,
      }),
    });
    const data = await res.json().catch(() => ({}));
    isLoading.value = false;

    if (data.config) {
      config.value = data.config;
    }

    testResult.value = {
      success: !!data.success,
      status: data.status || (data.success ? 'submitted' : 'failed'),
      details: data.details || data.error || (data.success ? 'Email dispatched successfully.' : (!res.ok ? `Server error (HTTP ${res.status}).` : 'SMTP transmission failed.')),
      latency_ms: data.latency_ms,
    };
  } catch (err: any) {
    isLoading.value = false;
    testResult.value = {
      success: false,
      status: 'failed',
      details: err?.message || 'Network error: Unable to contact server for SMTP test.',
    };
  }
};

watch(
  () => props.isOpen,
  (open) => {
    if (open) {
      fetchConfig();
      testResult.value = null;
    }
  }
);

onMounted(() => {
  if (props.isOpen) {
    fetchConfig();
  }
});
</script>
