"use client";
import { useEffect, useState } from "react";
import { Card, Input, Label, Badge } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { CheckCircle2, AlertCircle, Zap, Plus, Trash2, Star, X, Lock, Loader2 } from "lucide-react";
import { apiGet, apiPost, apiPatch, apiDelete, fetchCsrfCookie, ApiError } from "@/lib/api";

type Provider = {
  id: number; name: string; base_url: string; model: string;
  provider_type: string; is_active: boolean; api_key_masked: string;
  last_test_response: string | null; last_test_at: string | null;
};

const PROVIDER_TYPES = [
  { key: "openai", label: "OpenAI Compatible", defaultUrl: "https://api.openai.com/v1", defaultModel: "gpt-4o" },
  { key: "anthropic", label: "Anthropic Compatible", defaultUrl: "https://api.anthropic.com", defaultModel: "claude-sonnet-4-20250514" },
  { key: "custom", label: "Custom", defaultUrl: "", defaultModel: "" },
];

function emptyForm(type?: string) {
  const key = type || 'openai';
  const t = PROVIDER_TYPES.find(p => p.key === key) ?? PROVIDER_TYPES[0];
  return { name: "", base_url: t.defaultUrl, api_key: "", model: t.defaultModel, provider_type: key };
}

export default function ProviderSettings() {
  const [providers, setProviders] = useState<Provider[]>([]);
  const [loading, setLoading] = useState(true);
  const [denied, setDenied] = useState(false);
  const [showForm, setShowForm] = useState(false);
  const [editId, setEditId] = useState<number | null>(null);
  const [form, setForm] = useState(emptyForm());
  const [saving, setSaving] = useState(false);
  const [saveMsg, setSaveMsg] = useState("");
  const [saveError, setSaveError] = useState(false);
  const [promptBusy, setPromptBusy] = useState<number | null>(null);
  const [testBusy, setTestBusy] = useState<number | null>(null);
  const [promptRes, setPromptRes] = useState<{id: number; resp: string} | null>(null);

  function load() {
    apiGet<Provider[]>("/settings/provider").then(setProviders).catch((err) => {
      if (err instanceof ApiError && err.status === 403) setDenied(true);
      else console.error('Failed to load providers:', err);
    }).finally(() => setLoading(false));
  }
  useEffect(load, []);

interface ProviderFormData {
  name: string; base_url: string; model: string; provider_type: string; api_key?: string;
}

  async function save() {
    setSaving(true); setSaveMsg(""); setSaveError(false);
    try {
      await fetchCsrfCookie();
      if (editId) {
        const b: ProviderFormData = { name: form.name, base_url: form.base_url, model: form.model, provider_type: form.provider_type };
        if (form.api_key) b.api_key = form.api_key;
        await apiPatch(`/settings/provider/${editId}`, b);
      } else {
        await apiPost("/settings/provider", form);
      }
      setShowForm(false); setEditId(null); setForm(emptyForm()); setSaveMsg("Tersimpan!");
      load();
    } catch (e: unknown) { setSaveMsg(e instanceof Error ? e.message : "Gagal"); setSaveError(true); }
    finally { setSaving(false); }
  }

  function edit(p: Provider) {
    setEditId(p.id); setForm({
      name: p.name, base_url: p.base_url, api_key: "", model: p.model,
      provider_type: p.provider_type,
    }); setShowForm(true);
  }

  async function remove(id: number) {
    await fetchCsrfCookie(); await apiDelete(`/settings/provider/${id}`); load();
  }

  async function setActive(id: number) {
    await fetchCsrfCookie(); await apiPost(`/settings/provider/${id}/set-active`); load();
  }

  async function testConn(id: number) {
    setTestBusy(id);
    try {
      await fetchCsrfCookie();
      const r = await apiPost<{ok: boolean; message: string}>(`/settings/provider/${id}/test`);
      setSaveMsg(r.message); setSaveError(!r.ok);
    } catch (e: unknown) { setSaveMsg(e instanceof Error ? e.message : "Gagal"); setSaveError(true); }
    finally { setTestBusy(null); load(); }
  }

  async function testPrompt(id: number) {
    setPromptBusy(id); setPromptRes(null);
    try {
      await fetchCsrfCookie();
      const r = await apiPost<{ok: boolean; message: string; response: string | null}>(`/settings/provider/${id}/test-prompt`, { prompt: "Halo" });
      setPromptRes({ id, resp: r.response || r.message });
    } catch (e: unknown) { setPromptRes({ id, resp: e instanceof Error ? e.message : "Gagal" }); }
    finally { setPromptBusy(null); load(); }
  }

  const activeProvider = providers.find(p => p.is_active);

  if (loading) {
    return (
      <div className="flex h-40 items-center justify-center">
        <Loader2 size={24} className="animate-spin text-[var(--color-fg-muted)]" />
      </div>
    );
  }

  if (denied) {
    return (
      <Card className="flex flex-col items-center gap-3 p-10 text-center">
        <Lock size={32} className="text-[var(--color-danger)]" />
        <h3 className="font-semibold">Akses Ditolak</h3>
        <p className="text-sm text-[var(--color-fg-muted)]">
          Halaman ini hanya untuk administrator.
        </p>
      </Card>
    );
  }

  return (
    <div className="space-y-6">
      {saveMsg && (
        <Badge tone={saveError ? "danger" : "success"}>
          {saveError ? <AlertCircle size={13} /> : <CheckCircle2 size={13} />} {saveMsg}
        </Badge>
      )}

      {activeProvider && (
        <Card className="p-4 border-[var(--color-brand)]/40 bg-[color-mix(in_oklab,var(--color-brand)_6%,transparent)]">
          <div className="flex items-center gap-3 text-sm">
            <Star size={16} className="text-[var(--color-brand)]" />
            <span className="font-medium">Model Global:</span>
            <Badge tone="brand">{activeProvider.name}</Badge>
            <span className="text-[var(--color-fg-muted)]">{activeProvider.model} ({activeProvider.provider_type})</span>
          </div>
        </Card>
      )}

      <div className="space-y-3">
        <div className="flex items-center justify-between">
          <h3 className="font-semibold">Provider Tersimpan</h3>
          <Button size="sm" onClick={() => { setEditId(null); setForm(emptyForm()); setShowForm(true); }}>
            <Plus size={15} /> Tambah Provider
          </Button>
        </div>

        {loading && <p className="text-sm text-[var(--color-fg-muted)]">Memuat...</p>}
        {!loading && providers.length === 0 && (
          <Card className="p-6 text-center">
            <p className="text-sm text-[var(--color-fg-muted)]">Belum ada provider. Tambah provider AI pertama.</p>
          </Card>
        )}

        {providers.map(p => (
          <Card key={p.id} className={`p-4 ${p.is_active ? 'border-[var(--color-brand)]/50' : ''}`}>
            <div className="flex items-start justify-between gap-3">
              <div className="min-w-0 flex-1">
                <div className="flex items-center gap-2">
                  <span className="font-semibold">{p.name}</span>
                  <Badge tone="muted" className="text-[10px]">{p.provider_type}</Badge>
                  {p.is_active && <Badge tone="brand" className="text-[10px]"><Star size={10} /> Global</Badge>}
                  {p.last_test_response && (
                    <span className={`inline-block h-2 w-2 rounded-full ${p.last_test_response.toLowerCase().includes('ok') || p.last_test_response.toLowerCase().includes('berhasil') ? 'bg-green-500' : 'bg-yellow-500'}`} title={p.last_test_response} />
                  )}
                </div>
                <div className="mt-1 text-xs text-[var(--color-fg-muted)] space-y-0.5">
                  <div>Model: <span className="font-mono">{p.model}</span></div>
                  <div>Base URL: <span className="font-mono">{p.base_url}</span></div>
                  <div>API Key: <span className="font-mono">{p.api_key_masked || "⚠️ kosong"}</span></div>
                  {p.last_test_at && <div>Test terakhir: {p.last_test_at}</div>}
                </div>
              </div>
            </div>

            {p.last_test_response && !(promptRes?.id === p.id) && (
              <div className="mt-2 rounded-lg bg-[var(--color-bg-soft)] px-3 py-2 text-xs text-[var(--color-fg-muted)] border border-[var(--color-border)] max-h-20 overflow-y-auto">
                {p.last_test_response}
              </div>
            )}

            {promptRes?.id === p.id && promptRes.resp && (
              <div className="mt-2 rounded-xl border border-[var(--color-border)] bg-[var(--color-bg-soft)] p-3">
                <pre className="whitespace-pre-wrap text-xs text-[var(--color-fg)] max-h-40 overflow-y-auto">{promptRes.resp}</pre>
              </div>
            )}

            <div className="mt-3 flex flex-wrap items-center gap-2">
              <Button variant="secondary" size="sm" onClick={() => testConn(p.id)} disabled={testBusy === p.id}>
                {testBusy === p.id ? "..." : "Test Koneksi"}
              </Button>
              <Button variant="secondary" size="sm" onClick={() => testPrompt(p.id)} disabled={promptBusy === p.id}>
                <Zap size={13} /> {promptBusy === p.id ? "..." : "Test Prompt"}
              </Button>
              {!p.is_active && (
                <Button variant="outline" size="sm" onClick={() => setActive(p.id)}>
                  <Star size={13} /> Jadikan Global
                </Button>
              )}
              <Button variant="ghost" size="sm" onClick={() => edit(p)}>Edit</Button>
              <Button variant="ghost" size="sm" onClick={() => remove(p.id)} className="text-[var(--color-danger)]">
                <Trash2 size={13} />
              </Button>
            </div>
          </Card>
        ))}
      </div>

      {/* Modal Form */}
      {showForm && (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50" onClick={() => { setShowForm(false); setEditId(null); }}>
          <Card className="relative w-full max-w-lg mx-4 p-6 max-h-[90vh] overflow-y-auto" onClick={e => e.stopPropagation()}>
            <button className="absolute right-4 top-4 text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]"
              onClick={() => { setShowForm(false); setEditId(null); }}><X size={18} /></button>
            <h3 className="font-semibold mb-4">{editId ? "Edit Provider" : "Tambah Provider Baru"}</h3>
            <div className="space-y-4">
              <div>
                <Label htmlFor="pname">Nama Provider</Label>
                <Input id="pname" value={form.name} onChange={e => setForm({...form, name: e.target.value})} placeholder="Contoh: OpenAI GPT-4o" />
              </div>
              <div>
                <Label>Tipe Provider</Label>
                <div className="grid grid-cols-3 gap-2">
                  {PROVIDER_TYPES.map(t => (
                    <button key={t.key} onClick={() => setForm({...form, provider_type: t.key, base_url: t.defaultUrl, model: t.defaultModel})}
                      className={`rounded-xl border p-3 text-sm font-medium transition text-center ${
                        form.provider_type === t.key
                          ? 'border-[var(--color-brand)] bg-[color-mix(in_oklab,var(--color-brand)_12%,transparent)] text-[var(--color-brand)]'
                          : 'border-[var(--color-border)] text-[var(--color-fg-muted)]'
                      }`}>{t.label}</button>
                  ))}
                </div>
              </div>
              <div>
                <Label htmlFor="purl">Base URL</Label>
                <Input id="purl" value={form.base_url} onChange={e => setForm({...form, base_url: e.target.value})} />
              </div>
              <div>
                <Label htmlFor="pkey">API Key {editId && <span className="text-[var(--color-fg-subtle)]">(kosongkan jika tidak diubah)</span>}</Label>
                <Input id="pkey" type="password" value={form.api_key} onChange={e => setForm({...form, api_key: e.target.value})} />
              </div>
              <div>
                <Label htmlFor="pmodel">Model</Label>
                <Input id="pmodel" value={form.model} onChange={e => setForm({...form, model: e.target.value})} />
              </div>
              <div className="flex gap-3 pt-2">
                <Button onClick={save} disabled={saving || !form.name || !form.base_url || !form.model}>
                  {saving ? "Menyimpan..." : "Simpan"}
                </Button>
                <Button variant="secondary" onClick={() => { setShowForm(false); setEditId(null); }}>Batal</Button>
              </div>
            </div>
          </Card>
        </div>
      )}
    </div>
  );
}
