# 13 — Backend Testing (Laravel)

> Lihat juga: [04-api-contract](04-api-contract.md) · [12-security-checklist](12-security-checklist.md) · [15-dev-log](15-dev-log.md)
> Framework: **PHPUnit / Pest** (bawaan Laravel). Jalankan di container. Tiap fase punya test; hasil dicatat di [15-dev-log](15-dev-log.md).

## Prinsip
- **Feature test dulu** (uji endpoint end-to-end lewat HTTP) — paling bernilai untuk API. Unit test hanya untuk logika murni (validator ERD JSON, versioning).
- DB test terpisah (sqlite in-memory atau db test) + `RefreshDatabase`.
- AI Provider **di-mock** (jangan panggil provider asli saat test) — fake response streaming.
- Setiap logika non-trivial tinggalkan minimal 1 test runnable (aturan [11-development-rules](11-development-rules.md) #25).

## Menjalankan
```bash
docker compose exec api php artisan test
docker compose exec api php artisan test --filter=AuthTest
```

## Cakupan per Fase

### F3 — Auth & RBAC
- [ ] register: buat user, validasi email unik, password ter-hash.
- [ ] login sukses set sesi; login gagal → 422/401; throttle setelah N gagal.
- [ ] logout invalidasi sesi.
- [ ] `GET /api/user` tanpa auth → 401.
- [ ] middleware `role.admin`: member akses `/api/settings/*` → 403.
- [ ] IDOR: user A akses project user B → 403/404.

### F4 — Settings
- [ ] admin PUT provider → tersimpan; GET mengembalikan `api_key` **masked**.
- [ ] api_key tersimpan encrypted (nilai DB ≠ plaintext).
- [ ] test koneksi provider (mock) sukses/gagal ditangani.
- [ ] user CRUD: buat/ubah role/hapus; non-admin ditolak.

### F5 — Pipeline
- [ ] `AiClient` di-mock → `PipelineRunner::run` menyimpan artefak ke kolom `versions` yang benar.
- [ ] `stage_status` berubah `running`→`done`.
- [ ] Validator ERD JSON: input valid → tersimpan; **input invalid → ditolak/retry, tak simpan sampah**.
- [ ] Validator phases JSON serupa.
- [ ] Endpoint SSE `/api/generate/stream` mengembalikan `text/event-stream` + event berurutan (uji dengan fake stream).
- [ ] `auto=1` menjalankan seluruh stage; `auto=0` berhenti setelah 1 stage.

### F7 — Projects, Versioning, Export
- [ ] `POST /projects/{id}/versions` → version_no bertambah; versi lama tetap ada (snapshot).
- [ ] `PATCH phases/{key}` toggle `done`; progress terhitung benar.
- [ ] Export `?format=md` menghasilkan konten; `?format=zip` menghasilkan file.
- [ ] Cascade delete project → versions → phase_progress.

## Definition of Done (backend)
- Semua feature test fase hijau (`artisan test` exit 0).
- Security checklist bagian terkait ([12-security-checklist](12-security-checklist.md)) lulus.
- Hasil (jumlah test, pass/fail) dicatat di [15-dev-log](15-dev-log.md).
