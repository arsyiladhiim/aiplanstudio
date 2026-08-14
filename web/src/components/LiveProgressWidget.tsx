"use client";
import { useEffect, useState, useCallback, useRef } from "react";
import Link from "next/link";
import { Loader2, Check, Sparkles } from "lucide-react";
import { apiGet, type Project } from "@/lib/api";
import { getStages } from "@/lib/mock";

interface ActiveProject {
  id: number;
  title: string;
  progress: number;
  total: number;
  latest_version_id: number | null;
}

function detectActive(projects: Project[]): ActiveProject[] {
  return projects
    .map((p): ActiveProject | null => {
      const total = getStages(p.target).length;
      const done = p.progress ?? 0;
      if (done < total && p.latest_version_id) {
        return { id: p.id, title: p.title, progress: done, total, latest_version_id: p.latest_version_id };
      }
      return null;
    })
    .filter((p): p is ActiveProject => p !== null);
}

export function LiveProgressWidget() {
  const [active, setActive] = useState<ActiveProject[]>([]);
  const [justDone, setJustDone] = useState<string | null>(null);
  const activeRef = useRef<ActiveProject[]>([]);

  const poll = useCallback(async () => {
    try {
      const res = await apiGet<{ data: Project[] }>("/projects?per_page=20");
      return detectActive(res.data ?? []);
    } catch {
      return null;
    }
  }, []);

  useEffect(() => {
    let cancelled = false;
    const tick = async () => {
      const next = await poll();
      if (cancelled || !next) return;
      const prev = activeRef.current;
      if (prev.length > 0 && next.length === 0 && prev[0]) {
        setJustDone(prev[0].title);
        setTimeout(() => setJustDone(null), 5000);
      }
      activeRef.current = next;
      setActive(next);
    };
    tick();
    const id = setInterval(tick, 10_000);
    return () => { cancelled = true; clearInterval(id); };
  }, [poll]);

  if (active.length === 0 && !justDone) return null;

  const top = active[0];
  const pct = top ? Math.round((top.progress / top.total) * 100) : 100;

  return (
    <div
      className="fixed bottom-4 right-4 z-50 max-w-xs rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-1)]/95 p-3 shadow-lg backdrop-blur"
      data-testid="live-progress-widget"
    >
      {top ? (
        <Link href={`/projects/${top.id}?tab=tracking`} className="flex items-start gap-3 hover:opacity-90">
          <div className="grid h-9 w-9 shrink-0 place-items-center rounded-full bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-brand)]">
            <Loader2 size={16} className="animate-spin" />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <span className="truncate text-sm font-medium">{top.title}</span>
              {active.length > 1 && (
                <span className="shrink-0 rounded-full bg-[var(--color-brand)] px-1.5 text-[10px] font-semibold text-white">
                  +{active.length - 1}
                </span>
              )}
            </div>
            <div className="mt-1 h-1.5 w-full overflow-hidden rounded-full bg-[var(--color-surface-2)]">
              <div className="h-full bg-[var(--color-brand)] transition-all" style={{ width: `${pct}%` }} />
            </div>
            <div className="mt-1 text-xs text-[var(--color-fg-muted)]">
              {top.progress}/{top.total} stage selesai
            </div>
          </div>
        </Link>
      ) : (
        <div className="flex items-center gap-3">
          <div className="grid h-9 w-9 place-items-center rounded-full bg-[color-mix(in_oklab,var(--color-success)_18%,transparent)] text-[var(--color-success)]">
            <Check size={16} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-1 text-sm font-medium">
              <Sparkles size={12} /> Plan selesai!
            </div>
            <div className="truncate text-xs text-[var(--color-fg-muted)]">{justDone}</div>
          </div>
        </div>
      )}
    </div>
  );
}
