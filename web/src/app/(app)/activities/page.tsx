"use client";
import Link from "next/link";
import { useEffect, useMemo, useState } from "react";
import { Card, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/common";
import { apiGet, type Activity } from "@/lib/api";
import { History, User, ArrowRight, ChevronLeft, ChevronRight, ExternalLink, Filter, X } from "lucide-react";
import { formatRelativeTime } from "@/lib/format";

const FILTER_ACTIONS = [
  "user_approved",
  "user_rejected",
  "user_deleted",
  "user_registered",
  "user_login",
  "user_failed_login",
  "user_password_reset",
  "created_project",
  "deleted_project",
  "created_version",
  "deleted_version",
  "regenerate_stage",
  "webhook_received",
];

interface PaginatedResponse {
  data: Activity[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

export default function ActivitiesPage() {
  const [activities, setActivities] = useState<Activity[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [page, setPage] = useState(1);
  const [lastPage, setLastPage] = useState(1);
  // CP-18.F3: filters
  const [actionFilter, setActionFilter] = useState<string>("");
  const [fromDate, setFromDate] = useState<string>("");
  const [toDate, setToDate] = useState<string>("");

  const queryString = useMemo(() => {
    const params = new URLSearchParams();
    params.set("per_page", "20");
    params.set("page", String(page));
    if (actionFilter) params.set("action", actionFilter);
    if (fromDate) params.set("from", fromDate);
    if (toDate) params.set("to", toDate);
    return params.toString();
  }, [page, actionFilter, fromDate, toDate]);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      try {
        const data = await apiGet<PaginatedResponse>(`/activities?${queryString}`);
        if (!cancelled) {
          setActivities(data.data);
          setLastPage(data.last_page);
        }
      } catch (err) {
        if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat aktivitas");
      }
      if (!cancelled) setLoading(false);
    })();
    return () => { cancelled = true; };
  }, [queryString]);

  const actionBadge = (action: string) => {
    const tones: Record<string, "brand" | "success" | "warning" | "danger" | "muted"> = {
      created: "success",
      updated: "brand",
      deleted: "danger",
      generated: "warning",
      completed: "success",
      started: "brand",
    };
    return <Badge tone={tones[action] || "muted"}>{action}</Badge>;
  };

  function resetFilters() {
    setActionFilter("");
    setFromDate("");
    setToDate("");
    setPage(1);
  }

  return (
    <>
      <PageHeader
        title="Aktivitas"
        subtitle="Semua aktivitas di seluruh project."
      />

      {error && (
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)] mb-4">
          {error}
        </div>
      )}

      <Card className="mb-4 p-4">
        <div className="flex flex-wrap items-end gap-3">
          <div className="flex items-center gap-2 text-sm font-medium text-[var(--color-fg-muted)]">
            <Filter size={14} /> Filter
          </div>
          <div className="flex-1 min-w-[180px]">
            <label className="mb-1 block text-xs text-[var(--color-fg-subtle)]">Action</label>
            <select
              value={actionFilter}
              onChange={(e) => { setActionFilter(e.target.value); setPage(1); }}
              data-testid="activity-filter-action"
              className="h-9 w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 text-sm outline-none focus:border-[var(--color-brand)]"
            >
              <option value="">Semua</option>
              {FILTER_ACTIONS.map((a) => (
                <option key={a} value={a}>{a}</option>
              ))}
            </select>
          </div>
          <div>
            <label className="mb-1 block text-xs text-[var(--color-fg-subtle)]">Dari</label>
            <input
              type="date"
              value={fromDate}
              onChange={(e) => { setFromDate(e.target.value); setPage(1); }}
              data-testid="activity-filter-from"
              className="h-9 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 text-sm outline-none focus:border-[var(--color-brand)]"
            />
          </div>
          <div>
            <label className="mb-1 block text-xs text-[var(--color-fg-subtle)]">Sampai</label>
            <input
              type="date"
              value={toDate}
              onChange={(e) => { setToDate(e.target.value); setPage(1); }}
              data-testid="activity-filter-to"
              className="h-9 rounded-lg border border-[var(--color-border)] bg-[var(--color-bg)] px-3 text-sm outline-none focus:border-[var(--color-brand)]"
            />
          </div>
          {(actionFilter || fromDate || toDate) && (
            <Button variant="ghost" size="sm" onClick={resetFilters} data-testid="activity-filter-reset">
              <X size={14} /> Reset
            </Button>
          )}
        </div>
      </Card>

      {loading && activities.length === 0 && (
        <div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat aktivitas...</div>
      )}

      {!loading && !error && activities.length === 0 && (
        <div className="text-center py-12">
          <p className="text-[var(--color-fg-muted)]">Belum ada aktivitas.</p>
        </div>
      )}

      {activities.length > 0 && (
        <Card className="divide-y divide-[var(--color-border)]">
          {activities.map((a) => (
            <div key={a.id} className="flex items-start gap-4 p-4">
              <div className="mt-0.5 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[var(--color-surface-2)] text-[var(--color-fg-muted)]">
                <History size={14} />
              </div>
              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  {actionBadge(a.action)}
                  {a.project && (
                    <Link
                      href={`/projects/${a.project_id}`}
                      className="inline-flex items-center gap-1 text-sm font-medium text-[var(--color-brand)] hover:underline"
                    >
                      {a.project.title}
                      <ExternalLink size={12} />
                    </Link>
                  )}
                </div>
                <p className="mt-1 text-sm text-[var(--color-fg)]">{a.description}</p>
                <div className="mt-1 flex items-center gap-3 text-xs text-[var(--color-fg-subtle)]">
                  <span className="inline-flex items-center gap-1">
                    <User size={11} /> {a.user.name}
                  </span>
                  <span>{formatRelativeTime(a.created_at)}</span>
                </div>
              </div>
              {a.project_id && (
                <Link
                  href={`/projects/${a.project_id}`}
                  className="shrink-0 text-[var(--color-fg-muted)] hover:text-[var(--color-brand)] transition"
                >
                  <ArrowRight size={16} />
                </Link>
              )}
            </div>
          ))}
        </Card>
      )}

      {lastPage > 1 && (
        <div className="mt-6 flex items-center justify-center gap-3">
          <Button
            variant="secondary"
            size="sm"
            disabled={page <= 1}
            onClick={() => setPage(p => Math.max(1, p - 1))}
          >
            <ChevronLeft size={15} /> Sebelumnya
          </Button>
          <span className="text-sm text-[var(--color-fg-muted)]">
            {page} / {lastPage}
          </span>
          <Button
            variant="secondary"
            size="sm"
            disabled={page >= lastPage}
            onClick={() => setPage(p => p + 1)}
          >
            Selanjutnya <ChevronRight size={15} />
          </Button>
        </div>
      )}
    </>
  );
}
