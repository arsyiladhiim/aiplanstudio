import { useMemo, useState } from "react";
import { ChevronDown, ChevronRight } from "lucide-react";

export interface ParsedSection {
  title: string;
  level: number;
  content: string;
}

const SECTION_PATTERN = /^(#{1,3})\s+(.+?)\s*$/gm;

export function parseSections(markdown: string): { beforeFirstSection: string; sections: ParsedSection[] } {
  if (!markdown) return { beforeFirstSection: "", sections: [] };
  const matches: Array<{ index: number; level: number; title: string; length: number }> = [];
  let m: RegExpExecArray | null;
  SECTION_PATTERN.lastIndex = 0;
  while ((m = SECTION_PATTERN.exec(markdown)) !== null) {
    matches.push({ index: m.index, level: m[1].length, title: m[2].trim(), length: m[0].length });
  }
  if (matches.length === 0) return { beforeFirstSection: markdown, sections: [] };

  const beforeFirstSection = markdown.slice(0, matches[0].index).trim();
  const sections: ParsedSection[] = matches.map((sec, i) => {
    const start = sec.index + sec.length;
    const end = i + 1 < matches.length ? matches[i + 1].index : markdown.length;
    return { title: sec.title, level: sec.level, content: markdown.slice(start, end).trim() };
  });

  return { beforeFirstSection, sections };
}

export function SectionRenderer({
  markdown,
  defaultOpen = true,
  maxLevel = 3,
  renderSection,
}: {
  markdown: string;
  defaultOpen?: boolean;
  maxLevel?: number;
  renderSection?: (section: ParsedSection, body: React.ReactNode) => React.ReactNode;
}) {
  const { beforeFirstSection, sections } = useMemo(() => parseSections(markdown), [markdown]);
  const [openMap, setOpenMap] = useState<Record<number, boolean>>(() => {
    if (!defaultOpen) return {};
    const init: Record<number, boolean> = {};
    sections.forEach((_, i) => (init[i] = true));
    return init;
  });

  const filtered = sections.filter((s) => s.level <= maxLevel);

  if (filtered.length === 0) {
    return <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{beforeFirstSection || markdown}</pre>;
  }

  return (
    <div className="space-y-3">
      {beforeFirstSection && (
        <div className="text-sm leading-relaxed text-[var(--color-fg-muted)]">
          <MarkdownLike content={beforeFirstSection} />
        </div>
      )}
      {filtered.map((section, i) => {
        const isOpen = openMap[i] ?? false;
        const toggle = () => setOpenMap((prev) => ({ ...prev, [i]: !prev[i] }));
        const indent = section.level === 1 ? "pl-0" : section.level === 2 ? "pl-2" : "pl-4";
        const titleSize = section.level === 1 ? "text-base" : section.level === 2 ? "text-sm" : "text-xs";

        const header = (
          <button
            type="button"
            onClick={toggle}
            className={`flex w-full items-center gap-2 rounded-md px-2 py-1.5 text-left font-semibold ${titleSize} text-[var(--color-fg)] hover:bg-[var(--color-surface-2)] ${indent}`}
          >
            {isOpen ? <ChevronDown size={14} className="shrink-0" /> : <ChevronRight size={14} className="shrink-0" />}
            <span>{section.title}</span>
          </button>
        );

        const body = isOpen ? (
          <div className={`mt-1 text-sm leading-relaxed text-[var(--color-fg-muted)] ${indent}`}>
            <MarkdownLike content={section.content} />
          </div>
        ) : null;

        return (
          <div key={i} className="rounded-md border border-[var(--color-border)] bg-[var(--color-surface-1)]">
            {renderSection ? renderSection(section, body) : (
              <>
                {header}
                {body}
              </>
            )}
          </div>
        );
      })}
    </div>
  );
}

function MarkdownLike({ content }: { content: string }) {
  return (
    <div className="space-y-1.5">
      {content.split(/\n{2,}/).map((para, i) => (
        <p key={i} className="whitespace-pre-wrap">
          {para}
        </p>
      ))}
    </div>
  );
}
