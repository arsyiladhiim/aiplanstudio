# 05 — Wizard Flow ("Buat Plan")

> Lihat juga: [06-ai-pipeline](06-ai-pipeline.md) · [04-api-contract](04-api-contract.md) · [08-frontend](08-frontend.md)

## Prinsip
- **Wizard 22 tahap** (target `both`) / **16 tahap** (target `web`) — semua tahap dijalankan **per-stage manual** (default; toggle "Auto" di checkpoint bar untuk auto-advance antar tahap non-MCQ): setelah setiap tahap selesai, user meninjau lalu klik **Approve & Lanjut** (atau Analisa Ulang / Edit inline). Stage akhir menghasilkan dokumen operasional: `env_config`, `security`, `deployment`, `observability`.
- Untuk target `both`, mobile track (stage 10-13) menghasilkan phases, standards, agents & master prompt untuk platform mobile. Mobile track menunggu web track (`master_web`) selesai (gate), dan wizard meminta **konfirmasi** bila tracking fase web belum selesai.

## Input Awal
Sebelum tahap 1, user isi:
- **Ide** (deskripsi bebas)
- **Target**: `web` | `both`
- **Stack** (opsional; kosong = AI sarankan)
- **Template** (opsional) untuk pre-fill idea/target/stack

## 14 Tahap Pipeline

> **CP-10 update:** Stage `api_contract` (#6) tetap dijalankan di backend dan disimpan ke DB, **tapi tidak muncul** di nav wizard. Viewer API Contract pindah ke tab "API" di dalam stage `erd` (lihat §Wizard Stages per Version).

| # | Stage key | Nama | Input konteks | Output → DB column | Catatan |
|---|-----------|------|---------------|-------------------|---------|
| 1 | `pertanyaan` | Pertanyaan Klarifikasi (MCQ) | ide, target, stack | → `pertanyaan` (text) | MCQ A–D + E, minimal 8 pertanyaan. Jawaban user → `answers`. |
| 2 | `analisa` | Analisa & Klarifikasi | ide, target, stack, jawaban | `analysis` | Render via `AnalysisView` (persona grid + JTBD list). |
| 3 | `prd` | PRD | analisa + jawaban, ide, target | `prd` | Render via `PrdView` (story grouping + AC Given/When/Then). |
| 4 | `architecture` | Arsitektur & Tech Stack | PRD, target | `architecture` | Render via `ArchitectureView` (section cards + ASCII diagram preservation). |
| 5 | `erd` | ERD + API Contract | PRD, arsitektur | `erd` (jsonb) + `api_contract` (jsonb) | Render via `ErdTabs` (3 tabs: Diagram \| API \| Tables). Diagram pakai **React Flow** dengan table nodes (PK/FK badges) + animated edges berlabel relasi. API tab pakai `ApiEndpointList`. |
| ~~6~~ | ~~`api_contract`~~ | ~~API Contract~~ | ~~PRD, arsitektur, ERD~~ | `api_contract` (jsonb, array endpoint) | **DEPRECATED sebagai wizard stage (CP-10).** Backend tetap jalan, output tetap di-save, viewer absorbed ke tab API di stage `erd`. |
| 6 | `standards_web` | Web Standards | PRD, arsitektur, ERD | `standards` | STANDARDS.md web — sebelum phases agar roadmap ter-grounding. Render via `StandardsView`. |
| 7 | `phases_web` | Web Phases | standards, PRD, arsitektur, ERD | `phases` (jsonb) | Render via `PhasesView` (dual JSON + markdown `FASE:` format + effort badges S/M/L). |
| 8 | `master_web` | Master Prompt Web | standards, analisa, PRD, arsitektur, ERD, api_contract | `master_prompt` | Render via `MasterPromptViewer` (section accordion + inline edit + download .md). Auto-open modal setelah SSE `done` event. Embeds `SetupTrackingCard`. |
| 9 | `pertanyaan_mobile` | Mobile Klarifikasi (MCQ) | master_web, api_contract, erd | `pertanyaan_mobile` + `mobile_answers` | **Hanya target both.** Gate `master_web` done. Skip rule: return empty JSON kalau target !== "both". |
| 10 | `phases_mobile` | Mobile Phases | mobile_standards, PRD, arsitektur, ERD, master_web | `mobile_phases` (jsonb) | **Hanya target both.** |
| 11 | `standards_mobile` | Mobile Standards | PRD, arsitektur, ERD, master_web | `mobile_standards` | STANDARDS.md mobile |
| 12 | `master_mobile` | Master Prompt Mobile | mobile_standards, analisa, PRD, arsitektur, ERD, master_web | `mobile_master_prompt` | Self-contained master prompt mobile. Auto-open modal setelah done. |
| 13 | `env_config` | Env & Config | PRD, arsitektur, api_contract, master_web | `env_config` | Dokumen `.env.example` + env var per platform (web/mobile). |
| 14 | `security` | Security Checklist | PRD, arsitektur, api_contract, ops docs | `security` | OWASP checklist production-ready. |
| 15 | `deployment` | Deployment Guide | arsitektur, env_config | `deployment` | Docker Compose + Cloudflare Tunnel + backup/rollback. |
| 16 | `observability` | Observability & Runbook | arsitektur, env_config, deployment | `observability` | Health/Sentry/runbook. |
| 17 | `agents` | AI Agent Spec | master_web (+ master_mobile jika both) + ops docs | `agents` | Render via `AgentsView` (role cards dengan handoff arrows). |

**Wizard Stages per Version** (frontend `getStages()`):
- target `web` → 16 stages visible (web track + ops docs + agents). Sumber: StageRegistry.
- target `both` → 22 stages visible (web + mobile + ops docs + agents).
- `api_contract` (CP-10) tidak visible di kedua target (collapsed ke tab ERD).

### Stage Keys (PipelineRunner backend order)
```
pertanyaan → analisa → prd → architecture → erd → api_contract → phases_web → standards_web → master_web
→ [target both] pertanyaan_mobile → phases_mobile → standards_mobile → master_mobile
→ agents
```
- Nilai `stage_status`: `pending` | `running` | `done` | `error`
- Kolom di DB untuk artifact: `pertanyaan`, `analysis`, `prd`, `architecture`, `erd`, `api_contract`, `standards`, `agents`, `phases`, `master_prompt`, `pertanyaan_mobile`, `mobile_phases`, `mobile_standards`, `mobile_agents`, `mobile_master_prompt`
- Kolom JSONB: `answers`, `mobile_answers`, `erd`, `api_contract`, `phases`, `mobile_phases`

## Tracking Webhook (CP-6 restore)

**Token creation** — Setup Tracking UI (bukan auto-generate lagi):
- Frontend `TrackingPanel` header → button "Setup Tracking" → Modal → `apiSetupAutoTracking(projectId, versionId)` → `POST /api/projects/{project}/versions/{version}/tokens/auto-tracking`.
- Backend creates `ProjectApiToken` dengan `name = "auto-tracking-" + md5(version_id, 8 chars)` + per-token HMAC salt.
- Response: `{token, secret, id, name, existing: false}` (first call) atau `{existing: true, token: null, secret: null}` (repeat).
- Secret only shown ONCE di modal reveal step — user harus copy sekarang.
- Token cached di sessionStorage keyed `tracking-token-{projectId}-{versionId}`.

**Webhook call** (CP-6 corrected HMAC spec):
```bash
curl -X POST $APP_URL/api/webhooks/phase-complete \
  -H "Authorization: Bearer $TOKEN" \
  -H "X-Token-Secret: $SECRET" \
  -H "X-Timestamp: $(date +%s)" \
  -H "X-Signature: $(echo -n "$TIMESTAMP.$BODY" | openssl dgst -sha256 -hmac "$SECRET" | awk '{print $2}')" \
  -H "Content-Type: application/json" \
  -d '{"version_id": 1, "phase_key": "fase1_setup", "task_key": "fase1_setup_fitur_1", "task_type": "fitur", "title": "Auth Login", "status": "done", "output": "completed"}'
```

**Granular task_type** (CP-6 T7 + CP-10 G-4): `halaman` | `menu` | `fitur` | `flow` | `api`. Filter chip di TrackingPanel header dengan per-type progress counters (`done/total`).

**Prompt context** — `PipelineRunner::trackingBlock()` sekarang cek existing token by name pattern. Kalau ada → embed HMAC spec di prompt. Kalau tidak → instruksi "skip webhook until Setup Tracking creates one".

## Checkpoint (per-stage manual)
Setelah tiap stage `done`, wizard berhenti (tanpa auto-run). User dapat:
- **Review** artefak.
- **Approve & lanjut** ke stage berikut.
- **Re-run** stage (regenerate).
- **Edit inline** artifact — toggle view/edit, simpan via `PATCH /api/versions/{id}/artifacts`.
- Saat `master_web`: bila **tracking fase web** (phase_progress) belum selesai → wizard menampilkan konfirmasi sebelum lanjut ke mobile.

## Target-aware (contoh perbedaan)

| Aspek | Web | Mobile (APK/iOS) |
|-------|-----|------------------|
| Stack saran | Laravel 13 + Next.js + React 19 + Tailwind + PostgreSQL | Flutter + Dart + Riverpod + GoRouter + Material Design 3 |
| Arsitektur | SSR/SPA, routing web, **direct API call** ke Laravel via `NEXT_PUBLIC_API_URL` | screen/navigation, state mgmt, offline, **direct API call** ke Laravel via dio + cookie manager |
| ERD | tabel relasional | entity + storage lokal/remote |
| Phases | …→ deploy web/hosting | …→ build APK/IPA, signing, store submission |
| Master Prompt | instruksi lengkap untuk web stack | instruksi lengkap untuk mobile stack |

> **CP-12 note:** Mobile track (Flutter) consume API **direct** ke Laravel domain (bukan lewat Next.js). Backend reference sudah live dan mobile adalah client-only. Cookie manager di dio (`dio_cookie_manager`) handle HttpOnly session cookie + CSRF. Detail cross-origin setup: `docs/25-bypass-bff.md` (Sanctum stateful domain + CORS allowlist).

`Both` → dua jalur: tahap 1-12 untuk web+mobile track, lalu tahap 13-16 dokumen operasional (env_config, security, deployment, observability), lalu tahap 17 `agents`. Gate: mobile track menunggu `master_web` done.

## Versi & Pengembangan Lanjutan (R1)
- **Versi Baru (default `from_last`)**: menyalin artefak, jawaban, `answers`/`mobile_answers` & `stage_status` dari versi terakhir → **revisi/pengembangan lanjut**, bukan mulai dari nol. Kolom `source_version_id` + `baseline_notes` mencatat asal.
- Opsi `blank`: mulai kosong (rencana baru).
- UI: dialog "Buat Versi Baru" → pilih strategi + catatan revisi.
- Diff antar versi kini bermakna: hanya stage yang benar-benar diubah tampil sebagai delta.

## Tahap 8-12 — Kualitas Prompt (kunci nilai produk)
Prompt tiap phase **harus membawa konteks** phase sebelumnya (ringkasan PRD + arsitektur + apa yang sudah dibangun), agar AI agent tidak kehilangan benang merah. Master prompt = instruksi menyeluruh + urutan menjalankan phase. Semua prompt **copy-ready** dan 1-paste ke coding agent (Claude/Cursor/Claude Code) langsung jadi project skeleton.

**CP-7 overhaul** — semua 11 prompt di `api/app/Prompts/` di-rewrite dengan explicit output templates + self-check instructions. FOCUS items: `phased_master.php` + `phased_master_mobile.php` jadi centerpiece dengan 8 sections (Meta/Context/Stack/Folder/Phases/Standards/Webhook/Self-Verify) + correct HMAC webhook contract dengan 4 headers wajib.

Master prompt juga menyertakan **Webhook Tracking** spec (HMAC SHA-256) — bukan Bearer token auto-gen lagi. Token + Secret dibuat user via Setup Tracking UI. Webhook `phase_complete` adalah contract antara AI agent (yang eksekusi master prompt) dan AI Plan Studio (yang track progress).

## Progress
Tiap phase punya entri `phase_progress` (status `pending|running|done|error` + output) → progress bar project. Sub-item `task_progress` (granular: halaman/menu/fitur/flow/api) → filter chips di TrackingPanel dengan per-type progress counters. Lihat [03-database-schema](03-database-schema.md).
