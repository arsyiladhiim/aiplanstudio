"use client";
import { useState, useRef, useCallback, useEffect, useMemo, use } from "react";
import { useRouter } from "next/navigation";
import { Card, Badge, Textarea, Label } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { ErdDiagram } from "@/components/wizard/ErdDiagram";
import { getStages, type StageKey, type StageState, type Target } from "@/lib/mock";
import { apiPost, apiGet, apiPatch, createSSEPost, type Project, type Template, type Version } from "@/lib/api";
import {
  Wand2, Globe, Smartphone, Layers, Loader2, Check, Copy, ArrowRight,
  RotateCcw, CircleDot, Sparkles, AlertCircle, Pencil,
} from "lucide-react";

const TARGETS: { key: Target; label: string; icon: typeof Globe }[] = [
  { key: "web", label: "Web App", icon: Globe },
  { key: "mobile", label: "Mobile (APK)", icon: Smartphone },
  { key: "both", label: "Web + Mobile", icon: Layers },
];

interface PhaseItem {
  key?: string; title?: string; tasks?: string[]; prompt?: string; ac?: string;
}

interface ApiContractItem {
  method: string; path: string; description: string; auth: boolean;
}

interface ErdParsed {
  nodes?: Array<{ id: string; label: string; fields: string[] }>;
  edges?: Array<{ from: string; to: string; relation: string }>;
  api_contract?: ApiContractItem[];
  phases?: PhaseItem[];
  master?: string;
  standards?: boolean;
  agents?: boolean;
}

export default function NewPlanPage({ searchParams }: { searchParams: Promise<{ resume?: string; version?: string }> }) {
  const router = useRouter();
  const params = use(searchParams);
  const isResume = params.resume === '1';
  const resumeVersionId = params.version ? Number(params.version) : null;
  const [started, setStarted] = useState(false);
  const [idea, setIdea] = useState("");
  const [title, setTitle] = useState("");
  const [target, setTarget] = useState<Target>("web");
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [templates, setTemplates] = useState<Template[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState<string>("");
  const [current, setCurrent] = useState(0);

  function initStatus(t: Target): Record<StageKey, StageState> {
    return Object.fromEntries(getStages(t).map((s) => [s.key, "pending"])) as Record<StageKey, StageState>;
  }

  const [status, setStatus] = useState<Record<StageKey, StageState>>(() => initStatus(target));

  function setTargetAndReset(newTarget: Target) {
    setTarget(newTarget);
    setStatus(initStatus(newTarget));
  }

  const stages = useMemo(() => getStages(target), [target]);
  const allDone = stages.every((s) => status[s.key] === "done");
  const activeKey = stages[current]?.key;

  // Real backend integration states
  const [projectId, setProjectId] = useState<number | null>(null);
  const [versionId, setVersionId] = useState<number | null>(null);
  const [artifacts, setArtifacts] = useState<Record<StageKey, string>>({} as Record<StageKey, string>);
  const [error, setError] = useState<string>("");
  const [creating, setCreating] = useState(false);
  const [editingStage, setEditingStage] = useState<StageKey | null>(null);
  const [editContent, setEditContent] = useState("");

  const abortRef = useRef<AbortController | null>(null);
  const cancelled = useRef(false);
  const fallbackFetched = useRef(new Set<string>());
  const outputRef = useRef<HTMLDivElement>(null);

  // Derive questions from artifacts.pertanyaan (instead of setQuestions in effect)
  const questions = useMemo(() => {
    if (!artifacts.pertanyaan) return [] as string[];
    const lines = artifacts.pertanyaan.split('\n').filter((l: string) => l.trim());
    let parsed = lines
      .filter((l: string) => /^\d+[.)]\s/.test(l.trim()))
      .map((l: string) => l.replace(/^\d+[.)]\s*/, '').trim());
    if (parsed.length === 0) {
      parsed = lines.filter((l: string) => l.trim().endsWith('?'));
    }
    if (parsed.length === 0) {
      parsed = ['Jelaskan lebih detail tentang aplikasi yang kamu inginkan?'];
    }
    return parsed;
  }, [artifacts.pertanyaan]);

  // handleSSEEvent must be defined before startPipeline
  const handleSSEEvent = useCallback((event: string, rawData: unknown) => {
    if (cancelled.current) return;
    const data = rawData as Record<string, unknown>;

    switch (event) {
      case 'status': {
        const stage = data.stage as string | undefined;
        if (stage) {
          setStatus(s => ({ ...s, [stage]: data.state as StageState }));
          if (data.state === 'running') {
            const idx = stages.findIndex(x => x.key === stage);
            if (idx >= 0) setCurrent(idx);
          }
        }
        break;
      }

      case 'token': {
        const stage = data.stage as string | undefined;
        if (stage) {
          setArtifacts(prev => {
            const key = stage as StageKey;
            return { ...prev, [key]: ((prev[key] || '') + String(data.delta ?? '')) };
          });
        }
        break;
      }

      case 'artifact': {
        const stage = data.stage as string | undefined;
        if (stage) {
          setArtifacts(prev => ({ ...prev, [stage as StageKey]: String(data.content ?? '') }));
        }
        break;
      }

      case 'done': {
        const stage = data.stage as string | undefined;
        if (stage) {
          const stageIndex = stages.findIndex(x => x.key === stage);
          if (stageIndex >= 0) {
            setCurrent(stageIndex);
            setStatus(s => ({ ...s, [stage]: 'done' as StageState }));
          }
        }
        break;
      }

      case 'fail':
        setError(String(data.message ?? 'Terjadi kesalahan.'));
        { const stage = data.stage as string | undefined;
          if (stage) {
            setStatus(s => ({ ...s, [stage]: 'error' as StageState }));
          }
        }
        if (abortRef.current) {
          abortRef.current.abort();
          abortRef.current = null;
        }
        break;
    }
  }, [stages]);

  const startPipeline = useCallback((versionId: number, stage?: string) => {
    if (abortRef.current) {
      abortRef.current.abort();
    }

    const s = stage || stages[0].key;
    const idx = stages.findIndex(x => x.key === s);
    if (idx >= 0) setCurrent(idx);
    setStatus(prev => ({ ...prev, [s]: 'running' }));

    createSSEPost(
      `/generate/stream`,
      { version: versionId, stage: s },
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    ).then(ctrl => { abortRef.current = ctrl; });
  }, [handleSSEEvent, stages]);

  // Apply template seed when selected
  function handleTemplateChange(value: string) {
    setSelectedTemplate(value);
    if (!value) return;
    const tpl = templates.find(t => String(t.id) === value);
    if (!tpl || !tpl.seed) return;
    const seed = tpl.seed as Record<string, string>;
    if (seed.title) setTitle(seed.title);
    if (seed.idea) setIdea(seed.idea);
    if (seed.target && ["web","mobile","both"].includes(seed.target)) {
      setTargetAndReset(seed.target as Target);
    }
  }

  // Load templates on mount
  useEffect(() => {
    apiGet<Template[]>("/templates").then(setTemplates).catch((err) => console.error('Failed to load templates:', err));
  }, []);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortRef.current) {
        abortRef.current.abort();
        abortRef.current = null;
      }
    };
  }, []);

  // Fallback: fetch artifact from DB when SSE artifact event was lost
  useEffect(() => {
    if (!versionId) return;
    const colMap: Record<string, string> = {
      analisa: 'analysis', prd: 'prd', architecture: 'architecture',
      erd: 'erd', phased_master: 'master_prompt',
    };
    const missing = stages.filter(s =>
      status[s.key] === 'done' && !artifacts[s.key] && !fallbackFetched.current.has(s.key)
    );
    if (missing.length === 0) return;
    for (const stage of missing) fallbackFetched.current.add(stage.key);

    apiGet<Record<string, unknown>>(`/versions/${versionId}`).then(v => {
      setArtifacts(prev => {
        const next = { ...prev };
        for (const stage of missing) {
          const col = colMap[stage.key];
          if (!col) continue;
          if (next[stage.key]) continue;
          const content = v[col];
          if (content == null || content === '') continue;
          next[stage.key] = typeof content === 'string' ? content : JSON.stringify(content, null, 2);
        }
        return next;
      });
    }).catch((err) => console.error('Failed to fetch artifact fallback:', err));
  }, [status, versionId, stages, artifacts]);

  // Resume mode: load existing version data
  useEffect(() => {
    if (!isResume || !resumeVersionId || started) return;

    apiGet<Version>(`/versions/${resumeVersionId}`).then(v => {
      setProjectId(v.project?.id ?? null);
      setVersionId(v.id);
      setTitle(v.project?.title ?? '');
      setIdea(v.project?.idea ?? '');
      setTarget(v.project?.target ?? 'web');
      if (v.answers) setAnswers(v.answers);
      setStarted(true);

      const firstIdx = stages.findIndex(s => (v.stage_status as Record<string, string>)?.[s.key] !== 'done');
      setCurrent(Math.max(0, firstIdx));

      const loadedStatus = Object.fromEntries(stages.map(s => [s.key, (v.stage_status as Record<string, string>)?.[s.key] || 'pending'])) as Record<StageKey, StageState>;
      stages.forEach(s => { if (loadedStatus[s.key] === 'error') loadedStatus[s.key] = 'pending'; });
      setStatus(loadedStatus);

      const colMap: Record<string, keyof Version> = {
        pertanyaan: 'pertanyaan',
        analisa: 'analysis',
        prd: 'prd',
        architecture: 'architecture',
        erd: 'erd',
        phased_master: 'master_prompt',
        phased_master_mobile: 'mobile_master_prompt',
      };
      const loaded: Record<string, string> = {};
      stages.forEach(s => {
        const col = colMap[s.key];
        if (!col) return;
        const val = v[col];
        if (val) loaded[s.key] = typeof val === 'object' ? JSON.stringify(val) : String(val);
      });
      setArtifacts(loaded as Record<StageKey, string>);

      if (firstIdx >= 0) startPipeline(v.id, stages[firstIdx].key);
    }).catch(err => setError(err instanceof Error ? err.message : 'Gagal memuat data project'));
  }, [isResume, resumeVersionId, started, stages, startPipeline]);

  // Auto-scroll modal output
  const activeArtifact = artifacts[activeKey];
  useEffect(() => {
    if (outputRef.current) {
      outputRef.current.scrollTop = outputRef.current.scrollHeight;
    }
  }, [activeArtifact]);

  async function start() {
    if (!title.trim() || !idea.trim()) return;

    setCreating(true);
    setError("");
    cancelled.current = false;

    try {
      const project = await apiPost<Project>("/projects", {
        title: title.trim(),
        idea,
        target,
      });

      setProjectId(project.id);

      const projectWithVersions = await apiGet<Project & { versions: Array<{ id: number }> }>(`/projects/${project.id}`);
      const vId = projectWithVersions.versions?.[0]?.id;

      if (!vId) {
        throw new Error("Version tidak ditemukan");
      }

      setVersionId(vId);
      setStarted(true);
      setCreating(false);

      startPipeline(vId);
    } catch (err: unknown) {
      setError(err instanceof Error ? err.message : 'Gagal membuat project');
      setCreating(false);
      console.error('Failed to create project:', err);
    }
  }

  function approveNext() {
    if (!versionId || current + 1 >= stages.length) return;
    if (stages[current].key === 'pertanyaan') return;

    const nextStage = stages[current + 1].key;

    if (abortRef.current) {
      abortRef.current.abort();
    }

    createSSEPost(
      `/generate/stream`,
      { version: versionId, stage: nextStage },
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    ).then(ctrl => { abortRef.current = ctrl; });
  }

  function retryStage() {
    if (!versionId) return;

    const currentStage = stages[current].key;

    setStatus(s => ({ ...s, [currentStage]: 'pending' }));
    setArtifacts(prev => {
      const newArtifacts = { ...prev };
      delete newArtifacts[currentStage as StageKey];
      return newArtifacts;
    });
    setError("");

    if (abortRef.current) {
      abortRef.current.abort();
    }

    createSSEPost(
      `/generate/stream`,
      { version: versionId, stage: currentStage },
      handleSSEEvent,
      (err) => {
        console.error('SSE error:', err);
        setError('Koneksi SSE terputus');
      }
    ).then(ctrl => { abortRef.current = ctrl; });
  }

  function cancelGeneration() {
    cancelled.current = true;
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    setStatus(s => ({ ...s, [activeKey]: 'error' }));
    setError("Pembuatan plan dibatalkan.");
  }

  function reset() {
    cancelled.current = true;
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    fallbackFetched.current.clear();
    setStarted(false);
    setCurrent(0);
    setStatus(initStatus(target));
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
                  onChange={(e) => handleTemplateChange(e.target.value)}
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
              <Label>Target Platform</Label>
              <div className="grid grid-cols-3 gap-2">
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
          {stages.map((s, i) => {
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
                <h3 className="font-semibold">{stages[current].label}</h3>
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
                  onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setEditContent(e.target.value)}
                  className="w-full min-h-[200px] rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3 text-sm font-mono text-[var(--color-fg)] resize-y focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
                />
                  <div className="flex items-center gap-2">
                  <button
                    onClick={() => {
                      setArtifacts(prev => ({ ...prev, [activeKey]: editContent }));
                      setEditingStage(null);
                      setEditContent("");
                      if (versionId) {
                        apiPatch(`/versions/${versionId}/artifacts`, { stage: activeKey, content: editContent }).catch((err) => console.error('Failed to save artifact:', err));
                      }
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
                {activeKey === "pertanyaan" && status.pertanyaan === "done" ? (
                  <div className="space-y-4">
                    <h4 className="font-semibold">Jawab pertanyaan klarifikasi berikut:</h4>
                    {questions.length === 0 && artifacts.pertanyaan && (
                      <div className="text-sm text-[var(--color-fg-muted)]">Memproses pertanyaan...</div>
                    )}
                    {questions.map((q, i) => (
                      <div key={i}>
                        <Label>{q}</Label>
                        <textarea
                          rows={2}
                          value={answers[q] || ''}
                          onChange={(e: React.ChangeEvent<HTMLTextAreaElement>) => setAnswers(prev => ({ ...prev, [q]: e.target.value }))}
                          className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm"
                          placeholder="Tulis jawaban kamu..."
                        />
                      </div>
                    ))}
                    <Button
                      onClick={async () => {
                        if (!versionId) return;
                        await apiPatch(`/versions/${versionId}/answers`, { answers });
                        const nextStage = stages[current + 1]?.key;
                        if (nextStage && versionId) {
                          setStatus(s => ({ ...s, [nextStage]: 'running' }));
                          setCurrent(current + 1);
                          if (abortRef.current) abortRef.current.abort();
                          createSSEPost(
                            `/generate/stream`,
                            { version: versionId, stage: nextStage },
                            handleSSEEvent,
                            (err) => { console.error('SSE error:', err); setError('Koneksi SSE terputus'); }
                          ).then(ctrl => { abortRef.current = ctrl; });
                        }
                      }}
                      disabled={!Object.values(answers).some(a => a.trim())}
                    >
                      <ArrowRight size={15} /> Kirim Jawaban & Lanjutkan
                    </Button>
                  </div>
                ) : (activeKey === "erd" && status.erd === "done") || (activeKey === "architecture" && status.architecture === "done") || (activeKey === "phased_master" && status.phased_master === "done") ? null : (
                  <div className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">
                    {status[activeKey] === "done" && !artifacts[activeKey]
                      ? "Tidak ada output"
                      : artifacts[activeKey] || "Menunggu hasil AI..."}
                  </div>
                )}

                {activeKey === "architecture" && artifacts.architecture && (() => {
                  const text = artifacts.architecture;
                  const nodes: Array<{ id: string; label: string; fields: string[] }> = [];
                  const edges: Array<{ from: string; to: string; relation: string }> = [];

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
                        const erdData: ErdParsed = JSON.parse(artifacts.erd);
                        return (
                          <>
                            <div className="mb-6 mt-4"><ErdDiagram erd={erdData} /></div>
                            {(() => { const ac = erdData.api_contract; return ac && ac.length > 0 ? <>
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
                                      {ac.map((api: ApiContractItem, i: number) => (
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
                            </> : null; })()}
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
                    const data: ErdParsed = JSON.parse(artifacts.phased_master);
                    const phases: PhaseItem[] = data.phases || [];
                    const masterPrompt = data.master || '';

                    return (
                      <div className="mt-2 space-y-6">
                        {phases.length > 0 && (
                          <Card className="p-4">
                            <h3 className="mb-4 font-semibold">Phase Breakdown ({phases.length} fase)</h3>
                            <div className="space-y-3">
                              {phases.map((p: PhaseItem, i: number) => (
                                <div key={p.key || i} className="rounded-lg border border-[var(--color-border)] p-4">
                                  <div className="flex items-start justify-between gap-2">
                                    <div className="flex-1">
                                      <div className="text-sm font-semibold">{p.title}</div>
                                      {(() => { const tasks = p.tasks; return tasks && tasks.length > 0 ? (
                                        <ul className="mt-1 list-disc pl-4 text-xs text-[var(--color-fg-muted)]">
                                          {tasks.map((t: string, j: number) => <li key={j}>{t}</li>)}
                                        </ul>
                                      ) : null; })()}
                                      {p.ac && <div className="mt-1 text-xs text-[var(--color-fg-muted)]"><span className="font-medium">AC:</span> {p.ac}</div>}
                                    </div>
                                    {p.prompt && (
                                      <Button variant="secondary" size="sm" onClick={() => { navigator.clipboard.writeText(p.prompt ?? '').catch(() => {}); }}>
                                        <Copy size={12} /> Copy Prompt
                                      </Button>
                                    )}
                                  </div>
                                </div>
                              ))}
                            </div>
                          </Card>
                        )}

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
                                  }).catch((err: Error) => alert(err.message));
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
                                  }).catch((err: Error) => alert(err.message));
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
          {(status[activeKey] === "done" || status[activeKey] === "error") && !allDone && (
            <Card className="flex items-center justify-between p-4">
              <span className="text-sm text-[var(--color-fg-muted)]">
                {status[activeKey] === "error" ? "Terjadi kesalahan pada tahap ini." : "Tahap selesai. Lanjut ke berikutnya?"}
              </span>
              <div className="flex gap-2">
                {activeKey !== 'pertanyaan' && (
                  <Button variant="secondary" size="sm" onClick={retryStage}><RotateCcw size={15} /> Analisa Ulang</Button>
                )}
                {status[activeKey] === "done" && activeKey !== 'pertanyaan' && (
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
                    const mp = artifacts.phased_master;
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
                  Tahap {current + 1}/{stages.length}: {stages[current].label}
                </div>
                <div className="text-sm text-[var(--color-fg-muted)]">{stages[current].desc}</div>
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
                <span>{stages.filter(s => status[s.key] === "done" || status[s.key] === "running").length}/{stages.length} tahap</span>
              </div>
              <div className="h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
                <div
                  className="h-full rounded-full bg-[var(--color-brand)] transition-all duration-500"
                  style={{ width: `${(stages.filter(s => status[s.key] === "done").length / stages.length) * 100}%` }}
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
