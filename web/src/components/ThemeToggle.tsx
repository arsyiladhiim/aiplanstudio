"use client";
import { useState } from "react";
import { Moon, Sun } from "lucide-react";

export function ThemeToggle({ className = "" }: { className?: string }) {
  const [dark, setDark] = useState(() => typeof document !== "undefined" && document.documentElement.getAttribute("data-theme") === "dark");
  function toggle() {
    const next = !dark;
    setDark(next);
    if (next) {
      document.documentElement.setAttribute("data-theme", "dark");
    } else {
      document.documentElement.removeAttribute("data-theme");
    }
    try { localStorage.setItem("theme", next ? "dark" : "light"); } catch {}
  }
  return (
    <button
      onClick={toggle}
      aria-label="Ganti tema"
      data-testid="theme-toggle"
      className={`inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius)] border border-[var(--color-border)] text-[var(--color-fg-muted)] transition hover:text-[var(--color-fg)] hover:bg-[var(--color-surface-2)] ${className}`}
    >
      {dark ? <Sun size={18} /> : <Moon size={18} />}
    </button>
  );
}
