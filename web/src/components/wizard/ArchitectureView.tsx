import { SectionRenderer, parseSections } from "./SectionRenderer";
import { Card } from "@/components/ui";

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
    <div className="space-y-4">
      {beforeFirstSection && (
        <Card className="p-4">
          <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{beforeFirstSection}</pre>
        </Card>
      )}
      <div className="space-y-5">
        {matched.map((sec, i) => {
          const ascii = extractAscii(sec.content);
          if (ascii) {
            return (
              <div key={i}>
                <h3 className="mb-2 text-sm font-semibold text-[var(--color-fg)]">{sec.title}</h3>
                <pre className="overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3 font-mono text-xs leading-relaxed text-[var(--color-fg)]">
                  {ascii}
                </pre>
              </div>
            );
          }
          return (
            <div key={i}>
              <h3 className="mb-1.5 text-sm font-semibold text-[var(--color-fg)]">{sec.title}</h3>
              <div className="space-y-2 text-sm leading-relaxed text-[var(--color-fg-muted)]">
                {sec.content.split(/\n{2,}/).map((p, j) => (
                  <p key={j} className="whitespace-pre-wrap">{p}</p>
                ))}
              </div>
            </div>
          );
        })}
      </div>
    </div>
  );
}

