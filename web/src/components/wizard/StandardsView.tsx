import { useState } from "react";
import { Card } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Copy, Check } from "lucide-react";

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

function stripCodeBlocks(markdown: string): string {
  return markdown.replace(FENCE_PATTERN, "").trim();
}

export function StandardsView({ markdown }: { markdown: string }) {
  const blocks = extractCodeBlocks(markdown);

  if (blocks.length === 0) {
    return <pre className="whitespace-pre-wrap text-sm text-[var(--color-fg-muted)]">{markdown}</pre>;
  }

  return (
    <div className="space-y-3">
      {stripCodeBlocks(markdown).split(/\n{2,}/).filter(Boolean).map((para, i) => (
        <Card key={`p-${i}`} className="p-3">
          <p className="text-sm leading-relaxed text-[var(--color-fg-muted)] whitespace-pre-wrap">{para}</p>
        </Card>
      ))}
      {blocks.map((b, i) => (
        <CodeSnippet key={`c-${i}`} language={b.language} code={b.code} />
      ))}
    </div>
  );
}

function CodeSnippet({ language, code }: { language: string; code: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <Card className="overflow-hidden p-0">
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
