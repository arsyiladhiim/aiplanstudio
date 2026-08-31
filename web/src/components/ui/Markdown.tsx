import ReactMarkdown from "react-markdown"

export function Markdown({
  children,
  className,
}: {
  children?: string
  className?: string
}) {
  return (
    <div className={className}>
      <ReactMarkdown
        components={{
          h1: (p) => (
            <h1
              className="mt-1 mb-3 text-xl font-bold text-[var(--color-fg)]"
              {...p}
            />
          ),
          h2: (p) => (
            <h2
              className="mt-5 mb-2 text-lg font-bold text-[var(--color-fg)]"
              {...p}
            />
          ),
          h3: (p) => (
            <h3
              className="mt-4 mb-2 text-base font-semibold text-[var(--color-fg)]"
              {...p}
            />
          ),
          h4: (p) => (
            <h4
              className="mt-3 mb-2 text-sm font-semibold text-[var(--color-fg)]"
              {...p}
            />
          ),
          h5: (p) => (
            <h5
              className="mt-3 mb-2 text-sm font-semibold text-[var(--color-fg)]"
              {...p}
            />
          ),
          h6: (p) => (
            <h6
              className="mt-3 mb-2 text-sm font-semibold text-[var(--color-fg)]"
              {...p}
            />
          ),
          p: (p) => (
            <p
              className="my-2 leading-relaxed break-words text-[var(--color-fg)]"
              {...p}
            />
          ),
          strong: (p) => (
            <strong className="font-semibold text-[var(--color-fg)]" {...p} />
          ),
          em: (p) => <em className="text-[var(--color-fg-muted)]" {...p} />,
          ul: (p) => <ul className="my-2 list-disc space-y-1 pl-5" {...p} />,
          ol: (p) => <ol className="my-2 list-decimal space-y-1 pl-5" {...p} />,
          li: (p) => <li className="leading-relaxed break-words" {...p} />,
          a: ({ href, ...p }) => (
            <a
              href={href}
              target="_blank"
              rel="noopener noreferrer"
              className="text-[var(--color-brand)] underline underline-offset-2"
              {...p}
            />
          ),
          blockquote: (p) => (
            <blockquote
              className="my-3 border-l-2 border-[var(--color-border)] pl-4 text-[var(--color-fg-muted)]"
              {...p}
            />
          ),
          hr: () => <hr className="my-4 border-[var(--color-border)]" />,
          pre: ({ children }) => (
            <pre className="my-3 overflow-x-auto rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] p-3 text-sm leading-relaxed [&_code]:bg-transparent [&_code]:p-0 [&_code]:text-[var(--color-fg)]">
              {children}
            </pre>
          ),
          code: (p) => (
            <code
              className="rounded bg-[var(--color-surface-2)] px-1.5 py-0.5 font-mono text-xs text-[var(--color-fg-muted)]"
              {...p}
            />
          ),
          table: (p) => (
            <div className="my-3 overflow-x-auto">
              <table className="w-full border-collapse text-sm" {...p} />
            </div>
          ),
          th: (p) => (
            <th
              className="border border-[var(--color-border)] px-3 py-1.5 text-left font-semibold"
              {...p}
            />
          ),
          td: (p) => (
            <td
              className="border border-[var(--color-border)] px-3 py-1.5"
              {...p}
            />
          ),
          img: ({ alt, src }) => (
            // eslint-disable-next-line @next/next/no-img-element -- remote images from AI output; sizes unknown at build time
            <img
              src={src}
              alt={alt}
              className="my-3 max-w-full rounded-lg border border-[var(--color-border)]"
            />
          ),
        }}
      >
        {children ?? ""}
      </ReactMarkdown>
    </div>
  )
}
