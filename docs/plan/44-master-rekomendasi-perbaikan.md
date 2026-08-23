# Master Plan 44 — Rekomendasi Perbaikan & Penambahan AI Plan Studio

> Sumber: audit internal (graphify + explore agents, 22 Agu 2026) + ChatGPT analisa #1 (audit arsitektur devel) + ChatGPT analisa #2 (rekomendasi fitur/halaman/flow).
>
> Prinsip: **tidak rewrite**. Fondasi solid (pipeline 22 stage, webhook HMAC + replay protection, SSE, versioning, Sanctum cookie auth). Urutan: perbaiki kontrak internal → bug nyata → infra protocol → baru fitur besar. YAGNI untuk DAG pipeline, agent runtime, multi-agent reviewer, marketplace — dicatat di roadmap, bukan scope ini.
>
> Status legend: `[ ]` belum · `[x]` selesai. Setiap checkpoint di-update SEBELUM lanjut ke pekerjaan berikutnya.

---

## PHASE 0 — Stabilization (P0)

### CP-01 — Single Stage Registry
Masalah: definisi stage tersebar (`mock.ts`, `Version.php`, docs) dengan angka tidak konsisten (13/14/16/18/22). Faktanya 22 stage both / 16 web.

- [x] `StageRegistry` backend sebagai single source of truth (key, label, group, target, order). — `api/app/Services/StageRegistry.php`
- [x] `PipelineRunner` + controller baca dari registry. — via `Version::ALL_STAGES = StageRegistry::KEYS`; endpoint baru `GET /api/stages`
- [x] Frontend `mock.ts` urutan/key disinkronkan ke registry (urutan mobile dikoreksi: standards_mobile sebelum phases_mobile sesuai kanonik backend).
- [ ] Docs stage-count disinkronkan (CP-04 mengerjakan teksnya).
- [x] Test hijau: `StageRegistryTest` (5 test) + PipelineRunnerTest/PipelineNewStagesTest/VersionTest/ModelTest/GenerateStreamTest lulus; pint bersih.

### CP-02 — Perbaiki Delivery Webhook ke Agent Eksternal
Masalah: `trackingBlock()` pakai `config('app.url')` (localhost) → agent eksternal tak bisa reach; TOKEN+SECRET tidak pernah ada di prompt (hanya "ambil dari UI").

- [x] Env `TRACKING_BASE_URL` + `config/app.php tracking_base_url` (fallback `APP_URL`), update `.env.example`.
- [x] `trackingBlock()`: pakai URL publik; saat token ada → sematkan TOKEN+SECRET langsung di master prompt + contoh curl siap eksekusi + heading "RAHASIA". — kredensial disimpan terenkripsi (`token_encrypted`/`secret_encrypted` + migration), di-reveal via `revealStoredToken()/revealStoredSecret()`
- [x] Redaksi kredensial kini client-side: tombol "Copy Aman" + ".md aman" di MasterPromptViewer (`stripTrackingCredentials`); server tidak lagi men-strip dari artifact tersimpan. Bonus: edit master prompt sekarang persist ke server (PATCH artifacts) bila prop `stage` diberikan.
- [x] Test: `test_tracking_block_embeds_credentials_and_public_url`, `test_tracking_block_falls_back_to_app_url`; WebhookTest replay dibuat deterministik; PipelineRunnerTest/WebhookTest/TaskProgressTest 64 passed; lint+tsc bersih.

### CP-03 — Rapatkan Kontrak Webhook
Masalah: instruksi "kirim error lalu berhenti" = deadlock; tanpa retry/timeout/event_id; `task_key` tak divalidasi milik `phase_key`; alias `phase-N` ambigu.

- [x] Blok ERROR HANDLING di prompt (`trackingBlock()` + §6 phased_master web/mobile): retry 3x backoff 1s/2s/4s, timeout 10s, 409=lanjut, 422=perbaiki key, 429=tunggu 60s; instruksi "berhenti permanen" dihapus.
- [x] `event_id` opsional diterima & dicatat (metadata Activity + echo di response); replay-protection tetap.
- [x] Validasi `task_key ∈ tasks/halaman/menu/fitur/flow/api(phase_key)` → 422 dengan pesan jelas.
- [x] Alias `phase-N`: server tetap menerima sebagai fallback indeks (legacy), prompt melarang — ketegasan satu arah didokumentasikan di sini.
- [x] Test: WebhookTest +3 (task_key fase lain 422 / sub-item valid 200 / event_id tercatat), fixture sub-item nyata; TaskProgressTest + PipelineEndToEndSmokeTest diselaraskan. Full suite 414 passed +1 skipped; 1 FAILED = flake pre-existing `SocialiteControllerTest::first_google_login...` (terbukti gagal juga pada baseline tanpa diff — urutan test, bukan regresi).

> **URL produksi (dicatat user)**: API `https://api-aiplanstudio.arsyiladm.my.id` · Frontend `https://aiplanstudio.arsyiladm.my.id`. Set `TRACKING_BASE_URL=https://api-aiplanstudio.arsyiladm.my.id` di env produksi.

### CP-04 — Sinkronisasi Dokumentasi
- [x] AGENTS.md root: direct routing (tanpa istilah BFF), 22 stage, URL produksi API+Frontend, `TRACKING_BASE_URL`.
- [x] web/AGENTS.md: section Pipeline Stages ditulis ulang dari StageRegistry (22/16).
- [x] Istilah "BFF" dihapus dari seluruh dokumentasi aktif (AGENTS.md, README, web/AGENTS.md, docs 00–09,12,18) — hanya tersisa nama file arsip `25-bypass-bff.md` & catatan historis ber-tanggal (docs/10,15,16,17,21,22 sengaja tidak diubah karena arsip keputusan).
- [x] Angka stage dikoreksi di docs/00,01,03,05: 22 tahap both / 16 web.
- [x] URL produksi dicatat: API `https://api-aiplanstudio.arsyiladm.my.id`, Frontend `https://aiplanstudio.arsyiladm.my.id`.

### CP-05 — Bug Frontend Nyata
- [x] `diff/page.tsx`: endpoint kini pakai query `current` (version id); caller mengirim `&current=${selectedVersion.id}`.
- [x] Export/download (6 lokasi) pakai `${API_BASE_URL}/api/...` absolut.
- [x] Resume colMap lengkap (+design_system, design_system_mobile, app_spec_web, app_spec_mobile).
- [x] `createSSEPost`: AbortController baru per attempt + listener agar abort luar membatalkan retry.
- [x] Dead code dibuang: `TrackingPhases.tsx` dihapus (tipe dipindah lokal), `createSSE` (~2.2KB) dihapus, mock `projects/templates/users` dibuang, `masterPromptCharCount` dibuang. Lint + tsc bersih.

## PHASE 1 — Protocol & Refactor (P1)

### CP-06 — Dekomposisi PipelineRunner (mekanis, tanpa ubah perilaku)
- [x] Extract `StageContextBuilder` (contextPrompt per stage + summarize + trackingBlock + apiContractBlock + opsDocsBlock + truncateForContext). — `api/app/Services/StageContextBuilder.php`
- [x] Extract `StageArtifactValidator` (15 method validasi markdown/JSON/keyword/section-rules + GENERIC_PATTERNS). — `api/app/Services/StageArtifactValidator.php`; runner menyimpan delegate stub agar ReflectionMethod test lama tetap jalan.
- [x] Hasil: `PipelineRunner.php` 1.798 → 1.233 LOC. Sisa (retry/persister/emitter) dicatat sebagai lanjutan — berhenti di sini karena risk/benefit cut saat ini sudah negatif untuk satu sesi.
- [x] Semua test lama hijau tanpa ubah asersi (102 passed pada 10 class terkait); pint bersih.

### CP-07 — Agent Event Protocol v1 (backward-compatible)
- [x] Migration `aiplanstudio_project.agent_events` (run_id, event_id unique, event whitelist, payload jsonb).
- [x] Model `AgentEvent` + `AgentEventController`: `POST /api/agent/events` (auth.project-token, throttle 120/menit) — event round-1: agent.started, heartbeat, phase.started/completed, task.started/completed/failed, blocked, agent.completed/failed.
- [x] Idempotency: event_id duplikat → 202 duplicate (tanpa duplikasi); phase-complete ditulis-ulang sebagai adapter yang juga menulis baris `agent_events` ekuivalen (event `phase.completed`/`task.*`).
- [x] Feed UI: `GET /versions/{id}/agent-events` (sanctum) + komponen `AgentEventFeed.tsx` di tab Tracking project detail.
- [x] Test `AgentEventTest` 5 kasus (ingest, event tak dikenal 422, idempotent, adapter phase-complete, feed auth). Full suite 419 passed; hanya flake pre-existing Socialite.

### CP-08 — Wizard Decomposition (frontend god component)
- [ ] **DITUNDA dengan alasan tercatat**: refactor state inti wizard (~2.2k LOC) tanpa jalan E2E penuh berisiko mematahkan alur generate/resume menjelang rilis. Ekstraksi aman (hooks usePipelineStream/usePhaseTracking) sebaiknya dikerjakan di sesi khusus dengan Playwright E2E hijau sebagai pagar. Prasyarat: jalankan e2e existing dulu.

## PHASE 2+ — Roadmap (TIDAK dikerjakan sekarang)

Disimpan sebagai visi (ChatGPT #2): Project Health Score, Coverage Matrix, Requirement Traceability, Control Center restructure tab, Execution Center UI penuh, Webhook Inspector, Artifact revisioning, Impact Analysis, Template/Prompt Library, AI cost dashboard, model routing, provenance, multi-agent review, collaboration. Prasyaratnya adalah Phase 0–1 di atas.

---

## Eksekusi & Verifikasi Global

Per CP: implement → pint/prettier → `php artisan test` / lint+tsc → update checklist file ini → commit terpisah.
