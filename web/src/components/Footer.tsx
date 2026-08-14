"use client";
import { useEffect, useState } from "react";
import Link from "next/link";
import { fetchAppVersion, type AppVersion } from "@/lib/api";

export function Footer() {
  const [version, setVersion] = useState<AppVersion | null>(null);

  useEffect(() => {
    let cancelled = false;
    fetchAppVersion().then(v => {
      if (!cancelled) setVersion(v);
    });
    return () => { cancelled = true; };
  }, []);

  return (
    <footer className="border-t border-[var(--color-border)] py-3 px-4 text-xs text-[var(--color-fg-subtle)]">
      <div className="mx-auto flex max-w-7xl items-center justify-between gap-4">
        <span>
          AI Plan Studio · {version ? `v${version.version}` : "…"}
        </span>
        <div className="flex items-center gap-3">
          {version && (
            <Link href="/settings/about" className="hover:text-[var(--color-fg-muted)]">
              About
            </Link>
          )}
        </div>
      </div>
    </footer>
  );
}
