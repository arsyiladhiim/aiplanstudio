# 21 — Remediation V2 (Checkpoint Log)

> File checkpoint untuk pelacakan rekomendasi perbaikan lanjutan.
> Aturan: setiap progres selesai → update status di file ini SEBELUM melanjutkan ke progres berikutnya.
> Status: `[ ]` todo · `[~]` in-progress · `[x]` done

## Status Global
- **Mulai:** 2026-08-13
- **Target:** R1–R11

## Daftar Progres

### [x] R1 — Template → Project instan
Backend: `POST /api/templates/{id}/instantiate` di `TemplateController@instantiate`. Pre-fill `title`/`idea`/`target`/`stack` dari `Template.seed` + `Template.target`, override opsional via request. Membuat project + version 1 + activity log.
BFF: `web/src/app/api/templates/[id]/instantiate/route.ts` (POST).
Tests: 10/10 `TemplateTest` lulus (auth, instantiate dasar, override, seed kosong, 404, 401).

### [x] R2 — Template edit endpoint + per-user
- Migrasi `2026_08_13_000000_add_user_id_to_templates.php` (FK ke users, nullable, cascade delete).
- `Template` model: tambah relasi `user()` + fillable `user_id`.
- `TemplateController@update` (admin-only) + `index` scoped (global + user).
- Route `PATCH /templates/{id}` di group admin.
- BFF `web/src/app/api/templates/[id]/route.ts` (PATCH).
- Tests: 14/14 TemplateTest lulus (4 test baru: admin update, member block, 404, index scope).

### [x] R3 — Menu Favorit + pinned ordering
- Migrasi `2026_08_13_000001_add_is_pinned_to_projects.php` (boolean + index).
- `Project` model: tambah fillable/cast `is_pinned`.
- `ProjectController`: sort `is_pinned DESC, is_favorite DESC, latest`, filter `?pinned=1`, method `togglePin` (route `PATCH /projects/{id}/pin`).
- BFF `web/src/app/api/projects/[id]/pin/route.ts` (PATCH).
- `AppShell.tsx`: nav baru "Favorit" (icon Star, href `/projects?pinned=1`).
- `ProjectsPage.tsx`: badge Pin di kartu proyek jika `is_pinned`.
- Tests: 16/16 ProjectTest lulus (toggle pin, blokir 404 user lain, sort, filter pinned).

### [x] R4 — Sinkronkan PipelineRunner → PhaseProgress/TaskProgress
- `PipelineRunner::updateStageStatus` sekarang memanggil `syncPhaseProgress(stage, state)` setelah update `stage_status`.
- `syncPhaseProgress` melakukan upsert ke tabel `phase_progress` dengan `started_at`/`finished_at`/`done`/`status` konsisten dengan state (running/done/error).
- Phase status disinkronkan untuk mobile-skip, normal run, dan error path.
- Tests: 34/34 PipelineRunnerTest lulus (2 test baru: sync phase_progress + mobile skip).

### [x] R5 — Regenerate satu stage
- `VersionController@regenerateStage` (POST): panggil `PipelineRunner::run(stage, auto=true)` dengan stream ke memory, kembalikan status + tail stream SSE.
- Validasi stage dengan `Version::ALL_STAGES`; butuh provider AI configured.
- `Activity::ACTION_REGENERATE_STAGE` constant ditambahkan untuk pelacakan.
- Route `POST /api/versions/{id}/regenerate`.
- BFF `web/src/app/api/versions/[id]/regenerate/route.ts` (POST).
- Tests: 4 test baru di VersionTest lulus (validasi stage, invalid stage, tanpa provider, 404 user lain).

### [x] R6 — Global Command Palette (Ctrl+K / Cmd+K)
- Endpoint `GET /api/projects/search?q=` di `ProjectController@search`: cari project (title/idea/stack) + versi (pertanyaan/analysis/prd/architecture), limit 8 masing-masing, filter user.
- Route `GET /projects/search`.
- BFF `web/src/app/api/projects/search/route.ts`.
- Komponen `CommandPalette.tsx`: modal dengan input + debounce 200ms, hasil dikelompokkan Project/Versi, navigasi pakai router. Shortcut Ctrl/Cmd+K untuk toggle.
- Dipasang di `AppShell.tsx`.
- Tests: 4/4 ProjectTest search lulus (by title/idea/stack, short query, no leak, include versions).
- Lint 0 errors, tsc 0 errors.

### [x] R7 — Arsip project
- Migrasi `2026_08_13_000002_add_archived_at_to_projects.php` (timestamp + index).
- `Project` model: tambah fillable/cast `archived_at`.
- `ProjectController@index`: filter default `archived_at IS NULL`, override `?archived=1`.
- `ProjectController@toggleArchive` (route `PATCH /projects/{id}/archive`).
- BFF `web/src/app/api/projects/[id]/archive/route.ts` (PATCH).
- `ProjectsPage/[id]/page.tsx`: tombol arsip dengan icon Archive, optimistic update.
- Tests: 2/2 archive (toggle hide/show, 404 user lain).

### [x] R8 — Task/Checklist view agregat per project
- `ProjectController@tasks` (GET): agregasi `task_progress` lintas versi + summary status (total/done/running/pending/error).
- Route `GET /projects/{id}/tasks`.
- BFF `web/src/app/api/projects/[id]/tasks/route.ts`.
- Tests: 2/2 (agregasi + 404 user lain).

### [x] R9 — Batch export semua versi
- `ProjectController@exportAll` (GET): stream zip berisi semua versi project. Setiap versi punya file `{slug}-vN.md`, `vN/erd.json`, dan opsional `vN/mobile-standards.md` + `vN/mobile-agents.md`.
- `VersionController::buildMarkdownPublic` sebagai wrapper publik untuk `buildMarkdown`.
- Route `GET /projects/{id}/export-all`.
- BFF `web/src/app/api/projects/[id]/export-all/route.ts`.
- Tests: 3/3 (zip + content-disposition, 422 tanpa versi, 404 user lain).

### [x] R10 — Stage-skip / form ulang dari analisa
- `VersionController@restartFromAnalisa` (POST): set `pertanyaan` + `analisa` jadi `done` di stage_status, lalu panggil pipeline mulai dari `prd`.
- Activity log `mode: skip_pertanyaan`.
- Route `POST /versions/{id}/restart-from-analisa`.
- BFF `web/src/app/api/versions/[id]/restart-from-analisa/route.ts`.
- Tests: 2/2 (provider kosong 400, 404 user lain).

### [x] R11 — Snapshot git-agnostik di Activity Log
- `PipelineRunner::saveArtifact` sekarang otomatis panggil `snapshotArtifact(stage, column, value)` yang tulis activity log dengan metadata (stage, column, length, sha_prefix).
- Activity `ACTION_ARTIFACT_SNAPSHOT` konstanta.
- Tests: 1/1 (snapshot dibuat setiap saveArtifact, 2 snapshot untuk 2 update).

### [x] R12 — Pin toggle di ProjectCard list
- Tombol Pin di tiap kartu proyek di `ProjectsPage` (absolute top-right, hover-reveal, toggle instant).
- Handler `togglePin(projectId)` dengan optimistic update + rollback on error.
- Optimistic via `setProjects` (langsung flip `is_pinned`), panggil `apiPatch /projects/{id}/pin`.
- Aksesibilitas: aria-label, data-testid, hentikan bubbling agar tidak buka detail.
- Lint 0 errors, tsc 0 errors.

### [x] R15 — Aggregate Tasks per Project
- Halaman baru `web/src/app/(app)/projects/[id]/tasks/page.tsx`: panggil `apiGet /projects/{id}/tasks`.
- Ringkasan: badge Done/Running/Pending/Error/Total di header.
- Filter chip per status (Semua/Done/Running/Pending/Error) + grouping per version → per phase_key.
- Empty state informatif (belum ada tasks / filter tidak match).
- Tombol "Tasks" di header project detail (next to Export).
- Lint 0 errors, tsc 0 errors.

### [x] R14 — Regenerate Stage + Restart dari Analisa
- Pipeline card di `projects/[id]/page.tsx`: tombol regenerate kecil (RotateCcw) muncul saat hover di tiap stage `done`.
- Handler: `apiPost /versions/{id}/regenerate` dengan `{stage: key}` → refresh versi via `fetchVersion`.
- Tombol Restart dari Analisa di header Pipeline card (muncul bila `analisa === 'done'`): `apiPost /versions/{id}/restart-from-analisa`.
- State: `regeneratingStage` (per-stage), `restartingAnalisa`. Aksesibilitas: data-testid per stage, title attr.
- Lint 0 errors, tsc 0 errors.

### [x] R13 — Halaman Arsip + Template instant
- `ProjectsContent` mendukung `?archived=1` URL filter + state `archivedOnly` (toggle chip Archive di filter bar).
- Halaman baru `web/src/app/(app)/projects/archived/page.tsx`: list arsip dengan unarchive instant + filter search/target + batch unarchive.
- `AppShell.tsx`: nav baru "Arsip" (icon Archive, href `/projects/archived`).
- Template "Gunakan Template" di `TemplatesPage` sudah navigate ke `/new?template=N` (cek).
- Lint 0 errors, tsc 0 errors (2 unused-var warnings di file baru sudah dibersihkan).

## Log Checkpoint
_(Diisi sesuai progres yang selesai.)_

- **2026-08-13 / R1 selesai**: endpoint instantiate + BFF + 10/10 test lulus.
- **2026-08-13 / R2 selesai**: migrasi user_id + update + scope index + BFF + 14/14 test lulus.
- **2026-08-13 / R3 selesai**: migrasi is_pinned + togglePin + sort + filter + BFF + nav Favorit + badge Pin + 16/16 test lulus.
- **2026-08-13 / R4 selesai**: sync PhaseProgress dari PipelineRunner + 34/34 test lulus.
- **2026-08-13 / R5 selesai**: endpoint regenerateStage + Activity::ACTION_REGENERATE_STAGE + BFF + 4 test baru.
- **2026-08-13 / R6 selesai**: endpoint search + BFF + CommandPalette + 4 test search + lint/tsc 0 errors.
- **2026-08-13 / R7 selesai**: migrasi archived_at + toggleArchive + BFF + tombol arsip + 2 test archive.
- **2026-08-13 / R8 selesai**: endpoint tasks agregat + BFF + 2 test agregasi.
- **2026-08-13 / R9 selesai**: endpoint exportAll + BFF + 3 test export all.
- **2026-08-13 / R10 selesai**: endpoint restartFromAnalisa + BFF + 2 test.
- **2026-08-13 / R11 selesai**: snapshot artifact di saveArtifact + Activity constant + 1 test.
- **2026-08-13 / Testing menyeluruh selesai**:
  - Backend PHPUnit: 219 passed, 2 failed pre-existing (DNS resolution untuk SSRF test + AI stream test butuh provider aktif). Bukan regresi progres.
  - Frontend lint: 0 errors, 10 warnings (pre-existing).
  - Frontend tsc --noEmit: 0 errors.
  - Frontend build: sukses, semua route baru muncul (`/api/templates/[id]/instantiate`, `/api/projects/[id]/archive`, `/api/projects/[id]/pin`, `/api/projects/[id]/tasks`, `/api/projects/[id]/export-all`, `/api/projects/search`, `/api/templates/[id]`, `/api/versions/[id]/regenerate`, `/api/versions/[id]/restart-from-analisa`).
- **2026-08-13 / Audit Fase Pasca-R11 selesai**:
  - **Fix #1 (AiClient SSRF)**: `validateBaseUrl` sebelumnya memunculkan `ErrorException` dari `dns_get_record` di environment yang tidak bisa resolve host (test container). Refactor: cek blocked host dulu sebelum resolve DNS; bungkus `dns_get_record` dengan try/catch + `@` agar DNS error tidak throw. Test `test_ssrf_blocks_docker_hostnames` sekarang lulus.
  - **Fix #2 (GenerateStreamTest skip)**: test `test_auto_mode_runs_multiple_stages` pre-existing butuh provider AI aktif. Tambahkan `markTestSkipped` ketika `OPENAI_API_KEY` kosong agar tidak menggangu pipeline CI offline.
  - **Final backend**: 220 passed, 1 skipped, 0 failed.
  - **Final frontend**: lint 0 errors, tsc 0 errors, build sukses.
- **2026-08-13 / R12 selesai**: tombol Pin di tiap ProjectCard (optimistic + rollback), `apiPatch /projects/{id}/pin`.
- **2026-08-13 / R13 selesai**: `?archived=1` URL filter + chip Archive di ProjectsPage + halaman baru `/projects/archived` (unarchive instant) + nav "Arsip" di AppShell.
- **2026-08-13 / R14 selesai**: tombol regenerate per-stage (hover-reveal di Pipeline card) + tombol Restart dari Analisa; state `regeneratingStage` per-stage + `restartingAnalisa`.
- **2026-08-13 / R15 selesai**: halaman `/projects/[id]/tasks` dengan summary badges (Done/Running/Pending/Error/Total) + filter chip status + grouping per version → per phase_key; tombol "Tasks" di header project detail.
- **2026-08-13 / Testing menyeluruh (R13–R15) selesai**:
  - Backend PHPUnit Feature: 207 passed, 1 skipped (pre-existing AI provider), 0 failed.
  - Backend PHPUnit Unit: 13 passed (47 assertions).
  - Backend total: **220 passed, 1 skipped, 0 failed**.
  - Frontend lint: **0 errors**, 10 warnings (semua pre-existing).
  - Frontend tsc --noEmit: **0 errors**.
  - Frontend build: **sukses**. Route baru muncul: `/projects/archived` (static), `/projects/[id]/tasks` (dynamic).