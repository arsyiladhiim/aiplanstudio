"use client";
import { Badge } from "@/components/ui";
import { Lock, Unlock, CircleDashed, Loader2, Check, X, SkipForward, AlertTriangle, RotateCcw } from "lucide-react";
import type { StageGateName, StageState } from "@/lib/mock";

export interface WizardStageRailItem {
  key: string;
  label: string;
  state: StageState;
  gate: StageGateName;
  gatePassed?: boolean;
  gateReason?: string;
  retryCount?: number;
}

const STATE_ICON: Record<StageState, React.ReactNode> = {
  pending: <CircleDashed size={14} className="text-[var(--color-fg-subtle)]" />,
  ready: <Unlock size={14} className="text-[var(--color-fg-muted)]" />,
  running: <Loader2 size={14} className="animate-spin text-[var(--color-brand)]" />,
  done: <Check size={14} className="text-emerald-500" />,
  error: <X size={14} className="text-rose-500" />,
  blocked: <AlertTriangle size={14} className="text-amber-500" />,
  skipped: <SkipForward size={14} className="text-[var(--color-fg-subtle)]" />,
  retrying: <RotateCcw size={14} className="text-amber-500 animate-spin" />,
};

/**
 * CP-46.A — read-only stage rail dengan status + gate lock + retry badge.
 * Full interactivity (click-to-jump) menyusul di CP-46.D decomposition.
 */
export function WizardStageRail({ stages }: { stages: WizardStageRailItem[] }) {
  return (
    <div data-testid="wizard-stage-rail" role="list" className="flex flex-col gap-1">
      {stages.map((s) => (
        <div
          key={s.key}
          role="listitem"
          title={s.gateReason ?? s.label}
          className={`flex items-center gap-2 rounded-md px-2 py-1.5 text-sm ${
            s.state === "running"
              ? "bg-[var(--color-surface-2)] font-medium"
              : "hover:bg-[var(--color-surface-2)]"
          }`}
        >
          {STATE_ICON[s.state] ?? STATE_ICON.pending}
          <span className={s.state === "done" ? "text-[var(--color-fg-muted)] line-through decoration-[var(--color-border)]" : ""}>
            {s.label}
          </span>
          {s.gate && (
            <Badge tone={s.gatePassed === false ? "warning" : "muted"} className="ml-auto gap-1 text-[10px]">
              <Lock size={10} />
              {s.gate.replace("Gate", "")}
            </Badge>
          )}
          {(s.retryCount ?? 0) > 0 && (
            <Badge tone="danger" className="ml-auto text-[10px]">
              retry ×{s.retryCount}
            </Badge>
          )}
        </div>
      ))}
    </div>
  );
}
