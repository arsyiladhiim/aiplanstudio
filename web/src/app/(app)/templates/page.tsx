"use client";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, type Target } from "@/lib/api";
import {
  LayoutDashboard, ShoppingCart, Smartphone, Store, Rocket, Wrench, ArrowRight, Loader2,
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
  const [templates, setTemplates] = useState<Template[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    apiGet<Template[]>("/templates")
      .then(setTemplates)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat templates"))
      .finally(() => setLoading(false));
  }, []);

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
              </Card>
            );
          })}
        </div>
      )}
    </>
  );
}
