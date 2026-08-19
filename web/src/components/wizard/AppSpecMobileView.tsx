import { Card, Badge } from "@/components/ui";
import { Route, Component, GitBranch, ListTree } from "lucide-react";

interface ScreenItem {
  key: string;
  title: string;
  route: string;
  dart_path: string;
  phase_owner: string;
  description?: string;
  widgets_used?: string[];
  design_signature?: string;
}

interface MenuItem {
  key: string;
  title: string;
  icon?: string;
  route?: string;
}

interface FlowStep {
  order: number;
  from: string;
  action: string;
  to: string;
}

interface FlowItem {
  key: string;
  title: string;
  steps: FlowStep[];
}

interface WidgetItem {
  key: string;
  title: string;
  type: string;
  used_in?: string[];
  props_signature?: string;
}

interface AppSpecMobile {
  version?: string;
  generated_at?: string;
  generated_from_stages?: string[];
  screens?: ScreenItem[];
  navigation?: {
    primary_menu?: MenuItem[];
    bottom_nav?: MenuItem[];
    drawer_items?: MenuItem[];
  };
  flows?: FlowItem[];
  widgets?: WidgetItem[];
}

export function AppSpecMobileView({ data }: { data: AppSpecMobile | string | null | undefined }) {
  const spec: AppSpecMobile | null = typeof data === "string"
    ? (() => {
        try { return JSON.parse(data) as AppSpecMobile; }
        catch { return null; }
      })()
    : data ?? null;

  if (!spec) {
    return <p className="text-sm text-[var(--color-fg-subtle)] italic">App spec mobile belum di-generate.</p>;
  }

  return (
    <div className="space-y-4">
      <div className="flex flex-wrap items-center gap-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs">
        <Badge tone="brand">Mobile (Flutter)</Badge>
        {spec.version && <Badge tone="muted">v{spec.version}</Badge>}
        {spec.generated_at && <span className="text-[var(--color-fg-subtle)]">{spec.generated_at}</span>}
        {spec.generated_from_stages && spec.generated_from_stages.length > 0 && (
          <span className="text-[var(--color-fg-subtle)]">
            from: {spec.generated_from_stages.join(", ")}
          </span>
        )}
      </div>

      <div className="grid gap-4 md:grid-cols-2">
        <ScreensSection items={spec.screens ?? []} />
        <NavigationSection nav={spec.navigation ?? {}} />
      </div>

      <FlowsSection flows={spec.flows ?? []} />

      <WidgetsSection widgets={spec.widgets ?? []} screens={spec.screens ?? []} />
    </div>
  );
}

function ScreensSection({ items }: { items: ScreenItem[] }) {
  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <Route size={14} />
        <h3 className="text-sm font-semibold">Screens ({items.length})</h3>
      </div>
      <div className="space-y-2">
        {items.map((s) => (
          <div key={s.key} className="rounded border border-[var(--color-border-soft)] bg-[var(--color-bg-soft)] p-2 text-xs">
            <div className="flex items-center gap-2">
              <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{s.key}</code>
              <span className="font-medium">{s.title}</span>
              <Badge tone="muted" className="ml-auto">{s.route}</Badge>
            </div>
            {s.dart_path && (
              <code className="mt-1 block font-mono text-[10px] text-[var(--color-fg-subtle)]">{s.dart_path}</code>
            )}
            {s.description && (
              <p className="mt-1 text-[var(--color-fg-muted)]">{s.description}</p>
            )}
            {s.phase_owner && (
              <Badge tone="brand" className="mt-1">phase: {s.phase_owner}</Badge>
            )}
            {s.design_signature && (
              <p className="mt-1 text-[var(--color-fg-subtle)]">signature: {s.design_signature}</p>
            )}
          </div>
        ))}
      </div>
    </Card>
  );
}

function NavigationSection({ nav }: { nav: NonNullable<AppSpecMobile["navigation"]> }) {
  const groups: Array<{ label: string; items: MenuItem[] | undefined }> = [
    { label: "Primary", items: nav.primary_menu },
    { label: "Bottom Nav", items: nav.bottom_nav },
    { label: "Drawer", items: nav.drawer_items },
  ];

  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <ListTree size={14} />
        <h3 className="text-sm font-semibold">Navigation</h3>
      </div>
      <div className="space-y-3">
        {groups.filter((g) => g.items && g.items.length > 0).map((g) => (
          <div key={g.label}>
            <p className="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">{g.label}</p>
            <div className="mt-1 space-y-1">
              {g.items!.map((m) => (
                <div key={m.key} className="flex items-center gap-2 rounded border border-[var(--color-border-soft)] px-2 py-1 text-xs">
                  <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{m.key}</code>
                  <span>{m.title}</span>
                  {m.icon && <Badge tone="muted" className="ml-auto">{m.icon}</Badge>}
                  {m.route && <code className="text-[10px] text-[var(--color-fg-subtle)]">{m.route}</code>}
                </div>
              ))}
            </div>
          </div>
        ))}
      </div>
    </Card>
  );
}

function FlowsSection({ flows }: { flows: FlowItem[] }) {
  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <GitBranch size={14} />
        <h3 className="text-sm font-semibold">Flows ({flows.length})</h3>
      </div>
      <div className="space-y-3">
        {flows.map((f) => (
          <div key={f.key} className="rounded border border-[var(--color-border-soft)] bg-[var(--color-bg-soft)] p-2 text-xs">
            <div className="mb-1 flex items-center gap-2">
              <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{f.key}</code>
              <span className="font-medium">{f.title}</span>
            </div>
            <ol className="ml-4 space-y-1 text-[var(--color-fg-muted)]">
              {f.steps.map((s) => (
                <li key={s.order}>
                  <span className="font-mono text-[var(--color-fg-subtle)]">{s.order}.</span>{" "}
                  <code className="text-[10px]">{s.from}</code>{" → "}
                  <span className="italic">{s.action}</span>{" → "}
                  <code className="text-[10px]">{s.to}</code>
                </li>
              ))}
            </ol>
          </div>
        ))}
      </div>
    </Card>
  );
}

function WidgetsSection({ widgets, screens }: { widgets: WidgetItem[]; screens: ScreenItem[] }) {
  const usedInMap = new Map<string, string[]>();
  for (const s of screens) {
    for (const ref of s.widgets_used ?? []) {
      if (!usedInMap.has(ref)) usedInMap.set(ref, []);
      usedInMap.get(ref)!.push(s.title);
    }
  }

  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <Component size={14} />
        <h3 className="text-sm font-semibold">Widgets ({widgets.length})</h3>
      </div>
      <div className="space-y-2">
        {widgets.map((w) => {
          const reverseRefs = usedInMap.get(w.key) ?? [];
          const allRefs = Array.from(new Set([...(w.used_in ?? []), ...reverseRefs]));
          return (
            <div key={w.key} className="rounded border border-[var(--color-border-soft)] bg-[var(--color-bg-soft)] p-2 text-xs">
              <div className="flex items-center gap-2">
                <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{w.key}</code>
                <span className="font-medium">{w.title}</span>
                <Badge tone="muted" className="ml-auto">{w.type}</Badge>
              </div>
              {w.props_signature && (
                <pre className="mt-1 overflow-x-auto rounded bg-[var(--color-surface-2)] p-2 font-mono text-[10px] text-[var(--color-fg-muted)]">
                  {w.props_signature}
                </pre>
              )}
              {allRefs.length > 0 && (
                <div className="mt-1.5 flex flex-wrap items-center gap-1">
                  <span className="text-[10px] uppercase tracking-wide text-[var(--color-fg-subtle)]">Used in:</span>
                  {allRefs.map((u) => (
                    <Badge key={u} tone="brand">{u}</Badge>
                  ))}
                </div>
              )}
            </div>
          );
        })}
      </div>
    </Card>
  );
}
