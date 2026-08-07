# 13 — Backend Testing (Laravel)

> Lihat juga: [04-api-contract](04-api-contract.md) · [12-security-checklist](12-security-checklist.md) · [15-dev-log](15-dev-log.md)
> Framework: **PHPUnit / Pest** (bawaan Laravel). Jalankan di container. Tiap fase punya test; hasil dicatat di [15-dev-log](15-dev-log.md).

## Prinsip
- **Feature test dulu** (uji endpoint end-to-end lewat HTTP) — paling bernilai untuk API. Unit test hanya untuk logika murni (validator ERD JSON, versioning, model methods).
- DB test terpisah (sqlite in-memory + `RefreshDatabase`).
- AI Provider **di-mock** (jangan panggil provider asli saat test) — fake response streaming via `Http::fake()`.
- Setiap logika non-trivial tinggalkan minimal 1 test runnable.

## Menjalankan
```bash
docker compose exec api php artisan test
docker compose exec api php artisan test --filter=AuthTest
docker compose exec api php artisan test --filter=VersionTest
```

## Test Files Aktual
| File | Test | Status |
|------|------|--------|
| `tests/Feature/AuthTest.php` | register, login, logout, user, RBAC, IDOR | ✅ |
| `tests/Feature/ProjectTest.php` | CRUD, favorites, search, user scoping | ✅ |
| `tests/Feature/VersionTest.php` | store, show, togglePhase, export, diff | ✅ |
| `tests/Feature/GenerateStreamTest.php` | validation, SSE format | ✅ |
| `tests/Feature/SettingsTest.php` | provider CRUD, user CRUD, RBAC | ✅ |
| `tests/Feature/TemplateTest.php` | index, store, destroy | ✅ |
| `tests/Feature/HealthCheckTest.php` | /health endpoint | ✅ |
| `tests/Feature/AiClientTest.php` | streaming, mock SSE, HTTP mocking | ✅ |
| `tests/Feature/PipelineRunnerTest.php` | stages, JSON validation, defaults | ✅ |
| `tests/Feature/SocialiteControllerTest.php` | Google OAuth fake | ✅ |
| `tests/Feature/PasswordResetTest.php` | forgot/reset password | ✅ |
| `tests/Unit/ModelTest.php` | isAdmin, isActive, isPending, maskedKey, nextVersionNo | ✅ |

Total: **~126 test functions** (grep count).

## Cakupan per Feature

### F3 — Auth & RBAC
- [x] register: buat user, validasi email unik, password ter-hash, password_confirmation
- [x] register non-pertama: status=pending, tidak auto-login
- [x] login sukses set sesi; login gagal → 422; throttle aktif
- [x] login user pending → tolak dengan pesan generik
- [x] logout invalidasi sesi
- [x] `GET /api/user` tanpa auth → 401
- [x] middleware `role.admin`: member akses `/api/settings/*` → 403
- [x] IDOR: user A akses project user B → 404

### F4 — Settings
- [x] admin GET provider → list dengan api_key masked
- [x] admin POST/PATCH/DELETE provider → tersimpan
- [x] api_key tersimpan encrypted (nilai DB ≠ plaintext)
- [x] test koneksi provider (mock) sukses/gagal ditangani
- [x] user CRUD: buat/ubah role/status/hapus; non-admin ditolak; admin tidak bisa delete diri sendiri
- [x] Profile: GET/PATCH profile

### F5 — Pipeline
- [x] `AiClient` mock → `PipelineRunner::run` menyimpan artefak ke kolom benar
- [x] `stage_status` berubah `running`→`done`
- [x] `Version::defaultStageStatus()` → 7 keys
- [x] Validator ERD JSON: valid → tersimpan; invalid → retry → tetap gagal → error
- [x] `parseErdText`, `parsePhasesText`, `parsePhasedMaster` — multi-strategy decode
- [x] `parseArchText` — component/edge parsing
- [x] Endpoint SSE mengembalikan `text/event-stream` + event berurutan
- [x] `auto=1` menjalankan seluruh stage; `auto=0` berhenti setelah 1 stage
- [x] Continuation: finish_reason=length → prompt lanjutan
- [x] 7 stages: pertanyaan→analisa→prd→architecture→erd→phased_master→phased_master_mobile

### F7 — Projects, Versioning, Export
- [x] `POST /projects/{id}/versions` → version_no bertambah
- [x] `PATCH phases/{key}` toggle `done`; progress terhitung benar
- [x] Export `?format=md` menghasilkan konten; `?format=zip` menghasilkan file ZIP
- [x] Export invalid format → 422
- [x] Cascade delete project → versions → phase_progress
- [x] Favorites: toggle is_favorite
- [x] Search: `q` + `favorite` params
- [x] Activity Log: logActivity created/deleted

### F10 — Dashboard, Diff, Tokens
- [x] `GET /api/dashboard/stats` → server-computed stats
- [x] `GET /api/versions/{id}/diff?compare=` → structured diff
- [x] `PATCH /api/versions/{id}/artifacts` → inline edit, ERD JSON decode
- [x] `PATCH /api/versions/{id}/answers` → update answers
- [x] Project API tokens: GET list, POST create, DELETE revoke
- [x] Webhook `POST /api/webhooks/phase-complete` → token auth

### Activity Log
- [x] `GET /api/projects/{id}/activities` → paginated
- [x] `GET /api/activities` → global (admin)

### Standards & Agents
- [x] `GET /api/versions/{id}/standards` → download txt
- [x] `GET /api/versions/{id}/agents` → download txt
- [x] `POST /api/versions/{id}/regenerate-standards` → regenerate
- [x] Mobile versions: standards/mobile, agents/mobile, regenerate-standards/mobile

## Definition of Done (backend)
- Semua feature test hijau (`php artisan test` exit 0).
- Security checklist bagian terkait ([12-security-checklist](12-security-checklist.md)) lulus.
- Hasil (jumlah test, pass/fail) dicatat di [15-dev-log](15-dev-log.md).
