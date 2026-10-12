// Haptic Feedback Engine for Mobile Scanner (Audio Synthesizer removed per institutional policy)
import { ref } from 'vue';

class SoundEffects {
  public soundEnabled = ref<boolean>(false);
  public hapticsEnabled = ref<boolean>(true);

  constructor() {
    if (typeof window !== 'undefined') {
      try {
        const hapticPref = localStorage.getItem('integ2_scanner_haptic_enabled');
        if (hapticPref !== null) {
          this.hapticsEnabled.value = hapticPref === 'true';
        }
      } catch {}
    }
  }

  toggleSound(): boolean {
    // Sound effects permanently disabled per institutional policy
    this.soundEnabled.value = false;
    return false;
  }

  toggleHaptics(): boolean {
    this.hapticsEnabled.value = !this.hapticsEnabled.value;
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('integ2_scanner_haptic_enabled', String(this.hapticsEnabled.value));
      } catch {}
    }
    if (this.hapticsEnabled.value) {
      this.vibrateSuccess();
    }
    return this.hapticsEnabled.value;
  }

  // Audio methods silenced - no sound generated across the application
  playSuccess(): void {}
  playWarning(): void {}
  playError(): void {}
  playClick(): void {}

  // --- Haptic Mobile Vibration Synthesis ---

  vibrateSuccess(): void {
    if (!this.hapticsEnabled.value) return;
    try {
      if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(75);
      }
    } catch {}
  }

  vibrateWarning(): void {
    if (!this.hapticsEnabled.value) return;
    try {
      if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        // Double pulse for duplicate / warning
        navigator.vibrate([90, 60, 90]);
      }
    } catch {}
  }

  vibrateError(): void {
    if (!this.hapticsEnabled.value) return;
    try {
      if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        // Triple urgent buzz for denied entry
        navigator.vibrate([140, 70, 140]);
      }
    } catch {}
  }
}

export const sounds = new SoundEffects();
