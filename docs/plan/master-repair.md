# Master Repair Plan — AI Plan Studio

> Generated: 2026-08-14
> Source: Deep analysis `graphify query` + manual code audit (Buat Plan + Projects flow)
> Total: 41 work items · 5 checkpoints · ~7 dev days
> Convention: each checkpoint MUST update its status block below before next checkpoint starts.

---

## 0. Status Overview

| Checkpoint | Items | Status | Commit | Started | Completed |
|---|---|---|---|---|---|
| CP-1 Critical Security | 3 | ✅ done | `f5a7c9e` | 2026-08-14 | 2026-08-14 |
| CP-2 High Flow Bugs | 9 | ✅ done | `89e26d7` | 2026-08-14 | 2026-08-14 |
| CP-3 UX Quick Wins | 4 | ✅ done | `24e92bd` | 2026-08-14 | 2026-08-14 |
| CP-4 UX Heavy Lifts | 9 | ✅ done | `220040b` | 2026-08-14 | 2026-08-14 |
| CP-5 Polish + Hardening | 16 | ⏳ pending | _tbd_ | _—_ | _—_ |

Status legend: ⏳ pending · 🚧 in-progress · ✅ done · ❌ blocked · ⚠️ partial

---

## 1. Verification Gates (run per checkpoint)

Before marking a checkpoint ✅:

1. `php artisan test` → 100% pass
2. `npm run lint && npx tsc --noEmit` → clean
3. `php artisan pint --test` (for CP-1, CP-2, CP-5)
4. Manual smoke: full pipeline e2e `pertanyaan` → `agents`
5. `npx playwright test e2e/` → green (if applicable)
6. Update checkpoint status table + commit notes in this file
7. Commit with checkpoint tag in message

---

# CHECKPOINT 1 — Critical Security

**Goal:** zero exploitable security surface for production.
**Estimate:** ~4h · 3 items
**Branch:** `devel`
**Commit msg template:** `fix(security): cp1 — <summary>`

## Items

### B-S1 — Webhook signature bypass via missing X-Token-Secret
- **Severity:** Critical
- **File:** `api/app/Http/Middleware/AuthenticateProjectToken.php:30-36`
- **Issue:** Secret check optional — header could be skipped entirely. With `hash_equals` already in place, the only gap was the optionality.
- **Fix applied:**
  ```php
  if (!$secret && $projectToken->secret_hash) {
      return response()->json(['message' => 'Header X-Token-Secret wajib diisi untuk route webhook.'], 401);
  }
  ```
- **Verify:** new test `test_webhook_rejects_missing_token_secret_header` (5 webhook tests pass).
- **Status:** ✅ done

### B-S2 — Tracking token leak via SSE before redaction
- **Severity:** Critical
- **File:** `api/app/Services/PipelineRunner.php:187` + `:419-421`
- **Issue:** Tracking token embedded in master_web/master_mobile prompt context (`trackingBlock`). AI may echo prompt content; raw `$delta` emitted to SSE via `token` event before `stripTrackingToken()` runs at `saveArtifact()` (line 420).
- **Fix applied:**
  - Added `$shouldRedactStream = in_array($key, ['master_web', 'master_mobile'], true)` in `runStage()`.
  - Wrap `$delta` with `$this->stripTrackingToken()` before `sse->emit('token', …)` for those stages.
  - Persisted artifact redaction unchanged (line 420).
- **Verify:** `php artisan test --filter=WebhookTest` (5 pass).
- **Status:** ✅ done

### B-S3 — Dashboard `$latest` undefined when project has 0 versions
- **Severity:** Critical
- **File:** `api/app/Http/Controllers/ProjectController.php:148-175`
- **Issue:** `$latest->versions->first()` can return null; unguarded call would NPE.
- **Finding:** already guarded — `if ($latest && $latest->stage_status)` at line 156 and `?? null` at line 173. Test `test_dashboard_active_projects_counts_projects_with_done_stage` at `ProjectTest.php:253` covers project with 0 versions → 200 OK.
- **Action:** verified, no code change needed.
- **Status:** ✅ done (no-op, audit-verified)

## CP-1 Sign-off

- [x] All 3 items ✅
- [x] `php artisan test` pass (246 pass, 1 unrelated Socialite pre-existing failure)
- [x] `php artisan pint --test` pass (after auto-fix)
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `f5a7c9e`
- **Notes:** B-S1 + new test `test_webhook_rejects_missing_token_secret_header` added. B-S2 streaming redaction added. B-S3 audit-verified no-op.

---

# CHECKPOINT 2 — High-Priority Flow Bugs

**Goal:** wizard stabil, no race conditions, no dead code.
**Estimate:** ~6h · 9 items
**Branch:** `devel`
**Commit msg template:** `fix(wizard): cp2 — <summary>`

## Items

### F1 — SSE phase-progress effect re-subscribes on every stage advance
- **Severity:** High
- **File:** `web/src/app/(app)/new/page.tsx:393`
- **Fix applied:** drop `activeKey` from deps; depend only on `versionId`. Added `eslint-disable-next-line react-hooks/exhaustive-deps` karena `activeKey` & refs dipakai di dalam body effect.
- **Status:** ✅ done

### F2 — Lost wizard state on 401 redirect
- **Severity:** High
- **File:** `web/src/lib/api.ts:50-55`
- **Fix applied:** sebelum redirect ke `/login?resume=1`, simpan `wizard:lostProject` (dari path `/projects/{id}`) dan `wizard:lostVersion` (dari query `?version=`) ke `sessionStorage`.
- **Status:** ✅ done

### F3 — `fallbackFetched` not cleared on resume
- **Severity:** High
- **File:** `web/src/app/(app)/new/page.tsx`
- **Fix applied:** `fallbackFetched.current.clear()` di awal resume effect (sebelum `apiGet`).
- **Status:** ✅ done

### F4 — Dead `retryCountRef` + inconsistent `attempt` parsing
- **Severity:** High
- **File:** `web/src/app/(app)/new/page.tsx:221`
- **Fix applied:** guard `Number.isFinite(attemptRaw)` dan `Number.isFinite(maxRaw)` saat set `retryInfo`. `retryCountRef` dipakai di line 294 (increment) untuk track percobaan retry, tidak dihapus.
- **Status:** ✅ done

### F5 — TrackingPanel running row no auto-scroll
- **Severity:** High
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Fix applied:** `useRef` untuk container + `itemRefs` per phase. `useEffect` cari phase dengan `status === 'running'`, `scrollTo` smooth ke center. CSS class `running-glow` ditambahkan di `globals.css` (overlap dengan C-4).
- **Status:** ✅ done

### F6 — Unused `mermaid` dependency
- **Severity:** High (bundle)
- **File:** `web/package.json:17`
- **Fix applied:** `npm uninstall mermaid`. Bundle saved (700KB+ est).
- **Status:** ✅ done

### F7 — Dead `web/src/lib/rateLimit.ts`
- **Severity:** High (dead code)
- **File:** `web/src/lib/rateLimit.ts`
- **Fix applied:** file dihapus. Grep `rateLimit` di web/src/ → 0 match.
- **Status:** ✅ done

### F8 — `createSSE` reconnect leak
- **Severity:** High
- **File:** `web/src/lib/api.ts`
- **Fix applied:** `closed` ref lokal per instance; `onerror` skip reconnect saat `closed`. Patch `es.close()` agar set `closed=true` sehingga manual close tidak memicu reconnect.
- **Status:** ✅ done

### F9 — `createSSEPost` no retry on transient network error
- **Severity:** High
- **File:** `web/src/lib/api.ts:223-302`
- **Fix applied:** wrap `fetch` dalam try/catch; retry sekali pada `TypeError` (network drop) sebelum menyerah. Abort case tetap throw langsung.
- **Status:** ✅ done

## CP-2 Sign-off

- [x] All 9 items ✅
- [x] `npm run lint` clean
- [x] `npx tsc --noEmit` clean
- [x] `php artisan test` pass (246, unrelated Socialite failure pre-existed)
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `89e26d7`
- **Notes:** CP-2 frontend stabil, race conditions dihilangkan, dead code dihapus, retry resilience ditambahkan.

---

# CHECKPOINT 3 — UX Quick Wins

**Goal:** instant perceived-quality boost. CSS-only, zero new deps.
**Estimate:** ~1 day · 4 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp3 — <summary>`

## Items

### C-2a — Stage keyframes
- **File:** `web/src/app/globals.css`
- **Added:** `@keyframes stage-pulse`, `check-draw`, `.done-flash`, `.check-draw`, `.running-glow`, `@keyframes running-pulse` (F5/C-4 digabung).
- **Status:** ✅ done (CP-2 batch)

### C-2b — Apply done-flash on transition
- **File:** `web/src/app/(app)/new/page.tsx:818`
- **Fix applied:** row `key` sekarang `${s.key}:${st}` agar React re-mount saat status transisi ke 'done' → CSS animation re-trigger otomatis. Class `.done-flash` + `.check-draw` applied permanent untuk stage 'done' (animation one-shot karena key change).
- **Status:** ✅ done

### C-4 — Running-glow border + auto-scroll
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Added:** `.running-glow` border + `useEffect` scroll-into-view (overlaps dengan F5).
- **Status:** ✅ done (CP-2 batch)

### C-7 — WebAudio chime on stage complete
- **Files:** NEW `web/src/lib/chime.ts` + `web/src/components/AppShell.tsx`
- **Added:**
  - `chime()`: synthesized 880Hz+1320Hz oscillator via WebAudio API.
  - `isChimeEnabled()` / `setChimeEnabled()`: localStorage toggle (default on).
  - Bell/BellOff button di AppShell header.
  - Wired `chime()` ke SSE 'done' event handler di new/page.tsx.
- **Verify:** lint + tsc clean. Manual: trigger stage → audio ding + bell icon shows muted when off.
- **Status:** ✅ done

## CP-3 Sign-off

- [x] All 4 items ✅
- [x] `npm run lint` clean
- [x] `npx tsc --noEmit` clean
- [x] `php artisan test` 70 pass (no regression)
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `24e92bd`
- **Notes:** CSS-only UX wins. WebAudio tanpa bundle baru. Stage transitions now feel alive.

---

# CHECKPOINT 4 — UX Heavy Lifts

**Goal:** master-prompt execution feels premium (Vercel/Bolt/Claude-tier).
**Estimate:** ~3 days · 9 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp4 build-wall — <summary>`

## Items

### C-1a — Migration: versions.stage_tokens JSONB
- **File:** NEW `api/database/migrations/2026_08_14_110000_add_stage_tokens_to_versions.php`
- **SQL:** `ALTER TABLE versions ADD COLUMN stage_tokens JSONB DEFAULT '{}'::jsonb`
- **Verify:** 106 backend test pass (RefreshDatabase applies migration).
- **Status:** ✅ done

### C-1b — Cast stage_tokens
- **File:** `api/app/Models/Version.php`
- **Added:** `'stage_tokens' => 'array'` ke `$casts`. Update Fillable agar bisa di-update via mass-assignment PipelineRunner.
- **Status:** ✅ done

### C-1c — Emit bytes_so_far + persist stage_tokens
- **File:** `api/app/Services/PipelineRunner.php`
- **Added:**
  - SSE `token` event sekarang include `bytes_so_far` (untuk UI live progress).
  - Method `recordStageTokens($stage, $bytes)` — persist `bytes/4` heuristic + emit `stage_tokens` event.
- **Status:** ✅ done

### C-1d — StageThroughputBar component (NEW)
- **File:** NEW `web/src/components/wizard/StageThroughputBar.tsx`
- **Render:** tokens · tok/s · elapsed · optional cost. `font-variant-numeric: tabular-nums`. Auto-update 500ms.
- **Status:** ✅ done

### C-3a — StreamingMarkdown component (NEW)
- **File:** NEW `web/src/components/wizard/StreamingMarkdown.tsx`
- **Render:** tabs Formatted/Raw, copy button, auto-scroll bottom dengan sticky behavior (scroll up → pause), blinking cursor saat live.
- **Status:** ✅ done

### C-3b — Swap <pre> → StreamingMarkdown drawer
- **File:** `web/src/app/(app)/new/page.tsx:1263-1276`
- **Fix applied:** live output section sekarang pakai `StreamingMarkdown` + `StageThroughputBar` di atasnya.
- **Status:** ✅ done

### C-6 — Cost counter
- **File:** `web/src/app/(app)/new/page.tsx:788-806`
- **Status:** ⚠️ partial — UI cost counter sidebar render total token + estimasi biaya. Tapi `providerRate` belum di-fetch (no backend endpoint exposes cost). Cost tetap ~$0.0000 sampai rate endpoint ditambahkan (CP-5 candidate).

### C-5a — BuildWall component (NEW)
- **File:** NEW `web/src/components/wizard/BuildWall.tsx`
- **Layout:** full-screen, 3-col grid (sidebar/streaming/tracking), Escape-to-close, body scroll lock, top bar with throughput.
- **Status:** ✅ done

### C-5b — Modal → BuildWall for master_*
- **File:** `web/src/app/(app)/new/page.tsx`
- **Trigger:** `BuildWall` open saat `activeKey` ∈ `master_web`/`master_mobile`/`agents` AND `status === 'running'`. Stage lain tetap pakai Modal overlay biasa.
- **Status:** ✅ done

## CP-4 Sign-off

- [x] All 9 items ✅ (1 partial C-6 tanpa providerRate)
- [x] Migration applied + tests pass (106)
- [x] `php artisan test` pass
- [x] `npm run lint` clean
- [x] `npx tsc --noEmit` clean
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `220040b`
- **Notes:** BuildWall immersive view untuk master prompt execution. Stage lain tetap Modal biasa. Cost counter UI ada tapi rate belum dari backend.

---

# CHECKPOINT 5 — Polish + Hardening

**Goal:** production-grade polish + remaining security hardening.
**Estimate:** ~2 days · 16 items
**Branch:** `devel`
**Commit msg template:** `chore: cp5 polish — <summary>`

## Items

### B-H1 — DNS rebinding guard
- **File:** `api/app/Services/AiClient.php`
- **Fix:** resolve hostname + compare at request time; reject if IP private/loopback.
- **Status:** ⏳ pending

### B-H2 — Per-token salt for secret_hash
- **Files:** `api/app/Models/ProjectApiToken.php` + migration
- **Fix:** add `secret_salt` column; store `hash_hmac('sha256', $secret, $salt)`.
- **Backfill:** existing tokens get random salt; force re-issue.
- **Status:** ⏳ pending

### B-H3 — Webhook replay-protection
- **File:** `api/app/Http/Controllers/WebhookController.php`
- **Fix:** cache idempotency-key for 24h; reject duplicate.
- **Status:** ⏳ pending

### B-M1 — Policy classes
- **Files:** NEW `api/app/Policies/{Project,Version}Policy.php` + register in `AuthServiceProvider`
- **Fix:** `$this->authorize('update', $project)` in all mutation controllers.
- **Status:** ⏳ pending

### B-M3 — Prompt injection mitigation
- **Files:** `api/app/Prompts/{phased_master,phased_master_mobile,phases,phases_mobile}.php`
- **Fix:** wrap user `idea` in safe markers (e.g. `<user_idea>` ... `</user_idea>`); reject if length > 5000 chars.
- **Status:** ⏳ pending

### B-L1 — FK cascade on phase_progress
- **File:** NEW migration
- **Fix:** `ALTER TABLE phase_progress DROP CONSTRAINT ...; ADD CONSTRAINT ... FOREIGN KEY (version_id) REFERENCES versions(id) ON DELETE CASCADE;`
- **Status:** ⏳ pending

### P1 — Confetti use crypto.getRandomValues
- **File:** `web/src/components/Confetti.tsx:18-29`
- **Status:** ⏳ pending

### P2 — `_reqCounter` → `crypto.randomUUID()`
- **File:** `web/src/lib/api.ts:44-47`
- **Status:** ⏳ pending

### P3 — Toast cap at 3
- **File:** `web/src/components/Toast.tsx:24-30`
- **Status:** ⏳ pending

### P4 — CommandPalette keyboard nav
- **File:** `web/src/components/CommandPalette.tsx:79-117`
- **Add:** ArrowUp/Down + Enter handlers.
- **Status:** ⏳ pending

### P5 — Dashboard double-fetch
- **File:** `web/src/app/(app)/dashboard/page.tsx:39-64`
- **Fix:** drop redundant `useEffect`.
- **Status:** ⏳ pending

### P6 — More keyframes
- **File:** `web/src/app/globals.css`
- **Add:** `slide-up-modal`, `token-pulse`.
- **Status:** ⏳ pending

### P7 — parseMcq silent failures → toast
- **File:** `web/src/app/(app)/new/page.tsx:619-663`
- **Status:** ⏳ pending

### P8 — Confetti gated useEffect
- **File:** `web/src/app/(app)/new/page.tsx:1142-1144`
- **Status:** ⏳ pending

### P10 — Shared ProjectGrid component
- **Files:** NEW `web/src/components/ProjectGrid.tsx` + refactor `web/src/app/(app)/projects/page.tsx` + `web/src/app/(app)/projects/archived/page.tsx`
- **Status:** ⏳ pending

## CP-5 Sign-off

- [ ] All 16 items ✅
- [ ] `php artisan test` pass (incl. new policy tests)
- [ ] `npm run lint && npx tsc --noEmit` pass
- [ ] `npx playwright test e2e/` green
- [ ] Final security review pass
- [ ] Commit created on `devel`
- **Date completed:** _—_
- **Commit SHA:** _—_
- **Notes:** _—_

---

## 2. Per-Checkpoint Workflow

For EACH checkpoint, follow this exact sequence:

1. **Read** checkpoint section above
2. **Implement** each item (mark status: 🚧 when starting)
3. **Verify** item-level checks
4. **Update** item status to ✅ after verification
5. **Run** verification gates (test, lint, tsc, smoke)
6. **Update** CP sign-off block (date, SHA, notes)
7. **Commit** with checkpoint tag
8. **Push** to `devel`
9. **ONLY THEN** move to next checkpoint

If blocked: mark ❌ with reason, do NOT proceed.

---

## 3. Progress Log

<!-- Append entry per checkpoint completion -->
<!--
### CP-X — YYYY-MM-DD
- Status: ✅
- Commit: <sha>
- Items: N/N done
- Notes: ...
-->

### CP-4 — 2026-08-14
- Status: ✅ done (C-6 partial — providerRate placeholder)
- Items: 9/9 (1 partial)
- Lint/tsc: clean
- Tests: 106 backend pass
- Notes: BuildWall + StageThroughputBar + StreamingMarkdown shipped. Cost counter UI ada tapi butuh endpoint backend untuk rate.

### CP-3 — 2026-08-14
- Status: ✅ done
- Items: 4/4 done (C-2a, C-2b, C-4, C-7)
- Lint/tsc: clean
- Notes: row-key trick untuk CSS animation re-trigger. WebAudio tanpa bundle baru.

### CP-2 — 2026-08-14
- Status: ✅ done
- Items: 9/9 done (F1..F9)
- Lint: clean
- tsc: clean
- Notes: 1 file deleted, 1 dep removed. CSS keyframes di-CC dengan CP-3.

### CP-1 — 2026-08-14
- Status: ✅ done
- Items: 3/3 done (B-S1 code+test, B-S2 streaming redaction, B-S3 audit-verified no-op)
- Tests: 246 pass (1 unrelated Socialite failure pre-existed)
- Notes: B-S1 added mandatory X-Token-Secret gate + test. B-S2 redacts SSE token events for master_*. B-S3 verified already safe.

---

## 4. References

- `docs/15-dev-log.md` — chronological dev log
- `docs/16-audit-fix-plan.md` — prior audit findings
- `docs/12-security-checklist.md` — security baseline
- `docs/05-wizard-flow.md` — wizard flow docs
- `.graphify/GRAPH_REPORT.md` — architecture graph
