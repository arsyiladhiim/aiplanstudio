"use client";
import { useEffect, useState } from "react";

export interface ThroughputProps {
  startedAt: number | null;
  bytes: number;
  modelRate?: number;
  className?: string;
}

function fmtDuration(s: number): string {
  if (!Number.isFinite(s) || s < 0) return "0:00";
  const m = Math.floor(s / 60);
  const sec = Math.floor(s % 60);
  return `${m}:${sec.toString().padStart(2, "0")}`;
}

export function StageThroughputBar({
  startedAt,
  bytes,
  modelRate,
  className,
}: ThroughputProps) {
  const [now, setNow] = useState(() => Date.now());

  useEffect(() => {
    const t = setInterval(() => setNow(Date.now()), 500);
    return () => clearInterval(t);
  }, []);

  const elapsed = startedAt ? Math.max(0, (now - startedAt) / 1000) : 0;
  const tokens = Math.max(1, Math.floor(bytes / 4));
  const tps = elapsed > 0 ? tokens / elapsed : 0;
  const cost = typeof modelRate === "number" ? tokens * modelRate : null;

  return (
    <div
      className={`flex items-center gap-3 font-mono text-[11px] text-[var(--color-fg-muted)] ${className ?? ""}`}
      style={{ fontVariantNumeric: "tabular-nums" }}
    >
      <span><strong className="text-[var(--color-fg)]">{tokens.toLocaleString("id-ID")}</strong> tokens</span>
      <span aria-hidden>·</span>
      <span>{tps.toFixed(1)} tok/s</span>
      <span aria-hidden>·</span>
      <span>{fmtDuration(elapsed)}</span>
      {cost !== null && (
        <>
          <span aria-hidden>·</span>
          <span>~${cost.toFixed(4)}</span>
        </>
      )}
    </div>
  );
}
