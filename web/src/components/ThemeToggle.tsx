"use client";
import { useEffect, useState } from "react";
import { Moon, Sun } from "lucide-react";

export function ThemeToggle({ className = "" }: { className?: string }) {
  const [light, setLight] = useState(false);
  useEffect(() => {
    setLight(document.documentElement.getAttribute("data-theme") === "light");
  }, []);
  function toggle() {
    const next = !light;
    setLight(next);
    document.documentElement.setAttribute("data-theme", next ? "light" : "dark");
    try { localStorage.setItem("theme", next ? "light" : "dark"); } catch {}
  }
  return (
    <button
      onClick={toggle}
      aria-label="Ganti tema"
      data-testid="theme-toggle"
      className={`inline-flex h-10 w-10 items-center justify-center rounded-[var(--radius)] border border-[var(--color-border)] text-[var(--color-fg-muted)] transition hover:text-[var(--color-fg)] hover:bg-[var(--color-surface-2)] ${className}`}
    >
      {light ? <Moon size={18} /> : <Sun size={18} />}
    </button>
  );
}
