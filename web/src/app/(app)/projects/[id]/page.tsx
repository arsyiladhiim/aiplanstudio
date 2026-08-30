"use client"
import { useRouter, useSearchParams } from "next/navigation"
import { useState, useEffect, useRef } from "react"
import { notFound } from "next/navigation"
import { use } from "react"
import dynamic from "next/dynamic"
import { Card, Badge, Markdown, Modal } from "@/components/ui"
import { ConfirmDialog } from "@/components/ui/ConfirmDialog"
import { Button, ButtonLink } from "@/components/ui/Button"
import { TargetBadge } from "@/components/common"
import { ApiTokenSection } from "@/components/project/ApiTokenSection"
import { ErrorBoundary } from "@/components/ErrorBoundary"
import {
  getStages,
  getStageGroups,
  type StageKey,
  type Target,
} from "@/lib/mock"
import {
  API_BASE_URL,
  apiGet,
  apiPost,
  apiDelete,
  apiPatch,
  createPhaseProgressStream,
  WEBHOOK_URL,
  type Project,
  type Version,
  type Activity,
} from "@/lib/api"
import { copyToClipboard } from "@/lib/clipboard"
import {
  TrackingPanel,
  type ProgressItem,
} from "@/components/wizard/TrackingPanel"
import { AgentEventFeed } from "@/components/wizard/AgentEventFeed"
import type { PhaseItem } from "@/components/wizard/PhaseBreakdownCard"
import { ApiContractTable } from "@/components/wizard/ApiContractTable"
import { DesignSystemView } from "@/components/wizard/DesignSystemView"
import { DesignSystemMobileView } from "@/components/wizard/DesignSystemMobileView"
import { AppSpecWebView } from "@/components/wizard/AppSpecWebView"
import { AppSpecMobileView } from "@/components/wizard/AppSpecMobileView"
import { StageRow, type StageStatus } from "@/components/wizard/StageRow"
import { EmptyArtifact } from "@/components/wizard/EmptyArtifact"

type AppSpecWebLike = Parameters<typeof AppSpecWebView>[0]["data"]
type AppSpecMobileLike = Parameters<typeof AppSpecMobileView>[0]["data"]
type ApiContractLike = Parameters<typeof ApiContractTable>[0]["items"]
import {
  ArrowLeft,
  GitBranch,
  Download,
  Plus,
  Copy,
  ListChecks,
  Check,
  Loader2,
  Play,
  Trash2,
  GitCompareArrows,
  Smartphone,
  Pencil,
  X,
  History,
  Heart,
  RotateCcw,
  LayoutDashboard,
  Globe,
  Archive,
  BarChart3,
} from "lucide-react"

const ErdDiagram = dynamic(
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

const TABS = [
  { key: "overview", label: "Overview", icon: LayoutDashboard },
  { key: "web", label: "Web", icon: Globe },
  { key: "mobile", label: "Mobile", icon: Smartphone },
  { key: "tracking", label: "Tracking", icon: ListChecks },
  { key: "activities", label: "Aktivitas", icon: History },
] as const

type TabKey = (typeof TABS)[number]["key"]

export default function ProjectDetail({
  params,
}: {
  params: Promise<{ id: string }>
}) {
  const { id } = use(params)
  const router = useRouter()
  const searchParams = useSearchParams()
  const initialTab = (searchParams.get("tab") as TabKey) || "overview"
  const [project, setProject] = useState<
    (Project & { versions?: Version[] }) | null
  >(null)
  const [selectedVersion, setSelectedVersion] = useState<Version | null>(null)
  const [showQuality, setShowQuality] = useState(false)
  const [loading, setLoading] = useState(true)
  const [versionLoading, setVersionLoading] = useState(false)
  const [error, setError] = useState("")
  const [tab, setTab] = useState<TabKey>(initialTab)
  const [creatingVersion, setCreatingVersion] = useState(false)
  const [showVersionDialog, setShowVersionDialog] = useState(false)
  const [versionStrategy, setVersionStrategy] = useState<"from_last" | "blank">(
    "from_last"
  )
  const [baselineNotes, setBaselineNotes] = useState("")
  const [diffMode, setDiffMode] = useState(false)
  const [diffVersionId, setDiffVersionId] = useState<number | null>(null)
  const [editingProject, setEditingProject] = useState(false)
  const [editTitle, setEditTitle] = useState("")
  const [editIdea, setEditIdea] = useState("")
  const [editTarget, setEditTarget] = useState<Target>("web")
  const [savingProject, setSavingProject] = useState(false)
  const [regeneratingWeb, setRegeneratingWeb] = useState(false)
  const [regeneratingMobile, setRegeneratingMobile] = useState(false)
  const [regeneratingStage, setRegeneratingStage] = useState<StageKey | null>(
    null
  )
  const [restartingAnalisa, setRestartingAnalisa] = useState(false)

  // Fetch project with versions
  useEffect(() => {
    apiGet<Project & { versions: Version[] }>(`/projects/${id}`)
      .then((data) => {
        setProject(data)
        // Auto-select latest version
        if (data.versions && data.versions.length > 0) {
          const latest = data.versions[0] // backend returns latest() first
          fetchVersion(latest.id)
        }
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : "Gagal memuat project")
      })
      .finally(() => setLoading(false))
  }, [id])

  // Silent auto-refresh for real-time progress via SSE (no loading flash)
  const [lastRefreshed, setLastRefreshed] = useState<Date | null>(null)
  const [countdown, setCountdown] = useState(0)
  const allDoneRef = useRef(false)
  const stages = getStages(selectedVersion?.project?.target ?? "web")
  const stageStatus = (selectedVersion?.stage_status ?? {}) as Record<
    string,
    string
  >
  const totalStages = stages.length
  const doneStages = stages.filter((s) => stageStatus[s.key] === "done").length

  useEffect(() => {
    if (!selectedVersion?.id) return
    const status = selectedVersion.stage_status ?? {}
    allDoneRef.current = stages.every((s) => status[s.key] === "done")
    if (allDoneRef.current) return

    const pp = createPhaseProgressStream(
      `/versions/${selectedVersion.id}/phase-progress/stream`,
      (event) => {
        if (event === "phase_progress" || event === "done") {
          apiGet<Version>(`/versions/${selectedVersion.id}`)
            .then((v) => {
              const newStages = getStages(v.project?.target ?? "web")
              allDoneRef.current = newStages.every(
                (s) =>
                  (v.stage_status as Record<string, string>)?.[s.key] === "done"
              )
              setSelectedVersion(v)
              setLastRefreshed(new Date())
              setCountdown(0)
            })
            .catch(() => {})
        }
      }
    )
    return () => pp.abort()
    // stages is derived from selectedVersion; intentionally keyed on version id only
    // so SSE doesn't re-spawn on every render.
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [selectedVersion?.id])

  // Update countdown every second for the timer display
  useEffect(() => {
    if (!lastRefreshed) return
    const tick = setInterval(() => setCountdown((c) => c + 1), 1000)
    return () => clearInterval(tick)
  }, [lastRefreshed])

  const [activities, setActivities] = useState<Activity[]>([])
  const activitiesLoading = tab === "activities" && activities.length === 0
  const [confirmDeleteProject, setConfirmDeleteProject] = useState(false)
  const [confirmDeleteVersionId, setConfirmDeleteVersionId] = useState<
    number | null
  >(null)

  useEffect(() => {
    if (tab !== "activities" || activities.length > 0) return
    apiGet<{ data: Activity[] }>(`/projects/${id}/activities`)
      .then((res) => setActivities(res.data))
      .catch((err) => console.error("Failed to load activities:", err))
  }, [tab, id, activities.length])

  function fetchVersion(versionId: number) {
    setVersionLoading(true)
    apiGet<Version>(`/versions/${versionId}`)
      .then(setSelectedVersion)
      .catch((err) =>
        setError(err instanceof Error ? err.message : "Gagal memuat version")
      )
      .finally(() => setVersionLoading(false))
  }

  async function handleDelete() {
    setConfirmDeleteProject(false)
    try {
      await apiDelete(`/projects/${id}`)
      router.push("/projects")
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal menghapus project")
    }
  }

  async function handleCreateVersion() {
    if (!project || creatingVersion || !showVersionDialog) return
    setCreatingVersion(true)
    try {
      const body: Record<string, unknown> = { strategy: versionStrategy }
      if (versionStrategy === "from_last" && baselineNotes.trim()) {
        body.baseline_notes = baselineNotes.trim()
      }
      const newVersion = await apiPost<Version>(
        `/projects/${project.id}/versions`,
        body
      )
      // Refresh project to get updated versions list
      const updated = await apiGet<Project & { versions: Version[] }>(
        `/projects/${id}`
      )
      setProject(updated)
      setShowVersionDialog(false)
      setBaselineNotes("")
      fetchVersion(newVersion.id)
    } catch (err) {
      setError(
        err instanceof Error ? err.message : "Gagal membuat version baru"
      )
    } finally {
      setCreatingVersion(false)
    }
  }

  async function handleDeleteVersion(versionId: number) {
    try {
      await apiDelete(`/versions/${versionId}`)
      if (project) {
        const updated = await apiGet<Project & { versions: Version[] }>(
          `/projects/${id}`
        )
        setProject(updated)
        if (updated.versions && updated.versions.length > 0) {
          fetchVersion(updated.versions[0].id)
        } else {
          setSelectedVersion(null)
        }
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal menghapus versi")
    } finally {
      setConfirmDeleteVersionId(null)
    }
  }

  async function handleTogglePhase(phaseKey: string, currentDone: boolean) {
    if (!selectedVersion) return
    try {
      await apiPatch(`/versions/${selectedVersion.id}/phases/${phaseKey}`, {
        done: !currentDone,
      })
      // Refresh version to get updated phase progress
      fetchVersion(selectedVersion.id)
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal toggle phase")
    }
  }

  function handleExport(format: "md" | "zip") {
    if (!selectedVersion) return
    window.open(
      `${API_BASE_URL}/api/versions/${selectedVersion.id}/export?format=${format}`,
      "_blank"
    )
  }

  function handleExportAll() {
    if (!project) return
    window.open(
      `${API_BASE_URL}/api/projects/${project.id}/export-all`,
      "_blank"
    )
  }

  if (loading) {
    return (
      <div className="py-12 text-center">
        <Loader2 className="inline animate-spin" /> Memuat project...
      </div>
    )
  }

  if (error && !project) {
    return (
      <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
        {error}
      </div>
    )
  }

  if (!project) return notFound()

  const versions = project.versions || []
  const phases =
    (selectedVersion?.phases as Array<{
      key: string
      title: string
      tasks?: string[]
      prompt?: string
    }>) || []
  const mobilePhases =
    (selectedVersion?.mobile_phases as Array<{
      key: string
      title: string
      tasks?: string[]
      prompt?: string
    }>) || []
  const phaseProgress =
    (selectedVersion?.phase_progress as Array<{
      phase_key: string
      done: boolean
    }>) || []
  const doneMap = Object.fromEntries(
    phaseProgress.map((p) => [p.phase_key, p.done])
  )
  const doneCount = phaseProgress.filter((p) => p.done).length
  const progress =
    phases.length > 0 ? Math.round((doneCount / phases.length) * 100) : 0
  const mobileDoneCount = phaseProgress.filter(
    (p) => mobilePhases.some((mp) => mp.key === p.phase_key) && p.done
  ).length
  const mobileProgress =
    mobilePhases.length > 0
      ? Math.round((mobileDoneCount / mobilePhases.length) * 100)
      : 0

  return (
    <ErrorBoundary>
      <ButtonLink href="/projects" variant="ghost" size="sm" className="mb-4">
        <ArrowLeft size={16} /> Projects
      </ButtonLink>

      {error && (
        <div className="mb-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">
              {project.title}
            </h1>
            <TargetBadge target={project.target} />
          </div>
          <p className="mt-1.5 max-w-2xl text-sm text-[var(--color-fg-muted)]">
            {project.idea}
          </p>
          <button
            onClick={() => {
              setEditTitle(project.title)
              setEditIdea(project.idea)
              setEditTarget(project.target as Target)
              setEditingProject(true)
            }}
            className="mt-2 inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-[var(--color-brand)]"
          >
            <Pencil size={12} /> Edit project
          </button>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <button
            onClick={async () => {
              const prevFav = project?.is_favorite
              setProject((prev) =>
                prev ? { ...prev, is_favorite: !prevFav } : null
              )
              try {
                const res = await apiPatch<{ is_favorite: boolean }>(
                  `/projects/${id}/favorite`
                )
                setProject((prev) =>
                  prev ? { ...prev, is_favorite: res.is_favorite } : null
                )
              } catch (err) {
                setProject((prev) =>
                  prev ? { ...prev, is_favorite: prevFav } : null
                )
                setError(
                  err instanceof Error ? err.message : "Gagal mengubah favorit"
                )
              }
            }}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition ${
              project.is_favorite
                ? "text-red-500"
                : "text-[var(--color-fg-muted)] hover:text-red-400"
            }`}
            title={
              project.is_favorite
                ? "Hapus dari favorit"
                : "Tandai sebagai favorit"
            }
          >
            <Heart
              size={16}
              fill={project.is_favorite ? "currentColor" : "none"}
            />
          </button>
          <button
            onClick={async () => {
              const prevArchive = project?.archived_at
              setProject((prev) =>
                prev
                  ? {
                      ...prev,
                      archived_at: prevArchive
                        ? null
                        : new Date().toISOString(),
                    }
                  : null
              )
              try {
                const res = await apiPatch<{ archived_at: string | null }>(
                  `/projects/${id}/archive`
                )
                setProject((prev) =>
                  prev ? { ...prev, archived_at: res.archived_at } : null
                )
              } catch (err) {
                setProject((prev) =>
                  prev ? { ...prev, archived_at: prevArchive } : null
                )
                setError(
                  err instanceof Error ? err.message : "Gagal mengubah arsip"
                )
              }
            }}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition ${
              project.archived_at
                ? "text-amber-500"
                : "text-[var(--color-fg-muted)] hover:text-amber-400"
            }`}
            title={project.archived_at ? "Batal arsip" : "Arsipkan"}
            data-testid="archive-toggle"
          >
            <Archive
              size={16}
              fill={project.archived_at ? "currentColor" : "none"}
            />
          </button>
          {/* Export dropdown: current version MD / ZIP / all-versions ZIP */}
          <details className="relative inline-block">
            <summary className="flex cursor-pointer list-none items-center gap-1.5 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-1.5 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">
              <Download size={15} /> Export
            </summary>
            <div className="absolute right-0 z-20 mt-1 w-52 overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg">
              <button
                onClick={() => handleExport("md")}
                disabled={!selectedVersion}
                className="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--color-surface-2)] disabled:opacity-40"
              >
                Markdown (versi ini)
              </button>
              <button
                onClick={() => handleExport("zip")}
                disabled={!selectedVersion}
                className="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--color-surface-2)] disabled:opacity-40"
              >
                ZIP (versi ini)
              </button>
              <button
                onClick={handleExportAll}
                disabled={!project}
                className="block w-full px-3 py-2 text-left text-sm hover:bg-[var(--color-surface-2)] disabled:opacity-40"
              >
                ZIP (semua versi)
              </button>
            </div>
          </details>
          <ButtonLink
            variant="secondary"
            size="sm"
            href={`/projects/${id}/tasks`}
          >
            <ListChecks size={15} /> Tasks
          </ButtonLink>
          <Button size="sm" onClick={() => setShowVersionDialog(true)}>
            {creatingVersion ? (
              <Loader2 size={15} className="animate-spin" />
            ) : (
              <Plus size={15} />
            )}{" "}
            Versi Baru
          </Button>
          <Button
            variant="secondary"
            size="sm"
            onClick={() => setConfirmDeleteProject(true)}
          >
            <Trash2 size={15} /> Hapus
          </Button>
        </div>
      </div>

      {/* Version selector */}
      <div className="mt-5 flex flex-wrap items-center gap-2">
        <span className="text-sm text-[var(--color-fg-muted)]">Versi:</span>
        {versions.length === 0 && (
          <span className="text-sm text-[var(--color-fg-subtle)]">
            Belum ada versi
          </span>
        )}
        {versions.map((v) => (
          <div key={v.id} className="inline-flex items-center gap-0.5">
            <button
              onClick={() => {
                if (diffMode) {
                  setDiffVersionId(v.id)
                  return
                }
                fetchVersion(v.id)
              }}
              data-testid={`version-${v.version_no}`}
              disabled={versionLoading}
              className={`inline-flex items-center gap-1.5 rounded-l-full px-3 py-1.5 text-sm font-medium transition ${
                diffMode && diffVersionId === v.id
                  ? "bg-[var(--color-warning)]/10 text-[var(--color-warning)] ring-2 ring-[var(--color-warning)]"
                  : selectedVersion?.id === v.id
                    ? "bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-brand)]"
                    : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
              }`}
            >
              <GitBranch size={13} /> v{v.version_no}
            </button>
            {versions.length > 1 && (
              <button
                onClick={(e) => {
                  e.stopPropagation()
                  setConfirmDeleteVersionId(v.id)
                }}
                className={`rounded-r-full px-1.5 py-1.5 text-xs transition ${
                  selectedVersion?.id === v.id
                    ? "bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-fg-muted)] hover:text-red-500"
                    : "bg-[var(--color-surface-2)] text-[var(--color-fg-subtle)] hover:text-red-500"
                }`}
                title="Hapus versi"
              >
                <X size={12} />
              </button>
            )}
          </div>
        ))}
        {versions.length >= 2 && (
          <button
            onClick={() => {
              if (diffMode && diffVersionId && selectedVersion) {
                router.push(
                  `/projects/${id}/diff?current=${selectedVersion.id}&compare=${diffVersionId}`
                )
              } else {
                setDiffMode(!diffMode)
                setDiffVersionId(null)
              }
            }}
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition ${
              diffMode
                ? "bg-[var(--color-warning)]/15 text-[var(--color-warning)]"
                : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            }`}
          >
            <GitCompareArrows size={13} />
            {diffMode
              ? diffVersionId
                ? "Bandingkan"
                : "Pilih versi pembanding"
              : "Diff"}
          </button>
        )}
      </div>
      {diffMode && diffVersionId && selectedVersion && (
        <p className="mt-2 text-xs text-[var(--color-fg-muted)]">
          Membandingkan v{selectedVersion.version_no} dengan v
          {versions.find((v) => v.id === diffVersionId)?.version_no}. Klik
          tombol &ldquo;Bandingkan&rdquo; untuk melihat hasil.
        </p>
      )}

      {/* API Tokens */}
      <ApiTokenSection projectId={id} />

      {versionLoading && (
        <div className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">
          Memuat version...
        </div>
      )}

      {!versionLoading && selectedVersion && (
        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
          {/* Artifacts */}
          <Card className="overflow-hidden p-0">
            <div
              className="flex border-b border-[var(--color-border)]"
              role="tablist"
              aria-label="Artefak versi"
            >
              {TABS.filter(
                (t) => t.key !== "mobile" || project?.target === "both"
              ).map((t) => (
                <button
                  key={t.key}
                  onClick={() => setTab(t.key)}
                  data-testid={`tab-${t.key}`}
                  role="tab"
                  aria-selected={tab === t.key}
                  className={`flex items-center gap-2 px-5 py-3 text-sm font-medium transition ${
                    tab === t.key
                      ? "border-b-2 border-[var(--color-brand)] text-[var(--color-fg)]"
                      : "text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
                  }`}
                >
                  <t.icon size={16} /> {t.label}
                </button>
              ))}
              <button
                onClick={() => {
                  const content =
                    tab === "overview"
                      ? JSON.stringify(selectedVersion, null, 2)
                      : tab === "web"
                        ? (selectedVersion.master_prompt ?? "")
                        : tab === "mobile"
                          ? (selectedVersion.mobile_master_prompt ?? "")
                          : tab === "tracking"
                            ? JSON.stringify(
                                selectedVersion.phase_progress,
                                null,
                                2
                              )
                            : ""
                  copyToClipboard(content)
                }}
                className="my-auto mr-3 ml-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
              >
                <Copy size={13} /> Salin
              </button>
            </div>

            <div className="max-h-[600px] overflow-auto p-5" role="tabpanel">
              {tab === "overview" && (
                <div className="space-y-4">
                  <div className="grid gap-4 sm:grid-cols-2">
                    <div className="rounded-lg border border-[var(--color-border)] p-4">
                      <h4 className="mb-2 text-sm font-semibold">
                        Info Proyek
                      </h4>
                      <dl className="space-y-1 text-sm text-[var(--color-fg-muted)]">
                        <dt className="inline font-medium text-[var(--color-fg)]">
                          Target:{" "}
                        </dt>
                        <dd className="inline">
                          {project?.target === "both" ? "Web + Mobile" : "Web"}
                        </dd>
                        <br />
                        <dt className="inline font-medium text-[var(--color-fg)]">
                          Versi:{" "}
                        </dt>
                        <dd className="inline">
                          v{selectedVersion.version_no}
                        </dd>
                        <br />
                        <dt className="inline font-medium text-[var(--color-fg)]">
                          Pipeline:{" "}
                        </dt>
                        <dd className="inline">
                          {doneStages}/{totalStages} tahap
                        </dd>
                      </dl>
                    </div>
                    <div className="rounded-lg border border-[var(--color-border)] p-4">
                      <h4 className="mb-2 text-sm font-semibold">
                        Klarifikasi
                      </h4>
                      {!selectedVersion.answers ||
                      Object.keys(selectedVersion.answers).length === 0 ? (
                        <p className="text-xs text-[var(--color-fg-subtle)]">
                          Belum ada data klarifikasi.
                        </p>
                      ) : (
                        <div className="space-y-2">
                          {Object.entries(
                            selectedVersion.answers as Record<string, string>
                          )
                            .slice(0, 4)
                            .map(([q, a]) => (
                              <div key={q}>
                                <p className="text-xs font-medium">{q}</p>
                                <p className="text-xs text-[var(--color-fg-muted)]">
                                  {a.length > 80 ? a.slice(0, 80) + "…" : a}
                                </p>
                              </div>
                            ))}
                        </div>
                      )}
                    </div>
                  </div>
                  <div className="rounded-lg border border-[var(--color-border)] p-4">
                    <h4 className="mb-2 text-sm font-semibold">Ide</h4>
                    <p className="text-sm text-[var(--color-fg-muted)]">
                      {project?.idea ?? "—"}
                    </p>
                  </div>
                </div>
              )}
              {tab === "web" && (
                <div className="space-y-4">
                  {selectedVersion.analysis && (
                    <div>
                      <h4 className="mb-2 font-semibold">Analisa</h4>
                      <Markdown className="text-sm text-[var(--color-fg-muted)]">
                        {selectedVersion.analysis}
                      </Markdown>
                    </div>
                  )}
                  {selectedVersion.prd && (
                    <div>
                      <h4 className="mb-2 font-semibold">PRD</h4>
                      <Markdown className="text-sm text-[var(--color-fg-muted)]">
                        {selectedVersion.prd}
                      </Markdown>
                    </div>
                  )}
                  {selectedVersion.architecture && (
                    <div>
                      <h4 className="mb-2 font-semibold">Arsitektur</h4>
                      <Markdown className="text-sm text-[var(--color-fg-muted)]">
                        {selectedVersion.architecture}
                      </Markdown>
                    </div>
                  )}
                  {selectedVersion.erd && (
                    <div>
                      <h4 className="mb-2 font-semibold">ERD</h4>
                      <ErdDiagram erd={selectedVersion.erd} />
                    </div>
                  )}
                  {selectedVersion.api_contract &&
                    (selectedVersion.api_contract as unknown[]).length > 0 && (
                      <div>
                        <h4 className="mb-2 font-semibold">API Contract</h4>
                        <ApiContractTable
                          items={
                            selectedVersion.api_contract as ApiContractLike
                          }
                        />
                      </div>
                    )}
                  {selectedVersion.design_system && (
                    <div>
                      <h4 className="mb-2 font-semibold">Design System</h4>
                      <DesignSystemView
                        markdown={selectedVersion.design_system}
                      />
                    </div>
                  )}
                  {Boolean(selectedVersion.app_spec_web) && (
                    <div>
                      <h4 className="mb-2 font-semibold">App Spec — Web</h4>
                      <AppSpecWebView
                        data={selectedVersion.app_spec_web as AppSpecWebLike}
                      />
                    </div>
                  )}
                  {selectedVersion.standards && (
                    <div>
                      <h4 className="mb-2 font-semibold">Standards Web</h4>
                      <Markdown className="text-sm text-[var(--color-fg-muted)]">
                        {selectedVersion.standards}
                      </Markdown>
                    </div>
                  )}
                  {!selectedVersion.design_system && (
                    <EmptyArtifact
                      title="Design System"
                      description="Token warna, font, signature element, dan anti-pattern untuk UI web."
                      stageKey="design_system"
                      versionId={selectedVersion.id}
                      canGenerate={stageStatus.architecture === "done"}
                      blockReason="Selesaikan stage Arsitektur dulu"
                      onGenerate={async () => {
                        setRegeneratingStage("design_system")
                        try {
                          await apiPost(
                            `/versions/${selectedVersion.id}/regenerate`,
                            { stage: "design_system" }
                          )
                          fetchVersion(selectedVersion.id)
                        } catch (err) {
                          setError(
                            err instanceof Error
                              ? err.message
                              : "Gagal regenerate design system"
                          )
                        } finally {
                          setRegeneratingStage(null)
                        }
                      }}
                    />
                  )}
                  {!selectedVersion.app_spec_web && (
                    <EmptyArtifact
                      title="App Spec — Web"
                      description="Registry halaman, navigation, flows, dan components (JSON)."
                      stageKey="app_spec_web"
                      versionId={selectedVersion.id}
                      canGenerate={stageStatus.master_web === "done"}
                      blockReason="Selesaikan Master Prompt Web dulu"
                      onGenerate={async () => {
                        setRegeneratingStage("app_spec_web")
                        try {
                          await apiPost(
                            `/versions/${selectedVersion.id}/regenerate`,
                            { stage: "app_spec_web" }
                          )
                          fetchVersion(selectedVersion.id)
                        } catch (err) {
                          setError(
                            err instanceof Error
                              ? err.message
                              : "Gagal regenerate app spec web"
                          )
                        } finally {
                          setRegeneratingStage(null)
                        }
                      }}
                    />
                  )}
                  {phases.length > 0 && (
                    <div>
                      <h4 className="mb-3 font-semibold">Phases</h4>
                      <div className="space-y-3">
                        {phases.map((ph) => (
                          <div
                            key={ph.key}
                            className="rounded-xl border border-[var(--color-border)] p-4"
                          >
                            <div className="flex items-center justify-between">
                              <h4 className="font-semibold">{ph.title}</h4>
                              {ph.tasks && (
                                <Badge tone="muted">
                                  {ph.tasks.length} task
                                </Badge>
                              )}
                            </div>
                            {ph.tasks && (
                              <ul className="mt-2 space-y-1 text-sm text-[var(--color-fg-muted)]">
                                {ph.tasks.map((t, i) => (
                                  <li key={i}>• {t}</li>
                                ))}
                              </ul>
                            )}
                            {ph.prompt && (
                              <details className="mt-3">
                                <summary className="cursor-pointer text-xs text-[var(--color-brand)] hover:underline">
                                  Lihat prompt
                                </summary>
                                <pre className="mt-2 text-xs whitespace-pre-wrap text-[var(--color-fg-muted)]">
                                  {ph.prompt}
                                </pre>
                              </details>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                  {selectedVersion.master_prompt && (
                    <Card className="p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <h4 className="font-semibold">Master Prompt Web</h4>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() =>
                            copyToClipboard(
                              selectedVersion.master_prompt as string
                            )
                          }
                        >
                          <Copy size={13} /> Salin
                        </Button>
                      </div>
                      <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                        {selectedVersion.master_prompt}
                      </Markdown>
                    </Card>
                  )}
                </div>
              )}
              {tab === "mobile" && project?.target === "both" && (
                <div className="space-y-4">
                  {!selectedVersion?.mobile_phases &&
                    !selectedVersion?.mobile_master_prompt &&
                    !selectedVersion?.design_system_mobile &&
                    !selectedVersion?.app_spec_mobile && (
                      <p className="text-[var(--color-fg-subtle)]">
                        Belum ada output mobile. Pipeline untuk mobile harus
                        dijalankan.
                      </p>
                    )}
                  {selectedVersion.design_system_mobile && (
                    <div>
                      <h4 className="mb-2 font-semibold">
                        Design System Mobile
                      </h4>
                      <DesignSystemMobileView
                        markdown={selectedVersion.design_system_mobile}
                      />
                    </div>
                  )}
                  {Boolean(selectedVersion.app_spec_mobile) && (
                    <div>
                      <h4 className="mb-2 font-semibold">App Spec — Mobile</h4>
                      <AppSpecMobileView
                        data={
                          selectedVersion.app_spec_mobile as AppSpecMobileLike
                        }
                      />
                    </div>
                  )}
                  {selectedVersion.mobile_standards && (
                    <div>
                      <h4 className="mb-2 font-semibold">Standards Mobile</h4>
                      <Markdown className="text-sm text-[var(--color-fg-muted)]">
                        {selectedVersion.mobile_standards}
                      </Markdown>
                    </div>
                  )}
                  {!selectedVersion.design_system_mobile && (
                    <EmptyArtifact
                      title="Design System Mobile"
                      description="Token Material 3 + ThemeData + signature element untuk Flutter."
                      stageKey="design_system_mobile"
                      versionId={selectedVersion.id}
                      canGenerate={stageStatus.master_web === "done"}
                      blockReason="Selesaikan Master Prompt Web dulu"
                      onGenerate={async () => {
                        setRegeneratingStage("design_system_mobile")
                        try {
                          await apiPost(
                            `/versions/${selectedVersion.id}/regenerate`,
                            { stage: "design_system_mobile" }
                          )
                          fetchVersion(selectedVersion.id)
                        } catch (err) {
                          setError(
                            err instanceof Error
                              ? err.message
                              : "Gagal regenerate design system mobile"
                          )
                        } finally {
                          setRegeneratingStage(null)
                        }
                      }}
                    />
                  )}
                  {!selectedVersion.app_spec_mobile && (
                    <EmptyArtifact
                      title="App Spec — Mobile"
                      description="Registry screens, navigation, flows, dan widgets Flutter (JSON)."
                      stageKey="app_spec_mobile"
                      versionId={selectedVersion.id}
                      canGenerate={stageStatus.master_mobile === "done"}
                      blockReason="Selesaikan Master Prompt Mobile dulu"
                      onGenerate={async () => {
                        setRegeneratingStage("app_spec_mobile")
                        try {
                          await apiPost(
                            `/versions/${selectedVersion.id}/regenerate`,
                            { stage: "app_spec_mobile" }
                          )
                          fetchVersion(selectedVersion.id)
                        } catch (err) {
                          setError(
                            err instanceof Error
                              ? err.message
                              : "Gagal regenerate app spec mobile"
                          )
                        } finally {
                          setRegeneratingStage(null)
                        }
                      }}
                    />
                  )}
                  {mobilePhases.length > 0 && (
                    <div>
                      <h4 className="mb-3 font-semibold">Mobile Phases</h4>
                      <div className="space-y-3">
                        {mobilePhases.map((ph) => (
                          <div
                            key={ph.key}
                            className="rounded-xl border border-[var(--color-border)] p-4"
                          >
                            <div className="flex items-center justify-between">
                              <h4 className="font-semibold">{ph.title}</h4>
                              {ph.tasks && (
                                <Badge tone="muted">
                                  {ph.tasks.length} task
                                </Badge>
                              )}
                            </div>
                            {ph.tasks && (
                              <ul className="mt-2 space-y-1 text-sm text-[var(--color-fg-muted)]">
                                {ph.tasks.map((t, i) => (
                                  <li key={i}>• {t}</li>
                                ))}
                              </ul>
                            )}
                          </div>
                        ))}
                      </div>
                    </div>
                  )}
                  {selectedVersion?.mobile_master_prompt && (
                    <Card className="p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <h4 className="font-semibold">Mobile Master Prompt</h4>
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() =>
                            copyToClipboard(
                              selectedVersion.mobile_master_prompt as string
                            )
                          }
                        >
                          <Copy size={13} /> Salin
                        </Button>
                      </div>
                      <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                        {selectedVersion.mobile_master_prompt}
                      </Markdown>
                    </Card>
                  )}
                </div>
              )}
              {tab === "tracking" && (
                <div>
                  {phases.length === 0 && mobilePhases.length === 0 ? (
                    <p className="text-[var(--color-fg-subtle)]">
                      Belum ada fase. Jalankan pipeline sampai Master Prompt
                      untuk generate tracking.
                    </p>
                  ) : (
                    <TrackingPanel
                      phases={[
                        ...(phases as PhaseItem[]),
                        ...(mobilePhases as PhaseItem[]),
                      ]}
                      progMap={Object.fromEntries(
                        (selectedVersion.phase_progress ?? []).map(
                          (p: ProgressItem) => [p.phase_key, p]
                        )
                      )}
                      webhookUrl={WEBHOOK_URL}
                    />
                  )}
                  {/* CP-44 CP-07: feed telemetry coding agent */}
                  <div className="mt-4 space-y-2">
                    <h3 className="text-sm font-semibold">Agent Events</h3>
                    <AgentEventFeed versionId={selectedVersion.id} />
                  </div>
                </div>
              )}
              {tab === "activities" && (
                <div className="space-y-2">
                  {activitiesLoading ? (
                    <p className="text-sm text-[var(--color-fg-muted)]">
                      <Loader2 className="mr-2 inline animate-spin" size={14} />
                      Memuat aktivitas...
                    </p>
                  ) : activities.length === 0 ? (
                    <p className="text-[var(--color-fg-subtle)]">
                      Belum ada aktivitas.
                    </p>
                  ) : (
                    activities.map((a) => (
                      <div
                        key={a.id}
                        className="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3"
                      >
                        <div className="mt-0.5 grid h-7 w-7 place-items-center rounded-full bg-[var(--color-surface-2)] text-[var(--color-brand)]">
                          <History size={13} />
                        </div>
                        <div className="min-w-0 flex-1">
                          <p className="text-sm">{a.description}</p>
                          <p className="mt-0.5 text-xs text-[var(--color-fg-subtle)]">
                            {a.user.name} &middot;{" "}
                            {new Date(a.created_at).toLocaleString("id-ID")}
                          </p>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
            </div>
          </Card>

          {/* Sidebar: progress checklist */}
          <div className="space-y-4">
            <Card className="p-5">
              <h3 className="mb-3 font-semibold">Master Prompt</h3>
              <p className="text-sm text-[var(--color-fg-muted)]">
                {selectedVersion.master_prompt
                  ? "Prompt gabungan siap disuapkan ke AI coding agent."
                  : "Master prompt akan muncul setelah semua stage selesai."}
              </p>
              <Button
                variant="secondary"
                size="sm"
                className="mt-4 w-full"
                disabled={!selectedVersion.master_prompt}
                onClick={() =>
                  selectedVersion.master_prompt &&
                  copyToClipboard(selectedVersion.master_prompt as string)
                }
              >
                <Copy size={15} /> Salin Master Prompt
              </Button>
              {selectedVersion.mobile_master_prompt && (
                <Button
                  variant="secondary"
                  size="sm"
                  className="mt-2 w-full"
                  onClick={() =>
                    copyToClipboard(
                      selectedVersion.mobile_master_prompt as string
                    )
                  }
                >
                  <Smartphone size={14} /> Salin Mobile Master Prompt
                </Button>
              )}
            </Card>

            {selectedVersion && (
              <Card className="p-5">
                <h3 className="mb-3 font-semibold">Standards & Rules</h3>
                <div className="space-y-3">
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span
                        className={`inline-block h-2 w-2 rounded-full ${selectedVersion.standards ? "bg-green-500" : "bg-yellow-500"}`}
                      />
                      <span className="text-xs">
                        {selectedVersion.standards
                          ? "STANDARDS.md tersedia"
                          : "STANDARDS.md belum tersedia"}
                      </span>
                    </div>
                    {selectedVersion.standards ? (
                      <div className="flex gap-2">
                        <Button
                          variant="secondary"
                          size="sm"
                          onClick={() =>
                            window.open(
                              `${API_BASE_URL}/api/versions/${selectedVersion.id}/standards`,
                              "_blank"
                            )
                          }
                        >
                          <Copy size={13} /> Download
                        </Button>
                        <Button
                          variant="secondary"
                          size="sm"
                          disabled={regeneratingWeb}
                          onClick={async () => {
                            setRegeneratingWeb(true)
                            try {
                              await apiPost(
                                `/versions/${selectedVersion.id}/regenerate-standards`
                              )
                              fetchVersion(selectedVersion.id)
                            } catch (err) {
                              setError(
                                err instanceof Error
                                  ? err.message
                                  : "Gagal generate"
                              )
                            } finally {
                              setRegeneratingWeb(false)
                            }
                          }}
                        >
                          {regeneratingWeb ? (
                            <Loader2 size={13} className="animate-spin" />
                          ) : (
                            <RotateCcw size={13} />
                          )}{" "}
                          Regenerate
                        </Button>
                      </div>
                    ) : (
                      <Button
                        variant="secondary"
                        size="sm"
                        disabled={regeneratingWeb}
                        onClick={async () => {
                          setRegeneratingWeb(true)
                          try {
                            await apiPost(
                              `/versions/${selectedVersion.id}/regenerate-standards`
                            )
                            fetchVersion(selectedVersion.id)
                          } catch (err) {
                            setError(
                              err instanceof Error
                                ? err.message
                                : "Gagal generate"
                            )
                          } finally {
                            setRegeneratingWeb(false)
                          }
                        }}
                      >
                        {regeneratingWeb ? (
                          <Loader2 size={13} className="animate-spin" />
                        ) : (
                          <Copy size={13} />
                        )}{" "}
                        Generate
                      </Button>
                    )}
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span
                        className={`inline-block h-2 w-2 rounded-full ${selectedVersion.agents ? "bg-green-500" : "bg-yellow-500"}`}
                      />
                      <span className="text-xs">
                        {selectedVersion.agents
                          ? "AGENTS.md tersedia"
                          : "AGENTS.md belum tersedia"}
                      </span>
                    </div>
                    {selectedVersion.agents ? (
                      <Button
                        variant="secondary"
                        size="sm"
                        onClick={() =>
                          window.open(
                            `${API_BASE_URL}/api/versions/${selectedVersion.id}/agents`,
                            "_blank"
                          )
                        }
                      >
                        <Copy size={13} /> Download
                      </Button>
                    ) : (
                      <span className="text-xs text-[var(--color-fg-subtle)]">
                        Klik Generate di atas (regenerasi Standards + AGENTS
                        sekaligus)
                      </span>
                    )}
                  </div>
                  {selectedVersion.mobile_standards ||
                  selectedVersion.mobile_agents ? (
                    <>
                      <hr className="border-[var(--color-border)]" />
                      <p className="text-xs font-medium text-[var(--color-fg-muted)]">
                        Mobile (Flutter)
                      </p>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span
                            className={`inline-block h-2 w-2 rounded-full ${selectedVersion.mobile_standards ? "bg-green-500" : "bg-yellow-500"}`}
                          />
                          <span className="text-xs">
                            {selectedVersion.mobile_standards
                              ? "STANDARDS-MOBILE.md tersedia"
                              : "STANDARDS-MOBILE.md belum tersedia"}
                          </span>
                        </div>
                        {selectedVersion.mobile_standards ? (
                          <div className="flex gap-2">
                            <Button
                              variant="secondary"
                              size="sm"
                              onClick={() =>
                                window.open(
                                  `${API_BASE_URL}/api/versions/${selectedVersion.id}/standards/mobile`,
                                  "_blank"
                                )
                              }
                            >
                              <Copy size={13} /> Download
                            </Button>
                            <Button
                              variant="secondary"
                              size="sm"
                              disabled={regeneratingMobile}
                              onClick={async () => {
                                setRegeneratingMobile(true)
                                try {
                                  await apiPost(
                                    `/versions/${selectedVersion.id}/regenerate-standards/mobile`
                                  )
                                  fetchVersion(selectedVersion.id)
                                } catch (err) {
                                  setError(
                                    err instanceof Error
                                      ? err.message
                                      : "Gagal generate"
                                  )
                                } finally {
                                  setRegeneratingMobile(false)
                                }
                              }}
                            >
                              {regeneratingMobile ? (
                                <Loader2 size={13} className="animate-spin" />
                              ) : (
                                <RotateCcw size={13} />
                              )}{" "}
                              Regenerate
                            </Button>
                          </div>
                        ) : (
                          <Button
                            variant="secondary"
                            size="sm"
                            disabled={regeneratingMobile}
                            onClick={async () => {
                              setRegeneratingMobile(true)
                              try {
                                await apiPost(
                                  `/versions/${selectedVersion.id}/regenerate-standards/mobile`
                                )
                                fetchVersion(selectedVersion.id)
                              } catch (err) {
                                setError(
                                  err instanceof Error
                                    ? err.message
                                    : "Gagal generate"
                                )
                              } finally {
                                setRegeneratingMobile(false)
                              }
                            }}
                          >
                            {regeneratingMobile ? (
                              <Loader2 size={13} className="animate-spin" />
                            ) : (
                              <Copy size={13} />
                            )}{" "}
                            Generate
                          </Button>
                        )}
                      </div>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span
                            className={`inline-block h-2 w-2 rounded-full ${selectedVersion.mobile_agents ? "bg-green-500" : "bg-yellow-500"}`}
                          />
                          <span className="text-xs">
                            {selectedVersion.mobile_agents
                              ? "AGENTS-MOBILE.md tersedia"
                              : "AGENTS-MOBILE.md belum tersedia"}
                          </span>
                        </div>
                        {selectedVersion.mobile_agents ? (
                          <Button
                            variant="secondary"
                            size="sm"
                            onClick={() =>
                              window.open(
                                `${API_BASE_URL}/api/versions/${selectedVersion.id}/agents/mobile`,
                                "_blank"
                              )
                            }
                          >
                            <Copy size={13} /> Download
                          </Button>
                        ) : (
                          <span className="text-xs text-[var(--color-fg-subtle)]">
                            Klik Generate di atas (regenerasi Standards + AGENTS
                            sekaligus)
                          </span>
                        )}
                      </div>
                    </>
                  ) : null}
                </div>
              </Card>
            )}

            {selectedVersion.stage_status && (
              <Card className="p-5">
                <div className="mb-3 flex items-center justify-between">
                  <h3 className="font-semibold">Pipeline</h3>
                  <Button
                    variant="ghost"
                    size="sm"
                    onClick={() => setShowQuality(true)}
                    data-testid="quality-report"
                  >
                    <BarChart3 size={13} /> Laporan Kualitas
                  </Button>
                  {(selectedVersion.stage_status as Record<string, string>)
                    .analisa === "done" && (
                    <Button
                      variant="ghost"
                      size="sm"
                      disabled={restartingAnalisa}
                      onClick={async () => {
                        setRestartingAnalisa(true)
                        try {
                          await apiPost(
                            `/versions/${selectedVersion.id}/restart-from-analisa`
                          )
                          fetchVersion(selectedVersion.id)
                        } catch (err) {
                          setError(
                            err instanceof Error
                              ? err.message
                              : "Gagal restart dari analisa"
                          )
                        } finally {
                          setRestartingAnalisa(false)
                        }
                      }}
                      data-testid="restart-analisa"
                      title="Set pertanyaan+analisa done, jalankan ulang mulai PRD"
                    >
                      {restartingAnalisa ? (
                        <Loader2 size={13} className="animate-spin" />
                      ) : (
                        <RotateCcw size={13} />
                      )}
                    </Button>
                  )}
                </div>
                <div className="space-y-3">
                  {getStageGroups(project!.target as Target).map((group) => {
                    const groupStages = getStages(
                      project!.target as Target
                    ).filter((s) => group.stages.includes(s.key))
                    const statusMap = selectedVersion.stage_status as Record<
                      string,
                      string
                    >
                    const done = groupStages.filter(
                      (s) => statusMap[s.key] === "done"
                    ).length
                    const skipped = groupStages.filter(
                      (s) => statusMap[s.key] === "skipped"
                    ).length
                    return (
                      <details
                        key={group.key}
                        open
                        className="group/seg rounded-lg border border-[var(--color-border)] px-2 py-1.5"
                      >
                        <summary className="flex cursor-pointer items-center gap-2 text-[11px] font-semibold tracking-wide text-[var(--color-fg-muted)] uppercase">
                          <span>{group.label}</span>
                          <span className="ml-auto inline-flex items-center gap-1 text-[10px] normal-case">
                            {skipped > 0 && (
                              <span className="rounded-full bg-[var(--color-surface-2)] px-1.5 py-0.5 text-[var(--color-fg-muted)]">
                                {skipped} dilewati
                              </span>
                            )}
                            <span className="rounded-full bg-[var(--color-surface-2)] px-1.5 py-0.5">
                              {done}/{groupStages.length} selesai
                            </span>
                          </span>
                        </summary>
                        <div className="mt-1.5 space-y-0.5">
                          {groupStages.map((s) => {
                            const st = statusMap[s.key] as StageStatus
                            return (
                              <StageRow
                                key={s.key}
                                stageKey={s.key}
                                label={s.label}
                                status={st}
                                quality={selectedVersion.stage_quality?.[s.key]}
                                isRegenerating={regeneratingStage === s.key}
                                onRegenerate={async () => {
                                  setRegeneratingStage(s.key)
                                  try {
                                    await apiPost(
                                      `/versions/${selectedVersion.id}/regenerate`,
                                      { stage: s.key }
                                    )
                                    fetchVersion(selectedVersion.id)
                                  } catch (err) {
                                    setError(
                                      err instanceof Error
                                        ? err.message
                                        : "Gagal regenerate stage"
                                    )
                                  } finally {
                                    setRegeneratingStage(null)
                                  }
                                }}
                                onSkip={async (reason) => {
                                  setRegeneratingStage(s.key)
                                  try {
                                    await apiPost(
                                      `/versions/${selectedVersion.id}/skip-stage`,
                                      { stage: s.key, reason }
                                    )
                                    fetchVersion(selectedVersion.id)
                                  } catch (err) {
                                    setError(
                                      err instanceof Error
                                        ? err.message
                                        : "Gagal skip stage"
                                    )
                                  } finally {
                                    setRegeneratingStage(null)
                                  }
                                }}
                              />
                            )
                          })}
                        </div>
                      </details>
                    )
                  })}
                </div>
                {!getStages(project!.target as Target).every(
                  (s) =>
                    (selectedVersion.stage_status as Record<string, string>)[
                      s.key
                    ] === "done"
                ) && (
                  <Button
                    size="sm"
                    className="mt-3 w-full"
                    onClick={() =>
                      router.push(`/new?resume=1&version=${selectedVersion.id}`)
                    }
                  >
                    <Play size={14} /> Lanjutkan Pipeline
                  </Button>
                )}
              </Card>
            )}

            {/* Riwayat Versi — B1 timeline */}
            {versions.length > 0 && (
              <Card className="p-5">
                <details open={false}>
                  <summary className="flex cursor-pointer items-center gap-2 font-semibold">
                    <GitBranch
                      size={14}
                      className="text-[var(--color-brand)]"
                    />
                    Riwayat Versi
                    <span className="ml-auto text-xs font-normal text-[var(--color-fg-muted)]">
                      {versions.length}
                    </span>
                  </summary>
                  <div className="mt-3 space-y-2">
                    {[...versions]
                      .sort((a, b) => (b.version_no ?? 0) - (a.version_no ?? 0))
                      .map((v) => {
                        const st = v.stage_status ?? {}
                        const done = Object.values(st).filter(
                          (s) => s === "done"
                        ).length
                        const total = Object.keys(st).length
                        const source = versions.find(
                          (x) => x.id === v.source_version_id
                        )
                        return (
                          <div
                            key={v.id}
                            className="flex items-start gap-2 rounded-md border border-[var(--color-border)] px-2.5 py-2"
                          >
                            <button
                              onClick={() => setSelectedVersion(v)}
                              className={`shrink-0 text-xs font-semibold ${selectedVersion?.id === v.id ? "text-[var(--color-brand)]" : "text-[var(--color-fg)] hover:text-[var(--color-brand)]"}`}
                            >
                              v{v.version_no}
                            </button>
                            <div className="min-w-0 flex-1 text-xs text-[var(--color-fg-muted)]">
                              <div className="flex flex-wrap items-center gap-x-2">
                                <span>
                                  {new Date(v.created_at).toLocaleDateString(
                                    "id-ID",
                                    {
                                      day: "numeric",
                                      month: "short",
                                      year: "numeric",
                                    }
                                  )}
                                </span>
                                <span className="rounded-full bg-[var(--color-surface-2)] px-1.5 py-0.5">
                                  {done}/{total} tahap
                                </span>
                                {source && (
                                  <span className="text-[var(--color-fg-subtle)]">
                                    dari v{source.version_no}
                                  </span>
                                )}
                              </div>
                              {v.baseline_notes && (
                                <p className="mt-0.5 truncate text-[var(--color-fg-subtle)]">
                                  “{v.baseline_notes}”
                                </p>
                              )}
                              {Object.keys(v.skip_reasons ?? {}).length > 0 && (
                                <p className="mt-0.5 truncate text-[var(--color-fg-subtle)]">
                                  skip:{" "}
                                  {Object.keys(v.skip_reasons ?? {})
                                    .slice(0, 3)
                                    .join(", ")}
                                </p>
                              )}
                            </div>
                          </div>
                        )
                      })}
                  </div>
                </details>
              </Card>
            )}

            {/* Progress Bangun — real-time tracking */}
            {((selectedVersion?.phases?.length ?? 0) > 0 ||
              (selectedVersion?.mobile_phases?.length ?? 0) > 0) && (
              <Card className="p-5">
                <h3 className="mb-3 font-semibold">Progress Bangun</h3>
                {phases.length > 0 && (
                  <div className="mb-4">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">Web</span>
                      <span className="text-[var(--color-fg-muted)]">
                        {doneCount}/{phases.length} fase · {progress}%
                      </span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div
                        className="h-full rounded-full bg-blue-500 transition-all duration-500"
                        style={{ width: `${progress}%` }}
                      />
                    </div>
                    <div className="mt-2 space-y-1">
                      {phases.map((ph) => {
                        const isDone = doneMap[ph.key]
                        return (
                          <button
                            key={ph.key}
                            onClick={() =>
                              handleTogglePhase(ph.key, isDone || false)
                            }
                            className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition hover:bg-[var(--color-surface-2)]"
                          >
                            <span
                              className={`grid h-4 w-4 place-items-center rounded border transition ${
                                isDone
                                  ? "border-blue-500 bg-blue-500 text-white"
                                  : "border-[var(--color-border)]"
                              }`}
                            >
                              {isDone && <Check size={10} />}
                            </span>
                            <span
                              className={
                                isDone
                                  ? "text-[var(--color-fg-subtle)] line-through"
                                  : ""
                              }
                            >
                              {ph.title}
                            </span>
                          </button>
                        )
                      })}
                    </div>
                  </div>
                )}
                {mobilePhases.length > 0 && (
                  <div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">Mobile</span>
                      <span className="text-[var(--color-fg-muted)]">
                        {mobileDoneCount}/{mobilePhases.length} fase ·{" "}
                        {mobileProgress}%
                      </span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div
                        className="h-full rounded-full bg-emerald-500 transition-all duration-500"
                        style={{ width: `${mobileProgress}%` }}
                      />
                    </div>
                    <div className="mt-2 space-y-1">
                      {mobilePhases.map((ph) => {
                        const isDone = doneMap[ph.key]
                        return (
                          <button
                            key={ph.key}
                            onClick={() =>
                              handleTogglePhase(ph.key, isDone || false)
                            }
                            className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition hover:bg-[var(--color-surface-2)]"
                          >
                            <span
                              className={`grid h-4 w-4 place-items-center rounded border transition ${
                                isDone
                                  ? "border-emerald-500 bg-emerald-500 text-white"
                                  : "border-[var(--color-border)]"
                              }`}
                            >
                              {isDone && <Check size={10} />}
                            </span>
                            <span
                              className={
                                isDone
                                  ? "text-[var(--color-fg-subtle)] line-through"
                                  : ""
                              }
                            >
                              {ph.title}
                            </span>
                          </button>
                        )
                      })}
                    </div>
                  </div>
                )}
                <p className="mt-3 text-[10px] text-[var(--color-fg-subtle)]">
                  Status diperbarui real-time oleh AI agent via webhook.
                </p>
              </Card>
            )}

            {selectedVersion?.stage_tokens &&
              Object.keys(selectedVersion.stage_tokens).length > 0 && (
                <Card className="p-5">
                  <h3 className="mb-3 font-semibold">Token Usage</h3>
                  <div className="space-y-1.5">
                    {Object.entries(selectedVersion.stage_tokens).map(
                      ([stage, tokens]) => (
                        <div
                          key={stage}
                          className="flex items-center justify-between text-xs"
                        >
                          <span className="text-[var(--color-fg-muted)]">
                            {stage}
                          </span>
                          <span className="font-mono tabular-nums">
                            {Number(tokens).toLocaleString("id-ID")}
                          </span>
                        </div>
                      )
                    )}
                    <div className="mt-2 flex items-center justify-between border-t border-[var(--color-border)] pt-2 text-sm font-semibold">
                      <span>Total</span>
                      <span className="font-mono tabular-nums">
                        {Object.values(selectedVersion.stage_tokens)
                          .reduce((s, n) => s + (Number(n) || 0), 0)
                          .toLocaleString("id-ID")}
                      </span>
                    </div>
                  </div>
                </Card>
              )}

            {lastRefreshed && (
              <div className="text-center text-[10px] text-[var(--color-fg-subtle)]">
                otomatis {countdown}s yang lalu
              </div>
            )}
          </div>
        </div>
      )}
      <Modal
        open={showVersionDialog && !!project}
        onClose={() => setShowVersionDialog(false)}
        title="Buat Versi Baru"
        size="sm"
      >
        <div className="space-y-4">
          <div>
            <p className="mb-1 text-sm font-medium">Strategi</p>
            <label className="flex cursor-pointer items-start gap-2 rounded-lg border border-[var(--color-border)] p-3">
              <input
                type="radio"
                name="ver"
                checked={versionStrategy === "from_last"}
                onChange={() => setVersionStrategy("from_last")}
              />
              <div>
                <span className="text-sm font-medium">
                  Lanjutkan dari versi terakhir (baseline)
                </span>
                <p className="text-xs text-[var(--color-fg-muted)]">
                  Salin artefak, jawaban & status fase dari v
                  {project?.versions?.[0]?.version_no ?? "—"} — untuk
                  revisi/pengembangan lanjutan.
                </p>
              </div>
            </label>
            <label className="mt-2 flex cursor-pointer items-start gap-2 rounded-lg border border-[var(--color-border)] p-3">
              <input
                type="radio"
                name="versionStrategy"
                checked={versionStrategy === "blank"}
                onChange={() => setVersionStrategy("blank")}
              />
              <div>
                <span className="text-sm font-medium">Mulai dari kosong</span>
                <p className="text-xs text-[var(--color-fg-muted)]">
                  Buat rencana baru tanpa salin versi sebelumnya.
                </p>
              </div>
            </label>
          </div>
          {versionStrategy === "from_last" && (
            <div>
              <label className="mb-1 block text-sm font-medium">
                Catatan revisi (opsional)
              </label>
              <input
                type="text"
                value={baselineNotes}
                onChange={(e) => setBaselineNotes(e.target.value)}
                placeholder="Contoh: tambah fitur laporan, perbaiki auth..."
                className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
              />
            </div>
          )}
          <div className="flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setShowVersionDialog(false)}
            >
              Batal
            </Button>
            <Button
              size="sm"
              onClick={handleCreateVersion}
              disabled={creatingVersion}
            >
              {creatingVersion ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Check size={14} />
              )}{" "}
              Buat Versi
            </Button>
          </div>
        </div>
      </Modal>

      <Modal
        open={editingProject && !!project}
        onClose={() => setEditingProject(false)}
        title="Edit Project"
        size="md"
      >
        <div className="space-y-4">
          <div>
            <label className="mb-1 block text-sm font-medium">
              Judul Project
            </label>
            <input
              type="text"
              value={editTitle}
              onChange={(e) => setEditTitle(e.target.value)}
              className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">
              Ide Aplikasi
            </label>
            <textarea
              rows={3}
              value={editIdea}
              onChange={(e) => setEditIdea(e.target.value)}
              className="w-full resize-y rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
            />
          </div>
          <div>
            <label className="mb-1 block text-sm font-medium">
              Target Platform
            </label>
            <select
              value={editTarget}
              onChange={(e) => setEditTarget(e.target.value as Target)}
              className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
            >
              <option value="web">Web</option>
              <option value="both">Web + Mobile</option>
            </select>
          </div>
          <div className="flex justify-end gap-2">
            <Button
              variant="secondary"
              size="sm"
              onClick={() => setEditingProject(false)}
            >
              Batal
            </Button>
            <Button
              size="sm"
              disabled={savingProject || !editTitle.trim()}
              onClick={async () => {
                setSavingProject(true)
                try {
                  await apiPatch(`/projects/${project!.id}`, {
                    title: editTitle.trim(),
                    idea: editIdea,
                    target: editTarget,
                  })
                  setProject((prev) =>
                    prev
                      ? {
                          ...prev,
                          title: editTitle.trim(),
                          idea: editIdea,
                          target: editTarget,
                        }
                      : prev
                  )
                  setEditingProject(false)
                } catch (err) {
                  setError(
                    err instanceof Error ? err.message : "Gagal menyimpan"
                  )
                } finally {
                  setSavingProject(false)
                }
              }}
            >
              {savingProject ? (
                <Loader2 size={14} className="animate-spin" />
              ) : (
                <Check size={14} />
              )}{" "}
              Simpan
            </Button>
          </div>
        </div>
      </Modal>

      {/* Quality Report — B2 */}
      <Modal
        open={showQuality && !!selectedVersion}
        onClose={() => setShowQuality(false)}
        title={`Laporan Kualitas — v${selectedVersion?.version_no}`}
        size="lg"
      >
        {selectedVersion && (
          <div className="max-h-[70vh] overflow-auto">
            <div className="mb-3 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs text-[var(--color-fg-muted)]">
              Skor = agregat validator (struktur+keyword+panjang+orisinalitas).
              Skor &lt; 60% → sebaiknya regenerate stage-nya.
            </div>
            <table className="w-full text-left text-sm">
              <thead>
                <tr className="border-b border-[var(--color-border)] text-xs tracking-wide text-[var(--color-fg-subtle)] uppercase">
                  <th className="py-2 pr-2 font-medium">Stage</th>
                  <th className="py-2 pr-2 font-medium">Status</th>
                  <th className="py-2 pr-2 font-medium">Skor</th>
                  <th className="py-2 font-medium">Catatan</th>
                </tr>
              </thead>
              <tbody>
                {getStages(project!.target as Target).map((s) => {
                  const st =
                    (selectedVersion.stage_status as Record<string, string>)[
                      s.key
                    ] ?? "pending"
                  const q = selectedVersion.stage_quality?.[s.key]
                  const err = (
                    selectedVersion.stage_errors as
                      Record<string, string> | undefined
                  )?.[s.key]
                  const skipReason = selectedVersion.skip_reasons?.[s.key]
                  const note = err ? (
                    <span className="text-[var(--color-danger)]">
                      {err.slice(0, 90)}…
                    </span>
                  ) : skipReason ? (
                    <span className="text-[var(--color-fg-muted)]">
                      {skipReason.slice(0, 90)}
                    </span>
                  ) : (
                    <span className="text-[var(--color-fg-subtle)]">—</span>
                  )
                  return (
                    <tr
                      key={s.key}
                      className="border-b border-[var(--color-border)]/60"
                    >
                      <td className="py-1.5 pr-2">{s.label}</td>
                      <td className="py-1.5 pr-2">{st}</td>
                      <td className="py-1.5 pr-2">
                        {typeof q === "number" ? (
                          <Badge
                            tone={
                              q >= 0.8
                                ? "success"
                                : q >= 0.6
                                  ? "warning"
                                  : "danger"
                            }
                          >
                            {Math.round(q * 100)}%
                          </Badge>
                        ) : (
                          <span className="text-[var(--color-fg-subtle)]">
                            —
                          </span>
                        )}
                      </td>
                      <td className="py-1.5 text-xs">{note}</td>
                    </tr>
                  )
                })}
              </tbody>
            </table>
          </div>
        )}
      </Modal>

      <ConfirmDialog
        open={confirmDeleteProject}
        onClose={() => setConfirmDeleteProject(false)}
        onConfirm={handleDelete}
        title="Hapus Project?"
        message="Yakin ingin menghapus project ini? Semua versi & data akan hilang."
        confirmLabel="Ya, Hapus"
      />
      <ConfirmDialog
        open={confirmDeleteVersionId !== null}
        onClose={() => setConfirmDeleteVersionId(null)}
        onConfirm={() =>
          confirmDeleteVersionId !== null &&
          handleDeleteVersion(confirmDeleteVersionId)
        }
        title="Hapus Versi?"
        message="Yakin ingin menghapus versi ini?"
        confirmLabel="Ya, Hapus"
      />
    </ErrorBoundary>
  )
}
