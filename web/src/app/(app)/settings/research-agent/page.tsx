"use client"
import { useCallback, useEffect, useState } from "react"
import { Card, Input, Label, Badge } from "@/components/ui"
import { Button } from "@/components/ui/Button"
import { Loader2, Search, Play, FlaskConical } from "lucide-react"
import {
  apiGet,
  apiPatch,
  apiPost,
  ApiError,
  ResearchSettings,
  ResearchAiProvider,
  ResearchIdeasResponse,
  type ResearchIdea,
} from "@/lib/api"

export default function ResearchAgentSettings() {
  const [settings, setSettings] = useState<ResearchSettings | null>(null)
  const [providers, setProviders] = useState<ResearchAiProvider[]>([])
  const [ideas, setIdeas] = useState<ResearchIdea[]>([])
  const [keyInput, setKeyInput] = useState("")
  const [denied, setDenied] = useState(false)
  const [saved, setSaved] = useState("")
  const [saveErr, setSaveErr] = useState(false)
  const [saving, setSaving] = useState(false)
  const [testing, setTesting] = useState(false)
  const [testMsg, setTestMsg] = useState("")
  const [running, setRunning] = useState(false)
  const [runMsg, setRunMsg] = useState("")

  const load = useCallback(() => {
    Promise.all([
      apiGet<ResearchSettings>("/research/settings"),
      apiGet<ResearchAiProvider[]>("/research/ai-providers"),
      apiGet<ResearchIdeasResponse>("/research/ideas"),
    ])
      .then(([s, p, i]) => {
        setSettings(s)
        setProviders(p)
        setIdeas(i.ideas)
      })
      .catch((err) => {
        if (err instanceof ApiError && err.status === 403) setDenied(true)
        else console.error("research settings load:", err)
      })
  }, [])

  useEffect(load, [load])

  if (denied)
    return (
      <Card className="p-6 text-sm text-[var(--color-fg-muted)]">
        Halaman ini khusus admin.
      </Card>
    )
  if (!settings)
    return (
      <Card className="p-6">
        <Loader2 className="animate-spin" />
      </Card>
    )

  async function save() {
    if (!settings) return
    setSaving(true)
    setSaved("")
    setSaveErr(false)
    try {
      const body: Record<string, unknown> = {
        enabled: settings.enabled,
        search_provider: settings.search_provider,
        ai_provider_id: settings.ai_provider_id,
        max_per_day: settings.max_per_day,
      }
      if (keyInput) body.search_api_key = keyInput
      await apiPatch("/research/settings", body)
      setSaved("Tersimpan!")
      setKeyInput("")
      load()
    } catch (e) {
      setSaved(e instanceof Error ? e.message : "Gagal")
      setSaveErr(true)
    } finally {
      setSaving(false)
    }
  }

  async function testSearch() {
    setTesting(true)
    setTestMsg("")
    try {
      const r = await apiPost<{ ok: boolean; message: string }>(
        "/research/test-search",
        {}
      )
      setTestMsg(`OK — ${r.message}`)
    } catch (e) {
      setTestMsg(e instanceof Error ? e.message : "Gagal")
    } finally {
      setTesting(false)
    }
  }

  async function runNow() {
    setRunning(true)
    setRunMsg("")
    try {
      const r = await apiPost<{ status: string; created: number }>(
        "/research/run-now",
        {}
      )
      setRunMsg(`${r.status} — ${r.created} ide baru`)
      load()
    } catch (e) {
      setRunMsg(e instanceof Error ? e.message : "Gagal")
    } finally {
      setRunning(false)
    }
  }

  return (
    <div className="space-y-6" data-testid="research-agent-settings">
      <Card className="space-y-4 p-6">
        <div className="flex items-center justify-between">
          <h2 className="text-lg font-semibold">Research Agent</h2>
          <label className="flex items-center gap-2 text-sm">
            <input
              type="checkbox"
              checked={settings.enabled}
              onChange={(e) =>
                setSettings({ ...settings, enabled: e.target.checked })
              }
            />
            Aktif (scheduler hourly)
          </label>
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div>
            <Label>Search Provider</Label>
            <select
              className="mt-1 w-full rounded border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm"
              value={settings.search_provider}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  search_provider: e.target.value as "tavily" | "brave",
                })
              }
            >
              <option value="tavily">Tavily (free 1000 req/bln)</option>
              <option value="brave">Brave Search (free 2000 req/bln)</option>
            </select>
          </div>
          <div>
            <Label>
              Search API Key{" "}
              {settings.search_api_key_masked && (
                <span className="text-xs text-[var(--color-fg-muted)]">
                  ({settings.search_api_key_masked})
                </span>
              )}
            </Label>
            <Input
              type="password"
              placeholder="Kosongkan jika tidak diganti"
              value={keyInput}
              onChange={(e) => setKeyInput(e.target.value)}
            />
          </div>
          <div>
            <Label>AI Provider (untuk merangkum hasil)</Label>
            <select
              className="mt-1 w-full rounded border border-[var(--color-border)] bg-transparent px-3 py-2 text-sm"
              value={settings.ai_provider_id ?? ""}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  ai_provider_id: e.target.value
                    ? Number(e.target.value)
                    : null,
                })
              }
            >
              <option value="">— pilih provider —</option>
              {providers.map((p) => (
                <option key={p.id} value={p.id}>
                  {p.name} ({p.model})
                </option>
              ))}
            </select>
          </div>
          <div>
            <Label>Target ide per hari (1–50)</Label>
            <Input
              type="number"
              min={1}
              max={50}
              value={settings.max_per_day}
              onChange={(e) =>
                setSettings({
                  ...settings,
                  max_per_day: Number(e.target.value) || 5,
                })
              }
            />
          </div>
        </div>

        <div className="flex flex-wrap items-center gap-3">
          <Button onClick={save} disabled={saving}>
            {saving ? <Loader2 size={16} className="animate-spin" /> : null}{" "}
            Simpan
          </Button>
          <Button variant="outline" onClick={testSearch} disabled={testing}>
            <FlaskConical size={16} /> {testing ? "Mengetes…" : "Tes Search"}
          </Button>
          <Button variant="outline" onClick={runNow} disabled={running}>
            <Play size={16} /> {running ? "Berjalan…" : "Run Now"}
          </Button>
          {saved && (
            <span
              className={
                saveErr ? "text-sm text-red-500" : "text-sm text-emerald-500"
              }
            >
              {saved}
            </span>
          )}
          {testMsg && (
            <span className="text-sm text-[var(--color-fg-muted)]">
              {testMsg}
            </span>
          )}
          {runMsg && (
            <span className="text-sm text-[var(--color-fg-muted)]">
              {runMsg}
            </span>
          )}
        </div>

        {settings.last_run_status && (
          <p className="text-xs text-[var(--color-fg-muted)]">
            Run terakhir: {settings.last_run_at ?? "-"} —{" "}
            {settings.last_run_status}
          </p>
        )}
      </Card>

      <Card className="p-6">
        <h3 className="mb-3 flex items-center gap-2 text-sm font-semibold">
          <Search size={16} /> Bank Ide (30 terakhir)
        </h3>
        {ideas.length === 0 ? (
          <p className="text-sm text-[var(--color-fg-muted)]">
            Belum ada ide terkumpul.
          </p>
        ) : (
          <ul className="space-y-3">
            {ideas.map((i) => (
              <li
                key={i.id}
                className="rounded border border-[var(--color-border)] p-3"
              >
                <div className="flex items-center justify-between gap-2">
                  <strong className="text-sm">{i.title}</strong>
                  <Badge>{i.window_date}</Badge>
                </div>
                <p className="mt-1 text-xs text-[var(--color-fg-muted)]">
                  Target: {i.target_users}
                </p>
                <p className="mt-1 text-sm">
                  <span className="font-medium">Kendala:</span> {i.problem}
                </p>
                <p className="mt-1 text-sm">
                  <span className="font-medium">Solusi:</span> {i.solution}
                </p>
                {i.sources.length > 0 && (
                  <p className="mt-1 text-xs">
                    Sumber:{" "}
                    {i.sources.slice(0, 3).map((s, j) => (
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
              </li>
            ))}
          </ul>
        )}
      </Card>
    </div>
  )
}
