"use client";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { Button, ButtonLink } from "@/components/ui/Button";
import { ConfirmDialog } from "@/components/ui/ConfirmDialog";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, apiPost, apiDelete, type Target } from "@/lib/api";
import { useUser } from "@/components/UserContext";
import {
  LayoutDashboard, ShoppingCart, Smartphone, Store, Rocket, Wrench, ArrowRight, Loader2, Plus, Trash2,
} from "lucide-react";

type Template = {
  id: number;
  name: string;
  target: Target;
  description: string;
  seed?: object;
  created_at: string;
  updated_at: string;
};

const ICON_MAP: Record<string, typeof Rocket> = {
  "SaaS Dashboard": LayoutDashboard,
  "E-Commerce": ShoppingCart,
  "Mobile CRUD": Smartphone,
  "Marketplace": Store,
  "Landing + Waitlist": Rocket,
  "Internal Tool": Wrench,
};

export default function TemplatesPage() {
  const { user } = useUser();
  const isAdmin = user?.role === "admin";
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [showForm, setShowForm] = useState(false);
  const [saving, setSaving] = useState(false);
  const [deleting, setDeleting] = useState<number | null>(null);
  const [confirmDeleteId, setConfirmDeleteId] = useState<number | null>(null);

  useEffect(() => {
    apiGet<Template[]>("/templates")
      .then(setTemplates)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat templates"))
      .finally(() => setLoading(false));
  }, []);

  async function handleCreate(e: React.FormEvent<HTMLFormElement>) {
    e.preventDefault();
    setSaving(true);
    setError("");
    const form = new FormData(e.currentTarget);
    const body = {
      name: form.get("name") as string,
      target: form.get("target") as string,
      description: form.get("description") as string,
    };
    try {
      const created = await apiPost<Template>("/templates", body);
      setTemplates(prev => [...prev, created]);
      setShowForm(false);
      (e.target as HTMLFormElement).reset();
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membuat template");
    } finally {
      setSaving(false);
    }
  }

  async function handleDelete(id: number) {
    setDeleting(id);
    try {
      await apiDelete(`/templates/${id}`);
      setTemplates(prev => prev.filter(t => t.id !== id));
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal menghapus template");
    } finally {
      setDeleting(null);
      setConfirmDeleteId(null);
    }
  }

  if (loading) {
    return (
      <>
        <PageHeader title="Templates" subtitle="Mulai lebih cepat dari preset jenis aplikasi." />
        <div className="text-center py-12"><Loader2 className="animate-spin inline" /> Memuat templates...</div>
      </>
    );
  }

  if (error) {
    return (
      <>
        <PageHeader title="Templates" subtitle="Mulai lebih cepat dari preset jenis aplikasi." />
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      </>
    );
  }

  return (
    <>
      <PageHeader title="Templates" subtitle="Mulai lebih cepat dari preset jenis aplikasi." />
      {isAdmin && (
        <div className="mb-4">
          {!showForm ? (
            <Button variant="secondary" onClick={() => setShowForm(true)}>
              <Plus size={16} /> Buat Template
            </Button>
          ) : (
            <form onSubmit={handleCreate} className="rounded-xl border border-[var(--color-border)] bg-[var(--color-surface)] p-4 space-y-3">
              <div className="flex items-center justify-between">
                <h3 className="font-semibold">Template Baru</h3>
                <button type="button" onClick={() => setShowForm(false)} className="text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">Batal</button>
              </div>
              <div className="grid gap-3 sm:grid-cols-2">
                <input name="name" placeholder="Nama template" required className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm" />
                <select name="target" className="rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm">
                  <option value="web">Web</option>
                  <option value="both">Web + Mobile</option>
                </select>
              </div>
              <textarea name="description" placeholder="Deskripsi singkat" rows={2} className="w-full rounded-lg border border-[var(--color-border)] bg-[var(--color-surface-1)] px-3 py-2 text-sm" />
              <Button type="submit" size="sm" disabled={saving}>
                {saving ? <Loader2 size={14} className="animate-spin" /> : "Simpan Template"}
              </Button>
            </form>
          )}
        </div>
      )}
      {templates.length === 0 && (
        <div className="text-center py-12 text-[var(--color-fg-muted)]">
          Belum ada template tersedia.
        </div>
      )}
      {templates.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {templates.map((t) => {
            const Icon = ICON_MAP[t.name] ?? Rocket;
            return (
              <Card key={t.id} className="group flex flex-col p-6 transition hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]">
                <div className="flex items-center justify-between">
                  <div className="grid h-11 w-11 place-items-center rounded-xl bg-[color-mix(in_oklab,var(--color-brand)_14%,transparent)] text-[var(--color-brand)]">
                    <Icon size={20} />
                  </div>
                  <TargetBadge target={t.target} />
                </div>
                <h3 className="mt-4 font-semibold">{t.name}</h3>
                <p className="mt-1 flex-1 text-sm text-[var(--color-fg-muted)]">{t.description}</p>
                <ButtonLink href={`/new?template=${t.id}`} variant="secondary" size="sm" className="mt-4">
                  Gunakan Template <ArrowRight size={15} />
                </ButtonLink>
                {isAdmin && (
                  <button
                    onClick={() => setConfirmDeleteId(t.id)}
                    disabled={deleting === t.id}
                    className="mt-2 inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)] hover:text-red-500 transition disabled:opacity-50"
                    title="Hapus template"
                  >
                    {deleting === t.id ? <Loader2 size={12} className="animate-spin" /> : <Trash2 size={12} />} Hapus Template
                  </button>
                )}
              </Card>
            );
          })}
        </div>
      )}

      <ConfirmDialog
        open={confirmDeleteId !== null}
        onClose={() => setConfirmDeleteId(null)}
        onConfirm={() => confirmDeleteId !== null && handleDelete(confirmDeleteId)}
        title="Hapus Template?"
        message="Yakin ingin menghapus template ini?"
        confirmLabel="Ya, Hapus"
      />
    </>
  );
}
