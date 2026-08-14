"use client";
import Link from "next/link";
import { useSearchParams } from "next/navigation";
import { Suspense, useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, apiDelete, apiPatch, type Project } from "@/lib/api";
import { useDebounce } from "@/lib/useDebounce";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Plus, GitBranch, Clock, Search, Heart, Pin, Archive, RotateCcw } from "lucide-react";
import { formatRelativeTime } from "@/lib/format";

export default function ArchivedProjectsPage() {
  return (
    <Suspense fallback={<div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat...</div>}>
      <ArchivedProjectsContent />
    </Suspense>
  );
}

function ArchivedProjectsContent() {
  const searchParams = useSearchParams();
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [loadingMore, setLoadingMore] = useState(false);
  const [error, setError] = useState("");
  const [searchQuery, setSearchQuery] = useState(searchParams.get("q") || "");
  const [targetFilter, setTargetFilter] = useState<"all" | "web" | "both">("all");
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
        params.set("archived", "1");
        if (debouncedQuery) params.set("q", debouncedQuery);
        if (targetFilter !== "all") params.set("target", targetFilter);
        params.set("per_page", String(PAGE_SIZE));
        params.set("page", "1");
        const data = await apiGet<{ data: Project[]; current_page?: number; last_page?: number }>(`/projects?${params.toString()}`);
        if (!cancelled) {
          setProjects(data.data || []);
          setPage(1);
          setHasMore(data.current_page != null && data.last_page != null ? data.current_page < data.last_page : false);
        }
      } catch (err) {
        if (!cancelled) setError(err instanceof Error ? err.message : "Gagal memuat arsip");
      }
      if (!cancelled) setLoading(false);
    })();
    return () => { cancelled = true; };
  }, [debouncedQuery, targetFilter]);

  async function loadMore() {
    setLoadingMore(true);
    try {
      const params = new URLSearchParams();
      params.set("archived", "1");
      if (debouncedQuery) params.set("q", debouncedQuery);
      if (targetFilter !== "all") params.set("target", targetFilter);
      params.set("per_page", String(PAGE_SIZE));
      params.set("page", String(page + 1));
      const data = await apiGet<{ data: Project[]; current_page?: number; last_page?: number }>(`/projects?${params.toString()}`);
      setProjects(prev => [...prev, ...(data.data || [])]);
      setPage(prev => prev + 1);
      if (data.current_page != null && data.last_page != null) {
        setHasMore(data.current_page < data.last_page);
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal memuat lebih banyak");
    } finally {
      setLoadingMore(false);
    }
  }

  async function unarchive(projectId: number) {
    try {
      await apiPatch(`/projects/${projectId}/archive`);
      setProjects(list => list.filter(p => p.id !== projectId));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membatalkan arsip");
    }
  }

  async function handleDelete(projectId: number, e: React.MouseEvent) {
    e.preventDefault();
    setDeleteTarget(projectId);
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
        title="Arsip Projects"
        subtitle="Project yang diarsipkan. Klik kartu untuk membuka atau batal arsip."
        action={<ButtonLink href="/projects"><RotateCcw size={15} /> Kembali ke Projects</ButtonLink>}
      />

      <div className="mb-4 flex items-center gap-3">
        <div className="relative flex-1">
          <Search size={16} className="pointer-events-none absolute left-3 top-1/2 -translate-y-1/2 text-[var(--color-fg-muted)]" />
          <input
            type="text" value={searchQuery}
            onChange={(e) => setSearchQuery(e.target.value)}
            placeholder="Cari arsip..."
            className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] py-2.5 pl-10 pr-4 text-sm"
          />
        </div>
        <select
          value={targetFilter}
          onChange={(e) => setTargetFilter(e.target.value as "all" | "web" | "both")}
          className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2.5 text-sm text-[var(--color-fg-muted)]"
        >
          <option value="all">Semua Target</option>
          <option value="web">Web</option>
          <option value="both">Web + Mobile</option>
        </select>
      </div>

      {loading && <div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat arsip...</div>}

      {error && (
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {!loading && !error && projects.length === 0 && (
        <div className="text-center py-12">
          <Archive size={32} className="mx-auto mb-3 text-[var(--color-fg-subtle)]" />
          <p className="text-[var(--color-fg-muted)]">Belum ada project diarsipkan.</p>
          <ButtonLink href="/projects" className="mt-4" variant="secondary">
            <Plus size={15} /> Buat Plan Baru
          </ButtonLink>
        </div>
      )}

      {!loading && !error && projects.length > 0 && (
        <>
          <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
            {projects.map((p) => (
              <Card key={p.id} className="group relative h-full p-5 opacity-90 transition hover:-translate-y-0.5 hover:opacity-100 hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]" data-testid={`archived-${p.id}`}>
                <button
                  type="button"
                  onClick={() => unarchive(p.id)}
                  className="absolute right-3 top-3 inline-flex h-7 w-7 items-center justify-center rounded-lg bg-amber-500/15 text-amber-500 transition hover:bg-amber-500/25"
                  title="Batal arsip"
                  data-testid={`unarchive-${p.id}`}
                  aria-label="Batal arsip"
                >
                  <RotateCcw size={14} />
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
                <Link href={`/projects/${p.id}`} className="block">
                  <h3 className="mt-3 font-semibold hover:text-[var(--color-brand)] transition">{p.title}</h3>
                </Link>
                <p className="mt-1 line-clamp-2 text-sm text-[var(--color-fg-muted)]">{p.idea}</p>

                <div className="mt-3 flex items-center justify-between text-xs text-[var(--color-fg-muted)]">
                  <span className="inline-flex items-center gap-1">
                    <Clock size={12} /> {formatRelativeTime(p.archived_at ?? p.updated_at)}
                  </span>
                  <span>
                    {p.archived_at ? `Arsip ${formatRelativeTime(p.archived_at)}` : ""}
                  </span>
                </div>

                <div className="mt-4 flex items-center justify-between">
                  <Link href={`/projects/${p.id}`} className="inline-flex items-center gap-1 text-xs text-[var(--color-brand)] hover:underline">
                    Buka Detail
                  </Link>
                  <button
                    onClick={(e) => handleDelete(p.id, e)}
                    className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-red-500 transition"
                    title="Hapus permanen"
                  >
                    Hapus
                  </button>
                </div>
              </Card>
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
        message="Yakin ingin menghapus project ini secara permanen? Semua versi & data akan hilang."
        confirmLabel="Ya, Hapus"
      />
    </>
  );
}
