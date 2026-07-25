"use client";
import { useState, useRef, useCallback, useEffect } from "react";
import { useRouter } from "next/navigation";
import { Card, Badge, Textarea, Label } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { ErdDiagram } from "@/components/wizard/ErdDiagram";
import { STAGES, type StageKey, type StageState, type Target } from "@/lib/mock";
import { apiPost, apiGet, createSSE, type Project } from "@/lib/api";
import {
  Wand2, Globe, Smartphone, Layers, Loader2, Check, Copy, ArrowRight,
  RotateCcw, Zap, CircleDot, Sparkles, AlertCircle,
} from "lucide-react";

const TARGETS: { key: Target; label: string; icon: typeof Globe }[] = [
  { key: "web", label: "Web App", icon: Globe },
  { key: "mobile", label: "Mobile (APK/iOS)", icon: Smartphone },
  { key: "both", label: "Keduanya", icon: Layers },
];

export default function NewPlanPage() {
  const router = useRouter();
  const [started, setStarted] = useState(false);
  const [idea, setIdea] = useState("");
  const [target, setTarget] = useState<Target>("web");
  const [auto, setAuto] = useState(true);
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

  const eventSourceRef = useRef<EventSource | null>(null);
  const cancelled = useRef(false);

  // Cleanup EventSource on unmount
  useEffect(() => {
    return () => {
      if (eventSourceRef.current) {
        eventSourceRef.current.close();
        eventSourceRef.current = null;
      }
    };
  }, []);

  // Handle SSE events
  const handleSSEEvent = useCallback((event: string, data: any) => {
    if (cancelled.current) return;

    switch (event) {
      case 'status':
        // data: {stage: "analisa", state: "running" | "done"}
        if (data.stage && typeof data.stage === 'string') {
          setStatus(s => ({ ...s, [data.stage]: data.state }));
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

      case 'error':
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
    if (!idea.trim()) return;

    setCreating(true);
    setError("");
    cancelled.current = false;

    try {
      // Step 1: Create project (auto-creates version 1)
      const project = await apiPost<Project>("/projects", {
        title: idea.substring(0, 100),
        idea,
        target,
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

  function startPipeline(versionId: number) {
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
    }

    const stage = STAGES[0].key; // Start from first stage
    const autoParam = auto ? '1' : '0';

    eventSourceRef.current = createSSE(
      `/generate/stream?version=${versionId}&stage=${stage}&auto=${autoParam}`,
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

  function reset() {
    cancelled.current = true;
    if (eventSourceRef.current) {
      eventSourceRef.current.close();
      eventSourceRef.current = null;
    }
    setStarted(false);
    setCurrent(0);
    setStatus(Object.fromEntries(STAGES.map((s) => [s.key, "pending"])) as Record<StageKey, StageState>);
    setProjectId(null);
    setVersionId(null);
    setArtifacts({} as Record<StageKey, string>);
    setError("");
  }

  const allDone = STAGES.every((s) => status[s.key] === "done");
  const activeKey = STAGES[current].key;

  // ===== Input screen =====
  if (!started) {
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

            <Button onClick={start} disabled={!idea.trim() || creating} className="w-full" size="lg" data-testid="start-plan">
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
              <button className="inline-flex items-center gap-1.5 rounded-lg px-2.5 py-1.5 text-xs text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]">
                <Copy size={13} /> Salin
              </button>
            </div>

            {status[activeKey] === "running" ? (
              <div className="space-y-2">
                {[100, 90, 75, 95, 60].map((w, i) => (
                  <div key={i} className="shimmer h-3.5 rounded bg-[var(--color-surface-2)]" style={{ width: `${w}%` }} />
                ))}
              </div>
            ) : (
              <>
                <div className="whitespace-pre-wrap text-sm leading-relaxed text-[var(--color-fg-muted)]">
                  {artifacts[activeKey] || "Menunggu hasil AI..."}
                </div>
                {activeKey === "erd" && <div className="mt-4"><ErdDiagram /></div>}
                {activeKey === "phases" && artifacts.phases && (
                  <div className="mt-4 whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">
                    {artifacts.phases}
                  </div>
                )}
              </>
            )}
          </Card>

          {/* Checkpoint bar */}
          {!auto && status[activeKey] === "done" && !allDone && (
            <Card className="flex items-center justify-between p-4">
              <span className="text-sm text-[var(--color-fg-muted)]">Tahap selesai. Lanjut ke berikutnya?</span>
              <div className="flex gap-2">
                <Button variant="secondary" size="sm" onClick={retryStage}><RotateCcw size={15} /> Ulangi</Button>
                <Button size="sm" onClick={approveNext} data-testid="approve-next">Approve & Lanjut <ArrowRight size={15} /></Button>
              </div>
            </Card>
          )}

          {allDone && (
            <Card className="flex flex-col items-center gap-3 p-6 text-center">
              <span className="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-success)] text-white"><Check size={24} /></span>
              <h3 className="text-lg font-semibold">Plan selesai! 🎉</h3>
              <p className="text-sm text-[var(--color-fg-muted)]">Semua artefak siap. Salin master prompt & mulai bangun dengan AI agent.</p>
              <div className="flex gap-2">
                <Button variant="secondary"><Copy size={15} /> Salin Master Prompt</Button>
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
    </div>
  );
}
