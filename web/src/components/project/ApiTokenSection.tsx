"use client";
import { useState, useEffect } from "react";
import { Card } from "@/components/ui";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { Button } from "@/components/ui/Button";
import { Loader2 } from "lucide-react";
import { apiGet, apiPost, apiDelete } from "@/lib/api";

interface ApiToken {
  id: number;
  name: string;
  last_used_at: string | null;
  expires_at: string | null;
  created_at: string;
}

export function ApiTokenSection({ projectId }: { projectId: string }) {
  const [tokens, setTokens] = useState<ApiToken[]>([]);
  const [loading, setLoading] = useState(true);
  const [showForm, setShowForm] = useState(false);
  const [tokenName, setTokenName] = useState("");
  const [newToken, setNewToken] = useState<string | null>(null);
  const [creating, setCreating] = useState(false);
  const [tokenError, setTokenError] = useState("");
  const [confirmDeleteTokenId, setConfirmDeleteTokenId] = useState<number | null>(null);

  useEffect(() => {
    apiGet<ApiToken[]>(`/projects/${projectId}/tokens`)
      .then(setTokens)
      .catch((err) => console.error('Failed to load API tokens:', err))
      .finally(() => setLoading(false));
  }, [projectId]);

  async function handleCreate() {
    if (!tokenName.trim()) return;
    setCreating(true);
    try {
      const res = await apiPost<{ token: string; id: number; name: string }>(`/projects/${projectId}/tokens`, { name: tokenName });
      setNewToken(res.token);
      setTokens(prev => [...prev, { id: res.id, name: res.name, last_used_at: null, expires_at: null, created_at: new Date().toISOString() }]);
      setTokenName("");
      setShowForm(false);
    } catch (err) {
      setTokenError(err instanceof Error ? err.message : "Gagal membuat token");
    } finally {
      setCreating(false);
    }
  }

  async function handleDelete(tokenId: number) {
    try {
      await apiDelete(`/projects/${projectId}/tokens/${tokenId}`);
      setTokens(prev => prev.filter(t => t.id !== tokenId));
    } catch (err) {
      setTokenError(err instanceof Error ? err.message : "Gagal menghapus token");
    } finally {
      setConfirmDeleteTokenId(null);
    }
  }

  return (
    <>
    <Card className="mt-6 p-5">
      <div className="flex items-center justify-between">
        <h3 className="font-semibold">API Tokens</h3>
        <Button size="sm" onClick={() => setShowForm(!showForm)}>
          {showForm ? "Batal" : "Buat Token"}
        </Button>
      </div>

      {tokenError && (
        <p className="mt-2 text-sm text-red-500">{tokenError}</p>
      )}

      {showForm && (
        <div className="mt-4 flex items-center gap-2">
          <input
            type="text"
            value={tokenName}
            onChange={(e) => setTokenName(e.target.value)}
            placeholder="Nama token..."
            className="flex-1 rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-1.5 text-sm text-[var(--color-fg)] focus:outline-none focus:ring-2 focus:ring-[var(--color-accent)]"
          />
          <Button size="sm" onClick={handleCreate} disabled={creating}>
            {creating ? <Loader2 size={14} className="animate-spin" /> : "Simpan"}
          </Button>
        </div>
      )}

      {newToken && (
        <div className="mt-4 rounded-lg border border-[var(--color-warning)]/40 bg-[var(--color-warning)]/10 px-4 py-3 text-sm">
          <p className="font-medium text-[var(--color-warning)]">Token baru dibuat</p>
          <pre className="mt-2 overflow-auto rounded bg-[var(--color-surface-1)] p-3 text-xs">{newToken}</pre>
          <p className="mt-1 text-xs text-[var(--color-fg-muted)]">Salin token sekarang. Tidak bisa dilihat lagi nanti.</p>
        </div>
      )}

      {loading && <div className="mt-4 text-center text-sm text-[var(--color-fg-muted)]"><Loader2 className="animate-spin inline" size={14} /></div>}

      {!loading && tokens.length === 0 && (
        <p className="mt-4 text-sm text-[var(--color-fg-subtle)]">Belum ada API token.</p>
      )}

      {tokens.length > 0 && (
        <div className="mt-4 space-y-2">
          {tokens.map((t) => (
            <div key={t.id} className="flex items-center justify-between rounded-lg border border-[var(--color-border)] px-4 py-2.5 text-sm">
              <div>
                <span className="font-medium">{t.name}</span>
                {t.last_used_at && <span className="ml-2 text-xs text-[var(--color-fg-muted)]">Terakhir digunakan {new Date(t.last_used_at).toLocaleDateString("id-ID")}</span>}
              </div>
              <button
                onClick={() => setConfirmDeleteTokenId(t.id)}
                aria-label={`Hapus token ${t.name}`}
                className="text-xs text-[var(--color-danger)] hover:underline"
              >
                Hapus
              </button>
            </div>
          ))}
        </div>
      )}
    </Card>
    <ConfirmDialog
      open={confirmDeleteTokenId !== null}
      onClose={() => setConfirmDeleteTokenId(null)}
      onConfirm={() => confirmDeleteTokenId !== null && handleDelete(confirmDeleteTokenId)}
      title="Hapus Token?"
      message="Yakin ingin menghapus token ini? Akses webhook akan dicabut."
      confirmLabel="Ya, Hapus"
    />
    </>
  );
}
