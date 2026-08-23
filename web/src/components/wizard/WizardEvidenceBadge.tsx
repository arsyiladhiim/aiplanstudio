"use client";
import { Badge } from "@/components/ui";
import { Check, X, ShieldCheck, FlaskConical, Rocket, Gauge, Database, FileCode2, type LucideIcon } from "lucide-react";
import type { StageEvidence } from "@/hooks/useStageEvidence";

interface FlagMeta {
  icon: LucideIcon;
  label: string;
  tone: "success" | "warning" | "danger" | "muted";
}

const FLAG_META: Record<keyof Pick<StageEvidence, "tests_passed" | "lint_passed" | "build_passed" | "migrate_passed" | "security_passed" | "perf_passed">, FlagMeta> = {
  tests_passed: { icon: FlaskConical, label: "Tests", tone: "success" },
  lint_passed: { icon: FileCode2, label: "Lint", tone: "success" },
  build_passed: { icon: Rocket, label: "Build", tone: "success" },
  migrate_passed: { icon: Database, label: "Migrate", tone: "success" },
  security_passed: { icon: ShieldCheck, label: "Security", tone: "success" },
  perf_passed: { icon: Gauge, label: "Perf", tone: "success" },
};

/**
 * CP-46.B — per-stage evidence icon cluster.
 * Renders icon + label per check flag; absent → muted "no evidence".
 */
export function WizardEvidenceBadge({ evidence }: { evidence?: StageEvidence }) {
  if (!evidence) {
    return (
      <Badge tone="muted" data-testid="evidence-badge-none" title="Belum ada evidence dari agent">
        No evidence
      </Badge>
    );
  }

  const passed = (Object.keys(FLAG_META) as Array<keyof typeof FLAG_META>).filter((k) => evidence[k]);

  if (passed.length === 0) {
    return (
      <Badge tone="warning" data-testid="evidence-badge-empty" title="Evidence ada tapi semua flag false">
        <X size={10} /> No checks passed
      </Badge>
    );
  }

  return (
    <div className="flex items-center gap-1" data-testid="evidence-badge">
      {passed.map((key) => {
        const meta = FLAG_META[key];
        const Icon = meta.icon;
        return (
          <Badge key={key} tone={meta.tone} className="gap-1 text-[10px]" title={`${meta.label}: passed`}>
            <Icon size={10} />
            {meta.label}
          </Badge>
        );
      })}
    </div>
  );
}
