"use client";
import { useState, useRef, useCallback, useEffect, useMemo, use } from "react";
import { useRouter } from "next/navigation";
import { Card, Badge, Textarea, Label, Markdown, Modal } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import dynamic from "next/dynamic";
const ErdDiagramDynamic = dynamic(() => import("@/components/wizard/ErdDiagram").then(m => ({ default: m.ErdDiagram })), { ssr: false, loading: () => <div className="h-[460px] animate-pulse rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-1)]" /> });
import { ApiContractTable, type ApiContractItem } from "@/components/wizard/ApiContractTable";
import { PhaseBreakdownCard, type PhaseItem } from "@/components/wizard/PhaseBreakdownCard";
import type { ProgressItem } from "@/components/wizard/TrackingPhases";
import { TrackingPanel } from "@/components/wizard/TrackingPanel";
import { McqForm } from "@/components/wizard/McqForm";
import { StreamingMarkdown } from "@/components/wizard/StreamingMarkdown";
import { StageThroughputBar } from "@/components/wizard/StageThroughputBar";
import { BuildWall } from "@/components/wizard/BuildWall";
import { getStages, type StageKey, type StageState, type Target } from "@/lib/mock";
import { apiPost, apiGet, apiPatch, apiDelete, createSSEPost, createSSE, type Project, type Template, type Version, type McqData, type McqAnswer } from "@/lib/api";
import { copyToClipboard } from "@/lib/clipboard";
import { chime } from "@/lib/chime";
import {
  Wand2, Globe, Layers, Loader2, Check, Copy, ArrowRight,
  RotateCcw, CircleDot, Sparkles, AlertCircle, Pencil,
} from "lucide-react";
import { ErrorBoundary } from "@/components/ErrorBoundary";

const Confetti = dynamic(() => import("@/components/Confetti").then(m => ({ default: m.Confetti })), { ssr: false });

const TARGETS: { key: Target; label: string; icon: typeof Globe }[] = [
  { key: "web", label: "Web App", icon: Globe },
  { key: "both", label: "Web + Mobile", icon: Layers },
];

interface ErdParsed {
  nodes?: Array<{ id: string; label: string; fields: string[] }>;
  edges?: Array<{ from: string; to: string; relation: string }>;
  api_contract?: ApiContractItem[];
  phases?: PhaseItem[];
  master?: string;
  standards?: boolean;
  agents?: boolean;
}

export default function NewPlanPage({ searchParams }: { searchParams: Promise<{ resume?: string; version?: string; template?: string }> }) {
  const router = useRouter();
  const params = use(searchParams);
  const isResume = params.resume === '1';
  const resumeVersionId = params.version ? Number(params.version) : null;
  const templateParam = params.template ?? "";
  const [started, setStarted] = useState(false);
  const [idea, setIdea] = useState("");
  const [title, setTitle] = useState("");
  const [target, setTarget] = useState<Target>("web");
  const [answers, setAnswers] = useState<Record<string, string>>({});
  const [mcqAnswers, setMcqAnswers] = useState<Record<string, McqAnswer>>({});
  const [mobileMcqAnswers, setMobileMcqAnswers] = useState<Record<string, McqAnswer>>({});
  const [templates, setTemplates] = useState<Template[]>([]);
  const [selectedTemplate, setSelectedTemplate] = useState<string>("");
  const [current, setCurrent] = useState(0);

  function initStatus(t: Target): Record<StageKey, StageState> {
    return Object.fromEntries(getStages(t).map((s) => [s.key, "pending"])) as Record<StageKey, StageState>;
  }

  const [status, setStatus] = useState<Record<StageKey, StageState>>(() => initStatus(target));

  const setTargetAndReset = useCallback((newTarget: Target) => {
    setTarget(newTarget);
    setStatus(initStatus(newTarget));
  }, []);

  const stages = useMemo(() => getStages(target), [target]);
  const allDone = stages.every((s) => status[s.key] === "done");
  const activeKey = stages[current]?.key;
  const activeKeyRef = useRef(activeKey);
  useEffect(() => { activeKeyRef.current = activeKey; }, [activeKey]);

  // Real backend integration states
  const [projectId, setProjectId] = useState<number | null>(null);
  const [versionId, setVersionId] = useState<number | null>(null);
  const [artifacts, setArtifacts] = useState<Record<StageKey, string>>({} as Record<StageKey, string>);
  const [error, setError] = useState<string>("");
  const [creating, setCreating] = useState(false);
  const [deleting, setDeleting] = useState(false);
  const [editingStage, setEditingStage] = useState<StageKey | null>(null);
  const [editContent, setEditContent] = useState("");
  const [savingArtifact, setSavingArtifact] = useState(false);
  const [retryInfo, setRetryInfo] = useState<{ attempt: number; max: number } | null>(null);
  const [phaseProg, setPhaseProg] = useState<Version["phase_progress"]>([]);
  const [stageTokens, setStageTokens] = useState<Record<string, number>>({});
  const [providerRate] = useState<number | null>(null);
  // startedAt: updated by SSE event handler (bukan useEffect) untuk hindari
  // React Compiler 'set-state-in-effect' rule.
  const [startedAt, setStartedAt] = useState<number | null>(null);
  const [pendingConfirmMaster, setPendingConfirmMaster] = useState(false);
  const [showCancelConfirm, setShowCancelConfirm] = useState(false);

  // Fase web (phases) + status tracking — untuk gate: web belum selesai bangun
  // bila ada fase `phases_web` dengan phase_progress belum semua done.
  const webPhases = useMemo(() => {
    try {
      const p = JSON.parse(artifacts.phases_web || "[]");
      return Array.isArray(p) ? (p as PhaseItem[]) : [];
    } catch {
      return [];
    }
  }, [artifacts.phases_web]);
  const webTrackingDone = useMemo(() => {
    if (webPhases.length === 0) return true;
    const keySet = new Set(webPhases.map((p) => p.key ?? ""));
    const doneCount = (phaseProg ?? []).filter((pp) => keySet.has(pp.phase_key) && pp.done).length;
    return doneCount >= webPhases.length;
  }, [webPhases, phaseProg]);

  const mobilePhases = useMemo(() => {
    try {
      const p = JSON.parse(artifacts.phases_mobile || "[]");
      return Array.isArray(p) ? (p as PhaseItem[]) : [];
    } catch {
      return [];
    }
  }, [artifacts.phases_mobile]);

  const showTrackingPanel = activeKey === "master_web" || activeKey === "master_mobile"
    || (activeKey === "agents" && (webPhases.length > 0 || mobilePhases.length > 0));
  const trackingPhases = activeKey === "master_mobile" || (activeKey === "agents" && mobilePhases.length > 0)
    ? mobilePhases
    : webPhases;
  const progMap = useMemo(
    () => Object.fromEntries((phaseProg ?? []).map((p: ProgressItem) => [p.phase_key, p])),
    [phaseProg],
  );

  const abortRef = useRef<AbortController | null>(null);
  const cancelled = useRef(false);
  const creatingRef = useRef(false);
  const retryCountRef = useRef(0);
  const fallbackFetched = useRef(new Set<string>());
  const outputRef = useRef<HTMLDivElement>(null);
  const webPhasesRef = useRef<PhaseItem[]>([]);
  const mobilePhasesRef = useRef<PhaseItem[]>([]);
  const artifactsRef = useRef<Record<StageKey, string>>({} as Record<StageKey, string>);
  useEffect(() => { artifactsRef.current = artifacts; }, [artifacts]);
  useEffect(() => { webPhasesRef.current = webPhases; }, [webPhases]);
  useEffect(() => { mobilePhasesRef.current = mobilePhases; }, [mobilePhases]);

  // Parse MCQ JSON toleran: strip fence, buang trailing comma, ambil blok {..} terluar valid.
  const parseMcq = useCallback((raw: string): McqData | null => {
    if (!raw) return null;
    const attempt = (json: string): McqData | null => {
      try {
        const cleaned = json.replace(/,\s*([\]}])/g, "$1");
        const parsed = JSON.parse(cleaned) as McqData;
        if (parsed.questions && Array.isArray(parsed.questions) && parsed.questions.length > 0) {
          return parsed;
        }
      } catch { /* fallthrough */ }
      return null;
    };

    // Direct attempt
    const direct = attempt(raw);
    if (direct) return direct;

    // Strip code fences, lalu ambil blok { } terluar
    const unFenced = raw.replace(/```(?:json)?/gi, '').trim();
    const first = unFenced.indexOf('{');
    const last = unFenced.lastIndexOf('}');
    if (first !== -1 && last !== -1 && last > first) {
      const block = attempt(unFenced.slice(first, last + 1));
      if (block) return block;
    }
    return null;
  }, []);

  const mcqData = useMemo((): McqData | null => {
    if (!artifacts.pertanyaan) return null;
    return parseMcq(artifacts.pertanyaan);
  }, [artifacts.pertanyaan, parseMcq]);

  const mcqMobileData = useMemo((): McqData | null => {
    if (!artifacts.pertanyaan_mobile) return null;
    return parseMcq(artifacts.pertanyaan_mobile);
  }, [artifacts.pertanyaan_mobile, parseMcq]);

  // Legacy plain-text questions fallback
  const questions = useMemo(() => {
    if (mcqData) return [] as string[];
    if (!artifacts.pertanyaan) return [] as string[];
    const lines = artifacts.pertanyaan.split('\n').filter((l: string) => l.trim());
    let parsed = lines
      .filter((l: string) => /^\d+[.)]\s/.test(l.trim()))
      .map((l: string) => l.replace(/^\d+[.)]\s*/, '').trim());
    if (parsed.length === 0) parsed = lines.filter((l: string) => l.trim().endsWith('?'));
    if (parsed.length === 0) parsed = ['Jelaskan lebih detail tentang aplikasi yang kamu inginkan?'];
    return parsed;
  }, [artifacts.pertanyaan, mcqData]);

  // Parse ERD artifact toleran: strip code fence + ambil blok JSON terluar.
  const parseErdArtifact = useCallback((raw: string): ErdParsed | null => {
    try {
      return JSON.parse(raw) as ErdParsed;
    } catch {
      /* fallthrough */
    }
    try {
      const unFenced = raw.replace(/```(?:json)?/gi, '').trim();
      const first = unFenced.indexOf('{');
      const last = unFenced.lastIndexOf('}');
      if (first === -1 || last === -1) return null;
      return JSON.parse(unFenced.slice(first, last + 1)) as ErdParsed;
    } catch {
      return null;
    }
  }, []);

  // handleSSEEvent must be defined before startPipeline
  const handleSSEEvent = useCallback((event: string, rawData: unknown) => {
    if (cancelled.current) return;
    const data = rawData as Record<string, unknown>;

    switch (event) {
      case 'status': {
        const stage = data.stage as string | undefined;
        if (stage) {
          const state = data.state as string;
          if (state === 'retrying') {
            // Retry: buat buffer baru agar attempt baru tidak menumpuk → JSON korup.
            // Status tetap 'running' agar modal loading tampil; retryInfo menampilkan percobaan.
            setArtifacts(prev => ({ ...prev, [stage as StageKey]: '' }));
            const attemptRaw = Number(data.attempt ?? 1);
            const maxRaw = Number(data.max ?? 0);
            setRetryInfo({
              attempt: Number.isFinite(attemptRaw) ? attemptRaw : 1,
              max: Number.isFinite(maxRaw) ? maxRaw : 0,
            });
            setStatus(s => ({ ...s, [stage]: 'running' as StageState }));
          } else {
            setStatus(s => ({ ...s, [stage]: state as StageState }));
          }
          if (data.state === 'running') {
            const idx = stages.findIndex(x => x.key === stage);
            if (idx >= 0) setCurrent(idx);
            setStartedAt(prev => prev ?? Date.now());
          }
          if (data.state === 'done') {
            setRetryInfo(null);
            chime();
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

      case 'stage_tokens': {
        const stage = data.stage as string | undefined;
        const tokens = Number(data.tokens ?? 0);
        if (stage && Number.isFinite(tokens) && tokens > 0) {
          setStageTokens(prev => ({ ...prev, [stage]: tokens }));
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

  const doStream = useCallback((versionId: number, stage: string) => {
    retryCountRef.current = 0;
    const attempt = (retries: number) => {
      if (abortRef.current) abortRef.current.abort();
      if (cancelled.current) return;
      createSSEPost(
        `/generate/stream`,
        { version: versionId, stage },
        handleSSEEvent,
        (err) => {
          if (retries < 3 && !cancelled.current) {
            retryCountRef.current = retries + 1;
            console.warn(`SSE retry ${retries + 1}/3:`, err.message);
            setTimeout(() => attempt(retries + 1), 2000 * (retries + 1));
          } else {
            console.error('SSE error (max retries):', err);
            setError('Koneksi SSE terputus setelah 3x retry.');
          }
        }
      ).then(ctrl => { abortRef.current = ctrl; });
    };
    attempt(0);
  }, [handleSSEEvent]);

  const startPipeline = useCallback((versionId: number, stage?: string) => {
    if (abortRef.current) {
      abortRef.current.abort();
    }

    const s = stage || stages[0].key;
    const idx = stages.findIndex(x => x.key === s);
    if (idx >= 0) setCurrent(idx);
    setStatus(prev => ({ ...prev, [s]: 'running' }));

    doStream(versionId, s);
  }, [doStream, stages]);

  // Apply template seed when selected
  function handleTemplateChange(value: string) {
    setSelectedTemplate(value);
    if (!value) return;
    const tpl = templates.find(t => String(t.id) === value);
    if (!tpl || !tpl.seed) return;
    const seed = tpl.seed as Record<string, string>;
    if (seed.title) setTitle(seed.title);
    if (seed.idea) setIdea(seed.idea);
      if (seed.target && ["web","both"].includes(seed.target)) {
      setTargetAndReset(seed.target as Target);
    }
  }

  // Load templates on mount — auto-select if ?template=N URL param present
  useEffect(() => {
    apiGet<Template[]>("/templates").then((t) => {
      setTemplates(t);
      if (templateParam) {
        const tpl = t.find((x) => String(x.id) === templateParam);
        if (tpl && tpl.seed) {
          const seed = tpl.seed as Record<string, string>;
          setSelectedTemplate(String(tpl.id));
          if (seed.title) setTitle(seed.title);
          if (seed.idea) setIdea(seed.idea);
          if (seed.target && ["web", "both"].includes(seed.target)) {
            setTargetAndReset(seed.target as Target);
          }
        }
      }
    }).catch((err) => console.error('Failed to load templates:', err));
  }, [templateParam, setTargetAndReset]);

  // Cleanup on unmount
  useEffect(() => {
    return () => {
      if (abortRef.current) {
        abortRef.current.abort();
        abortRef.current = null;
      }
    };
  }, []);

  // Tracking fase real-time — SSE via EventSource saat berada di stage master.
  // Effect hanya depend on versionId agar tidak re-subscribe tiap advance stage.
  useEffect(() => {
    if (!versionId) return;
    const showTracking = activeKey === "master_web" || activeKey === "master_mobile"
      || (activeKey === "agents" && (webPhasesRef.current.length > 0 || mobilePhasesRef.current.length > 0));
    if (!showTracking) return;

    // Initial fetch for immediate render
    apiGet<Version>(`/versions/${versionId}`).then(v => {
      if (v.phase_progress) setPhaseProg(v.phase_progress);
      if (v.stage_tokens) setStageTokens(v.stage_tokens);
    }).catch(() => {});

    const es = createSSE(
      `/versions/${versionId}/phase-progress/stream`,
      (event, data) => {
        if (event === "phase_progress") {
          const d = data as ProgressItem;
          setPhaseProg(prev => {
            const idx = (prev ?? []).findIndex(p => p.phase_key === d.phase_key);
            if (idx >= 0) {
              const next = [...(prev ?? [])];
              next[idx] = { ...next[idx], ...d };
              return next;
            }
            return [...(prev ?? []), d];
          });
        }
      },
    );
    return () => es.close();
    // eslint-disable-next-line react-hooks/exhaustive-deps
  }, [versionId]);

  // Fallback: fetch artifact from DB when SSE artifact event was lost
  useEffect(() => {
    if (!versionId) return;
    const colMap: Record<string, string> = {
      pertanyaan: 'pertanyaan', pertanyaan_mobile: 'pertanyaan_mobile',
      analisa: 'analysis', prd: 'prd', architecture: 'architecture', erd: 'erd',
      api_contract: 'api_contract',
      phases_web: 'phases', standards_web: 'standards', master_web: 'master_prompt',
      phases_mobile: 'mobile_phases', standards_mobile: 'mobile_standards', master_mobile: 'mobile_master_prompt',
      agents: 'agents',
    };
    const missing = stages.filter(s =>
      status[s.key] === 'done' && !artifactsRef.current[s.key] && !fallbackFetched.current.has(s.key)
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
  }, [status, versionId, stages]);

  // Resume mode: load existing version data
  useEffect(() => {
    if (!isResume || !resumeVersionId || started) return;

    // Reset fallback cache agar resume bisa re-fetch artifact dari DB bila SSE
    // 'artifact' event hilang saat restart.
    fallbackFetched.current.clear();

    apiGet<Version>(`/versions/${resumeVersionId}`).then(v => {
      setProjectId(v.project?.id ?? null);
      setVersionId(v.id);
      setTitle(v.project?.title ?? '');
      setIdea(v.project?.idea ?? '');
      const projectTarget = v.project?.target ?? 'web';
      setTarget(projectTarget);
      if (v.answers) setAnswers(v.answers);
      setStarted(true);

      // Hitung stage berdasarkan target project (bukan `stages` memo yang masih
      // stale ke target default 'web' saat effect jalan) — agar resume lanjut ke
      // stage sebenarnya (mis. pertanyaan_mobile utk target both).
      const resumeStages = getStages(projectTarget);
      let firstIdx = resumeStages.findIndex(s => (v.stage_status as Record<string, string>)?.[s.key] !== 'done');
      let idx = firstIdx >= 0 ? firstIdx : resumeStages.length - 1; // semua done → stage terakhir
      setCurrent(idx);

      const loadedStatus = Object.fromEntries(resumeStages.map(s => [s.key, (v.stage_status as Record<string, string>)?.[s.key] || 'pending'])) as Record<StageKey, StageState>;
      resumeStages.forEach(s => { if (loadedStatus[s.key] === 'error') loadedStatus[s.key] = 'pending'; });
      setStatus(loadedStatus);

      const colMap: Record<string, keyof Version> = {
        pertanyaan: 'pertanyaan',
        pertanyaan_mobile: 'pertanyaan_mobile',
        analisa: 'analysis',
        prd: 'prd',
        architecture: 'architecture',
        erd: 'erd',
        api_contract: 'api_contract',
        phases_web: 'phases',
        standards_web: 'standards',
        master_web: 'master_prompt',
        phases_mobile: 'mobile_phases',
        standards_mobile: 'mobile_standards',
        master_mobile: 'mobile_master_prompt',
        agents: 'agents',
      };
      const loaded: Record<string, string> = {};
      resumeStages.forEach(s => {
        const col = colMap[s.key];
        if (!col) return;
        const val = v[col];
        if (val) loaded[s.key] = typeof val === 'object' ? JSON.stringify(val) : String(val);
      });
      setArtifacts(loaded as Record<StageKey, string>);

      // Gate tracking: muat phase_progress; bila ada fase web belum selesai dan firstIdx
      // sudah melampaui master_web (ke mobile), jangan auto-lanjut — tahan di master_web.
      const prog = v.phase_progress ?? [];
      setPhaseProg(prog);
      let resumeWebPhases: PhaseItem[] = [];
      try { const p = JSON.parse(String(loaded.phases_web || '[]')); resumeWebPhases = Array.isArray(p) ? (p as PhaseItem[]) : []; } catch { resumeWebPhases = []; }
      const webKeySet = new Set(resumeWebPhases.map(ph => ph.key ?? ''));
      const webDoneCount = webKeySet.size > 0
        ? prog.filter(pp => webKeySet.has(pp.phase_key) && pp.done).length
        : 0;
      const resumeWebTrackingDone = webKeySet.size === 0 || webDoneCount >= webKeySet.size;

      const masterWebIdx = resumeStages.findIndex(s => s.key === 'master_web');
      if (!resumeWebTrackingDone && masterWebIdx >= 0 && idx > masterWebIdx) {
        idx = masterWebIdx;
        firstIdx = -1; // jangan auto-start pipeline; biarkan user konfirmasi
        setCurrent(idx);
        setStatus(s => ({ ...s, master_web: 'running' as StageState }));
        setError('Tracking fase web belum selesai. Lanjutkan hanya setelah kamu yakin web sudah jadi.');
      }

      if (firstIdx >= 0) startPipeline(v.id, resumeStages[firstIdx].key);
    }).catch(err => setError(err instanceof Error ? err.message : 'Gagal memuat data project'));
  }, [isResume, resumeVersionId, started, startPipeline]);

  // Auto-scroll modal output
  const activeArtifact = artifacts[activeKey];
  useEffect(() => {
    if (outputRef.current) {
      outputRef.current.scrollTop = outputRef.current.scrollHeight;
    }
  }, [activeArtifact]);

  async function start() {
    if (!title.trim() || !idea.trim()) return;
    if (creatingRef.current) return;

    creatingRef.current = true;
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
      creatingRef.current = false;
      setCreating(false);
      console.error('Failed to create project:', err);
    }
  }

  function approveNext() {
    if (!versionId || current + 1 >= stages.length) return;
    if (stages[current].key === 'pertanyaan') return;

    const currentKey = stages[current].key;
    // Gate: bila current = master_web (akhir track web) & tracking fase belum selesai,
    // jangan langsung lanjut — minta konfirmasi user (web kemungkinan belum selesai dibangun).
    if (currentKey === 'master_web' && !webTrackingDone) {
      setPendingConfirmMaster(true);
      return;
    }

    const nextStage = stages[current + 1].key;

    if (abortRef.current) {
      abortRef.current.abort();
    }

    doStream(versionId, nextStage);
  }

  function proceedAfterMasterConfirm() {
    setPendingConfirmMaster(false);
    if (!versionId || current + 1 >= stages.length) return;
    const nextStage = stages[current + 1].key;
    if (abortRef.current) {
      abortRef.current.abort();
    }
    doStream(versionId, nextStage);
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

    doStream(versionId, currentStage);
  }

  function cancelGeneration() {
    cancelled.current = true;
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    setStatus(s => ({ ...s, [activeKeyRef.current]: 'error' }));
    setError("Pembuatan plan dibatalkan.");
    setShowCancelConfirm(false);
  }

  async function reset() {
    if (deleting) return;
    cancelled.current = true;
    if (abortRef.current) {
      abortRef.current.abort();
      abortRef.current = null;
    }
    fallbackFetched.current.clear();

    const pid = projectId;
    setDeleting(true);
    setError("");

    // Hapus project yang sedang berjalan secara permanen (backend cascade menghapus versions).
    if (pid) {
      try {
        await apiDelete(`/projects/${pid}`);
      } catch (err) {
        console.error("Gagal menghapus project:", err);
      }
    }

    setDeleting(false);
    setProjectId(null);
    setVersionId(null);
    setStarted(false);
    setCurrent(0);
    setStatus(initStatus(target));
    setArtifacts({} as Record<StageKey, string>);
    setAnswers({});
    setMcqAnswers({});
    setMobileMcqAnswers({});
    setPhaseProg([]);
    setRetryInfo(null);
    setTitle("");
    setIdea("");
    setSelectedTemplate("");
    setEditingStage(null);
    setEditContent("");
    // Kosongkan query resume agar isResume=false → kembali ke form input (bukan spinner).
    router.replace("/new");
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
    <ErrorBoundary>
    <div className="mx-auto max-w-5xl">
      <div className="mb-6 flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
        <div>
          <h1 className="text-2xl font-bold">Menyusun Plan…</h1>
          <p className="mt-1 line-clamp-1 text-sm text-[var(--color-fg-muted)]">{idea}</p>
        </div>
        <Button variant="secondary" size="sm" onClick={reset} disabled={deleting} data-testid="reset-plan">{deleting ? <Loader2 size={15} className="animate-spin" /> : <RotateCcw size={15} />} {deleting ? "Menghapus..." : "Mulai Ulang"}</Button>
      </div>

      <div className={`grid gap-6 ${showTrackingPanel ? "lg:grid-cols-[260px_1fr_340px]" : "lg:grid-cols-[280px_1fr]"}`}>
        {/* Stage tracker */}
        <div className="space-y-2">
          {(() => {
            const totalTokens = Object.values(stageTokens ?? {}).reduce<number>(
              (sum, n) => sum + (typeof n === "number" ? n : 0),
              0,
            );
            const cost = (totalTokens * (providerRate ?? 0)).toFixed(4);
            return (
              <div className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-2 text-[11px] text-[var(--color-fg-muted)]" style={{ fontVariantNumeric: "tabular-nums" }}>
                <div className="flex items-center justify-between">
                  <span>Total token</span>
                  <span className="font-mono text-[var(--color-fg)]">{totalTokens.toLocaleString("id-ID")}</span>
                </div>
                <div className="mt-1 flex items-center justify-between">
                  <span>Estimasi biaya</span>
                  <span className="font-mono text-[var(--color-fg)]">~${cost}</span>
                </div>
              </div>
            );
          })()}
          {stages.map((s, i) => {
            const st = status[s.key];
            // CP-3: key mencakup status agar CSS animation re-trigger saat transisi ke 'done'.
            const rowKey = `${s.key}:${st}`;
            return (
              <div
                key={rowKey}
                data-testid={`stage-${s.key}`}
                data-state={st}
                className={`flex items-start gap-3 rounded-xl border p-3 transition ${
                  i === current && st === "running"
                    ? "border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_8%,transparent)]"
                    : "border-[var(--color-border)]"
                } ${st === "done" ? "done-flash" : ""}`}
              >
                <span className="mt-0.5">
                  {st === "done" ? (
                    <span className="check-draw grid h-6 w-6 place-items-center rounded-full bg-[var(--color-success)] text-white"><Check size={14} /></span>
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
                    if (text) copyToClipboard(text);
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
                    onClick={async () => {
                      setArtifacts(prev => ({ ...prev, [activeKey]: editContent }));
                      setEditingStage(null);
                      setEditContent("");
                      if (versionId) {
                        setSavingArtifact(true);
                        try {
                          await apiPatch(`/versions/${versionId}/artifacts`, { stage: activeKey, content: editContent });
                        } catch (err) {
                          setError(err instanceof Error ? err.message : "Gagal menyimpan artifact");
                        } finally {
                          setSavingArtifact(false);
                        }
                      }
                    }}
                    disabled={savingArtifact}
                    className="inline-flex items-center gap-1.5 rounded-lg bg-[var(--color-accent)] px-3 py-1.5 text-xs text-white hover:opacity-90 disabled:opacity-50"
                  >
                    {savingArtifact ? <Loader2 size={13} className="animate-spin" /> : "Simpan"}
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
                    {mcqData ? (
                      <McqForm
                        mcqData={mcqData}
                        answers={mcqAnswers}
                        onAnswerChange={(qId, ans) => setMcqAnswers(prev => ({ ...prev, [qId]: ans }))}
                        onSubmit={async () => {
                          if (!versionId) return;
                          const formatted: Record<string, string> = {};
                          Object.entries(mcqAnswers).forEach(([qId, ans]) => {
                            const q = mcqData.questions.find((x) => x.id === qId);
                            formatted[`${qId}: ${q?.question || ""}`] = ans.selected === "E"
                              ? `E. Lainnya: ${ans.custom_text || ""}`
                              : `${ans.selected}. ${q?.options.find(o => o.key === ans.selected)?.text || ""}`;
                          });
                          try { await apiPatch(`/versions/${versionId}/answers`, { answers: formatted }); }
                          catch (e) { setError(e instanceof Error ? e.message : "Gagal menyimpan jawaban"); return; }
                          const nextStage = stages[current + 1]?.key;
                            if (nextStage && versionId) {
                              setStatus(s => ({ ...s, [nextStage]: 'running' }));
                              setCurrent(current + 1);
                              doStream(versionId, nextStage);
                            }
                          }}
                          submitLabel="Kirim Jawaban & Lanjutkan"
                        />
                    ) : (
                      <>
                        <h4 className="font-semibold">Jawab pertanyaan klarifikasi berikut:</h4>
                        {questions.length === 0 && artifacts.pertanyaan && (
                          <div className="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                            <Loader2 size={14} className="animate-spin" />
                            Memproses pertanyaan{retryInfo ? ` (percobaan ${retryInfo.attempt}/${retryInfo.max})` : "..."}
                          </div>
                        )}
                        {questions.map((q: string, i: number) => (
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
                            try { await apiPatch(`/versions/${versionId}/answers`, { answers }); }
                            catch (e) { setError(e instanceof Error ? e.message : "Gagal menyimpan jawaban"); return; }
                            const nextStage = stages[current + 1]?.key;
                            if (nextStage && versionId) {
                              setStatus(s => ({ ...s, [nextStage]: 'running' }));
                              setCurrent(current + 1);
                              doStream(versionId, nextStage);
                            }
                          }}
                          disabled={!Object.values(answers).some(a => a.trim())}
                        >
                          <ArrowRight size={15} /> Kirim Jawaban & Lanjutkan
                        </Button>
                      </>
                    )}
                  </div>
                ) : activeKey === "pertanyaan_mobile" && status.pertanyaan_mobile === "done" ? (
                  <div className="space-y-4">
                    {mcqMobileData ? (
                      <McqForm
                        mcqData={mcqMobileData}
                        answers={mobileMcqAnswers}
                        onAnswerChange={(qId, ans) => setMobileMcqAnswers(prev => ({ ...prev, [qId]: ans }))}
                        onSubmit={async () => {
                          if (!versionId) return;
                          const formatted: Record<string, string> = {};
                          Object.entries(mobileMcqAnswers).forEach(([qId, ans]) => {
                            const q = mcqMobileData.questions.find((x) => x.id === qId);
                            formatted[`${qId}: ${q?.question || ""}`] = ans.selected === "E"
                              ? `E. Lainnya: ${ans.custom_text || ""}`
                              : `${ans.selected}. ${q?.options.find(o => o.key === ans.selected)?.text || ""}`;
                          });
                          try { await apiPatch(`/versions/${versionId}/answers`, { mobile_answers: formatted }); }
                          catch (e) { setError(e instanceof Error ? e.message : "Gagal menyimpan jawaban mobile"); return; }
                          const nextStage = stages[current + 1]?.key;
                          if (nextStage && versionId) {
                            setStatus(s => ({ ...s, [nextStage]: 'running' }));
                            setCurrent(current + 1);
                            doStream(versionId, nextStage);
                          }
                        }}
                        submitLabel="Kirim Jawaban Mobile & Lanjutkan"
                        ambiguitiesLabel="Area mobile yang perlu diperjelas:"
                      />
                    ) : (
                      <div className="flex items-center gap-2 text-sm text-[var(--color-fg-muted)]">
                        <Loader2 size={14} className="animate-spin" />
                        Memproses pertanyaan mobile{retryInfo ? ` (percobaan ${retryInfo.attempt}/${retryInfo.max})` : "..."}
                      </div>
                    )}
                  </div>
                ) : (activeKey === "erd" && status.erd === "done") || (activeKey === "architecture" && status.architecture === "done") || (activeKey === "master_web" && status.master_web === "done") ? null : (
                  <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                    {status[activeKey] === "done" && !artifacts[activeKey]
                      ? "Tidak ada output"
                      : artifacts[activeKey] || "Menunggu hasil AI..."}
                  </Markdown>
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
                      {nodes.length > 0 && <div className="mb-6"><ErdDiagramDynamic erd={{ nodes, edges }} /></div>}
                      {cleanText && (
                        <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                          {cleanText}
                        </Markdown>
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
                      const erdData = parseErdArtifact(artifacts.erd);
                      if (!erdData) return <pre className="whitespace-pre-wrap text-sm">{artifacts.erd}</pre>;
                      return (
                        <>
                          <div className="mb-6 mt-4"><ErdDiagramDynamic erd={erdData} /></div>
                            {(() => { const ac = erdData.api_contract; return ac && ac.length > 0 ? <>
                              <div className="mt-6">
                                <h4 className="mb-3 font-semibold">API Contract</h4>
                                <ApiContractTable items={ac} />
                              </div>
                            </> : null; })()}
                        </>
                      );
                    })()}
                  </>
                )}
                {activeKey === "phases_web" && artifacts.phases_web && (() => {
                  try {
                    const parsed = JSON.parse(artifacts.phases_web);
                    const phases: PhaseItem[] = Array.isArray(parsed) ? parsed : [];
                    if (phases.length === 0) throw new Error("not array");
                    return <PhaseBreakdownCard phases={phases} label="Phase Breakdown Web" />;
                  } catch {
                    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{artifacts.phases_web}</Markdown>;
                  }
                })()}
                {activeKey === "phases_mobile" && artifacts.phases_mobile && (() => {
                  try {
                    const parsed = JSON.parse(artifacts.phases_mobile);
                    const phases: PhaseItem[] = Array.isArray(parsed) ? parsed : [];
                    if (phases.length === 0) throw new Error("not array");
                    return <PhaseBreakdownCard phases={phases} label="Phase Breakdown Mobile" />;
                  } catch {
                    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{artifacts.phases_mobile}</Markdown>;
                  }
                })()}
                {activeKey === "api_contract" && artifacts.api_contract && (() => {
                  try {
                    const parsed = JSON.parse(artifacts.api_contract);
                    const ac: ApiContractItem[] = Array.isArray(parsed) ? parsed : [];
                    if (ac.length === 0) throw new Error("not array");
                    return (
                      <Card className="p-4">
                        <h3 className="mb-4 font-semibold">API Contract ({ac.length} endpoint)</h3>
                        <ApiContractTable items={ac} />
                      </Card>
                    );
                  } catch {
                    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{artifacts.api_contract}</Markdown>;
                  }
                })()}
                {activeKey === "master_web" && artifacts.master_web && (() => {
                  const masterPrompt = artifacts.master_web;
                  return (
                    <Card className="p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Master Prompt Web</h3>
                        <Button variant="secondary" size="sm" onClick={() => copyToClipboard(masterPrompt)}>
                          <Copy size={13} /> Salin Master Prompt
                        </Button>
                      </div>
                      <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{masterPrompt}</Markdown>
                    </Card>
                  );
                })()}
                {activeKey === "master_mobile" && artifacts.master_mobile && (() => {
                  const masterPrompt = artifacts.master_mobile;
                  return (
                    <Card className="p-4">
                      <div className="mb-3 flex items-center justify-between">
                        <h3 className="font-semibold">Master Prompt Mobile</h3>
                        <Button variant="secondary" size="sm" onClick={() => copyToClipboard(masterPrompt)}>
                          <Copy size={13} /> Salin Master Prompt
                        </Button>
                      </div>
                      <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{masterPrompt}</Markdown>
                    </Card>
                  );
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
                  <Button variant="secondary" size="sm" onClick={retryStage}><RotateCcw size={15} /> Coba Lagi</Button>
                )}
                {status[activeKey] === "done" && activeKey !== 'pertanyaan' && (
                  <Button size="sm" onClick={approveNext} data-testid="approve-next">Approve & Lanjut <ArrowRight size={15} /></Button>
                )}
              </div>
            </Card>
          )}

          {allDone && (
            <>
              <Confetti />
              <Card className="flex flex-col items-center gap-3 p-6 text-center">
              <span className="grid h-12 w-12 place-items-center rounded-full bg-[var(--color-success)] text-white"><Check size={24} /></span>
              <h3 className="text-lg font-semibold">Plan selesai! 🎉</h3>
              <p className="text-sm text-[var(--color-fg-muted)]">Semua artefak siap. Salin master prompt & mulai bangun dengan AI agent.</p>
              <div className="flex flex-wrap justify-center gap-2">
                <Button
                  variant="secondary"
                  onClick={() => { if (artifacts.master_web) copyToClipboard(artifacts.master_web); }}
                ><Copy size={15} /> Salin Master Prompt (Web)</Button>
                {target === "both" && artifacts.master_mobile && (
                  <Button
                    variant="secondary"
                    onClick={() => copyToClipboard(artifacts.master_mobile as string)}
                  ><Copy size={15} /> Salin Master Prompt (Mobile)</Button>
                )}
                {target === "both" && artifacts.agents && (
                  <Button
                    variant="secondary"
                    onClick={() => copyToClipboard(artifacts.agents as string)}
                  ><Copy size={15} /> Salin Agents</Button>
                )}
                <Button onClick={() => projectId && router.push(`/projects/${projectId}`)} data-testid="goto-project">Buka Project <ArrowRight size={15} /></Button>
              </div>
            </Card>
            </>
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

        {/* Tracking side panel */}
        {showTrackingPanel && trackingPhases.length > 0 && (
          <TrackingPanel
            phases={trackingPhases}
            progMap={progMap}
            webhookUrl={`/api/webhooks/phase-complete`}
          />
        )}
      </div>

      {/* Full-screen Build Wall untuk master_* + agents */}
      <BuildWall
        open={status[activeKey] === "running" && ["master_web", "master_mobile", "agents"].includes(activeKey)}
        stageLabel={`Tahap ${current + 1}/${stages.length}: ${stages[current]?.label ?? ""}`}
        content={artifacts[activeKey] ?? ""}
        isRunning={status[activeKey] === "running"}
        onClose={() => { if (status[activeKey] !== "running") setCurrent(Math.min(current + 1, stages.length - 1)); }}
        throughput={{
          startedAt: status[activeKey] === "running" ? startedAt : null,
          bytes: artifacts[activeKey]?.length ?? 0,
        }}
        sidebar={
          <div className="space-y-3 text-xs">
            <div>
              <div className="text-[10px] uppercase tracking-wider text-[var(--color-fg-subtle)]">Deskripsi</div>
              <div className="mt-1 text-[var(--color-fg)]">{stages[current]?.desc}</div>
            </div>
            {retryInfo && (
              <div className="flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 font-medium text-amber-600">
                <Loader2 size={12} className="animate-spin" />
                Percobaan {retryInfo.attempt}/{retryInfo.max}
              </div>
            )}
            <Button variant="secondary" size="sm" onClick={() => setShowCancelConfirm(true)}>
              <AlertCircle size={14} /> Batalkan
            </Button>
          </div>
        }
      />

      {/* Loading modal untuk stage biasa (non-master) */}
      <Modal
        open={status[activeKey] === "running" && !["master_web", "master_mobile", "agents"].includes(activeKey)}
        onClose={() => {}}
        title={`Tahap ${current + 1}/${stages.length}: ${stages[current]?.label ?? ""}`}
        size="lg"
        closeOnBackdrop={false}
      >
        {/* Header */}
        <div className="mb-4 flex items-center gap-3">
          <Loader2 size={24} className="animate-spin text-[var(--color-brand)]" />
          <div className="flex-1">
            <div className="text-sm text-[var(--color-fg-muted)]">{stages[current]?.desc ?? ""}</div>
            {retryInfo && (
              <div className="flex items-center gap-2 rounded-lg bg-amber-500/10 px-2.5 py-1 text-xs font-medium text-amber-600">
                <Loader2 size={12} className="animate-spin" />
                Percobaan ulang {retryInfo.attempt}{retryInfo.max ? `/${retryInfo.max}` : ""} — mencari minimal 5 pertanyaan
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
          <div ref={outputRef} className="max-h-80 min-h-[80px] overflow-hidden rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)]">
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
          <Button variant="secondary" size="sm" onClick={() => setShowCancelConfirm(true)}>
            <AlertCircle size={15} /> Batalkan
          </Button>
        </div>
      </Modal>

      {/* Konfirmasi lanjut setelah master_web — tracking fase web belum selesai */}
      <Modal open={pendingConfirmMaster} onClose={() => setPendingConfirmMaster(false)} title="Tracking fase web belum selesai" size="sm">
        <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
          Ada fase web yang masih berjalan / belum ditandai selesai. Web kemungkinan belum selesai dibangun.
          Yakin melanjutkan ke tahap berikutnya?
        </p>
        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" size="sm" onClick={() => setPendingConfirmMaster(false)}>Batal</Button>
          <Button size="sm" onClick={proceedAfterMasterConfirm}>Tetap Lanjut <ArrowRight size={15} /></Button>
        </div>
      </Modal>

      {/* Konfirmasi batalkan */}
      <Modal open={showCancelConfirm} onClose={() => setShowCancelConfirm(false)} title="Batalkan pembuatan?" size="sm">
        <p className="mt-2 text-sm text-[var(--color-fg-muted)]">
          Proses akan dihentikan dan stage saat ini ditandai error. Yakin membatalkan?
        </p>
        <div className="mt-5 flex justify-end gap-2">
          <Button variant="secondary" size="sm" onClick={() => setShowCancelConfirm(false)}>Lanjutkan</Button>
          <Button variant="danger" size="sm" onClick={cancelGeneration}><AlertCircle size={15} /> Ya, Batalkan</Button>
        </div>
      </Modal>
    </div>
    </ErrorBoundary>
  );
}

