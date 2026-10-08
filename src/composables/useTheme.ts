import { ref, computed } from 'vue';

export type ThemeMode = 'light' | 'dark';

const STORAGE_KEY = 'integ2_theme';

// Get initial theme from localStorage or system preference, default to dark
const getSavedTheme = (): ThemeMode => {
  if (typeof window === 'undefined') return 'dark';
  const saved = localStorage.getItem(STORAGE_KEY);
  if (saved === 'light' || saved === 'dark') {
    return saved;
  }
  return window.matchMedia && window.matchMedia('(prefers-color-scheme: light)').matches ? 'light' : 'dark';
};

const currentTheme = ref<ThemeMode>(getSavedTheme());

const applyTheme = (theme: ThemeMode) => {
  if (typeof document === 'undefined') return;
  const root = document.documentElement;
  if (theme === 'dark') {
    root.classList.add('dark');
    root.classList.remove('light');
    root.setAttribute('data-theme', 'dark');
  } else {
    root.classList.remove('dark');
    root.classList.add('light');
    root.setAttribute('data-theme', 'light');
  }
  localStorage.setItem(STORAGE_KEY, theme);
};

// Initial apply
if (typeof window !== 'undefined') {
  applyTheme(currentTheme.value);
}

export const useTheme = () => {
  const isDark = computed(() => currentTheme.value === 'dark');

  const setTheme = (theme: ThemeMode) => {
    currentTheme.value = theme;
    applyTheme(theme);
  };

  const toggleTheme = () => {
    setTheme(currentTheme.value === 'dark' ? 'light' : 'dark');
  };

  return {
    theme: currentTheme,
    isDark,
    setTheme,
    toggleTheme,
  };
};
