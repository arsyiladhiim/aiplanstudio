import { useEffect, useMemo, useRef, useState } from "react";
import { Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import { ChevronDown, ChevronRight, Loader2, Check, Clock, AlertCircle, CircleDot, FileText, Menu, Settings, GitBranch, Link, Link2 } from "lucide-react";
import type { PhaseItem, SubItem } from "./PhaseBreakdownCard";
import { apiSetupAutoTracking, type AutoTrackingToken } from "@/lib/api";

const TASK_TYPES = ["halaman", "menu", "fitur", "flow", "api"] as const;
type TaskType = (typeof TASK_TYPES)[number];

export interface TaskProgressItem {
  task_key: string;
  task_type?: string;
  title?: string;
  status?: "pending" | "running" | "done" | "error";
  output?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
}

export interface ProgressItem {
  phase_key: string;
  done: boolean;
  status?: "pending" | "running" | "done" | "error";
  output?: string | null;
  started_at?: string | null;
  finished_at?: string | null;
  tasks?: TaskProgressItem[];
}

function fmtTime(ts?: string | null): string {
  if (!ts) return "";
  try {
    return new Date(ts).toLocaleTimeString("id-ID", { hour: "2-digit", minute: "2-digit", second: "2-digit" });
  } catch {
    return "";
  }
}

function StatusIcon({ status, size = 11 }: { status?: string; size?: number }) {
  if (status === "done") return <span className="grid place-items-center rounded-full bg-[var(--color-success)] text-white" style={{ width: size + 4, height: size + 4 }}><Check size={size} /></span>;
  if (status === "running") return <span className="grid place-items-center rounded-full bg-[var(--color-brand)] text-white" style={{ width: size + 4, height: size + 4 }}><Loader2 size={size} className="animate-spin" /></span>;
  if (status === "error") return <span className="grid place-items-center rounded-full bg-[var(--color-danger)] text-white" style={{ width: size + 4, height: size + 4 }}><AlertCircle size={size} /></span>;
  return <span className="grid place-items-center rounded-full border border-[var(--color-border)] text-[var(--color-fg-subtle)]" style={{ width: size + 4, height: size + 4 }}><CircleDot size={size - 1} /></span>;
}

const TYPE_ICONS: Record<string, typeof FileText> = {
  halaman: FileText,
  menu: Menu,
  fitur: Settings,
  flow: GitBranch,
  api: Link,
};

const TYPE_LABELS: Record<string, string> = {
  halaman: "Halaman",
  menu: "Menu",
  fitur: "Fitur",
  flow: "Flow",
  api: "API",
};

function countSubItems(p: PhaseItem): number {
  return (p.halaman?.length ?? 0) + (p.menu?.length ?? 0) + (p.fitur?.length ?? 0) + (p.flow?.length ?? 0) + (p.api?.length ?? 0);
}

function getTaskProgMap(prog?: ProgressItem): Record<string, TaskProgressItem> {
  if (!prog?.tasks) return {};
  return Object.fromEntries(prog.tasks.map((t) => [t.task_key, t]));
}

export function TrackingPanel({
  phases,
  progMap,
  webhookUrl,
  projectId,
  versionId,
}: {
  phases: PhaseItem[];
  progMap: Record<string, ProgressItem>;
  webhookUrl?: string;
  projectId?: number | null;
  versionId?: number | null;
}) {
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const toggleExpand = (key: string) => setExpanded((p) => ({ ...p, [key]: !p[key] }));
  const scrollRef = useRef<HTMLDivElement>(null);
  const itemRefs = useRef<Record<string, HTMLDivElement | null>>({});

  // CP-6 T8: granular task_type filter (client-side on tasks list).
  const [typeFilter, setTypeFilter] = useState<TaskType | "all">("all");

  // CP-6 T5: Setup Tracking modal state.
  const [setupOpen, setSetupOpen] = useState(false);
  const [setupLoading, setSetupLoading] = useState(false);
  const [setupResult, setSetupResult] = useState<AutoTrackingToken | null>(null);
  const [setupError, setSetupError] = useState<string | null>(null);

  const tokenCacheKey = projectId && versionId ? `tracking-token-${projectId}-${versionId}` : null;
  const [hasToken, setHasToken] = useState<boolean>(() =>
    tokenCacheKey ? typeof window !== "undefined" && !!sessionStorage.getItem(tokenCacheKey) : false,
  );

  const doneCount = phases.filter((p) => progMap[p.key ?? ""]?.status === "done").length;
  const total = phases.length;
  const pct = total > 0 ? Math.round((doneCount / total) * 100) : 0;

  // CP-6 T8: per-type progress counters (filtered by typeFilter).
  const typeCounts = useMemo(() => {
    const counts: Record<TaskType, { done: number; total: number }> = {
      halaman: { done: 0, total: 0 },
      menu: { done: 0, total: 0 },
      fitur: { done: 0, total: 0 },
      flow: { done: 0, total: 0 },
      api: { done: 0, total: 0 },
    };
    for (const p of phases) {
      const prog = progMap[p.key ?? ""];
      for (const type of TASK_TYPES) {
        const items = (p[type] as SubItem[] | undefined) ?? [];
        for (const it of items) {
          counts[type].total += 1;
          const tp = it.key ? prog?.tasks?.find((t) => t.task_key === it.key) : undefined;
          if (tp?.status === "done") counts[type].done += 1;
        }
      }
    }
    return counts;
  }, [phases, progMap]);

  const filteredPhases = useMemo(() => {
    if (typeFilter === "all") return phases;
    return phases.map((p) => {
      const matching = (p[typeFilter] as SubItem[] | undefined) ?? [];
      const placeholder: PhaseItem = { ...p, [typeFilter]: matching };
      for (const t of TASK_TYPES) {
        if (t !== typeFilter) (placeholder as Record<string, unknown>)[t] = [];
      }
      return placeholder;
    });
  }, [phases, typeFilter]);

  // Auto-scroll fase running ke tengah viewport.
  useEffect(() => {
    const runningKey = phases.find((p) => progMap[p.key ?? ""]?.status === "running")?.key;
    if (!runningKey) return;
    const el = itemRefs.current[runningKey];
    const container = scrollRef.current;
    if (!el || !container) return;
    const elTop = el.offsetTop;
    const containerH = container.clientHeight;
    const target = elTop - containerH / 2 + el.clientHeight / 2;
    container.scrollTo({ top: Math.max(0, target), behavior: "smooth" });
  }, [phases, progMap]);

  async function handleSetupTracking() {
    if (!projectId || !versionId) return;
    setSetupLoading(true);
    setSetupError(null);
    try {
      const result = await apiSetupAutoTracking(projectId, versionId);
      setSetupResult(result);
      if (tokenCacheKey && result.token && result.secret) {
        sessionStorage.setItem(
          tokenCacheKey,
          JSON.stringify({ token: result.token, secret: result.secret, createdAt: Date.now() }),
        );
        setHasToken(true);
      }
    } catch (err) {
      setSetupError(err instanceof Error ? err.message : "Gagal membuat token.");
    } finally {
      setSetupLoading(false);
    }
  }

  function closeSetup() {
    setSetupOpen(false);
    setSetupResult(null);
    setSetupError(null);
  }

  async function copyToClipboard(text: string) {
    try {
      await navigator.clipboard.writeText(text);
    } catch {
      // fallback noop — UI sudah show nilai
    }
  }

  return (
    <div ref={scrollRef} className="sticky top-4 max-h-[calc(100vh-2rem)] overflow-y-auto rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)]">
      <div className="sticky top-0 z-10 border-b border-[var(--color-border)] bg-[var(--color-surface)] px-4 py-3">
        <div className="flex items-center justify-between">
          <h3 className="text-sm font-semibold">Tracking Build</h3>
          <Badge tone={doneCount === total && total > 0 ? "success" : "muted"}>
            {doneCount}/{total}
          </Badge>
        </div>
        <div className="mt-2 h-1.5 overflow-hidden rounded-full bg-[var(--color-surface-2)]">
          <div className="h-full rounded-full bg-[var(--color-brand)] transition-all duration-500" style={{ width: `${pct}%` }} />
        </div>
        <div className="mt-2 flex items-center justify-between gap-2">
          {webhookUrl && (
            <div className="truncate text-[10px] text-[var(--color-fg-subtle)]" title={webhookUrl}>
              Webhook: {webhookUrl}
            </div>
          )}
          <Button
            variant={hasToken ? "ghost" : "primary"}
            size="sm"
            onClick={() => setSetupOpen(true)}
            data-testid="setup-tracking-btn"
            className="shrink-0"
          >
            <Link2 size={12} />
            {hasToken ? "Token Aktif" : "Setup Tracking"}
          </Button>
        </div>
        {/* CP-6 T8: granular filter chips */}
        <div className="mt-2 flex flex-wrap gap-1">
          <FilterChip
            active={typeFilter === "all"}
            label={`All ${total}`}
            onClick={() => setTypeFilter("all")}
          />
          {TASK_TYPES.map((t) => (
            <FilterChip
              key={t}
              active={typeFilter === t}
              label={`${TYPE_LABELS[t]} ${typeCounts[t].done}/${typeCounts[t].total}`}
              onClick={() => setTypeFilter(t)}
            />
          ))}
        </div>
      </div>

      <div className="divide-y divide-[var(--color-border)]">
        {filteredPhases.length === 0 && (
          <div className="px-4 py-6 text-center text-xs text-[var(--color-fg-muted)]">
            {phases.length === 0
              ? "Belum ada fase. Jalankan master prompt untuk mulai tracking."
              : "Tidak ada sub-item untuk filter ini."}
          </div>
        )}
        {filteredPhases.map((p, i) => {
          const prog = progMap[p.key ?? ""];
          const st = prog?.status ?? "pending";
          const subCount = countSubItems(p);
          const taskProgMap = getTaskProgMap(prog);
          const isExpanded = expanded[p.key ?? `f${i}`] ?? false;

          let doneSubItems = 0;
          const totalSubItems = subCount;
          if (prog?.tasks) {
            doneSubItems = prog.tasks.filter((t) => t.status === "done").length;
          }

          return (
            <div
              key={p.key ?? i}
              ref={(el) => { itemRefs.current[p.key ?? `f${i}`] = el; }}
              className={`px-4 py-3 transition-colors ${st === "running" ? "running-glow" : ""}`}
            >
              <button
                className="flex w-full items-start gap-2.5 text-left"
                onClick={() => subCount > 0 && toggleExpand(p.key ?? `f${i}`)}
              >
                <div className="mt-0.5 shrink-0"><StatusIcon status={st} /></div>
                <div className="min-w-0 flex-1">
                  <div className="flex items-center gap-1.5">
                    {subCount > 0 && (isExpanded ? <ChevronDown size={12} className="shrink-0 text-[var(--color-fg-subtle)]" /> : <ChevronRight size={12} className="shrink-0 text-[var(--color-fg-subtle)]" />)}
                    <div className="text-xs font-semibold leading-tight">{p.title ?? p.key ?? `Fase ${i + 1}`}</div>
                  </div>
                  {p.key && <div className="mt-0.5 text-[10px] text-[var(--color-fg-subtle)]">{p.key}</div>}
                  {subCount > 0 && (
                    <div className="mt-0.5 text-[10px] text-[var(--color-fg-muted)]">
                      {doneSubItems}/{totalSubItems} checkpoint
                    </div>
                  )}
                  {prog?.output && !isExpanded && (
                    <div className="mt-1 truncate text-[10px] text-[var(--color-fg-subtle)]">{prog.output.length > 80 ? prog.output.slice(0, 80) + "…" : prog.output}</div>
                  )}
                  {(prog?.started_at || prog?.finished_at) && (
                    <div className="mt-1 flex items-center gap-1 text-[10px] text-[var(--color-fg-subtle)]">
                      <Clock size={9} />
                      {prog.finished_at ? `${fmtTime(prog.started_at)} - ${fmtTime(prog.finished_at)}` : `${fmtTime(prog.started_at)} - berjalan…`}
                    </div>
                  )}
                </div>
              </button>

              {isExpanded && subCount > 0 && (
                <div className="mt-2 ml-7 space-y-2">
                  {(["halaman", "menu", "fitur", "flow", "api"] as const).map((type) => {
                    const items = p[type] as SubItem[] | undefined;
                    if (!items || items.length === 0) return null;
                    const Icon = TYPE_ICONS[type];
                    return (
                      <div key={type}>
                        <div className="flex items-center gap-1.5 text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">
                          <Icon size={11} /> {TYPE_LABELS[type]}
                        </div>
                        <ul className="mt-1 space-y-1">
                          {items.map((it, j) => {
                            const taskProg = it.key ? taskProgMap[it.key] : undefined;
                            const taskSt = taskProg?.status ?? "pending";
                            return (
                              <li key={it.key || j} className="flex items-start gap-2 text-xs">
                                <span className="mt-0.5 shrink-0"><StatusIcon status={taskSt} size={9} /></span>
                                <div className="min-w-0 flex-1">
                                  <div className="leading-tight">
                                    {it.title}
                                    {it.desc && <span className="text-[var(--color-fg-subtle)]"> — {it.desc}</span>}
                                    {it.func && <span className="text-[var(--color-fg-subtle)]"> — {it.func}</span>}
                                    {it.steps && <span className="text-[var(--color-fg-subtle)]"> — {it.steps}</span>}
                                    {it.endpoint && <span className="text-[var(--color-fg-subtle)]"> — {it.method} {it.endpoint}</span>}
                                  </div>
                                  {taskProg?.output && (
                                    <div className="mt-0.5 truncate text-[10px] text-[var(--color-fg-subtle)]">{taskProg.output.length > 60 ? taskProg.output.slice(0, 60) + "…" : taskProg.output}</div>
                                  )}
                                </div>
                              </li>
                            );
                          })}
                        </ul>
                      </div>
                    );
                  })}
                </div>
              )}
            </div>
          );
        })}
      </div>

      <div className="border-t border-[var(--color-border)] px-4 py-2 text-[10px] text-[var(--color-fg-subtle)]">
        Status diperbarui real-time via webhook SSE.
      </div>

      {/* CP-6 T5: Setup Tracking modal */}
      <Modal
        open={setupOpen}
        title={hasToken && !setupResult?.token ? "Tracking Token" : "Setup Tracking Token"}
        onClose={closeSetup}
      >
          <div className="space-y-3">
            <p className="text-sm text-[var(--color-fg-muted)]">
              Buat token webhook untuk version ini. Token dipakai coding agent untuk mengirim checkpoint per fase/sub-item.
              Secret hanya ditampilkan <strong>sekali</strong> — simpan sekarang.
            </p>
            {!setupResult && !setupError && (
              <Button
                variant="primary"
                size="md"
                onClick={handleSetupTracking}
                disabled={setupLoading}
                data-testid="create-token-btn"
              >
                {setupLoading ? <Loader2 size={14} className="animate-spin" /> : <Link2 size={14} />}
                {setupLoading ? "Membuat…" : "Buat Token Sekarang"}
              </Button>
            )}
            {setupError && (
              <div className="rounded-md bg-[color-mix(in_oklab,var(--color-danger)_15%,transparent)] p-3 text-sm text-[var(--color-danger)]">
                {setupError}
              </div>
            )}
            {setupResult && (
              <div className="space-y-3">
                {setupResult.existing ? (
                  <div className="rounded-md bg-[var(--color-surface-2)] p-3 text-sm">
                    Token sudah pernah dibuat. Buat token baru via <code className="rounded bg-[var(--color-bg-soft)] px-1.5 py-0.5">/projects/{`{id}`}/tokens</code> jika secret hilang.
                  </div>
                ) : (
                  <>
                    <SecretField label="X-Token-Secret" value={setupResult.secret ?? ""} onCopy={copyToClipboard} />
                    <SecretField label="Bearer Token" value={setupResult.token ?? ""} onCopy={copyToClipboard} />
                    <div className="rounded-md bg-[var(--color-surface-2)] p-3 text-xs text-[var(--color-fg-muted)]">
                      <strong>Cara pakai (contoh curl):</strong>
                      <pre className="mt-1 overflow-x-auto rounded bg-[var(--color-bg-soft)] p-2 text-[10px]">
{`curl -X POST ${webhookUrl ?? "/api/webhooks/phase-complete"} \\
  -H "Authorization: Bearer ${setupResult.token ?? "<TOKEN>"}" \\
  -H "X-Token-Secret: ${setupResult.secret ?? "<SECRET>"}" \\
  -H "X-Timestamp: $(date +%s)" \\
  -H "X-Signature: <hmac_sha256(timestamp.body, secret)>" \\
  -H "Content-Type: application/json" \\
  -d '{"version_id": ${versionId ?? 0}, "phase_key": "fase1_setup", "status": "done"}'`}
                      </pre>
                    </div>
                  </>
                )}
              </div>
            )}
          </div>
        </Modal>
    </div>
  );
}

function FilterChip({
  active,
  label,
  onClick,
}: {
  active: boolean;
  label: string;
  onClick: () => void;
}) {
  return (
    <button
      onClick={onClick}
      className={`rounded-full px-2 py-0.5 text-[10px] font-medium transition-colors ${
        active
          ? "bg-[var(--color-brand)] text-white"
          : "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)] hover:bg-[var(--color-surface)]"
      }`}
    >
      {label}
    </button>
  );
}

function SecretField({
  label,
  value,
  onCopy,
}: {
  label: string;
  value: string;
  onCopy: (text: string) => void | Promise<void>;
}) {
  const [copied, setCopied] = useState(false);
  return (
    <div>
      <div className="mb-1 text-xs font-semibold text-[var(--color-fg-muted)]">{label}</div>
      <div className="flex items-center gap-2">
        <code className="flex-1 overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-bg-soft)] px-3 py-2 font-mono text-xs">
          {value || <span className="text-[var(--color-fg-subtle)]">(tersembunyi)</span>}
        </code>
        <Button
          variant="outline"
          size="sm"
          onClick={async () => {
            await onCopy(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
          }}
          disabled={!value}
        >
          {copied ? <Check size={12} /> : <Link2 size={12} />}
          {copied ? "Copied" : "Copy"}
        </Button>
      </div>
    </div>
  );
}
