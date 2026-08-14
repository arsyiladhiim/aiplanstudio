import { useEffect, useRef, useState } from "react";
import { Badge } from "@/components/ui";
import { ChevronDown, ChevronRight, Loader2, Check, Clock, AlertCircle, CircleDot, FileText, Menu, Settings, GitBranch, Link } from "lucide-react";
import type { PhaseItem, SubItem } from "./PhaseBreakdownCard";

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
}: {
  phases: PhaseItem[];
  progMap: Record<string, ProgressItem>;
  webhookUrl?: string;
}) {
  const [expanded, setExpanded] = useState<Record<string, boolean>>({});
  const toggleExpand = (key: string) => setExpanded((p) => ({ ...p, [key]: !p[key] }));
  const scrollRef = useRef<HTMLDivElement>(null);
  const itemRefs = useRef<Record<string, HTMLDivElement | null>>({});

  const doneCount = phases.filter((p) => progMap[p.key ?? ""]?.status === "done").length;
  const total = phases.length;
  const pct = total > 0 ? Math.round((doneCount / total) * 100) : 0;

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
        {webhookUrl && (
          <div className="mt-2 truncate text-[10px] text-[var(--color-fg-subtle)]" title={webhookUrl}>
            Webhook: {webhookUrl}
          </div>
        )}
      </div>

      <div className="divide-y divide-[var(--color-border)]">
        {phases.length === 0 && (
          <div className="px-4 py-6 text-center text-xs text-[var(--color-fg-muted)]">
            Belum ada fase. Jalankan master prompt untuk mulai tracking.
          </div>
        )}
        {phases.map((p, i) => {
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
    </div>
  );
}
