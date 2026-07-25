import { cva } from "./cva";
import Link from "next/link";
import type { ComponentProps } from "react";

type Variant = "primary" | "secondary" | "ghost" | "outline" | "danger";
type Size = "sm" | "md" | "lg" | "icon";

const button = cva(
  "inline-flex items-center justify-center gap-2 font-medium rounded-[var(--radius)] transition-all active:scale-[.98] disabled:opacity-50 disabled:pointer-events-none focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-[var(--color-brand)] whitespace-nowrap",
  {
    variants: {
      variant: {
        primary:
          "text-white bg-[linear-gradient(100deg,var(--color-brand),var(--color-brand-2))] shadow-[0_8px_24px_-8px_var(--color-brand)] hover:brightness-110",
        secondary:
          "bg-[var(--color-surface-2)] text-[var(--color-fg)] border border-[var(--color-border)] hover:bg-[var(--color-surface)]",
        ghost: "text-[var(--color-fg-muted)] hover:text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]",
        outline: "border border-[var(--color-border)] text-[var(--color-fg)] hover:bg-[var(--color-surface-2)]",
        danger: "bg-[var(--color-danger)] text-white hover:brightness-110",
      },
      size: {
        sm: "h-9 px-3.5 text-sm",
        md: "h-11 px-5 text-sm",
        lg: "h-12 px-6 text-base",
        icon: "h-10 w-10",
      },
    },
    defaultVariants: { variant: "primary", size: "md" },
  }
);

type BaseProps = { variant?: Variant; size?: Size; className?: string };

export function Button({ variant, size, className, ...props }: Omit<ComponentProps<"button">, "className"> & BaseProps) {
  return <button className={button({ variant, size, className })} {...props} />;
}

export function ButtonLink({ variant, size, className, ...props }: Omit<ComponentProps<typeof Link>, "className"> & BaseProps) {
  return <Link className={button({ variant, size, className })} {...props} />;
}
