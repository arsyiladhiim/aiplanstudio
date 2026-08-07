# 05 — Wizard Flow ("Buat Plan")

> Lihat juga: [06-ai-pipeline](06-ai-pipeline.md) · [04-api-contract](04-api-contract.md) · [08-frontend](08-frontend.md)

## Prinsip
- **Wizard 7 tahap** dengan mode **auto-run** (default): semua tahap dijalankan berurutan tanpa henti, user review di akhir.
- Tersedia mode **checkpoint** (toggle): user bisa approve tiap tahap sebelum lanjut.
- **Target-aware**: output menyesuaikan `target` project (Web / Mobile / Both).
- Tiap tahap = 1 panggilan LLM streaming (SSE), output menjadi konteks tahap berikutnya. Hasil disimpan sebagai **Version**.
- Untuk target `both`, tahap ke-7 (`phased_master_mobile`) menghasilkan phases & master prompt untuk platform mobile.

## Input Awal
Sebelum tahap 1, user isi:
- **Ide** (deskripsi bebas)
- **Target**: `web` | `mobile` | `both`
- **Stack** (opsional; kosong = AI sarankan)
- **Template** (opsional) untuk pre-fill idea/target/stack

## 7 Tahap Pipeline

| # | Stage key | Nama | Input konteks | Output → DB column | Catatan |
|---|-----------|------|---------------|-------------------|---------|
| 1 | `pertanyaan` | Pertanyaan Klarifikasi | ide, target, stack | → `pertanyaan` (text) | Output disimpan ke DB. Jawaban user disimpan di `answers`. |
| 2 | `analisa` | Analisa & Klarifikasi | ide, target, stack, jawaban | `analysis` | |
| 3 | `prd` | PRD | analisa + jawaban, ide, target | `prd` | |
| 4 | `architecture` | Arsitektur & Tech Stack | PRD, target | `architecture` | |
| 5 | `erd` | ERD + API Contract | PRD, arsitektur | `erd` + `api_contract` (jsonb) | JSON `{nodes,edges,api_contract}`; API contract juga disimpan terpisah |
| 6 | `phased_master` | Breakdown Phase & Master Prompt | PRD, arsitektur, ERD | `phases` (jsonb), `master_prompt`, `standards`, `agents` | Parsed via separator `===PHASES===`, `===MASTER===`, `===STANDARDS===`, `===AGENTS===` |
| 7 | `phased_master_mobile` | Mobile Phases & Master Prompt | sama konteks | `mobile_phases`, `mobile_master_prompt`, `mobile_standards`, `mobile_agents` | **Hanya** untuk target `both`. Skip otomatis untuk target `web`/`mobile`. |

### Stage Keys (PipelineRunner ALL_STAGES)
```
pertanyaan → analisa → prd → architecture → erd → phased_master → phased_master_mobile
```
- Nilai `stage_status`: `pending` | `running` | `done` | `error`
- Kolom di DB untuk artifact: `analysis`, `prd`, `architecture`, `erd`, `master_prompt`, `mobile_master_prompt`
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

`Both` → hasilkan dua jalur: tahap 1-6 untuk web, tahap 7 khusus mobile phases/master prompt.

## Tahap 6 — Kualitas Prompt (kunci nilai produk)
Prompt tiap phase **harus membawa konteks** phase sebelumnya (ringkasan PRD + arsitektur + apa yang sudah dibangun), agar AI agent tidak kehilangan benang merah. Master prompt = instruksi menyeluruh + urutan menjalankan phase. Semua prompt **copy-ready** dan bisa di-export (lihat [04-api-contract](04-api-contract.md) export).

## Progress
Tiap phase punya entri `phase_progress` (checkbox done) → progress bar project. Lihat [03-database-schema](03-database-schema.md).
