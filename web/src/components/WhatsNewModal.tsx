"use client";
import { useEffect, useState, useRef } from "react";
import { Modal } from "@/components/ui/Modal";
import { Button } from "@/components/ui/Button";
import { Sparkles, Loader2 } from "lucide-react";
import { apiGet, fetchAppVersion, type AppVersion } from "@/lib/api";

const STORAGE_KEY = "app:lastSeenVersion";

interface ChangelogEntry {
  version: string;
  date: string;
  highlights: string[];
  migrations?: string[];
}

export function WhatsNewModal() {
  const [open, setOpen] = useState(false);
  const [version, setVersion] = useState<AppVersion | null>(null);
  const [entries, setEntries] = useState<ChangelogEntry[]>([]);
  const [loading, setLoading] = useState(true);
  const checkedRef = useRef(false);

  useEffect(() => {
    if (checkedRef.current) return;
    checkedRef.current = true;

    let cancelled = false;
    (async () => {
      let lastSeen = "";
      try { lastSeen = localStorage.getItem(STORAGE_KEY) ?? ""; } catch {}

      const v = await fetchAppVersion();
      if (cancelled) return;
      setVersion(v);

      if (!v || v.version === lastSeen) {
        setLoading(false);
        return;
      }

      try {
        const res = await apiGet<{ data: ChangelogEntry[] } | ChangelogEntry[]>("/changelog");
        const list = Array.isArray(res) ? res : (res.data ?? []);
        if (!cancelled) setEntries(list);
      } catch {
        // changelog not yet available (MP8 TODO) — still show version info
      }

      if (!cancelled) {
        setOpen(true);
        setLoading(false);
      }
    })();

    return () => { cancelled = true; };
  }, []);

  const handleClose = () => {
    if (version) {
      try { localStorage.setItem(STORAGE_KEY, version.version); } catch {}
    }
    setOpen(false);
  };

  if (loading) {
    return (
      <Modal open={open} onClose={handleClose} title="What's new" size="md">
        <div className="py-8 text-center text-[var(--color-fg-muted)]">
          <Loader2 className="mx-auto animate-spin" /> Memuat...
        </div>
      </Modal>
    );
  }

  return (
    <Modal open={open} onClose={handleClose} title="What's new" size="md">
      <div className="space-y-4">
        {version && (
          <div className="flex items-center gap-3 rounded-lg border border-[var(--color-brand)]/30 bg-[color-mix(in_oklab,var(--color-brand)_8%,transparent)] p-3">
            <Sparkles size={18} className="text-[var(--color-brand)]" />
            <div className="flex-1">
              <div className="font-medium">You&apos;re on v{version.version}</div>
            </div>
          </div>
        )}

        {entries.length === 0 ? (
          <p className="text-sm text-[var(--color-fg-muted)]">
            Rilis baru sudah tersedia. Lihat halaman <a href="/settings/about" className="text-[var(--color-brand)] hover:underline">About</a> untuk detail versi.
          </p>
        ) : (
          <div className="space-y-4">
            {entries.map((e) => (
              <div key={e.version} className="rounded-lg border border-[var(--color-border)] p-4">
                <div className="flex items-center justify-between">
                  <div className="font-semibold">v{e.version}</div>
                  <div className="text-xs text-[var(--color-fg-muted)]">
                    {new Date(e.date).toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" })}
                  </div>
                </div>
                {e.highlights.length > 0 && (
                  <ul className="mt-2 space-y-1 text-sm">
                    {e.highlights.map((h, i) => (
                      <li key={i} className="flex gap-2">
                        <span className="text-[var(--color-brand)]">•</span>
                        <span>{h}</span>
                      </li>
                    ))}
                  </ul>
                )}
                {e.migrations && e.migrations.length > 0 && (
                  <details className="mt-2">
                    <summary className="cursor-pointer text-xs font-medium text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">
                      Migration notes ({e.migrations.length})
                    </summary>
                    <ul className="mt-1 space-y-1 text-xs text-[var(--color-fg-muted)]">
                      {e.migrations.map((m, i) => <li key={i}>— {m}</li>)}
                    </ul>
                  </details>
                )}
              </div>
            ))}
          </div>
        )}

        <div className="flex justify-end pt-2">
          <Button onClick={handleClose} data-testid="whatsnew-close">
            Got it
          </Button>
        </div>
      </div>
    </Modal>
  );
}
