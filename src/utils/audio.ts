// Audio Synthesizer & Haptic Vibration Engine for Mobile Scanner Feedback
import { ref } from 'vue';

class SoundEffects {
  private ctx: AudioContext | null = null;
  public soundEnabled = ref<boolean>(true);
  public hapticsEnabled = ref<boolean>(true);

  constructor() {
    if (typeof window !== 'undefined') {
      try {
        const soundPref = localStorage.getItem('integ2_scanner_sound_enabled');
        if (soundPref !== null) {
          this.soundEnabled.value = soundPref === 'true';
        }
        const hapticPref = localStorage.getItem('integ2_scanner_haptic_enabled');
        if (hapticPref !== null) {
          this.hapticsEnabled.value = hapticPref === 'true';
        }
      } catch {}
    }
  }

  toggleSound(): boolean {
    this.soundEnabled.value = !this.soundEnabled.value;
    if (typeof window !== 'undefined') {
      try {
        localStorage.setItem('integ2_scanner_sound_enabled', String(this.soundEnabled.value));
      } catch {}
    }
    return this.soundEnabled.value;
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

  private init() {
    if (!this.ctx && typeof window !== 'undefined') {
      const AudioCtx = window.AudioContext || (window as unknown as { webkitAudioContext: typeof AudioContext }).webkitAudioContext;
      if (AudioCtx) {
        this.ctx = new AudioCtx();
      }
    }
    if (this.ctx && this.ctx.state === 'suspended') {
      this.ctx.resume();
    }
  }

  playSuccess() {
    if (!this.soundEnabled.value) return;
    try {
      this.init();
      if (!this.ctx) return;

      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'sine';
      // Pleasant dual ascending chime (D5 -> A5)
      osc.frequency.setValueAtTime(587.33, now); // D5
      osc.frequency.setValueAtTime(880.00, now + 0.08); // A5

      gain.gain.setValueAtTime(0.2, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.35);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.35);
    } catch {
      // Audio playback silently skipped if not permitted
    }
  }

  playWarning() {
    if (!this.soundEnabled.value) return;
    try {
      this.init();
      if (!this.ctx) return;

      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'triangle';
      // Distinct double warning tone (E5 -> C5)
      osc.frequency.setValueAtTime(659.25, now);
      osc.frequency.setValueAtTime(523.25, now + 0.12);

      gain.gain.setValueAtTime(0.25, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.38);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.38);
    } catch {}
  }

  playError() {
    if (!this.soundEnabled.value) return;
    try {
      this.init();
      if (!this.ctx) return;

      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'sawtooth';
      // Low alert buzz (A3 -> D#3)
      osc.frequency.setValueAtTime(220, now);
      osc.frequency.setValueAtTime(155, now + 0.12);

      gain.gain.setValueAtTime(0.25, now);
      gain.gain.exponentialRampToValueAtTime(0.01, now + 0.4);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.4);
    } catch {}
  }

  playClick() {
    if (!this.soundEnabled.value) return;
    try {
      this.init();
      if (!this.ctx) return;

      const now = this.ctx.currentTime;
      const osc = this.ctx.createOscillator();
      const gain = this.ctx.createGain();

      osc.type = 'triangle';
      osc.frequency.setValueAtTime(1200, now);
      gain.gain.setValueAtTime(0.05, now);
      gain.gain.exponentialRampToValueAtTime(0.001, now + 0.05);

      osc.connect(gain);
      gain.connect(this.ctx.destination);

      osc.start(now);
      osc.stop(now + 0.05);
    } catch {}
  }

  // --- Haptic Mobile Vibration Synthesis ---

  vibrateSuccess() {
    if (!this.hapticsEnabled.value) return;
    try {
      if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        navigator.vibrate(75);
      }
    } catch {}
  }

  vibrateWarning() {
    if (!this.hapticsEnabled.value) return;
    try {
      if (typeof navigator !== 'undefined' && 'vibrate' in navigator) {
        // Double pulse for duplicate / warning
        navigator.vibrate([90, 60, 90]);
      }
    } catch {}
  }

  vibrateError() {
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
