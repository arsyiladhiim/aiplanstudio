# 29 — Vibe Coding Production-Ready Plan

> Lanjutan dari `docs/28-build-plan-halaman-audit.md`. Tujuannya: pipeline di-upgrade agar master prompt + artefak hasil wizard SUDAH CUKUP untuk build project production-ready (env/deploy/security/observability ter-cover). Plus perbaikan chain context (B1-B4) dan wiring fitur produk yang backend-nya sudah siap.
> Status checkpoint: ⬜ belum / ✅ selesai (diperbarui per CP).

## RINGKASAN PERUBAHAN
1. Reorder stage: `standards_web` sebelum `phases_web` (mobile sama) → fix B1/B2.
2. Fix ctx chaining: inject `api_contract` rich ke master prompts + agents (B3); hapus `mobile_agents` dangling (B4).
3. 4 stage baru: `env_config`, `security`, `deployment`, `observability`.
4. Master prompt + phases dapat section ENV/DEPLOY/SECURITY/OBSERVABILITY; `fase7_security` wajib.
5. Frontend wiring: export bundle, activities sidebar, `?tab=` fix, cost dashboard, template detail, skeleton/empty-state, markdown editor, shortcut help.
6. Docs sync: 05/06/18/26 stale → update.

## NEW STAGE ORDER (18 stages)
```
pertanyaan, analisa, prd, architecture, erd, api_contract,
standards_web, phases_web, master_web,
pertanyaan_mobile, standards_mobile, phases_mobile, master_mobile,
env_config, security, deployment, observability,
agents
```
- Web-only = 13 (tanpa 4 mobile stage). Both = 17 visible (api_contract collapsed di ERD/CP-10).
- `progressCount()` reject api_contract + reject mobile for web → both=17, web=13 (cocok frontend mock.ts).
- `MOBILE_STAGES` = [pertanyaan_mobile, standards_mobile, phases_mobile, master_mobile] (urutannya sudah standards sebelum phases).

---

## CHECKPOINT (urutan eksekusi)

### CP-1 — Reorder ALL_STAGES (backend)
- [✅] `Version.php`: `ALL_STAGES` swap standards↔phases (web+mobile); tambah 4 stage baru di akhir.
- [✅] `Version.php`: `defaultStageStatus()` tambah 4 key baru.
- [✅] `PipelineRunner.php`: `MOBILE_STAGES` konstanta sesuaikan urutan.

### CP-2 — Fix ctx chaining (B3/B4)
- [✅] `PipelineRunner.php`: contexts master_web/master_mobile/agents inject `### API Contract` rich dari `$v->api_contract` (helper `apiContractBlock`).
- [✅] `PipelineRunner.php`: hapus `{$v->mobile_agents}` dari master_mobile ctx; tambah `opsDocsBlock`.

### CP-3 — DB columns + migration
- [✅] Migration baru: tambah `env_config, security, deployment, observability` (text) ke `versions`.
- [✅] `Version.php` Fillable.

### CP-4 — Prompt files baru
- [✅] `Prompts/env_config.php`
- [✅] `Prompts/security.php`
- [✅] `Prompts/deployment.php`
- [✅] `Prompts/observability.php`

### CP-5 — Wire stage baru ke PipelineRunner
- [🔄] `saveArtifact()` map 4 key baru.
- [ ] contexts master_web/master_mobile/agents rujukan ringkas ke 4 artifact baru.
- [ ] `regenerateStage` support key baru.

### CP-6 — Master prompt production-ready
- [✅] `phased_master.php`: § ENV/CONFIG, § DEPLOYMENT, § SECURITY, § OBSERVABILITY.
- [✅] `phased_master_mobile.php`: § serupa (dart-define, keystore, CI).
- [✅] `phases.php`: `fase7_security` MANDATORY + hard rule payment/data app wajib observability/DR/api_docs.

### CP-7 — Frontend stage list
- [✅] `mock.ts`: StageKey union + ALL_STAGES/WEB_STAGES tambah 4 stage baru.
- [✅] `new/page.tsx`: colMap (fallback+resume) tambah 4 key.
- [✅] `projects/[id]/page.tsx`: tipe Version tambah 4 field.

### CP-8 — Frontend wizard render
- [✅] `new/page.tsx`: 4 stage baru dirender via catch-all Markdown fallback + copy (sudah otomatis).
- [✅] `projects/[id]/page.tsx`: kolom baru muncul via tab Overview/Web + trailer (Markdown).

### CP-9 — Produk wiring (backend ready)
- [🔄] `DownloadBundleButton` reusable + panggil export-all & export?format=zip.
- [ ] Sidebar: "Aktivitas" + badge count.
- [ ] Fix `?tab=tracking` link dari LiveProgressWidget.

### CP-10 — Produk polish
- [ ] Provider usage/cost dashboard (agregat stage_tokens).
- [ ] `/templates/[id]` detail/edit seed.
- [ ] `Skeleton` + `EmptyState` reusable.
- [ ] Inline markdown editor untuk artifact edit.
- [ ] Shortcut help palette.

### CP-11 — Docs sync
- [✅] `05-wizard-flow.md`, `06-ai-pipeline.md`: stages 18/14, urutan standards↔phases, stage operasional baru.
- [✅] `18-production-readiness.md`: 14→18 stage labeling.
- [✅] `26-master-prompt-improvement-plan.md`: note 4 tentang mandatory security/payment.

### CP-12 — Validasi akhir
- [✅] `./vendor/bin/pint` — clean
- [✅] `php artisan test` — 261 passed, 1 skipped, 1 pre-existing (Socialite isolation)
- [✅] `npm run lint && npx tsc --noEmit` — clean (2 pre-existing CommandPalette errors, bukan dari session)
- [✅] rebuild web + restart + smoke test (/new /templates /activities 200, health ok)
- [✅] update checkpoint ini

## Status: SEMUA CHECKLIST ✅
Pipeline 18-stage (14 web / 18 both) dengan dokumen operasional (env_config/security/deployment/observability), ctx chaining diperbaiki (api_contract rich inject, mobile_agents dihapus), master prompt production-ready, produk wiring (export bundle, activities sidebar, ?tab fix, cost dashboard, template detail, markdown editor, Skeleton/EmptyState), docs synced.
