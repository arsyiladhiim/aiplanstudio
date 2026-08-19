import { useState } from "react";
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

export function DesignSystemMobileView({ markdown }: { markdown: string }) {
  if (!markdown.trim()) {
    return <p className="text-sm text-[var(--color-fg-subtle)] italic">Design system mobile belum di-generate.</p>;
  }

  const sections = markdown.split(/(?=^##\s\d+\.)/m).filter(Boolean);

  return (
    <div className="space-y-4">
      <div className="flex items-center gap-2 rounded-md border border-[var(--color-border)] bg-[var(--color-surface-2)] px-3 py-2 text-xs text-[var(--color-fg-muted)]">
        <Sparkles size={14} className="text-[var(--color-brand-2)]" />
        <span>Design tokens Material 3 (ThemeData) + signature element Flutter. Baca sebelum generate widget manapun.</span>
      </div>

      {sections.map((section, i) => {
        const headingMatch = section.match(/^##\s(\d+\..+?)$/m);
        const heading = headingMatch ? headingMatch[1] : `Section ${i + 1}`;
        const sectionBlocks = extractCodeBlocks(section);
        const sectionText = section.replace(FENCE_PATTERN, "").trim();

        return (
          <Card key={i} className="p-4">
            <div className="mb-2 flex items-center gap-2">
              <span className="grid h-5 w-5 shrink-0 place-items-center rounded-md bg-[color-mix(in_oklab,var(--color-brand)_14%,transparent)] text-[10px] font-bold text-[var(--color-brand)]">
                {i}
              </span>
              <h3 className="text-sm font-semibold">{heading}</h3>
              {heading.startsWith("2.") && (
                <Badge tone="brand"><Palette size={10} className="mr-1 inline" />ThemeData</Badge>
              )}
              {heading.startsWith("3.") && (
                <Badge tone="warning"><Sparkles size={10} className="mr-1 inline" />Signature</Badge>
              )}
            </div>
            {sectionText && (
              <div className="space-y-2 text-xs leading-relaxed text-[var(--color-fg-muted)]">
                {sectionText.split(/\n{2,}/).map((p, j) => (
                  <p key={j} className="whitespace-pre-wrap">{p}</p>
                ))}
              </div>
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
