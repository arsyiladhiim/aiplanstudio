import {
  Check,
  Loader2,
  AlertCircle,
  Circle,
  RotateCcw,
  SkipForward,
  Minus,
} from "lucide-react"
import type { LucideIcon } from "lucide-react"
import {
  HelpCircle,
  FileText,
  Layers,
  Database,
  Code2,
  Palette,
  ListChecks,
  ShieldCheck,
  Rocket,
  Activity,
  Bot,
  FileCode,
  BookOpen,
} from "lucide-react"

export type StageStatus =
  "pending" | "running" | "done" | "error" | "skipped" | "blocked"

const STAGE_ICONS: Record<string, LucideIcon> = {
  pertanyaan: HelpCircle,
  analisa: FileText,
  prd: FileText,
  architecture: Layers,
  erd: Database,
  api_contract: Code2,
  design_system: Palette,
  design_system_mobile: Palette,
  phases_web: ListChecks,
  phases_mobile: ListChecks,
  standards_web: BookOpen,
  standards_mobile: BookOpen,
  master_web: FileCode,
  master_mobile: FileCode,
  app_spec_web: FileCode,
  app_spec_mobile: FileCode,
  pertanyaan_mobile: HelpCircle,
  env_config: BookOpen,
  security: ShieldCheck,
  deployment: Rocket,
  observability: Activity,
  agents: Bot,
  master_prompt: FileCode,
}

interface StageRowProps {
  stageKey: string
  label: string
  status?: StageStatus
  quality?: number | null
  onRegenerate?: () => void
  isRegenerating?: boolean
  onSkip?: (reason: string) => void
  isSkipping?: boolean
}

export function StageRow({
  stageKey,
  label,
  status = "pending",
  quality,
  onRegenerate,
  isRegenerating = false,
  onSkip,
  isSkipping = false,
}: StageRowProps) {
  const Icon = STAGE_ICONS[stageKey] ?? Circle
  const statusColor =
    status === "done"
      ? "text-[var(--color-success)]"
      : status === "running"
        ? "text-[var(--color-brand)]"
        : status === "error"
          ? "text-[var(--color-danger)]"
          : status === "skipped"
            ? "text-[var(--color-fg-subtle)]"
            : status === "blocked"
              ? "text-[var(--color-warning,#f59e0b)]"
              : "text-[var(--color-fg-subtle)]"

  return (
    <div className="group flex items-center gap-2 rounded-md px-1.5 py-1 text-xs transition-colors hover:bg-[var(--color-surface-2)]">
      <span className="grid h-5 w-5 shrink-0 place-items-center">
        {status === "done" ? (
          <span className="grid h-4 w-4 place-items-center rounded-full bg-[var(--color-success)] text-white">
            <Check size={10} />
          </span>
        ) : status === "running" ? (
          <Loader2
            size={14}
            className="animate-spin text-[var(--color-brand)]"
          />
        ) : status === "error" ? (
          <AlertCircle size={14} className="text-[var(--color-danger)]" />
        ) : status === "skipped" ? (
          <Minus size={12} className="text-[var(--color-fg-subtle)]" />
        ) : status === "blocked" ? (
          <AlertCircle
            size={13}
            className="text-[var(--color-warning,#f59e0b)]"
          />
        ) : (
          <Circle size={12} className="text-[var(--color-fg-subtle)]" />
        )}
      </span>
      <Icon size={12} className={`shrink-0 ${statusColor}`} />
      <span
        className={`flex-1 ${status === "done" || status === "skipped" ? "text-[var(--color-fg-subtle)]" : status === "error" ? "text-[var(--color-danger)]" : ""}`}
      >
        {label}
        {status === "skipped" && (
          <span className="ml-1.5 rounded-full bg-[var(--color-surface-2)] px-1.5 py-0.5 text-[10px] text-[var(--color-fg-muted)]">
            Dilewati
          </span>
        )}
      </span>
      {status === "done" && typeof quality === "number" && (
        <span
          title={`Kualitas output: ${Math.round(quality * 100)}%`}
          className={`shrink-0 rounded-full px-1.5 py-0.5 text-[10px] font-medium ${
            quality >= 0.8
              ? "bg-[var(--color-success)]/15 text-[var(--color-success)]"
              : quality >= 0.6
                ? "bg-[var(--color-warning)]/15 text-[var(--color-warning)]"
                : "bg-[var(--color-danger)]/15 text-[var(--color-danger)]"
          }`}
        >
          {Math.round(quality * 100)}%
        </span>
      )}
      {status === "done" && onRegenerate && (
        <button
          onClick={onRegenerate}
          disabled={isRegenerating}
          className="rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-[var(--color-surface-3)]"
          title={`Regenerate ${label}`}
        >
          {isRegenerating ? (
            <Loader2
              size={11}
              className="animate-spin text-[var(--color-brand)]"
            />
          ) : (
            <RotateCcw
              size={11}
              className="text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            />
          )}
        </button>
      )}
      {status === "error" && onRegenerate && (
        <button
          onClick={onRegenerate}
          disabled={isRegenerating}
          className="shrink-0 rounded bg-[var(--color-danger)]/10 p-1 text-[var(--color-danger)] transition-colors hover:bg-[var(--color-danger)]/20"
          title={`Coba lagi (dengan perbaikan) — ${label}`}
          data-testid={`retry-${label.toLowerCase().replace(/\s+/g, "-")}`}
        >
          {isRegenerating ? (
            <Loader2 size={11} className="animate-spin" />
          ) : (
            <RotateCcw size={11} />
          )}
        </button>
      )}
      {status !== "done" &&
        status !== "running" &&
        status !== "error" &&
        onSkip && (
          <button
            onClick={() => {
              const reason = window.prompt(`Alasan skip "${label}":`)
              if (reason && reason.trim()) onSkip(reason.trim())
            }}
            disabled={isSkipping}
            className="rounded p-1 opacity-0 transition-opacity group-hover:opacity-100 hover:bg-[var(--color-surface-3)]"
            title={`Skip ${label}`}
          >
            {isSkipping ? (
              <Loader2
                size={11}
                className="animate-spin text-[var(--color-brand)]"
              />
            ) : (
              <SkipForward
                size={11}
                className="text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
              />
            )}
          </button>
        )}
    </div>
  )
}
