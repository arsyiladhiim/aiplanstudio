# 41 — Research Agent (Scheduled Idea Bank) — Plan

> Fitur: agent terjadwal yang research demand/trend di berbagai bidang via **web search nyata**, menghasilkan minimal **5 ide digitalisasi/hari** (window 24 jam, anchor 06:30 WIB server), tampil di dashboard + halaman pengaturan khusus.

## Keputusan (dari user)

| # | Aspek | Keputusan |
|---|-------|-----------|
| 1 | Akses | **Admin only** (halaman & endpoint gated role admin) |
| 2 | Topik research | Bebas ke AI (AI pilih bidang berdasar trend hasil search) |
| 3 | Provider AI | Admin set **1 provider khusus** agent (FK ke AiProvider) |
| 4 | Search engine | **Tavily (default)** + **Brave Search API** switchable. Keduanya free tier. SerpAPI tidak diimplementasikan |
| 5 | Max ide/hari | Default 5, configurable, hard cap 50. Dedup `LOWER(title)` per window |
| 6 | Scheduler | Hourly `research:collect` + sweep 06:00; window 06:30→06:30 |

## Arsitektur

```
scheduler container (php artisan schedule:work)
  → hourly: research:collect
      → ResearchAgentService::collect()
        1. quota = max_per_day - ideas window ini; skip jika 0
        2. pilih AI provider (settings.ai_provider_id → AiClient)
        3. generateQuery() via AI → 1 query trend
        4. WebSearchClient::search() (tavily|brave) → 5 snippets
        5. AI parse → N ide JSON {title,target_users,problem,solution}
        6. simpan ke research_ideas (dedup judul), Activity::log
```

- Key search disimpan **encrypted** di DB (`research_settings`), SSRF-safe: hardcoded endpoint Tavily/Brave (tidak ambil URL dari input).
- Command idempotent + silent-skip bila config belum ada (tidak crash loop).

## STATUS CHECKPOINT

- [x] CP-0 Dokumen plan ini
- [x] CP-1 Fix TwoFactorController import (bug ditemukan saat audit)
- [x] CP-2 Migration `research_ideas` + `research_settings` (schema settings)
- [x] CP-3 `WebSearchClient` (Tavily/Brave)
- [x] CP-4 `ResearchAgentService` + prompts
- [x] CP-5 Command `research:collect` + schedule (hourly + 06:00) + `routes/console.php`
- [x] CP-6 Docker: service `scheduler` (schedule:work)
- [x] CP-7 Controller + routes `/api/research/*` (admin): ideas list, settings get/patch, test-search, run-now
- [x] CP-8 Tests: service (HTTP fake), command, endpoint authz, settings validation
- [x] CP-9 FE: types api.ts + dashboard card (admin-only) + badge N/5
- [x] CP-10 FE: halaman `/settings/research-agent` (admin): toggle, provider search + key (masked/test), AI provider dropdown, max/hari, run-now, status run terakhir, history table
- [x] CP-11 Verifikasi penuh: artisan test, pint, lint, tsc; docs update; commit

## Definition of Done

- Dashboard admin menampilkan kolom ide research hari ini (0–5, bertambah tiap jam)
- Pukul 06:00 sweep memastikan ≥5 ide terkumpul (bila search key + AI provider valid)
- Settings page: admin bisa switch Tavily/Brave, set key, pilih AI provider, run now
- Non-admin: 403 di semua `/api/research/*`, menu tidak tampil
- Semua test hijau; lint/tsc clean

## Batasan yang disengaja (ponytail)

- Tidak ada UI idea→project conversion (ide cuma dibaca). Upgrade path: tombol "Buat Proyek dari Ide" → prefill `/new`.
- History table halaman settings = list 30 terakhir tanpa paginasi penuh. Upgrade bila bank > ratusan.
