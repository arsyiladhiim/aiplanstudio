"use client";
import { use, useEffect, useState } from "react";
import { Card, Badge } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { ArrowLeft, Loader2, ListChecks, Check, Clock, AlertTriangle, CircleDot } from "lucide-react";
import { apiGet } from "@/lib/api";

type Task = {
  id: number;
  task_key: string;
  task_type: string;
  title: string;
  status: "pending" | "running" | "done" | "error";
  checkpoint: string | null;
  phase_key: string;
  version_no: number;
};

type Summary = {
  total: number;
  done: number;
  running: number;
  pending: number;
  error: number;
};

const STATUS_META: Record<Task["status"], { icon: typeof Check; label: string; tone: "success" | "warning" | "danger" | "muted" }> = {
  done: { icon: Check, label: "Done", tone: "success" },
  running: { icon: Clock, label: "Running", tone: "warning" },
  pending: { icon: CircleDot, label: "Pending", tone: "muted" },
  error: { icon: AlertTriangle, label: "Error", tone: "danger" },
};

export default function ProjectTasksPage({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const [tasks, setTasks] = useState<Task[]>([]);
  const [summary, setSummary] = useState<Summary | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [filter, setFilter] = useState<"all" | "done" | "running" | "pending" | "error">("all");

  useEffect(() => {
    let cancelled = false;
    apiGet<{ summary: Summary; tasks: Task[] }>(`/projects/${id}/tasks`)
      .then(res => {
        if (cancelled) return;
        setTasks(res.tasks || []);
        setSummary(res.summary || null);
      })
      .catch(err => {
        if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat tasks");
      })
      .finally(() => {
        if (!cancelled) setLoading(false);
      });
    return () => { cancelled = true; };
  }, [id]);

  const visible = filter === "all" ? tasks : tasks.filter(t => t.status === filter);

  const groupedByVersion = visible.reduce<Record<number, { version_no: number; phases: Record<string, Task[]> }>>((acc, t) => {
    if (!acc[t.version_no]) acc[t.version_no] = { version_no: t.version_no, phases: {} };
    if (!acc[t.version_no].phases[t.phase_key]) acc[t.version_no].phases[t.phase_key] = [];
    acc[t.version_no].phases[t.phase_key].push(t);
    return acc;
  }, {});

  return (
    <>
      <div className="mb-4 flex items-center justify-between gap-2">
        <ButtonLink href={`/projects/${id}`} variant="ghost" size="sm">
          <ArrowLeft size={16} /> Kembali ke Project
        </ButtonLink>
      </div>

      <div className="mb-6 flex flex-col gap-2 sm:flex-row sm:items-end sm:justify-between">
        <div>
          <h1 className="flex items-center gap-2 text-2xl font-bold">
            <ListChecks size={22} /> Tasks Aggregate
          </h1>
          <p className="mt-1 text-sm text-[var(--color-fg-muted)]">
            Semua task lintas versi pada project ini.
          </p>
        </div>
        {summary && (
          <div className="flex flex-wrap gap-2 text-xs">
            <Badge tone="success">Done {summary.done}</Badge>
            <Badge tone="warning">Running {summary.running}</Badge>
            <Badge tone="muted">Pending {summary.pending}</Badge>
            {summary.error > 0 && <Badge tone="danger">Error {summary.error}</Badge>}
            <Badge tone="muted">Total {summary.total}</Badge>
          </div>
        )}
      </div>

      <div className="mb-4 flex flex-wrap gap-2">
        {(["all", "done", "running", "pending", "error"] as const).map((f) => (
          <button
            key={f}
            onClick={() => setFilter(f)}
            data-testid={`filter-${f}`}
            className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-1.5 text-xs transition ${
              filter === f
                ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_12%,transparent)] text-[var(--color-brand)]"
                : "border-[var(--color-border)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            }`}
          >
            {f === "all" ? "Semua" : STATUS_META[f].label}
            {f !== "all" && summary && (
            <span className="ml-1 text-[var(--color-fg-subtle)]">
              {summary[f as Exclude<typeof f, "all">]}
            </span>
          )}
          </button>
        ))}
      </div>

      {loading && (
        <div className="text-center py-12 text-[var(--color-fg-muted)]">
          <Loader2 className="animate-spin inline" /> Memuat tasks...
        </div>
      )}

      {error && (
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {!loading && !error && tasks.length === 0 && (
        <Card className="p-8 text-center text-[var(--color-fg-muted)]">
          <ListChecks size={28} className="mx-auto mb-3 opacity-50" />
          Belum ada tasks. Tasks akan muncul setelah AI agent mulai membangun dan mengirim checkpoint.
        </Card>
      )}

      {!loading && !error && visible.length === 0 && tasks.length > 0 && (
        <Card className="p-6 text-center text-sm text-[var(--color-fg-muted)]">
          Tidak ada task dengan status &ldquo;{filter === "all" ? "Semua" : STATUS_META[filter]?.label ?? filter}&rdquo;.
        </Card>
      )}

      {!loading && !error && visible.length > 0 && (
        <div className="space-y-4">
          {Object.values(groupedByVersion).sort((a, b) => b.version_no - a.version_no).map(v => (
            <Card key={v.version_no} className="p-5">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="font-semibold">v{v.version_no}</h3>
                <span className="text-xs text-[var(--color-fg-muted)]">
                  {Object.values(v.phases).reduce((sum, list) => sum + list.length, 0)} tasks
                </span>
              </div>
              <div className="space-y-4">
                {Object.entries(v.phases).sort(([a], [b]) => a.localeCompare(b)).map(([phaseKey, list]) => (
                  <div key={phaseKey}>
                    <p className="mb-1.5 text-xs font-medium uppercase tracking-wider text-[var(--color-fg-subtle)]">
                      {phaseKey}
                    </p>
                    <div className="space-y-1">
                      {list.map(t => {
                        const meta = STATUS_META[t.status];
                        const Icon = meta.icon;
                        return (
                          <div
                            key={t.id}
                            data-testid={`task-${t.id}`}
                            data-status={t.status}
                            className="flex items-start gap-3 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                          >
                            <Icon size={14} className={`mt-0.5 shrink-0 ${
                              t.status === 'done' ? 'text-[var(--color-success)]' :
                              t.status === 'running' ? 'text-[var(--color-warning)]' :
                              t.status === 'error' ? 'text-[var(--color-danger)]' :
                              'text-[var(--color-fg-subtle)]'
                            }`} />
                            <div className="min-w-0 flex-1">
                              <p className={`truncate ${t.status === 'done' ? 'line-through text-[var(--color-fg-subtle)]' : ''}`}>
                                {t.title}
                              </p>
                              <p className="mt-0.5 text-[10px] text-[var(--color-fg-subtle)]">
                                {t.task_key} · {t.task_type}
                                {t.checkpoint && ` · checkpoint: ${t.checkpoint}`}
                              </p>
                            </div>
                            <Badge tone={meta.tone}>{meta.label}</Badge>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                ))}
              </div>
            </Card>
          ))}
        </div>
      )}
    </>
  );
}
