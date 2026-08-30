"use client"
import { useCallback, useMemo, useRef } from "react"
import { createSSEPost } from "@/lib/api"
import type { StageState } from "@/lib/mock"

export interface PipelineStreamHandlers {
  onStatus: (
    stage: string,
    state: StageState,
    extra?: Record<string, unknown>
  ) => void
  onToken: (stage: string, delta: string) => void
  onArtifact: (stage: string, content: string) => void
  onStageTokens: (stage: string, tokens: number) => void
  onDone: (stage: string) => void
  onFail: (stage: string | undefined, message: string) => void
  onRetryInfo?: (attempt: number, max: number) => void
  onRunningStage?: (stage: string) => void
  onDoneStage?: (stage: string) => void
}

export interface PipelineStreamApi {
  startPipeline: (versionId: number, stage?: string) => void
  abort: () => void
  cancelAll: () => void
  retryCount: () => number
}

/**
 * CP-46.D step 2 — SSE pipeline stream consumer (extract dari /new).
 * Handles: status/token/artifact/stage_tokens/done/fail events + retry 3x + abort.
 */
export function usePipelineStream(
  liteMode: boolean,
  handlers: PipelineStreamHandlers
): PipelineStreamApi {
  const abortRef = useRef<AbortController | null>(null)
  const cancelledRef = useRef(false)
  const retryCountRef = useRef(0)

  const handleSSEEvent = useCallback(
    (event: string, rawData: unknown) => {
      if (cancelledRef.current) return
      const data = rawData as Record<string, unknown>

      switch (event) {
        case "status": {
          const stage = data.stage as string | undefined
          if (!stage) break
          const state = data.state as string

          if (state === "retrying") {
            handlers.onRetryInfo?.(
              Number(data.attempt ?? 1),
              Number(data.max ?? 0)
            )
            handlers.onStatus(stage, "running")
          } else {
            handlers.onStatus(stage, state as StageState)
            if (state === "running") {
              handlers.onRunningStage?.(stage)
            }
            if (state === "done") {
              handlers.onDoneStage?.(stage)
            }
          }
          break
        }

        case "token": {
          const stage = data.stage as string | undefined
          if (stage) handlers.onToken(stage, String(data.delta ?? ""))
          break
        }

        case "artifact": {
          const stage = data.stage as string | undefined
          if (stage) handlers.onArtifact(stage, String(data.content ?? ""))
          break
        }

        case "stage_tokens": {
          const stage = data.stage as string | undefined
          const tokens = Number(data.tokens ?? 0)
          if (stage && Number.isFinite(tokens) && tokens > 0) {
            handlers.onStageTokens(stage, tokens)
          }
          break
        }

        case "done": {
          const stage = data.stage as string | undefined
          if (stage) handlers.onDone(stage)
          break
        }

        case "fail": {
          const stage = data.stage as string | undefined
          handlers.onFail(stage, String(data.message ?? "Terjadi kesalahan."))
          if (abortRef.current) {
            abortRef.current.abort()
            abortRef.current = null
          }
          break
        }
      }
    },
    [handlers]
  )

  const doStream = useCallback(
    (versionId: number, stage: string) => {
      retryCountRef.current = 0
      const attempt = (retries: number) => {
        if (abortRef.current) abortRef.current.abort()
        if (cancelledRef.current) return
        createSSEPost(
          `/generate/stream`,
          { version: versionId, stage, auto: 1, lite: liteMode ? 1 : 0 },
          handleSSEEvent,
          (err) => {
            if (
              retries < 3 &&
              !cancelledRef.current &&
              err.name !== "TooManyRequests"
            ) {
              retryCountRef.current = retries + 1
              console.warn(`SSE retry ${retries + 1}/3:`, err.message)
              setTimeout(() => attempt(retries + 1), 2000 * (retries + 1))
            } else {
              console.error("SSE error (max retries):", err)
              handlers.onStatus(stage, "error")
              handlers.onFail(
                stage,
                err.name === "TooManyRequests"
                  ? "Terlalu banyak permintaan. Tunggu ±1 menit, lalu klik Coba Lagi."
                  : `Koneksi SSE terputus setelah 3x retry. (${err.message})`
              )
            }
          }
        ).then((ctrl) => {
          abortRef.current = ctrl
        })
      }
      attempt(0)
    },
    [handleSSEEvent, liteMode, handlers]
  )

  const startPipeline = useCallback(
    (versionId: number, stage?: string) => {
      if (abortRef.current) abortRef.current.abort()
      cancelledRef.current = false
      doStream(versionId, stage || "")
    },
    [doStream]
  )

  const abort = useCallback(() => {
    if (abortRef.current) abortRef.current.abort()
    abortRef.current = null
  }, [])

  const cancelAll = useCallback(() => {
    cancelledRef.current = true
    abort()
  }, [abort])

  const retryCount = useCallback(() => retryCountRef.current, [])

  return useMemo(
    () => ({ startPipeline, abort, cancelAll, retryCount }),
    [startPipeline, abort, cancelAll, retryCount]
  )
}
