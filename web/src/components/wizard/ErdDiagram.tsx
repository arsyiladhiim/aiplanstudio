"use client";
import { useMemo } from "react";
import ReactFlow, { Background, Controls, Handle, Position, type Node, type Edge } from "reactflow";
import "reactflow/dist/style.css";
import { sampleErd } from "@/lib/mock";
import type { ErdData } from "@/lib/api";

function isPk(field: string): boolean {
  return field === "id" || field.endsWith("_id") || /^(pk|key)_/i.test(field);
}

function isFk(field: string): boolean {
  return field.endsWith("_id") && field !== "id";
}

function TableNode({ data }: { data: { label: string; fields?: string[] } }) {
  return (
    <div className="min-w-[180px] overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg">
      <Handle type="target" position={Position.Left} className="!bg-[var(--color-brand)]" />
      <div className="bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] px-3 py-2 text-sm font-semibold text-white">
        {data.label}
      </div>
      <div className="max-h-56 overflow-y-auto divide-y divide-[var(--color-border)]">
        {(data.fields ?? []).map((f) => {
          const pk = isPk(f);
          const fk = isFk(f);
          return (
            <div key={f} className="flex items-center gap-2 px-3 py-1.5 text-xs">
              <span className={pk ? "font-semibold text-[var(--color-brand)]" : "text-[var(--color-fg-muted)]"}>{f}</span>
              {pk && (
                <span className="ml-auto rounded bg-[var(--color-brand)]/10 px-1 text-[9px] font-bold text-[var(--color-brand)]">PK</span>
              )}
              {fk && !pk && (
                <span className="ml-auto rounded bg-amber-500/10 px-1 text-[9px] font-bold text-amber-600">FK</span>
              )}
            </div>
          );
        })}
      </div>
      <Handle type="source" position={Position.Right} className="!bg-[var(--color-brand)]" />
    </div>
  );
}

const NODE_TYPES = { table: TableNode };

/** Layout berjenjang sederhana (tanpa dagre): nodes dirutekan per-kedalaman relasi. */
function layoutGraph(erd: ErdData): { nodes: Node[]; edges: Edge[] } {
  const erdNodes = erd.nodes ?? [];
  const erdEdges = erd.edges ?? [];
  const indegree = new Map<string, number>();
  for (const n of erdNodes) indegree.set(n.id, 0);
  for (const e of erdEdges) {
    if (!indegree.has(e.from)) indegree.set(e.from, 0);
    indegree.set(e.to, (indegree.get(e.to) ?? 0) + 1);
  }

  const levels = new Map<string, number>();
  const queue: string[] = [];
  for (const n of erdNodes) {
    if ((indegree.get(n.id) ?? 0) === 0) {
      levels.set(n.id, 0);
      queue.push(n.id);
    }
  }

  const outEdges = new Map<string, string[]>();
  for (const e of erdEdges) {
    if (!outEdges.has(e.from)) outEdges.set(e.from, []);
    outEdges.get(e.from)!.push(e.to);
  }

  const GAP_X = 320;
  const GAP_Y = 240;
  const perLevel = new Map<number, number>();

  while (queue.length > 0) {
    const cur = queue.shift()!;
    const lvl = levels.get(cur) ?? 0;
    for (const next of outEdges.get(cur) ?? []) {
      const nextLvl = levels.get(next) ?? 0;
      if (lvl + 1 > nextLvl) levels.set(next, lvl + 1);
      queue.push(next);
    }
  }

  const nodes: Node[] = erdNodes.map((n) => {
    const lvl = levels.get(n.id) ?? 0;
    const idx = perLevel.get(lvl) ?? 0;
    perLevel.set(lvl, idx + 1);
    return {
      id: n.id,
      type: "table",
      position: { x: lvl * GAP_X, y: idx * GAP_Y },
      data: { label: n.label, fields: n.fields },
    };
  });

  const edges: Edge[] = erdEdges.map((e, i) => ({
    id: `e${i}`,
    source: e.from,
    target: e.to,
    label: e.relation,
    animated: true,
    style: { stroke: "var(--color-brand)" },
    labelStyle: { fill: "var(--color-fg-muted)", fontSize: 11 },
  }));

  return { nodes, edges };
}

export function ErdDiagram({ erd }: { erd?: ErdData }) {
  const nodeTypes = useMemo(() => NODE_TYPES, []);
  const { nodes, edges } = useMemo(() => {
    const erdData = erd ?? sampleErd;
    return layoutGraph(erdData);
  }, [erd]);

  return (
    <div className="h-[460px] w-full overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-soft)]">
      <ReactFlow nodes={nodes} edges={edges} nodeTypes={nodeTypes} fitView proOptions={{ hideAttribution: true }}>
        <Background color="var(--color-border)" gap={20} />
        <Controls className="!border-[var(--color-border)] !bg-[var(--color-surface)]" />
      </ReactFlow>
    </div>
  );
}