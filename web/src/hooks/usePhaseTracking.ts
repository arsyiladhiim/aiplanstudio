"use client";
import { useEffect, useState } from "react";
import { apiGet, createPhaseProgressStream } from "@/lib/api";

export interface ProgressItem {
  phase_key: string;
  task_key?: string | null;
  task_type?: string | null;
  title?: string | null;
  done?: boolean;
  status?: string;
  output?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  checkpoint?: string | null;
}

export interface PhaseTrackingApi {
  progress: ProgressItem[];
  byPhase: Record<string, ProgressItem>;
  loading: boolean;
  refresh: () => Promise<void>;
}

/**
 * CP-46.D step 3 — phase tracking via SSE (subscribe + initial fetch).
 */
export function usePhaseTracking(versionId: number | null): PhaseTrackingApi {
  const [progress, setProgress] = useState<ProgressItem[]>([]);
  const [loading, setLoading] = useState(false);

  const refresh = async () => {
    if (!versionId) return;
    setLoading(true);
    try {
      const v = await apiGet<{ phase_progress?: ProgressItem[] }>(
        `/versions/${versionId}`
      );
      if (v.phase_progress) setProgress(v.phase_progress);
    } catch {
      // silent — phase tracking is best-effort
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    if (!versionId) return;
    refresh();

    const pp = createPhaseProgressStream(
      `/versions/${versionId}/phase-progress/stream`,
      (event, data) => {
        if (event === "phase_progress") {
          const d = data as ProgressItem;
          setProgress((prev) => {
            const idx = prev.findIndex((p) => p.phase_key === d.phase_key);
            if (idx >= 0) {
              const next = [...prev];
              next[idx] = { ...next[idx], ...d };
              return next;
            }
            return [...prev, d];
          });
        }
      }
    );
    return () => pp.abort();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [versionId]);

  const byPhase: Record<string, ProgressItem> = Object.fromEntries(
    progress.map((p) => [p.phase_key, p])
  );

  return { progress, byPhase, loading, refresh };
}
