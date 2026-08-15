"use client";
import { useState } from "react";
import { Card } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Copy, Check, Eye, EyeOff } from "lucide-react";

type Variant = "default" | "secret";

export function CopyField({
  label,
  value,
  variant = "default",
  placeholder = "(kosong)",
}: {
  label: string;
  value: string | null | undefined;
  variant?: Variant;
  placeholder?: string;
}) {
  const [copied, setCopied] = useState(false);
  const [revealed, setRevealed] = useState(variant !== "secret");

  const displayValue = value ?? "";
  const masked = displayValue ? "•".repeat(Math.min(displayValue.length, 24)) : "";
  const shown = revealed || !value ? displayValue : masked;
  const isEmpty = !displayValue;

  async function handleCopy() {
    if (!displayValue) return;
    try {
      await navigator.clipboard.writeText(displayValue);
      setCopied(true);
      setTimeout(() => setCopied(false), 1500);
    } catch {
      // clipboard blocked — silent fail (UI shows value anyway)
    }
  }

  return (
    <div>
      <div className="mb-1 flex items-center justify-between">
        <div className="text-xs font-semibold text-[var(--color-fg-muted)]">{label}</div>
        {variant === "secret" && value && (
          <button
            type="button"
            onClick={() => setRevealed((v) => !v)}
            className="flex items-center gap-1 text-[10px] text-[var(--color-fg-subtle)] hover:text-[var(--color-fg)]"
          >
            {revealed ? <EyeOff size={11} /> : <Eye size={11} />}
            {revealed ? "Sembunyi" : "Lihat"}
          </button>
        )}
      </div>
      <Card className="flex items-center gap-2 p-2">
        <code className="flex-1 overflow-x-auto rounded-md bg-[var(--color-bg-soft)] px-3 py-2 font-mono text-xs text-[var(--color-fg)]">
          {isEmpty ? <span className="text-[var(--color-fg-subtle)]">{placeholder}</span> : shown}
        </code>
        <Button
          variant="outline"
          size="sm"
          onClick={handleCopy}
          disabled={isEmpty}
          data-testid={`copy-field-${label.toLowerCase().replace(/\s+/g, "-")}`}
        >
          {copied ? <Check size={12} /> : <Copy size={12} />}
          {copied ? "Copied" : "Copy"}
        </Button>
      </Card>
    </div>
  );
}
