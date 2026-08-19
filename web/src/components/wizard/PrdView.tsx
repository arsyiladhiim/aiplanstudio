import { useMemo } from "react";
import { parseSections } from "./SectionRenderer";
import { Card, Badge } from "@/components/ui";

interface UserStory {
  id: string;
  area: string;
  story: string;
  ac: string[];
}

function parseStoryLine(line: string): { id: string; story: string } | null {
  const m = line.match(/^\*\*(US-\d+):\*\*\s*(.+)$/);
  if (!m) return null;
  return { id: m[1], story: m[2].trim() };
}

function parseAcLines(content: string): string[] {
  return content
    .split("\n")
    .map((l) => l.trim())
    .filter((l) => /^(Given|When|Then|And)\s+/i.test(l))
    .map((l) => l.replace(/^(Given|When|Then|And)\s+/i, ""));
}

export function PrdView({ markdown }: { markdown: string }) {
  const stories = useMemo<UserStory[]>(() => {
    const { sections } = parseSections(markdown);
    const out: UserStory[] = [];
    let currentArea = "General";
    let pendingStory: { id: string; story: string } | null = null;
    let pendingAc: string[] = [];

    for (const sec of sections) {
      if (sec.level === 2 && !/^(US-|user stor)/i.test(sec.title)) {
        currentArea = sec.title.replace(/^###?\s*/, "");
        pendingStory = null;
        pendingAc = [];
        continue;
      }
      if (sec.level === 3 && /^(US-|user stor)/i.test(sec.title)) {
        if (pendingStory) out.push({ id: pendingStory.id, area: currentArea, story: pendingStory.story, ac: pendingAc });
        const parsed = parseStoryLine(`**${sec.title.replace(/^###?\s*/, "")}:**`);
        if (parsed) {
          pendingStory = parsed;
          pendingAc = parseAcLines(sec.content);
        }
      } else if (pendingStory) {
        pendingAc.push(...parseAcLines(sec.content));
      }
    }
    if (pendingStory) out.push({ id: pendingStory.id, area: currentArea, story: pendingStory.story, ac: pendingAc });
    return out;
  }, [markdown]);

  if (stories.length === 0) {
    const { sections } = parseSections(markdown);
    return (
      <div className="space-y-3">
        {sections.map((s, i) => (
          <Card key={i} className="p-4">
            <h3 className="mb-2 text-sm font-semibold">{s.title}</h3>
            <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{s.content}</pre>
          </Card>
        ))}
      </div>
    );
  }

  const grouped = stories.reduce<Record<string, UserStory[]>>((acc, s) => {
    (acc[s.area] ??= []).push(s);
    return acc;
  }, {});

  return (
    <div className="space-y-4">
      {Object.entries(grouped).map(([area, items]) => (
        <div key={area}>
          <h3 className="mb-2 text-xs font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">{area}</h3>
          <div className="space-y-2">
            {items.map((s) => (
              <Card key={s.id} className="p-3">
                <div className="mb-2 flex items-start gap-2">
                  <Badge tone="brand">{s.id}</Badge>
                  <p className="text-sm font-medium leading-snug text-[var(--color-fg)]">{s.story}</p>
                </div>
                {s.ac.length > 0 && (
                  <ul className="mt-2 space-y-1 border-l border-[var(--color-border)] pl-3">
                    {s.ac.map((line, j) => (
                      <li key={j} className="text-xs leading-snug text-[var(--color-fg-muted)]">{line}</li>
                    ))}
                  </ul>
                )}
              </Card>
            ))}
          </div>
        </div>
      ))}
    </div>
  );
}
