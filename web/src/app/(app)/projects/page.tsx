"use client";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, apiDelete, type Project } from "@/lib/api";
import { getStages } from "@/lib/mock";
import { Plus, GitBranch, Clock, Play, Trash2, Search, Heart } from "lucide-react";

export default function ProjectsPage() {
  return (
    <Suspense fallback={<div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat...</div>}>
      <ProjectsContent />
    </Suspense>
  );
}

function ProjectsContent() {
  const router = useRouter();
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [searchQuery, setSearchQuery] = useState(searchParams.get("q") || "");
  const [favoriteOnly, setFavoriteOnly] = useState(false);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const params = new URLSearchParams();
        if (searchQuery) params.set("q", searchQuery);
        if (favoriteOnly) params.set("favorite", "true");
        const qs = params.toString();
        const data = await apiGet<{ data: Project[] }>(`/projects${qs ? `?${qs}` : ""}`);
        if (!cancelled) setProjects(data.data || data);
      } catch (err) {
        if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat projects");
      }
      if (!cancelled) setLoading(false);
    })();
    return () => { cancelled = true; };
  }, [searchQuery, favoriteOnly]);

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

  async function handleDelete(projectId: number, e: React.MouseEvent) {
    e.preventDefault();
    if (!window.confirm("Yakin ingin menghapus project ini? Semua versi & data akan hilang.")) return;
    try {
      await apiDelete(`/projects/${projectId}`);
      setProjects(prev => prev.filter(p => p.id !== projectId));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal menghapus project');
    }
  }

  return (
    <>
      <PageHeader
        title="Projects"
        subtitle="Semua plan yang kamu buat, lengkap dengan versi & progress."
        action={<ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>}
      />

      <div className="mb-4 flex items-center gap-3">
        <div className="relative flex-1">
          <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-fg-muted)]" />
          <input
            type="text" value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari project..."
            className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] py-2.5 pl-10 pr-4 text-sm"
          />
        </div>
        <button
          onClick={() => setFavoriteOnly(!favoriteOnly)}
          className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm transition ${
            favoriteOnly
              ? "border-red-400/40 bg-red-500/10 text-red-500"
              : "border-[var(--color-border)] text-[var(--color-fg-muted)] hover:text-red-400"
          }`}
          title="Tampilkan favorit saja"
        >
          <Heart size={15} fill={favoriteOnly ? "currentColor" : "none"} />
        </button>
      </div>

      {loading && <div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat projects...</div>}

      {error && (
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {!loading && !error && projects.length === 0 && (
        <div className="text-center py-12">
          <p className="text-[var(--color-fg-muted)] mb-4">Belum ada project. Mulai buat plan pertamamu!</p>
          <ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>
        </div>
      )}

      {!loading && !error && projects.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((p) => (
            <Link key={p.id} href={`/projects/${p.id}`} data-testid={`project-${p.id}`}>
              <Card className="group h-full p-5 transition hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]">
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <TargetBadge target={p.target} />
                    {p.is_favorite && <Heart size={12} fill="currentColor" className="text-red-400" />}
                  </div>
                  <span className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-subtle)]">
                    <GitBranch size={12} /> {p.versions_count || 0} versi
                  </span>
                </div>
                <h3 className="mt-3 font-semibold">{p.title}</h3>
                <p className="mt-1 line-clamp-2 text-sm text-[var(--color-fg-muted)]">{p.idea}</p>

                {p.stage_status && (
                  <div className="mt-3 flex items-center gap-2">
                    <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div
                        className="h-full rounded-full bg-[var(--color-brand)] transition-all"
                        style={{ width: `${((p.progress ?? 0) / getStages(p.target).length) * 100}%` }}
                      />
                    </div>
                    <span className="shrink-0 text-xs text-[var(--color-fg-muted)]">
                      {p.progress ?? 0}/{getStages(p.target).length}
                    </span>
                    {(p.progress ?? 0) < getStages(p.target).length && (
                      <Button
                        size="sm"
                        variant="secondary"
                        onClick={(e) => {
                          e.preventDefault();
                          router.push(`/new?resume=1&version=${p.latest_version_id}`);
                        }}
                        className="shrink-0"
                      >
                        <Play size={12} /> Lanjutkan
                      </Button>
                    )}
                  </div>
                )}

                <div className="mt-4 flex items-center justify-between">
                  <span className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)]">
                    <Clock size={12} /> {formatDate(p.updated_at)}
                  </span>
                  <button
                    onClick={(e) => handleDelete(p.id, e)}
                    className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-red-500 transition"
                    title="Hapus project"
                  >
                    <Trash2 size={12} /> Hapus
                  </button>
                </div>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
