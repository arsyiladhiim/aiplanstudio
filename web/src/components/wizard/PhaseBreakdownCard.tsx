import { Card, Markdown } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Copy } from "lucide-react";
import { copyToClipboard } from "@/lib/clipboard";

export interface SubItem {
  key?: string;
  title?: string;
  desc?: string;
  func?: string;
  parent?: string;
  steps?: string;
  endpoint?: string;
  method?: string;
}

export interface PhaseItem {
  key?: string;
  title?: string;
  tasks?: string[];
  prompt?: string;
  ac?: string;
  halaman?: SubItem[];
  menu?: SubItem[];
  fitur?: SubItem[];
  flow?: SubItem[];
  api?: SubItem[];
}

const TYPE_ICONS: Record<string, string> = {
  halaman: "📄",
  menu: "☰",
  fitur: "⚙",
  flow: "→",
  api: "{}",
};

const TYPE_LABELS: Record<string, string> = {
  halaman: "Halaman",
  menu: "Menu",
  fitur: "Fitur",
  flow: "Flow",
  api: "API",
};

function SubItemList({ items, type }: { items?: SubItem[]; type: keyof typeof TYPE_LABELS }) {
  if (!items || items.length === 0) return null;
  return (
    <div className="mt-1.5">
      <div className="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">
        {TYPE_ICONS[type]} {TYPE_LABELS[type]}
      </div>
      <ul className="mt-0.5 space-y-0.5">
        {items.map((it, j) => (
          <li key={it.key || j} className="text-xs text-[var(--color-fg-muted)]">
            {it.key && <span className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{it.key}</span>} {it.title}
            {it.desc && <span className="text-[var(--color-fg-subtle)]"> — {it.desc}</span>}
            {it.func && <span className="text-[var(--color-fg-subtle)]"> — {it.func}</span>}
            {it.steps && <span className="text-[var(--color-fg-subtle)]"> — {it.steps}</span>}
            {it.endpoint && <span className="text-[var(--color-fg-subtle)]"> — {it.method} {it.endpoint}</span>}
          </li>
        ))}
      </ul>
    </div>
  );
}

export function PhaseBreakdownCard({
  phases,
  label,
  rawFallback,
}: {
  phases: PhaseItem[];
  label: string;
  rawFallback?: string;
}) {
  if (phases.length === 0 && rawFallback) {
    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{rawFallback}</Markdown>;
  }

  return (
    <Card className="p-4">
      <h3 className="mb-4 font-semibold">{label} ({phases.length} fase)</h3>
      <div className="space-y-3">
        {phases.map((p, i) => (
          <div key={p.key || i} className="rounded-lg border border-[var(--color-border)] p-4">
            <div className="flex items-start justify-between gap-2">
              <div className="flex-1">
                <div className="text-sm font-semibold">{p.title}</div>
                {p.tasks && p.tasks.length > 0 && (
                  <ul className="mt-1 list-disc pl-4 text-xs text-[var(--color-fg-muted)]">
                    {p.tasks.map((t, j) => <li key={j}>{t}</li>)}
                  </ul>
                )}
                <SubItemList items={p.halaman} type="halaman" />
                <SubItemList items={p.menu} type="menu" />
                <SubItemList items={p.fitur} type="fitur" />
                <SubItemList items={p.flow} type="flow" />
                <SubItemList items={p.api} type="api" />
                {p.ac && (
                  <div className="mt-1 text-xs text-[var(--color-fg-muted)]">
                    <span className="font-medium">AC:</span> {p.ac}
                  </div>
                )}
              </div>
              {p.prompt && (
                <Button variant="secondary" size="sm" onClick={() => copyToClipboard(p.prompt ?? '')}>
                  <Copy size={12} /> Copy Prompt
                </Button>
              )}
            </div>
          </div>
        ))}
      </div>
    </Card>
  );
}
