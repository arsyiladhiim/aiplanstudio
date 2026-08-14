"use client";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, type Activity } from "@/lib/api";
import type { Target } from "@/lib/mock";
import { Wand2, FolderKanban, GitBranch, ArrowRight, Plus, Clock, Loader2, TrendingUp, CalendarDays, Heart, History, RefreshCw } from "lucide-react";
import { formatRelativeTime } from "@/lib/format";

interface DashboardStats {
  total_projects: number;
  total_versions: number;
  active_projects: number;
  favorite_projects: number;
  projects_this_week: number;
  versions_this_week: number;
  recent_projects: Array<{
    id: number;
    title: string;
    target: string;
    idea: string;
    versions_count: number;
    is_favorite?: boolean;
    updated_at: string;
    progress?: number;
    stage_count?: number;
    latest_version_id?: number | null;
  }>;
  recent_activities: Activity[];
}

export default function DashboardPage() {
  const [stats, setStats] = useState<DashboardStats | null>(null);
  const [loading, setLoading] = useState(true);
  const [refreshing, setRefreshing] = useState(false);
  const [error, setError] = useState("");

  function refresh() {
    setRefreshing(true);
    setError("");
    apiGet<DashboardStats>("/dashboard/stats")
      .then(setStats)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat data"))
      .finally(() => setRefreshing(false));
  }

  useEffect(() => {
    let cancelled = false;
    apiGet<DashboardStats>("/dashboard/stats")
      .then((d) => { if (!cancelled) setStats(d); })
      .catch((err) => { if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat data"); })
      .finally(() => { if (!cancelled) setLoading(false); });
    const handler = () => {
      setRefreshing(true);
      setError("");
      apiGet<DashboardStats>("/dashboard/stats")
        .then(setStats)
        .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat data"))
        .finally(() => setRefreshing(false));
    };
    window.addEventListener('profile-updated', handler);
    return () => { cancelled = true; window.removeEventListener('profile-updated', handler); };
  }, []);

  if (loading) {
    return <div className="text-center py-12"><Loader2 className="animate-spin inline" /> Memuat dashboard...</div>;
  }

  if (error) {
    return (
      <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
        {error}
      </div>
    );
  }

  const statCards = [
    { label: "Total Project", value: stats!.total_projects.toString(), icon: FolderKanban },
    { label: "Total Versi", value: stats!.total_versions.toString(), icon: GitBranch },
    { label: "Project Aktif", value: stats!.active_projects.toString(), icon: Wand2 },
    { label: "Favorit", value: stats!.favorite_projects.toString(), icon: Heart },
    { label: "Project Minggu Ini", value: stats!.projects_this_week.toString(), icon: TrendingUp },
    { label: "Versi Minggu Ini", value: stats!.versions_this_week.toString(), icon: CalendarDays },
  ];

  const recentProjects = stats!.recent_projects;
  const recentActivities = stats!.recent_activities;

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle="Ringkasan planning & lanjutkan pekerjaanmu."
        action={<><button onClick={refresh} disabled={refreshing} className="mr-2 inline-flex h-10 w-10 items-center justify-center rounded-xl border border-[var(--color-border)] text-[var(--color-fg-muted)] transition hover:text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]" title="Segarkan"><RefreshCw size={16} /></button><ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink></>}
      />

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
        {statCards.map((s) => (
          <Card key={s.label} className="p-5">
            <div className="flex items-center justify-between">
              <span className="text-sm text-[var(--color-fg-muted)]">{s.label}</span>
              <span className="grid h-9 w-9 place-items-center rounded-lg bg-[color-mix(in_oklab,var(--color-brand)_14%,transparent)] text-[var(--color-brand)]">
                <s.icon size={17} />
              </span>
            </div>
            <div className="mt-3 text-3xl font-bold">{s.value}</div>
          </Card>
        ))}
      </div>

      {/* Continue working */}
      {recentProjects.length > 0 && (
        <>
          <div className="mt-8 flex items-center justify-between">
            <h2 className="text-lg font-semibold">Project Terakhir</h2>
            <ButtonLink href="/projects" variant="ghost" size="sm">Semua project <ArrowRight size={15} /></ButtonLink>
          </div>
          <div className="mt-4 grid gap-4 lg:grid-cols-2">
            {recentProjects.map((p) => (
              <Card key={p.id} className="group p-5 transition hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]">
                <div className="flex items-start justify-between gap-3">
                  <div className="min-w-0">
                    <div className="flex items-center gap-2">
                      <h3 className="truncate font-semibold">{p.title}</h3>
                      <TargetBadge target={p.target as Target} />
                      {p.is_favorite && <Heart size={12} fill="currentColor" className="text-red-400 shrink-0" />}
                    </div>
                    <p className="mt-1 line-clamp-1 text-sm text-[var(--color-fg-muted)]">{p.idea}</p>
                  </div>
                  <ButtonLink href={`/projects/${p.id}`} variant="secondary" size="sm" className="shrink-0">Buka</ButtonLink>
                </div>
                <div className="mt-4 flex items-center gap-4 text-xs text-[var(--color-fg-muted)]">
                  <span className="inline-flex items-center gap-1"><Clock size={12} /> {formatRelativeTime(p.updated_at)}</span>
                  <span className="inline-flex items-center gap-1"><GitBranch size={12} /> {p.versions_count} versi</span>
                </div>
                {typeof p.progress === "number" && typeof p.stage_count === "number" && p.stage_count > 0 && (
                  <div className="mt-3">
                    <div className="flex items-center justify-between text-xs text-[var(--color-fg-muted)]">
                      <span>Pipeline</span>
                      <span>{p.progress}/{p.stage_count} tahap</span>
                    </div>
                    <div className="mt-1 h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div className="h-full rounded-full bg-[var(--color-brand)] transition-all" style={{ width: `${(p.progress / p.stage_count) * 100}%` }} />
                    </div>
                  </div>
                )}
              </Card>
            ))}
          </div>
        </>
      )}

      {recentProjects.length === 0 && (
        <div className="mt-8 text-center py-12">
          <p className="text-[var(--color-fg-muted)] mb-4">Belum ada project. Mulai buat plan pertamamu!</p>
          <ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>
        </div>
      )}

      {/* Recent Activity */}
      {recentActivities.length > 0 && (
        <div className="mt-8">
          <h2 className="mb-4 text-lg font-semibold">Aktivitas Terbaru</h2>
          <Card className="divide-y divide-[var(--color-border)]">
            {recentActivities.map((a) => (
              <div key={a.id} className="flex items-start gap-3 p-4">
                <div className="mt-0.5 grid h-7 w-7 place-items-center rounded-full bg-[var(--color-surface-2)] text-[var(--color-fg-muted)]">
                  <History size={13} />
                </div>
                <div className="flex-1 min-w-0">
                  <p className="text-sm">{a.description}</p>
                  <p className="mt-0.5 text-xs text-[var(--color-fg-subtle)]">
                    {a.user?.name} &middot; {formatRelativeTime(a.created_at)}
                  </p>
                </div>
              </div>
            ))}
          </Card>
        </div>
      )}
    </>
  );
}
