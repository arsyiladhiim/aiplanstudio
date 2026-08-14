"use client";
import { useEffect, useState, useRef, useCallback } from "react";
import { Button } from "@/components/ui/Button";
import { X, ChevronLeft, ChevronRight } from "lucide-react";

const STORAGE_KEY = "onboarding:completed";

interface Step {
  selector: string;
  title: string;
  body: string;
}

const STEPS: Step[] = [
  {
    selector: "[data-onboarding='new-plan']",
    title: "Mulai dari sini",
    body: "Klik 'Buat Plan Baru' untuk mulai bikin project pertamamu. AI akan memandu dari ide sampai blueprint.",
  },
  {
    selector: "[data-onboarding='projects-nav']",
    title: "Semua project",
    body: "Lihat semua project kamu di sini. Bisa filter berdasarkan favorit, arsip, atau pencarian.",
  },
  {
    selector: "[data-onboarding='search']",
    title: "Cari cepat",
    body: "Ketik judul atau ide project di sini untuk jump langsung ke yang kamu cari.",
  },
  {
    selector: "[data-onboarding='settings-nav']",
    title: "Pengaturan",
    body: "Atur AI provider, profil, dan preferensi lain di menu Settings.",
  },
];

interface Rect { top: number; left: number; width: number; height: number; }

function getRect(selector: string): Rect | null {
  const el = document.querySelector(selector);
  if (!el) return null;
  const r = el.getBoundingClientRect();
  return { top: r.top, left: r.left, width: r.width, height: r.height };
}

export function OnboardingTour() {
  const [active, setActive] = useState(false);
  const [step, setStep] = useState(0);
  const [rect, setRect] = useState<Rect | null>(null);
  const completedRef = useRef(false);

  const finish = useCallback(() => {
    try { localStorage.setItem(STORAGE_KEY, "1"); } catch {}
    completedRef.current = true;
    setActive(false);
  }, []);

  const skip = useCallback(() => finish(), [finish]);

  useEffect(() => {
    if (completedRef.current) return;
    let seen = "0";
    try { seen = localStorage.getItem(STORAGE_KEY) ?? "0"; } catch {}
    if (seen === "1") return;
    completedRef.current = false;
    setActive(true);
  }, []);

  useEffect(() => {
    if (!active) return;
    const sel = STEPS[step].selector;
    const update = () => setRect(getRect(sel));
    update();
    window.addEventListener("resize", update);
    window.addEventListener("scroll", update, true);
    return () => {
      window.removeEventListener("resize", update);
      window.removeEventListener("scroll", update, true);
    };
  }, [active, step]);

  const next = useCallback(() => {
    setStep((s) => {
      if (s + 1 >= STEPS.length) {
        finish();
        return s;
      }
      return s + 1;
    });
  }, [finish]);
  const prev = useCallback(() => setStep((s) => (s > 0 ? s - 1 : s)), []);

  const nextRef = useRef(next);
  const prevRef = useRef(prev);
  useEffect(() => { nextRef.current = next; prevRef.current = prev; }, [next, prev]);

  useEffect(() => {
    if (!active) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") return skip();
      if (e.key === "ArrowRight") return nextRef.current();
      if (e.key === "ArrowLeft") return prevRef.current();
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
  }, [active, skip]);

  if (!active || !rect) return null;

  const current = STEPS[step];
  const pad = 8;
  const tooltipW = 320;
  const tooltipBelow = rect.top + rect.height + pad + 200 < window.innerHeight;
  const tipTop = tooltipBelow ? rect.top + rect.height + pad : Math.max(pad, rect.top - 180);
  const tipLeft = Math.min(Math.max(pad, rect.left + rect.width / 2 - tooltipW / 2), window.innerWidth - tooltipW - pad);

  return (
    <div className="fixed inset-0 z-[60]" data-testid="onboarding-tour">
      <svg className="absolute inset-0 h-full w-full">
        <defs>
          <mask id="onb-mask">
            <rect width="100%" height="100%" fill="white" />
            <rect
              x={rect.left - pad}
              y={rect.top - pad}
              width={rect.width + pad * 2}
              height={rect.height + pad * 2}
              rx="12"
              fill="black"
            />
          </mask>
        </defs>
        <rect width="100%" height="100%" fill="rgba(0,0,0,0.6)" mask="url(#onb-mask)" />
        <rect
          x={rect.left - pad}
          y={rect.top - pad}
          width={rect.width + pad * 2}
          height={rect.height + pad * 2}
          rx="12"
          fill="none"
          stroke="var(--color-brand)"
          strokeWidth="2"
        />
      </svg>

      <div
        className="absolute z-10 w-[320px] rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 shadow-2xl"
        style={{ top: tipTop, left: tipLeft }}
      >
        <button
          onClick={skip}
          aria-label="Lewati tour"
          className="absolute right-2 top-2 text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
        >
          <X size={16} />
        </button>
        <div className="text-xs font-medium text-[var(--color-brand)]">
          Step {step + 1} dari {STEPS.length}
        </div>
        <div className="mt-1 font-semibold">{current.title}</div>
        <p className="mt-2 text-sm text-[var(--color-fg-muted)]">{current.body}</p>
        <div className="mt-4 flex items-center justify-between gap-2">
          <Button variant="ghost" size="sm" onClick={skip}>Lewati</Button>
          <div className="flex items-center gap-1">
            <Button
              variant="ghost"
              size="icon"
              onClick={prev}
              disabled={step === 0}
              aria-label="Sebelumnya"
            >
              <ChevronLeft size={16} />
            </Button>
            <Button size="sm" onClick={next} data-testid="onboarding-next">
              {step + 1 === STEPS.length ? "Selesai" : "Lanjut"}
              <ChevronRight size={14} />
            </Button>
          </div>
        </div>
      </div>
    </div>
  );
}
