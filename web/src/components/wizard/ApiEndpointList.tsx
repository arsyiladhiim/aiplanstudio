import { useMemo, useState } from "react";
import { Card, Badge } from "@/components/ui";
import { ChevronDown, ChevronRight } from "lucide-react";

export interface ApiEndpoint {
  resource?: string;
  method: string;
  path: string;
  description: string;
  auth: boolean;
  request_example?: unknown;
  response_example?: unknown;
}

const METHOD_TONES: Record<string, "brand" | "success" | "warning" | "danger" | "muted"> = {
  GET: "success",
  POST: "brand",
  PUT: "warning",
  PATCH: "warning",
  DELETE: "danger",
};

function inferResource(path: string): string {
  const segments = path.replace(/^\/+/, "").split("/").filter(Boolean);
  if (segments.length === 0) return "root";
  const first = segments[0];
  if (first.startsWith("{") || first === "api") {
    return segments[1] ?? "root";
  }
  return first;
}

export function ApiEndpointList({ items }: { items: ApiEndpoint[] }) {
  const grouped = useMemo(() => {
    const map = new Map<string, ApiEndpoint[]>();
    for (const item of items) {
      const key = item.resource ?? inferResource(item.path);
      if (!map.has(key)) map.set(key, []);
      map.get(key)!.push(item);
    }
    return Array.from(map.entries()).sort(([a], [b]) => a.localeCompare(b));
  }, [items]);

  if (items.length === 0) {
    return <p className="text-sm text-[var(--color-fg-muted)]">Tidak ada endpoint.</p>;
  }

  return (
    <div className="space-y-3">
      {grouped.map(([resource, endpoints]) => (
        <ResourceGroup key={resource} resource={resource} endpoints={endpoints} />
      ))}
    </div>
  );
}

function ResourceGroup({ resource, endpoints }: { resource: string; endpoints: ApiEndpoint[] }) {
  const [open, setOpen] = useState(true);
  return (
    <Card className="overflow-hidden p-0">
      <button
        type="button"
        onClick={() => setOpen((v) => !v)}
        className="flex w-full items-center gap-2 px-4 py-2.5 text-left hover:bg-[var(--color-surface-2)]"
      >
        {open ? <ChevronDown size={14} /> : <ChevronRight size={14} />}
        <span className="text-sm font-semibold capitalize">{resource}</span>
        <Badge tone="muted" className="ml-auto">
          {endpoints.length} endpoint
        </Badge>
      </button>
      {open && (
        <div className="divide-y divide-[var(--color-border)] border-t border-[var(--color-border)]">
          {endpoints.map((ep, i) => (
            <EndpointRow key={`${ep.method}-${ep.path}-${i}`} endpoint={ep} />
          ))}
        </div>
      )}
    </Card>
  );
}

function EndpointRow({ endpoint }: { endpoint: ApiEndpoint }) {
  const [showExample, setShowExample] = useState(false);
  const tone = METHOD_TONES[endpoint.method.toUpperCase()] ?? "muted";
  const hasExample = Boolean(endpoint.request_example) || Boolean(endpoint.response_example);

  return (
    <div className="px-4 py-2.5">
      <div className="flex items-start gap-3">
        <Badge tone={tone} className="shrink-0 font-mono text-[10px]">
          {endpoint.method}
        </Badge>
        <div className="min-w-0 flex-1">
          <code className="block break-all text-xs font-semibold text-[var(--color-fg)]">{endpoint.path}</code>
          {endpoint.description && (
            <p className="mt-1 text-xs leading-relaxed text-[var(--color-fg-muted)]">{endpoint.description}</p>
          )}
          <div className="mt-1 flex flex-wrap items-center gap-1.5">
            {endpoint.auth ? (
              <Badge tone="warning" className="text-[9px]">🔒 Auth</Badge>
            ) : (
              <Badge tone="muted" className="text-[9px]">Public</Badge>
            )}
            {hasExample && (
              <button
                type="button"
                onClick={() => setShowExample((v) => !v)}
                className="text-[10px] text-[var(--color-brand)] hover:underline"
              >
                {showExample ? "Sembunyi" : "Lihat contoh"}
              </button>
            )}
          </div>
        </div>
      </div>
      {showExample && hasExample && (
        <div className="mt-2 space-y-2 pl-12">
          {endpoint.request_example != null && (
            <div>
              <div className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">Request</div>
              <pre className="overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-2 font-mono text-[11px] text-[var(--color-fg)]">
                {JSON.stringify(endpoint.request_example, null, 2)}
              </pre>
            </div>
          )}
          {endpoint.response_example != null && (
            <div>
              <div className="mb-1 text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">Response</div>
              <pre className="overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-2 font-mono text-[11px] text-[var(--color-fg)]">
                {JSON.stringify(endpoint.response_example, null, 2)}
              </pre>
            </div>
          )}
        </div>
      )}
    </div>
  );
}
