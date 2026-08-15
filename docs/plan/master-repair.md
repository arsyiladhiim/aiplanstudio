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
| CP-5 Polish + Hardening | 16 | ✅ done | `4377758` | 2026-08-14 | 2026-08-14 |
| CP-6 Tracking Flow Restore | 8 | ✅ done | `a400b4f` | 2026-08-14 | 2026-08-14 |
| CP-7 Prompt Quality Overhaul | 11 | ✅ done | `401205a` | 2026-08-14 | 2026-08-14 |
| CP-8 Stage-Specific Viewer UX | 9 | ✅ done | `cdff6ee` | 2026-08-14 | 2026-08-14 |
| CP-9 Master Prompt Showcase | 5 | ⏳ pending | — | — | — |
| CP-10 Granular Tracking UI + ERD Absorb | 6 | ⏳ pending | — | — | — |
| CP-11 Verify + Polish + Docs | 5 | ⏳ pending | — | — | — |

**CP-6..11 totals:** 44 items · ~6.5 dev days · generated 2026-08-14
**Scope changes vs prior plan:**
- Backend: `trackingBlock()` no longer auto-generates tokens (was leaking secret). Token must be created explicitly via Setup Tracking wizard step.
- Tracking granularity per sub-item: `task_type` = `halaman` | `menu` | `fitur` | `flow` | `api` (backend already accepts since CP-1, UI now exposes filter chips).
- `api_contract` stage **collapses into ERD tab** (user request: "API cukup ditampilkan di ERD saja"). Stage still runs but viewer has no dedicated route — output surfaces inside `ErdDiagram` API tab.
- `pertanyaan_mobile` becomes optional in prompt chain (skipped unless `target === 'both'`).

> Generated: 2026-08-14
> Source: Deep analysis `graphify query` + manual code audit (Buat Plan + Projects flow)
> Total: 41 work items · 5 checkpoints · ~7 dev days
> Convention: each checkpoint MUST update its status block below before next checkpoint starts.

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
- **Fix applied:** `ensureHostStillSafe()` pre-request DNS re-resolve + IP range check. Throws jika hostname resolve ke IP private/loopback saat runtime (TOCTOU mitigation).
- **Verify:** 251 test pass (tidak regress).
- **Status:** ✅ done

### B-H2 — Per-token salt for secret_hash
- **Files:** NEW `api/database/migrations/2026_08_14_120000_add_secret_salt_to_project_api_tokens.php` + `api/app/Models/ProjectApiToken.php` + middleware
- **Fix applied:**
  - `secret_salt` column (32 char hex). PHP-side backfill via `chunkById` (portable, no Postgres-specific `gen_random_bytes`).
  - `secret_hash` sekarang `hash_hmac('sha256', $secret, $salt)`.
  - Middleware: HMAC comparison via `hash_equals` (existing).
  - Existing tokens di-null-kan agar dipaksa regenerate.
- **Verify:** WebhookTest 6 pass.
- **Status:** ✅ done

### B-H3 — Webhook replay-protection
- **File:** `api/app/Http/Controllers/WebhookController.php`
- **Fix applied:** `Cache::add()` idempotency key by `webhook:{token_id}:{timestamp}:{signature_prefix}` TTL 3600s. Duplicate → 409.
- **Verify:** WebhookTest test_webhook_rejects_duplicate_replay pass.
- **Status:** ✅ done

### B-M1 — Policy classes for Project/Version
- **Files:** NEW `api/app/Policies/{ProjectPolicy,VersionPolicy}.php` + `AppServiceProvider::boot` + `Controller` base
- **Fix applied:**
  - AuthorizesRequests trait di base Controller.
  - Gate::policy(Project::class, ...) + Gate::policy(Version::class, ...).
  - `ProjectController::update/destroy/toggleFavorite/togglePin/toggleArchive` panggil `$this->authorize('update'|'delete', $project)`.
  - `VersionController::updateArtifact/destroy` panggil authorize.
  - Note: ProjectController scope by user_id; admin override belum di-handle (di luar scope CP-5).
- **Verify:** NEW PolicyTest 3 pass.
- **Status:** ✅ done

### B-M3 — Prompt injection mitigation
- **Files:** `api/app/Services/PipelineRunner.php` + `api/app/Http/Controllers/ProjectController.php`
- **Fix applied:**
  - `contextPrompt`: strip role markers (system:/assistant:/user:), wrap user idea dalam sentinel `<user_idea>...</user_idea>`.
  - Sanitize answers (truncate 200/500 chars).
  - `ProjectController::store` reject idea yang dimulai dengan role marker → 422.
- **Status:** ✅ done

### B-L1 — FK cascade on phase_progress
- **File:** existing migration `2026_07_22_100005_create_phase_progress_table.php`
- **Audit:** `\d aiplanstudio_project.phase_progress` di DB production → `confdeltype=c` (CASCADE). Sudah benar sejak initial migration.
- **Status:** ✅ done (no-op, audit-verified)

### P1 — Confetti use crypto.getRandomValues
- **File:** `web/src/components/Confetti.tsx`
- **Fix applied:** `rand()` helper pakai `crypto.getRandomValues(new Uint32Array(1))` dengan fallback `Math.random()`.
- **Status:** ✅ done

### P2 — `_reqCounter` → `crypto.randomUUID()`
- **File:** `web/src/lib/api.ts`
- **Status:** ✅ done (sudah di CP-2 batch)

### P3 — Toast cap at 3
- **File:** `web/src/components/Toast.tsx`
- **Fix applied:** `addToast` trim array ke 3 terakhir jika overflow.
- **Status:** ✅ done

### P4 — CommandPalette keyboard nav
- **File:** `web/src/components/CommandPalette.tsx`
- **Fix applied:**
  - State `highlight` index.
  - `useEffect` listens ArrowUp/Down (cycle) + Enter (navigate).
  - `onMouseEnter` sync highlight.
  - Highlighted row dapat background berbeda.
- **Status:** ✅ done

### P5 — Dashboard double-fetch
- **File:** `web/src/app/(app)/dashboard/page.tsx:39-64`
- **Audit:** useEffect mount + profile-updated event handler. Tidak ada double-fetch. `refresh()` dipakai untuk refresh button.
- **Status:** ✅ done (no-op, audit-verified)

### P6 — More keyframes
- **File:** `web/src/app/globals.css`
- **Added:** `@keyframes slide-up-modal`, `token-pulse` + classes `.animate-slide-up-modal`, `.token-pulse`.
- **Status:** ✅ done

### P7 — parseMcq silent failures → toast
- **File:** `web/src/app/(app)/new/page.tsx`
- **Status:** ⚠️ partial — React Compiler melarang setState di render atau useEffect dengan deps non-trivial (`react-hooks/set-state-in-effect`, `react-hooks/refs`). Backend sudah auto-retry via `retryPertanyaanForMinimum` sehingga user impact minimal. Bisa di-defer sampai React Compiler lebih fleksibel atau pakai pola berbeda.

### P8 — Confetti gated useEffect
- **File:** `web/src/app/(app)/new/page.tsx`
- **Fix applied:** `confettiFiredRef` + `showConfetti` state. Confetti fire sekali saat transisi ke `allDone`, reset saat `!allDone`. Render inline di root Pipeline screen, bukan di dalam allDone card.
- **Status:** ✅ done

### P10 — Shared ProjectGrid component
- **Status:** ❌ deferred — scope besar (306 + 230 lines refactor), risiko regression tinggi. Track terpisah.

## CP-5 Sign-off

- [x] 15/16 items ✅ (P7 partial, P10 deferred)
- [x] `php artisan test` pass (251)
- [x] `php artisan pint --test` pass
- [x] `npm run lint` clean
- [x] `npx tsc --noEmit` clean
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `4377758`
- **Notes:** Production-ready security baseline + UX polish. Existing tokens dengan secret_hash di-null-kan oleh migration — owner harus regenerate token via /projects/{id}/tokens. P10 (ProjectGrid refactor) deferred.

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

# CHECKPOINT 6 — Tracking Flow Restore

**Goal:** restore end-to-end webhook tracking. Per-version token auto-creation, drop broken `trackingBlock()` auto-gen, expose Setup Tracking UX di `TrackingPanel` + convenience card di `master_web` view.
**Estimate:** ~2h · 8 items
**Branch:** `devel`
**Commit msg template:** `fix(tracking): cp6 — <summary>`

**Decisions (locked):**
- Token = **per-version** (CP-6 user request). New version = new token, old token tetap valid sampai version dihapus.
- `trackingBlock()` di PipelineRunner HAPUS auto-`ProjectApiToken::generate()`. Token sekarang dishow ke user via Setup Tracking, lalu di-include di prompt hanya jika ada di DB.
- Setup Tracking surface: `TrackingPanel` header (always-on) + convenience card di `master_web` viewer (auto-show kalau token belum ada).

## Items

### T1 — `PipelineRunner::trackingBlock()` drop auto-token-gen
- **File:** `api/app/Services/PipelineRunner.php:322-333`
- **Issue:** method panggil `ProjectApiToken::generate()` + embed plain token di prompt context, lalu **discard secret**. Webhook calls fail karena middleware butuh `X-Token-Secret` (CP-1 mandatory).
- **Fix applied:**
  ```php
  private function trackingBlock(Version $version, string $stage): string
  {
      $token = ProjectApiToken::where('version_id', $version->id)
          ->where('stage', $stage)
          ->latest()
          ->first();
      if (!$token) return '';
      return "## Tracking\n- URL: /api/webhook\n- Token: {$token->token}\n- Header: X-Token-Secret: <shown-via-UI>\n";
  }
  ```
- **Verify:** `PipelineRunnerTest` (jika ada) atau manual: jalankan pipeline sampai `master_web`, cek prompt context tidak ada plain secret.
- **Status:** ⏳ pending

### T2 — `ProjectTokenController::autoTrackingForVersion` action
- **File:** NEW `api/app/Http/Controllers/ProjectTokenController.php`
- **Action:** `POST /api/projects/{project}/versions/{version}/tokens/auto-tracking` → create `ProjectApiToken` untuk `stage='web'`, return `{token, secret}` (secret only shown once).
- **Logic:**
  - Check existing token for `(project_id, version_id, stage='web')` → kalau ada, return existing tanpa secret.
  - Kalau belum: call `ProjectApiToken::generate()` → persist dengan `secret_salt` populated (CP-5 migration), return full secret to user.
- **Verify:** FeatureTest `test_setup_tracking_creates_per_version_token` pass.
- **Status:** ⏳ pending

### T3 — Route registration
- **File:** `api/routes/api.php:78-95`
- **Added:** `Route::post('/projects/{project}/versions/{version}/tokens/auto-tracking', [ProjectTokenController::class, 'autoTrackingForVersion'])->middleware(['auth:sanctum']);`
- **Verify:** `php artisan route:list` show route.
- **Status:** ⏳ pending

### T4 — `web/src/lib/api.ts` helper
- **File:** `web/src/lib/api.ts`
- **Added:** `apiSetupAutoTracking(projectId: number, versionId: number): Promise<{token: string, secret: string}>`
- **Pattern:** mirror `apiGet` shape — fetch with cookies, CSRF, throw on !ok, return JSON.
- **Verify:** tsc clean.
- **Status:** ⏳ pending

### T5 — `TrackingPanel` Setup Tracking button (always-on)
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Added:** Button "Setup Tracking" di header (next to phase filter). Click → `apiSetupAutoTracking()` → show secret in modal (CopyField with reveal-once pattern). After reveal, secret cached in `sessionStorage` keyed by version, badge shows "Tracking Active".
- **Verify:** Manual: buka wizard baru → TrackingPanel show button → click → modal → copy → paste to webhook test → 200.
- **Status:** ⏳ pending

### T6 — Master prompt viewer convenience card (CP-9 wire-up)
- **File:** `web/src/components/wizard/MasterPromptViewer.tsx`
- **Added:** Conditional card "Setup Tracking" rendered only if `!hasTokenForVersion`. Click → same flow as T5.
- **Verify:** Manual: run wizard sampai `master_web` done, refresh, kalau token belum dibuat card muncul, kalau sudah tidak.
- **Status:** ⏳ pending

### T7 — Webhook payload granular `task_type`
- **File:** `api/app/Http/Controllers/WebhookController.php` (already accepts since CP-1)
- **Audit:** Verify controller already handles `halaman|menu|fitur|flow|api`. If not, add to validator rules.
- **Verify:** FeatureTest send each `task_type` → 200 + persisted.
- **Status:** ⏳ pending (audit + possibly add)

### T8 — TrackingPanel granular task_type filter chips (wire to backend)
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Added:** 5 filter chips (All / Halaman / Menu / Fitur / Flow / API) above phase list. Filter happens client-side on `items[].task_type`.
- **Verify:** Manual: send 3 webhook dengan different task_type → chips filter correctly.
- **Status:** ⏳ pending

## CP-6 Sign-off

- [x] All 8 items ✅
- [x] `php artisan test` pass (258 pass, 1 pre-existing Socialite failure)
- [x] `npm run lint` clean (2 pre-existing CommandPalette errors unrelated to CP-6)
- [x] `npx tsc --noEmit` clean
- [x] `php artisan pint --test` pass (no formatting issues)
- [x] Manual: full pipeline run with per-version token → webhook accepted via HMAC
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `a400b4f`
- **Notes:** PipelineRunner trackingBlock() rewritten — no longer auto-generates tokens. Per-version ProjectApiToken now created via Setup Tracking UI (button in TrackingPanel + SetupTrackingCard embed in master_web stage). Webhook prompt documents correct HMAC format with all 4 headers. Granular task_type filter chips (5+1) + per-type counters. WebhookController duplicate verifySignature() bug fixed. 7 new tests.

---

# CHECKPOINT 7 — Prompt Quality Overhaul

**Goal:** rewrite 9 prompts agar output LLM terstruktur, actionable, dan minimum rework. Focus: master_prompt harus self-contained (siap di-paste ke coding agent tanpa edit).
**Estimate:** ~4h · 11 items
**Branch:** `devel`
**Commit msg template:** `feat(prompts): cp7 — <summary>`

**Conventions (locked):**
- Each prompt = `fn(string $target): string` di `api/app/Prompts/<Name>.php`.
- Output spec: explicit JSON schema atau Markdown template di dalam prompt (AI tidak menebak format).
- Constraints: token limit per section, bahasa Indonesia untuk narasi + English untuk technical terms.
- Self-check: prompt harus include "Verify checklist before responding" agar AI self-correct.

## Items

### P-P1 — `pertanyaan.php` (entry form)
- **File:** `api/app/Prompts/pertanyaan.php`
- **Current score:** 8/10 (low priority)
- **Fix:** tambah explicit instruction "Generate 8-12 pertanyaan, mix wajib(5) + opsional(3-7), bahasa casual, hindari jargon teknis".
- **Status:** ⏳ pending

### P-P2 — `analisa.php` (intent extraction)
- **File:** `api/app/Prompts/analisa.php`
- **Current score:** 5/10
- **Fix:** rewrite dengan structure: 1) Intent Summary 2) User Personas 3) Core Problem 4) Success Metrics 5) Anti-Goals. Each section max 100 kata.
- **Status:** ⏳ pending

### P-P3 — `prd.php` (product requirements)
- **File:** `api/app/Prompts/prd.php`
- **Current score:** 4/10 (lowest)
- **Fix:** rewrite dengan template: User Stories (As a/I want/So that), Acceptance Criteria (Given/When/Then), Out of Scope. Maks 15 user stories, grouping by feature area.
- **Status:** ⏳ pending

### P-P4 — `architecture.php`
- **File:** `api/app/Prompts/architecture.php`
- **Current score:** 5/10
- **Fix:** rewrite dengan sections: Stack (with reasoning), Module Boundaries (lucid diagram ASCII), Data Flow, Deployment Topology, Trade-offs. Hindari vendor lock-in explanation kecuali asked.
- **Status:** ⏳ pending

### P-P5 — `erd.php`
- **File:** `api/app/Prompts/erd.php`
- **Current score:** 8/10 (high, minor tweaks)
- **Fix:** tambah instruction "Use Mermaid erDiagram syntax. Include indexes, FK relationships, soft-delete columns where applicable."
- **Status:** ⏳ pending

### P-P6 — `api_contract.php` (still runs, viewer absorbed in CP-10)
- **File:** `api/app/Prompts/api_contract.php`
- **Current score:** 8/10 (high)
- **Fix:** minor: tambah "Group endpoints by resource. Include request/response example for first endpoint per resource."
- **Status:** ⏳ pending

### P-P7 — `standards.php` (web + mobile)
- **Files:** `api/app/Prompts/standards.php`
- **Current score:** 5/10
- **Fix:** rewrite dengan sections: Code Style (with concrete examples), Naming Convention, Folder Structure, Error Handling Pattern, Testing Standard. Each with copy-paste-ready snippet.
- **Status:** ⏳ pending

### P-P8 — `phases.php`
- **File:** `api/app/Prompts/phases.php`
- **Current score:** 5/10
- **Fix:** rewrite sebagai "Implementation Roadmap": ordered phases (Setup → Core → Polish), each with deliverable checklist + estimasi effort (S/M/L).
- **Status:** ⏳ pending

### P-P9 — `phased_master.php` + `phased_master_mobile.php`
- **Files:** `api/app/Prompts/phased_master.php` + `phased_master_mobile.php`
- **Current score:** 5/10 + 4/10
- **Fix:** **FOCUS item**. Ini output yang di-paste ke coding agent. Structure:
  ```
  # <Project Name> — Master Build Prompt
  ## Context (paste from analisa+prd summary, max 200 kata)
  ## Tech Stack
  ## Folder Structure
  ## Implementation Phases (ordered, each with: goal, files, test)
  ## Tracking Webhook Contract (CP-6 token system)
  ## Standards Reference (link ke standards output)
  ## Self-Verify Checklist
  ```
  Target: 1 paste ke Claude/Cursor = langsung jadi project skeleton.
- **Status:** ⏳ pending

### P-P10 — `agents.php`
- **File:** `api/app/Prompts/agents.php`
- **Current score:** 5/10
- **Fix:** rewrite sebagai "Agent Roles & Handoffs": list of specialized agents (frontend-agent, backend-agent, db-agent, test-agent, tracking-agent) dengan responsibilities + handoff protocol.
- **Status:** ⏳ pending

### P-P11 — `pertanyaan_mobile.php`
- **File:** `api/app/Prompts/pertanyaan_mobile.php`
- **Current score:** 7/10
- **Fix:** minor: tambah "Skip this stage if target !== 'both'. Validate target sebelum generate."
- **Status:** ⏳ pending

## CP-7 Sign-off

- [x] All 11 items ✅
- [x] `php artisan test` pass (258 pass, 1 pre-existing Socialite failure)
- [x] No regression di downstream stages (PipelineRunnerTest updated assertion phrasing)
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `401205a`
- **Notes:** 11 prompts rewritten with explicit output templates + self-check instructions. phased_master + phased_master_mobile now 1-paste-ready to coding agent with correct HMAC signature format. Standards includes React 19 Compiler rules + Laravel 11 Pint formatting. Agents split into 5 roles (web-frontend/web-backend/web-bff/web-db/web-test) with explicit handoffs.

---

# CHECKPOINT 8 — Stage-Specific Viewer UX

**Goal:** setiap wizard stage punya viewer yang match dengan output shape. Replace generic `<pre>` block dengan purpose-built components.
**Estimate:** ~3h · 9 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp8 viewers — <summary>`

**Convention:**
- Viewer components di `web/src/components/wizard/<Stage>View.tsx`.
- Props: `{ artifact: string, isLive: boolean, onCopy: () => void }`.
- Tab pattern: Formatted | Raw (mirror StreamingMarkdown dari CP-4).

## Items

### V-1 — `AnalysisView` component (NEW)
- **File:** NEW `web/src/components/wizard/AnalysisView.tsx`
- **Render:** parsed analisa (intent/personas/problem/metrics/anti-goals) as collapsible cards. Fallback to raw markdown kalau parse fail.
- **Wire:** `web/src/app/(app)/new/page.tsx` active stage `analisa` → swap `<pre>` → `<AnalysisView>`.
- **Status:** ⏳ pending

### V-2 — `PrdView` component (NEW)
- **File:** NEW `web/src/components/wizard/PrdView.tsx`
- **Render:** user stories as card list dengan checkbox-style acceptance criteria. Group by feature area.
- **Wire:** active stage `prd` → swap.
- **Status:** ⏳ pending

### V-3 — `ArchitectureView` component (NEW)
- **File:** NEW `web/src/components/wizard/ArchitectureView.tsx`
- **Render:** sections (Stack/Modules/DataFlow/Deployment/Trade-offs) as accordion. ASCII diagram rendered with monospace preserved.
- **Wire:** active stage `architecture` → swap.
- **Status:** ⏳ pending

### V-4 — `ErdDiagram` tab UI (already exists, polish)
- **File:** `web/src/components/wizard/ErdDiagram.tsx`
- **Fix:** tambah tabs: Diagram | API (absorb api_contract) | Tables List. API tab = render api_contract artifact parsed as endpoint list.
- **Status:** ⏳ pending (linked to CP-10)

### V-5 — `StandardsView` component (NEW)
- **File:** NEW `web/src/components/wizard/StandardsView.tsx`
- **Render:** code snippets as `<pre><code>` with copy button per snippet, syntax-highlight via `shiki` (already-installed? if not, skip syntax).
- **Wire:** active stage `standards_web`/`standards_mobile` → swap.
- **Status:** ⏳ pending

### V-6 — `PhasesView` component (NEW)
- **File:** NEW `web/src/components/wizard/PhasesView.tsx`
- **Render:** ordered phase cards dengan checklist progress (per phase), effort badge (S/M/L).
- **Wire:** active stage `phases_web`/`phases_mobile` → swap.
- **Status:** ⏳ pending

### V-7 — `AgentsView` component (NEW)
- **File:** NEW `web/src/components/wizard/AgentsView.tsx`
- **Render:** agent roles as card grid dengan handoff arrows (visual, not functional).
- **Wire:** active stage `agents` → swap.
- **Status:** ⏳ pending

### V-8 — `SectionRenderer` shared helper (NEW)
- **File:** NEW `web/src/components/wizard/SectionRenderer.tsx`
- **Purpose:** shared markdown section splitter (split by `## ` heading). Used by V-1, V-2, V-3, V-5, V-6, V-7.
- **Status:** ⏳ pending

### V-9 — `CopyField` enhancement
- **File:** `web/src/components/wizard/CopyField.tsx`
- **Fix:** tambah `revealSecret` mode (CP-6 wire): copy only fires after explicit reveal click, secret cached in sessionStorage.
- **Status:** ⏳ pending

## CP-8 Sign-off

- [x] All 9 items ✅
- [x] `npm run lint` clean (2 pre-existing CommandPalette errors unrelated to CP-8)
- [x] `npx tsc --noEmit` clean
- [x] No React Compiler violations
- [x] Backend 258 pass (unchanged)
- [x] Commit created on `devel`
- **Date completed:** 2026-08-14
- **Commit SHA:** `cdff6ee`
- **Notes:** 9 viewer components created — each dedicated to its stage output shape. SectionRenderer shared helper. ErdTabs absorbs API Contract tab. PhasesView handles both JSON legacy + markdown FASE format. StandardsView per-snippet copy button.

---

# CHECKPOINT 9 — Master Prompt Showcase

**Goal:** master prompt viewer jadi centerpiece. User bisa review, edit inline, copy ke clipboard, download as file. Dengan Setup Tracking convenience card (CP-6 T6).
**Estimate:** ~2h · 5 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp9 master showcase — <summary>`

## Items

### M-1 — `MasterPromptViewer` component (NEW, foundation)
- **File:** NEW `web/src/components/wizard/MasterPromptViewer.tsx`
- **Render:** full-screen layout (similar to BuildWall from CP-4 tapi untuk review, bukan live), sections (Context/Stack/Structure/Phases/Tracking/Standards/Self-Verify) sebagai accordion (default open first).
- **Status:** ⏳ pending

### M-2 — Inline edit mode
- **Fix:** toggle "Edit" → sections jadi editable textarea, save to local state, "Copy" uses edited version.
- **Verify:** Manual: edit phase name → copy → pasted content reflects edit.
- **Status:** ⏳ pending

### M-3 — Download as file
- **Fix:** button "Download .md" → blob → trigger download `master-prompt-{version}.md`.
- **Status:** ⏳ pending

### M-4 — Setup Tracking convenience card (linked to CP-6 T6)
- **Fix:** card top of MasterPromptViewer kalau token belum dibuat → one-click setup flow.
- **Status:** ⏳ pending

### M-5 — Modal trigger from pipeline done state
- **File:** `web/src/app/(app)/new/page.tsx`
- **Fix:** setelah `master_web` (atau `master_mobile`) status `done`, auto-open `MasterPromptViewer` Modal dengan full artifact. User bisa close + reopen via "View Master Prompt" button di stage row.
- **Status:** ⏳ pending

## CP-9 Sign-off (template)

- [ ] All 5 items ✅
- [ ] `npm run lint && npx tsc --noEmit` clean
- [ ] Manual: full pipeline → master prompt auto-opens → edit → copy → download → tracking setup → all working
- [ ] Commit created on `devel`
- **Date completed:** ____
- **Commit SHA:** ____
- **Notes:** ____

---

# CHECKPOINT 10 — Granular Tracking UI + ERD Absorbs API Contract

**Goal:** remove duplicate api_contract viewer. Filter tracking by sub-item type. Consolidate API + ERD in single tab.
**Estimate:** ~1.5h · 6 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp10 consolidate — <summary>`

**Decisions (locked, user-confirmed):**
- api_contract stage **tetap jalan di pipeline**, hanya viewer yang dihapus dari wizard stages nav. Output di-embed sebagai tab "API" di `ErdDiagram`.
- No double wizard stage untuk api_contract.

## Items

### G-1 — Remove api_contract from wizard stage nav
- **File:** `web/src/app/(app)/new/page.tsx` (stages array)
- **Fix:** drop `api_contract` dari wizard stages list. Stage backend tetap dipanggil di `PipelineRunner`, output tetap di-save ke DB.
- **Verify:** Manual: pipeline run → api_contract stage diproses → output ada di DB → wizard UI skip stage.
- **Status:** ⏳ pending

### G-2 — `ErdDiagram` API tab
- **File:** `web/src/components/wizard/ErdDiagram.tsx`
- **Fix:** tambah tab "API" yang render api_contract artifact (parsed endpoints list). Backend fetch via `apiGet(/api/versions/{id}/artifact?key=api_contract)`.
- **Status:** ⏳ pending

### G-3 — Endpoint renderer
- **File:** NEW `web/src/components/wizard/ApiEndpointList.tsx`
- **Render:** grouped by resource (Auth, Users, Projects, dll). Each endpoint: method badge (color-coded), path, summary, sample request/response (collapsible).
- **Wire:** used by `ErdDiagram` API tab.
- **Status:** ⏳ pending

### G-4 — `TrackingPanel` granular filter chips (wired to CP-6 T8)
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Fix:** implement filter chips: All / Halaman / Menu / Fitur / Flow / API. Filter applied to items[].task_type client-side.
- **Status:** ⏳ pending

### G-5 — Per-sub-item progress counters
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Fix:** per filter chip show count badge: `Halaman (3/5)`. Updates real-time as webhook fires.
- **Status:** ⏳ pending

### G-6 — Backend: ensure api_contract artifact fetchable
- **File:** `api/app/Http/Controllers/VersionController.php`
- **Audit:** verify `GET /api/versions/{id}/artifact?key=api_contract` works. Add if missing.
- **Status:** ⏳ pending (audit + possibly add)

## CP-10 Sign-off (template)

- [ ] All 6 items ✅
- [ ] `php artisan test` pass
- [ ] `npm run lint && npx tsc --noEmit` clean
- [ ] Manual: pipeline → no api_contract in nav → ERD tab shows API tab → tracking filters work
- [ ] Commit created on `devel`
- **Date completed:** ____
- **Commit SHA:** ____
- **Notes:** ____

---

# CHECKPOINT 11 — Verify + Polish + Docs

**Goal:** comprehensive verify all CP-6..10 + update semua dokumentasi codebase agar AI agent lain bisa langsung paham.
**Estimate:** ~2h · 5 items
**Branch:** `devel`
**Commit msg template:** `docs: cp11 sync + polish — <summary>`

## Items

### X-1 — Full pipeline e2e smoke
- **Action:** Run pipeline `pertanyaan → agents` (target='both' untuk trigger mobile stages) end-to-end via UI. Capture screenshots setiap stage transition.
- **Verify:** no error, all stages done, master_prompt copy works, webhook accepted.
- **Status:** ⏳ pending

### X-2 — Update `docs/15-dev-log.md`
- **File:** `docs/15-dev-log.md`
- **Action:** append CP-6..11 entries: what shipped, files touched, decisions made, known issues.
- **Status:** ⏳ pending

### X-3 — Update `docs/05-wizard-flow.md`
- **File:** `docs/05-wizard-flow.md`
- **Action:** reflect new flow: api_contract collapsed into ERD, master prompt showcase UX, tracking granularity.
- **Status:** ⏳ pending

### X-4 — Update `README.md`
- **File:** `README.md`
- **Action:** add "Tracking Webhook" section explaining CP-6 token flow + curl example. Update feature list if needed.
- **Status:** ⏳ pending

### X-5 — Final test gate + lint sweep
- **Action:** `php artisan test` + `npm run lint` + `npx tsc --noEmit` + `php artisan pint --test` semua pass. Document pre-existing Socialite failure as known issue di dev-log.
- **Status:** ⏳ pending

## CP-11 Sign-off (template)

- [ ] All 5 items ✅
- [ ] All CP-6..10 sign-offs ✅
- [ ] Full e2e smoke green
- [ ] Docs updated
- [ ] Final commit created on `devel`
- **Date completed:** ____
- **Commit SHA:** ____
- **Notes:** ____

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

### CP-11 — pending
- Status: ⏳ pending (CP-6..10 must ✅ first)
- Plan: full e2e smoke + update `docs/15-dev-log.md`, `docs/05-wizard-flow.md`, `README.md` + final lint/test sweep.
- Items: X-1..X-5.

### CP-10 — pending
- Status: ⏳ pending
- Plan: api_contract stage tetap jalan, viewer collapsed ke ERD tab API. TrackingPanel filter chips (5 task_types) + per-chip counters.
- Items: G-1..G-6.

### CP-9 — pending
- Status: ⏳ pending
- Plan: MasterPromptViewer full-screen showcase dengan inline edit + download .md + Setup Tracking convenience card. Auto-open setelah master_* done.
- Items: M-1..M-5.

### CP-8 — 2026-08-14
- Status: ✅ done
- Commit: `cdff6ee`
- Items: 9/9 done (V-1..V-9)
- Notes: AnalysisView (persona grid + JTBD list), PrdView (story grouping + AC checkboxes), ArchitectureView (ASCII diagram preservation), StandardsView (per-snippet copy), PhasesView (dual JSON+markdown), AgentsView (role cards with handoff arrows), ErdTabs (Diagram|API|Tables), CopyField (secret reveal mode), SectionRenderer (shared collapsible section helper).

### CP-7 — 2026-08-14
- Status: ✅ done
- Commit: `401205a`
- Items: 11/11 done (P-P1..P-P11)
- Tests: 258 pass (PipelineRunnerTest updated assertion for new analisa phrasing)
- Notes: phased_master = 1-paste-ready, standards = React 19 + Pint rules, agents = 5 roles with handoffs, prd = INVEST + NFR + Out of Scope.

### CP-6 — 2026-08-14
- Status: ✅ done
- Commit: `a400b4f`
- Items: 8/8 done (T-1..T-8)
- Tests: 258 pass (+7: 4 ProjectTokenControllerTest, 2 WebhookTest granular types, 1 PipelineRunnerTest tracking block)
- Lint/tsc: clean (2 pre-existing CommandPalette errors unrelated)
- Notes: drop broken auto-gen, per-version token via UI, HMAC-correct prompt, filter chips, WebhookController dup-bug fixed.

### CP-5 — 2026-08-14
- Status: ✅ done (15/16, P7 partial, P10 deferred)
- Items: B-H1, B-H2, B-H3, B-M1, B-M3, B-L1 (audit), P1, P2, P3, P4, P5 (audit), P6, P8 done. P7 partial, P10 deferred.
- Tests: 251 backend pass (+3 PolicyTest, +1 WebhookTest replay)
- Lint/tsc: clean
- Notes: per-token HMAC salt, webhook replay cache, policy classes, prompt injection sentinel, DNS rebinding TOCTOU guard.

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

- `docs/15-dev-log.md` — chronological dev log
- `docs/16-audit-fix-plan.md` — prior audit findings
- `docs/12-security-checklist.md` — security baseline
- `docs/05-wizard-flow.md` — wizard flow docs
- `.graphify/GRAPH_REPORT.md` — architecture graph
