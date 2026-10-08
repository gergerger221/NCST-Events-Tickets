<template>
  <div
    class="relative overflow-hidden bg-slate-900 dark:bg-[#121212] select-none flex-shrink-0"
    :class="[
      containerClass,
      roundedClass
    ]"
  >
    <!-- 1. Real Image Element (if src provided and not failed) -->
    <img
      v-if="validSrc && !hasError"
      :src="validSrc"
      :alt="computedAlt"
      loading="lazy"
      @error="handleImageError"
      class="w-full h-full object-cover transition-transform duration-500 group-hover:scale-105"
    />

    <!-- 2. NCST Clean System Fallback Placeholder (when no image or error) -->
    <div
      v-else
      class="w-full h-full flex flex-col items-center justify-center p-3 text-center bg-gradient-to-br transition-all duration-300 relative overflow-hidden"
      :class="categoryGradient"
      :title="computedAlt"
      role="img"
      :aria-label="computedAlt"
    >
      <!-- Background Abstract Pattern Grid -->
      <div class="absolute inset-0 opacity-15 bg-[radial-gradient(#ffffff_1px,transparent_1px)] [background-size:12px_12px] pointer-events-none" />
      
      <!-- Center Icon -->
      <div class="relative z-10 w-9 h-9 sm:w-10 sm:h-10 rounded-xl bg-white/10 backdrop-blur-md border border-white/20 flex items-center justify-center text-white mb-1.5 shadow-sm">
        <component :is="categoryIcon" class="w-5 h-5 sm:w-5 sm:h-5 text-blue-200" />
      </div>

      <!-- Category Label & Event Code -->
      <div class="relative z-10 space-y-0.5">
        <span class="text-[9px] sm:text-[10px] font-black uppercase tracking-widest text-blue-200/90 block font-mono">
          {{ category || 'NCST EVENT' }}
        </span>
        <span v-if="code" class="text-[8px] text-white/60 font-mono block">
          {{ code }}
        </span>
      </div>
    </div>

    <!-- Subtle Edge Gradient Overlay for readability -->
    <div class="absolute inset-0 bg-gradient-to-t from-black/40 via-transparent to-transparent pointer-events-none" />

    <!-- Optional Corner Category Badge -->
    <div v-if="showBadge && (category || badgeText)" class="absolute top-2 left-2 z-10 pointer-events-none">
      <span class="text-[9px] font-extrabold uppercase px-2 py-0.5 rounded-md bg-black/60 backdrop-blur-md text-white border border-white/20 tracking-wider shadow-xs">
        {{ badgeText || category }}
      </span>
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import {
  Calendar,
  Sparkles,
  Music,
  Trophy,
  GraduationCap,
  Cpu,
  Building2,
  Ticket as TicketIcon
} from 'lucide-vue-next';

const props = withDefaults(
  defineProps<{
    src?: string | null;
    alt?: string;
    title?: string;
    category?: string;
    code?: string;
    aspectRatio?: '16:9' | 'compact' | 'wide' | 'auto';
    size?: 'sm' | 'md' | 'lg' | 'full';
    showBadge?: boolean;
    badgeText?: string;
    rounded?: 'none' | 'lg' | 'xl' | '2xl' | '3xl';
  }>(),
  {
    src: null,
    alt: '',
    title: '',
    category: 'Institutional',
    aspectRatio: '16:9',
    size: 'md',
    showBadge: false,
    rounded: '2xl'
  }
);

const hasError = ref(false);

const validSrc = computed(() => {
  if (!props.src) return null;
  const trimmed = props.src.trim();
  if (!trimmed || trimmed === 'null' || trimmed === 'undefined') return null;
  return trimmed;
});

watch(() => props.src, () => {
  hasError.value = false;
});

const handleImageError = () => {
  hasError.value = true;
};

const computedAlt = computed(() => {
  if (props.alt) return props.alt;
  if (props.title) return `${props.title} event cover`;
  return 'NCST event cover placeholder';
});

// Category-based cohesive gradients
const categoryGradient = computed(() => {
  const cat = (props.category || '').toLowerCase();
  if (cat.includes('foundation') || cat.includes('anniversary')) {
    return 'from-blue-900 via-indigo-950 to-slate-900 border border-blue-800/40 text-blue-100';
  }
  if (cat.includes('concert') || cat.includes('music') || cat.includes('battle') || cat.includes('band')) {
    return 'from-purple-900 via-indigo-950 to-slate-950 border border-purple-800/40 text-purple-100';
  }
  if (cat.includes('sport') || cat.includes('intra')) {
    return 'from-emerald-900 via-teal-950 to-slate-950 border border-emerald-800/40 text-emerald-100';
  }
  if (cat.includes('tech') || cat.includes('hack') || cat.includes('it')) {
    return 'from-cyan-950 via-blue-950 to-slate-950 border border-cyan-800/40 text-cyan-100';
  }
  if (cat.includes('acad') || cat.includes('seminar') || cat.includes('summit')) {
    return 'from-slate-900 via-blue-950 to-indigo-950 border border-blue-800/40 text-blue-100';
  }
  return 'from-slate-900 via-slate-950 to-blue-950 border border-slate-800 text-slate-200';
});

// Category Icon
const categoryIcon = computed(() => {
  const cat = (props.category || '').toLowerCase();
  if (cat.includes('foundation')) return Building2;
  if (cat.includes('concert') || cat.includes('music') || cat.includes('band')) return Music;
  if (cat.includes('sport') || cat.includes('intra')) return Trophy;
  if (cat.includes('tech') || cat.includes('hack')) return Cpu;
  if (cat.includes('acad')) return GraduationCap;
  return Calendar;
});

// Dimensions based on size & aspect ratio
const containerClass = computed(() => {
  if (props.size === 'full') return 'w-full h-full';
  
  if (props.size === 'sm') {
    return 'w-24 h-16 sm:w-28 sm:h-20';
  }

  if (props.size === 'lg') {
    return 'w-full aspect-[16/9] max-h-72 sm:max-h-80';
  }

  // Default 'md' (approx 180–210px wide x 105–120px tall on desktop)
  return 'w-full sm:w-48 md:w-52 aspect-[16/9] sm:aspect-[16/10]';
});

const roundedClass = computed(() => {
  switch (props.rounded) {
    case 'none': return 'rounded-none';
    case 'lg': return 'rounded-lg';
    case 'xl': return 'rounded-xl';
    case '3xl': return 'rounded-3xl';
    default: return 'rounded-2xl';
  }
});
</script>
