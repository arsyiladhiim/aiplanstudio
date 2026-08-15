import { Card, Badge } from "@/components/ui";
import { PhaseBreakdownCard, type PhaseItem } from "./PhaseBreakdownCard";
import { Markdown } from "@/components/ui";

const EFFORT_PATTERN = /\*\*Effort:\*\*\s*([SML])\b/i;

function extractEffort(markdown: string): "S" | "M" | "L" | null {
  const m = markdown.match(EFFORT_PATTERN);
  if (!m) return null;
  const c = m[1].toUpperCase();
  return c === "S" || c === "M" || c === "L" ? c : null;
}

export function PhasesView({ markdown, label = "Phase Breakdown" }: { markdown: string; label?: string }) {
  const parsed = safeParsePhases(markdown);

  if (parsed.kind === "json") {
    return <PhaseBreakdownCard phases={parsed.phases} label={label} />;
  }

  const phases = parsed.kind === "markdown" ? parsed.phases : [];
  if (phases.length === 0) {
    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{markdown}</Markdown>;
  }

  return (
    <div className="space-y-3">
      {phases.map((p, i) => {
        const effort = extractEffort(p.body);
        return (
          <Card key={i} className="p-4">
            <div className="mb-2 flex items-center gap-2">
              <span className="rounded-md bg-[var(--color-surface-2)] px-2 py-0.5 font-mono text-[10px] text-[var(--color-fg-muted)]">{p.key}</span>
              <h3 className="text-sm font-semibold">{p.title}</h3>
              {effort && <Badge tone={effort === "S" ? "success" : effort === "M" ? "brand" : "warning"}>{effort === "S" ? "Small" : effort === "M" ? "Medium" : "Large"}</Badge>}
            </div>
            <pre className="whitespace-pre-wrap text-xs leading-relaxed text-[var(--color-fg-muted)]">{p.body}</pre>
          </Card>
        );
      })}
    </div>
  );
}

type ParsedPhases =
  | { kind: "json"; phases: PhaseItem[] }
  | { kind: "markdown"; phases: MarkdownPhase[] }
  | { kind: "raw"; phases: [] };

function safeParsePhases(markdown: string): ParsedPhases {
  try {
    const parsed = JSON.parse(markdown);
    if (Array.isArray(parsed) && parsed.length > 0) {
      return { kind: "json", phases: parsed as PhaseItem[] };
    }
  } catch {
    // not JSON — fall through to markdown parser
  }
  const md = parsePhasesFromMarkdown(markdown);
  if (md.length > 0) return { kind: "markdown", phases: md };
  return { kind: "raw", phases: [] };
}

interface MarkdownPhase {
  key: string;
  title: string;
  body: string;
}

function parsePhasesFromMarkdown(markdown: string): MarkdownPhase[] {
  const phases: MarkdownPhase[] = [];
  const lines = markdown.split("\n");
  let current: MarkdownPhase | null = null;

  for (const line of lines) {
    const m = line.match(/^FASE:\s*([\w-]+)\s*\|\s*(.+)$/);
    if (m) {
      if (current) phases.push(current);
      current = { key: m[1].trim(), title: m[2].trim(), body: "" };
      continue;
    }
    if (current) {
      if (/^---$/.test(line.trim())) {
        phases.push(current);
        current = null;
        continue;
      }
      current.body += (current.body ? "\n" : "") + line;
    }
  }
  if (current) phases.push(current);
  return phases;
}
