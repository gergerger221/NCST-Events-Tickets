<template>
  <Modal
    :is-open="isOpen"
    @close="$emit('close')"
    title="Switch User Account Profile"
    subtitle="Choose any teacher, student, or administrator to inspect their portal view"
    max-width="2xl"
  >
    <div class="space-y-4">
      <!-- Role Filter Tabs -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1 border-b border-slate-200 dark:border-[#333333]">
        <button
          v-for="r in (['all', 'admin', 'event_staff', 'cashier', 'teacher', 'student'] as const)"
          :key="r"
          @click="roleFilter = r"
          class="px-3 py-1.5 rounded-xl text-xs font-semibold uppercase tracking-wider transition cursor-pointer"
          :class="roleFilter === r ? 'bg-blue-600 text-white shadow' : 'text-slate-600 dark:text-[#b3b3b3] hover:text-slate-900 dark:hover:text-white hover:bg-slate-100 dark:hover:bg-[#2c2c2c]'"
        >
          {{ r === 'event_staff' ? 'Event Staff' : r }}
        </button>
      </div>

      <!-- Account List -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 max-h-[420px] overflow-y-auto pr-1">
        <div
          v-for="user in filteredUsers"
          :key="user.id"
          @click="handleSelect(user)"
          class="p-3.5 rounded-2xl border transition-all cursor-pointer flex items-center justify-between group"
          :class="[
            currentUser.id === user.id
              ? 'bg-blue-50 dark:bg-blue-950/40 border-blue-500 shadow-md ring-1 ring-blue-500/50'
              : 'bg-slate-50 dark:bg-[#1e1e1e] border-slate-200 dark:border-[#333333] hover:border-slate-300 dark:hover:border-[#3a3a3a] hover:bg-slate-100 dark:hover:bg-[#2c2c2c]'
          ]"
        >
          <div class="flex items-center gap-3 min-w-0">
            <UserAvatar :src="user.photoUrl" :alt="user.name" size="md" shape="rounded" />
            <div class="min-w-0">
              <div class="flex items-center gap-1.5">
                <p class="text-xs font-bold text-slate-900 dark:text-white truncate">{{ user.name }}</p>
                <CheckCircle2 v-if="currentUser.id === user.id" class="w-3.5 h-3.5 text-emerald-500 dark:text-emerald-400 flex-shrink-0" />
              </div>
              <p class="text-[11px] text-slate-500 dark:text-[#b3b3b3] truncate">{{ user.email }}</p>
              <p v-if="user.studentId" class="text-[10px] text-blue-600 dark:text-blue-400 font-mono">
                ID: {{ user.studentId }}
              </p>
            </div>
          </div>

          <div class="flex-shrink-0">
            <StatusBadge type="role" :status="user.role" />
          </div>
        </div>
      </div>
    </div>
  </Modal>
</template>

<script setup lang="ts">
import { ref, computed } from 'vue';
import { CheckCircle2 } from 'lucide-vue-next';
import { useAppStore } from '../composables/useAppStore';
import { User, UserRole } from '../types';
import Modal from './common/Modal.vue';
import StatusBadge from './common/StatusBadge.vue';
import UserAvatar from './common/UserAvatar.vue';

defineProps<{
  isOpen: boolean;
}>();

const emit = defineEmits<{
  (e: 'close'): void;
}>();

const { users, currentUser, setCurrentUser } = useAppStore();

const roleFilter = ref<'all' | UserRole>('all');

const filteredUsers = computed(() => {
  if (roleFilter.value !== 'all') {
    return users.value.filter(u => u.role === roleFilter.value);
  }
  return users.value;
});

const handleSelect = (user: User) => {
  setCurrentUser(user);
  emit('close');
};
</script>
