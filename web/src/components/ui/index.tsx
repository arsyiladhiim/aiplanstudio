import { cx } from "./cva";
import type { ComponentProps } from "react";

export function Card({ className, ...props }: ComponentProps<"div">) {
  return <div className={cx("card", className)} {...props} />;
}

export function Badge({
  className,
  tone = "brand",
  ...props
}: ComponentProps<"span"> & { tone?: "brand" | "success" | "warning" | "danger" | "muted" }) {
  const tones: Record<string, string> = {
    brand: "bg-[color-mix(in_oklab,var(--color-brand)_18%,transparent)] text-[var(--color-brand)]",
    success: "bg-[color-mix(in_oklab,var(--color-success)_16%,transparent)] text-[var(--color-success)]",
    warning: "bg-[color-mix(in_oklab,var(--color-warning)_16%,transparent)] text-[var(--color-warning)]",
    danger: "bg-[color-mix(in_oklab,var(--color-danger)_16%,transparent)] text-[var(--color-danger)]",
    muted: "bg-[var(--color-surface-2)] text-[var(--color-fg-muted)]",
  };
  return (
    <span
      className={cx(
        "inline-flex items-center gap-1.5 rounded-full px-2.5 py-1 text-xs font-medium",
        tones[tone],
        className
      )}
      {...props}
    />
  );
}

export function Input({ className, ...props }: ComponentProps<"input">) {
  return (
    <input
      className={cx(
        "h-11 w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-soft)] px-4 text-sm text-[var(--color-fg)] placeholder:text-[var(--color-fg-subtle)] outline-none transition focus:border-[var(--color-brand)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--color-brand)_35%,transparent)]",
        className
      )}
      {...props}
    />
  );
}

export function Textarea({ className, ...props }: ComponentProps<"textarea">) {
  return (
    <textarea
      className={cx(
        "w-full rounded-[var(--radius)] border border-[var(--color-border)] bg-[var(--color-bg-soft)] px-4 py-3 text-sm text-[var(--color-fg)] placeholder:text-[var(--color-fg-subtle)] outline-none transition focus:border-[var(--color-brand)] focus:ring-2 focus:ring-[color-mix(in_oklab,var(--color-brand)_35%,transparent)]",
        className
      )}
      {...props}
    />
  );
}

export function Label({ className, ...props }: ComponentProps<"label">) {
  return <label className={cx("mb-1.5 block text-sm font-medium text-[var(--color-fg-muted)]", className)} {...props} />;
}

export { Markdown } from "./Markdown";
export { Modal } from "./Modal";

export function Skeleton({ className = "" }: { className?: string }) {
  return <div className={cx("animate-pulse rounded bg-[var(--color-surface-2)]", className)} />;
}

export function EmptyState({
  icon,
  title,
  description,
  action,
}: {
  icon?: React.ReactNode;
  title: string;
  description?: string;
  action?: React.ReactNode;
}) {
  return (
    <div className="flex flex-col items-center justify-center rounded-xl border border-dashed border-[var(--color-border)] px-6 py-12 text-center">
      {icon && <div className="mb-3 text-[var(--color-fg-subtle)]">{icon}</div>}
      <p className="font-medium text-[var(--color-fg)]">{title}</p>
      {description && <p className="mt-1 max-w-sm text-sm text-[var(--color-fg-muted)]">{description}</p>}
      {action && <div className="mt-4">{action}</div>}
    </div>
  );
}
