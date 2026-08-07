# 11 — Development Rules

> Aturan wajib untuk semua pekerjaan di project ini. Baca sebelum menulis kode. Lihat juga: [09-roadmap](09-roadmap.md) · [10-decision-log](10-decision-log.md)

## A. Alur Kerja & Resumability
1. **Selalu mulai dari [09-roadmap](09-roadmap.md)** — kerjakan hanya fase aktif; jangan lompat fase tanpa alasan.
2. **Update roadmap** setiap sub-task selesai (ubah `[ ]`→`[~]`→`[x]`) dan tanggal "terakhir diupdate".
3. **Keputusan baru dicatat** di [10-decision-log](10-decision-log.md) (dengan alasan + alternatif ditolak).
4. **Dokumen adalah sumber kebenaran.** Sebelum mengubah kode, update dokumentasi terkait terlebih dahulu. Docs di-commit bersamaan atau sebelum kode. Jangan pernah biarkan docs basi terhadap kode yang sudah berubah. Lihat [17-next-progress.md](17-next-progress.md) untuk next steps aktif.
5. Satu fase = unit yang bisa diverifikasi berdiri sendiri (lihat kriteria "Selesai bila" tiap fase).

## B. Docker & Infrastruktur
6. **Semua service jalan di Docker.** Tidak menjalankan Laravel/Next/DB langsung di host.
7. **Hanya `nginx` yang boleh publish port** ke host. Service lain pakai `expose:` (internal). Dilarang menambah `ports:` pada db/api/web/redis.
8. **Antar-service pakai nama container** (`db`, `api`, `web`, `redis`) — bukan `localhost`/IP.
9. **DB tidak ter-expose** ke host. Akses DB lewat `docker compose exec db ...`.
10. Perintah artisan/npm dijalankan **di dalam container** (`docker compose exec api ...`, `... web ...`).

## C. Keamanan
11. **Rahasia tak pernah ke client.** API key AI Provider hanya dipakai backend; pada response API selalu **masked**.
12. `ai_providers.api_key` disimpan **encrypted** (cast Laravel). Jangan log nilai mentah.
13. **Jangan hardcode rahasia** di repo. Gunakan env; commit `.env.example` saja.
14. **Scoping kepemilikan:** semua query Project/Version difilter `user_id` pemilik. Endpoint admin dijaga middleware `role.admin`.
15. Validasi semua input di trust boundary (request Laravel `FormRequest`/Validator). Jangan percaya body client.
16. Error yang dikirim ke client **tidak** membocorkan detail rahasia/stack sensitif.

## D. AI Pipeline
17. **Jangan percaya output AI mentah.** Stage yang menghasilkan JSON (`erd`, `phases`) wajib divalidasi (`json_decode` + cek struktur). Bila invalid → retry sekali atau tandai error, jangan simpan sampah.
18. Prompt tiap **phase harus membawa konteks** phase sebelumnya (jaga benang merah).
19. Prompt **target-aware** (Web/Mobile/Both) — jangan campur aduk output antar target.
20. SSE: pastikan buffering dimatikan di nginx & response Laravel (`X-Accel-Buffering: no`) agar realtime.

## E. Kualitas Kode
21. **Lazy/YAGNI:** tulis kode minimum yang bekerja. Tanpa abstraksi yang belum dibutuhkan. Manfaatkan fitur bawaan Laravel/Next sebelum bikin sendiri.
22. Ikuti konvensi framework: Laravel (Eloquent, FormRequest, Resource, migration) & Next (App Router, server/client component sesuai kebutuhan).
23. **Non-scope tetap non-scope** (lihat [01-overview](01-overview.md)) kecuali user minta — jangan menambah fitur sendiri.
24. **Verifikasi sebelum klaim selesai.** Jalankan langkah "Selesai bila" tiap fase; laporkan apa adanya bila gagal.
25. Tiap logika non-trivial (mis. validator ERD JSON, versioning) tinggalkan **satu cek runnable** (feature/unit test kecil) — tanpa framework berat baru.

## F. Bahasa & Dokumentasi
26. Dokumentasi & komentar penting dalam **Bahasa Indonesia** (konsisten dengan docs ini).
27. Link antar dokumen memakai path relatif agar navigasi tetap hidup.
28. Jaga docs **ringkas tapi cukup untuk eksekusi**; hindari duplikasi — rujuk dokumen lain.

## G. Git/Commit (bila dipakai)
29. Commit per fase/sub-task yang koheren; pesan jelas menyebut fase (mis. `F3: auth sanctum + guard`).
30. Jangan commit rahasia, `node_modules`, `vendor`, volume DB.

## H. Testing, Keamanan & Log (wajib tiap fase)
31. **Backend:** tiap fase punya feature/unit test yang lulus sebelum lanjut ([13-backend-testing](13-backend-testing.md)). AI Provider selalu di-mock saat test.
32. **Frontend:** uji dengan **Playwright di Chromium/Chrome** — **setiap button & menu diklik & diverifikasi** ([14-frontend-testing](14-frontend-testing.md)).
33. **Loop perbaikan:** bila test/ browser menemukan error → perbaiki → jalankan ulang → **ulangi sampai hijau**. Fase tak boleh ditandai selesai selama masih ada test merah.
34. **Security checklist** ([12-security-checklist](12-security-checklist.md)) bagian terkait fase harus lulus sebelum fase selesai.
35. **Catat setiap proses development** di [15-dev-log](15-dev-log.md): apa dikerjakan, perintah, hasil test, kendala, perbaikan. Termasuk saat gagal.
36. Gunakan `data-testid` pada elemen interaktif kunci agar test browser stabil.
