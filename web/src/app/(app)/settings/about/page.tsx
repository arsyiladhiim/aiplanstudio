"use client"
import { useEffect, useState } from "react"
import { Card } from "@/components/ui"
import { PageHeader } from "@/components/common"
import { fetchAppVersion, type AppVersion } from "@/lib/api"
import { Loader2, Server, Cpu } from "lucide-react"

export default function AboutPage() {
  const [version, setVersion] = useState<AppVersion | null>(null)
  const [error, setError] = useState("")

  useEffect(() => {
    let cancelled = false
    fetchAppVersion()
      .then((v) => {
        if (!cancelled) setVersion(v)
      })
      .catch((err) => {
        if (!cancelled)
          setError(
            err instanceof Error ? err.message : "Gagal memuat info versi"
          )
      })
    return () => {
      cancelled = true
    }
  }, [])

  return (
    <>
      <PageHeader title="About" subtitle="Informasi versi aplikasi & stack." />

      {error && (
        <div className="mb-4 rounded-lg border border-[var(--color-danger)]/40 bg-[var(--color-danger)]/10 px-4 py-3 text-sm text-[var(--color-danger)]">
          {error}
        </div>
      )}

      {!version && !error && (
        <div className="py-12 text-center text-[var(--color-fg-muted)]">
          <Loader2 className="inline animate-spin" /> Memuat info versi...
        </div>
      )}

      {version && (
        <div className="grid gap-4 sm:grid-cols-2">
          <Card className="p-5">
            <div className="mb-3 flex items-center gap-2">
              <Server size={18} className="text-[var(--color-brand)]" />
              <h3 className="font-semibold">Backend</h3>
            </div>
            <dl className="space-y-2 text-sm">
              <Row label="Application" value={version.name} />
              <Row label="Version" value={version.version} />
            </dl>
          </Card>

          <Card className="p-5 sm:col-span-2">
            <div className="mb-3 flex items-center gap-2">
              <Cpu size={18} className="text-[var(--color-brand)]" />
              <h3 className="font-semibold">Stack</h3>
            </div>
            <div className="flex flex-wrap gap-2">
              <Badge>Next.js 16</Badge>
              <Badge>React 19</Badge>
              <Badge>Tailwind CSS v4</Badge>
              <Badge>Laravel 13</Badge>
              <Badge>PHP 8.4</Badge>
              <Badge>PostgreSQL 18</Badge>
              <Badge>Redis</Badge>
              <Badge>Docker Compose</Badge>
              <Badge>Sanctum SPA</Badge>
              <Badge>Direct Routing</Badge>
            </div>
          </Card>
        </div>
      )}
    </>
  )
}

function Row({
  label,
  value,
  mono,
  icon,
}: {
  label: string
  value: string
  mono?: boolean
  icon?: React.ReactNode
}) {
  return (
    <div className="flex items-center justify-between">
      <dt className="text-[var(--color-fg-muted)]">{label}</dt>
      <dd
        className={`flex items-center gap-1 ${mono ? "font-mono text-xs" : ""}`}
      >
        {icon}
        {value}
      </dd>
    </div>
  )
}

function Badge({ children }: { children: React.ReactNode }) {
  return (
    <span className="inline-flex items-center rounded-full border border-[var(--color-border)] bg-[var(--color-surface-2)] px-2.5 py-0.5 text-xs">
      {children}
    </span>
  )
}
