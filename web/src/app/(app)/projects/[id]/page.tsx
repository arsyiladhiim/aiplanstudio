"use client";
import { useRouter } from "next/navigation";
import { useState, useEffect } from "react";
import { notFound } from "next/navigation";
import { use } from "react";
import { Card, Badge, Markdown } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { TargetBadge } from "@/components/common";
import { ErdDiagram } from "@/components/wizard/ErdDiagram";
import { ErrorBoundary } from "@/components/ErrorBoundary";
import { getStages, type Target } from "@/lib/mock";
import { apiGet, apiPost, apiDelete, apiPatch, type Project, type Version, type Activity } from "@/lib/api";
import {
  ArrowLeft, GitBranch, Download, Plus, Copy, FileText, Database, ListChecks, Check, Loader2, Play, Trash2, GitCompareArrows, Smartphone, Pencil, X, MessageCircle, History, Heart,
} from "lucide-react";

const TABS = [
  { key: "answers", label: "Klarifikasi", icon: MessageCircle },
  { key: "analysis", label: "Analisa", icon: FileText },
  { key: "prd", label: "PRD", icon: FileText },
  { key: "architecture", label: "Arsitektur", icon: FileText },
  { key: "erd", label: "ERD", icon: Database },
  { key: "phases", label: "Phases", icon: ListChecks },
  { key: "mobile", label: "Mobile", icon: Smartphone },
  { key: "activities", label: "Aktivitas", icon: History },
] as const;

type TabKey = typeof TABS[number]["key"];

export default function ProjectDetail({ params }: { params: Promise<{ id: string }> }) {
  const { id } = use(params);
  const router = useRouter();
  const [project, setProject] = useState<Project & { versions?: Version[] } | null>(null);
  const [selectedVersion, setSelectedVersion] = useState<Version | null>(null);
  const [loading, setLoading] = useState(true);
  const [versionLoading, setVersionLoading] = useState(false);
  const [error, setError] = useState("");
  const [tab, setTab] = useState<TabKey>("prd");
  const [creatingVersion, setCreatingVersion] = useState(false);
  const [diffMode, setDiffMode] = useState(false);
  const [diffVersionId, setDiffVersionId] = useState<number | null>(null);
  const [editingProject, setEditingProject] = useState(false);
  const [editTitle, setEditTitle] = useState("");
  const [editIdea, setEditIdea] = useState("");
  const [editTarget, setEditTarget] = useState<Target>("web");
  const [savingProject, setSavingProject] = useState(false);

  // Fetch project with versions
  useEffect(() => {
    apiGet<Project & { versions: Version[] }>(`/projects/${id}`)
      .then((data) => {
        setProject(data);
        // Auto-select latest version
        if (data.versions && data.versions.length > 0) {
          const latest = data.versions[0]; // backend returns latest() first
          fetchVersion(latest.id);
        }
      })
      .catch((err) => {
        setError(err instanceof Error ? err.message : "Gagal memuat project");
      })
      .finally(() => setLoading(false));
  }, [id]);

  // Silent auto-refresh for real-time progress (no loading flash)
  const [lastRefreshed, setLastRefreshed] = useState<Date | null>(null);
  const [countdown, setCountdown] = useState(0);

  useEffect(() => {
    if (!selectedVersion?.id) return;
    const interval = setInterval(async () => {
      try {
        const v = await apiGet<Version>(`/versions/${selectedVersion.id}`);
        setSelectedVersion(v);
        setLastRefreshed(new Date());
        setCountdown(0);
      } catch { /* silent skip */ }
    }, 10000);
    return () => clearInterval(interval);
  }, [selectedVersion?.id]);

  // Update countdown every second for the timer display
  useEffect(() => {
    if (!lastRefreshed) return;
    const tick = setInterval(() => setCountdown(c => c + 1), 1000);
    return () => clearInterval(tick);
  }, [lastRefreshed]);

  const [activities, setActivities] = useState<Activity[]>([]);
  const activitiesLoading = tab === "activities" && activities.length === 0;

  useEffect(() => {
    if (tab !== "activities" || activities.length > 0) return;
    apiGet<{ data: Activity[] }>(`/projects/${id}/activities`)
      .then(res => setActivities(res.data))
      .catch((err) => console.error('Failed to load activities:', err));
  }, [tab, id, activities.length]);

  function fetchVersion(versionId: number) {
    setVersionLoading(true);
    apiGet<Version>(`/versions/${versionId}`)
      .then(setSelectedVersion)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat version"))
      .finally(() => setVersionLoading(false));
  }

  async function handleDelete() {
    if (!window.confirm("Yakin ingin menghapus project ini? Semua versi & data akan hilang.")) return;
    try {
      await apiDelete(`/projects/${id}`);
      router.push('/projects');
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal menghapus project');
    }
  }

  async function handleCreateVersion() {
    if (!project || creatingVersion) return;
    setCreatingVersion(true);
    try {
      const newVersion = await apiPost<Version>(`/projects/${project.id}/versions`);
      // Refresh project to get updated versions list
      const updated = await apiGet<Project & { versions: Version[] }>(`/projects/${id}`);
      setProject(updated);
      fetchVersion(newVersion.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membuat version baru");
    } finally {
      setCreatingVersion(false);
    }
  }

  async function handleDeleteVersion(versionId: number) {
    if (!window.confirm('Yakin ingin menghapus versi ini?')) return;
    try {
      await apiDelete(`/versions/${versionId}`);
      if (project) {
        const updated = await apiGet<Project & { versions: Version[] }>(`/projects/${id}`);
        setProject(updated);
        if (updated.versions && updated.versions.length > 0) {
          fetchVersion(updated.versions[0].id);
        } else {
          setSelectedVersion(null);
        }
      }
    } catch (err) {
      setError(err instanceof Error ? err.message : 'Gagal menghapus versi');
    }
  }

  async function handleTogglePhase(phaseKey: string, currentDone: boolean) {
    if (!selectedVersion) return;
    try {
      await apiPatch(`/versions/${selectedVersion.id}/phases/${phaseKey}`, { done: !currentDone });
      // Refresh version to get updated phase progress
      fetchVersion(selectedVersion.id);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal toggle phase");
    }
  }

  function handleExport(format: 'md' | 'zip') {
    if (!selectedVersion) return;
    window.open(`/api/versions/${selectedVersion.id}/export?format=${format}`, '_blank');
  }

  function copyToClipboard(text: string) {
    navigator.clipboard.writeText(text).catch(() => {
      // Clipboard write denied by browser permissions — fallback
      const ta = document.createElement('textarea');
      ta.value = text;
      ta.style.position = 'fixed';
      ta.style.opacity = '0';
      document.body.appendChild(ta);
      ta.select();
      document.execCommand('copy');
      document.body.removeChild(ta);
    });
  }

  if (loading) {
    return <div className="text-center py-12"><Loader2 className="animate-spin inline" /> Memuat project...</div>;
  }

  if (error && !project) {
    return (
      <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
        {error}
      </div>
    );
  }

  if (!project) return notFound();

  const versions = project.versions || [];
  const phases = selectedVersion?.phases as Array<{ key: string; title: string; tasks?: string[]; prompt?: string }> || [];
  const mobilePhases = selectedVersion?.mobile_phases as Array<{ key: string; title: string; tasks?: string[]; prompt?: string }> || [];
  const phaseProgress = selectedVersion?.phaseProgress as Array<{ phase_key: string; done: boolean }> || [];
  const doneMap = Object.fromEntries(phaseProgress.map(p => [p.phase_key, p.done]));
  const doneCount = phaseProgress.filter(p => p.done).length;
  const progress = phases.length > 0 ? Math.round((doneCount / phases.length) * 100) : 0;
  const mobileDoneCount = phaseProgress.filter(p => mobilePhases.some(mp => mp.key === p.phase_key) && p.done).length;
  const mobileProgress = mobilePhases.length > 0 ? Math.round((mobileDoneCount / mobilePhases.length) * 100) : 0;

  return (
    <ErrorBoundary>
      <ButtonLink href="/projects" variant="ghost" size="sm" className="mb-4"><ArrowLeft size={16} /> Projects</ButtonLink>

      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{project.title}</h1>
            <TargetBadge target={project.target} />
          </div>
          <p className="mt-1.5 max-w-2xl text-sm text-[var(--color-fg-muted)]">{project.idea}</p>
          <button
            onClick={() => {
              setEditTitle(project.title);
              setEditIdea(project.idea);
              setEditTarget(project.target as Target);
              setEditingProject(true);
            }}
            className="mt-2 inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-[var(--color-brand)]"
          >
            <Pencil size={12} /> Edit project
          </button>
        </div>
        <div className="flex shrink-0 items-center gap-2">
          <button
            onClick={async () => {
              try {
                const res = await apiPatch<{ is_favorite: boolean }>(`/projects/${id}/favorite`);
                setProject(prev => prev ? { ...prev, is_favorite: res.is_favorite } : null);
              } catch {}
            }}
            className={`inline-flex items-center gap-1.5 rounded-lg px-3 py-1.5 text-sm transition ${
              project.is_favorite ? "text-red-500" : "text-[var(--color-fg-muted)] hover:text-red-400"
            }`}
            title={project.is_favorite ? "Hapus dari favorit" : "Tandai sebagai favorit"}
          >
            <Heart size={16} fill={project.is_favorite ? "currentColor" : "none"} />
          </button>
          <Button variant="secondary" size="sm" onClick={() => handleExport('md')} disabled={!selectedVersion}>
            <Download size={15} /> Export
          </Button>
          <Button size="sm" onClick={handleCreateVersion} disabled={creatingVersion}>
            {creatingVersion ? <Loader2 size={15} className="animate-spin" /> : <Plus size={15} />} Versi Baru
          </Button>
          <Button variant="secondary" size="sm" onClick={handleDelete}>
            <Trash2 size={15} /> Hapus
          </Button>
        </div>
      </div>

      {/* Version selector */}
      <div className="mt-5 flex flex-wrap items-center gap-2">
        <span className="text-sm text-[var(--color-fg-muted)]">Versi:</span>
        {versions.length === 0 && <span className="text-sm text-[var(--color-fg-subtle)]">Belum ada versi</span>}
        {versions.map((v) => (
          <div key={v.id} className="inline-flex items-center gap-0.5">
            <button
              onClick={() => {
                if (diffMode) {
                  setDiffVersionId(v.id);
                  return;
                }
                fetchVersion(v.id);
              }}
              data-testid={`version-${v.version_no}`}
              disabled={versionLoading}
              className={`inline-flex items-center gap-1.5 rounded-l-full px-3 py-1.5 text-sm font-medium transition ${
                diffMode && diffVersionId === v.id
                  ? "ring-2 ring-[var(--color-warning)] bg-[var(--color-warning)]/10 text-[var(--color-warning)]"
                  : selectedVersion?.id === v.id
                    ? "bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-brand)]"
                    : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
              }`}
            >
              <GitBranch size={13} /> v{v.version_no}
            </button>
            {versions.length > 1 && (
              <button
                onClick={(e) => { e.stopPropagation(); handleDeleteVersion(v.id); }}
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
                router.push(`/projects/${id}/diff?compare=${diffVersionId}`);
              } else {
                setDiffMode(!diffMode);
                setDiffVersionId(null);
              }
            }}
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition ${
              diffMode ? "bg-[var(--color-warning)]/15 text-[var(--color-warning)]" : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            }`}
          >
            <GitCompareArrows size={13} />
            {diffMode ? (diffVersionId ? "Bandingkan" : "Pilih versi pembanding") : "Diff"}
          </button>
        )}
      </div>
      {diffMode && diffVersionId && selectedVersion && (
        <p className="mt-2 text-xs text-[var(--color-fg-muted)]">
          Membandingkan v{selectedVersion.version_no} dengan v{versions.find(v => v.id === diffVersionId)?.version_no}.
          Klik tombol &ldquo;Bandingkan&rdquo; untuk melihat hasil.
        </p>
      )}

      {/* API Tokens */}
      <ApiTokenSection projectId={id} />

      {versionLoading && <div className="mt-6 text-center text-sm text-[var(--color-fg-muted)]">Memuat version...</div>}

      {!versionLoading && selectedVersion && (
        <div className="mt-6 grid gap-6 lg:grid-cols-[1fr_300px]">
          {/* Artifacts */}
          <Card className="overflow-hidden p-0">
            <div className="flex border-b border-[var(--color-border)]">
              {TABS.map((t) => (
                <button
                  key={t.key}
                  onClick={() => setTab(t.key)}
                  data-testid={`tab-${t.key}`}
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
                  const content = tab === 'answers' ? JSON.stringify(selectedVersion.answers, null, 2) :
                    tab === 'erd' ? JSON.stringify(selectedVersion.erd, null, 2) :
                    tab === 'phases' ? JSON.stringify(selectedVersion.phases, null, 2) :
                    tab === 'mobile' ? JSON.stringify(selectedVersion.mobile_phases, null, 2) :
                    tab === 'analysis' ? (selectedVersion.analysis ?? '') :
                    tab === 'prd' ? (selectedVersion.prd ?? '') :
                    tab === 'architecture' ? (selectedVersion.architecture ?? '') : '';
                  copyToClipboard(content);
                }}
                className="ml-auto mr-3 my-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
              >
                <Copy size={13} /> Salin
              </button>
            </div>

            <div className="p-5 max-h-[600px] overflow-auto">
              {tab === "answers" && (
                <div className="space-y-4">
                  {!selectedVersion.answers || Object.keys(selectedVersion.answers).length === 0 ? (
                    <p className="text-[var(--color-fg-subtle)]">Belum ada data klarifikasi.</p>
                  ) : (
                    Object.entries(selectedVersion.answers as Record<string, string>).map(([q, a]) => (
                      <div key={q} className="rounded-lg border border-[var(--color-border)] p-4">
                        <p className="text-sm font-medium">{q}</p>
                        <p className="mt-1 text-sm text-[var(--color-fg-muted)]">{a}</p>
                      </div>
                    ))
                  )}
                </div>
              )}
              {tab === "analysis" && (
                selectedVersion.analysis ? (
                  <Markdown className="text-sm text-[var(--color-fg-muted)]">{selectedVersion.analysis}</Markdown>
                ) : (
                  <p className="text-[var(--color-fg-subtle)]">Analisa belum dihasilkan.</p>
                )
              )}
              {tab === "prd" && (
                selectedVersion.prd ? (
                  <Markdown className="text-sm text-[var(--color-fg-muted)]">{selectedVersion.prd}</Markdown>
                ) : (
                  <p className="text-[var(--color-fg-subtle)]">PRD belum dihasilkan.</p>
                )
              )}
              {tab === "architecture" && (
                selectedVersion.architecture ? (
                  <Markdown className="text-sm text-[var(--color-fg-muted)]">{selectedVersion.architecture}</Markdown>
                ) : (
                  <p className="text-[var(--color-fg-subtle)]">Arsitektur belum dihasilkan.</p>
                )
              )}
              {tab === "erd" && (
                selectedVersion.erd ? (
                  <ErdDiagram erd={selectedVersion.erd} />
                ) : (
                  <p className="text-[var(--color-fg-subtle)]">ERD belum dihasilkan.</p>
                )
              )}
              {tab === "phases" && (
                <div className="space-y-3">
                  {phases.length === 0 && <p className="text-[var(--color-fg-subtle)]">Phases belum dihasilkan.</p>}
                  {phases.map((ph) => (
                    <div key={ph.key} className="rounded-xl border border-[var(--color-border)] p-4">
                      <div className="flex items-center justify-between">
                        <h4 className="font-semibold">{ph.title}</h4>
                        {ph.tasks && <Badge tone="muted">{ph.tasks.length} task</Badge>}
                      </div>
                      {ph.tasks && (
                        <ul className="mt-2 space-y-1 text-sm text-[var(--color-fg-muted)]">
                          {ph.tasks.map((t, i) => <li key={i}>• {t}</li>)}
                        </ul>
                      )}
                      {ph.prompt && (
                        <details className="mt-3">
                          <summary className="cursor-pointer text-xs text-[var(--color-brand)] hover:underline">Lihat prompt</summary>
                          <pre className="mt-2 text-xs text-[var(--color-fg-muted)] whitespace-pre-wrap">{ph.prompt}</pre>
                        </details>
                      )}
                    </div>
                  ))}
                </div>
              )}
              {tab === "activities" && (
                <div className="space-y-2">
                  {activitiesLoading ? (
                    <p className="text-sm text-[var(--color-fg-muted)]"><Loader2 className="animate-spin inline mr-2" size={14} />Memuat aktivitas...</p>
                  ) : activities.length === 0 ? (
                    <p className="text-[var(--color-fg-subtle)]">Belum ada aktivitas.</p>
                  ) : (
                    activities.map(a => (
                      <div key={a.id} className="flex items-start gap-3 rounded-lg border border-[var(--color-border)] p-3">
                        <div className="mt-0.5 grid h-7 w-7 place-items-center rounded-full bg-[var(--color-surface-2)] text-[var(--color-fg-muted)]">
                          <History size={13} />
                        </div>
                        <div className="flex-1 min-w-0">
                          <p className="text-sm">{a.description}</p>
                          <p className="mt-0.5 text-xs text-[var(--color-fg-subtle)]">
                            {a.user.name} &middot; {new Date(a.created_at).toLocaleString("id-ID")}
                          </p>
                        </div>
                      </div>
                    ))
                  )}
                </div>
              )}
              {tab === "mobile" && (
                <div className="space-y-4">
                  {!selectedVersion?.mobile_phases && !selectedVersion?.mobile_master_prompt && (
                    <p className="text-[var(--color-fg-subtle)]">Belum ada output mobile. Pipeline untuk target mobile harus dijalankan.</p>
                  )}
                  {mobilePhases.length > 0 && (
                    <div>
                      <h4 className="mb-3 font-semibold">Mobile Phases</h4>
                      <div className="space-y-3">
                        {mobilePhases.map((ph) => (
                          <div key={ph.key} className="rounded-xl border border-[var(--color-border)] p-4">
                            <div className="flex items-center justify-between">
                              <h4 className="font-semibold">{ph.title}</h4>
                              {ph.tasks && <Badge tone="muted">{ph.tasks.length} task</Badge>}
                            </div>
                            {ph.tasks && (
                              <ul className="mt-2 space-y-1 text-sm text-[var(--color-fg-muted)]">
                                {ph.tasks.map((t, i) => <li key={i}>• {t}</li>)}
                              </ul>
                            )}
                            {ph.prompt && (
                              <details className="mt-3">
                                <summary className="cursor-pointer text-xs text-[var(--color-brand)] hover:underline">Lihat prompt</summary>
                                <pre className="mt-2 text-xs text-[var(--color-fg-muted)] whitespace-pre-wrap">{ph.prompt}</pre>
                              </details>
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
                        <Button variant="secondary" size="sm" onClick={() => copyToClipboard(selectedVersion.mobile_master_prompt as string)}>
                          <Copy size={13} /> Salin
                        </Button>
                      </div>
                      <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{selectedVersion.mobile_master_prompt}</Markdown>
                    </Card>
                  )}
                </div>
              )}
            </div>
          </Card>

          {/* Sidebar: progress checklist */}
          <div className="space-y-4">
            <Card className="p-5">
              <div className="mb-3 flex items-center justify-between">
                <h3 className="font-semibold">Progress</h3>
                <span className="text-sm text-[var(--color-fg-muted)]">{progress}%</span>
              </div>
              <div className="h-2 rounded-full bg-[var(--color-surface-2)] overflow-hidden">
                <div className="h-full bg-[var(--color-brand)] transition-all" style={{ width: `${progress}%` }} />
              </div>
              <div className="mt-4 space-y-2">
                {phases.length === 0 && <p className="text-xs text-[var(--color-fg-subtle)]">Checklist akan muncul setelah phases dibuat.</p>}
                {phases.map((ph) => (
                  <button
                    key={ph.key}
                    onClick={() => handleTogglePhase(ph.key, doneMap[ph.key] || false)}
                    data-testid={`phase-toggle-${ph.key}`}
                    className="flex w-full items-center gap-3 rounded-lg px-2 py-2 text-left text-sm transition hover:bg-[var(--color-surface-2)]"
                  >
                    <span className={`grid h-5 w-5 place-items-center rounded-md border transition ${
                      doneMap[ph.key] ? "border-[var(--color-brand)] bg-[var(--color-brand)] text-white" : "border-[var(--color-border)]"
                    }`}>
                      {doneMap[ph.key] && <Check size={13} />}
                    </span>
                    <span className={doneMap[ph.key] ? "text-[var(--color-fg-subtle)] line-through" : ""}>{ph.title}</span>
                  </button>
                ))}
              </div>
            </Card>

            <Card className="p-5">
              <h3 className="mb-3 font-semibold">Master Prompt</h3>
              <p className="text-sm text-[var(--color-fg-muted)]">
                {selectedVersion.master_prompt ? "Prompt gabungan siap disuapkan ke AI coding agent." : "Master prompt akan muncul setelah semua stage selesai."}
              </p>
              <Button
                variant="secondary"
                size="sm"
                className="mt-4 w-full"
                disabled={!selectedVersion.master_prompt}
                onClick={() => selectedVersion.master_prompt && copyToClipboard(selectedVersion.master_prompt as string)}
              >
                <Copy size={15} /> Salin Master Prompt
              </Button>
              {selectedVersion.mobile_master_prompt && (
                <Button
                  variant="secondary"
                  size="sm"
                  className="mt-2 w-full"
                  onClick={() => copyToClipboard(selectedVersion.mobile_master_prompt as string)}
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
                      <span className={`inline-block h-2 w-2 rounded-full ${selectedVersion.standards ? 'bg-green-500' : 'bg-yellow-500'}`} />
                      <span className="text-xs">{selectedVersion.standards ? 'STANDARDS.md tersedia' : 'STANDARDS.md belum tersedia'}</span>
                    </div>
                    {selectedVersion.standards ? (
                      <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${selectedVersion.id}/standards`, '_blank')}>
                        <Copy size={13} /> Download
                      </Button>
                    ) : (
                      <Button variant="secondary" size="sm" onClick={async () => {
                        try { await apiPost(`/versions/${selectedVersion.id}/regenerate-standards`); fetchVersion(selectedVersion.id); } catch (err) { setError(err instanceof Error ? err.message : 'Gagal generate'); }
                      }}>
                        <Copy size={13} /> Generate
                      </Button>
                    )}
                  </div>
                  <div className="flex items-center justify-between">
                    <div className="flex items-center gap-2">
                      <span className={`inline-block h-2 w-2 rounded-full ${selectedVersion.agents ? 'bg-green-500' : 'bg-yellow-500'}`} />
                      <span className="text-xs">{selectedVersion.agents ? 'AGENTS.md tersedia' : 'AGENTS.md belum tersedia'}</span>
                    </div>
                    {selectedVersion.agents ? (
                      <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${selectedVersion.id}/agents`, '_blank')}>
                        <Copy size={13} /> Download
                      </Button>
                    ) : (
                      <Button variant="secondary" size="sm" onClick={async () => {
                        try { await apiPost(`/versions/${selectedVersion.id}/regenerate-standards`); fetchVersion(selectedVersion.id); } catch (err) { setError(err instanceof Error ? err.message : 'Gagal generate'); }
                      }}>
                        <Copy size={13} /> Generate
                      </Button>
                    )}
                  </div>
                  {selectedVersion.mobile_standards || selectedVersion.mobile_agents ? (
                    <>
                      <hr className="border-[var(--color-border)]" />
                      <p className="text-xs font-medium text-[var(--color-fg-muted)]">Mobile (Flutter)</p>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span className={`inline-block h-2 w-2 rounded-full ${selectedVersion.mobile_standards ? 'bg-green-500' : 'bg-yellow-500'}`} />
                          <span className="text-xs">{selectedVersion.mobile_standards ? 'STANDARDS-MOBILE.md tersedia' : 'STANDARDS-MOBILE.md belum tersedia'}</span>
                        </div>
                        {selectedVersion.mobile_standards ? (
                          <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${selectedVersion.id}/standards/mobile`, '_blank')}>
                            <Copy size={13} /> Download
                          </Button>
                        ) : (
                          <Button variant="secondary" size="sm" onClick={async () => {
                            try { await apiPost(`/versions/${selectedVersion.id}/regenerate-standards/mobile`); fetchVersion(selectedVersion.id); } catch (err) { setError(err instanceof Error ? err.message : 'Gagal generate'); }
                          }}>
                            <Copy size={13} /> Generate
                          </Button>
                        )}
                      </div>
                      <div className="flex items-center justify-between">
                        <div className="flex items-center gap-2">
                          <span className={`inline-block h-2 w-2 rounded-full ${selectedVersion.mobile_agents ? 'bg-green-500' : 'bg-yellow-500'}`} />
                          <span className="text-xs">{selectedVersion.mobile_agents ? 'AGENTS-MOBILE.md tersedia' : 'AGENTS-MOBILE.md belum tersedia'}</span>
                        </div>
                        {selectedVersion.mobile_agents ? (
                          <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${selectedVersion.id}/agents/mobile`, '_blank')}>
                            <Copy size={13} /> Download
                          </Button>
                        ) : (
                          <Button variant="secondary" size="sm" onClick={async () => {
                            try { await apiPost(`/versions/${selectedVersion.id}/regenerate-standards/mobile`); fetchVersion(selectedVersion.id); } catch (err) { setError(err instanceof Error ? err.message : 'Gagal generate'); }
                          }}>
                            <Copy size={13} /> Generate
                          </Button>
                        )}
                      </div>
                    </>
                  ) : null}
                </div>
              </Card>
            )}

            {selectedVersion.stage_status && (
              <Card className="p-5">
                <h3 className="mb-3 font-semibold">Pipeline</h3>
                <div className="space-y-1.5">
                  {getStages(project!.target as Target).map((s) => {
                    const st = (selectedVersion.stage_status as Record<string, string>)[s.key];
                    return (
                      <div key={s.key} className="flex items-center gap-2 text-xs">
                        <span>
                          {st === 'done' ? '✅' : st === 'running' ? '⏳' : st === 'error' ? '❌' : '⭕'}
                        </span>
                        <span className={st === 'done' ? 'text-[var(--color-fg-subtle)]' : ''}>{s.label}</span>
                      </div>
                    );
                  })}
                </div>
                {!getStages(project!.target as Target).every(s => (selectedVersion.stage_status as Record<string, string>)[s.key] === 'done') && (
                  <Button
                    size="sm"
                    className="mt-3 w-full"
                    onClick={() => router.push(`/new?resume=1&version=${selectedVersion.id}`)}
                  >
                    <Play size={14} /> Lanjutkan Pipeline
                  </Button>
                )}
              </Card>
            )}

            {/* Progress Bangun — real-time tracking */}
            {((selectedVersion?.phases?.length ?? 0) > 0 || (selectedVersion?.mobile_phases?.length ?? 0) > 0) && (
              <Card className="p-5">
                <h3 className="mb-3 font-semibold">Progress Bangun</h3>
                {phases.length > 0 && (
                  <div className="mb-4">
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">Web</span>
                      <span className="text-[var(--color-fg-muted)]">{doneCount}/{phases.length} fase · {progress}%</span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div className="h-full rounded-full bg-blue-500 transition-all duration-500" style={{ width: `${progress}%` }} />
                    </div>
                    <div className="mt-2 space-y-1">
                      {phases.map((ph) => {
                        const isDone = doneMap[ph.key];
                        return (
                          <div key={ph.key} className="flex items-center gap-2 text-xs">
                            <span>{isDone ? '✅' : '⏳'}</span>
                            <span className={isDone ? 'text-[var(--color-fg-subtle)] line-through' : ''}>{ph.title}</span>
                          </div>
                        );
                      })}
                    </div>
                  </div>
                )}
                {mobilePhases.length > 0 && (
                  <div>
                    <div className="flex items-center justify-between text-sm">
                      <span className="font-medium">Mobile</span>
                      <span className="text-[var(--color-fg-muted)]">{mobileDoneCount}/{mobilePhases.length} fase · {mobileProgress}%</span>
                    </div>
                    <div className="mt-1 h-2 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                      <div className="h-full rounded-full bg-emerald-500 transition-all duration-500" style={{ width: `${mobileProgress}%` }} />
                    </div>
                    <div className="mt-2 space-y-1">
                      {mobilePhases.map((ph) => {
                        const isDone = doneMap[ph.key];
                        return (
                          <button
                            key={ph.key}
                            onClick={() => handleTogglePhase(ph.key, isDone || false)}
                            className="flex w-full items-center gap-2 rounded-lg px-2 py-1.5 text-left text-xs transition hover:bg-[var(--color-surface-2)]"
                          >
                            <span className={`grid h-4 w-4 place-items-center rounded border transition ${
                              isDone ? 'border-emerald-500 bg-emerald-500 text-white' : 'border-[var(--color-border)]'
                            }`}>
                              {isDone && <Check size={10} />}
                            </span>
                            <span className={isDone ? 'text-[var(--color-fg-subtle)] line-through' : ''}>{ph.title}</span>
                          </button>
                        );
                      })}
                    </div>
                  </div>
                )}
                <p className="mt-3 text-[10px] text-[var(--color-fg-subtle)]">
                  Status diperbarui real-time oleh AI agent via webhook.
                </p>
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
      {/* Edit Project Modal */}
      {editingProject && project && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="mx-4 w-full max-w-lg rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 shadow-2xl">
            <div className="mb-4 flex items-center justify-between">
              <h3 className="text-lg font-semibold">Edit Project</h3>
              <button onClick={() => setEditingProject(false)} className="text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">
                <X size={18} />
              </button>
            </div>
            <div className="space-y-4">
              <div>
                <label className="mb-1 block text-sm font-medium">Judul Project</label>
                <input
                  type="text" value={editTitle}
                  onChange={(e) => setEditTitle(e.target.value)}
                  className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Ide Aplikasi</label>
                <textarea
                  rows={3} value={editIdea}
                  onChange={(e) => setEditIdea(e.target.value)}
                  className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm resize-y"
                />
              </div>
              <div>
                <label className="mb-1 block text-sm font-medium">Target Platform</label>
                <select
                  value={editTarget}
                  onChange={(e) => setEditTarget(e.target.value as Target)}
                  className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                >
                  <option value="web">Web</option>
                  <option value="mobile">Mobile (APK)</option>
                  <option value="both">Web + Mobile</option>
                </select>
              </div>
              <div className="flex justify-end gap-2">
                <Button variant="secondary" size="sm" onClick={() => setEditingProject(false)}>Batal</Button>
                <Button
                  size="sm"
                  disabled={savingProject || !editTitle.trim()}
                  onClick={async () => {
                    setSavingProject(true);
                    try {
                      await apiPatch(`/projects/${project.id}`, { title: editTitle.trim(), idea: editIdea, target: editTarget });
                      setProject(prev => prev ? { ...prev, title: editTitle.trim(), idea: editIdea, target: editTarget } : prev);
                      setEditingProject(false);
                    } catch (err) {
                      setError(err instanceof Error ? err.message : 'Gagal menyimpan');
                    } finally {
                      setSavingProject(false);
                    }
                  }}
                >
                  {savingProject ? <Loader2 size={14} className="animate-spin" /> : <Check size={14} />} Simpan
                </Button>
              </div>
            </div>
          </div>
        </div>
      )}
    </ErrorBoundary>
  );
}

interface ApiToken {
  id: number;
  name: string;
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

function ApiTokenSection({ projectId }: { projectId: string }) {
  const [tokens, setTokens] = useState<ApiToken[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [tokenName, setTokenName] = useState("");
  const [newToken, setNewToken] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [tokenError, setTokenError] = useState("");

  useEffect(() => {
    apiGet<ApiToken[]>(`/projects/${projectId}/tokens`)
      .then(setTokens)
      .catch((err) => console.error('Failed to load API tokens:', err))
      .finally(() => setLoading(false));
  }, [projectId]);

  async function handleCreate() {
    if (!tokenName.trim()) return;
    setCreating(true);
    try {
      const res = await apiPost<{ token: string; id: number; name: string }>(`/projects/${projectId}/tokens`, { name: tokenName });
      setNewToken(res.token);
      setTokens(prev => [...prev, { id: res.id, name: res.name, last_used_at: null, expires_at: null, created_at: new Date().toISOString() }]);
      setTokenName("");
      setShowForm(false);
    } catch (err) {
      setTokenError(err instanceof Error ? err.message : "Gagal membuat token");
    } finally {
      setCreating(false);
    }
  }

  async function handleDelete(tokenId: number) {
    if (!window.confirm("Yakin ingin menghapus token ini?")) return;
    try {
      await apiDelete(`/projects/${projectId}/tokens/${tokenId}`);
      setTokens(prev => prev.filter(t => t.id !== tokenId));
    } catch (err) {
      setTokenError(err instanceof Error ? err.message : "Gagal menghapus token");
    }
  }

  return (
    <Card className="mt-6 p-5">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold">API Tokens</h3>
        <Button size="sm" onClick={() => setShowForm(!showForm)}>
          {showForm ? "Batal" : "Buat Token"}
        </Button>
      </div>

      {tokenError && (
        <p className="mt-2 text-sm text-red-500">{tokenError}</p>
      )}

      {showForm && (
        <div className="mt-4 flex items-center gap-2">
          <input
            type="text"
            value={tokenName}
            onChange={(e) => setTokenName(e.target.value)}
            placeholder="Nama token..."
            className="flex-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-1.5 text-sm text-[var(--color-fg)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
          />
          <Button size="sm" onClick={handleCreate} disabled={creating}>
            {creating ? <Loader2 size={14} className="animate-spin" /> : "Simpan"}
          </Button>
        </div>
      )}

      {newToken && (
        <div className="mt-4 rounded-lg border border-[var(--color-warning)]/40 bg-[var(--color-warning)]/10 px-4 py-3 text-sm">
          <p className="font-medium text-[var(--color-warning)]">Token baru dibuat</p>
          <pre className="mt-2 overflow-auto rounded bg-[var(--color-surface-1)] p-3 text-xs">{newToken}</pre>
          <p className="mt-1 text-xs text-[var(--color-fg-muted)]">Salin token sekarang. Tidak bisa dilihat lagi nanti.</p>
        </div>
      )}

      {loading && <div className="mt-4 text-center text-sm text-[var(--color-fg-muted)]"><Loader2 className="animate-spin inline" size={14} /></div>}

      {!loading && tokens.length === 0 && (
        <p className="mt-4 text-sm text-[var(--color-fg-subtle)]">Belum ada API token.</p>
      )}

      {tokens.length > 0 && (
        <div className="mt-4 space-y-2">
          {tokens.map((t) => (
            <div key={t.id} className="flex items-center justify-between rounded-lg border border-[var(--color-border)] px-4 py-2.5 text-sm">
              <div>
                <span className="font-medium">{t.name}</span>
                {t.last_used_at && <span className="ml-2 text-xs text-[var(--color-fg-muted)]">Terakhir digunakan {new Date(t.last_used_at).toLocaleDateString("id-ID")}</span>}
              </div>
              <button
                onClick={() => handleDelete(t.id)}
                className="text-xs text-[var(--color-danger)] hover:underline"
              >
                Hapus
              </button>
            </div>
          ))}
        </div>
      )}
    </Card>
  );
}
