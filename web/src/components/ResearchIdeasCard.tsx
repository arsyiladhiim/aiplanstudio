"use client"
import { useEffect, useState } from "react"
import Link from "next/link"
import { Card, Badge } from "@/components/ui"
import { Telescope } from "lucide-react"
import { fetchResearchIdeas, ApiError, type ResearchIdea } from "@/lib/api"

export function ResearchIdeasCard() {
  const [data, setData] = useState<{
    ideas: ResearchIdea[]
    count_today: number
    max_per_day: number
  } | null>(null)
  const [hidden, setHidden] = useState(false)

  useEffect(() => {
    fetchResearchIdeas()
      .then(setData)
      .catch((err) => {
        if (!(err instanceof ApiError && err.status === 403))
          console.error("research ideas:", err)
        setHidden(true)
      })
  }, [])

  if (hidden || !data) return null

  const todays = data.ideas.filter(
    (i) => i.window_date === new Date().toISOString().slice(0, 10)
  )

  return (
    <div className="mt-8" data-testid="research-ideas-card">
      <h2 className="mb-4 flex items-center gap-2 text-lg font-semibold">
        <Telescope size={18} /> Ide Research Hari Ini
        <Badge>
          {data.count_today}/{data.max_per_day}
        </Badge>
        <Link
          href="/ideas"
          className="ml-auto text-sm font-normal text-[var(--color-fg-muted)] underline"
        >
          Lihat semua →
        </Link>
      </h2>
      <Card className="divide-y divide-[var(--color-border)]">
        {todays.length === 0 ? (
          <p className="p-4 text-sm text-[var(--color-fg-muted)]">
            Belum ada ide terkumpul hari ini. Cek{" "}
            <Link href="/settings/research-agent" className="underline">
              pengaturan Research Agent
            </Link>
            .
          </p>
        ) : (
          todays.map((idea) => (
            <details key={idea.id} className="group p-4">
              <summary className="cursor-pointer text-sm font-medium select-none">
                {idea.title}
              </summary>
              <div className="mt-2 space-y-1 text-sm">
                <p>
                  <span className="font-medium">Target:</span>{" "}
                  {idea.target_users}
                </p>
                <p>
                  <span className="font-medium">Kendala:</span> {idea.problem}
                </p>
                <p>
                  <span className="font-medium">Solusi:</span> {idea.solution}
                </p>
              </div>
            </details>
          ))
        )}
      </Card>
    </div>
  )
}
