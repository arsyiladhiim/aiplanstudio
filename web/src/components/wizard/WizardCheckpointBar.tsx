"use client";
import { Badge, Button } from "@/components/ui";
import { AlertCircle, Loader2 } from "lucide-react";
import type { StageKey, StageState } from "@/lib/mock";

export interface CheckpointBarProps {
  stages: { key: StageKey; label: string }[];
  status: Record<StageKey, StageState>;
  currentIdx: number;
  retryInfo?: { attempt: number; max: number } | null;
  startedAt?: number | null;
  onCancel?: () => void;
}

/**
 * CP-46.D step 4 — top checkpoint bar: current stage + progress % + retry + elapsed.
 */
export function WizardCheckpointBar({
  stages,
  status,
  currentIdx,
  retryInfo,
  startedAt,
  onCancel,
}: CheckpointBarProps) {
  const doneCount = stages.filter((s) => status[s.key] === "done").length;
  const pct = stages.length > 0 ? (doneCount / stages.length) * 100 : 0;
  const current = stages[currentIdx];

  return (
    <div
      data-testid="wizard-checkpoint-bar"
      className="sticky top-0 z-10 border-b border-[var(--color-border)] bg-[var(--color-bg)] px-4 py-3 backdrop-blur"
    >
      <div className="mx-auto flex max-w-5xl flex-col gap-2">
        <div className="flex items-center gap-3">
          <span className="text-sm font-medium">
            {current ? current.label : "—"}
          </span>
          <Badge tone="muted" data-testid="checkpoint-progress">
            {doneCount}/{stages.length} tahap
          </Badge>
          {retryInfo && (
            <Badge tone="warning" data-testid="checkpoint-retry">
              Retry {retryInfo.attempt}/{retryInfo.max}
            </Badge>
          )}
          {startedAt && (
            <Badge tone="muted" className="ml-auto" title="Elapsed">
              <Loader2 size={10} className="mr-1 animate-spin inline" />
              {formatElapsed(startedAt)}
            </Badge>
          )}
          {onCancel && (
            <Button variant="ghost" size="sm" onClick={onCancel}>
              <AlertCircle size={12} /> Batalkan
            </Button>
          )}
        </div>
        <div className="h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
          <div
            data-testid="checkpoint-progress-fill"
            className="h-full rounded-full bg-[var(--color-brand)] transition-all duration-500"
            style={{ width: `${pct}%` }}
          />
        </div>
      </div>
    </div>
  );
}

function formatElapsed(since: number): string {
  const secs = Math.floor((Date.now() - since) / 1000);
  if (secs < 60) return `${secs}s`;
  const mins = Math.floor(secs / 60);
  if (mins < 60) return `${mins}m ${secs % 60}s`;
  const hrs = Math.floor(mins / 60);
  return `${hrs}h ${mins % 60}m`;
}
