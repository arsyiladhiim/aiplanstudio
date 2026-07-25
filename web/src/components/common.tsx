import { Badge } from "@/components/ui";
import { Globe, Smartphone, Layers } from "lucide-react";
import type { Target } from "@/lib/mock";

export function PageHeader({ title, subtitle, action }: { title: string; subtitle?: string; action?: React.ReactNode }) {
  return (
    <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
      <div>
        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
        {subtitle && <p className="mt-1 text-sm text-[var(--color-fg-muted)]">{subtitle}</p>}
      </div>
      {action}
    </div>
  );
}

export function ProgressBar({ value }: { value: number }) {
  return (
    <div className="h-2 w-full overflow-hidden rounded-full bg-[var(--color-surface-2)]">
      <div
        className="h-full rounded-full bg-[linear-gradient(90deg,var(--color-brand),var(--color-brand-2))] transition-all"
        style={{ width: `${value}%` }}
      />
    </div>
  );
}

export function TargetBadge({ target }: { target: Target }) {
  const map = {
    web: { icon: Globe, label: "Web", tone: "brand" as const },
    mobile: { icon: Smartphone, label: "Mobile", tone: "success" as const },
    both: { icon: Layers, label: "Web + Mobile", tone: "warning" as const },
  };
  const { icon: Icon, label, tone } = map[target];
  return <Badge tone={tone}><Icon size={12} /> {label}</Badge>;
}
