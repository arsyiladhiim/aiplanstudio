"use client"

import { useEffect, useRef, useState } from "react"
import { useRouter } from "next/navigation"
import { Badge, Card, Input, Label } from "@/components/ui"
import { Button } from "@/components/ui/Button"
import { PageHeader } from "@/components/common"
import {
  ApiError,
  fetchResearchIdeasPaginated,
  type ResearchIdea,
  type ResearchIdeasPaginated,
} from "@/lib/api"
import { Lightbulb, Loader2, Search, Wand2 } from "lucide-react"

function ideaTextOf(idea: ResearchIdea): string {
  return [
    `Kendala: ${idea.problem}`,
    `Solusi: ${idea.solution}`,
    idea.target_users ? `Target pengguna: ${idea.target_users}` : "",
  ]
    .filter(Boolean)
    .join("\n")
}

export default function IdeasPage() {
  const router = useRouter()
  const [data, setData] = useState<ResearchIdeasPaginated | null>(null)
  const [denied, setDenied] = useState(false)
  const [loading, setLoading] = useState(true)
  const [searchInput, setSearchInput] = useState("")
  const [q, setQ] = useState("")
  const [dateFrom, setDateFrom] = useState("")
  const [dateTo, setDateTo] = useState("")
  const [page, setPage] = useState(1)
  const debounceRef = useRef<ReturnType<typeof setTimeout> | null>(null)

  useEffect(() => {
    fetchResearchIdeasPaginated({
      q: q || undefined,
      date_from: dateFrom || undefined,
      date_to: dateTo || undefined,
      page,
    })
      .then(setData)
      .catch((err) => {
        if (err instanceof ApiError && err.status === 403) setDenied(true)
        else console.error("ideas load:", err)
      })
      .finally(() => setLoading(false))
  }, [q, dateFrom, dateTo, page])

  function onSearch(value: string) {
    setSearchInput(value)
    if (debounceRef.current) clearTimeout(debounceRef.current)
    debounceRef.current = setTimeout(() => {
      setLoading(true)
      setPage(1)
      setQ(value)
    }, 400)
  }

  function onDate(setter: (v: string) => void, value: string) {
    setLoading(true)
    setPage(1)
    setter(value)
  }

  function goPage(next: number) {
    setLoading(true)
    setPage(next)
  }

  function createProject(idea: ResearchIdea) {
    const params = new URLSearchParams({
      idea_title: idea.title,
      idea_text: ideaTextOf(idea),
    })
    router.push(`/new?${params.toString()}`)
  }

  if (denied)
    return (
      <Card className="p-6 text-sm text-[var(--color-fg-muted)]">
        Halaman ini khusus admin.
      </Card>
    )

  return (
    <div data-testid="ideas-page">
      <PageHeader
        title="Bank Ide"
        subtitle="Hasil research agent — ide digitalisasi tersimpan permanen dan tidak hilang."
      />

      <Card className="mb-6 flex flex-wrap items-end gap-4 p-4">
        <div className="min-w-48 flex-1">
          <Label>Cari ide</Label>
          <div className="relative mt-1">
            <Search
              size={14}
              className="absolute top-1/2 left-3 -translate-y-1/2 text-[var(--color-fg-muted)]"
            />
            <Input
              className="pl-8"
              placeholder="Judul, kendala, solusi, target…"
              value={searchInput}
              onChange={(e) => onSearch(e.target.value)}
            />
          </div>
        </div>
        <div>
          <Label>Dari tanggal</Label>
          <Input
            type="date"
            value={dateFrom}
            onChange={(e) => onDate(setDateFrom, e.target.value)}
          />
        </div>
        <div>
          <Label>Sampai tanggal</Label>
          <Input
            type="date"
            value={dateTo}
            onChange={(e) => onDate(setDateTo, e.target.value)}
          />
        </div>
        {data && <Badge>{data.pagination.total} ide</Badge>}
      </Card>

      {loading && !data ? (
        <div className="py-12 text-center">
          <Loader2 className="mx-auto animate-spin" />
        </div>
      ) : !data || data.ideas.length === 0 ? (
        <Card className="p-8 text-center text-sm text-[var(--color-fg-muted)]">
          <Lightbulb className="mx-auto mb-2" size={20} />
          Belum ada ide yang cocok. Research agent mengumpulkan ide baru tiap
          jam.
        </Card>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2">
            {data.ideas.map((idea) => (
              <Card key={idea.id} className="flex flex-col gap-2 p-5">
                <div className="flex items-start justify-between gap-2">
                  <h3 className="text-sm font-semibold">{idea.title}</h3>
                  <Badge>{idea.window_date}</Badge>
                </div>
                {idea.target_users && (
                  <p className="text-xs text-[var(--color-fg-muted)]">
                    Target: {idea.target_users}
                  </p>
                )}
                <p className="text-sm">
                  <span className="font-medium">Kendala:</span> {idea.problem}
                </p>
                <p className="text-sm">
                  <span className="font-medium">Solusi:</span> {idea.solution}
                </p>
                {idea.sources.length > 0 && (
                  <p className="text-xs text-[var(--color-fg-muted)]">
                    Sumber:{" "}
                    {idea.sources.slice(0, 3).map((s, j) => (
                      <a
                        key={j}
                        href={s.url}
                        target="_blank"
                        rel="noreferrer"
                        className="mr-2 underline"
                      >
                        {s.title || s.url}
                      </a>
                    ))}
                  </p>
                )}
                <div className="mt-auto pt-2">
                  <Button
                    size="sm"
                    onClick={() => createProject(idea)}
                    data-testid={`idea-create-${idea.id}`}
                  >
                    <Wand2 size={14} /> Buat Proyek
                  </Button>
                </div>
              </Card>
            ))}
          </div>

          {data.pagination.last_page > 1 && (
            <div className="mt-6 flex items-center justify-center gap-3">
              <Button
                variant="secondary"
                size="sm"
                disabled={page <= 1}
                onClick={() => goPage(page - 1)}
              >
                Sebelumnya
              </Button>
              <span className="text-sm text-[var(--color-fg-muted)]">
                {data.pagination.current_page} / {data.pagination.last_page}
              </span>
              <Button
                variant="secondary"
                size="sm"
                disabled={page >= data.pagination.last_page}
                onClick={() => goPage(page + 1)}
              >
                Berikutnya
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  )
}
