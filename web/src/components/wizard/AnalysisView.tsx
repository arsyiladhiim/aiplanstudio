import { SectionRenderer, parseSections } from "./SectionRenderer";
import { Card } from "@/components/ui";

export function AnalysisView({ markdown }: { markdown: string }) {
  const { sections } = parseSections(markdown);
  const knownTitles = ["Intent Summary", "User Personas", "Core Problem", "Jobs to be Done", "Success Metrics", "Anti-Goals", "Daftar Halaman"];
  const matched = sections.filter((s) => knownTitles.some((k) => s.title.toLowerCase().includes(k.toLowerCase())));

  if (matched.length === 0) {
    return <SectionRenderer markdown={markdown} maxLevel={2} />;
  }

  return (
    <div className="space-y-3">
      {matched.map((sec, i) => {
        const isPersona = sec.title.toLowerCase().includes("persona");
        const isJTBD = sec.title.toLowerCase().includes("jobs") || sec.title.toLowerCase().includes("jtbd");
        return (
          <Card key={i} className="p-4">
            <h3 className="mb-2 text-sm font-semibold text-[var(--color-fg)]">{sec.title}</h3>
            {isPersona ? <PersonaList content={sec.content} /> : isJTBD ? <JtbdList content={sec.content} /> : (
              <div className="space-y-1.5 text-sm leading-relaxed text-[var(--color-fg-muted)]">
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

function initials(name: string): string {
  return name.split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase() || "P";
}

function PersonaList({ content }: { content: string }) {
  const personas = content.split(/(?=^###\s)/m).filter((s) => s.trim());
  if (personas.length === 0) {
    return <div className="text-sm text-[var(--color-fg-muted)] whitespace-pre-wrap">{content}</div>;
  }
  return (
    <div className="grid gap-3 sm:grid-cols-2">
      {personas.map((p, i) => {
        const lines = p.split("\n").map((l) => l.replace(/^###\s*/, "").trim()).filter(Boolean);
        const name = lines[0] ?? `Persona ${i + 1}`;
        const rest = lines.slice(1);
        return (
<div key={i} className="rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-3">
              <div className="mb-1 flex items-center gap-2">
                <span className="grid h-5 w-5 place-items-center rounded-full bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[10px] font-bold text-[var(--color-brand)]">
                  {initials(name)}
                </span>
                <span className="text-xs font-semibold text-[var(--color-fg)]">{name}</span>
              </div>
              <ul className="space-y-1 pl-px text-xs text-[var(--color-fg-muted)]">
                {rest.map((line, j) => (
                  <li key={j} className="leading-snug">{line}</li>
                ))}
              </ul>
            </div>
        );
      })}
    </div>
  );
}

function JtbdList({ content }: { content: string }) {
  const items = content.split("\n").filter((l) => /^[-*]\s+/.test(l));
  if (items.length === 0) {
    return <div className="text-sm text-[var(--color-fg-muted)] whitespace-pre-wrap">{content}</div>;
  }
  return (
    <ul className="space-y-2">
      {items.map((item, i) => {
        const cleaned = item.replace(/^[-*]\s+/, "").trim();
        return (
          <li key={i} className="flex items-start gap-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] p-2.5 text-xs leading-relaxed text-[var(--color-fg-muted)]">
            <span className="mt-0.5 shrink-0 text-[var(--color-brand)]">→</span>
            <span className="whitespace-pre-wrap">{cleaned}</span>
          </li>
        );
      })}
    </ul>
  );
}
