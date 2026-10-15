<template>
  <Modal :is-open="isOpen" title="Account & Profile" subtitle="Your Authenticated User Session" max-width="md" @close="$emit('close')">
    <div v-if="currentUser" class="space-y-6">
      <!-- Profile Header -->
      <div class="flex items-center gap-4 p-4 rounded-2xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] transition-colors">
        <div class="relative group">
          <UserAvatar :src="currentUser.photoUrl" :alt="currentUser.name" size="lg" shape="rounded" />
          <input
            type="file"
            accept="image/*"
            ref="profileFileInput"
            @change="handleProfilePhotoUpload"
            class="hidden"
          />
          <button
            type="button"
            @click="profileFileInput?.click()"
            class="absolute -bottom-1 -right-1 p-1 bg-blue-600 hover:bg-blue-700 text-white rounded-full shadow transition cursor-pointer"
            title="Upload new profile picture"
          >
            <Camera class="w-3.5 h-3.5" />
          </button>
        </div>

        <div class="min-w-0 flex-1">
          <div class="flex items-center gap-2">
            <h4 class="font-extrabold text-slate-900 dark:text-white text-base truncate">{{ currentUser.name }}</h4>
          </div>

          <div class="mt-1.5 flex items-center gap-2">
            <StatusBadge type="role" :status="currentUser.role" />
            <span v-if="currentUser.studentId" class="text-[10px] font-mono text-slate-600 dark:text-[#b3b3b3] bg-slate-200 dark:bg-[#2c2c2c] px-2 py-0.5 rounded border border-slate-300 dark:border-[#3a3a3a]">
              ID: {{ currentUser.studentId }}
            </span>
          </div>
        </div>
      </div>

      <!-- Theme Preference Setting -->
      <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] space-y-2.5 transition-colors">
        <div class="flex items-center justify-between">
          <span class="text-xs font-bold text-slate-700 dark:text-slate-300 flex items-center gap-2">
            <Palette class="w-4 h-4 text-blue-600 dark:text-blue-400" />
            Display Theme
          </span>
          <span class="text-[11px] font-mono text-slate-500 dark:text-[#b3b3b3] capitalize">
            {{ isDark ? 'Dark Mode' : 'Light Mode' }}
          </span>
        </div>

        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="setTheme('light')"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer"
            :class="!isDark ? 'bg-white text-blue-600 shadow-sm border border-blue-500/40 ring-2 ring-blue-500/20' : 'bg-slate-200/60 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white border border-transparent'"
          >
            <Sun class="w-4 h-4 text-amber-500" />
            <span>Light Mode</span>
          </button>

          <button
            type="button"
            @click="setTheme('dark')"
            class="flex items-center justify-center gap-2 py-2 px-3 rounded-lg text-xs font-bold transition-all cursor-pointer"
            :class="isDark ? 'bg-[#2c2c2c] text-blue-400 shadow-sm border border-blue-500/40 ring-2 ring-blue-500/20' : 'bg-slate-200/60 dark:bg-[#242424] text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white border border-transparent'"
          >
            <Moon class="w-4 h-4 text-blue-400" />
            <span>Dark Mode</span>
          </button>
        </div>
      </div>

      <!-- User Details Grid -->
      <div class="space-y-2.5 text-xs">
        <div v-if="currentUser.course" class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Program / Year & Section</span>
          <span class="font-semibold text-slate-800 dark:text-slate-200 text-right truncate max-w-[200px]">
            {{ currentUser.course }} {{ currentUser.yearLevel ? `• ${currentUser.yearLevel}` : '' }} {{ currentUser.section ? `(${currentUser.section})` : '' }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Department / Affiliation</span>
          <span class="font-semibold text-slate-800 dark:text-slate-200 text-right truncate max-w-[200px]">
            {{ currentUser.department || 'Not specified' }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Account Type</span>
          <span class="font-semibold uppercase tracking-wider text-blue-600 dark:text-blue-400 font-mono text-[11px]">
            {{ currentUser.role }}
          </span>
        </div>

        <div v-if="currentUser.expiresAt" class="flex items-center justify-between p-3 rounded-xl bg-amber-50 dark:bg-amber-950/30 border border-amber-200 dark:border-amber-500/30">
          <span class="text-amber-700 dark:text-amber-300 font-medium">Pass Expires</span>
          <span class="font-mono text-amber-800 dark:text-amber-200 text-[11px]">
            {{ formatDateTime(currentUser.expiresAt) }}
          </span>
        </div>

        <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50/60 dark:bg-[#1e1e1e]/60 border border-slate-200 dark:border-[#333333]">
          <span class="text-slate-500 dark:text-[#b3b3b3] font-medium">Member Since</span>
          <span class="font-semibold text-slate-700 dark:text-slate-300">
            {{ formatDate(currentUser.createdAt) }}
          </span>
        </div>
      </div>

      <!-- Action Buttons -->
      <div class="pt-2 border-t border-slate-200 dark:border-[#333333] flex items-center justify-between gap-3">
        <button
          @click="$emit('close')"
          class="flex-1 py-2.5 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition cursor-pointer"
        >
          Close
        </button>

        <button
          @click="handleLogout"
          class="flex-1 py-2.5 px-4 bg-rose-600 hover:bg-rose-700 text-white text-xs font-bold rounded-xl shadow-md transition flex items-center justify-center gap-2 cursor-pointer"
        >
          <LogOut class="w-4 h-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </div>

    <!-- Empty State if no current user is logged in -->
    <div v-else class="py-8 text-center space-y-3">
      <p class="text-sm text-slate-500 dark:text-slate-400">No active user session found.</p>
      <button
        @click="$emit('close')"
        class="py-2 px-4 bg-slate-100 hover:bg-slate-200 dark:bg-[#2c2c2c] dark:hover:bg-[#333333] text-slate-800 dark:text-slate-200 text-xs font-bold rounded-xl transition cursor-pointer"
      >
        Close
      </button>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { LogOut, Palette, Sun, Moon, Camera } from 'lucide-vue-next';
import Modal from './common/Modal.vue';
import StatusBadge from './common/StatusBadge.vue';
import UserAvatar from './common/UserAvatar.vue';
import { useAppStore } from '../composables/useAppStore';
import { useTheme } from '../composables/useTheme';

defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { currentUser, updateUser, logout } = useAppStore();
const { isDark, setTheme } = useTheme();
const profileFileInput = ref<HTMLInputElement | null>(null);

const handleProfilePhotoUpload = (e: Event) => {
  const target = e.target as HTMLInputElement;
  if (!target.files || target.files.length === 0 || !currentUser.value) return;

  const file = target.files[0];
  if (!file.type.startsWith('image/')) return;

  const reader = new FileReader();
  reader.onload = (event) => {
    if (event.target?.result && currentUser.value) {
      updateUser(currentUser.value.id, { photoUrl: event.target.result as string });
    }
  };
  reader.readAsDataURL(file);
};

const formatDate = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? 'N/A' : d.toLocaleDateString();
};

const formatDateTime = (dateStr?: string) => {
  if (!dateStr) return 'N/A';
  const d = new Date(dateStr);
  return isNaN(d.getTime()) ? 'N/A' : d.toLocaleString();
};

const handleLogout = () => {
  emit('close');
  logout();
};
</script>
