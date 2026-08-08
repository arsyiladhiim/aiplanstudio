# 05 — Wizard Flow ("Buat Plan")

> Lihat juga: [06-ai-pipeline](06-ai-pipeline.md) · [04-api-contract](04-api-contract.md) · [08-frontend](08-frontend.md)

## Prinsip
- **Wizard 13 tahap** (target `both`) / **9 tahap** (target `web`) dengan mode **auto-run** (default): semua tahap dijalankan berurutan tanpa henti, user review di akhir.
- Tersedia mode **checkpoint** (toggle): user bisa approve tiap tahap sebelum lanjut.
- **Target-aware**: output menyesuaikan `target` project (Web / Both).
- Tiap tahap = 1 panggilan LLM streaming (SSE), output menjadi konteks tahap berikutnya. Hasil disimpan sebagai **Version**.
- Untuk target `both`, mobile track (stage 10-13) menghasilkan phases, standards, agents & master prompt untuk platform mobile. Mobile track menunggu web track (`master_web`) selesai (gate).

## Input Awal
Sebelum tahap 1, user isi:
- **Ide** (deskripsi bebas)
- **Target**: `web` | `both`
- **Stack** (opsional; kosong = AI sarankan)
- **Template** (opsional) untuk pre-fill idea/target/stack

## 13 Tahap Pipeline

| # | Stage key | Nama | Input konteks | Output → DB column | Catatan |
|---|-----------|------|---------------|-------------------|---------|
| 1 | `pertanyaan` | Pertanyaan Klarifikasi | ide, target, stack | → `pertanyaan` (text) | Output disimpan ke DB. Jawaban user disimpan di `answers`. |
| 2 | `analisa` | Analisa & Klarifikasi | ide, target, stack, jawaban | `analysis` | |
| 3 | `prd` | PRD | analisa + jawaban, ide, target | `prd` | |
| 4 | `architecture` | Arsitektur & Tech Stack | PRD, target | `architecture` | |
| 5 | `erd` | ERD + API Contract | PRD, arsitektur | `erd` + `api_contract` (jsonb) | JSON `{nodes,edges,api_contract}`; API contract juga disimpan terpisah |
| 6 | `standards_web` | Web Standards | PRD, arsitektur, ERD | `standards` | STANDARDS.md web |
| 7 | `agents_web` | Web Agents | standards, PRD, arsitektur | `agents` | AGENTS.md web |
| 8 | `phases_web` | Web Phases | standards, agents, PRD, arsitektur, ERD | `phases` (jsonb) | Breakdown fase web (format `FASE:`, `TASK:`, `PROMPT:`) |
| 9 | `master_web` | Master Prompt Web | standards, agents, analisa, PRD, arsitektur, ERD | `master_prompt` | Self-contained master prompt web |
| 10 | `phases_mobile` | Mobile Phases | mobile_standards, PRD, arsitektur, ERD, master_web | `mobile_phases` (jsonb) | **Hanya target both.** Gate: menunggu `master_web` done. |
| 11 | `standards_mobile` | Mobile Standards | PRD, arsitektur, ERD, master_web | `mobile_standards` | STANDARDS.md mobile |
| 12 | `agents_mobile` | Mobile Agents | mobile_standards, master_web | `mobile_agents` | AGENTS.md mobile |
| 13 | `master_mobile` | Master Prompt Mobile | mobile_standards, mobile_agents, analisa, PRD, arsitektur, ERD, master_web | `mobile_master_prompt` | Self-contained master prompt mobile |

### Stage Keys (PipelineRunner ALL_STAGES)
```
pertanyaan → analisa → prd → architecture → erd → standards_web → agents_web → phases_web → master_web
→ phases_mobile → standards_mobile → agents_mobile → master_mobile
```
- Nilai `stage_status`: `pending` | `running` | `done` | `error`
- Kolom di DB untuk artifact: `pertanyaan`, `analysis`, `prd`, `architecture`, `erd`, `standards`, `agents`, `phases`, `master_prompt`, `mobile_phases`, `mobile_standards`, `mobile_agents`, `mobile_master_prompt`
- Kolom JSONB: `answers`, `erd`, `api_contract`, `phases`, `mobile_phases`

## Checkpoint
- Saat mode **checkpoint**: setelah tiap stage `done`, wizard berhenti. User dapat:
  - **Review** artefak.
  - **Approve & lanjut** ke stage berikut.
  - **Re-run** stage (regenerate).
  - **Edit inline** artifact — toggle view/edit, simpan via `PATCH /api/versions/{id}/artifacts`.
- Mode **auto-run** (default): langsung lanjut ke stage berikutnya tanpa berhenti; user review di akhir.

## Target-aware (contoh perbedaan)

| Aspek | Web | Mobile (APK/iOS) |
|-------|-----|------------------|
| Stack saran | Laravel 11 + Next.js + React 19 + Tailwind + PostgreSQL | Flutter + Dart + Riverpod + GoRouter + Material Design 3 |
| Arsitektur | SSR/SPA, routing web | screen/navigation, state mgmt, offline |
| ERD | tabel relasional | entity + storage lokal/remote |
| Phases | …→ deploy web/hosting | …→ build APK/IPA, signing, store submission |
| Master Prompt | instruksi lengkap untuk web stack | instruksi lengkap untuk mobile stack |

`Both` → dua jalur: tahap 1-9 untuk web, tahap 10-13 khusus mobile (phases, standards, agents, master). Gate: mobile menunggu `master_web` done.

## Tahap 8-9 — Kualitas Prompt (kunci nilai produk)
Prompt tiap phase **harus membawa konteks** phase sebelumnya (ringkasan PRD + arsitektur + apa yang sudah dibangun), agar AI agent tidak kehilangan benang merah. Master prompt = instruksi menyeluruh + urutan menjalankan phase. Semua prompt **copy-ready** dan bisa di-export (lihat [04-api-contract](04-api-contract.md) export).

## Progress
Tiap phase punya entri `phase_progress` (checkbox done) → progress bar project. Lihat [03-database-schema](03-database-schema.md).
