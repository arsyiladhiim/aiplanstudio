"use client";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, type Project } from "@/lib/api";
import { Wand2, FolderKanban, GitBranch, ArrowRight, Plus, Clock, Loader2 } from "lucide-react";

export default function DashboardPage() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    apiGet<Project[]>("/projects")
      .then(setProjects)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat data"))
      .finally(() => setLoading(false));
  }, []);

  function formatDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 60) return `${diffMins} menit lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays === 1) return "kemarin";
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" });
  }

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

  const totalProjects = projects.length;
  const totalVersions = projects.reduce((sum, p) => sum + (p.versions_count || 0), 0);
  const activeProjects = projects.filter(p => (p.versions_count || 0) > 0).length;

  const stats = [
    { label: "Total Project", value: totalProjects.toString(), icon: FolderKanban },
    { label: "Versi Dibuat", value: totalVersions.toString(), icon: GitBranch },
    { label: "Project Aktif", value: activeProjects.toString(), icon: Wand2 },
  ];

  const recentProjects = projects.slice(0, 4);

  return (
    <>
      <PageHeader
        title="Dashboard"
        subtitle="Ringkasan planning & lanjutkan pekerjaanmu."
        action={<ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>}
      />

      {/* Stats */}
      <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
        {stats.map((s) => (
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
      {projects.length > 0 && (
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
                      <TargetBadge target={p.target} />
                    </div>
                    <p className="mt-1 line-clamp-1 text-sm text-[var(--color-fg-muted)]">{p.idea}</p>
                  </div>
                  <ButtonLink href={`/projects/${p.id}`} variant="secondary" size="sm" className="shrink-0">Buka</ButtonLink>
                </div>
                <div className="mt-4 flex items-center gap-4 text-xs text-[var(--color-fg-muted)]">
                  <span className="inline-flex items-center gap-1"><Clock size={12} /> {formatDate(p.updated_at)}</span>
                  <span className="inline-flex items-center gap-1"><GitBranch size={12} /> {p.versions_count || 0} versi</span>
                </div>
              </Card>
            ))}
          </div>
        </>
      )}

      {projects.length === 0 && (
        <div className="mt-8 text-center py-12">
          <p className="text-[var(--color-fg-muted)] mb-4">Belum ada project. Mulai buat plan pertamamu!</p>
          <ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>
        </div>
      )}
    </>
  );
}
