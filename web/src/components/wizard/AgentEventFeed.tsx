"use client";
import { useEffect, useState } from "react";
import { apiGet } from "@/lib/api";

interface AgentEventRow {
  id: number;
  run_id: string;
  event_id: string;
  event: string;
  phase_key?: string | null;
  task_key?: string | null;
  status?: string | null;
  created_at: string;
}

/** CP-44 CP-07: feed telemetry coding agent (Agent Event Protocol v1). */
export function AgentEventFeed({ versionId }: { versionId: number }) {
  const [events, setEvents] = useState<AgentEventRow[] | null>(null);
  const [error, setError] = useState("");

  useEffect(() => {
    let alive = true;
    apiGet<{ data: AgentEventRow[] }>(`/versions/${versionId}/agent-events`)
      .then((d) => alive && setEvents(d.data))
      .catch((e) => alive && setError(e instanceof Error ? e.message : "Gagal memuat agent events"));
    return () => {
      alive = false;
    };
  }, [versionId]);

  if (error) return <p className="text-xs text-[var(--color-danger)]">{error}</p>;
  if (!events) return <p className="text-xs text-[var(--color-fg-subtle)]">Memuat agent events...</p>;
  if (events.length === 0)
    return (
      <p className="text-xs text-[var(--color-fg-subtle)]">
        Belum ada agent event. Coding agent bisa mengirim telemetry via <code>POST /api/agent/events</code>.
      </p>
    );

  return (
    <div className="space-y-1">
      {events.map((e) => (
        <div key={e.id} className="flex items-center gap-2 rounded-md border border-[var(--color-border)] px-2.5 py-1.5 text-xs">
          <span className="font-mono font-medium">{e.event}</span>
          {e.phase_key && <span className="text-[var(--color-fg-subtle)]">· {e.phase_key}</span>}
          {e.task_key && <span className="text-[var(--color-fg-subtle)]">· {e.task_key}</span>}
          {e.status && <span className="ml-auto text-[var(--color-fg-subtle)]">{e.status}</span>}
          <span className="ml-auto text-[var(--color-fg-subtle)]">{new Date(e.created_at).toLocaleTimeString()}</span>
        </div>
      ))}
    </div>
  );
}
