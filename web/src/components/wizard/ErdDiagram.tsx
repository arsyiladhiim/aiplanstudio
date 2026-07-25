"use client";
import { useMemo } from "react";
import ReactFlow, { Background, Controls, Handle, Position, type Node, type Edge } from "reactflow";
import "reactflow/dist/style.css";
import { sampleErd } from "@/lib/mock";

function TableNode({ data }: { data: { label: string; fields: string[] } }) {
  return (
    <div className="min-w-[160px] overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] shadow-lg">
      <Handle type="target" position={Position.Left} className="!bg-[var(--color-brand)]" />
      <div className="bg-[linear-gradient(135deg,var(--color-brand),var(--color-brand-2))] px-3 py-2 text-sm font-semibold text-white">
        {data.label}
      </div>
      <div className="divide-y divide-[var(--color-border)]">
        {data.fields.map((f) => (
          <div key={f} className="px-3 py-1.5 text-xs text-[var(--color-fg-muted)]">{f}</div>
        ))}
      </div>
      <Handle type="source" position={Position.Right} className="!bg-[var(--color-brand)]" />
    </div>
  );
}

const nodeTypes = { table: TableNode };

type ErdData = {
  nodes: Array<{ id: string; label: string; fields: string[] }>;
  edges: Array<{ from: string; to: string; relation: string }>;
};

export function ErdDiagram({ erd }: { erd?: object }) {
  const { nodes, edges } = useMemo(() => {
    const erdData = (erd as ErdData) || sampleErd;
    const cols = 2;
    const nodes: Node[] = erdData.nodes.map((n, i) => ({
      id: n.id,
      type: "table",
      position: { x: (i % cols) * 280, y: Math.floor(i / cols) * 220 },
      data: { label: n.label, fields: n.fields },
    }));
    const edges: Edge[] = erdData.edges.map((e, i) => ({
      id: `e${i}`,
      source: e.from,
      target: e.to,
      label: e.relation,
      animated: true,
      style: { stroke: "var(--color-brand)" },
      labelStyle: { fill: "var(--color-fg-muted)", fontSize: 11 },
    }));
    return { nodes, edges };
  }, [erd]);

  return (
    <div className="h-[420px] w-full overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-soft)]">
      <ReactFlow nodes={nodes} edges={edges} nodeTypes={nodeTypes} fitView proOptions={{ hideAttribution: true }}>
        <Background color="var(--color-border)" gap={20} />
        <Controls className="!border-[var(--color-border)] !bg-[var(--color-surface)]" />
      </ReactFlow>
    </div>
  );
}
