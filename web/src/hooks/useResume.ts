"use client"
import { useCallback, useEffect, useState } from "react"
import { apiGet } from "@/lib/api"
import type { PhaseItem, StageKey, StageState, Target } from "@/lib/mock"
import { getStages } from "@/lib/mock"

export interface VersionLite {
  id: number
  project?: {
    id: number
    title?: string
    idea?: string
    target?: Target
  } | null
  answers?: Record<string, unknown> | null
  mobile_answers?: Record<string, unknown> | null
  stage_status?: Record<string, StageState> | null
  skip_reasons?: Record<string, string> | null
  phase_progress?: Array<{
    phase_key: string
    done?: boolean
    status?: StageState
  }> | null
  // artifact columns used by resume:
  pertanyaan?: string | null
  pertanyaan_mobile?: string | null
  analysis?: string | null
  prd?: string | null
  architecture?: string | null
  erd?: unknown
  api_contract?: unknown
  phases?: unknown
  standards?: string | null
  master_prompt?: string | null
  mobile_phases?: unknown
  mobile_standards?: string | null
  mobile_master_prompt?: string | null
  env_config?: string | null
  security?: string | null
  deployment?: string | null
  observability?: string | null
  design_system?: string | null
  design_system_mobile?: string | null
  app_spec_web?: unknown
  app_spec_mobile?: unknown
  agents?: string | null
}

export interface ResumeResult {
  projectId: number | null
  versionId: number
  title: string
  idea: string
  target: Target
  answers: Record<string, unknown>
  liteMode: boolean
  started: boolean
  currentStageIdx: number
  status: Record<StageKey, StageState>
  artifacts: Record<StageKey, string>
  phaseProg: NonNullable<VersionLite["phase_progress"]>
  resumeInfo: { stage: string; remaining: number; total: number } | null
  resumeError: string | null
}

/** CP-46.D — extract resume flow dari /new (god component decomposition). */
export function useResume(
  resumeVersionId: number | null,
  isResume: boolean,
  alreadyStarted: boolean
) {
  const [loading, setLoading] = useState(false)
  const [error, setError] = useState<string | null>(null)
  const [result, setResult] = useState<ResumeResult | null>(null)

  const reset = useCallback(() => {
    setResult(null)
    setError(null)
  }, [])

  useEffect(() => {
    if (!isResume || !resumeVersionId || alreadyStarted) return

    setLoading(true)
    setError(null)

    apiGet<VersionLite>(`/versions/${resumeVersionId}`)
      .then((v) => {
        const projectTarget: Target = (v.project?.target ?? "web") as Target
        const resumeStages = getStages(projectTarget)

        let firstIdx = resumeStages.findIndex(
          (s) =>
            (v.stage_status as Record<string, StageState> | undefined)?.[
              s.key
            ] !== "done"
        )
        let idx = firstIdx >= 0 ? firstIdx : resumeStages.length - 1

        const loadedStatus: Record<StageKey, StageState> = Object.fromEntries(
          resumeStages.map((s) => [
            s.key,
            ((v.stage_status as Record<string, StageState> | undefined)?.[
              s.key
            ] || "pending") as StageState,
          ])
        ) as Record<StageKey, StageState>
        resumeStages.forEach((s) => {
          if (
            loadedStatus[s.key] === "error" ||
            loadedStatus[s.key] === "running"
          ) {
            loadedStatus[s.key] = "pending"
          }
        })

        const colMap: Record<string, keyof VersionLite> = {
          pertanyaan: "pertanyaan",
          pertanyaan_mobile: "pertanyaan_mobile",
          analisa: "analysis",
          prd: "prd",
          architecture: "architecture",
          erd: "erd",
          api_contract: "api_contract",
          phases_web: "phases",
          standards_web: "standards",
          master_web: "master_prompt",
          phases_mobile: "mobile_phases",
          standards_mobile: "mobile_standards",
          master_mobile: "mobile_master_prompt",
          env_config: "env_config",
          security: "security",
          deployment: "deployment",
          observability: "observability",
          design_system: "design_system",
          design_system_mobile: "design_system_mobile",
          app_spec_web: "app_spec_web",
          app_spec_mobile: "app_spec_mobile",
          agents: "agents",
        }
        const loadedArtifacts: Record<string, string> = {}
        resumeStages.forEach((s) => {
          const col = colMap[s.key]
          if (!col) return
          const val = v[col]
          if (val) {
            loadedArtifacts[s.key] =
              typeof val === "object" ? JSON.stringify(val) : String(val)
          }
        })

        const liteReasons = Object.values(v.skip_reasons ?? {}).some((r) =>
          (r || "").includes("Lite plan")
        )

        const prog = v.phase_progress ?? []
        const phasesRaw = String(
          (loadedArtifacts.phases_web as string | undefined) || "[]"
        )
        let resumeWebPhases: PhaseItem[] = []
        try {
          const parsed = JSON.parse(phasesRaw)
          resumeWebPhases = Array.isArray(parsed) ? (parsed as PhaseItem[]) : []
        } catch {
          resumeWebPhases = []
        }
        const webKeySet = new Set(resumeWebPhases.map((ph) => ph.key ?? ""))
        const webDoneCount =
          webKeySet.size > 0
            ? prog.filter((pp) => webKeySet.has(pp.phase_key) && pp.done).length
            : 0
        const resumeWebTrackingDone =
          webKeySet.size === 0 || webDoneCount >= webKeySet.size

        const masterWebIdx = resumeStages.findIndex(
          (s) => s.key === "master_web"
        )
        let resumeError: string | null = null
        if (!resumeWebTrackingDone && masterWebIdx >= 0 && idx > masterWebIdx) {
          idx = masterWebIdx
          firstIdx = -1
          loadedStatus.master_web = "running"
          resumeError =
            "Tracking fase web belum selesai. Lanjutkan hanya setelah kamu yakin web sudah jadi."
        }

        setResult({
          projectId: v.project?.id ?? null,
          versionId: v.id,
          title: v.project?.title ?? "",
          idea: v.project?.idea ?? "",
          target: projectTarget,
          answers: (v.answers ?? {}) as Record<string, unknown>,
          liteMode: liteReasons,
          started: true,
          currentStageIdx: idx,
          status: loadedStatus,
          artifacts: loadedArtifacts as Record<StageKey, string>,
          phaseProg: prog as NonNullable<VersionLite["phase_progress"]>,
          resumeInfo:
            firstIdx >= 0
              ? {
                  stage: resumeStages[firstIdx].key,
                  remaining: resumeStages.length - firstIdx,
                  total: resumeStages.length,
                }
              : null,
          resumeError,
        })
      })
      .catch((err) =>
        setError(
          err instanceof Error ? err.message : "Gagal memuat data project"
        )
      )
      .finally(() => setLoading(false))
  }, [isResume, resumeVersionId, alreadyStarted])

  return { loading, error, result, reset }
}
