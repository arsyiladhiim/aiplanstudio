# 36 — Wizard Consistency & Anti-Slop — Build Plan & Checkpoints

> **Status:** ✅ COMPLETED
> **Started:** 2026-08-19
> **Completed:** 2026-08-19
> **Scope:** Hilangkan error permanen di stage architecture/security, hardening semua validator (toleran phrasis LLM), terapkan FRONTEND ANTI-SLOP STANDARD (prompt design_system + wizard views + dashboard + project detail)
> **Parent:** docs/32, 33, 35 (COMPLETED)

---

## Objective

1. **W1** Fix keyword map (root cause: keyword Inggris ≠ prompt Indonesia → architecture & security selalu error)
2. **W2** `validateArchitectureSectionRules` (ASCII diagram + tabel trade-off + no placeholder)
3. **W3** `validateSecuritySectionRules` (checklist ≥7)
4. **W4** Parser hardening (env fence normalize, api_contract auth coerce, prd bullet, phases case, standards fence prefix)
5. **W5** Soften app_spec↔master cross-ref (warning + skor, bukan hard-fail)
6. **W6** Retry hint adaptif (sebut frasa eksplisit utk keyword gagal)
7. **W7** Fixture real-language (mencegah regresi)
8. **W8** Anti-Slop: prompt design_system 16-item + Visual QA scorecard; view wizard variasi layout; audit dashboard + project detail

---

## Checkpoint Tracker

### W1 — Fix Keyword Map ✅
- [x] Implementasi grup-OR/synonym di `STAGE_REQUIRED_KEYWORDS` + `assertRequiredKeywords`
- [x] `computeStageQuality` diupdate ke grup-OR
- [x] Test `KeywordSynonymTest` 6 pass; regresi 17 pass
- [x] Update checkpoint

### W2 — validateArchitectureSectionRules ✅
- [x] Implementasi validator (ASCII diagram, trade-off ≥4 baris, no placeholder)
- [x] Call di saveArtifact architecture
- [x] Test `ArchitectureValidationTest` 4 pass
- [x] pint clean
- [x] Update checkpoint

### W3 — validateSecuritySectionRules ✅
- [x] Implementasi validator (checklist ≥7, no placeholder)
- [x] Call di saveArtifact security
- [x] Test `SecurityValidationTest` 4 pass
- [x] pint clean
- [x] Update checkpoint

### W4 — Parser Hardening ✅
- [x] env fence normalize (`extractCodeFencePrefix` — cocok ```env.example)
- [x] api_contract normalizeAuth/path (`normalizeApiContract`)
- [x] prd bullet `[-*•]` + flag `/u` (multibyte)
- [x] phases sudah case-insensitive (verified, no change needed)
- [x] standards fence prefix normalize
- [x] Test `EnvFenceTest` + `ApiContractCoerceTest` + `PhasesCasingTest` 9 pass
- [x] pint clean
- [x] Update checkpoint

### W5 — Soften App Spec ↔ Master ✅
- [x] `validateAppSpecMasterCrossRef` → warning + skor −0.1 (`crossRefPenalty`), tidak throw
- [x] Test cross-ref diupdate (gagal = warning) 4 pass
- [x] pint clean
- [x] Update checkpoint

### W6 — Retry Hint Adaptif ✅
- [x] `injectRetryHint` sebut frasa eksplisit (CEK KEYWORD STAGE reminder per grup)
- [x] Test `RetryWithHintTest` extend 4 pass
- [x] pint clean
- [x] Update checkpoint

### W7 — Fixture Real-Language ✅
- [x] `ArchitectureValidationTest` (4) + `SecurityValidationTest` (4) + `EnvFenceTest` (3) + `ApiContractCoerceTest` (4) + `PhasesCasingTest` (2) + `KeywordSynonymTest` (6) — stub bahasa Indonesia realistis
- [x] Full test regression below
- [x] Update checkpoint

### W8 — Frontend Anti-Slop
- [x] 8a: prompt design_system 2x (16-item anti-pattern [+10 more] + ### 10 Visual QA Scorecard soft)
- [x] 8b: AnalysisView (emoji→avatar initial) / PrdView (hapus ikon Check berulang) / ArchitectureView (prose tanpa card per-section) / DesignSystemView+Mobile (paragraph + number glyph, hapus SECTION_ICONS)
- [x] 8c: dashboard audit (3 primary stat typographic tanpa icon-tile + inline strip secondary)
- [x] 8d: project detail polish (group summary tampil jumlah dilewati)
- [x] tsc + lint clean
- [x] Update checkpoint

---

## Keputusan Final (anti-error)

- Anti-pattern design_system: prompt list 16 item, **validator min tetap 7** (hindari regen error)
- Visual QA scorecard: **soft** — kurang skor hanya turunkan stage_quality, bukan hard-throw
- app_spec↔master: **warning + skor −0.1**, bukan throw
- Dashboard: 6 icon-card → 4 primary stat tanpa icon-tile + inline secondary strip
- View wizard: variasi layout (list/split/panel), hapus emoji 👤, kurangi ikon berulang

---

## File touch

**Backend:** `api/app/Services/PipelineRunner.php`, `api/app/Services/AiOutputParser.php`, `api/app/Prompts/design_system.php`, `design_system_mobile.php`
**Backend tests (new/update):** `KeywordSynonymTest`, `ArchitectureValidationTest`, `SecurityValidationTest`, `EnvFenceTest`, `ApiContractCoerceTest`, `PhasesCasingTest`, update `CrossReferenceValidatorTest`, `RetryWithHintTest`
**Frontend:** `web/src/components/wizard/{AnalysisView,PrdView,ArchitectureView,DesignSystemView,DesignSystemMobileView}.tsx`, `web/src/app/(app)/dashboard/page.tsx`, `web/src/app/(app)/projects/[id]/page.tsx`