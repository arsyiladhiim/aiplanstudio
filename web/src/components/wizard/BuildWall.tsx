"use client";
import { X, Maximize2 } from "lucide-react";
import { useEffect } from "react";
import { StreamingMarkdown } from "./StreamingMarkdown";
import { StageThroughputBar } from "./StageThroughputBar";

export interface BuildWallProps {
  open: boolean;
  stageLabel: string;
  content: string;
  isRunning: boolean;
  onClose: () => void;
  sidebar?: React.ReactNode;
  throughput?: { startedAt: number | null; bytes: number };
}

export function BuildWall({
  open,
  stageLabel,
  content,
  isRunning,
  onClose,
  sidebar,
  throughput,
}: BuildWallProps) {
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      if (e.key === "Escape") onClose();
    };
    window.addEventListener("keydown", onKey);
    document.body.style.overflow = "hidden";
    return () => {
      window.removeEventListener("keydown", onKey);
      document.body.style.overflow = "";
    };
  }, [open, onClose]);

  if (!open) return null;

  return (
    <div
      role="dialog"
      aria-modal="true"
      aria-label={`Build wall — ${stageLabel}`}
      className="fixed inset-0 z-50 flex flex-col bg-[var(--color-bg)]/95 backdrop-blur"
    >
      <header className="flex shrink-0 items-center justify-between border-b border-[var(--color-border)] bg-[var(--color-surface)] px-5 py-3">
        <div className="flex items-center gap-3">
          <Maximize2 size={16} className="text-[var(--color-brand)]" />
          <div>
            <div className="text-[10px] uppercase tracking-wider text-[var(--color-fg-subtle)]">Live Build Wall</div>
            <div className="text-sm font-semibold">{stageLabel}</div>
          </div>
          {throughput && (
            <div className="ml-4">
              <StageThroughputBar
                startedAt={throughput.startedAt}
                bytes={throughput.bytes}
              />
            </div>
          )}
        </div>
        <button
          type="button"
          onClick={onClose}
          aria-label="Tutup build wall"
          className="grid h-9 w-9 place-items-center rounded-full border border-[var(--color-border)] bg-[var(--color-bg-soft)] text-[var(--color-fg-muted)] transition hover:bg-[var(--color-surface)]"
        >
          <X size={16} />
        </button>
      </header>

      <div className="grid min-h-0 flex-1 grid-cols-1 gap-0 lg:grid-cols-[300px_1fr_360px]">
        <aside className="hidden min-h-0 overflow-y-auto border-r border-[var(--color-border)] bg-[var(--color-surface)] p-4 lg:block">
          {sidebar}
        </aside>
        <main className="min-h-0 overflow-hidden p-5">
          <StreamingMarkdown content={content || "Menunggu output AI..."} live={isRunning} />
        </main>
        <aside className="hidden min-h-0 overflow-y-auto border-l border-[var(--color-border)] bg-[var(--color-surface)] p-4 lg:block">
          <div className="text-xs text-[var(--color-fg-muted)]">
            <p className="mb-2 font-semibold text-[var(--color-fg)]">Tips</p>
            <ul className="list-disc space-y-1 pl-4">
              <li>Tekan <kbd className="rounded border px-1 text-[10px]">Esc</kbd> untuk keluar dari mode layar penuh.</li>
              <li>Scroll ke bawah otomatis saat AI menulis, kecuali kamu scroll ke atas.</li>
              <li>Tab &ldquo;Formatted&rdquo; merender Markdown real-time; &ldquo;Raw&rdquo; menampilkan teks mentah.</li>
            </ul>
          </div>
        </aside>
      </div>
    </div>
  );
}
