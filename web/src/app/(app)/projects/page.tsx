"use client";
import Link from "next/link";
import { useEffect, useState } from "react";
import { Card } from "@/components/ui";
import { ButtonLink } from "@/components/ui/Button";
import { PageHeader, TargetBadge } from "@/components/common";
import { apiGet, type Project } from "@/lib/api";
import { Plus, GitBranch, Clock } from "lucide-react";

export default function ProjectsPage() {
  const [projects, setProjects] = useState<Project[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState("");

  useEffect(() => {
    apiGet<Project[]>("/projects")
      .then(setProjects)
      .catch((err) => setError(err instanceof Error ? err.message : "Gagal memuat projects"))
      .finally(() => setLoading(false));
  }, []);

  function formatDate(dateString: string): string {
    const date = new Date(dateString);
    const now = new Date();
    const diffMs = now.getTime() - date.getTime();
    const diffMins = Math.floor(diffMs / 60000);
    const diffHours = Math.floor(diffMs / 3600000);
    const diffDays = Math.floor(diffMs / 86400000);

    if (diffMins < 60) return `${diffMins} menit lalu`;
    if (diffHours < 24) return `${diffHours} jam lalu`;
    if (diffDays === 1) return "kemarin";
    if (diffDays < 7) return `${diffDays} hari lalu`;
    return date.toLocaleDateString("id-ID", { day: "numeric", month: "short", year: "numeric" });
  }

  return (
    <>
      <PageHeader
        title="Projects"
        subtitle="Semua plan yang kamu buat, lengkap dengan versi & progress."
        action={<ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>}
      />

      {loading && <div className="text-center py-12 text-[var(--color-fg-muted)]">Memuat projects...</div>}

      {error && (
        <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {!loading && !error && projects.length === 0 && (
        <div className="text-center py-12">
          <p className="text-[var(--color-fg-muted)] mb-4">Belum ada project. Mulai buat plan pertamamu!</p>
          <ButtonLink href="/new"><Plus size={16} /> Buat Plan Baru</ButtonLink>
        </div>
      )}

      {!loading && !error && projects.length > 0 && (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
          {projects.map((p) => (
            <Link key={p.id} href={`/projects/${p.id}`} data-testid={`project-${p.id}`}>
              <Card className="group h-full p-5 transition hover:-translate-y-0.5 hover:border-[color-mix(in_oklab,var(--color-brand)_45%,var(--color-border))]">
                <div className="flex items-center justify-between">
                  <TargetBadge target={p.target} />
                  <span className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-subtle)]">
                    <GitBranch size={12} /> {p.versions_count || 0} versi
                  </span>
                </div>
                <h3 className="mt-3 font-semibold">{p.title}</h3>
                <p className="mt-1 line-clamp-2 text-sm text-[var(--color-fg-muted)]">{p.idea}</p>

                <div className="mt-4">
                  <span className="inline-flex items-center gap-1 text-xs text-[var(--color-fg-muted)]">
                    <Clock size={12} /> {formatDate(p.updated_at)}
                  </span>
                </div>
              </Card>
            </Link>
          ))}
        </div>
      )}
    </>
  );
}
