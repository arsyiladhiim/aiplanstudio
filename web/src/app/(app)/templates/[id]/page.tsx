"use client";
import Link from "next/link";
import { useRouter } from "next/navigation";
import { useEffect, useState } from "react";
import { Card, Badge, Markdown, EmptyState } from "@/components/ui";
import { Button } from "@/components/ui/Button";
import { TargetBadge } from "@/components/common";
import { apiGet, apiPost, type Template } from "@/lib/api";
import { ArrowLeft, ArrowRight, Loader2, Plus } from "lucide-react";

export default function TemplateDetail({ params }: { params: Promise<{ id: string }> }) {
  const router = useRouter();
  const [template, setTemplate] = useState<Template | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");
  const [instantiateLoading, setInstantiateLoading] = useState(false);

  useEffect(() => {
    const resolve = async () => {
      const { id } = await params;
      apiGet<Template>(`/templates/${id}`)
        .then(setTemplate)
        .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat template"))
        .finally(() => setLoading(false));
    };
    resolve();
  }, [params]);

  async function handleInstantiate() {
    if (!template) return;
    setInstantiateLoading(true);
    try {
      const project = await apiPost<{ id: number }>(`/templates/${template.id}/instantiate`, {});
      router.push(`/projects/${project.id}`);
    } catch (err) {
      setError(err instanceof Error ? err.message : "Gagal membuat project");
    } finally {
      setInstantiateLoading(false);
    }
  }

  if (loading) {
    return <div className="py-12 text-center text-[var(--color-fg-muted)]"><Loader2 className="mr-2 inline animate-spin" />Memuat template...</div>;
  }

  if (error && !template) {
    return <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">{error}</div>;
  }

  if (!template) return null;

  return (
    <div className="mx-auto max-w-3xl">
      <Link href="/templates" className="mb-4 inline-flex items-center gap-1 text-sm text-[var(--color-fg-muted)] hover:text-[var(--color-fg)]">
        <ArrowLeft size={14} /> Templates
      </Link>

      <div className="flex items-start justify-between gap-4">
        <div>
          <div className="flex items-center gap-2">
            <h1 className="text-2xl font-bold">{template.name}</h1>
            <TargetBadge target={template.target} />
          </div>
          {template.description && <p className="mt-1.5 text-sm text-[var(--color-fg-muted)]">{template.description}</p>}
        </div>
        <Button onClick={handleInstantiate} disabled={instantiateLoading}>
          {instantiateLoading ? <Loader2 size={15} className="animate-spin" /> : <Plus size={15} />}
          Gunakan Template
        </Button>
      </div>

      {error && (
        <div className="mt-4 rounded-lg border border-red-500/40 bg-red-500/10 px-4 py-2 text-sm text-red-500">{error}</div>
      )}

      <h2 className="mt-8 mb-2 text-lg font-semibold">Seed</h2>
      {(() => {
        const seed = (template.seed ?? {}) as Record<string, string>;
        return Object.keys(seed).length > 0 ? (
        <Card className="p-5">
          {seed.title && (
            <div className="mb-3">
              <Badge tone="muted">title</Badge>
              <p className="mt-1">{seed.title}</p>
            </div>
          )}
          {seed.idea && (
            <div>
              <Badge tone="muted">idea</Badge>
              <Markdown className="mt-1 text-sm text-[var(--color-fg-muted)]">{seed.idea}</Markdown>
            </div>
          )}
        </Card>
      ) : (
        <EmptyState title="Tidak ada seed" description="Template ini hanya men-set judul & target; isi idea secara manual di wizard." />
      );
      })()}

      <div className="mt-8 flex gap-2">
        <Button variant="secondary" onClick={() => router.push("/templates")}>
          <ArrowLeft size={15} /> Kembali
        </Button>
        <Button onClick={() => router.push(`/new?template=${template.id}`)}>
          Keform Bootstrap <ArrowRight size={15} />
        </Button>
      </div>
    </div>
  );
}