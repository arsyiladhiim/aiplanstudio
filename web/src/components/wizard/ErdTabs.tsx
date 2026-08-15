"use client";
import { useState } from "react";
import dynamic from "next/dynamic";
import type { ErdData } from "@/lib/api";
import { ApiEndpointList, type ApiEndpoint } from "./ApiEndpointList";
import { Card, Badge } from "@/components/ui";
import { Database, Link2, ListTree } from "lucide-react";

const ErdDiagram = dynamic(() => import("./ErdDiagram").then((m) => m.ErdDiagram), {
  ssr: false,
  loading: () => (
    <div className="grid h-[460px] place-items-center rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-soft)] text-xs text-[var(--color-fg-subtle)]">
      Memuat diagram…
    </div>
  ),
});

type Tab = "diagram" | "api" | "tables";

export function ErdTabs({ erd, apiContract }: { erd?: ErdData; apiContract?: ApiEndpoint[] }) {
  const [tab, setTab] = useState<Tab>("diagram");
  const hasApi = apiContract && apiContract.length > 0;
  const hasErd = erd && (erd.nodes?.length ?? 0) > 0;

  const tabs: Array<{ key: Tab; label: string; icon: React.ReactNode; show: boolean }> = [
    { key: "diagram", label: "Diagram", icon: <Database size={12} />, show: hasErd ?? true },
    { key: "api", label: `API (${apiContract?.length ?? 0})`, icon: <Link2 size={12} />, show: hasApi ?? false },
    { key: "tables", label: `Tables (${erd?.nodes?.length ?? 0})`, icon: <ListTree size={12} />, show: hasErd ?? false },
  ];
  const visible = tabs.filter((t) => t.show);

  return (
    <div>
      <div className="mb-3 flex flex-wrap gap-1 border-b border-[var(--color-border)]">
        {visible.map((t) => (
          <button
            key={t.key}
            type="button"
            onClick={() => setTab(t.key)}
            className={`flex items-center gap-1.5 rounded-t-md px-3 py-1.5 text-xs font-medium transition-colors ${
              tab === t.key
                ? "border-b-2 border-[var(--color-brand)] text-[var(--color-brand)]"
                : "text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            }`}
          >
            {t.icon} {t.label}
          </button>
        ))}
      </div>
      {tab === "diagram" && (
        <ErdDiagram erd={erd} />
      )}
      {tab === "api" && hasApi && (
        <ApiEndpointList items={apiContract!} />
      )}
      {tab === "tables" && hasErd && erd?.nodes && (
        <div className="space-y-2">
          {erd.nodes.map((n) => (
            <Card key={n.id} className="p-3">
              <div className="mb-1 flex items-center gap-2">
                <Badge tone="brand">{n.id}</Badge>
                <h4 className="text-sm font-semibold">{n.label}</h4>
              </div>
              <ul className="grid grid-cols-2 gap-1 sm:grid-cols-3">
                {(n.fields ?? []).map((f) => (
                  <li key={f} className="rounded bg-[var(--color-surface-2)] px-2 py-1 font-mono text-[11px] text-[var(--color-fg-muted)]">{f}</li>
                ))}
              </ul>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
