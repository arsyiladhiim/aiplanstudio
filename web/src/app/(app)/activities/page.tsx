"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Card, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { PageHeader } from "@/components/common";
import { apiGet, type Activity } from "@/lib/api";
import { History, User, ArrowRight, ChevronLeft, ChevronRight, ExternalLink } from "lucide-react";
import { formatRelativeTime } from "@/lib/format";

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

  useEffect(() => {
    let cancelled = false;
    (async () => {
      setLoading(true);
      try {
        const data = await apiGet<PaginatedResponse>(`/activities?per_page=20&page=${page}`);
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
  }, [page]);

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
