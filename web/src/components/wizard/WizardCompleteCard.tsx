"use client";
import { Badge, Card } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { CheckCircle2, Sparkles } from "lucide-react";

export interface WizardCompleteCardProps {
  projectId: number;
  versionId: number;
  versionLabel: string;
  productionReadyAt?: string | null;
  evidenceCount: number;
}

/**
 * CP-46.D step 6 + CP-46.E — production-ready complete card.
 * Renders success badge + summary + link ke detail project.
 */
export function WizardCompleteCard({
  projectId,
  versionId,
  versionLabel,
  productionReadyAt,
  evidenceCount,
}: WizardCompleteCardProps) {
  const ready = !!productionReadyAt;

  return (
    <Card
      data-testid="wizard-complete"
      className={`border-emerald-500/30 p-5 ${ready ? "bg-emerald-500/5" : "bg-[var(--color-surface-1)]"}`}
    >
      <div className="flex items-start gap-3">
        {ready ? (
          <CheckCircle2 size={28} className="mt-1 shrink-0 text-emerald-500" />
        ) : (
          <Sparkles size={28} className="mt-1 shrink-0 text-[var(--color-brand)]" />
        )}
        <div className="flex-1 space-y-3">
          <div>
            <h3 className="text-base font-semibold">
              {ready ? "Production Ready" : "Wizard Selesai"}
            </h3>
            <p className="text-sm text-[var(--color-fg-muted)]">
              Versi <strong>{versionLabel}</strong> ({versionId}) —{" "}
              {evidenceCount} evidence dikumpulkan
              {productionReadyAt &&
                ` · siap-produksi sejak ${new Date(productionReadyAt).toLocaleString("id-ID")}`}
            </p>
          </div>

          <div className="flex flex-wrap gap-2">
            <Badge tone={ready ? "success" : "muted"}>
              {ready ? "production_ready" : "wizard_complete"}
            </Badge>
          </div>

          <div className="flex gap-2 pt-1">
            <ButtonLink
              size="sm"
              variant="primary"
              href={`/projects/${projectId}?version=${versionId}`}
            >
              Buka Detail Project
            </ButtonLink>
          </div>
        </div>
      </div>
    </Card>
  );
}
