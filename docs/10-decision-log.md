# 10 — Decision Log

> Catatan keputusan penting + alasan. Tambah entri baru di atas (terbaru dulu). Format: tanggal · keputusan · alasan · alternatif ditolak.

## 2026-08-08

### D-029 · Versi Baru Clone Baseline (pengembangan lanjutan, bukan mulai nol)
- **Keputusan:** `POST /projects/{id}/versions` default `strategy=from_last` — menyalin artefak, jawaban (`answers`/`mobile_answers`) & `stage_status` dari versi terakhir; kolom `source_version_id` + `baseline_notes` mencatat asal. Opsi `blank` utk rencana baru murni.
- **Alasan:** Sebelumnya versi baru selalu kosong → user harus jalankan ulang seluruh pipeline untuk "revisi/update". Clone baseline membuat diff antar versi bermakna dan pengembangan berlanjut.
- **Ditolak:** Tetap versi kosong (revisi tidak praktis); sinkronisasi otomatis kode dari CLI (di luar tujuan planning-tool).

### D-028 · Pipeline 13-Stage Overhaul (split phased_master, hapus target mobile)
- **Keputusan:** Split `phased_master` (1 stage → 4 artifact) menjadi 4 stage terpisah: `standards_web`, `agents_web`, `phases_web`, `master_web`. Begitu juga `phased_master_mobile` → `phases_mobile`, `standards_mobile`, `agents_mobile`, `master_mobile`. Total 13 stage (both) / 9 stage (web). Hapus target `mobile` standalone (mobile butuh API/web). Gate: mobile track menunggu `master_web` done.
- **Alasan:** `phased_master` overload — 1 panggilan AI menghasilkan 4 artifact, token terbagi, hasil parsial. Master prompt web & mobile hampir sama. Mobile tidak menunggu web selesai.
- **Ditolak:** Tetap 7 stage (token terbagi, hasil parsial); target `mobile` standalone (mobile butuh API backend web).
- **Docs:** [docs/19-pipeline-13stage.md](19-pipeline-13stage.md)

## 2026-08-06

### D-025 · Sinkronisasi dokumentasi menyeluruh Phase 2
- **Keputusan:** Perbaikan gap docs vs code yang ditemukan setelah Phase 1: 7 stages pipeline (vs "6 tahap"), schema mobile artifacts, API contract endpoints, RS-7 false-positive, dan decision log duplicates.
- **Alasan:** Dokumentasi harus akurat dan mencerminkan realitas kode untuk resume development yang reliable.
- **Ditolak:** Membiarkan docs outdated (risiko misguidance di sesi berikutnya).

### D-018 · Dashboard analytics endpoint replaces client-side computation
- **Keputusan:** New `GET /api/dashboard/stats` endpoint in ProjectController returns server-computed stats (total_projects, total_versions, active_projects, projects_this_week, versions_this_week, recent_projects).
- **Alasan:** Client-side computation from `/api/projects` was inaccurate (missed versions that belong to projects with no versions_count). Server-side query is authoritative.
- **Ditolak:** Keeping client-side aggregation.

### D-019 · Inline artifact editing: single PATCH endpoint for all stages
- **Keputusan:** `PATCH /api/versions/{id}/artifacts` with `{stage, content}` body maps stage key (standards_web/agents_web/phases_web/master_web/phases_mobile/standards_mobile/agents_mobile/master_mobile) to the correct DB column. ERD content is JSON-decoded before storage.
- **Alasan:** One endpoint for all artifact types is simpler than separate endpoints.
- **Ditolak:** Separate endpoints per stage type.

### D-020 · Version diff: GET endpoint with `?compare=` query param
- **Keputusan:** `GET /api/versions/{id}/diff?compare={otherId}` returns a structured diff of all 10+ artifact fields with `changed` boolean.
- **Alasan:** Side-by-side comparison is the most intuitive way to review changes between versions.
- **Ditolak:** JS-based diff on frontend (inconsistent with SSR); unified diff format (less readable).

### D-021 · Activity Log menggunakan tabel sendiri (bukan log laravel)
- **Keputusan:** Migration `create_activities_table` dengan `project_id`, `user_id`, `action`, `description`, `metadata` (jsonb). `Project::logActivity()` helper.
- **Alasan:** Fitur-specific, perlu query per-project dengan pagination. Metadata jsonb untuk data polymorphic.
- **Ditolak:** Menggunakan Logging Laravel (`\Log::info`); event sourcing dengan package eksternal.

### D-022 · Search menggunakan `ilike` (bukan full-text search)
- **Keputusan:** `ProjectController::index()` filter `WHERE title ILIKE ? OR idea ILIKE ?` untuk query param `q`.
- **Alasan:** PostgreSQL `ilike` sudah cukup untuk search sederhana. Tidak perlu `tsvector` untuk scale saat ini.
- **Ditolak:** PostgreSQL full-text search (`to_tsvector`/`to_tsquery`); Elasticsearch.

### D-023 · `setTargetAndReset()` helper menggantikan ref-based tracking
- **Keputusan:** Fungsi helper di `new/page.tsx` yang memanggil `setTarget()` + `setStatus()` sekaligus, menggantikan `prevTargetRef.current` render-time tracking.
- **Alasan:** React 19 `react-hooks/refs` rule melarang baca/tulis `ref.current` saat render. Helper lebih clean dan type-safe.
- **Ditolak:** `useEffect` untuk sync status (kena `set-state-in-effect` rule).

### D-024 · `questions` sebagai `useMemo` derived value (bukan state)
- **Keputusan:** Menghilangkan `questions`/`setQuestions` state, menggantinya dengan `const questions = useMemo(...)` yang mengekstrak dari `artifacts.pertanyaan`.
- **Alasan:** React 19 `set-state-in-effect` rule mencegah `setQuestions` di dalam effect. Nilai questions selalu derivatif dari artifact, jadi useMemo lebih tepat.
- **Ditolak:** Menyimpan questions di ref; memparsing di event handler.

### D-026 · Project API tokens: BFF routes + UI in project detail
- **Keputusan:** Tokens are managed via 3 routes (GET/POST/DELETE) under `/api/projects/{id}/tokens`. UI embedded as a collapsible card in the project detail page.
- **Alasan:** Tokens are project-scoped, not user-scoped. Embedding in project detail keeps UX simple without a separate page.
- **Ditolak:** Separate tokens management page.

### D-027 · Fallback artifact fetcher: single batch request instead of per-stage loop
- **Keputusan:** Changed the `useEffect` fallback fetcher to collect all missing stages first, then make a single `/versions/{id}` call and set all artifacts at once.
- **Alasan:** Original code had a `break` that caused only the first missing stage to be fetched. The single-fetch approach also reduces network calls.
- **Ditolak:** Keeping per-stage loop with proper `break` removal (still N network calls).

## 2026-07-26

### D-016 · Kembali ke Sanctum SPA Session Auth (Cookies + CSRF)
- **Keputusan:** Batalkan D-012 (Migrasi ke Bearer Token). Kembali ke Sanctum SPA Session auth (HttpOnly cookies + CSRF).
- **Alasan:** Audit sinkronisasi menemukan bahwa implementasi Bearer Token hanya ada di dokumentasi & decision log — kode aktual masih menggunakan SPA Session Auth. Alih-alih migrasi ulang, yang lebih efisien adalah sinkronisasi dokumentasi dengan kode aktual. Ini menghindari rewrite besar-besaran di frontend, backend, dan BFF.
- **Ditolak:** Migrasi paksa ke Bearer Token (banyak kerja ulang di 17 BFF routes + frontend auth flow + backend middlewares).

### D-017 · Decision Log D-012/D-013 dianggap tidak berlaku
- **Keputusan:** D-012 (Bearer Token) dan D-013 (sessionStorage) dibatalkan. Kode tetap menggunakan SPA Session Auth.
- **Alasan:** D-012 dan D-013 mencatat keputusan yang tidak pernah diimplementasikan di kode. Dokumentasi dan decision log harus mencerminkan realitas kode.
- **Ditolak:** Menghapus entri dari log (riwayat keputusan tetap penting untuk audit trail).

## 2026-07-24

### D-012 · Migrasi Auth: Session Cookie → Bearer Token (Pure Token)
- **Keputusan:** Hapus semua session cookie, CSRF, dan Sanctum SPA. Gunakan **PersonalAccessTokens** sepenuhnya.
- **Alasan:** Tidak ada cookies → tidak perlu CSRF. Bearer token kebal CSRF otomatis.
- **Ditolak:** Keep SPA cookie + CSRF (lebih kompleks).

### D-013 · Token storage: sessionStorage, bukan localStorage
- **Keputusan:** Token disimpan di `sessionStorage`.
- **Alasan:** Hilang saat tab ditutup → kurangi XSS persist window.
- **Ditolak:** localStorage (persist tak terbatas).

### D-014 · Security headers nginx: CSP + HSTS + Permissions-Policy
- **Keputusan:** Tambah Content-Security-Policy, Strict-Transport-Security, Permissions-Policy di nginx.
- **Alasan:** CSP cegah XSS. HSTS paksa HTTPS. Permissions-Policy batasi API browser.
- **Ditolak:** Tidak menambah header.

## 2026-07-23

### D-010 · Service token removed — BFF hanya pakai cookie
- **Keputusan:** Routing diubah dari nginx→api langsung menjadi nginx→web (BFF) → api. Nginx hanya route `/` dan `/_next/` ke `web:3000`. Semua `/api/*` ditangani Next.js proxy.
- **Alasan:** Arsitektur BFF memberikan kontrol header/cookie yang lebih baik, consistent error handling, dan security boundary yang jelas antara public dan internal.
- **Ditolak:** nginx→api langsung (lebih sederhana tapi tidak punya control layer).

## 2026-07-22

### D-009 · Testing wajib tiap fase + Playwright/Chrome + dev-log
- **Keputusan:** Tiap fase butuh backend test (PHPUnit/Pest) + frontend E2E **Playwright di Chrome** yang mengklik setiap button/menu; error diperbaiki loop **sampai fix**. Security checklist per fase. Setiap proses dicatat di `15-dev-log.md`.
- **Alasan:** Permintaan user; jaminan tiap elemen UI benar-benar berfungsi di browser nyata; jejak development resumable & auditable.
- **Ditolak:** test manual saja / tanpa browser nyata (rawan meleset elemen interaktif).

### D-008 · Dokumentasi dibuat lebih dulu (docs-first)
- **Keputusan:** Sebelum coding, buat set dokumentasi terstruktur di `docs/` + aturan development.
- **Alasan:** Project besar & multi-service; user ingin development **resumable** — bisa dilanjut sesuai plan meski terputus.
- **Ditolak:** langsung coding (rawan kehilangan arah antar sesi).

### D-007 · Alur wizard + checkpoint, target-aware
- **Keputusan:** Menu "Buat Plan" = wizard 6 tahap dengan checkpoint approve tiap tahap (+ opsi auto-run). Output menyesuaikan target Web/Mobile/Both.
- **Alasan:** Solo dev butuh titik koreksi; kalau analisa awal meleset, artefak downstream ikut salah. Target berbeda butuh stack/ERD/prompt berbeda.
- **Ditolak:** auto-run penuh tanpa henti (akurasi rendah); web-only (tak sesuai tujuan Web+Mobile).

### D-006 · Backend Laravel, frontend Next.js, full Docker, hanya nginx expose
- **Keputusan:** Backend REST = Laravel; frontend = Next.js; semua service Docker; komunikasi antar-service via nama container; hanya nginx publish port ke host.
- **Alasan:** Permintaan eksplisit user; isolasi jaringan lebih aman; satu titik masuk.
- **Ditolak:** Next.js API routes sebagai backend utama; expose banyak port.

### D-005 · Hapus DB lama, buat ulang dari 0
- **Keputusan:** `docker compose -p aistack down -v` lalu migrasi fresh.
- **Alasan:** Permintaan user; skema baru; hindari sisa data lama.

### D-004 · Auth Sanctum SPA cookie; AI dipanggil backend; nginx 1 domain path-based
- **Keputusan:** Sanctum SPA (cookie httpOnly + CSRF); Laravel yang memanggil AI Provider & streaming SSE; nginx route `/`→web, `/api`+`/sanctum`→api.
- **Alasan:** Same-origin → cookie mulus tanpa CORS; rahasia AI tetap di backend; token tak terekspos ke JS.
- **Ditolak:** token Bearer di localStorage (rawan XSS); AI dipanggil dari frontend (bocor key).

### D-003 · Postgres + AI Provider global (admin)
- **Keputusan:** DB Postgres (container existing di-reuse image, volume baru). AI Provider = 1 config global diatur admin.
- **Alasan:** Postgres sudah tersedia; provider global lebih simpel untuk MVP multi-user.
- **Ditolak:** per-user provider (lebih banyak UI, ditunda).

### D-002 · Multi-user + versioning snapshot
- **Keputusan:** Multi-user + auth + role admin/member; "update ke Versi 2" = snapshot Version baru, riwayat disimpan.
- **Alasan:** Permintaan user (User Management, Projects dengan versi).
- **Ditolak:** single-user localStorage; overwrite tanpa riwayat.

### D-001 · Produk = generator dokumentasi/prompt (bukan eksekutor)
- **Keputusan:** App menghasilkan dokumen & prompt; eksekusi kode dilakukan AI agent eksternal.
- **Alasan:** Tujuan produk membantu solo dev menyiapkan input untuk AI agent; menjaga scope.
- **Ditolak:** app mengeksekusi/menulis kode nyata (agentic loop) — di luar scope MVP.
