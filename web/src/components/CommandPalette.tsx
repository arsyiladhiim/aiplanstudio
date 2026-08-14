"use client";
import { useEffect, useState, useCallback } from "react";
import { useRouter } from "next/navigation";
import { Modal } from "@/components/ui/Modal";
import { apiGet } from "@/lib/api";
import { Search, Pin, Heart, FolderKanban } from "lucide-react";

type ProjectHit = { id: number; title: string; target: string; is_pinned?: boolean; is_favorite?: boolean };
type VersionHit = { id: number; project_id: number; version_no: number; pertanyaan?: string; project?: { id: number; title: string; target: string } };

export function CommandPalette() {
  const router = useRouter();
  const [open, setOpen] = useState(false);
  const [q, setQ] = useState("");
  const [results, setResults] = useState<{ projects: ProjectHit[]; versions: VersionHit[] }>({ projects: [], versions: [] });
  const [loading, setLoading] = useState(false);
  const [highlight, setHighlight] = useState(0);

  const handleKey = useCallback((e: KeyboardEvent) => {
    const mod = e.ctrlKey || e.metaKey;
    if (mod && e.key.toLowerCase() === "k") {
      e.preventDefault();
      setOpen((v) => !v);
      setHighlight(0);
    }
  }, []);

  useEffect(() => {
    document.addEventListener("keydown", handleKey);
    return () => document.removeEventListener("keydown", handleKey);
  }, [handleKey]);

  // P4: ArrowUp/Down + Enter navigation dalam result list.
  useEffect(() => {
    if (!open) return;
    const onKey = (e: KeyboardEvent) => {
      const total = results.projects.length + results.versions.length;
      if (total === 0) return;
      if (e.key === "ArrowDown") {
        e.preventDefault();
        setHighlight(h => (h + 1) % total);
      } else if (e.key === "ArrowUp") {
        e.preventDefault();
        setHighlight(h => (h - 1 + total) % total);
      } else if (e.key === "Enter") {
        e.preventDefault();
        const idx = highlight;
        if (idx < results.projects.length) {
          go(`/projects/${results.projects[idx].id}`);
        } else {
          const v = results.versions[idx - results.projects.length];
          if (v) go(`/projects/${v.project_id}`);
        }
      }
    };
    window.addEventListener("keydown", onKey);
    return () => window.removeEventListener("keydown", onKey);
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [open, results, highlight]);

  useEffect(() => {
    setHighlight(0);
  }, [q]);

  const go = (href: string) => {
    setOpen(false);
    setQ("");
    router.push(href);
  };

  useEffect(() => {
    if (!open || q.length < 2) {
      return;
    }
    let cancelled = false;
    const t = setTimeout(async () => {
      if (cancelled) return;
      setLoading(true);
      try {
        const res = await apiGet<{ projects: ProjectHit[]; versions: VersionHit[] }>(`/projects/search?q=${encodeURIComponent(q)}`);
        if (!cancelled) setResults(res);
      } catch {
        if (!cancelled) setResults({ projects: [], versions: [] });
      } finally {
        if (!cancelled) setLoading(false);
      }
    }, 200);
    return () => { cancelled = true; clearTimeout(t); };
  }, [q, open]);

  return (
    <Modal open={open} onClose={() => setOpen(false)} title="Pencarian Cepat" size="md" closeOnBackdrop>
      <div className="space-y-3">
        <div className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] px-3 py-2">
          <Search size={16} className="text-[var(--color-fg-muted)]" />
          <input
            autoFocus
            value={q}
            onChange={(e) => setQ(e.target.value)}
            placeholder="Cari project, ide, stack, atau versi..."
            className="flex-1 bg-transparent outline-none placeholder:text-[var(--color-fg-muted)]"
            data-testid="command-palette-input"
          />
          <kbd className="rounded bg-[var(--color-surface-2)] px-2 py-0.5 text-xs">Ctrl+K</kbd>
        </div>

        {loading && <p className="text-xs text-[var(--color-fg-muted)]">Mencari…</p>}

        {!loading && q.length >= 2 && results.projects.length === 0 && results.versions.length === 0 && (
          <p className="text-sm text-[var(--color-fg-muted)]">Tidak ada hasil.</p>
        )}

        {results.projects.length > 0 && (
          <div>
            <p className="mb-2 text-xs uppercase tracking-wide text-[var(--color-fg-subtle)]">Project</p>
            <ul className="space-y-1">
              {results.projects.map((p, i) => (
                <li key={`p-${p.id}`}>
                  <button
                    onClick={() => go(`/projects/${p.id}`)}
                    onMouseEnter={() => setHighlight(i)}
                    className={`flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-[var(--color-surface-2)] ${highlight === i ? "bg-[var(--color-surface-2)]" : ""}`}
                  >
                    <FolderKanban size={14} />
                    <span className="flex-1">{p.title}</span>
                    {p.is_pinned && <Pin size={12} className="text-amber-400" />}
                    {p.is_favorite && <Heart size={12} className="text-red-400" />}
                  </button>
                </li>
              ))}
            </ul>
          </div>
        )}

        {results.versions.length > 0 && (
          <div>
            <p className="mb-2 text-xs uppercase tracking-wide text-[var(--color-fg-subtle)]">Versi</p>
            <ul className="space-y-1">
              {results.versions.map((v, i) => {
                const idx = results.projects.length + i;
                return (
                  <li key={`v-${v.id}`}>
                    <button
                      onClick={() => go(`/projects/${v.project_id}`)}
                      onMouseEnter={() => setHighlight(idx)}
                      className={`flex w-full items-center gap-2 rounded px-2 py-1.5 text-left text-sm hover:bg-[var(--color-surface-2)] ${highlight === idx ? "bg-[var(--color-surface-2)]" : ""}`}
                    >
                      <span className="flex-1 truncate">{v.project?.title} · v{v.version_no}</span>
                      <span className="text-xs text-[var(--color-fg-subtle)]">→</span>
                    </button>
                  </li>
                );
              })}
            </ul>
          </div>
        )}
      </div>
    </Modal>
  );
}
