"use client"
import { useEffect, useState } from "react"
import { useParams, useSearchParams } from "next/navigation"
import { Card, Markdown } from "@/components/ui"
import { ButtonLink } from "@/components/ui/Button"
import { PageHeader } from "@/components/common"
import { apiGet } from "@/lib/api"
import { ChevronLeft, GitBranch, Loader2 } from "lucide-react"

interface DiffEntry {
  field: string
  label: string
  left: string | null
  right: string | null
  changed: boolean
}

interface DiffResponse {
  left: { id: number; version_no: number; project_title: string }
  right: { id: number; version_no: number; project_title: string }
  diffs: DiffEntry[]
}

function DiffBlock({ label, left, right, changed }: DiffEntry) {
  return (
    <Card
      className={`overflow-hidden ${changed ? "border-[var(--color-warning)]/40" : ""}`}
    >
      <div className="flex items-center gap-2 border-b border-[var(--color-border)] bg-[var(--color-surface-2)] px-4 py-2 text-sm font-medium">
        {label}
        {changed && (
          <span className="ml-auto rounded-full bg-[var(--color-warning)]/15 px-2 py-0.5 text-xs text-[var(--color-warning)]">
            Berubah
          </span>
        )}
        {!changed && (
          <span className="ml-auto rounded-full bg-[var(--color-success)]/15 px-2 py-0.5 text-xs text-[var(--color-success)]">
            Sama
          </span>
        )}
      </div>
      <div className="grid grid-cols-1 divide-y divide-[var(--color-border)] md:grid-cols-2 md:divide-x md:divide-y-0">
        <div className="p-4">
          <div className="mb-2 text-xs font-medium text-[var(--color-fg-muted)]">
            Sebelum
          </div>
          {left ? (
            <Markdown className="text-sm text-[var(--color-fg)]">
              {left}
            </Markdown>
          ) : (
            <span className="text-sm text-[var(--color-fg-muted)] italic">
              Kosong
            </span>
          )}
        </div>
        <div className="p-4">
          <div className="mb-2 text-xs font-medium text-[var(--color-fg-muted)]">
            Sesudah
          </div>
          {right ? (
            <Markdown className="text-sm text-[var(--color-fg)]">
              {right}
            </Markdown>
          ) : (
            <span className="text-sm text-[var(--color-fg-muted)] italic">
              Kosong
            </span>
          )}
        </div>
      </div>
    </Card>
  )
}

export default function DiffPage() {
  const params = useParams()
  const searchParams = useSearchParams()
  const id = params.id as string
  const compare = searchParams.get("compare")
  // CP-44 CP-05: route `id` = project id; endpoint butuh version id → wajib query `current`.
  const currentVersionId = searchParams.get("current")

  const [data, setData] = useState<DiffResponse | null>(null)
  const [loading, setLoading] = useState(true)
  const [error, setError] = useState("")

  useEffect(() => {
    if (!compare || !currentVersionId) return
    apiGet<DiffResponse>(
      `/versions/${currentVersionId}/diff?compare=${compare}`
    )
      .then(setData)
      .catch((err) =>
        setError(err instanceof Error ? err.message : "Gagal memuat diff")
      )
      .finally(() => setLoading(false))
  }, [id, compare, currentVersionId])

  if (!compare || !currentVersionId) {
    return (
      <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
        Parameter <code>current</code> dan <code>compare</code> diperlukan.
      </div>
    )
  }

  if (loading) {
    return (
      <div className="py-12 text-center">
        <Loader2 className="inline animate-spin" /> Memuat perbandingan...
      </div>
    )
  }

  if (error) {
    return (
      <div className="rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
        {error}
      </div>
    )
  }

  return (
    <>
      <PageHeader
        title="Perbandingan Versi"
        subtitle={`${data!.left.project_title} — v${data!.left.version_no} vs v${data!.right.version_no}`}
        action={
          <ButtonLink href={`/projects/${params.id}`} variant="ghost" size="sm">
            <ChevronLeft size={15} /> Kembali
          </ButtonLink>
        }
      />

      <div className="mt-2 flex items-center gap-4 rounded-lg bg-[var(--color-surface-2)] px-4 py-3 text-sm text-[var(--color-fg-muted)]">
        <span className="inline-flex items-center gap-1.5">
          <GitBranch size={14} /> v{data!.left.version_no}
        </span>
        <span className="text-[var(--color-fg-ghost)]">→</span>
        <span className="inline-flex items-center gap-1.5">
          <GitBranch size={14} /> v{data!.right.version_no}
        </span>
        <span className="ml-auto text-xs">
          {data!.diffs.filter((d) => d.changed).length} dari{" "}
          {data!.diffs.length} field berubah
        </span>
      </div>

      <div className="mt-6 space-y-6">
        {data!.diffs.map((d) => (
          <DiffBlock key={d.field} {...d} />
        ))}
      </div>
    </>
  )
}
