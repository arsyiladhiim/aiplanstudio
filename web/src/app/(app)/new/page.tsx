"use client"
import { useState, useRef, useCallback, useEffect, useMemo, use } from "react"
import { useRouter } from "next/navigation"
import { useResume } from "@/hooks/useResume"
import { usePipelineStream } from "@/hooks/usePipelineStream"
import { Card, Badge, Textarea, Label, Markdown, Modal } from "@/components/ui"
import { ButtonLink } from "@/components/ui/Button"
import { Button } from "@/components/ui/Button"
import dynamic from "next/dynamic"
const ErdDiagramDynamic = dynamic(
  () =>
    import("@/components/wizard/ErdDiagram").then((m) => ({
      default: m.ErdDiagram,
    })),
  {
    ssr: false,
    loading: () => (
      <div className="h-[460px] animate-pulse rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-1)]" />
    ),
  }
)
import type { ApiContractItem } from "@/components/wizard/ApiContractTable"
import { ApiContractTable } from "@/components/wizard/ApiContractTable"
import type { PhaseItem } from "@/components/wizard/PhaseBreakdownCard"
import { AnalysisView } from "@/components/wizard/AnalysisView"
import { PrdView } from "@/components/wizard/PrdView"
import { ArchitectureView } from "@/components/wizard/ArchitectureView"
import { StandardsView } from "@/components/wizard/StandardsView"
import { PhasesView } from "@/components/wizard/PhasesView"
import { AgentsView } from "@/components/wizard/AgentsView"
import { DesignSystemView } from "@/components/wizard/DesignSystemView"
import { DesignSystemMobileView } from "@/components/wizard/DesignSystemMobileView"
import { AppSpecWebView } from "@/components/wizard/AppSpecWebView"
import { AppSpecMobileView } from "@/components/wizard/AppSpecMobileView"
import { ErdTabs } from "@/components/wizard/ErdTabs"
import {
  MasterPromptViewer,
  hasMasterPromptArtifact,
} from "@/components/wizard/MasterPromptViewer"
// CP-44 CP-05: tipe lokal (TrackingPhases dihapus); bentuk sempit untuk phase-progress stream.
interface ProgressItem {
  phase_key: string
  done: boolean
  status?: "pending" | "running" | "done" | "error"
  output?: string | null
  started_at?: string | null
  finished_at?: string | null
}
import { TrackingPanel } from "@/components/wizard/TrackingPanel"
import { McqForm } from "@/components/wizard/McqForm"
import { StreamingMarkdown } from "@/components/wizard/StreamingMarkdown"
import { StageThroughputBar } from "@/components/wizard/StageThroughputBar"
import { BuildWall } from "@/components/wizard/BuildWall"
import {
  getStages,
  ARTIFACT_COL_MAP,
  type StageKey,
  type StageState,
  type Target,
} from "@/lib/mock"
import {
  apiPost,
  apiGet,
  apiPatch,
  apiDelete,
  createPhaseProgressStream,
  WEBHOOK_URL,
  type Project,
  type Template,
  type Version,
  type McqData,
  type McqAnswer,
} from "@/lib/api"
import { copyToClipboard } from "@/lib/clipboard"
import { chime } from "@/lib/chime"
import {
  Wand2,
  Globe,
  Layers,
  Loader2,
  Check,
  Copy,
  ArrowRight,
  RotateCcw,
  CircleDot,
  Sparkles,
  AlertCircle,
  Pencil,
  Play,
} from "lucide-react"
import { ErrorBoundary } from "@/components/ErrorBoundary"

const Confetti = dynamic(
  () => import("@/components/Confetti").then((m) => ({ default: m.Confetti })),
  { ssr: false }
)

const TARGETS: { key: Target; label: string; icon: typeof Globe }[] = [
  { key: "web", label: "Web App", icon: Globe },
  { key: "both", label: "Web + Mobile", icon: Layers },
]

interface ErdParsed {
  nodes?: Array<{ id: string; label: string; fields: string[] }>
  edges?: Array<{ from: string; to: string; relation: string }>
  api_contract?: ApiContractItem[]
  phases?: PhaseItem[]
  master?: string
  standards?: boolean
  agents?: boolean
}

export default function NewPlanPage({
  searchParams,
}: {
  searchParams: Promise<{
    resume?: string
    version?: string
    template?: string
    idea_title?: string
    idea_text?: string
  }>
}) {
  const router = useRouter()
  const params = use(searchParams)
  const isResume = params.resume === "1"
  const resumeVersionId = params.version ? Number(params.version) : null
  const templateParam = params.template ?? ""
  const prefillTitle = params.idea_title ?? ""
  const prefillIdea = params.idea_text ?? ""
  const [started, setStarted] = useState(false)
  const [idea, setIdea] = useState(prefillIdea)
  const [title, setTitle] = useState(prefillTitle)
  const [target, setTarget] = useState<Target>("web")
  const [liteMode, setLiteMode] = useState(false)
  const [resumeInfo, setResumeInfo] = useState<{
    stage: string
    remaining: number
    total: number
  } | null>(null)
  const [failedStage, setFailedStage] = useState<StageKey | null>(null)
  const [answers, setAnswers] = useState<Record<string, string>>({})
  const [resumedMobileAnswers, setResumedMobileAnswers] = useState<
    Record<string, string>
  >({})
  const [mcqAnswers, setMcqAnswers] = useState<Record<string, McqAnswer>>({})
  const [mobileMcqAnswers, setMobileMcqAnswers] = useState<
    Record<string, McqAnswer>
  >({})
  const [templates, setTemplates] = useState<Template[]>([])
  const [selectedTemplate, setSelectedTemplate] = useState<string>("")
  const [current, setCurrent] = useState(0)

  function initStatus(t: Target): Record<StageKey, StageState> {
    return Object.fromEntries(
      getStages(t).map((s) => [s.key, "pending"])
    ) as Record<StageKey, StageState>
  }

  const [status, setStatus] = useState<Record<StageKey, StageState>>(() =>
    initStatus(target)
  )

  const setTargetAndReset = useCallback((newTarget: Target) => {
    setTarget(newTarget)
    setStatus(initStatus(newTarget))
  }, [])

  const stages = useMemo(() => getStages(target), [target])
  const allDone = stages.every((s) => status[s.key] === "done")

  // P8: fire Confetti only on transition to allDone (one-shot).
  const confettiFiredRef = useRef(false)
  const [showConfetti, setShowConfetti] = useState(false)
  // M2: auto-advance antar tahap non-MCQ saat toggle aktif (target=both 14 stage).
  const [autoAdvance, setAutoAdvance] = useState(false)
  const autoAdvanceRef = useRef(false)
  useEffect(() => {
    autoAdvanceRef.current = autoAdvance
  }, [autoAdvance])
  useEffect(() => {
    if (allDone && !confettiFiredRef.current) {
      confettiFiredRef.current = true
      setShowConfetti(true)
    } else if (!allDone && confettiFiredRef.current) {
      confettiFiredRef.current = false
      setShowConfetti(false)
    }
  }, [allDone])
  const activeKey = stages[current]?.key
  // K-mode: review stage sebelumnya (done) tanpa mengubah posisi pipeline.
  const [viewingKey, setViewingKey] = useState<StageKey | null>(null)
  const [regenFromStage, setRegenFromStage] = useState<StageKey | null>(null)
  const [regenBusy, setRegenBusy] = useState(false)
  // Review mode: artifact diambil dari DB sebagai sumber kebenaran (di-merge
  // ke artifacts). reviewFetchedRef invalidasi dilakukan saat regen/retry/reset.
  const reviewFetchedRef = useRef(new Set<string>())
  const displayKey = viewingKey ?? activeKey
  const displayStage =
    stages.find((s) => s.key === displayKey) ?? stages[current]
  const isViewing = viewingKey !== null && viewingKey !== activeKey
  const activeKeyRef = useRef(activeKey)
  useEffect(() => {
    activeKeyRef.current = activeKey
  }, [activeKey])

  // M2: auto-advance antar tahap non-MCQ saat toggle aktif (target=both 14 stage).
  const approveNextRef = useRef<() => void>(() => {})
  useEffect(() => {
    approveNextRef.current = approveNext
  })
  useEffect(() => {
    if (!autoAdvance) return
    const key = stages[current]?.key
    if (!key || status[key] !== "done") return
    // Berhenti auto di stage klarifikasi (butuh input user) — user lanjut manual.
    if (key === "pertanyaan" || key === "pertanyaan_mobile") return
    const t = setTimeout(() => {
      approveNextRef.current()
    }, 500)
    return () => clearTimeout(t)
  }, [status, current, autoAdvance, stages])

  // Real backend integration states
  const [projectId, setProjectId] = useState<number | null>(null)
  const [versionId, setVersionId] = useState<number | null>(null)
  const [artifacts, setArtifacts] = useState<Record<StageKey, string>>(
    {} as Record<StageKey, string>
  )
  const [error, setError] = useState<string>("")
  const [creating, setCreating] = useState(false)
  const [deleting, setDeleting] = useState(false)
  const [editingStage, setEditingStage] = useState<StageKey | null>(null)
  const [editPreview, setEditPreview] = useState(false)
  const [editContent, setEditContent] = useState("")
  const [savingArtifact, setSavingArtifact] = useState(false)
  const [retryInfo, setRetryInfo] = useState<{
    attempt: number
    max: number
  } | null>(null)
  const [phaseProg, setPhaseProg] = useState<Version["phase_progress"]>([])
  const [stageTokens, setStageTokens] = useState<Record<string, number>>({})
  // startedAt: updated by SSE event handler (bukan useEffect) untuk hindari
  // React Compiler 'set-state-in-effect' rule.
  const [startedAt, setStartedAt] = useState<number | null>(null)
  const [pendingConfirmMaster, setPendingConfirmMaster] = useState(false)
  const [showCancelConfirm, setShowCancelConfirm] = useState(false)
  const [showResetConfirm, setShowResetConfirm] = useState(false)
  // CP-9 M-5: auto-open MasterPromptViewer modal saat stage master_web/mobile baru selesai.
  const [masterModalOpen, setMasterModalOpen] = useState(false)
  const [masterModalTarget, setMasterModalTarget] = useState<
    "web" | "mobile" | null
  >(null)
  const masterAutoOpenedRef = useRef<{ web: boolean; mobile: boolean }>({
    web: false,
    mobile: false,
  })

  // Fase web (phases) + status tracking — untuk gate: web belum selesai bangun
  // bila ada fase `phases_web` dengan phase_progress belum semua done.
  const webPhases = useMemo(() => {
    try {
      const p = JSON.parse(artifacts.phases_web || "[]")
      return Array.isArray(p) ? (p as PhaseItem[]) : []
    } catch {
      return []
    }
  }, [artifacts.phases_web])
  const webTrackingDone = useMemo(() => {
    if (webPhases.length === 0) return true
    const keySet = new Set(webPhases.map((p) => p.key ?? ""))
    const doneCount = (phaseProg ?? []).filter(
      (pp) => keySet.has(pp.phase_key) && pp.done
    ).length
    return doneCount >= webPhases.length
  }, [webPhases, phaseProg])

  const mobilePhases = useMemo(() => {
    try {
      const p = JSON.parse(artifacts.phases_mobile || "[]")
      return Array.isArray(p) ? (p as PhaseItem[]) : []
    } catch {
      return []
    }
  }, [artifacts.phases_mobile])

  const showTrackingPanel =
    activeKey === "master_web" ||
    activeKey === "master_mobile" ||
    (activeKey === "agents" &&
      (webPhases.length > 0 || mobilePhases.length > 0))
  const trackingPhases =
    activeKey === "master_mobile" ||
    (activeKey === "agents" && mobilePhases.length > 0)
      ? mobilePhases
      : webPhases
  const progMap = useMemo(
    () =>
      Object.fromEntries(
        (phaseProg ?? []).map((p: ProgressItem) => [p.phase_key, p])
      ),
    [phaseProg]
  )

  const cancelled = useRef(false)
  const creatingRef = useRef(false)
  const fallbackFetched = useRef(new Set<string>())
  const resumeAutoStartedRef = useRef(false)
  const [apiContractDbItems, setApiContractDbItems] = useState<
    ApiContractItem[] | null
  >(null)
  const apiContractFetchRef = useRef(false)
  const outputRef = useRef<HTMLDivElement>(null)
  const webPhasesRef = useRef<PhaseItem[]>([])
  const mobilePhasesRef = useRef<PhaseItem[]>([])
  const artifactsRef = useRef<Record<StageKey, string>>(
    {} as Record<StageKey, string>
  )
  useEffect(() => {
    artifactsRef.current = artifacts
  }, [artifacts])
  useEffect(() => {
    webPhasesRef.current = webPhases
  }, [webPhases])
  useEffect(() => {
    mobilePhasesRef.current = mobilePhases
  }, [mobilePhases])

  // Parse MCQ JSON toleran: strip fence, buang trailing comma, ambil blok {..} terluar valid.
  const parseMcq = useCallback((raw: string): McqData | null => {
    if (!raw) return null
    const attempt = (json: string): McqData | null => {
      try {
        const cleaned = json.replace(/,\s*([\]}])/g, "$1")
        const parsed = JSON.parse(cleaned) as McqData
        if (
          parsed.questions &&
          Array.isArray(parsed.questions) &&
          parsed.questions.length > 0
        ) {
          return parsed
        }
      } catch {
        /* fallthrough */
      }
      return null
    }

    // Direct attempt
    const direct = attempt(raw)
    if (direct) return direct

    // Strip code fences, lalu ambil blok { } terluar
    const unFenced = raw.replace(/```(?:json)?/gi, "").trim()
    const first = unFenced.indexOf("{")
    const last = unFenced.lastIndexOf("}")
    if (first !== -1 && last !== -1 && last > first) {
      const block = attempt(unFenced.slice(first, last + 1))
      if (block) return block
    }
    return null
  }, [])

  const mcqData = useMemo((): McqData | null => {
    if (!artifacts.pertanyaan) return null
    return parseMcq(artifacts.pertanyaan)
  }, [artifacts.pertanyaan, parseMcq])

  // P7: parseMcq failures silently degraded — backend akan auto-retry via
  // retryPertanyaanForMinimum. Tidak menampilkan warning karena React Compiler
  // melarang setState di render atau effect dengan deps baru.

  const mcqMobileData = useMemo((): McqData | null => {
    if (!artifacts.pertanyaan_mobile) return null
    return parseMcq(artifacts.pertanyaan_mobile)
  }, [artifacts.pertanyaan_mobile, parseMcq])

  // Restore pilihan radio MCQ dari jawaban tersimpan (format: "q5: ..." → "A. ..." / "E. Lainnya: ...").
  // Dipisah dari useResume effect karena butuh questions dari artifact yang
  // di-parse (mcqData) — baru tersedia setelah artifacts diload.
  const restoreMcq = useCallback(
    (
      stored: Record<string, string>,
      data: McqData | null,
      dataSetter: (k: Record<string, McqAnswer>) => void
    ) => {
      if (!data || Object.keys(stored).length === 0) return
      const restored: Record<string, McqAnswer> = {}
      Object.entries(stored).forEach(([k, v]) => {
        const qm = k.match(/^q(\d+)\s*:/i)
        if (!qm) return
        const q = data.questions[parseInt(qm[1], 10) - 1]
        if (!q) return
        const vm = String(v).match(/^([A-E])\.\s*(.+)$/)
        if (!vm) return
        if (vm[1] === "E") {
          restored[q.id] = {
            selected: "E",
            custom_text: vm[2].replace(/^Lainnya\s*:\s*/i, ""),
          }
        } else {
          restored[q.id] = { selected: vm[1] }
        }
      })
      if (Object.keys(restored).length > 0) dataSetter(restored)
    },
    []
  )

  useEffect(() => {
    if (Object.keys(mcqAnswers).length > 0) return
    restoreMcq(answers, mcqData, setMcqAnswers)
  }, [mcqData, answers, mcqAnswers, restoreMcq])

  useEffect(() => {
    if (Object.keys(mobileMcqAnswers).length > 0) return
    restoreMcq(resumedMobileAnswers, mcqMobileData, setMobileMcqAnswers)
  }, [mcqMobileData, resumedMobileAnswers, mobileMcqAnswers, restoreMcq])

  // Legacy plain-text questions fallback
  const questions = useMemo(() => {
    if (mcqData) return [] as string[]
    if (!artifacts.pertanyaan) return [] as string[]
    const lines = artifacts.pertanyaan
      .split("\n")
      .filter((l: string) => l.trim())
    let parsed = lines
      .filter((l: string) => /^\d+[.)]\s/.test(l.trim()))
      .map((l: string) => l.replace(/^\d+[.)]\s*/, "").trim())
    if (parsed.length === 0)
      parsed = lines.filter((l: string) => l.trim().endsWith("?"))
    if (parsed.length === 0)
      parsed = ["Jelaskan lebih detail tentang aplikasi yang kamu inginkan?"]
    return parsed
  }, [artifacts.pertanyaan, mcqData])

  // Permanent fallback untuk pertanyaan mobile bila AI output teks (bukan JSON).
  const mobileQuestions = useMemo(() => {
    if (mcqMobileData) return [] as string[]
    if (!artifacts.pertanyaan_mobile) return [] as string[]
    const lines = artifacts.pertanyaan_mobile
      .split("\n")
      .filter((l: string) => l.trim())
    let parsed = lines
      .filter((l: string) => /^\d+[.)]\s/.test(l.trim()))
      .map((l: string) => l.replace(/^\d+[.)]\s*/, "").trim())
    if (parsed.length === 0)
      parsed = lines.filter((l: string) => l.trim().endsWith("?"))
    if (parsed.length === 0)
      parsed = ["Jelaskan lebih detail tentang kebutuhan mobile kamu?"]
    return parsed
  }, [artifacts.pertanyaan_mobile, mcqMobileData])

  // API contract parser toleran: array | {endpoints:[...]} | resource-keyed object.
  // auth backend bisa "none"/"required" (string) atau boolean.
  function parseApiContractItems(raw: unknown): ApiContractItem[] {
    try {
      let v: unknown = raw
      if (typeof v === "string") v = JSON.parse(v)
      let arr: unknown[] = []
      if (Array.isArray(v)) arr = v
      else if (v && typeof v === "object") {
        const o = v as Record<string, unknown>
        if (Array.isArray(o.endpoints)) arr = o.endpoints
        else arr = Object.values(o).flatMap((x) => (Array.isArray(x) ? x : []))
      }
      return arr
        .filter(
          (it): it is Record<string, unknown> => !!it && typeof it === "object"
        )
        .filter((it) => it.method || it.path)
        .map((it) => ({
          method: String(it.method ?? "GET").toUpperCase(),
          path: String(it.path ?? ""),
          description: String(it.description ?? ""),
          auth: it.auth === true || it.auth === "required" || it.auth === "yes",
        }))
    } catch {
      return []
    }
  }

  // Normalisasi item api_contract: auth backend bisa string, paksa boolean.
  const normalizeApiItems = useCallback(
    (items: unknown): ApiContractItem[] => parseApiContractItems(items),
    []
  )

  // Parse ERD artifact toleran: strip code fence + ambil blok JSON terluar.
  const parseErdArtifact = useCallback(
    (raw: string): ErdParsed | null => {
      const withNorm = (p: ErdParsed): ErdParsed => ({
        ...p,
        api_contract: Array.isArray(p.api_contract)
          ? normalizeApiItems(p.api_contract)
          : p.api_contract,
      })
      try {
        return withNorm(JSON.parse(raw) as ErdParsed)
      } catch {
        /* fallthrough */
      }
      try {
        const unFenced = raw.replace(/```(?:json)?/gi, "").trim()
        const first = unFenced.indexOf("{")
        const last = unFenced.lastIndexOf("}")
        if (first === -1 || last === -1) return null
        return withNorm(
          JSON.parse(unFenced.slice(first, last + 1)) as ErdParsed
        )
      } catch {
        return null
      }
    },
    [normalizeApiItems]
  )

  // CP-46.D step 2 — wire usePipelineStream.
  const streamApi = usePipelineStream(
    liteMode,
    useMemo(
      () => ({
        onStatus: (stage, state) => {
          setStatus((s) => ({ ...s, [stage]: state }))
          if (state === "retrying") {
            setArtifacts((prev) => ({ ...prev, [stage]: "" }))
          }
          if (state === "running") {
            const idx = stages.findIndex((x) => x.key === stage)
            if (idx >= 0) setCurrent(idx)
            setStartedAt((prev) => prev ?? Date.now())
          }
          if (state === "done") {
            setRetryInfo(null)
            chime()
            if (stage === "master_web" && !masterAutoOpenedRef.current.web) {
              masterAutoOpenedRef.current.web = true
              setMasterModalTarget("web")
              setMasterModalOpen(true)
            } else if (
              stage === "master_mobile" &&
              !masterAutoOpenedRef.current.mobile
            ) {
              masterAutoOpenedRef.current.mobile = true
              setMasterModalTarget("mobile")
              setMasterModalOpen(true)
            }
          }
        },
        onToken: (stage, delta) => {
          setArtifacts((prev) => {
            const key = stage as StageKey
            return {
              ...prev,
              [key]: (prev[key] || "") + delta,
            }
          })
        },
        onArtifact: (stage, content) => {
          setArtifacts((prev) => ({ ...prev, [stage]: content }))
        },
        onStageTokens: (stage, tokens) => {
          setStageTokens((prev) => ({ ...prev, [stage]: tokens }))
        },
        onDone: (stage) => {
          setFailedStage(null)
          const stageIndex = stages.findIndex((x) => x.key === stage)
          if (stageIndex >= 0) {
            setCurrent(stageIndex)
            setStatus((s) => ({ ...s, [stage]: "done" as StageState }))
          }
        },
        onFail: (stage, message) => {
          setError(message)
          if (stage) {
            setStatus((s) => ({ ...s, [stage]: "error" as StageState }))
            setFailedStage(stage as StageKey)
          }
        },
        onRetryInfo: (attempt, max) => {
          setRetryInfo({ attempt, max })
        },
      }),
      [stages]
    )
  )

  const startPipeline = useCallback(
    (versionId: number, stage?: string) => {
      const s = stage || stages[0].key
      const idx = stages.findIndex((x) => x.key === s)
      if (idx >= 0) setCurrent(idx)
      setStatus((prev) => ({ ...prev, [s]: "running" }))
      streamApi.startPipeline(versionId, s)
    },
    [streamApi, stages]
  )

  // Apply template seed when selected
  function handleTemplateChange(value: string) {
    setSelectedTemplate(value)
    if (!value) return
    const tpl = templates.find((t) => String(t.id) === value)
    if (!tpl || !tpl.seed) return
    const seed = tpl.seed as Record<string, string>
    if (seed.title) setTitle(seed.title)
    if (seed.idea) setIdea(seed.idea)
    if (seed.target && ["web", "both"].includes(seed.target)) {
      setTargetAndReset(seed.target as Target)
    }
  }

  // Load templates on mount — auto-select if ?template=N URL param present
  useEffect(() => {
    apiGet<Template[]>("/templates")
      .then((t) => {
        setTemplates(t)
        if (templateParam) {
          const tpl = t.find((x) => String(x.id) === templateParam)
          if (tpl && tpl.seed) {
            const seed = tpl.seed as Record<string, string>
            setSelectedTemplate(String(tpl.id))
            if (seed.title) setTitle(seed.title)
            if (seed.idea) setIdea(seed.idea)
            if (seed.target && ["web", "both"].includes(seed.target)) {
              setTargetAndReset(seed.target as Target)
            }
          }
        }
      })
      .catch((err) => console.error("Failed to load templates:", err))
  }, [templateParam, setTargetAndReset])

  // M3: resume session detect — setelah login ulang, /new otomatis lanjut ke versi yang hilang.
  useEffect(() => {
    if (isResume) return
    if (typeof window === "undefined") return
    const v = sessionStorage.getItem("wizard:lostVersion")
    if (!v) return
    sessionStorage.removeItem("wizard:lostVersion")
    sessionStorage.removeItem("wizard:lostProject")
    router.replace(`/new?resume=1&version=${encodeURIComponent(v)}`)
  }, [isResume, router])

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      streamApi.abort()
    }
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [])

  // Tracking fase real-time — SSE via EventSource saat berada di stage master.
  // Deps: versionId + showTracking (bukan hanya versionId) agar subscribe ulang
  // saat masuk master_*/agents — sebelumnya panel tak pernah hidup (bug 55-1.1).
  const showTrackingNow =
    versionId !== null &&
    (activeKey === "master_web" ||
      activeKey === "master_mobile" ||
      (activeKey === "agents" &&
        (webPhasesRef.current.length > 0 ||
          mobilePhasesRef.current.length > 0)))
  useEffect(() => {
    if (!versionId || !showTrackingNow) return

    // Initial fetch for immediate render
    apiGet<Version>(`/versions/${versionId}`)
      .then((v) => {
        if (v.phase_progress) setPhaseProg(v.phase_progress)
        if (v.stage_tokens) setStageTokens(v.stage_tokens)
      })
      .catch(() => {})

    const pp = createPhaseProgressStream(
      `/versions/${versionId}/phase-progress/stream`,
      (event, data) => {
        if (event === "phase_progress") {
          const d = data as ProgressItem
          setPhaseProg((prev) => {
            const idx = (prev ?? []).findIndex(
              (p) => p.phase_key === d.phase_key
            )
            if (idx >= 0) {
              const next = [...(prev ?? [])]
              next[idx] = { ...next[idx], ...d }
              return next
            }
            return [...(prev ?? []), d]
          })
        }
      }
    )
    return () => pp.abort()
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [versionId, showTrackingNow])

  // api_contract: kalau parse artifact SSE gagal/kosong walau stage done,
  // fetch kolom DB sekali (artifact stream bisa non-normalized).
  useEffect(() => {
    if (status.api_contract !== "done" || !versionId) return
    if (apiContractFetchRef.current) return
    if (parseApiContractItems(artifacts.api_contract).length > 0) return
    apiContractFetchRef.current = true
    apiGet<Record<string, unknown>>(`/versions/${versionId}`)
      .then((v) => setApiContractDbItems(parseApiContractItems(v.api_contract)))
      .catch(() => setApiContractDbItems([]))
  }, [status.api_contract, versionId, artifacts.api_contract])

  // Review mode: fetch artifact dari DB saat stage lain dilihat (sekali per stage).
  // Hasil langsung di-merge ke artifacts (sumber render yang sudah ada).
  useEffect(() => {
    if (!viewingKey || !versionId) return
    const col = ARTIFACT_COL_MAP[viewingKey]
    if (!col) return
    if (reviewFetchedRef.current.has(viewingKey)) return
    reviewFetchedRef.current.add(viewingKey)
    apiGet<Record<string, unknown>>(`/versions/${versionId}`)
      .then((v) => {
        const c = v[col]
        if (c != null && c !== "") {
          const val = typeof c === "string" ? c : JSON.stringify(c, null, 2)
          setArtifacts((p) => ({ ...p, [viewingKey]: val }))
        }
      })
      .catch(() => reviewFetchedRef.current.delete(viewingKey))
  }, [viewingKey, versionId])

  // Fallback: fetch artifact from DB when SSE artifact event was lost
  useEffect(() => {
    if (!versionId) return
    const colMap = ARTIFACT_COL_MAP
    const missing = stages.filter(
      (s) =>
        status[s.key] === "done" &&
        !artifactsRef.current[s.key] &&
        !fallbackFetched.current.has(s.key)
    )
    if (missing.length === 0) return
    for (const stage of missing) fallbackFetched.current.add(stage.key)

    apiGet<Record<string, unknown>>(`/versions/${versionId}`)
      .then((v) => {
        setArtifacts((prev) => {
          const next = { ...prev }
          for (const stage of missing) {
            const col = colMap[stage.key]
            if (!col) continue
            if (next[stage.key]) continue
            const content = v[col]
            if (content == null || content === "") continue
            next[stage.key] =
              typeof content === "string"
                ? content
                : JSON.stringify(content, null, 2)
          }
          return next
        })
      })
      .catch((err) => console.error("Failed to fetch artifact fallback:", err))
  }, [status, versionId, stages])

  // Resume mode: load existing version data via useResume hook (CP-46.D step 1).
  const { result: resumeResult, error: resumeError } = useResume(
    resumeVersionId,
    isResume,
    started
  )

  useEffect(() => {
    if (!resumeResult) return
    const r = resumeResult

    // Reset fallback cache agar resume bisa re-fetch artifact dari DB bila SSE
    // 'artifact' event hilang saat restart.
    fallbackFetched.current.clear()
    reviewFetchedRef.current.clear()
    setViewingKey(null)

    setProjectId(r.projectId)
    setVersionId(r.versionId)
    setTitle(r.title)
    setIdea(r.idea)
    setTarget(r.target)
    if (r.answers && Object.keys(r.answers).length > 0) {
      setAnswers(r.answers as Record<string, string>)
    }
    if (r.mobileAnswers && Object.keys(r.mobileAnswers).length > 0) {
      setResumedMobileAnswers(r.mobileAnswers as Record<string, string>)
    }
    if (r.liteMode) setLiteMode(true)
    setStarted(r.started)

    setCurrent(r.currentStageIdx)
    setStatus(r.status)
    setArtifacts(r.artifacts)
    setPhaseProg(
      r.phaseProg.map((p) => ({
        ...p,
        done: p.done ?? false,
      })) as Version["phase_progress"]
    )
    setResumeInfo(r.resumeInfo)

    if (r.resumeError) {
      setError(r.resumeError)
      setStatus((s) => ({ ...s, master_web: "running" as StageState }))
    }

    // Auto-start pipeline jika ada stage belum done. One-shot: tanpa guard ini
    // efek re-run tiap render (startPipeline berganti identity) → POST
    // /generate/stream berulang tanpa henti → flash loop + 429.
    if (r.resumeInfo && r.currentStageIdx < r.resumeInfo.total) {
      const st = r.status[r.resumeInfo.stage as StageKey]
      if (!resumeAutoStartedRef.current && st !== "running") {
        resumeAutoStartedRef.current = true
        startPipeline(r.versionId, r.resumeInfo.stage)
      }
    }
  }, [resumeResult, startPipeline])

  useEffect(() => {
    if (resumeError) setError(resumeError)
  }, [resumeError])

  // Auto-scroll modal output
  const activeArtifact = artifacts[activeKey]
  useEffect(() => {
    if (outputRef.current) {
      outputRef.current.scrollTop = outputRef.current.scrollHeight
    }
  }, [activeArtifact])

  async function start() {
    if (!title.trim() || !idea.trim()) return
    if (creatingRef.current) return

    creatingRef.current = true
    setCreating(true)
    setError("")
    cancelled.current = false

    try {
      const project = await apiPost<Project>("/projects", {
        title: title.trim(),
        idea,
        target,
      })

      setProjectId(project.id)

      const projectWithVersions = await apiGet<
        Project & { versions: Array<{ id: number }> }
      >(`/projects/${project.id}`)
      const vId = projectWithVersions.versions?.[0]?.id

      if (!vId) {
        throw new Error("Version tidak ditemukan")
      }

      setVersionId(vId)
      setStarted(true)
      setCreating(false)

      startPipeline(vId)
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : "Gagal membuat project")
      creatingRef.current = false
      setCreating(false)
      console.error("Failed to create project:", err)
    }
  }

  function approveNext() {
    if (!versionId || current + 1 >= stages.length) return
    if (stages[current].key === "pertanyaan") return

    const currentKey = stages[current].key
    // Gate: bila current = master_web (akhir track web) & tracking fase belum selesai,
    // jangan langsung lanjut — minta konfirmasi user (web kemungkinan belum selesai dibangun).
    if (currentKey === "master_web" && !webTrackingDone) {
      setPendingConfirmMaster(true)
      return
    }

    const nextStage = stages[current + 1].key

    streamApi.abort()

    startPipeline(versionId, nextStage)
  }

  function proceedAfterMasterConfirm() {
    setPendingConfirmMaster(false)
    if (!versionId || current + 1 >= stages.length) return
    const nextStage = stages[current + 1].key
    streamApi.abort()
    startPipeline(versionId, nextStage)
  }

  function retryStage() {
    if (!versionId) return

    const currentStage = stages[current].key

    setStatus((s) => ({ ...s, [currentStage]: "pending" }))
    setArtifacts((prev) => {
      const newArtifacts = { ...prev }
      delete newArtifacts[currentStage as StageKey]
      return newArtifacts
    })
    setError("")

    streamApi.abort()

    startPipeline(versionId, currentStage)
  }

  async function handleRegenFromStage() {
    if (!regenFromStage || !versionId || regenBusy) return
    setRegenBusy(true)
    setError("")
    try {
      // Keputusan UX: pipeline yang berjalan di-stop dulu, lalu regen dari stage terpilih.
      reviewFetchedRef.current.clear()
      streamApi.abort()
      const res = await apiPost<{ ok?: boolean; message?: string }>(
        `/versions/${versionId}/regenerate`,
        { stage: regenFromStage }
      )
      if (res && res.ok === false) {
        setError(res.message || "Generate ulang gagal — state dikembalikan.")
        setRegenFromStage(null)
        return
      }
      setRegenFromStage(null)
      setViewingKey(null)
      // Reload via resume flow agar state + auto start konsisten. Hard reload
      // sengaja: reset React state sepenuhnya sebelum resume dari DB.
      // eslint-disable-next-line @next/next/no-location-assign-relative-destination
      window.location.assign(`/new?resume=1&version=${versionId}`)
    } catch (e) {
      setError(e instanceof Error ? e.message : "Gagal regenerate.")
      // Jangan tinggalkan stage 'running' tanpa stream — pipeline sudah diabort.
      if (status[activeKey] === "running") {
        setStatus((s) => ({ ...s, [activeKey]: "error" }))
        setFailedStage(activeKey)
      }
    } finally {
      setRegenBusy(false)
    }
  }

  function cancelGeneration() {
    cancelled.current = true
    streamApi.cancelAll()
    const stage = activeKeyRef.current
    setFailedStage(null)
    setStatus((s) => ({ ...s, [stage]: "pending" }))
    setError("Pembuatan plan dibatalkan. Stage bisa diulang kapan saja.")
    setShowCancelConfirm(false)
    if (versionId && stage) {
      apiPost(`/versions/${versionId}/cancel`, { stage }).catch(() => {})
    }
  }

  async function reset() {
    if (deleting) return
    setShowResetConfirm(false)
    cancelled.current = true
    streamApi.abort()
    fallbackFetched.current.clear()
    reviewFetchedRef.current.clear()
    resumeAutoStartedRef.current = false
    setResumeInfo(null)

    const pid = projectId
    setError("")

    // Hapus project yang sedang berjalan secara permanen (backend cascade menghapus versions).
    if (pid) {
      try {
        await apiDelete(`/projects/${pid}`)
      } catch (err) {
        console.error("Gagal menghapus project:", err)
      }
    }

    setDeleting(false)
    setProjectId(null)
    setVersionId(null)
    setStarted(false)
    setCurrent(0)
    setStatus(initStatus(target))
    setArtifacts({} as Record<StageKey, string>)
    masterAutoOpenedRef.current = { web: false, mobile: false }
    setMasterModalOpen(false)
    setMasterModalTarget(null)
    setAnswers({})
    setMcqAnswers({})
    setMobileMcqAnswers({})
    setPhaseProg([])
    setRetryInfo(null)
    setTitle("")
    setIdea("")
    setSelectedTemplate("")
    setEditingStage(null)
    setEditContent("")
    // Kosongkan query resume agar isResume=false → kembali ke form input (bukan spinner).
    router.replace("/new")
  }

  // ===== Input screen =====
  if (!started) {
    if (isResume) {
      if (resumeError) {
        return (
          <div className="mx-auto max-w-lg space-y-4 py-24 text-center">
            <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
              {resumeError}
            </div>
            <ButtonLink href="/projects" variant="secondary">
              Kembali ke Projects
            </ButtonLink>
          </div>
        )
      }
      return (
        <div className="mx-auto flex max-w-lg items-center justify-center py-24">
          <div className="text-center">
            <Loader2
              size={32}
              className="mx-auto animate-spin text-[var(--color-brand)]"
            />
            <p className="mt-4 text-sm text-[var(--color-fg-muted)]">
              Memuat project...
            </p>
          </div>
        </div>
      )
    }

    return (
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white">
            <Wand2 size={26} />
          </div>
          <h1 className="text-2xl font-bold sm:text-3xl">Buat Plan Baru</h1>
          <p className="mt-2 text-[var(--color-fg-muted)]">
            Deskripsikan idemu, AI akan menyusun blueprint lengkap.
          </p>
        </div>

        <Card className="p-6">
          <div className="space-y-5">
            {templates.length > 0 && (
              <div>
                <Label htmlFor="template">Template (opsional)</Label>
                <select
                  id="template"
                  value={selectedTemplate}
                  onChange={(e) => handleTemplateChange(e.target.value)}
                  className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm"
                  data-testid="template-select"
                >
                  <option value="">— Pilih template —</option>
                  {templates.map((t) => (
                    <option key={t.id} value={t.id}>
                      {t.name} ({t.target})
                    </option>
                  ))}
                </select>
              </div>
            )}

            <div>
              <Label htmlFor="title">Judul Project</Label>
              <input
                id="title"
                type="text"
                value={title}
                onChange={(e) => setTitle(e.target.value)}
                placeholder="Contoh: Aplikasi Kasir UMKM"
                className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm"
                data-testid="title-input"
              />
            </div>

            <div>
              <Label htmlFor="idea">Ide Aplikasi</Label>
              <Textarea
                id="idea"
                rows={4}
                value={idea}
                onChange={(e) => setIdea(e.target.value)}
                placeholder="Contoh: Aplikasi kasir untuk warung dengan manajemen stok dan laporan penjualan harian…"
                data-testid="idea-input"
              />
            </div>

            <div>
              <Label>Target Platform</Label>
              <div className="grid grid-cols-2 gap-2">
                {TARGETS.map((t) => (
                  <button
                    key={t.key}
                    onClick={() => setTargetAndReset(t.key)}
                    data-testid={`target-${t.key}`}
                    className={`flex flex-col items-center gap-2 rounded-xl border p-4 text-sm font-medium transition ${
                      target === t.key
                        ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_12%,transparent)] text-[var(--color-brand)]"
                        : "border-[var(--color-border)] text-[var(--color-fg-muted)] hover:border-[var(--color-fg-subtle)]"
                    }`}
                  >
                    <t.icon size={20} /> {t.label}
                  </button>
                ))}
              </div>
            </div>

            <div className="flex items-center gap-2 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs text-[var(--color-fg-muted)]">
              <label className="inline-flex cursor-pointer items-center gap-2 select-none">
                <input
                  type="checkbox"
                  checked={liteMode}
                  onChange={(e) => setLiteMode(e.target.checked)}
                  className="rounded border-[var(--color-border)] accent-[var(--color-brand)]"
                />
                <span>Lite Plan (lebih cepat, kurang detail)</span>
              </label>
            </div>

            <Button
              onClick={start}
              disabled={!title.trim() || !idea.trim() || creating}
              className="w-full"
              size="lg"
              data-testid="start-plan"
            >
              <Sparkles size={18} />{" "}
              {creating ? "Membuat Project..." : "Buat Plan"}
            </Button>

            {error && (
              <div className="flex items-center gap-2 rounded-lg border border-red-500/50 bg-red-500/10 p-3 text-sm text-red-500">
                <AlertCircle size={16} />
                <span>{error}</span>
              </div>
            )}
          </div>
        </Card>
      </div>
    )
  }

  // ===== Pipeline screen =====
  return (
    <ErrorBoundary>
      {showConfetti && <Confetti />}
      <div className="mx-auto max-w-5xl">
        <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
          <div>
            <h1 className="text-2xl font-bold">Menyusun Plan…</h1>
            <p className="mt-1 line-clamp-1 text-sm text-[var(--color-fg-muted)]">
              {idea}
            </p>
          </div>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setShowResetConfirm(true)}
            disabled={deleting}
            data-testid="reset-plan"
          >
            {deleting ? (
              <Loader2 size={15} className="animate-spin" />
            ) : (
              <RotateCcw size={15} />
            )}{" "}
            {deleting ? "Menghapus..." : "Mulai Ulang"}
          </Button>
        </div>

        {resumeInfo && isResume && (
          <div className="mb-4 flex items-center gap-2 rounded-lg border border-[var(--color-brand)]/40 bg-[color-mix(in_oklab,var(--color-brand)_10%,transparent)] px-4 py-2.5 text-sm text-[var(--color-fg)]">
            <Play size={14} className="text-[var(--color-brand)]" />
            <span>
              Melanjutkan dari <strong>{resumeInfo.stage}</strong> —{" "}
              {resumeInfo.remaining} dari {resumeInfo.total} tahap tersisa.
              Tahap yang sudah selesai tidak akan diulang.
            </span>
          </div>
        )}

        <div className="grid gap-6 lg:grid-cols-[280px_minmax(0,1fr)_340px]">
          {/* Stage tracker */}
          <div className="min-w-0 space-y-2">
            {(() => {
              const totalTokens = Object.values(
                stageTokens ?? {}
              ).reduce<number>(
                (sum, n) => sum + (typeof n === "number" ? n : 0),
                0
              )
              return (
                <div
                  className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 text-[11px] text-[var(--color-fg-muted)]"
                  style={{ fontVariantNumeric: "tabular-nums" }}
                >
                  <div className="flex items-center justify-between">
                    <span>Total token</span>
                    <span className="font-mono text-[var(--color-fg)]">
                      {totalTokens.toLocaleString("id-ID")}
                    </span>
                  </div>
                </div>
              )
            })()}
            {stages.map((s, i) => {
              const st = status[s.key]
              // CP-3: key mencakup status agar CSS animation re-trigger saat transisi ke 'done'.
              const rowKey = `${s.key}:${st}`
              // Review mode: row stage done (non-master) clickable untuk melihat lagi.
              const reviewable =
                st === "done" &&
                s.key !== "master_web" &&
                s.key !== "master_mobile" &&
                s.key !== "verify.review" &&
                s.key !== "smoke_test" &&
                s.key !== "verify.production_readiness"
              return (
                <div
                  key={rowKey}
                  data-testid={`stage-${s.key}`}
                  data-state={st}
                  role={reviewable ? "button" : undefined}
                  tabIndex={reviewable ? 0 : undefined}
                  onClick={
                    reviewable
                      ? () => {
                          setViewingKey((cur) =>
                            cur === s.key ? null : (s.key as StageKey)
                          )
                        }
                      : undefined
                  }
                  onKeyDown={
                    reviewable
                      ? (e) => {
                          if (e.key === "Enter" || e.key === " ") {
                            e.preventDefault()
                            setViewingKey(s.key as StageKey)
                          }
                        }
                      : undefined
                  }
                  className={`flex items-start gap-3 rounded-xl border p-3 transition ${
                    reviewable ? "cursor-pointer" : ""
                  } ${
                    viewingKey === s.key
                      ? "border-[var(--color-brand-2,#8b5cf6)] bg-[color-mix(in_oklab,var(--color-brand)_6%,transparent)]"
                      : i === current && st === "running"
                        ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_8%,transparent)]"
                        : "border-[var(--color-border)]"
                  } ${st === "done" ? "done-flash" : ""}`}
                >
                  <span className="mt-0.5">
                    {st === "done" ? (
                      <span className="check-draw grid h-6 w-6 place-items-center rounded-full bg-[var(--color-success)] text-white">
                        <Check size={14} />
                      </span>
                    ) : st === "running" ? (
                      <span className="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-brand)] text-white">
                        <Loader2 size={14} className="animate-spin" />
                      </span>
                    ) : st === "error" ? (
                      <span className="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-danger)] text-white">
                        <AlertCircle size={14} />
                      </span>
                    ) : st === "blocked" ? (
                      <span
                        className="grid h-6 w-6 place-items-center rounded-full border border-[var(--color-warning,#f59e0b)] text-[var(--color-warning,#f59e0b)]"
                        title="Stage diblokir gate — selesaikan dependensi/approve tracking dulu"
                      >
                        <AlertCircle size={13} />
                      </span>
                    ) : st === "skipped" ? (
                      <span className="grid h-6 w-6 place-items-center rounded-full border border-dashed border-[var(--color-border)] text-[var(--color-fg-subtle)]">
                        <CircleDot size={13} />
                      </span>
                    ) : (
                      <span className="grid h-6 w-6 place-items-center rounded-full border border-[var(--color-border)] text-[var(--color-fg-subtle)]">
                        <CircleDot size={13} />
                      </span>
                    )}
                  </span>
                  <div className="min-w-0">
                    <div className="text-sm font-medium">{s.label}</div>
                    <div className="text-xs text-[var(--color-fg-muted)]">
                      {s.desc}
                    </div>
                  </div>
                </div>
              )
            })}
          </div>

          {/* Review banner untuk stage yang sedang dilihat (bukan posisi pipeline) */}
          {isViewing && (
            <div className="flex flex-wrap items-center justify-between gap-3 rounded-xl border border-[var(--color-brand-2,#8b5cf6)]/40 bg-[color-mix(in_oklab,var(--color-brand)_6%,transparent)] px-4 py-3">
              <div className="text-sm">
                Sedang melihat <strong>{displayStage.label}</strong>. Pipeline
                tidak berubah.
              </div>
              <div className="flex items-center gap-2">
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => setViewingKey(null)}
                >
                  Kembali ke stage aktif
                </Button>
                <Button
                  variant="secondary"
                  size="sm"
                  onClick={() => setRegenFromStage(displayKey)}
                  disabled={regenBusy}
                >
                  <RotateCcw size={13} /> Generate ulang dari sini
                </Button>
              </div>
            </div>
          )}

          {/* Artifact panel */}
          <div className="min-w-0 space-y-4">
            <Card className="p-5">
              <div className="mb-3 flex items-center justify-between">
                <div className="flex items-center gap-2">
                  <h3 className="font-semibold">{displayStage.label}</h3>
                  {status[displayKey] === "running" && (
                    <Badge tone="brand">Menyusun…</Badge>
                  )}
                  {status[displayKey] === "done" && (
                    <Badge tone="success">
                      <Check size={12} /> Selesai
                    </Badge>
                  )}
                </div>
                <div className="flex items-center gap-1.5">
                  {status[displayKey] === "done" &&
                    editingStage !== displayKey && (
                      <button
                        onClick={() => {
                          setEditingStage(displayKey)
                          setEditContent(artifacts[displayKey] || "")
                        }}
                        className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                      >
                        <Pencil size={13} /> Sunting
                      </button>
                    )}
                  <button
                    onClick={() => {
                      const text = artifacts[displayKey]
                      if (text) copyToClipboard(text)
                    }}
                    className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                  >
                    <Copy size={13} /> Salin
                  </button>
                </div>
              </div>

              {status[displayKey] === "running" ? (
                <div className="max-h-80 overflow-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3">
                  <StreamingMarkdown
                    content={artifacts[displayKey] || "Menunggu hasil AI..."}
                    live
                    className="border-0 bg-transparent"
                  />
                </div>
              ) : editingStage === displayKey ? (
                <div className="space-y-3">
                  <div className="flex items-center justify-between gap-2">
                    <div className="flex gap-1 rounded-lg border border-[var(--color-border)] p-0.5 text-xs">
                      {(["edit", "preview"] as const).map((m) => (
                        <button
                          key={m}
                          type="button"
                          onClick={() => setEditPreview(m === "preview")}
                          className={`rounded-md px-2.5 py-1 transition ${
                            (m === "preview") === editPreview
                              ? "bg-[var(--color-brand)] text-white"
                              : "text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                          }`}
                        >
                          {m === "edit" ? "Edit" : "Preview"}
                        </button>
                      ))}
                    </div>
                  </div>
                  {editPreview ? (
                    <div className="max-h-[400px] overflow-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3 text-sm">
                      <Markdown className="text-[var(--color-fg-muted)]">
                        {editContent || "_(kosong)_"}
                      </Markdown>
                    </div>
                  ) : (
                    <textarea
                      value={editContent}
                      onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) =>
                        setEditContent(e.target.value)
                      }
                      className="min-h-[200px] w-full resize-y rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3 font-mono text-sm text-[var(--color-fg)] focus:ring-2 focus:ring-[var(--color-accent)] focus:outline-none"
                    />
                  )}
                  <div className="flex items-center gap-2">
                    <button
                      onClick={async () => {
                        setArtifacts((prev) => ({
                          ...prev,
                          [displayKey]: editContent,
                        }))
                        setEditingStage(null)
                        setEditContent("")
                        if (versionId) {
                          setSavingArtifact(true)
                          try {
                            await apiPatch(`/versions/${versionId}/artifacts`, {
                              stage: displayKey,
                              content: editContent,
                            })
                          } catch (err) {
                            setError(
                              err instanceof Error
                                ? err.message
                                : "Gagal menyimpan artifact"
                            )
                          } finally {
                            setSavingArtifact(false)
                          }
                        }
                      }}
                      disabled={savingArtifact}
                      className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-accent)] px-3 py-1.5 text-xs text-white hover:opacity-90 disabled:opacity-50"
                    >
                      {savingArtifact ? (
                        <Loader2 size={13} className="animate-spin" />
                      ) : (
                        "Simpan"
                      )}
                    </button>
                    <button
                      onClick={() => {
                        setEditingStage(null)
                        setEditContent("")
                      }}
                      className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                    >
                      Batal
                    </button>
                  </div>
                </div>
              ) : (
                <>
                  {displayKey === "pertanyaan" &&
                  status.pertanyaan === "done" ? (
                    <div className="space-y-4">
                      {mcqData ? (
                        <McqForm
                          mcqData={mcqData}
                          answers={mcqAnswers}
                          onAnswerChange={(qId, ans) =>
                            setMcqAnswers((prev) => ({ ...prev, [qId]: ans }))
                          }
                          onSubmit={async () => {
                            if (!versionId) return
                            const formatted: Record<string, string> = {}
                            Object.entries(mcqAnswers).forEach(([qId, ans]) => {
                              const q = mcqData.questions.find(
                                (x) => x.id === qId
                              )
                              formatted[`${qId}: ${q?.question || ""}`] =
                                ans.selected === "E"
                                  ? `E. Lainnya: ${ans.custom_text || ""}`
                                  : `${ans.selected}. ${q?.options.find((o) => o.key === ans.selected)?.text || ""}`
                            })
                            try {
                              await apiPatch(`/versions/${versionId}/answers`, {
                                answers: formatted,
                              })
                            } catch (e) {
                              setError(
                                e instanceof Error
                                  ? e.message
                                  : "Gagal menyimpan jawaban"
                              )
                              return
                            }
                            const nextStage = stages[current + 1]?.key
                            if (nextStage && versionId) {
                              setStatus((s) => ({
                                ...s,
                                [nextStage]: "running",
                              }))
                              setCurrent(current + 1)
                              startPipeline(versionId, nextStage)
                            }
                          }}
                          submitLabel="Kirim Jawaban & Lanjutkan"
                        />
                      ) : (
                        <>
                          <h4 className="font-semibold">
                            Jawab pertanyaan klarifikasi berikut:
                          </h4>
                          {questions.length === 0 && artifacts.pertanyaan && (
                            <div className="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                              <Loader2 size={14} className="animate-spin" />
                              Memproses pertanyaan
                              {retryInfo
                                ? ` (percobaan ${retryInfo.attempt}/${retryInfo.max})`
                                : "..."}
                            </div>
                          )}
                          {questions.map((q: string, i: number) => (
                            <div key={i}>
                              <Label>{q}</Label>
                              <textarea
                                rows={2}
                                value={answers[q] || ""}
                                onChange={(
                                  e: React.ChangeEvent<HTMLTextAreaElement>
                                ) =>
                                  setAnswers((prev) => ({
                                    ...prev,
                                    [q]: e.target.value,
                                  }))
                                }
                                className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                                placeholder="Tulis jawaban kamu..."
                              />
                            </div>
                          ))}
                          <Button
                            onClick={async () => {
                              if (!versionId) return
                              try {
                                await apiPatch(
                                  `/versions/${versionId}/answers`,
                                  { answers }
                                )
                              } catch (e) {
                                setError(
                                  e instanceof Error
                                    ? e.message
                                    : "Gagal menyimpan jawaban"
                                )
                                return
                              }
                              const nextStage = stages[current + 1]?.key
                              if (nextStage && versionId) {
                                setStatus((s) => ({
                                  ...s,
                                  [nextStage]: "running",
                                }))
                                setCurrent(current + 1)
                                startPipeline(versionId, nextStage)
                              }
                            }}
                            disabled={
                              !Object.values(answers).some((a) => a.trim())
                            }
                          >
                            <ArrowRight size={15} /> Kirim Jawaban & Lanjutkan
                          </Button>
                        </>
                      )}
                    </div>
                  ) : displayKey === "pertanyaan_mobile" &&
                    status.pertanyaan_mobile === "done" ? (
                    <div className="space-y-4">
                      {mcqMobileData ? (
                        <McqForm
                          mcqData={mcqMobileData}
                          answers={mobileMcqAnswers}
                          onAnswerChange={(qId, ans) =>
                            setMobileMcqAnswers((prev) => ({
                              ...prev,
                              [qId]: ans,
                            }))
                          }
                          onSubmit={async () => {
                            if (!versionId) return
                            const formatted: Record<string, string> = {}
                            Object.entries(mobileMcqAnswers).forEach(
                              ([qId, ans]) => {
                                const q = mcqMobileData.questions.find(
                                  (x) => x.id === qId
                                )
                                formatted[`${qId}: ${q?.question || ""}`] =
                                  ans.selected === "E"
                                    ? `E. Lainnya: ${ans.custom_text || ""}`
                                    : `${ans.selected}. ${q?.options.find((o) => o.key === ans.selected)?.text || ""}`
                              }
                            )
                            try {
                              await apiPatch(`/versions/${versionId}/answers`, {
                                mobile_answers: formatted,
                              })
                            } catch (e) {
                              setError(
                                e instanceof Error
                                  ? e.message
                                  : "Gagal menyimpan jawaban mobile"
                              )
                              return
                            }
                            const nextStage = stages[current + 1]?.key
                            if (nextStage && versionId) {
                              setStatus((s) => ({
                                ...s,
                                [nextStage]: "running",
                              }))
                              setCurrent(current + 1)
                              startPipeline(versionId, nextStage)
                            }
                          }}
                          submitLabel="Kirim Jawaban Mobile & Lanjutkan"
                          ambiguitiesLabel="Area mobile yang perlu diperjelas:"
                        />
                      ) : (
                        <>
                          <h4 className="font-semibold">
                            Jawab pertanyaan klarifikasi mobile berikut:
                          </h4>
                          {mobileQuestions.length === 0 &&
                            artifacts.pertanyaan_mobile && (
                              <div className="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                                <Loader2 size={14} className="animate-spin" />
                                Memproses pertanyaan mobile
                                {retryInfo
                                  ? ` (percobaan ${retryInfo.attempt}/${retryInfo.max})`
                                  : "..."}
                              </div>
                            )}
                          {mobileQuestions.map((q: string, i: number) => (
                            <div key={i}>
                              <Label>{q}</Label>
                              <textarea
                                rows={2}
                                value={mobileMcqAnswers[q]?.selected || ""}
                                onChange={(e) =>
                                  setMobileMcqAnswers((prev) => ({
                                    ...prev,
                                    [q]: { selected: e.target.value },
                                  }))
                                }
                                className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                                placeholder="Tulis jawaban kamu..."
                              />
                            </div>
                          ))}
                          <Button
                            onClick={async () => {
                              if (!versionId) return
                              const formatted: Record<string, string> = {}
                              Object.entries(mobileMcqAnswers).forEach(
                                ([q, a]) => {
                                  if (a?.selected) formatted[q] = a.selected
                                }
                              )
                              try {
                                await apiPatch(
                                  `/versions/${versionId}/answers`,
                                  { mobile_answers: formatted }
                                )
                              } catch (e) {
                                setError(
                                  e instanceof Error
                                    ? e.message
                                    : "Gagal menyimpan jawaban mobile"
                                )
                                return
                              }
                              const nextStage = stages[current + 1]?.key
                              if (nextStage && versionId) {
                                setStatus((s) => ({
                                  ...s,
                                  [nextStage]: "running",
                                }))
                                setCurrent(current + 1)
                                startPipeline(versionId, nextStage)
                              }
                            }}
                            disabled={
                              !Object.values(mobileMcqAnswers).some((a) =>
                                a?.selected?.trim()
                              )
                            }
                          >
                            <ArrowRight size={15} /> Kirim Jawaban Mobile &
                            Lanjutkan
                          </Button>
                        </>
                      )}
                    </div>
                  ) : (displayKey === "erd" && status.erd === "done") ||
                    (displayKey === "architecture" &&
                      status.architecture === "done") ||
                    (displayKey === "master_web" &&
                      status.master_web === "done") ? null : (
                    <>
                      {displayKey === "analisa" && artifacts.analisa && (
                        <AnalysisView markdown={artifacts.analisa} />
                      )}
                      {displayKey === "prd" && artifacts.prd && (
                        <PrdView markdown={artifacts.prd} />
                      )}
                      {displayKey === "standards_web" &&
                        artifacts.standards_web && (
                          <StandardsView markdown={artifacts.standards_web} />
                        )}
                      {displayKey === "standards_mobile" &&
                        artifacts.standards_mobile && (
                          <StandardsView
                            markdown={artifacts.standards_mobile}
                          />
                        )}
                      {displayKey === "agents" && artifacts.agents && (
                        <AgentsView markdown={artifacts.agents} />
                      )}
                      {displayKey === "design_system" &&
                        artifacts.design_system && (
                          <DesignSystemView
                            markdown={artifacts.design_system}
                          />
                        )}
                      {displayKey === "design_system_mobile" &&
                        artifacts.design_system_mobile && (
                          <DesignSystemMobileView
                            markdown={artifacts.design_system_mobile}
                          />
                        )}
                      {displayKey === "api_contract" &&
                        (artifacts.api_contract || apiContractDbItems) &&
                        (() => {
                          const parsed =
                            parseApiContractItems(artifacts.api_contract)
                              .length > 0
                              ? parseApiContractItems(artifacts.api_contract)
                              : (apiContractDbItems ?? [])
                          if (parsed.length === 0)
                            return (
                              <p className="text-sm text-[var(--color-fg-subtle)] italic">
                                API contract belum tersedia.
                              </p>
                            )
                          return <ApiContractTable items={parsed} />
                        })()}
                      {displayKey === "app_spec_web" &&
                        artifacts.app_spec_web && (
                          <AppSpecWebView data={artifacts.app_spec_web} />
                        )}
                      {displayKey === "app_spec_mobile" &&
                        artifacts.app_spec_mobile && (
                          <AppSpecMobileView data={artifacts.app_spec_mobile} />
                        )}
                      {!(
                        (displayKey === "analisa" && artifacts.analisa) ||
                        (displayKey === "prd" && artifacts.prd) ||
                        (displayKey === "standards_web" &&
                          artifacts.standards_web) ||
                        (displayKey === "standards_mobile" &&
                          artifacts.standards_mobile) ||
                        (displayKey === "agents" && artifacts.agents) ||
                        (displayKey === "design_system" &&
                          artifacts.design_system) ||
                        (displayKey === "design_system_mobile" &&
                          artifacts.design_system_mobile) ||
                        (displayKey === "api_contract" &&
                          artifacts.api_contract) ||
                        (displayKey === "app_spec_web" &&
                          artifacts.app_spec_web) ||
                        (displayKey === "app_spec_mobile" &&
                          artifacts.app_spec_mobile)
                      ) && (
                        <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                          {status[displayKey] === "done" &&
                          !artifacts[displayKey]
                            ? "Tidak ada output"
                            : artifacts[displayKey] || "Menunggu hasil AI..."}
                        </Markdown>
                      )}
                    </>
                  )}

                  {displayKey === "architecture" &&
                    artifacts.architecture &&
                    (() => {
                      const text = artifacts.architecture
                      const nodes: Array<{
                        id: string
                        label: string
                        fields: string[]
                      }> = []
                      const edges: Array<{
                        from: string
                        to: string
                        relation: string
                      }> = []

                      for (const line of text.split("\n")) {
                        const cm = line.match(
                          /^KOMPONEN:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i
                        )
                        if (cm) {
                          nodes.push({
                            id: cm[1].trim(),
                            label: cm[2].trim(),
                            fields: cm[3]
                              .split(",")
                              .map((f: string) => f.trim()),
                          })
                        }
                        const em = line.match(
                          /^KONEKSI:\s*(.+?)\s*->\s*(.+?)\s*\|\s*(.+)$/i
                        )
                        if (em) {
                          edges.push({
                            from: em[1].trim(),
                            to: em[2].trim(),
                            relation: em[3].trim(),
                          })
                        }
                      }
                      return (
                        <>
                          <ArchitectureView markdown={text} />
                          {nodes.length > 0 && (
                            <div className="mt-6">
                              <h4 className="mb-3 text-sm font-semibold">
                                Module Diagram
                              </h4>
                              <ErdDiagramDynamic erd={{ nodes, edges }} />
                            </div>
                          )}
                        </>
                      )
                    })()}

                  {displayKey === "erd" && (
                    <>
                      {status.erd === "running" && (
                        <div className="mt-4 text-sm whitespace-pre-wrap text-[var(--color-fg-muted)]">
                          {artifacts[displayKey] || "Menunggu hasil AI..."}
                        </div>
                      )}
                      {status.erd === "done" && !artifacts.erd && (
                        <p className="text-sm text-[var(--color-fg-subtle)] italic">
                          Artifact ERD belum termuat — muat ulang halaman atau
                          generate ulang.
                        </p>
                      )}
                      {status.erd === "done" &&
                        artifacts.erd &&
                        (() => {
                          const erdData = parseErdArtifact(artifacts.erd)
                          if (!erdData)
                            return (
                              <pre className="text-sm whitespace-pre-wrap">
                                {artifacts.erd}
                              </pre>
                            )
                          return (
                            <div className="mt-4">
                              <ErdTabs
                                erd={erdData}
                                apiContract={erdData.api_contract}
                              />
                            </div>
                          )
                        })()}
                    </>
                  )}
                  {displayKey === "phases_web" && artifacts.phases_web && (
                    <PhasesView
                      markdown={artifacts.phases_web}
                      label="Phase Breakdown Web"
                    />
                  )}
                  {displayKey === "phases_mobile" &&
                    artifacts.phases_mobile && (
                      <PhasesView
                        markdown={artifacts.phases_mobile}
                        label="Phase Breakdown Mobile"
                      />
                    )}
                  {(displayKey === "master_web" ||
                    displayKey === "master_mobile") &&
                    (() => {
                      const isWeb = displayKey === "master_web"
                      const artifact = isWeb
                        ? artifacts.master_web
                        : artifacts.master_mobile
                      const label = isWeb
                        ? "Master Prompt Web"
                        : "Master Prompt Mobile"
                      const target = isWeb ? "web" : "mobile"
                      if (!artifact || !hasMasterPromptArtifact(artifact))
                        return null
                      return (
                        <Card className="p-4">
                          <div className="mb-3 flex items-center justify-between">
                            <h3 className="font-semibold">{label}</h3>
                            <Button
                              variant="primary"
                              size="sm"
                              onClick={() => {
                                setMasterModalTarget(target as "web" | "mobile")
                                setMasterModalOpen(true)
                              }}
                              data-testid={`open-master-${target}`}
                            >
                              Buka Master Prompt
                            </Button>
                          </div>
                          <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                            {artifact.slice(0, 600) +
                              (artifact.length > 600 ? "…" : "")}
                          </Markdown>
                        </Card>
                      )
                    })()}
                </>
              )}
            </Card>

            {/* Checkpoint bar — hanya untuk stage aktif pipeline, tidak di mode review */}
            {!isViewing &&
              (status[activeKey] === "done" || status[activeKey] === "error") &&
              !allDone && (
                <Card className="flex items-center justify-between p-4">
                  <div className="flex items-center gap-3">
                    <span className="text-sm text-[var(--color-fg-muted)]">
                      {status[activeKey] === "error"
                        ? "Terjadi kesalahan pada tahap ini."
                        : "Tahap selesai. Lanjut ke berikutnya?"}
                    </span>
                    <button
                      type="button"
                      onClick={() => setAutoAdvance((v) => !v)}
                      className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-xs font-medium transition ${
                        autoAdvance
                          ? "bg-[var(--color-brand)] text-white"
                          : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
                      }`}
                      data-testid="auto-advance-toggle"
                      title="Lanjut otomatis antar tahap (kecuali klarifikasi yang butuh jawaban)"
                    >
                      <Sparkles size={12} /> Auto
                    </button>
                  </div>
                  <div className="flex gap-2">
                    {/* Coba Lagi: semua stage saat error (utk klarifikasi web/mobile), dan regenerate utk stage done non-klarifikasi. */}
                    {(status[activeKey] === "error" ||
                      (status[activeKey] === "done" &&
                        activeKey !== "pertanyaan" &&
                        activeKey !== "pertanyaan_mobile")) && (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={retryStage}
                      >
                        <RotateCcw size={15} /> Coba Lagi
                      </Button>
                    )}
                    {status[activeKey] === "done" &&
                      activeKey !== "pertanyaan" &&
                      activeKey !== "pertanyaan_mobile" && (
                        <Button
                          size="sm"
                          onClick={approveNext}
                          data-testid="approve-next"
                        >
                          Approve & Lanjut <ArrowRight size={15} />
                        </Button>
                      )}
                  </div>
                </Card>
              )}

            {allDone && (
              <>
                <Card className="flex flex-col items-center gap-3 p-6 text-center">
                  <span className="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-success)] text-white">
                    <Check size={24} />
                  </span>
                  <h3 className="text-lg font-semibold">Plan selesai! 🎉</h3>
                  <p className="text-sm text-[var(--color-fg-muted)]">
                    Semua artefak siap. Salin master prompt & mulai bangun
                    dengan AI agent.
                  </p>
                  <div className="flex flex-wrap justify-center gap-2">
                    <Button
                      variant="secondary"
                      onClick={() => {
                        if (artifacts.master_web)
                          copyToClipboard(artifacts.master_web)
                      }}
                    >
                      <Copy size={15} /> Salin Master Prompt (Web)
                    </Button>
                    {target === "both" && artifacts.master_mobile && (
                      <Button
                        variant="secondary"
                        onClick={() =>
                          copyToClipboard(artifacts.master_mobile as string)
                        }
                      >
                        <Copy size={15} /> Salin Master Prompt (Mobile)
                      </Button>
                    )}
                    {target === "both" && artifacts.agents && (
                      <Button
                        variant="secondary"
                        onClick={() =>
                          copyToClipboard(artifacts.agents as string)
                        }
                      >
                        <Copy size={15} /> Salin Agents
                      </Button>
                    )}
                    <Button
                      onClick={() =>
                        projectId && router.push(`/projects/${projectId}`)
                      }
                      data-testid="goto-project"
                    >
                      Buka Project <ArrowRight size={15} />
                    </Button>
                  </div>
                </Card>
              </>
            )}

            {error && (
              <div className="flex flex-wrap items-center gap-3 rounded-lg border border-red-500/50 bg-red-500/10 p-4 text-sm text-red-500">
                <AlertCircle size={18} />
                <div className="min-w-0 flex-1">
                  <div className="font-medium">Terjadi Kesalahan</div>
                  <div className="mt-1 text-xs opacity-90">{error}</div>
                </div>
                {failedStage && versionId && (
                  <Button
                    variant="secondary"
                    size="sm"
                    onClick={() => {
                      setFailedStage(null)
                      setError("")
                      startPipeline(versionId, failedStage)
                    }}
                    data-testid="retry-stage"
                  >
                    <Play size={14} /> Coba lagi dengan perbaikan
                  </Button>
                )}
              </div>
            )}
          </div>

          {/* Tracking side panel — selalu tampil di stage master (web/mobile) agar user melihat progres
            komunikasi agent ↔ webhook secara live; untuk agents hanya bila ada fase. */}
          <div
            className={
              showTrackingPanel
                ? ""
                : "pointer-events-none invisible hidden lg:block"
            }
          >
            {showTrackingPanel &&
              (activeKey === "master_web" ||
                activeKey === "master_mobile" ||
                trackingPhases.length > 0) && (
                <TrackingPanel
                  phases={trackingPhases}
                  progMap={progMap}
                  webhookUrl={WEBHOOK_URL}
                  projectId={projectId}
                  versionId={versionId}
                />
              )}
          </div>
        </div>

        {/* Full-screen Build Wall untuk master_* + agents */}
        <BuildWall
          open={
            status[activeKey] === "running" &&
            ["master_web", "master_mobile", "agents"].includes(activeKey)
          }
          stageLabel={`Tahap ${current + 1}/${stages.length}: ${stages[current]?.label ?? ""}`}
          content={artifacts[activeKey] ?? ""}
          isRunning={status[activeKey] === "running"}
          onClose={() => {
            if (status[activeKey] !== "running")
              setCurrent(Math.min(current + 1, stages.length - 1))
          }}
          throughput={{
            startedAt: status[activeKey] === "running" ? startedAt : null,
            bytes: artifacts[activeKey]?.length ?? 0,
          }}
          sidebar={
            <div className="space-y-3 text-xs">
              <div>
                <div className="text-[10px] tracking-wider text-[var(--color-fg-subtle)] uppercase">
                  Deskripsi
                </div>
                <div className="mt-1 text-[var(--color-fg)]">
                  {stages[current]?.desc}
                </div>
              </div>
              {retryInfo && (
                <div className="flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 font-medium text-amber-600">
                  <Loader2 size={12} className="animate-spin" />
                  Percobaan {retryInfo.attempt}/{retryInfo.max}
                </div>
              )}
              <Button
                variant="secondary"
                size="sm"
                onClick={() => setShowCancelConfirm(true)}
              >
                <AlertCircle size={14} /> Batalkan
              </Button>
            </div>
          }
        />

        {/* Loading modal untuk stage biasa (non-master) */}
        <Modal
          open={
            status[activeKey] === "running" &&
            !["master_web", "master_mobile", "agents"].includes(activeKey)
          }
          onClose={() => {}}
          title={`Tahap ${current + 1}/${stages.length}: ${stages[current]?.label ?? ""}`}
          size="lg"
          closeOnBackdrop={false}
        >
          {/* Header */}
          <div className="mb-4 flex items-center gap-3">
            <Loader2
              size={24}
              className="animate-spin text-[var(--color-brand)]"
            />
            <div className="flex-1">
              <div className="text-sm text-[var(--color-fg-muted)]">
                {stages[current]?.desc ?? ""}
              </div>
              {retryInfo && (
                <div className="flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-600">
                  <Loader2 size={12} className="animate-spin" />
                  Percobaan ulang {retryInfo.attempt}
                  {retryInfo.max ? `/${retryInfo.max}` : ""} — mencari minimal 5
                  pertanyaan
                </div>
              )}
            </div>
          </div>

          {/* Live output */}
          <div className="space-y-2">
            <StageThroughputBar
              startedAt={status[activeKey] === "running" ? startedAt : null}
              bytes={artifacts[activeKey]?.length ?? 0}
            />
            <div
              ref={outputRef}
              className="max-h-80 min-h-[80px] overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)]"
            >
              <StreamingMarkdown
                content={artifacts[activeKey] || "Menunggu hasil AI..."}
                live={status[activeKey] === "running"}
                className="border-0 bg-transparent"
              />
            </div>
          </div>

          {/* Progress */}
          <div className="mt-4">
            <div className="mb-1 flex items-center justify-between text-xs text-[var(--color-fg-muted)]">
              <span>Progress</span>
              <span>
                {
                  stages.filter(
                    (s) =>
                      status[s.key] === "done" || status[s.key] === "running"
                  ).length
                }
                /{stages.length} tahap
              </span>
            </div>
            <div className="h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
              <div
                className="h-full rounded-full bg-[var(--color-brand)] transition-all duration-500"
                style={{
                  width: `${(stages.filter((s) => status[s.key] === "done").length / stages.length) * 100}%`,
                }}
              />
            </div>
          </div>

          {/* Cancel */}
          <div className="mt-4 flex justify-end">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setShowCancelConfirm(true)}
            >
              <AlertCircle size={15} /> Batalkan
            </Button>
          </div>
        </Modal>

        {/* Konfirmasi lanjut setelah master_web — tracking fase web belum selesai */}
        <Modal
          open={pendingConfirmMaster}
          onClose={() => setPendingConfirmMaster(false)}
          title="Tracking fase web belum selesai"
          size="sm"
        >
          <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
            Ada fase web yang masih berjalan / belum ditandai selesai. Web
            kemungkinan belum selesai dibangun. Yakin melanjutkan ke tahap
            berikutnya?
          </p>
          <div className="mt-5 flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setPendingConfirmMaster(false)}
            >
              Batal
            </Button>
            <Button size="sm" onClick={proceedAfterMasterConfirm}>
              Tetap Lanjut <ArrowRight size={15} />
            </Button>
          </div>
        </Modal>

        {/* CP-9 M-5: Master Prompt showcase modal */}
        <Modal
          open={masterModalOpen}
          onClose={() => setMasterModalOpen(false)}
          title={
            masterModalTarget === "mobile"
              ? "Master Prompt — Mobile"
              : "Master Prompt — Web"
          }
          size="xl"
        >
          {masterModalTarget &&
            projectId &&
            versionId &&
            (() => {
              const artifact =
                masterModalTarget === "web"
                  ? artifacts.master_web
                  : artifacts.master_mobile
              if (!artifact)
                return (
                  <p className="text-sm text-[var(--color-fg-muted)]">
                    Master prompt belum tersedia.
                  </p>
                )
              return (
                <MasterPromptViewer
                  projectId={projectId}
                  versionId={versionId}
                  versionLabel={masterModalTarget === "web" ? "Web" : "Mobile"}
                  stage={
                    masterModalTarget === "web" ? "master_web" : "master_mobile"
                  }
                  artifact={artifact}
                />
              )
            })()}
        </Modal>

        {/* Konfirmasi batalkan */}
        <Modal
          open={showCancelConfirm}
          onClose={() => setShowCancelConfirm(false)}
          title="Batalkan pembuatan?"
          size="sm"
        >
          <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
            Proses akan dihentikan dan stage saat ini ditandai error. Yakin
            membatalkan?
          </p>
          <div className="mt-5 flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setShowCancelConfirm(false)}
            >
              Lanjutkan
            </Button>
            <Button variant="danger" size="sm" onClick={cancelGeneration}>
              <AlertCircle size={15} /> Ya, Batalkan
            </Button>
          </div>
        </Modal>

        {/* Konfirmasi mulai ulang — menghapus project permanen */}
        <Modal
          open={regenFromStage !== null}
          onClose={() => setRegenFromStage(null)}
          title="Generate Ulang Stage"
          size="sm"
        >
          <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
            Stage{" "}
            <strong>
              {stages.find((s) => s.key === regenFromStage)?.label}
            </strong>{" "}
            dan <strong>semua stage setelahnya</strong> akan di-generate ulang.
            Jika pipeline sedang berjalan, proses akan dihentikan terlebih
            dahulu. Proses bisa memakan waktu 1-3 menit — jangan tutup halaman.
          </p>
          <div className="mt-5 flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setRegenFromStage(null)}
            >
              Batal
            </Button>
            <Button
              variant="primary"
              size="sm"
              onClick={handleRegenFromStage}
              disabled={regenBusy}
            >
              {regenBusy ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <RotateCcw size={14} />
              )}
              Ya, Generate Ulang
            </Button>
          </div>
        </Modal>

        <Modal
          open={showResetConfirm}
          onClose={() => setShowResetConfirm(false)}
          title="Mulai Ulang?"
          size="sm"
        >
          <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
            Mulai ulang akan{" "}
            <strong className="text-[var(--color-danger)]">
              menghapus project ini beserta semua versi dan artefak secara
              permanen
            </strong>
            . Tindakan ini tidak bisa dibatalkan. Yakin melanjutkan?
          </p>
          <div className="mt-5 flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setShowResetConfirm(false)}
            >
              Batal
            </Button>
            <Button variant="danger" size="sm" onClick={reset}>
              <AlertCircle size={15} /> Ya, Mulai Ulang
            </Button>
          </div>
        </Modal>
      </div>
    </ErrorBoundary>
  )
}
