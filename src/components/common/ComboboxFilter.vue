<template>
  <div class="relative w-full" ref="containerRef">
    <label v-if="label" class="block text-[10px] font-extrabold uppercase tracking-wider text-slate-500 dark:text-[#b3b3b3] mb-1">
      {{ label }}
    </label>

    <div class="relative flex items-center">
      <!-- Input for manual typing & combobox display -->
      <input
        type="text"
        :value="displayValue"
        @input="onInput"
        @focus="openDropdown"
        @click="openDropdown"
        @keydown.down.prevent="navigateDown"
        @keydown.up.prevent="navigateUp"
        @keydown.enter.prevent="selectHighlighted"
        @keydown.esc="closeDropdown"
        :placeholder="placeholder || `Type or select ${label}...`"
        class="w-full bg-slate-50 dark:bg-[#121212] border border-slate-300 dark:border-[#3a3a3a] rounded-lg pl-2.5 pr-14 py-1.5 text-xs text-slate-800 dark:text-slate-200 placeholder-slate-400 dark:placeholder-slate-600 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 font-medium transition-all"
      />

      <!-- Action Buttons inside input: Clear & Toggle Dropdown -->
      <div class="absolute right-1.5 flex items-center gap-0.5 text-slate-400 dark:text-slate-500">
        <button
          v-if="modelValue && modelValue !== 'all' && modelValue !== ''"
          @click.stop="clearValue"
          type="button"
          class="p-1 hover:text-slate-700 dark:hover:text-slate-200 rounded transition cursor-pointer"
          title="Clear"
        >
          <X class="w-3 h-3" />
        </button>

        <button
          @click.stop="toggleDropdown"
          type="button"
          class="p-1 hover:text-slate-700 dark:hover:text-slate-200 rounded transition cursor-pointer"
          :title="isOpen ? 'Close options' : 'Show options'"
        >
          <ChevronDown
            class="w-3.5 h-3.5 transition-transform duration-200"
            :class="{ 'rotate-180 text-blue-500': isOpen }"
          />
        </button>
      </div>
    </div>

    <!-- Dropdown Menu List -->
    <transition
      enter-active-class="transition duration-100 ease-out"
      enter-from-class="transform scale-95 opacity-0"
      enter-to-class="transform scale-100 opacity-100"
      leave-active-class="transition duration-75 ease-in"
      leave-from-class="transform scale-100 opacity-100"
      leave-to-class="transform scale-95 opacity-0"
    >
      <div
        v-if="isOpen"
        class="absolute z-50 left-0 right-0 mt-1 max-h-56 overflow-y-auto bg-white dark:bg-[#1e1e1e] border border-slate-200 dark:border-[#333333] rounded-xl shadow-xl py-1 text-xs custom-scrollbar"
      >
        <!-- "All" Default Option -->
        <button
          type="button"
          @click="selectOption('all')"
          class="w-full text-left px-3 py-1.5 flex items-center justify-between transition hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer"
          :class="[
            (!modelValue || modelValue === 'all')
              ? 'text-blue-600 dark:text-blue-400 font-bold bg-blue-50/70 dark:bg-blue-950/20'
              : 'text-slate-700 dark:text-slate-300'
          ]"
        >
          <span>{{ allLabel || `All ${label || 'Options'}` }}</span>
          <Check v-if="!modelValue || modelValue === 'all'" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
        </button>

        <div class="my-1 border-t border-slate-100 dark:border-[#2a2a2a]"></div>

        <!-- Filtered Options List -->
        <template v-if="filteredOptions.length > 0">
          <button
            v-for="(opt, idx) in filteredOptions"
            :key="opt"
            type="button"
            @click="selectOption(opt)"
            class="w-full text-left px-3 py-1.5 flex items-center justify-between transition hover:bg-blue-50 dark:hover:bg-blue-950/40 cursor-pointer"
            :class="[
              modelValue === opt
                ? 'text-blue-600 dark:text-blue-400 font-bold bg-blue-50/70 dark:bg-blue-950/20'
                : highlightedIndex === idx
                ? 'bg-slate-100 dark:bg-[#282828] text-slate-900 dark:text-white'
                : 'text-slate-700 dark:text-slate-300'
            ]"
          >
            <span>{{ opt }}</span>
            <Check v-if="modelValue === opt" class="w-3.5 h-3.5 text-blue-600 dark:text-blue-400" />
          </button>
        </template>

        <!-- No matching preset options, allow current custom input -->
        <div v-else class="px-3 py-2 text-[11px] text-slate-400 dark:text-slate-500 italic">
          <span v-if="searchTerm">Press Enter to use custom: "<strong>{{ searchTerm }}</strong>"</span>
          <span v-else>No presets available</span>
        </div>
      </div>
    </transition>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { ChevronDown, X, Check } from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    modelValue: string;
    label?: string;
    placeholder?: string;
    options: string[];
    allLabel?: string;
  }>(),
  {
    modelValue: 'all',
    options: () => [],
  }
);

const emit = defineEmits<{
  (e: 'update:modelValue', val: string): void;
  (e: 'change', val: string): void;
}>();

const isOpen = ref(false);
const searchTerm = ref('');
const highlightedIndex = ref(-1);
const containerRef = ref<HTMLElement | null>(null);

const displayValue = computed(() => {
  if (isOpen.value) {
    return searchTerm.value;
  }
  if (!props.modelValue || props.modelValue === 'all') {
    return '';
  }
  return props.modelValue;
});

const filteredOptions = computed(() => {
  if (!searchTerm.value.trim()) {
    return props.options;
  }
  const term = searchTerm.value.toLowerCase();
  return props.options.filter(opt => opt.toLowerCase().includes(term));
});

const openDropdown = () => {
  if (!isOpen.value) {
    searchTerm.value = (!props.modelValue || props.modelValue === 'all') ? '' : props.modelValue;
    isOpen.value = true;
    highlightedIndex.value = -1;
  }
};

const closeDropdown = () => {
  isOpen.value = false;
  searchTerm.value = '';
  highlightedIndex.value = -1;
};

const toggleDropdown = () => {
  if (isOpen.value) {
    closeDropdown();
  } else {
    openDropdown();
  }
};

const onInput = (e: Event) => {
  const target = e.target as HTMLInputElement;
  const val = target.value;
  searchTerm.value = val;
  if (!isOpen.value) {
    isOpen.value = true;
  }
  highlightedIndex.value = -1;
  emit('update:modelValue', val);
  emit('change', val);
};

const selectOption = (opt: string) => {
  emit('update:modelValue', opt);
  emit('change', opt);
  closeDropdown();
};

const clearValue = () => {
  emit('update:modelValue', 'all');
  emit('change', 'all');
  searchTerm.value = '';
  closeDropdown();
};

const navigateDown = () => {
  if (!isOpen.value) {
    openDropdown();
    return;
  }
  if (filteredOptions.value.length > 0) {
    highlightedIndex.value = (highlightedIndex.value + 1) % filteredOptions.value.length;
  }
};

const navigateUp = () => {
  if (!isOpen.value) {
    openDropdown();
    return;
  }
  if (filteredOptions.value.length > 0) {
    highlightedIndex.value = (highlightedIndex.value - 1 + filteredOptions.value.length) % filteredOptions.value.length;
  }
};

const selectHighlighted = () => {
  if (isOpen.value && highlightedIndex.value >= 0 && highlightedIndex.value < filteredOptions.value.length) {
    selectOption(filteredOptions.value[highlightedIndex.value]);
  } else if (searchTerm.value.trim()) {
    selectOption(searchTerm.value.trim());
  } else {
    closeDropdown();
  }
};

// Close when clicking outside
const handleClickOutside = (e: MouseEvent) => {
  if (containerRef.value && !containerRef.value.contains(e.target as Node)) {
    closeDropdown();
  }
};

onMounted(() => {
  document.addEventListener('click', handleClickOutside);
});

onUnmounted(() => {
  document.removeEventListener('click', handleClickOutside);
});
</script>
