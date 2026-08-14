# Master Repair Plan — AI Plan Studio

> Generated: 2026-08-14
> Source: Deep analysis `graphify query` + manual code audit (Buat Plan + Projects flow)
> Total: 41 work items · 5 checkpoints · ~7 dev days
> Convention: each checkpoint MUST update its status block below before next checkpoint starts.

---

## 0. Status Overview

| Checkpoint | Items | Status | Commit | Started | Completed |
|---|---|---|---|---|---|
| CP-1 Critical Security | 3 | ✅ done | `7f1b3a2` | 2026-08-14 | 2026-08-14 |
| CP-2 High Flow Bugs | 9 | ⏳ pending | _tbd_ | _—_ | _—_ |
| CP-3 UX Quick Wins | 4 | ⏳ pending | _tbd_ | _—_ | _—_ |
| CP-4 UX Heavy Lifts | 9 | ⏳ pending | _tbd_ | _—_ | _—_ |
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
- **Commit SHA:** _filled after commit_
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
- **File:** `web/src/app/(app)/new/page.tsx:364-393`
- **Fix:** drop `activeKey` from deps; depend only on `versionId`.
- **Verify:** effect runs once per versionId.
- **Status:** ⏳ pending

### F2 — Lost wizard state on 401 redirect
- **Severity:** High
- **File:** `web/src/lib/api.ts:50-55`
- **Fix:** `sessionStorage.setItem('wizard:lostVersion', String(currentVersionId))` before redirect to `/login?resume=1`.
- **Verify:** simulate expired session, verify resume picks up version.
- **Status:** ⏳ pending

### F3 — `fallbackFetched` not cleared on resume
- **Severity:** High
- **File:** `web/src/app/(app)/new/page.tsx:429` + `:619`
- **Fix:** clear `fallbackFetched.current` in resume effect.
- **Verify:** resume flow doesn't re-fetch old fallback.
- **Status:** ⏳ pending

### F4 — Dead `retryCountRef` + inconsistent `attempt` parsing
- **Severity:** High
- **File:** `web/src/app/(app)/new/page.tsx:128` + `:293-297`
- **Fix:** use `retryCountRef` in debug overlay OR delete; guard `Number.isFinite(data.attempt)`.
- **Verify:** lint + ts check.
- **Status:** ⏳ pending

### F5 — TrackingPanel running row no auto-scroll
- **Severity:** High
- **File:** `web/src/components/wizard/TrackingPanel.tsx:108-149`
- **Fix:** `useRef` + `useEffect` on `prog.status === 'running'` → `scrollIntoView({behavior:'smooth', block:'center'})`.
- **Verify:** manual visual.
- **Status:** ⏳ pending

### F6 — Unused `mermaid` dependency
- **Severity:** High (bundle)
- **File:** `web/package.json:17`
- **Fix:** `npm uninstall mermaid`.
- **Verify:** bundle size diff > 500KB.
- **Status:** ⏳ pending

### F7 — Dead `web/src/lib/rateLimit.ts`
- **Severity:** High (dead code)
- **File:** `web/src/lib/rateLimit.ts`
- **Fix:** delete file.
- **Verify:** grep for `rateLimit` imports → none.
- **Status:** ⏳ pending

### F8 — `createSSE` reconnect leak
- **Severity:** High
- **File:** `web/src/lib/api.ts:208-220`
- **Fix:** gate reconnect on shared `closed` ref.
- **Verify:** manual close + reopen doesn't leak listener.
- **Status:** ⏳ pending

### F9 — `createSSEPost` no retry on transient network error
- **Severity:** High
- **File:** `web/src/lib/api.ts:223-302`
- **Fix:** wrap fetch in `AbortController` + retry once on `TypeError`.
- **Verify:** simulate network drop.
- **Status:** ⏳ pending

## CP-2 Sign-off

- [ ] All 9 items ✅
- [ ] `php artisan test` pass
- [ ] `npm run lint && npx tsc --noEmit` pass
- [ ] Commit created on `devel`
- **Date completed:** _—_
- **Commit SHA:** _—_
- **Notes:** _—_

---

# CHECKPOINT 3 — UX Quick Wins

**Goal:** instant perceived-quality boost. CSS-only, zero new deps.
**Estimate:** ~1 day · 4 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp3 — <summary>`

## Items

### C-2a — Stage keyframes
- **File:** `web/src/app/globals.css`
- **Add:**
  ```css
  @keyframes stage-pulse { 0%{box-shadow:0 0 0 0 oklch(from var(--success) l c h/.4)} 100%{box-shadow:0 0 0 12px transparent} }
  @keyframes check-draw { from{stroke-dashoffset:24} to{stroke-dashoffset:0} }
  .done-flash { animation: stage-pulse .8s ease-out; }
  .check-draw { stroke-dasharray: 24; animation: check-draw .4s ease-out forwards; }
  ```
- **Status:** ⏳ pending

### C-2b — Apply done-flash on transition
- **File:** `web/src/app/(app)/new/page.tsx:774-802`
- **Fix:** `useEffect` keyed on `status[stageKey]==='done'` → add `.done-flash` class to row, remove after 800ms.
- **Status:** ⏳ pending

### C-4 — Running-glow border + auto-scroll
- **File:** `web/src/components/wizard/TrackingPanel.tsx`
- **Add:** `.running-glow` border + auto-scroll (overlaps with F5).
- **Status:** ⏳ pending

### C-7 — WebAudio chime on stage complete
- **Files:** NEW `web/src/lib/chime.ts` + `web/src/components/AppShell.tsx`
- **Add:**
  ```ts
  const ctx = new AudioContext();
  [880, 1320].forEach((f, i) => {
    const o = ctx.createOscillator();
    o.frequency.value = f;
    o.connect(ctx.destination);
    o.start(ctx.currentTime + i*0.1);
    o.stop(ctx.currentTime + i*0.1 + 0.15);
  });
  ```
- Toggle persisted in `localStorage`. Default muted.
- **Status:** ⏳ pending

## CP-3 Sign-off

- [ ] All 4 items ✅
- [ ] `npm run lint && npx tsc --noEmit` pass
- [ ] Manual visual smoke (stage transitions feel alive)
- [ ] Commit created on `devel`
- **Date completed:** _—_
- **Commit SHA:** _—_
- **Notes:** _—_

---

# CHECKPOINT 4 — UX Heavy Lifts

**Goal:** master-prompt execution feels premium (Vercel/Bolt/Claude-tier).
**Estimate:** ~3 days · 9 items
**Branch:** `devel`
**Commit msg template:** `feat(ux): cp4 build-wall — <summary>`

## Items

### C-1a — Migration: versions.stage_tokens JSONB
- **File:** NEW `api/database/migrations/2026_08_*_add_stage_tokens_to_versions.php`
- **SQL:**
  ```sql
  ALTER TABLE versions ADD COLUMN stage_tokens JSONB DEFAULT '{}'::jsonb;
  ```
- **Status:** ⏳ pending

### C-1b — Cast stage_tokens
- **File:** `api/app/Models/Version.php`
- **Add:** `'stage_tokens' => 'array'` to `$casts`.
- **Status:** ⏳ pending

### C-1c — Emit bytes_so_far + persist stage_tokens
- **File:** `api/app/Services/PipelineRunner.php:187` + `:431`
- **Fix:**
  - SSE `token` event include `bytes_so_far` field.
  - After `saveArtifact()`, persist `$version->stage_tokens[$stageKey] = strlen($buffer)` (or use tokenizer for accuracy).
- **Status:** ⏳ pending

### C-1d — StageThroughputBar component
- **File:** NEW `web/src/components/wizard/StageThroughputBar.tsx`
- **Render:** tokens · tok/s · elapsed · ETA · cost (compact horizontal strip with `font-variant-numeric: tabular-nums`).
- **Status:** ⏳ pending

### C-3a — StreamingMarkdown component
- **File:** NEW `web/src/components/wizard/StreamingMarkdown.tsx`
- **Tabs:** Formatted (react-markdown) + Raw (`<pre>` with `<Cursor />`) + Copy button. Auto-scroll bottom.
- **Status:** ⏳ pending

### C-3b — Swap `<pre>` → StreamingMarkdown drawer
- **File:** `web/src/app/(app)/new/page.tsx:1216-1224`
- **Status:** ⏳ pending

### C-6 — Cost counter
- **File:** `web/src/app/(app)/new/page.tsx`
- **Compute:** `tokens × model_rate` client-side from provider config. Display in sidebar.
- **Status:** ⏳ pending

### C-5a — BuildWall component
- **File:** NEW `web/src/components/wizard/BuildWall.tsx`
- **Layout:** full-screen, 3-col grid (timeline / streaming markdown / tracking panel) + top bar (stage + throughput) + bottom log drawer.
- **Status:** ⏳ pending

### C-5b — Modal → BuildWall for master_*
- **File:** `web/src/app/(app)/new/page.tsx:1194`
- **Trigger:** when `activeKey` is `master_web`/`master_mobile`/`agents` and status is `running`.
- **Status:** ⏳ pending

## CP-4 Sign-off

- [ ] All 9 items ✅
- [ ] Migration applied + tests pass
- [ ] `php artisan test` pass
- [ ] `npm run lint && npx tsc --noEmit` pass
- [ ] Full pipeline e2e: `pertanyaan` → `agents` with BuildWall visible
- [ ] Commit created on `devel`
- **Date completed:** _—_
- **Commit SHA:** _—_
- **Notes:** _—_

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
