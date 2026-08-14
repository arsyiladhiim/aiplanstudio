"use client";
import { useEffect, useRef, useState } from "react";
import { Markdown } from "@/components/ui/Markdown";
import { Copy, Check } from "lucide-react";

export interface StreamingMarkdownProps {
  content: string;
  live?: boolean;
  className?: string;
}

type Tab = "formatted" | "raw";

export function StreamingMarkdown({ content, live, className }: StreamingMarkdownProps) {
  const [tab, setTab] = useState<Tab>("formatted");
  const [copied, setCopied] = useState(false);
  const scrollRef = useRef<HTMLDivElement>(null);
  const stickyBottom = useRef(true);

  useEffect(() => {
    if (!live) return;
    const el = scrollRef.current;
    if (!el || !stickyBottom.current) return;
    el.scrollTop = el.scrollHeight;
  }, [content, live]);

  const onScroll = () => {
    const el = scrollRef.current;
    if (!el) return;
    const atBottom = el.scrollHeight - el.scrollTop - el.clientHeight < 40;
    stickyBottom.current = atBottom;
  };

  const copy = async () => {
    try {
      await navigator.clipboard.writeText(content);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // ignore
    }
  };

  return (
    <div className={`flex flex-col overflow-hidden rounded-xl border border-[var(--color-border)] bg-[var(--color-surface-1)] ${className ?? ""}`}>
      <div className="flex shrink-0 items-center justify-between border-b border-[var(--color-border)] bg-[var(--color-surface)] px-3 py-1.5">
        <div className="flex gap-1 text-[11px]">
          {(["formatted", "raw"] as const).map((t) => (
            <button
              key={t}
              type="button"
              onClick={() => setTab(t)}
              className={`rounded-full px-2.5 py-0.5 transition ${
                tab === t
                  ? "bg-[var(--color-brand)] text-white"
                  : "text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
              }`}
            >
              {t === "formatted" ? "Formatted" : "Raw"}
            </button>
          ))}
        </div>
        <button
          type="button"
          onClick={copy}
          title="Salin"
          className="grid h-6 w-6 place-items-center rounded text-[var(--color-fg-muted)] hover:bg-[var(--color-surface-2)]"
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
        </button>
      </div>
      <div ref={scrollRef} onScroll={onScroll} className="relative max-h-[60vh] flex-1 overflow-auto px-4 py-3 text-sm">
        {tab === "formatted" ? (
          <div className="prose-sm">
            <Markdown>{content}</Markdown>
          </div>
        ) : (
          <pre className="whitespace-pre-wrap font-mono text-[12px] leading-relaxed text-[var(--color-fg)]">
            {content}
            {live && <span className="ml-0.5 inline-block h-4 w-1.5 translate-y-0.5 animate-pulse bg-[var(--color-brand)] align-baseline" />}
          </pre>
        )}
      </div>
    </div>
  );
}
