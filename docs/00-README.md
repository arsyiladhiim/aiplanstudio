# AI Planning Studio — Dokumentasi

> Peta dokumentasi & titik masuk untuk melanjutkan development kapan saja.

## Apa ini
**AI Planning Studio**: web app untuk **solo developer** mengubah satu ide menjadi **dokumentasi + rantai prompt lengkap** yang siap disuapkan ke AI coding agent (Claude Code/Cursor) untuk membangun aplikasi **Web** atau **Mobile (APK Android/iOS)**. App ini **tidak mengeksekusi kode**, hanya menghasilkan artefak perencanaan.

## Cara pakai dokumen ini
Saat memulai/melanjutkan sesi development, baca urut:
1. **[09-roadmap.md](09-roadmap.md)** — cek fase mana yang sedang/berikutnya dikerjakan. **Ini titik masuk utama.**
2. **[01-overview.md](01-overview.md)** — pastikan paham tujuan & scope.
3. Dokumen relevan dengan fase yang dikerjakan.
4. **[11-development-rules.md](11-development-rules.md)** — aturan wajib sebelum menulis kode.
5. **[15-dev-log.md](15-dev-log.md)** — catat setiap proses; **[12](12-security-checklist.md)/[13](13-backend-testing.md)/[14](14-frontend-testing.md)** untuk security & testing tiap fase.

## Daftar dokumen
| File | Isi |
|------|-----|
| [01-overview.md](01-overview.md) | Tujuan produk, target user, scope, non-scope |
| [02-architecture.md](02-architecture.md) | Topologi Docker, service, jaringan, BFF, pipeline 7 stages |
| [03-database-schema.md](03-database-schema.md) | Tabel, kolom, relasi, versioning, 3 schemas |
| [04-api-contract.md](04-api-contract.md) | Endpoint, payload, response, SSE, webhook |
| [05-wizard-flow.md](05-wizard-flow.md) | Alur wizard 7 tahap + checkpoint + target-aware |
| [06-ai-pipeline.md](06-ai-pipeline.md) | AiClient, PipelineRunner, prompt template, SSE |
| [07-docker-setup.md](07-docker-setup.md) | Compose, env, perintah build/migrate |
| [08-frontend.md](08-frontend.md) | Struktur Next.js, halaman, auth flow, BFF |
| [09-roadmap.md](09-roadmap.md) | Fase development + status checkbox |
| [10-decision-log.md](10-decision-log.md) | Keputusan penting + alasan |
| [11-development-rules.md](11-development-rules.md) | Aturan development wajib |
| [12-security-checklist.md](12-security-checklist.md) | Checklist keamanan per fase |
| [13-backend-testing.md](13-backend-testing.md) | Test backend Laravel (PHPUnit/Pest) |
| [14-frontend-testing.md](14-frontend-testing.md) | Test frontend Playwright + Chrome |
| [15-dev-log.md](15-dev-log.md) | Log setiap proses development |
| [16-audit-fix-plan.md](16-audit-fix-plan.md) | Rencana perbaikan hasil audit sinkronisasi |
| [17-next-progress.md](17-next-progress.md) | Next steps & rencana fitur |

**Dokumen di root:** [AUTH.md](../AUTH.md) (auth flow lengkap), [AGENTS.md](../AGENTS.md) (opencode rules).

## Status ringkas
- **Semua F0–F9, F10, R1, Phase 4 selesai** ✅
- **Backend:** Laravel 100% ✅ (~126 test functions)
- **Frontend:** Next.js UI 100% ✅ (17/17 pages, tsc 0 errors, lint 0 errors)
- **BFF Pattern:** nginx → Next.js → Laravel ✅
- **Docker:** 7 services (nginx, web, api, migrate, db, redis)
- **Pipeline:** 7 stages aktif ✅
- **Audit findings:** 73 items R1 selesai. 1 pending (RS-9 FPM).
- Next progress: lihat **[17-next-progress.md](17-next-progress.md)**

## Aturan Dokumen Realtime
> **WAJIB:** Sesudah mengubah kode, update dokumentasi terkait **terlebih dahulu** sebelum commit. Docs adalah sumber kebenaran. Lihat [11-development-rules.md](11-development-rules.md) rule #4.

## Quick Start (Non-Docker)
```bash
# Backend
cd api && php artisan serve --port=8000

# Frontend (terminal lain)
cd web && npm run dev

# Buka http://localhost:3000
```
## Quick Start (Non-Docker)
```bash
# Backend
cd api && php artisan serve --port=8000

# Frontend (terminal lain)
cd web && npm run dev

# Buka http://localhost:3000
```
Detail setup lengkap: [07-docker-setup.md → Development Tanpa Docker](07-docker-setup.md#development-tanpa-docker)
