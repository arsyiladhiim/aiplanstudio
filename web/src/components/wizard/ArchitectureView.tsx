import { SectionRenderer, parseSections } from "./SectionRenderer";
import { Card, Badge } from "@/components/ui";

function extractAscii(content: string): string | null {
  const fence = content.match(/```\n([\s\S]*?)```/);
  if (!fence) return null;
  const inner = fence[1];
  if (!/[\s│├└┌┐┘─┬┴┼]/.test(inner)) return null;
  return inner;
}

export function ArchitectureView({ markdown }: { markdown: string }) {
  const { beforeFirstSection, sections } = parseSections(markdown);
  const knownTitles = ["Stack", "Module Boundaries", "Data Flow", "Folder Structure", "Deployment", "Trade-offs"];
  const matched = sections.filter((s) => knownTitles.some((k) => s.title.toLowerCase().includes(k.toLowerCase())));

  if (matched.length === 0) {
    return <SectionRenderer markdown={markdown} maxLevel={2} />;
  }

  return (
    <div className="space-y-3">
      {beforeFirstSection && (
        <Card className="p-4">
          <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{beforeFirstSection}</pre>
        </Card>
      )}
      {matched.map((sec, i) => {
        const ascii = extractAscii(sec.content);
        return (
          <Card key={i} className="p-4">
            <div className="mb-2 flex items-center gap-2">
              <h3 className="text-sm font-semibold text-[var(--color-fg)]">{sec.title}</h3>
              {sec.title.toLowerCase().includes("trade") && <Badge tone="warning">Trade-offs</Badge>}
            </div>
            {ascii ? (
              <>
                <pre className="overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 font-mono text-xs leading-relaxed text-[var(--color-fg)]">
                  {ascii}
                </pre>
                <div className="mt-2 text-sm leading-relaxed text-[var(--color-fg-muted)]">
                  <RestContent content={sec.content} />
                </div>
              </>
            ) : (
              <div className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
                {sec.content.split(/\n{2,}/).map((p, j) => (
                  <p key={j} className="whitespace-pre-wrap">{p}</p>
                ))}
              </div>
            )}
          </Card>
        );
      })}
    </div>
  );
}

function RestContent({ content }: { content: string }) {
  const stripped = content.replace(/```\n[\s\S]*?```/, "").trim();
  if (!stripped) return null;
  return (
    <>
      {stripped.split(/\n{2,}/).map((p, j) => (
        <p key={j} className="whitespace-pre-wrap">{p}</p>
      ))}
    </>
  );
}
