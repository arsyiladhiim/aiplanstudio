# 10 — Decision Log

> Catatan keputusan penting + alasan. Tambah entri baru di atas (terbaru dulu). Format: tanggal · keputusan · alasan · alternatif ditolak.

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
