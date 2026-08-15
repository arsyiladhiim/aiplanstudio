"use client";
import { useState } from "react";
import { Card, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { Modal } from "@/components/ui/Modal";
import { Link2, Check, Loader2 } from "lucide-react";
import { apiSetupAutoTracking, type AutoTrackingToken } from "@/lib/api";

export function SetupTrackingCard({
  projectId,
  versionId,
}: {
  projectId: number;
  versionId: number;
}) {
  const [open, setOpen] = useState(false);
  const [loading, setLoading] = useState(false);
  const [result, setResult] = useState<AutoTrackingToken | null>(null);
  const [error, setError] = useState<string | null>(null);
  const cacheKey = `tracking-token-${projectId}-${versionId}`;
  const [hasToken, setHasToken] = useState<boolean>(() => {
    if (typeof window === "undefined") return false;
    return !!sessionStorage.getItem(cacheKey);
  });

  async function handleCreate() {
    setLoading(true);
    setError(null);
    try {
      const r = await apiSetupAutoTracking(projectId, versionId);
      setResult(r);
      if (r.token && r.secret) {
        sessionStorage.setItem(cacheKey, JSON.stringify({ token: r.token, secret: r.secret, createdAt: Date.now() }));
        setHasToken(true);
      }
    } catch (e) {
      setError(e instanceof Error ? e.message : "Gagal membuat token.");
    } finally {
      setLoading(false);
    }
  }

  function close() {
    setOpen(false);
    setResult(null);
    setError(null);
  }

  if (hasToken) return null;

  return (
    <>
      <Card className="border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_8%,transparent)] p-4">
        <div className="flex items-start gap-3">
          <div className="mt-0.5 rounded-md bg-[var(--color-brand)] p-2 text-white">
            <Link2 size={16} />
          </div>
          <div className="min-w-0 flex-1">
            <div className="flex items-center gap-2">
              <h4 className="text-sm font-semibold">Aktifkan Tracking Webhook</h4>
              <Badge tone="brand">CP-6</Badge>
            </div>
            <p className="mt-1 text-xs text-[var(--color-fg-muted)]">
              Buat token agar coding agent bisa mengirim checkpoint per fase + sub-item (halaman/menu/fitur/flow/api). Tanpa token, agent akan skip webhook.
            </p>
            <Button variant="primary" size="sm" onClick={() => setOpen(true)} className="mt-3" data-testid="open-setup-tracking-card">
              Setup Sekarang
            </Button>
          </div>
        </div>
      </Card>

      <Modal open={open} title="Setup Tracking Token" onClose={close}>
        <div className="space-y-3">
          <p className="text-sm text-[var(--color-fg-muted)]">
            Token dipakai untuk Authorization header, Secret untuk HMAC signature. Simpan keduanya sekarang — secret tidak ditampilkan ulang.
          </p>
          {!result && !error && (
            <Button variant="primary" size="md" onClick={handleCreate} disabled={loading}>
              {loading ? <Loader2 size={14} className="animate-spin" /> : <Link2 size={14} />}
              {loading ? "Membuat…" : "Buat Token"}
            </Button>
          )}
          {error && <div className="rounded-md bg-[color-mix(in_oklab,var(--color-danger)_15%,transparent)] p-3 text-sm text-[var(--color-danger)]">{error}</div>}
          {result?.existing && (
            <div className="rounded-md bg-[var(--color-surface-2)] p-3 text-sm">
              Token sudah ada. Buat baru via <code className="rounded bg-[var(--color-bg-soft)] px-1.5 py-0.5">/projects/{`{id}`}/tokens</code> jika secret hilang.
            </div>
          )}
          {result && !result.existing && result.secret && (
            <div className="space-y-3">
              <SecretField label="X-Token-Secret" value={result.secret} />
              <SecretField label="Bearer Token" value={result.token ?? ""} />
            </div>
          )}
        </div>
      </Modal>
    </>
  );
}

function SecretField({ label, value }: { label: string; value: string }) {
  const [copied, setCopied] = useState(false);
  return (
    <div>
      <div className="mb-1 text-xs font-semibold text-[var(--color-fg-muted)]">{label}</div>
      <div className="flex items-center gap-2">
        <code className="flex-1 overflow-x-auto rounded-md border border-[var(--color-border)] bg-[var(--color-bg-soft)] px-3 py-2 font-mono text-xs">{value}</code>
        <Button
          variant="outline"
          size="sm"
          onClick={async () => {
            await navigator.clipboard.writeText(value);
            setCopied(true);
            setTimeout(() => setCopied(false), 1500);
          }}
        >
          {copied ? <Check size={12} /> : <Link2 size={12} />}
          {copied ? "Copied" : "Copy"}
        </Button>
      </div>
    </div>
  );
}
