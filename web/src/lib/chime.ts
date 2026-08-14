// WebAudio chime untuk CP-3 — subtle "ding" saat stage selesai.
// Tidak menambah bundle: native WebAudio API, synthesized oscillator.

const STORAGE_KEY = "wizard:chime";

function isEnabled(): boolean {
  if (typeof window === "undefined") return false;
  try {
    const v = window.localStorage.getItem(STORAGE_KEY);
    return v === null ? true : v === "1";
  } catch {
    return true;
  }
}

let ctx: AudioContext | null = null;
function getCtx(): AudioContext | null {
  if (typeof window === "undefined") return null;
  if (ctx) return ctx;
  try {
    const W = window as unknown as { AudioContext?: typeof AudioContext; webkitAudioContext?: typeof AudioContext };
    const Ctor = W.AudioContext ?? W.webkitAudioContext;
    if (!Ctor) return null;
    ctx = new Ctor();
    return ctx;
  } catch {
    return null;
  }
}

export function chime(): void {
  if (!isEnabled()) return;
  const c = getCtx();
  if (!c) return;
  if (c.state === "suspended") {
    c.resume().catch(() => {});
  }
  const freqs = [880, 1320];
  freqs.forEach((f, i) => {
    try {
      const o = c.createOscillator();
      const g = c.createGain();
      o.frequency.value = f;
      o.type = "sine";
      g.gain.value = 0.08;
      o.connect(g);
      g.connect(c.destination);
      const start = c.currentTime + i * 0.1;
      o.start(start);
      o.stop(start + 0.15);
    } catch {
      // ignore per-note failures
    }
  });
}

export function isChimeEnabled(): boolean {
  return isEnabled();
}

export function setChimeEnabled(enabled: boolean): void {
  if (typeof window === "undefined") return;
  try {
    window.localStorage.setItem(STORAGE_KEY, enabled ? "1" : "0");
  } catch {
    // ignore
  }
}
