import { Badge } from "@/components/ui";
import { Loader2 } from "lucide-react";
import type { PhaseItem } from "./PhaseBreakdownCard";

export interface ProgressItem {
  phase_key: string;
  done: boolean;
  status?: "pending" | "running" | "done" | "error";
  output?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
}

export function TrackingPhases({
  phases,
  progMap,
}: {
  phases: PhaseItem[];
  progMap: Record<string, ProgressItem>;
}) {
  const badge = (p?: ProgressItem) => {
    const st = p?.status ?? "pending";
    if (st === "running") return <Badge tone="brand"><Loader2 size={11} className="animate-spin" /> Running</Badge>;
    if (st === "done") return <Badge tone="success">Selesai</Badge>;
    if (st === "error") return <Badge tone="danger">Error</Badge>;
    return <Badge tone="muted">Menunggu</Badge>;
  };

  return (
    <div className="mb-4 overflow-hidden rounded-xl border border-[var(--color-border)]">
      <div className="flex items-center justify-between bg-[var(--color-surface-2)] px-4 py-2">
        <h4 className="text-sm font-semibold">Tracking Fase</h4>
        <span className="text-xs text-[var(--color-fg-muted)]">
          {phases.filter((p) => progMap[p.key ?? ""]?.status === "done").length}/{phases.length} selesai
        </span>
      </div>
      <div className="divide-y divide-[var(--color-border)]">
        {phases.length === 0 && (
          <div className="px-4 py-3 text-xs text-[var(--color-fg-muted)]">Belum ada fase. Jalankan agent dengan master prompt untuk mulai tracking.</div>
        )}
        {phases.map((p, i) => {
          const prog = progMap[p.key ?? ""];
          return (
            <div key={p.key ?? i} className="flex items-center justify-between gap-2 px-4 py-2">
              <div className="min-w-0">
                <div className="truncate text-sm">{p.title}</div>
                {prog?.output ? (
                  <div className="mt-0.5 truncate text-xs text-[var(--color-fg-muted)]">{prog.output}</div>
                ) : prog?.started_at || prog?.finished_at ? (
                  <div className="mt-0.5 text-xs text-[var(--color-fg-subtle)]">
                    {prog.finished_at ? new Date(prog.finished_at).toLocaleString("id-ID") : prog.started_at ? new Date(prog.started_at).toLocaleString("id-ID") : ""}
                  </div>
                ) : null}
              </div>
              {badge(prog)}
            </div>
          );
        })}
      </div>
      <p className="px-4 py-2 text-[10px] text-[var(--color-fg-subtle)]">
        Status diperbarui real-time oleh AI agent via webhook (Authorization Bearer).
      </p>
    </div>
  );
}
