# 05 — Wizard Flow ("Buat Plan")

> Lihat juga: [06-ai-pipeline](06-ai-pipeline.md) · [04-api-contract](04-api-contract.md) · [08-frontend](08-frontend.md)

## Prinsip
- **Wizard 6 tahap** dengan mode **auto-run** (default): semua tahap dijalankan berurutan tanpa henti, user review di akhir.
- Tersedia mode **checkpoint** (toggle): user bisa approve tiap tahap sebelum lanjut.
- **Target-aware**: output menyesuaikan `target` project (Web / Mobile / Both).
- **Target-aware**: output menyesuaikan `target` project (Web / Mobile / Both).
- Tiap tahap = 1 panggilan LLM streaming (SSE), output menjadi konteks tahap berikutnya. Hasil disimpan sebagai **Version**.

## Input Awal
Sebelum tahap 1, user isi:
- **Ide** (deskripsi bebas)
- **Target**: `web` | `mobile` | `both`
- **Stack** (opsional; kosong = AI sarankan)
- **Template** (opsional) untuk pre-fill idea/target/stack

## 6 Tahap
| # | Stage key | Nama | Input konteks | Output |
|---|-----------|------|---------------|--------|
| 1 | `analisa` | Analisa & Klarifikasi | ide, target, stack | ringkasan + daftar pertanyaan + asumsi |
| 2 | `prd` | PRD | analisa (+ jawaban user) | dokumen PRD markdown |
| 3 | `architecture` | Arsitektur & Tech Stack | PRD, target | struktur folder + pilihan library (beda Web/Mobile) |
| 4 | `erd` | ERD + API Contract | PRD, arsitektur | JSON `{nodes,edges}` (React Flow) + daftar endpoint |
| 5 | `phases` | Breakdown Phase/Task | PRD, arsitektur, ERD | roadmap fase (Setup→Auth→Fitur…→Deploy) + task |
| 6 | `master` | Master Prompt + Prompt/Phase | semua di atas | master prompt + prompt siap-copy tiap fase |

## Checkpoint
- Saat mode **checkpoint**: setelah tiap stage `done`, wizard berhenti. User dapat:
  - **Review** artefak.
  - **Approve & lanjut** ke stage berikut.
  - **Re-run** stage (regenerate).
  - **Edit** artefak inline — **belum diimplementasikan** (lihat [16-audit-fix-plan](16-audit-fix-plan.md#rw)).
- Mode **Auto-run** (default mode checkpoint): langsung lanjut ke stage berikut tanpa berhenti; user review di akhir.

## Target-aware (contoh perbedaan)
| Aspek | Web | Mobile (APK/iOS) |
|-------|-----|------------------|
| Stack saran | Next/React + Laravel/Node + Postgres | Flutter / React Native + REST + SQLite/Firebase |
| Arsitektur | SSR/SPA, routing web | screen/navigation, state mgmt, offline |
| ERD | tabel relasional | entity + storage lokal/remote |
| Phase | …→ deploy web/hosting | …→ build APK/IPA, signing, store submission |

`Both` → hasilkan dua jalur (web & mobile) di artefak yang relevan.

## Tahap 6 — Kualitas Prompt (kunci nilai produk)
Prompt tiap phase **harus membawa konteks** phase sebelumnya (ringkasan PRD + arsitektur + apa yang sudah dibangun), agar AI agent tidak kehilangan benang merah. Master prompt = instruksi menyeluruh + urutan menjalankan phase. Semua prompt **copy-ready** dan bisa di-export (lihat [04-api-contract](04-api-contract.md) export).

## Progress
Tiap phase punya entri `phase_progress` (checkbox done) → progress bar project. Lihat [03-database-schema](03-database-schema.md).
