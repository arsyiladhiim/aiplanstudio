import { Loader2, Sparkles } from "lucide-react";
import { Button } from "@/components/ui/Button";

interface EmptyArtifactProps {
  title: string;
  description: string;
  stageKey: string;
  versionId: number;
  isRegenerating?: boolean;
  onGenerate: () => void;
  canGenerate?: boolean;
  blockReason?: string;
}

export function EmptyArtifact({
  title,
  description,
  isRegenerating = false,
  onGenerate,
  canGenerate = true,
  blockReason,
}: EmptyArtifactProps) {
  return (
    <div className="rounded-xl border border-dashed border-[var(--color-border)] bg-[var(--color-surface-2)] p-6 text-center">
      <div className="mx-auto mb-3 grid h-10 w-10 place-items-center rounded-lg bg-[color-mix(in_oklab,var(--color-brand)_12%,transparent)] text-[var(--color-brand)]">
        <Sparkles size={18} />
      </div>
      <h5 className="font-semibold text-sm">{title}</h5>
      <p className="mt-1 text-xs text-[var(--color-fg-muted)]">{description}</p>
      <div className="mt-3">
        {canGenerate ? (
          <Button
            size="sm"
            onClick={onGenerate}
            disabled={isRegenerating}
            data-testid={`generate-${title.toLowerCase().replace(/\s+/g, '-')}`}
          >
            {isRegenerating ? (
              <>
                <Loader2 size={13} className="animate-spin" />
                Generating...
              </>
            ) : (
              <>
                <Sparkles size={13} />
                Generate {title}
              </>
            )}
          </Button>
        ) : (
          <div className="text-xs text-[var(--color-fg-subtle)] italic">
            {blockReason ?? "Selesaikan dependency dulu"}
          </div>
        )}
      </div>
    </div>
  );
}
