"use client";
import Link from "next/link";
import { useRouter, useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, apiDelete, apiPatch, type Project } from "@/lib/api";
import { useDebounce } from "@/lib/useDebounce";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { getStages } from "@/lib/mock";
import { Plus, GitBranch, Clock, Play, Trash2, Search, Heart, Pin, Archive } from "lucide-react";
import { formatRelativeTime } from "@/lib/format";

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
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState("");
  const [searchQuery, setSearchQuery] = useState(searchParams.get("q") || "");
  const [favoriteOnly, setFavoriteOnly] = useState(false);
  const [targetFilter, setTargetFilter] = useState<"all" | "web" | "both">("all");
  const [archivedOnly, setArchivedOnly] = useState(searchParams.get("archived") === "1");
  const [hasMore, setHasMore] = useState(false);
  const [page, setPage] = useState(1);
  const [deleteTarget, setDeleteTarget] = useState<number | null>(null);
  const PAGE_SIZE = 24;
  const debouncedQuery = useDebounce(searchQuery, 300);

  useEffect(() => {
    let cancelled = false;
    (async () => {
      try {
        const params = new URLSearchParams();
        if (debouncedQuery) params.set("q", debouncedQuery);
        if (favoriteOnly) params.set("favorite", "true");
        if (targetFilter !== "all") params.set("target", targetFilter);
        if (archivedOnly) params.set("archived", "1");
        params.set("per_page", String(PAGE_SIZE));
        params.set("page", "1");
        const qs = params.toString();
        const data = await apiGet<{ data: Project[]; current_page?: number; last_page?: number; total?: number }>(`/projects?${qs}`);
        if (!cancelled) {
          setProjects(data.data || []);
          setPage(1);
          setHasMore(data.current_page != null && data.last_page != null ? data.current_page < data.last_page : false);
        }
      } catch (err) {
        if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat projects");
      }
      if (!cancelled) setLoading(false);
    })();
    return () => { cancelled = true; };
  }, [debouncedQuery, favoriteOnly, targetFilter, archivedOnly]);

  async function loadMore() {
    setLoadingMore(true);
    try {
      const params = new URLSearchParams();
      if (debouncedQuery) params.set("q", debouncedQuery);
      if (favoriteOnly) params.set("favorite", "true");
      if (targetFilter !== "all") params.set("target", targetFilter);
      if (archivedOnly) params.set("archived", "1");
      params.set("per_page", String(PAGE_SIZE));
      params.set("page", String(page + 1));
      const data = await apiGet<{ data: Project[]; current_page?: number; last_page?: number }>(`/projects?${params.toString()}`);
      setProjects(prev => [...prev, ...(data.data || [])]);
      setPage(prev => prev + 1);
      if (data.current_page != null && data.last_page != null) {
        setHasMore(data.current_page < data.last_page);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal memuat lebih banyak project");
    } finally {
      setLoadingMore(false);
    }
  }

  async function handleDelete(projectId: number, e: React.MouseEvent) {
    e.preventDefault();
    setDeleteTarget(projectId);
  }

  async function togglePin(projectId: number) {
    const prev = projects.find(p => p.id === projectId);
    if (!prev) return;
    const nextPinned = !prev.is_pinned;
    setProjects(list => list.map(p => p.id === projectId ? { ...p, is_pinned: nextPinned } : p));
    try {
      await apiPatch<{ is_pinned: boolean }>(`/projects/${projectId}/pin`);
    } catch (err) {
      setProjects(list => list.map(p => p.id === projectId ? { ...p, is_pinned: prev.is_pinned } : p));
      setError(err instanceof Error ? err.message : 'Gagal mengubah pin');
    }
  }

  async function confirmDelete() {
    if (deleteTarget === null) return;
    try {
      await apiDelete(`/projects/${deleteTarget}`);
      setProjects(prev => prev.filter(p => p.id !== deleteTarget));
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal menghapus project');
    } finally {
      setDeleteTarget(null);
    }
  }

  return (
    <>
      <PageHeader
        title={archivedOnly ? "Arsip Projects" : "Projects"}
        subtitle={archivedOnly ? "Project yang diarsipkan. Klik untuk membuka." : "Semua plan yang kamu buat, lengkap dengan versi & progress."}
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
        <select
          value={targetFilter}
          onChange={(e) => setTargetFilter(e.target.value as "all" | "web" | "both")}
          className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2.5 text-sm text-[var(--color-fg-muted)]"
        >
          <option value="all">Semua Target</option>
          <option value="web">Web</option>
          <option value="both">Web + Mobile</option>
        </select>
        <button
          onClick={() => setArchivedOnly(!archivedOnly)}
          className={`inline-flex items-center gap-1.5 rounded-xl border px-3 py-2.5 text-sm transition ${
            archivedOnly
              ? "border-amber-400/40 bg-amber-500/10 text-amber-500"
              : "border-[var(--color-border)] text-[var(--color-fg-muted)] hover:text-amber-400"
          }`}
          title="Tampilkan arsip saja"
          data-testid="archive-filter"
        >
          <Archive size={15} fill={archivedOnly ? "currentColor" : "none"} />
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
        <>
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((p) => (
            <Link key={p.id} href={`/projects/${p.id}`} data-testid={`project-${p.id}`}>
              <Card className="group relative h-full p-5 transition hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]">
                <button
                  type="button"
                  aria-label={p.is_pinned ? "Lepas pin" : "Pin project"}
                  data-testid={`pin-toggle-${p.id}`}
                  onClick={(e) => {
                    e.preventDefault();
                    e.stopPropagation();
                    togglePin(p.id);
                  }}
                  className={`absolute right-3 top-3 inline-flex h-7 w-7 items-center justify-center rounded-lg transition ${
                    p.is_pinned
                      ? "bg-amber-500/15 text-amber-500 hover:bg-amber-500/25"
                      : "text-[var(--color-fg-muted)] opacity-0 hover:bg-[var(--color-surface-2)] hover:text-amber-400 group-hover:opacity-100"
                  }`}
                >
                  <Pin size={14} fill={p.is_pinned ? "currentColor" : "none"} />
                </button>
                <div className="flex items-center justify-between">
                  <div className="flex items-center gap-2">
                    <TargetBadge target={p.target} />
                    {p.is_pinned && <Pin size={12} fill="currentColor" className="text-amber-400" />}
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
                    <Clock size={12} /> {formatRelativeTime(p.updated_at)}
                  </span>
                  {p.archived_at ? (
                    <button
                      onClick={async (e) => {
                        e.preventDefault();
                        e.stopPropagation();
                        try {
                          await apiPatch(`/projects/${p.id}/archive`);
                          setProjects(list => list.filter(x => x.id !== p.id));
                        } catch (err) {
                          setError(err instanceof Error ? err.message : 'Gagal membatalkan arsip');
                        }
                      }}
                      className="inline-flex items-center gap-1 text-xs text-amber-500 hover:text-amber-400 transition"
                      title="Batal arsip"
                      data-testid={`unarchive-${p.id}`}
                    >
                      <Archive size={12} /> Batal Arsip
                    </button>
                  ) : (
                    <button
                      onClick={(e) => handleDelete(p.id, e)}
                      className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-red-500 transition"
                      title="Hapus project"
                    >
                      <Trash2 size={12} /> Hapus
                    </button>
                  )}
                </div>
              </Card>
            </Link>
          ))}
        </div>

        {hasMore && (
          <div className="mt-8 text-center">
            <Button variant="secondary" onClick={loadMore} disabled={loadingMore}>
              {loadingMore ? "Memuat..." : "Muat Lebih Banyak"}
            </Button>
          </div>
        )}
        </>
      )}

      <ConfirmDialog
        open={deleteTarget !== null}
        onClose={() => setDeleteTarget(null)}
        onConfirm={confirmDelete}
        title="Hapus Project?"
        message="Yakin ingin menghapus project ini? Semua versi & data akan hilang."
        confirmLabel="Ya, Hapus"
      />
    </>
  );
}
