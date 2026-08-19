import { useState } from "react";
import type { ReactElement } from "react";
import { Card, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Copy, Check, Palette, Sparkles } from "lucide-react";

interface CodeBlock {
  language: string;
  code: string;
}

const FENCE_PATTERN = /```(\w*)\n([\s\S]*?)```/g;

function extractCodeBlocks(markdown: string): CodeBlock[] {
  const blocks: CodeBlock[] = [];
  let m: RegExpExecArray | null;
  FENCE_PATTERN.lastIndex = 0;
  while ((m = FENCE_PATTERN.exec(markdown)) !== null) {
    blocks.push({ language: m[1] || "text", code: m[2] });
  }
  return blocks;
}

const SECTION_ICONS: Record<string, ReactElement> = {
  "## 0.": <Sparkles size={14} />,
  "## 1.": <Sparkles size={14} />,
  "## 2.": <Palette size={14} />,
  "## 3.": <Sparkles size={14} />,
  "## 4.": <Sparkles size={14} />,
  "## 5.": <Sparkles size={14} />,
  "## 6.": <Sparkles size={14} />,
  "## 7.": <Sparkles size={14} />,
  "## 8.": <Sparkles size={14} />,
  "## 9.": <Sparkles size={14} />,
};

function highlightSection(heading: string): ReactElement | null {
  for (const [prefix, icon] of Object.entries(SECTION_ICONS)) {
    if (heading.startsWith(prefix)) return icon;
  }
  return null;
}

export function DesignSystemView({ markdown }: { markdown: string }) {
  if (!markdown.trim()) {
    return <p className="text-sm text-[var(--color-fg-subtle)] italic">Design system belum di-generate.</p>;
  }

  const sections = markdown.split(/(?=^##\s\d+\.)/m).filter(Boolean);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs text-[var(--color-fg-muted)]">
        <Sparkles size={14} className="text-[var(--color-brand)]" />
        <span>Design tokens + signature element + anti-pattern checklist untuk web. Baca dokumen ini sebelum generate komponen UI manapun.</span>
      </div>

      {sections.map((section, i) => {
        const headingMatch = section.match(/^##\s(\d+\..+?)$/m);
        const heading = headingMatch ? headingMatch[1] : `Section ${i + 1}`;
        const icon = highlightSection(`## ${heading}`);
        const sectionBlocks = extractCodeBlocks(section);
        const sectionText = section.replace(FENCE_PATTERN, "").trim();

        return (
          <Card key={i} className="p-4">
            <div className="mb-2 flex items-center gap-2">
              {icon}
              <h3 className="text-sm font-semibold">{heading}</h3>
              {heading.startsWith("2.") && (
                <Badge tone="brand"><Palette size={10} className="mr-1 inline" />Tokens</Badge>
              )}
              {heading.startsWith("3.") && (
                <Badge tone="warning"><Sparkles size={10} className="mr-1 inline" />Signature</Badge>
              )}
            </div>
            {sectionText && (
              <pre className="whitespace-pre-wrap text-xs leading-relaxed text-[var(--color-fg-muted)]">{sectionText}</pre>
            )}
            {sectionBlocks.map((b, j) => (
              <CodeSnippet key={j} language={b.language} code={b.code} />
            ))}
          </Card>
        );
      })}
    </div>
  );
}

function CodeSnippet({ language, code }: { language: string; code: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <Card className="mt-2 overflow-hidden p-0">
      <div className="flex items-center justify-between border-b border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-1.5">
        <span className="text-[10px] font-semibold uppercase tracking-wide text-[var(--color-fg-subtle)]">{language}</span>
        <Button
          variant="ghost"
          size="sm"
          onClick={async () => {
            await navigator.clipboard.writeText(code);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
          }}
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? "Copied" : "Copy"}
        </Button>
      </div>
      <pre className="overflow-x-auto p-3 font-mono text-xs leading-relaxed text-[var(--color-fg)]">
        <code>{code}</code>
      </pre>
    </Card>
  );
}
