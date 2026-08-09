# 05 — Wizard Flow ("Buat Plan")

> Lihat juga: [06-ai-pipeline](06-ai-pipeline.md) · [04-api-contract](04-api-contract.md) · [08-frontend](08-frontend.md)

## Prinsip
- **Wizard 14 tahap** (target `both`) / **10 tahap** (target `web`) — semua tahap dijalankan **per-stage manual** (tanpa auto-run): setelah setiap tahap selesai, user meninjau lalu klik **Approve & Lanjut** (atau Analisa Ulang / Edit inline).
- Untuk target `both`, mobile track (stage 10-13) menghasilkan phases, standards, agents & master prompt untuk platform mobile. Mobile track menunggu web track (`master_web`) selesai (gate), dan wizard meminta **konfirmasi** bila tracking fase web belum selesai.

## Input Awal
Sebelum tahap 1, user isi:
- **Ide** (deskripsi bebas)
- **Target**: `web` | `both`
- **Stack** (opsional; kosong = AI sarankan)
- **Template** (opsional) untuk pre-fill idea/target/stack

## 14 Tahap Pipeline

| # | Stage key | Nama | Input konteks | Output → DB column | Catatan |
|---|-----------|------|---------------|-------------------|---------|
| 1 | `pertanyaan` | Pertanyaan Klarifikasi (MCQ) | ide, target, stack | → `pertanyaan` (text) | MCQ A–D + E, minimal 5 pertanyaan. Jawaban user → `answers`. |
| 2 | `analisa` | Analisa & Klarifikasi | ide, target, stack, jawaban | `analysis` | |
| 3 | `prd` | PRD | analisa + jawaban, ide, target | `prd` | |
| 4 | `architecture` | Arsitektur & Tech Stack | PRD, target | `architecture` | |
| 5 | `erd` | ERD + API Contract | PRD, arsitektur | `erd` + `api_contract` (jsonb) | JSON `{nodes,edges,api_contract}` |
| 6 | `api_contract` | API Contract | PRD, arsitektur, ERD | `api_contract` (jsonb, array endpoint) | Stage terpisah — daftar endpoint lengkap |
| 7 | `phases_web` | Web Phases | standards, agents, PRD, arsitektur, ERD | `phases` (jsonb) | Breakdown fase web (format `FASE:`, `TASK:`, `PROMPT:`) |
| 8 | `standards_web` | Web Standards | PRD, arsitektur, ERD | `standards` | STANDARDS.md web |
| 9 | `master_web` | Master Prompt Web | standards, agents, analisa, PRD, arsitektur, ERD | `master_prompt` | Self-contained; auto-token tracking + webhook |
| 10 | `pertanyaan_mobile` | Mobile Klarifikasi (MCQ) | master_web, api_contract, erd | `pertanyaan_mobile` + `mobile_answers` | **Hanya target both.** Gate `master_web` done. |
| 11 | `phases_mobile` | Mobile Phases | mobile_standards, PRD, arsitektur, ERD, master_web | `mobile_phases` (jsonb) | **Hanya target both.** |
| 12 | `standards_mobile` | Mobile Standards | PRD, arsitektur, ERD, master_web | `mobile_standards` | STANDARDS.md mobile |
| 13 | `master_mobile` | Master Prompt Mobile | mobile_standards, mobile_agents, analisa, PRD, arsitektur, ERD, master_web | `mobile_master_prompt` | Self-contained master prompt mobile |
| 14 | `agents` | AI Agent Spec | master_web (+ master_mobile jika both) | `agents` | AGENTS.md — spesifikasi agent |

### Stage Keys (PipelineRunner ALL_STAGES)
```
pertanyaan → analisa → prd → architecture → erd → api_contract → phases_web → standards_web → master_web
→ [target both] pertanyaan_mobile → phases_mobile → standards_mobile → master_mobile
→ agents
```
- Nilai `stage_status`: `pending` | `running` | `done` | `error`
- Kolom di DB untuk artifact: `pertanyaan`, `analysis`, `prd`, `architecture`, `erd`, `api_contract`, `standards`, `agents`, `phases`, `master_prompt`, `pertanyaan_mobile`, `mobile_phases`, `mobile_standards`, `mobile_agents`, `mobile_master_prompt`
- Kolom JSONB: `answers`, `mobile_answers`, `erd`, `api_contract`, `phases`, `mobile_phases`

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
| Stack saran | Laravel 11 + Next.js + React 19 + Tailwind + PostgreSQL | Flutter + Dart + Riverpod + GoRouter + Material Design 3 |
| Arsitektur | SSR/SPA, routing web | screen/navigation, state mgmt, offline |
| ERD | tabel relasional | entity + storage lokal/remote |
| Phases | …→ deploy web/hosting | …→ build APK/IPA, signing, store submission |
| Master Prompt | instruksi lengkap untuk web stack | instruksi lengkap untuk mobile stack |

`Both` → dua jalur: tahap 1-9 untuk web, tahap 10-13 khusus mobile (pertanyaan_mobile, phases, standards, master), lalu tahap 14 `agents`. Gate: mobile menunggu `master_web` done.

## Versi & Pengembangan Lanjutan (R1)
- **Versi Baru (default `from_last`)**: menyalin artefak, jawaban, `answers`/`mobile_answers` & `stage_status` dari versi terakhir → **revisi/pengembangan lanjut**, bukan mulai dari nol. Kolom `source_version_id` + `baseline_notes` mencatat asal.
- Opsi `blank`: mulai kosong (rencana baru).
- UI: dialog "Buat Versi Baru" → pilih strategi + catatan revisi.
- Diff antar versi kini bermakna: hanya stage yang benar-benar diubah tampil sebagai delta.

## Tahap 8-11 — Kualitas Prompt (kunci nilai produk)
Prompt tiap phase **harus membawa konteks** phase sebelumnya (ringkasan PRD + arsitektur + apa yang sudah dibangun), agar AI agent tidak kehilangan benang merah. Master prompt = instruksi menyeluruh + urutan menjalankan phase. Semua prompt **copy-ready** dan bisa di-export (lihat [04-api-contract](04-api-contract.md) export). Master prompt menyertakan **Webhook Tracking** (auto Bearer token) agar fase dieksekusi agent tercatat sebagai `phase_progress`.

## Progress
Tiap phase punya entri `phase_progress` (status `pending|running|done|error` + output) → progress bar project. Lihat [03-database-schema](03-database-schema.md).
