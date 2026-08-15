import { Card, Badge } from "@/components/ui";
import { Markdown } from "@/components/ui";
import { parseSections } from "./SectionRenderer";
import { ArrowRight } from "lucide-react";

const ROLE_PATTERN = /^###\s+([^\n]+?)\s*\(([^)]+)\)/;

interface Agent {
  emoji: string;
  name: string;
  scope: string;
  owns: string[];
  tools: string[];
  constraint: string;
  handoff: string;
}

function parseAgent(section: { title: string; content: string }): Agent | null {
  const headerMatch = section.title.match(ROLE_PATTERN);
  if (!headerMatch) return null;
  const emoji = (headerMatch[1].match(/^\p{Emoji}/u) ?? [""])[0];
  const name = headerMatch[1].replace(emoji, "").trim();
  const scope = headerMatch[2].trim();

  const get = (label: string): string | null => {
    const re = new RegExp(`\\*\\*${label}:\\*\\*\\s*([\\s\\S]*?)(?=\\n\\*\\*[^*]+:\\*\\*|$)`, "m");
    const m = section.content.match(re);
    return m ? m[1].trim() : null;
  };

  const ownsRaw = get("Owns") ?? "";
  const toolsRaw = get("Tools") ?? "";
  return {
    emoji,
    name,
    scope,
    owns: ownsRaw.split(/[,\n]/).map((s) => s.trim()).filter(Boolean),
    tools: toolsRaw.split(/[,\n]/).map((s) => s.trim()).filter(Boolean),
    constraint: get("Constraint") ?? "",
    handoff: get("Handoff") ?? "",
  };
}

export function AgentsView({ markdown }: { markdown: string }) {
  const { sections, beforeFirstSection } = parseSections(markdown);
  const agents: Agent[] = [];
  for (const sec of sections) {
    const agent = parseAgent(sec);
    if (agent) agents.push(agent);
  }

  if (agents.length === 0) {
    return <Markdown className="text-sm leading-relaxed text-[var(--color-fg-muted)]">{markdown}</Markdown>;
  }

  return (
    <div className="space-y-4">
      {beforeFirstSection && (
        <Card className="p-3">
          <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{beforeFirstSection}</pre>
        </Card>
      )}
      <div className="grid gap-3 sm:grid-cols-2">
        {agents.map((a, i) => (
          <Card key={i} className="p-4">
            <div className="mb-2 flex items-center gap-2">
              <span className="text-xl">{a.emoji}</span>
              <h3 className="text-sm font-semibold">{a.name}</h3>
              <Badge tone="brand">{a.scope}</Badge>
            </div>
            {a.owns.length > 0 && (
              <div className="mb-2">
                <div className="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">Owns</div>
                <ul className="mt-1 space-y-0.5">
                  {a.owns.map((o, j) => (
                    <li key={j} className="font-mono text-[11px] text-[var(--color-fg-muted)]">{o}</li>
                  ))}
                </ul>
              </div>
            )}
            {a.tools.length > 0 && (
              <div className="mb-2 flex flex-wrap gap-1">
                {a.tools.map((t, j) => (
                  <Badge key={j} tone="muted">{t}</Badge>
                ))}
              </div>
            )}
            {a.constraint && (
              <p className="mt-1 text-xs leading-relaxed text-[var(--color-fg-muted)]">{a.constraint}</p>
            )}
            {a.handoff && (
              <div className="mt-2 flex items-start gap-1.5 border-t border-[var(--color-border)] pt-2 text-xs text-[var(--color-brand)]">
                <ArrowRight size={12} className="mt-0.5 shrink-0" />
                <span className="leading-snug">{a.handoff}</span>
              </div>
            )}
          </Card>
        ))}
      </div>
    </div>
  );
}
