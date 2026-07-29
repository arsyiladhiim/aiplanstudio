"use client";
import { useRouter } from "next/navigation";
import { useState, useEffect } from "react";
import { notFound } from "next/navigation";
import { use } from "react";
import { Card, Badge } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { TargetBadge } from "@/components/common";
import { ErdDiagram } from "@/components/wizard/ErdDiagram";
import { STAGES } from "@/lib/mock";
import { apiGet, apiPost, apiDelete, apiPatch, type Project, type Version } from "@/lib/api";
import {
  ArrowLeft, GitBranch, Download, Plus, Copy, FileText, Database, ListChecks, Check, Loader2, Play, Trash2,
} from "lucide-react";

const TABS = [
  { key: "analysis", label: "Analisa", icon: FileText },
  { key: "prd", label: "PRD", icon: FileText },
  { key: "architecture", label: "Arsitektur", icon: FileText },
  { key: "erd", label: "ERD", icon: Database },
  { key: "phases", label: "Phases", icon: ListChecks },
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
    setLastRefreshed(new Date());
    setCountdown(0);
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
  const phaseProgress = selectedVersion?.phaseProgress as Array<{ phase_key: string; done: boolean }> || [];
  const doneMap = Object.fromEntries(phaseProgress.map(p => [p.phase_key, p.done]));
  const doneCount = phaseProgress.filter(p => p.done).length;
  const progress = phases.length > 0 ? Math.round((doneCount / phases.length) * 100) : 0;

  return (
    <>
      <ButtonLink href="/projects" variant="ghost" size="sm" className="mb-4"><ArrowLeft size={16} /> Projects</ButtonLink>

      {/* Header */}
      <div className="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{project.title}</h1>
            <TargetBadge target={project.target} />
          </div>
          <p className="mt-1.5 max-w-2xl text-sm text-[var(--color-fg-muted)]">{project.idea}</p>
        </div>
        <div className="flex shrink-0 items-center gap-2">
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
          <button
            key={v.id}
            onClick={() => fetchVersion(v.id)}
            data-testid={`version-${v.version_no}`}
            disabled={versionLoading}
            className={`inline-flex items-center gap-1.5 rounded-full px-3 py-1.5 text-sm font-medium transition ${
              selectedVersion?.id === v.id
                ? "bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-brand)]"
                : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
            }`}
          >
            <GitBranch size={13} /> v{v.version_no}
          </button>
        ))}
      </div>

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
                  const content = tab === 'erd' ? JSON.stringify(selectedVersion.erd, null, 2) :
                    tab === 'phases' ? JSON.stringify(selectedVersion.phases, null, 2) :
                    selectedVersion[tab] || '';
                  copyToClipboard(content);
                }}
                className="ml-auto mr-3 my-auto inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
              >
                <Copy size={13} /> Salin
              </button>
            </div>

            <div className="p-5 max-h-[600px] overflow-auto">
              {tab === "analysis" && (
                <article className="prose prose-sm max-w-none">
                  {selectedVersion.analysis ? (
                    <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{selectedVersion.analysis}</pre>
                  ) : (
                    <p className="text-[var(--color-fg-subtle)]">Analisa belum dihasilkan.</p>
                  )}
                </article>
              )}
              {tab === "prd" && (
                <article className="prose prose-sm max-w-none">
                  {selectedVersion.prd ? (
                    <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{selectedVersion.prd}</pre>
                  ) : (
                    <p className="text-[var(--color-fg-subtle)]">PRD belum dihasilkan.</p>
                  )}
                </article>
              )}
              {tab === "architecture" && (
                <article className="prose prose-sm max-w-none">
                  {selectedVersion.architecture ? (
                    <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{selectedVersion.architecture}</pre>
                  ) : (
                    <p className="text-[var(--color-fg-subtle)]">Arsitektur belum dihasilkan.</p>
                  )}
                </article>
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
                      <Button variant="secondary" size="sm" onClick={() => {
                        apiPost(`/versions/${selectedVersion.id}/regenerate-standards`).then(() => window.location.reload()).catch(err => alert(err.message));
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
                      <Button variant="secondary" size="sm" onClick={() => {
                        apiPost(`/versions/${selectedVersion.id}/regenerate-standards`).then(() => window.location.reload()).catch(err => alert(err.message));
                      }}>
                        <Copy size={13} /> Generate
                      </Button>
                    )}
                  </div>
                </div>
              </Card>
            )}

            {selectedVersion.stage_status && (
              <Card className="p-5">
                <h3 className="mb-3 font-semibold">Pipeline</h3>
                <div className="space-y-1.5">
                  {STAGES.map((s) => {
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
                {!STAGES.every(s => (selectedVersion.stage_status as Record<string, string>)[s.key] === 'done') && (
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

            {lastRefreshed && (
              <div className="text-center text-[10px] text-[var(--color-fg-subtle)]">
                otomatis {countdown}s yang lalu
              </div>
            )}
          </div>
        </div>
      )}
    </>
  );
}
