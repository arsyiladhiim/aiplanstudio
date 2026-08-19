import { Card, Badge } from "@/components/ui";
import { Route, Component, GitBranch, ListTree } from "lucide-react";

interface HalamanItem {
  key: string;
  title: string;
  route: string;
  phase_owner: string;
  description?: string;
  components_used?: string[];
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

interface ComponentItem {
  key: string;
  title: string;
  type: string;
  used_in?: string[];
  props_signature?: string;
}

interface AppSpecWeb {
  version?: string;
  generated_at?: string;
  generated_from_stages?: string[];
  halaman?: HalamanItem[];
  navigation?: {
    primary_menu?: MenuItem[];
    user_menus?: MenuItem[];
    admin_menus?: MenuItem[];
  };
  flows?: FlowItem[];
  components?: ComponentItem[];
}

export function AppSpecWebView({ data }: { data: AppSpecWeb | string | null | undefined }) {
  const spec: AppSpecWeb | null = typeof data === "string"
    ? (() => {
        try { return JSON.parse(data) as AppSpecWeb; }
        catch { return null; }
      })()
    : data ?? null;

  if (!spec) {
    return <p className="text-sm text-[var(--color-fg-subtle)] italic">App spec web belum di-generate.</p>;
  }

  return (
    <div className="space-y-4">
      <SpecHeader spec={spec} platform="Web" />

      <div className="grid gap-4 md:grid-cols-2">
        <HalamanSection items={spec.halaman ?? []} />
        <NavigationSection nav={spec.navigation ?? {}} />
      </div>

      <FlowsSection flows={spec.flows ?? []} />

      <ComponentsSection components={spec.components ?? []} halaman={spec.halaman ?? []} />
    </div>
  );
}

function SpecHeader({ spec, platform }: { spec: AppSpecWeb; platform: string }) {
  return (
    <div className="flex flex-wrap items-center gap-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs">
      <Badge tone="brand">{platform}</Badge>
      {spec.version && <Badge tone="muted">v{spec.version}</Badge>}
      {spec.generated_at && <span className="text-[var(--color-fg-subtle)]">{spec.generated_at}</span>}
      {spec.generated_from_stages && spec.generated_from_stages.length > 0 && (
        <span className="text-[var(--color-fg-subtle)]">
          from: {spec.generated_from_stages.join(", ")}
        </span>
      )}
    </div>
  );
}

function HalamanSection({ items }: { items: HalamanItem[] }) {
  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <Route size={14} />
        <h3 className="text-sm font-semibold">Halaman ({items.length})</h3>
      </div>
      <div className="space-y-2">
        {items.map((h) => (
          <div key={h.key} className="rounded border border-[var(--color-border-soft)] bg-[var(--color-bg-soft)] p-2 text-xs">
            <div className="flex items-center gap-2">
              <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{h.key}</code>
              <span className="font-medium">{h.title}</span>
              <Badge tone="muted" className="ml-auto">{h.route}</Badge>
            </div>
            {h.description && (
              <p className="mt-1 text-[var(--color-fg-muted)]">{h.description}</p>
            )}
            {h.phase_owner && (
              <Badge tone="brand" className="mt-1">phase: {h.phase_owner}</Badge>
            )}
            {h.design_signature && (
              <p className="mt-1 text-[var(--color-fg-subtle)]">signature: {h.design_signature}</p>
            )}
          </div>
        ))}
      </div>
    </Card>
  );
}

function NavigationSection({ nav }: { nav: NonNullable<AppSpecWeb["navigation"]> }) {
  const groups: Array<{ label: string; items: MenuItem[] | undefined }> = [
    { label: "Primary", items: nav.primary_menu },
    { label: "User", items: nav.user_menus },
    { label: "Admin", items: nav.admin_menus },
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

function ComponentsSection({ components, halaman }: { components: ComponentItem[]; halaman: HalamanItem[] }) {
  const usedInMap = new Map<string, string[]>();
  for (const h of halaman) {
    for (const ref of h.components_used ?? []) {
      if (!usedInMap.has(ref)) usedInMap.set(ref, []);
      usedInMap.get(ref)!.push(h.title);
    }
  }

  return (
    <Card className="p-4">
      <div className="mb-2 flex items-center gap-2">
        <Component size={14} />
        <h3 className="text-sm font-semibold">Components ({components.length})</h3>
      </div>
      <div className="space-y-2">
        {components.map((c) => {
          const reverseRefs = usedInMap.get(c.key) ?? [];
          const allRefs = Array.from(new Set([...(c.used_in ?? []), ...reverseRefs]));
          return (
            <div key={c.key} className="rounded border border-[var(--color-border-soft)] bg-[var(--color-bg-soft)] p-2 text-xs">
              <div className="flex items-center gap-2">
                <code className="font-mono text-[10px] text-[var(--color-fg-subtle)]">{c.key}</code>
                <span className="font-medium">{c.title}</span>
                <Badge tone="muted" className="ml-auto">{c.type}</Badge>
              </div>
              {c.props_signature && (
                <pre className="mt-1 overflow-x-auto rounded bg-[var(--color-surface-2)] p-2 font-mono text-[10px] text-[var(--color-fg-muted)]">
                  {c.props_signature}
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
