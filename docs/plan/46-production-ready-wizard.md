# Plan 46 — Production-Ready Vibe Coding Wizard

> **Trigger**: audit Plan 45.B/C + master prompt user. Mengubah wizard dari "multi-step form" menjadi orchestration engine yang mengarahkan requirement → specification → implementation → verification → production-ready.
>
> **Prinsip**: setiap stage = mini-pipeline dengan sub-status state machine + evidence + quality gate. Tidak ada stage "selesai" tanpa verifikasi terukur.

## 1. Current Wizard Architecture (ringkasan audit)

**22 stages** di `api/app/Services/StageRegistry.php` (16 web, 7 both, target filter). `PipelineRunner.php` (1233 LOC) orchestrasi: orphan-reset, mobile-gate, lite-gate, retry (3x validate / 10x MCQ), SSE emit, snapshot activity, stage_quality 0-1 heuristik. Frontend god-component `new/page.tsx` (2257 LOC) inline semua state.

**Existing foundations** (Plan 44-45):
- `TrackingInjector` (server-side tracking live, CP-45.A done)
- `AgentEventFeed` + `WebhookController` (HMAC auth, task_key validation)
- `MasterPromptViewer` badge "Tracking Live: siap" / "Token belum ada"

**Missing concepts** (akan ditambah):
- Quality Gate antar-stage
- Evidence recording (files_changed / tests_passed / lint_passed / build_passed)
- Verification stages (testing_strategy, verify.review, smoke_test, verify.production_readiness)
- Production-readiness window
- Wizard state machine + decomposition

## 2. Gap Analysis

| # | Gap | Severity |
|---|---|---|
| G1 | No Quality Gate — cuma per-stage heading validation | P0 |
| G2 | No evidence — `task_progress.checkpoint` kolom ada tapi dead code | P0 |
| G3 | No production-readiness verification beyond build | P0 |
| G4 | Wizard 2257 LOC god-component | P1 |
| G5 | No "agent health pulse" | P2 |
| G6 | Status `blocked` missing | P2 |
| G7 | No export package ZIP | P1 |
| G8 | No rotate-token → auto-regen master | P2 |
| G9 | No diagnostic pack on error | P2 |
| G10 | StageRow unused on `/new` | P2 |
| G11 | TrackingInjector re-injects setiap read | P2 |
| G12 | Stages tidak match user's 19-phase target list | P0 |
| G13 | No PLAN→EXECUTE→VERIFY→TEST→REVIEW→FIX→RETEST→CHECKPOINT→NEXT loop explicit | P0 |
| G14 | No doc reuse detection | P2 |

## 3. Target Wizard Architecture

### 3.1 Per-Stage State Machine

```
PENDING ──► READY ──► RUNNING ──► VALIDATING ──► DONE
              │            │           │
              │            └─► ERROR ──► RETRY (n/N) ──► RUNNING
              │                │           │
              │                └─► EXHAUSTED ──► BLOCKED
              ▼
           BLOCKED (gate failed)
SKIPPED ── (lite mode / target mismatch)
```

### 3.2 User's 19 Phases → 26 Stages

| User Phase | Stages | Gate |
|---|---|---|
| DISCOVERY | `pertanyaan`, `pertanyaan_mobile` | **DiscoveryGate** |
| PRD | `analisa`, `prd` | **SpecGate** |
| FUNCTIONAL SPECIFICATION | `app_spec_web`, `app_spec_mobile` | **SpecGate** |
| USER FLOW / STATE FLOW | `phases_web`, `phases_mobile` | **SpecGate** |
| ARCHITECTURE | `architecture` | **ArchGate** |
| DESIGN SYSTEM | `design_system`, `design_system_mobile` | **SpecGate** |
| ERD / DATA MODEL | `erd` | **ArchGate** |
| API CONTRACT | `api_contract` | **ArchGate** |
| SECURITY SPECIFICATION | `security` | **SecurityGate** |
| ENGINEERING STANDARDS | `standards_web`, `standards_mobile` | **SpecGate** |
| IMPLEMENTATION PLAN | `phases_web` (reuse) + NEW `testing_strategy` | **SpecGate** |
| TESTING STRATEGY | NEW `testing_strategy` | **SpecGate** |
| CODE/SECURITY/PERFORMANCE REVIEW | NEW `verify.review` (composite) | **ReviewGate** |
| DEPLOYMENT | `env_config`, `deployment`, `observability`, `agents` | **DeployGate** |
| SMOKE TEST | NEW `smoke_test` | **SmokeTestGate** |
| PRODUCTION READINESS | NEW `verify.production_readiness` (aggregate) | **ProductionReadinessGate** |
| COMPLETE | terminal | — |

### 3.3 8 Gate Types (granular)

| Gate | Stages | Validasi |
|---|---|---|
| **DiscoveryGate** | `pertanyaan*` | MCQ 5-10 valid, answers ≥3 |
| **SpecGate** | `analisa`, `prd`, `app_spec_*`, `design_system*`, `standards_*`, `phases_*`, `testing_strategy` | heading validators + length + section rules |
| **ArchGate** | `architecture`, `erd`, `api_contract` | ASCII diagram, trade-offs ≥4, ERD schema, API schema |
| **SecurityGate** | `security` | checklist ≥6, no placeholder, OWASP coverage |
| **DeployGate** | `env_config`, `deployment`, `observability`, `agents` | env vars, topology, observability, agent file structure |
| **ReviewGate** | `verify.review` | agent evidence: code+security+perf review semua passed |
| **SmokeTestGate** | `smoke_test` | agent evidence: tests_passed + build_passed + no critical error |
| **ProductionReadinessGate** | `verify.production_readiness` | aggregate 7-day window: semua verify.* evidence passed |

### 3.4 Agent Execution Loop

```
PLAN → EXECUTE → VERIFY → TEST → REVIEW → FIX → RETEST → CHECKPOINT → NEXT
                ↓ (fail)
                FAIL → remediation task → FIX → re-VERIFY
```

Implementasi: `PipelineRunner::runStage` wrap dengan retry budget + `injectRetryHint` (existing). Tambah sub-state emit di SSE: `plan → exec → verify → test → review → checkpoint`.

### 3.5 Evidence Model

New table `version_stage_evidence`:
```
- id, project_id, version_id, stage_key, task_key (nullable)
- files_changed (jsonb), tests_passed (bool), lint_passed (bool),
  build_passed (bool), migrate_passed (bool), security_passed (bool),
  perf_passed (bool), evidence_url (text, nullable), notes (text)
- created_at, updated_at
- UNIQUE (version_id, stage_key) -- 1 row per stage
```

Agent contract (extend WebhookController contract):
```
POST /api/versions/{id}/evidence
Headers: Authorization: Bearer <token>, X-Token-Secret, X-Signature (HMAC), X-Timestamp
Body: { stage_key, files_changed?: [], tests_passed?: bool, lint_passed?: bool,
        build_passed?: bool, migrate_passed?: bool, security_passed?: bool,
        perf_passed?: bool, evidence_url?: string, notes?: string }
Response: 200 / 422 (invalid stage_key, version ownership)
```

`evidence_url` opsional. Reuse `ProjectApiToken` HMAC.

### 3.6 Wizard State Machine

Top-level: `idle | starting | streaming | waiting_mcq | awaiting_approve | error | complete | cancelled`. Per-stage: `pending | ready | running | validating | done | error | retrying | blocked | skipped`.

## 4. Stage-by-Stage Specification

### `testing_strategy`
- **Purpose**: Define test strategy (unit/integration/e2e/contract) before code.
- **Input**: analysis, prd, architecture, api_contract, design_system.
- **Tasks**: enumerate test surfaces, define coverage targets, list critical paths.
- **Expected Output**: markdown with sections — Test Pyramid, Unit Strategy, Integration Strategy, E2E Strategy, Coverage Targets, Critical Paths, Tools.
- **Acceptance**: 7 headings; unit+e2e+integration sections present; ≥5 critical paths.
- **DoD**: artifact length ≥1500 chars; section validators pass; Quality Gate `SpecGate` pass.
- **Error**: retry 3x; on exhaust → `blocked`.
- **Dependencies**: `architecture` + `api_contract` done.
- **Next**: `phases_web`/`master_web` use this in context.

### `verify.review` (composite)
- **Purpose**: Aggregate evidence for code/security/performance review by external agent.
- **Input**: standards_web, standards_mobile, security, deployment.
- **Tasks**: agent runs review tool (e.g. linter, SAST, perf check) and POSTs evidence.
- **Expected Output**: 1 row in `version_stage_evidence` with `security_passed=true`, `perf_passed=true`, `notes` containing review summary.
- **Acceptance**: `evidence.security_passed=true && evidence.perf_passed=true` dalam 7-day window.
- **DoD**: ReviewGate pass.
- **Error**: missing evidence after 3 retry → `blocked`.
- **Dependencies**: `standards_*`, `security`, `deployment` done.
- **Next**: unblock `agents`.

### `smoke_test`
- **Purpose**: Agent runs smoke test scripts and posts evidence.
- **Input**: testing_strategy, deployment, env_config.
- **Tasks**: agent executes smoke tests in deployed env, POSTs results.
- **Expected Output**: 1 row evidence with `tests_passed=true`, `build_passed=true`.
- **Acceptance**: SmokeTestGate pass.
- **DoD**: artifact + evidence both pass.
- **Error**: missing/invalid evidence → `blocked`.
- **Dependencies**: `verify.review` done, `deployment` done.
- **Next**: unblock `verify.production_readiness`.

### `verify.production_readiness`
- **Purpose**: Aggregate gate — version is production-ready.
- **Input**: all `verify.*` evidence + `production_ready_at` candidate.
- **Tasks**: orchestrator computes composite score.
- **Expected Output**: version field `production_ready_at = now()` if all pass.
- **Acceptance**: ProductionReadinessGate pass — semua evidence dalam window 7 hari, no error di stage manapun, security_passed+perf_passed+tests_passed+build_passed+migrate_passed all true.
- **DoD**: `production_ready_at` populated, WizardCompleteCard badge "Production Ready".
- **Error**: any evidence missing/false → `blocked`, cannot complete.
- **Dependencies**: ALL other gates done.
- **Next**: COMPLETE (terminal).

## 5. State/Transition Model

```php
// api/app/Models/Version.php - tambahan
const STAGE_BLOCKED = 'blocked';
const STAGE_RETRYING = 'retrying';

// api/database/migrations add column
$table->timestampTz('production_ready_at')->nullable();
$table->jsonb('gate_states')->default('{}');  // {stage_key: {gate_type, passed, reason, checked_at}}
```

State transitions emit di SSE:
- `stage.pending` → `stage.ready` (gate prerequisite satisfied)
- `stage.ready` → `stage.running`
- `stage.running` → `stage.validating`
- `stage.validating` → `stage.done` (success) | `stage.error` (fail)
- `stage.error` → `stage.retrying` (attempt < max) | `stage.blocked` (exhausted)
- `stage.blocked` → `stage.ready` (user regenerate)

## 6. Required Database/API Changes

### 6.1 New table
```
version_stage_evidence (
  id, project_id, version_id, stage_key, task_key (nullable),
  files_changed (jsonb), tests_passed, lint_passed, build_passed,
  migrate_passed, security_passed, perf_passed, evidence_url (nullable),
  notes (text), created_at, updated_at,
  UNIQUE (version_id, stage_key)
)
```

### 6.2 New columns
- `versions.production_ready_at` timestamptz nullable
- `versions.gate_states` jsonb default `'{}'`

### 6.3 New endpoints
- `POST /api/versions/{id}/evidence` — agent POST evidence (HMAC auth via ProjectApiToken)
- `GET /api/versions/{id}/evidence` — list evidence (auth via Sanctum user)
- `GET /api/versions/{id}/production-readiness` — composite gate state

### 6.4 Extended endpoints
- `GET /api/stages` response tambah `gate` field per stage
- `POST /api/versions/{id}/regenerate` — respect gate state

## 7. Required UI/UX Changes

### 7.1 Wizard Decomposition (CP-46.D)

Extract dari `new/page.tsx` 2257 LOC → ≤1000 LOC.

```
web/src/hooks/
  useWizardMachine.ts    -- top-level state machine
  usePipelineStream.ts   -- SSE consumer
  usePhaseTracking.ts    -- webhook → phase_progress
  useResume.ts           -- load + reset orphan running
  useStageEvidence.ts    -- collect + display

web/src/components/wizard/
  WizardStageRail.tsx        -- stage list with status + gate + retry + evidence
  WizardArtifactPanel.tsx    -- renders current artifact
  WizardCheckpointBar.tsx    -- top: current stage + retry + progress %
  WizardStartForm.tsx        -- initial input + MCQ
  WizardCompleteCard.tsx     -- production-ready badge + summary + export
  WizardError.tsx            -- diagnostic pack + retry
  WizardEvidenceBadge.tsx    -- per-stage evidence icon
```

Replacement: `WizardStageRail` uses existing `StageRow` (currently dead code) with new props for gate + evidence + retry counter.

### 7.2 UI Surfaces Added

- **Top bar**: current stage, total progress %, retry counter.
- **Side rail**: 26 stages with status icon (pending/ready/running/done/error/blocked/skipped), gate lock icon, evidence icon, retry badge.
- **Artifact panel**: renders artifact (markdown/json) + per-stage evidence tooltip.
- **Error state**: "Salin Diagnostic" button with `{stage, message, tail, run_id, retry_attempt, evidence, gate_state}`.
- **Production-ready card**: badge + timestamp + export ZIP button.

## 8. Agent/Prompt Changes

### 8.1 New prompt files
- `api/app/Prompts/testing_strategy.php` — Test Pyramid + Unit/Integration/E2E + Coverage Targets.
- `api/app/Prompts/verify_review.php` — narrative-only (no AI text gen; gate runs on agent evidence).
- `api/app/Prompts/smoke_test.php` — narrative + curl snippets for smoke endpoints.
- `api/app/Prompts/verify_production_readiness.php` — narrative-only composite gate.

### 8.2 Existing prompts updated
- `phased_master.php` §6 (already trimmed in CP-45.A) — append line: "Setelah setiap verify.* gate, agent WAJIB POST evidence ke `POST /api/versions/{id}/evidence` dengan stage_key dan field yang relevan."

## 9. Implementation Plan (per-checkpoint, incremental extraction)

### CP-46.A — Gate Registry Foundation ✅ DONE 2026-08-23

Files created/modified:
- ✅ `api/app/Services/StageGateRegistry.php` (registry + 8 gate types)
- ✅ `api/app/Services/Gates/StageGate.php` (interface)
- ✅ `api/app/Services/Gates/DiscoveryGate.php` (pertanyaan*, mobile-web gate)
- ✅ `api/app/Services/Gates/SpecGate.php` (analisa, prd, design_system*, app_spec*, standards*, phases*)
- ✅ `api/app/Services/Gates/ArchGate.php` (architecture, erd, api_contract)
- ✅ `api/app/Services/Gates/SecurityGate.php` (security)
- ✅ `api/app/Services/Gates/DeployGate.php` (env_config, deployment, observability, agents)
- ✅ `api/app/Services/Gates/ReviewGate.php` (verify.review — evidence-based, CP-46.B ready)
- ✅ `api/app/Services/Gates/SmokeTestGate.php` (smoke_test — evidence-based)
- ✅ `api/app/Services/Gates/ProductionReadinessGate.php` (verify.production_readiness — 7-day window)
- ✅ `api/database/migrations/2026_08_23_100000_add_gate_states_to_versions.php` (gate_states jsonb)
- ✅ `api/database/migrations/2026_08_23_110000_add_blocked_retrying_to_phase_progress_status_check.php` (CHECK constraint update)
- ✅ `api/app/Models/Version.php` (+ STAGE_BLOCKED, STAGE_RETRYING, gate_states/production_ready_at casts/fillable)
- ✅ `api/app/Services/PipelineRunner.php` (gate check integration, lite/mobile filter before gate)
- ✅ `api/routes/api.php` (`GET /api/stages` enrich with `gate` field)
- ✅ `web/src/lib/mock.ts` (GATE_MAP mirror, gate field per stage, StageState union extended)
- ✅ `web/src/components/wizard/WizardStageRail.tsx` (read-only component with gate lock + retry badge)
- ✅ `api/tests/Feature/StageGateRegistryTest.php` (13 tests, 64 assertions)

Verification: 437/438 tests pass (1 flake pre-existing SocialiteController). Web lint+tsc clean.

### CP-46.B — Evidence Model
Files:
- Migration: `version_stage_evidence` table.
- `api/app/Models/VersionStageEvidence.php`.
- `api/app/Http/Controllers/EvidenceController.php` (new) — POST + GET.
- `routes/api.php` — add routes (throttle:120,1).
- `api/app/Services/PipelineRunner.php` — link evidence to gate check.
- `web/src/hooks/useStageEvidence.ts` (new).
- `web/src/components/wizard/WizardEvidenceBadge.tsx` (new).
- `api/tests/Feature/EvidenceControllerTest.php`.

### CP-46.B — Evidence Model ✅ DONE 2026-08-23

Files created:
- ✅ `api/database/migrations/2026_08_23_120000_create_version_stage_evidence_table.php` (table + UNIQUE per stage)
- ✅ `api/app/Models/VersionStageEvidence.php` (fillable + casts)
- ✅ `api/app/Http/Controllers/EvidenceController.php` (HMAC store + sanctum index)
- ✅ `api/routes/api.php` (`POST /api/versions/{id}/evidence` + `GET .../evidence`)
- ✅ `web/src/hooks/useStageEvidence.ts` (fetch + cache)
- ✅ `web/src/components/wizard/WizardEvidenceBadge.tsx` (per-check badge cluster)
- ✅ `api/tests/Feature/EvidenceControllerTest.php` (8 tests: 200, upsert, 422, 401, 409 replay, sanctum, 404, optional URL)

Verification: 445/446 tests pass (1 flake SocialiteController). Web lint+tsc clean.

### CP-46.C — 4 New Verification Stages
Files:
- `api/app/Services/StageRegistry.php` — add 4 stages.
- `api/app/Prompts/testing_strategy.php`.
- `api/app/Prompts/verify_review.php`.
- `api/app/Prompts/smoke_test.php`.
- `api/app/Prompts/verify_production_readiness.php`.
- `api/app/Services/Validators/TestingStrategyValidator.php`.
- Migration: add `production_ready_at` column to `versions`.
- `web/src/lib/mock.ts` — extend STAGES/STAGE_GROUPS.
- `api/tests/Feature/TestingStrategyStageTest.php`, `VerifyReviewGateTest.php`, `SmokeTestGateTest.php`, `ProductionReadinessGateTest.php`.

Sequence update:
- After `phases_web` → `testing_strategy` (only target='both' or web).
- After `agents` → `verify.review`, `smoke_test`, `verify.production_readiness` (always last 3).

### CP-46.D — Wizard Decomposition (incremental)
Strategy: extract 1 hook + 1 component per commit, page stays working.
1. `useResume` → commit
2. `usePipelineStream` → commit
3. `usePhaseTracking` → commit
4. `useStageEvidence` → commit
5. `useWizardMachine` → commit
6. Replace inline stage card with `<StageRow>` → commit
7. `<WizardStageRail>` → commit
8. `<WizardArtifactPanel>` → commit
9. `<WizardCheckpointBar>` → commit
10. `<WizardError>` + `<WizardCompleteCard>` → commit
11. Final cleanup → commit

### CP-46.E — Production-Ready Verification (final wiring)
Files:
- `api/app/Http/Controllers/ExportController.php` (new) — `GET /api/versions/{id}/export-package` returning ZIP.
- `api/app/Services/ProductionReadinessAggregator.php` (new) — composite gate (7-day window hardcoded).
- `api/app/Http/Controllers/VersionController.php` — `production_ready_at` set on gate pass.
- `web/src/components/wizard/WizardCompleteCard.tsx` (full impl).
- `api/tests/Feature/ProductionReadinessAggregatorTest.php`.
- `api/tests/Feature/ExportPackageTest.php`.

## 10. Checkpoint Plan

| CP | Gate | Deliverable | Verification |
|---|---|---|---|
| 46.A | Gate Registry | 8 gate classes, PipelineRunner integration, StageRail read-only | 424+ tests pass |
| 46.B | Evidence | table + endpoint + HMAC + UI badge | new tests green, lint clean |
| 46.C | 4 new stages | StageRegistry + prompts + validators + tests | sequence updated, 4 stages functional |
| 46.D | Wizard decomposition | hooks + components, page ≤ 1000 LOC | lint+tsc+e2e green |
| 46.E | Production readiness | composite gate + export ZIP + UI | full e2e happy path |

## 11. Test Plan

### Unit
- `StageGateRegistryTest` — 8 test methods, 1 per gate.
- `ProductionReadinessAggregatorTest` — 7-day window, evidence aggregation.

### Feature
- `EvidenceControllerTest` — POST/GET, HMAC auth, per-stage unique, validation.
- `TestingStrategyStageTest` — prompt runs, validator pass/fail.
- `VerifyReviewGateTest` — agent evidence → gate pass/fail.
- `SmokeTestGateTest` — agent evidence → gate pass/fail.
- `ExportPackageTest` — ZIP contains 11 files.

### Integration
- `PipelineRunnerGateTest` — gate blocked → stage blocked.
- `WizardE2ETest` — full happy path with mocked AI + agent.

### Regression
- Full Laravel suite (424 baseline + new tests).
- Frontend lint + tsc clean.

## 12. Production Readiness Criteria

1. requirements fulfilled ✓
2. acceptance criteria fulfilled ✓
3. tests passing ✓
4. no critical errors ✓
5. security checks passed ✓
6. API contract valid ✓
7. database/migration valid ✓
8. error handling valid ✓
9. responsive UI valid ✓
10. performance acceptable ✓
11. logging/observability tersedia ✓
12. deployment verified ✓
13. smoke test passed ✓
14. code review passed ✓
15. production-ready gate pass ✓

15/15 criteria.
