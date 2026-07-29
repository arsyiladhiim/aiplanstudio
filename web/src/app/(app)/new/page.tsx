"use client";
import { useState, useRef, useCallback, useEffect, use } from "react";
import { useRouter } from "next/navigation";
import { Card, Badge, Textarea, Label } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { ErdDiagram } from "@/components/wizard/ErdDiagram";
import { STAGES, type StageKey, type StageState, type Target } from "@/lib/mock";
import { apiPost, apiGet, createSSE, type Project, type Template } from "@/lib/api";
import {
  Wand2, Globe, Smartphone, Layers, Loader2, Check, Copy, ArrowRight,
  RotateCcw, Zap, CircleDot, Sparkles, AlertCircle, Code2, Pencil,
} from "lucide-react";

const TARGETS: { key: Target; label: string; icon: typeof Globe }[] = [
  { key: "web", label: "Web App", icon: Globe },
  { key: "mobile", label: "Mobile (APK/iOS)", icon: Smartphone },
  { key: "both", label: "Keduanya", icon: Layers },
];

export default function NewPlanPage({ searchParams }: { searchParams: Promise<{ resume?: string; version?: string }> }) {
  const router = useRouter();
  const params = use(searchParams);
  const isResume = params.resume === '1';
  const resumeVersionId = params.version ? Number(params.version) : null;
  const [started, setStarted] = useState(false);
  const [idea, setIdea] = useState("");
  const [title, setTitle] = useState("");
  const [target, setTarget] = useState<Target>("web");
  const [stack, setStack] = useState("");
  const [auto, setAuto] = useState(false);
  const [templates, setTemplates] = useState<Template[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState<string>("");
  const [current, setCurrent] = useState(0);
  const [status, setStatus] = useState<Record<StageKey, StageState>>(
    Object.fromEntries(STAGES.map((s) => [s.key, "pending"])) as Record<StageKey, StageState>
  );

  // Real backend integration states
  const [projectId, setProjectId] = useState<number | null>(null);
  const [versionId, setVersionId] = useState<number | null>(null);
  const [artifacts, setArtifacts] = useState<Record<StageKey, string>>({} as Record<StageKey, string>);
  const [error, setError] = useState<string>("");
  const [creating, setCreating] = useState(false);
  const [editingStage, setEditingStage] = useState<StageKey | null>(null);
  const [editContent, setEditContent] = useState("");

  const eventSourceRef = useRef<EventSource | null>(null);
  const cancelled = useRef(false);
  const fallbackFetched = useRef(new Set<string>());
  const outputRef = useRef<HTMLDivElement>(null);

  const allDone = STAGES.every((s) => status[s.key] === "done");
  const activeKey = STAGES[current].key;

  // Load templates on mount
  useEffect(() => {
    apiGet<Template[]>("/templates").then(setTemplates).catch(() => {});
  }, []);

  // Apply template seed when selected
  useEffect(() => {
    if (!selectedTemplate) return;
    const tpl = templates.find(t => String(t.id) === selectedTemplate);
    if (!tpl || !tpl.seed) return;
    const seed = tpl.seed as Record<string, string>;
    if (seed.title) setTitle(seed.title);
    if (seed.idea) setIdea(seed.idea);
    if (seed.stack) setStack(seed.stack);
    if (seed.target && ["web","mobile","both"].includes(seed.target)) setTarget(seed.target as Target);
  }, [selectedTemplate, templates]);

  // Cleanup EventSource on unmount
  useEffect(() => {
    return () => {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
        eventSourceRef.current = null;
      }
    };
  }, []);

  // Fallback: fetch artifact from DB when SSE artifact event was lost
  useEffect(() => {
    if (!versionId) return;
    for (const stage of STAGES) {
      if (status[stage.key] !== 'done') continue;
      if (artifacts[stage.key]) continue;
      if (fallbackFetched.current.has(stage.key)) continue;
      fallbackFetched.current.add(stage.key);

      const colMap: Record<string, string> = {
        analisa: 'analysis', prd: 'prd', architecture: 'architecture',
        erd: 'erd', phased_master: 'master_prompt',
      };
      const col = colMap[stage.key];
      if (!col) continue;

      apiGet<Record<string, any>>(`/versions/${versionId}`).then(v => {
        const content = v[col];
        setArtifacts(prev => {
          if (prev[stage.key]) return prev;
          if (content == null || content === '') return prev;
          return { ...prev, [stage.key]: typeof content === 'string' ? content : JSON.stringify(content, null, 2) };
        });
      }).catch(() => {});
      break;
    }
  }, [status, versionId]);

  // Resume mode: load existing version data
  useEffect(() => {
    if (!isResume || !resumeVersionId || started) return;

    apiGet<Version>(`/versions/${resumeVersionId}`).then(v => {
      setProjectId(v.project?.id ?? null);
      setVersionId(v.id);
      setTitle(v.project?.title ?? '');
      setIdea(v.project?.idea ?? '');
      setTarget(v.project?.target ?? 'web');
      setStack(v.project?.stack ?? '');
      setStarted(true);

      const firstIdx = STAGES.findIndex(s => (v.stage_status as Record<string, string>)?.[s.key] !== 'done');
      setCurrent(Math.max(0, firstIdx));

      const loadedStatus = Object.fromEntries(STAGES.map(s => [s.key, (v.stage_status as Record<string, string>)?.[s.key] || 'pending'])) as Record<StageKey, StageState>;
      // Reset error stages to pending so resume can retry
      STAGES.forEach(s => { if (loadedStatus[s.key] === 'error') loadedStatus[s.key] = 'pending'; });
      setStatus(loadedStatus);

      const colMap: Record<string, string> = { analisa: 'analysis', prd: 'prd', architecture: 'architecture', erd: 'erd', phased_master: 'master_prompt' };
      const loaded: Record<string, string> = {};
      STAGES.forEach(s => {
        const val = (v as any)[colMap[s.key]];
        if (val) loaded[s.key] = typeof val === 'object' ? JSON.stringify(val) : String(val);
      });
      setArtifacts(loaded as Record<StageKey, string>);

      if (firstIdx >= 0) startPipeline(v.id, STAGES[firstIdx].key);
    }).catch(err => setError(err instanceof Error ? err.message : 'Gagal memuat data project'));
  }, []);

  // Auto-scroll modal output
  useEffect(() => {
    if (outputRef.current) {
      outputRef.current.scrollTop = outputRef.current.scrollHeight;
    }
  }, [artifacts[activeKey]]);

  // Handle SSE events
  const handleSSEEvent = useCallback((event: string, data: any) => {
    if (cancelled.current) return;

    switch (event) {
      case 'status':
        // data: {stage: "analisa", state: "running" | "done"}
        if (data.stage && typeof data.stage === 'string') {
          setStatus(s => ({ ...s, [data.stage]: data.state }));
          if (data.state === 'running') {
            const idx = STAGES.findIndex(s => s.key === data.stage);
            if (idx >= 0) setCurrent(idx);
          }
        }
        break;

      case 'token':
        // data: {stage: "analisa", delta: "text..."}
        // Append streaming tokens to artifact
        if (data.stage && typeof data.stage === 'string') {
          setArtifacts(prev => ({
            ...prev,
            [data.stage]: ((prev as any)[data.stage] || '') + data.delta
          }));
        }
        break;

      case 'artifact':
        // data: {stage: "analisa", content: "...final..."}
        if (data.stage && typeof data.stage === 'string') {
          setArtifacts(prev => ({ ...prev, [data.stage]: data.content }));
        }
        break;

      case 'done':
        // data: {stage: "analisa"}
        const stageIndex = STAGES.findIndex(s => s.key === data.stage);
        if (stageIndex >= 0 && data.stage) {
          setCurrent(stageIndex);
          setStatus(s => ({ ...s, [data.stage]: 'done' }));
        }
        break;

      case 'fail':
        // data: {stage: "analisa", message: "..."}
        setError(data.message || 'Terjadi kesalahan.');
        if (data.stage && typeof data.stage === 'string') {
          setStatus(s => ({ ...s, [data.stage]: 'error' }));
        }
        if (eventSourceRef.current) {
          eventSourceRef.current.close();
          eventSourceRef.current = null;
        }
        break;
    }
  }, []);

  async function start() {
    if (!title.trim() || !idea.trim()) return;

    setCreating(true);
    setError("");
    cancelled.current = false;

    try {
      // Step 1: Create project (auto-creates version 1)
      const project = await apiPost<Project>("/projects", {
        title: title.trim(),
        idea,
        target,
        stack: stack || undefined,
      });

      setProjectId(project.id);

      // Step 2: Get version 1 from project response
      // Need to fetch project with versions
      const projectWithVersions = await apiGet<Project & { versions: Array<{ id: number }> }>(`/projects/${project.id}`);
      const versionId = projectWithVersions.versions?.[0]?.id;

      if (!versionId) {
        throw new Error("Version tidak ditemukan");
      }

      setVersionId(versionId);
      setStarted(true);
      setCreating(false);

      // Step 3: Start SSE pipeline
      startPipeline(versionId);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal membuat project');
      setCreating(false);
      console.error('Failed to create project:', err);
    }
  }

  function startPipeline(versionId: number, stage?: string) {
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }

    const s = stage || STAGES[0].key;
    const idx = STAGES.findIndex(x => x.key === s);
    if (idx >= 0) setCurrent(idx);
    setStatus(prev => ({ ...prev, [s]: 'running' }));

    const autoParam = auto ? '1' : '0';

    eventSourceRef.current = createSSE(
      `/generate/stream?version=${versionId}&stage=${s}&auto=${autoParam}`,
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    );
  }

  function approveNext() {
    if (!versionId || current + 1 >= STAGES.length) return;

    const nextStage = STAGES[current + 1].key;

    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }

    eventSourceRef.current = createSSE(
      `/generate/stream?version=${versionId}&stage=${nextStage}&auto=0`,
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    );
  }

  function retryStage() {
    if (!versionId) return;

    const currentStage = STAGES[current].key;

    // Reset current stage status
    setStatus(s => ({ ...s, [currentStage]: 'pending' }));
    setArtifacts(prev => {
      const newArtifacts = { ...prev };
      delete (newArtifacts as any)[currentStage];
      return newArtifacts;
    });
    setError("");

    // Close existing connection and restart
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }

    eventSourceRef.current = createSSE(
      `/generate/stream?version=${versionId}&stage=${currentStage}&auto=0`,
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    );
  }

  function cancelGeneration() {
    cancelled.current = true;
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
      eventSourceRef.current = null;
    }
    setStatus(s => ({ ...s, [activeKey]: 'error' }));
    setError("Pembuatan plan dibatalkan.");
  }

  function reset() {
    cancelled.current = true;
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
      eventSourceRef.current = null;
    }
    fallbackFetched.current.clear();
    setStarted(false);
    setCurrent(0);
    setStatus(Object.fromEntries(STAGES.map((s) => [s.key, "pending"])) as Record<StageKey, StageState>);
    setProjectId(null);
    setVersionId(null);
    setArtifacts({} as Record<StageKey, string>);
    setError("");
  }

  // ===== Input screen =====
  if (!started) {
    if (isResume) {
      return (
        <div className="mx-auto flex max-w-lg items-center justify-center py-24">
          <div className="text-center">
            <Loader2 size={32} className="mx-auto animate-spin text-[var(--color-brand)]" />
            <p className="mt-4 text-sm text-[var(--color-fg-muted)]">Memuat project...</p>
          </div>
        </div>
      );
    }

    return (
      <div className="mx-auto max-w-2xl">
        <div className="mb-6 text-center">
          <div className="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] text-white">
            <Wand2 size={26} />
          </div>
          <h1 className="text-2xl font-bold sm:text-3xl">Buat Plan Baru</h1>
          <p className="mt-2 text-[var(--color-fg-muted)]">Deskripsikan idemu, AI akan menyusun blueprint lengkap.</p>
        </div>

        <Card className="p-6">
          <div className="space-y-5">
            {templates.length > 0 && (
              <div>
                <Label htmlFor="template">Template (opsional)</Label>
                <select
                  id="template"
                  value={selectedTemplate}
                  onChange={(e) => setSelectedTemplate(e.target.value)}
                  className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm"
                  data-testid="template-select"
                >
                  <option value="">— Pilih template —</option>
                  {templates.map((t) => (
                    <option key={t.id} value={t.id}>{t.name} ({t.target})</option>
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
              <Label htmlFor="stack">Stack Teknologi (opsional)</Label>
              <input
                id="stack"
                type="text"
                value={stack}
                onChange={(e) => setStack(e.target.value)}
                placeholder="Misal: Laravel + React Native + PostgreSQL"
                className="w-full rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3 text-sm"
                data-testid="stack-input"
              />
            </div>

            <div>
              <Label>Target Platform</Label>
              <div className="grid grid-cols-3 gap-2">
                {TARGETS.map((t) => (
                  <button
                    key={t.key}
                    onClick={() => setTarget(t.key)}
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

            <label className="flex items-center gap-3 rounded-xl border border-[var(--color-border)] p-3">
              <input type="checkbox" checked={auto} onChange={(e) => setAuto(e.target.checked)} className="accent-[var(--color-brand)]" data-testid="auto-toggle" />
              <div className="flex-1">
                <div className="flex items-center gap-1.5 text-sm font-medium"><Zap size={14} className="text-[var(--color-brand)]" /> Auto-run semua tahap</div>
                <div className="text-xs text-[var(--color-fg-muted)]">Jalankan 6 tahap tanpa henti. Matikan untuk approve tiap tahap.</div>
              </div>
            </label>

            <Button onClick={start} disabled={!title.trim() || !idea.trim() || creating} className="w-full" size="lg" data-testid="start-plan">
              <Sparkles size={18} /> {creating ? "Membuat Project..." : "Buat Plan"}
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
    );
  }

  // ===== Pipeline screen =====
  return (
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Menyusun Plan…</h1>
          <p className="mt-1 line-clamp-1 text-sm text-[var(--color-fg-muted)]">{idea}</p>
        </div>
        <Button variant="secondary" size="sm" onClick={reset} data-testid="reset-plan"><RotateCcw size={15} /> Mulai Ulang</Button>
      </div>

      <div className="grid gap-6 lg:grid-cols-[280px_1fr]">
        {/* Stage tracker */}
        <div className="space-y-2">
          {STAGES.map((s, i) => {
            const st = status[s.key];
            return (
              <div
                key={s.key}
                data-testid={`stage-${s.key}`}
                data-state={st}
                className={`flex items-start gap-3 rounded-xl border p-3 transition ${
                  i === current && st === "running"
                    ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_8%,transparent)]"
                    : "border-[var(--color-border)]"
                }`}
              >
                <span className="mt-0.5">
                  {st === "done" ? (
                    <span className="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-success)] text-white"><Check size={14} /></span>
                  ) : st === "running" ? (
                    <span className="grid h-6 w-6 place-items-center rounded-full bg-[var(--color-brand)] text-white"><Loader2 size={14} className="animate-spin" /></span>
                  ) : (
                    <span className="grid h-6 w-6 place-items-center rounded-full border border-[var(--color-border)] text-[var(--color-fg-subtle)]"><CircleDot size={13} /></span>
                  )}
                </span>
                <div className="min-w-0">
                  <div className="text-sm font-medium">{s.label}</div>
                  <div className="text-xs text-[var(--color-fg-muted)]">{s.desc}</div>
                </div>
              </div>
            );
          })}
        </div>

        {/* Artifact panel */}
        <div className="space-y-4">
          <Card className="p-5">
            <div className="mb-3 flex items-center justify-between">
              <div className="flex items-center gap-2">
                <h3 className="font-semibold">{STAGES[current].label}</h3>
                {status[activeKey] === "running" && <Badge tone="brand">Menyusun…</Badge>}
                {status[activeKey] === "done" && <Badge tone="success"><Check size={12} /> Selesai</Badge>}
              </div>
              <div className="flex items-center gap-1.5">
                {status[activeKey] === "done" && editingStage !== activeKey && (
                  <button
                    onClick={() => {
                      setEditingStage(activeKey);
                      setEditContent(artifacts[activeKey] || "");
                    }}
                    className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                  >
                    <Pencil size={13} /> Sunting
                  </button>
                )}
                <button
                  onClick={() => {
                    const text = artifacts[activeKey];
                    if (text) navigator.clipboard.writeText(text).catch(() => {
                      const ta = document.createElement('textarea');
                      ta.value = text;
                      ta.style.position = 'fixed';
                      ta.style.opacity = '0';
                      document.body.appendChild(ta);
                      ta.select();
                      document.execCommand('copy');
                      document.body.removeChild(ta);
                    });
                  }}
                  className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                >
                  <Copy size={13} /> Salin
                </button>
              </div>
            </div>

            {status[activeKey] === "running" ? (
              <div className="space-y-2">
                {[100, 90, 75, 95, 60].map((w, i) => (
                  <div key={i} className="shimmer h-3.5 rounded bg-[var(--color-surface-2)]" style={{ width: `${w}%` }} />
                ))}
              </div>
            ) : editingStage === activeKey ? (
              <div className="space-y-3">
                <textarea
                  value={editContent}
                  onChange={(e) => setEditContent(e.target.value)}
                  className="w-full min-h-[200px] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3 text-sm font-mono text-[var(--color-fg)] resize-y focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
                />
                <div className="flex items-center gap-2">
                  <button
                    onClick={() => {
                      setArtifacts(prev => ({ ...prev, [activeKey]: editContent }));
                      setEditingStage(null);
                      setEditContent("");
                    }}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-accent)] px-3 py-1.5 text-xs text-white hover:opacity-90"
                  >
                    Simpan
                  </button>
                  <button
                    onClick={() => {
                      setEditingStage(null);
                      setEditContent("");
                    }}
                    className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
                  >
                    Batal
                  </button>
                </div>
              </div>
            ) : (
              <>
                {(activeKey === "erd" && status.erd === "done") || (activeKey === "architecture" && status.architecture === "done") || (activeKey === "phased_master" && status.phased_master === "done") ? null : (
                  <div className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">
                    {status[activeKey] === "done" && !artifacts[activeKey]
                      ? "Tidak ada output"
                      : artifacts[activeKey] || "Menunggu hasil AI..."}
                  </div>
                )}

                {activeKey === "architecture" && artifacts.architecture && (() => {
                  const text = artifacts.architecture;
                  const nodes: any[] = [];
                  const edges: any[] = [];

                  for (const line of text.split('\n')) {
                    const cm = line.match(/^KOMPONEN:\s*(.+?)\s*\|\s*(.+?)\s*\|\s*(.+)$/i);
                    if (cm) {
                      nodes.push({
                        id: cm[1].trim(),
                        label: cm[2].trim(),
                        fields: cm[3].split(',').map((f: string) => f.trim()),
                      });
                    }
                    const em = line.match(/^KONEKSI:\s*(.+?)\s*->\s*(.+?)\s*\|\s*(.+)$/i);
                    if (em) {
                      edges.push({ from: em[1].trim(), to: em[2].trim(), relation: em[3].trim() });
                    }
                  }

                  const cleanText = text
                    .replace(/^KOMPONEN:.*$/gm, '')
                    .replace(/^KONEKSI:.*$/gm, '')
                    .replace(/\n{3,}/g, '\n\n')
                    .trim();

                  return (
                    <>
                      {nodes.length > 0 && <div className="mb-6"><ErdDiagram erd={{ nodes, edges }} /></div>}
                      {cleanText && (
                        <div className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">
                          {cleanText}
                        </div>
                      )}
                    </>
                  );
                })()}

                {activeKey === "erd" && (
                  <>
                    {status.erd === "running" && (
                      <div className="mt-4 whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">
                        {artifacts[activeKey] || "Menunggu hasil AI..."}
                      </div>
                    )}
                    {status.erd === "done" && artifacts.erd && (() => {
                      try {
                        const erdData = JSON.parse(artifacts.erd);
                        return (
                          <>
                            <div className="mb-6 mt-4"><ErdDiagram erd={erdData} /></div>
                            {erdData.api_contract?.length > 0 && (
                              <div className="mt-6">
                                <h4 className="mb-3 font-semibold">API Contract</h4>
                                <div className="overflow-x-auto rounded-lg border border-[var(--color-border)]">
                                  <table className="w-full text-sm">
                                    <thead>
                                      <tr className="bg-[var(--color-surface-2)]">
                                        <th className="px-3 py-2 text-left font-medium">Method</th>
                                        <th className="px-3 py-2 text-left font-medium">Endpoint</th>
                                        <th className="px-3 py-2 text-left font-medium">Deskripsi</th>
                                        <th className="px-3 py-2 text-left font-medium">Auth</th>
                                      </tr>
                                    </thead>
                                    <tbody className="divide-y divide-[var(--color-border)]">
                                      {erdData.api_contract.map((api: any, i: number) => (
                                        <tr key={i}>
                                          <td className="px-3 py-2">
                                            <Badge tone={
                                              api.method === 'GET' ? 'success' :
                                              api.method === 'POST' ? 'brand' :
                                              api.method === 'PUT' || api.method === 'PATCH' ? 'warning' : 'danger'
                                            }>{api.method}</Badge>
                                          </td>
                                          <td className="px-3 py-2 font-mono text-xs">{api.path}</td>
                                          <td className="px-3 py-2 text-[var(--color-fg-muted)]">{api.description}</td>
                                          <td className="px-3 py-2">{api.auth ? '✅ Ya' : '❌ Tidak'}</td>
                                        </tr>
                                      ))}
                                    </tbody>
                                  </table>
                                </div>
                              </div>
                            )}
                          </>
                        );
                      } catch {
                        return <pre className="whitespace-pre-wrap text-sm">{artifacts.erd}</pre>;
                      }
                    })()}
                  </>
                )}
                {activeKey === "phased_master" && artifacts.phased_master && (() => {
                  try {
                    const data = JSON.parse(artifacts.phased_master);
                    const phases: any[] = data.phases || [];
                    const masterPrompt = data.master || '';

                    return (
                      <div className="mt-2 space-y-6">
                        {/* Phase Breakdown */}
                        {phases.length > 0 && (
                          <Card className="p-4">
                            <h3 className="mb-4 font-semibold">Phase Breakdown ({phases.length} fase)</h3>
                            <div className="space-y-3">
                              {phases.map((p: any, i: number) => (
                                <div key={p.key || i} className="rounded-lg border border-[var(--color-border)] p-4">
                                  <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1">
                                      <div className="text-sm font-semibold">{p.title}</div>
                                      {p.tasks?.length > 0 && (
                                        <ul className="mt-1 list-disc pl-4 text-xs text-[var(--color-fg-muted)]">
                                          {p.tasks.map((t: string, j: number) => <li key={j}>{t}</li>)}
                                        </ul>
                                      )}
                                      {p.ac && <div className="mt-1 text-xs text-[var(--color-fg-muted)]"><span className="font-medium">AC:</span> {p.ac}</div>}
                                    </div>
                                    {p.prompt && (
                                      <Button variant="secondary" size="sm" onClick={() => { navigator.clipboard.writeText(p.prompt).catch(() => {}); }}>
                                        <Copy size={12} /> Copy Prompt
                                      </Button>
                                    )}
                                  </div>
                                </div>
                              ))}
                            </div>
                          </Card>
                        )}

                        {/* Master Prompt */}
                        {masterPrompt && (
                          <Card className="p-4">
                            <div className="mb-3 flex items-center justify-between">
                              <h3 className="font-semibold">Master Prompt</h3>
                              <Button variant="secondary" size="sm" onClick={() => { navigator.clipboard.writeText(masterPrompt).catch(() => {}); }}>
                                <Copy size={13} /> Salin Master Prompt
                              </Button>
                            </div>
                            <pre className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">{masterPrompt}</pre>
                          </Card>
                        )}

                        {/* Standards & Rules */}
                        <Card className="p-4">
                          <h3 className="mb-3 font-semibold">Standards & Rules</h3>
                          <p className="mb-3 text-xs text-[var(--color-fg-muted)]">Download dan letakkan di root project sebelum AI coding agent mulai.</p>
                          <div className="flex flex-wrap items-center gap-4">
                            <div className="flex items-center gap-2">
                              <span className={`inline-block h-2 w-2 rounded-full ${data.standards ? 'bg-green-500' : 'bg-yellow-500'}`} />
                              <span className="text-xs">
                                {data.standards ? 'STANDARDS.md tersedia' : 'STANDARDS.md belum tersedia'}
                              </span>
                              {data.standards ? (
                                <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${versionId}/standards`, '_blank')}>
                                  <Copy size={13} /> Download
                                </Button>
                              ) : (
                                <Button variant="secondary" size="sm" onClick={() => {
                                  apiPost(`/versions/${versionId}/regenerate-standards`).then(() => {
                                    window.location.reload();
                                  }).catch(err => alert(err.message));
                                }}>
                                  <Copy size={13} /> Generate
                                </Button>
                              )}
                            </div>
                            <div className="flex items-center gap-2">
                              <span className={`inline-block h-2 w-2 rounded-full ${data.agents ? 'bg-green-500' : 'bg-yellow-500'}`} />
                              <span className="text-xs">
                                {data.agents ? 'AGENTS.md tersedia' : 'AGENTS.md belum tersedia'}
                              </span>
                              {data.agents ? (
                                <Button variant="secondary" size="sm" onClick={() => window.open(`/api/versions/${versionId}/agents`, '_blank')}>
                                  <Copy size={13} /> Download
                                </Button>
                              ) : (
                                <Button variant="secondary" size="sm" onClick={() => {
                                  apiPost(`/versions/${versionId}/regenerate-standards`).then(() => {
                                    window.location.reload();
                                  }).catch(err => alert(err.message));
                                }}>
                                  <Copy size={13} /> Generate
                                </Button>
                              )}
                            </div>
                          </div>
                        </Card>
                      </div>
                    );
                  } catch {}
                  return <div className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{artifacts.phased_master}</div>;
                })()}
              </>
            )}
          </Card>

          {/* Checkpoint bar */}
          {!auto && (status[activeKey] === "done" || status[activeKey] === "error") && !allDone && (
            <Card className="flex items-center justify-between p-4">
              <span className="text-sm text-[var(--color-fg-muted)]">
                {status[activeKey] === "error" ? "Terjadi kesalahan pada tahap ini." : "Tahap selesai. Lanjut ke berikutnya?"}
              </span>
              <div className="flex gap-2">
                <Button variant="secondary" size="sm" onClick={retryStage}><RotateCcw size={15} /> Analisa Ulang</Button>
                {status[activeKey] === "done" && (
                  <Button size="sm" onClick={approveNext} data-testid="approve-next">Approve & Lanjut <ArrowRight size={15} /></Button>
                )}
              </div>
            </Card>
          )}

          {allDone && (
            <Card className="flex flex-col items-center gap-3 p-6 text-center">
              <span className="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-success)] text-white"><Check size={24} /></span>
              <h3 className="text-lg font-semibold">Plan selesai! 🎉</h3>
              <p className="text-sm text-[var(--color-fg-muted)]">Semua artefak siap. Salin master prompt & mulai bangun dengan AI agent.</p>
              <div className="flex gap-2">
                <Button
                  variant="secondary"
                  onClick={() => {
                    const mp = artifacts.master;
                    if (mp) navigator.clipboard.writeText(mp).catch(() => {
                      const ta = document.createElement('textarea');
                      ta.value = mp;
                      ta.style.position = 'fixed';
                      ta.style.opacity = '0';
                      document.body.appendChild(ta);
                      ta.select();
                      document.execCommand('copy');
                      document.body.removeChild(ta);
                    });
                  }}
                ><Copy size={15} /> Salin Master Prompt</Button>
                <Button onClick={() => projectId && router.push(`/projects/${projectId}`)} data-testid="goto-project">Buka Project <ArrowRight size={15} /></Button>
              </div>
            </Card>
          )}

          {error && (
            <div className="flex items-center gap-2 rounded-lg border border-red-500/50 bg-red-500/10 p-4 text-sm text-red-500">
              <AlertCircle size={18} />
              <div>
                <div className="font-medium">Terjadi Kesalahan</div>
                <div className="mt-1 text-xs opacity-90">{error}</div>
              </div>
            </div>
          )}
        </div>
      </div>

      {/* Loading modal overlay */}
      {status[activeKey] === "running" && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm">
          <div className="mx-4 w-full max-w-2xl rounded-2xl border border-[var(--color-border)] bg-[var(--color-surface)] p-6 shadow-2xl">
            {/* Header */}
            <div className="mb-4 flex items-center gap-3">
              <Loader2 size={24} className="animate-spin text-[var(--color-brand)]" />
              <div className="flex-1">
                <div className="text-lg font-semibold">
                  Tahap {current + 1}/{STAGES.length}: {STAGES[current].label}
                </div>
                <div className="text-sm text-[var(--color-fg-muted)]">{STAGES[current].desc}</div>
              </div>
            </div>

            {/* Live output */}
            <div
              ref={outputRef}
              className="max-h-80 min-h-[80px] overflow-y-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-4"
            >
              <pre className="whitespace-pre-wrap text-xs leading-relaxed text-[var(--color-fg)]">
                {artifacts[activeKey] || "Menunggu hasil AI..."}
                <span className="inline-block h-4 w-0.5 animate-pulse bg-[var(--color-brand)]" />
              </pre>
            </div>

            {/* Progress */}
            <div className="mt-4">
              <div className="mb-1 flex items-center justify-between text-xs text-[var(--color-fg-muted)]">
                <span>Progress</span>
                <span>{STAGES.filter(s => status[s.key] === "done" || status[s.key] === "running").length}/{STAGES.length} tahap</span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                <div
                  className="h-full rounded-full bg-[var(--color-brand)] transition-all duration-500"
                  style={{ width: `${(STAGES.filter(s => status[s.key] === "done").length / STAGES.length) * 100}%` }}
                />
              </div>
            </div>

            {/* Cancel */}
            <div className="mt-4 flex justify-end">
              <Button variant="secondary" size="sm" onClick={cancelGeneration}>
                <AlertCircle size={15} /> Batalkan
              </Button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
