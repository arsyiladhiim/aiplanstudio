# 42 — Bank Ide — COMPLETED

> Data research ide sudah permanen di DB (`research_ideas`) — halaman ini mengeksposnya penuh + aksi "Buat Proyek".

## Keputusan

| # | Aspek | Keputusan |
|---|-------|-----------|
| 1 | Lokasi menu | Main nav (admin-only), icon `Lightbulb` |
| 2 | Fitur Buat Proyek | Ya — kirim `idea_title` + `idea_text` (gabungan Kendala/Solusi/Target) via query string ke `/new` |
| 3 | Data | Tidak ada migration baru; endpoint `/api/research/ideas` diperluas dengan query params |

## STATUS CHECKPOINT

- [x] CP-0 Dokumen plan ini
- [x] CP-1 Backend: `ResearchAgentController::ideas` params `q`, `date_from`, `date_to`, `?page` paginate(20), backward-compat tanpa params
- [x] CP-2 Test: search, filter tanggal, paginasi, authz member 403
- [x] CP-3 FE `/ideas`: halaman admin-only, search debounce, filter tanggal, grid kartu, paginasi, tombol Buat Proyek
- [x] CP-4 FE nav: item "Bank Ide" (admin-only) di AppShell
- [x] CP-5 FE `/new`: prefill dari `idea_title`/`idea_text`; link "Lihat semua →" di dashboard card
- [x] CP-6 Verifikasi: `php artisan test`, pint, `npm run lint`, `npx tsc --noEmit`, rebuild `aiplanstudio_web`
- [x] CP-7 Docs final + commit

## Definition of Done

- Admin buka `/ideas` → list semua ide, bisa search/filter tanggal/paginasi
- Member akses `/ideas` → 403 / not-found
- Klik "Buat Proyek" → redirect `/new` dengan judul & ide terisi
- Dashboard card "Ide Research" ada link "Lihat semua →" ke `/ideas`
- Semua test hijau, build bersih
