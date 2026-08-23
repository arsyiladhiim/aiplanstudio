"use client";
import { useCallback, useEffect, useState } from "react";
import { apiGet } from "@/lib/api";

export interface StageEvidence {
  stage_key: string;
  task_key?: string | null;
  files_changed?: string[];
  tests_passed: boolean;
  lint_passed: boolean;
  build_passed: boolean;
  migrate_passed: boolean;
  security_passed: boolean;
  perf_passed: boolean;
  evidence_url?: string | null;
  notes?: string | null;
  updated_at?: string;
}

/**
 * CP-46.B — fetch & cache per-version stage evidence.
 * Polling-friendly: parent bisa trigger refresh via re-call.
 */
export function useStageEvidence(versionId: number | null) {
  const [data, setData] = useState<Record<string, StageEvidence>>({});
  const [loading, setLoading] = useState(false);

  const refresh = useCallback(async () => {
    if (!versionId) return;
    setLoading(true);
    try {
      const res = await apiGet<{ data: StageEvidence[] }>(`/versions/${versionId}/evidence`);
      const map: Record<string, StageEvidence> = {};
      for (const row of res.data ?? []) {
        map[row.stage_key] = row;
      }
      setData(map);
    } catch {
      // silent — evidence endpoint opsional.
    } finally {
      setLoading(false);
    }
  }, [versionId]);

  useEffect(() => {
    refresh();
  }, [refresh]);

  return { data, loading, refresh };
}
