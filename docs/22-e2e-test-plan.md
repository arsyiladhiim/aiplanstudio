# 22 — E2E Test Plan (Playwright MCP)

> Dokumen ini adalah **rencana eksekusi** Playwright E2E untuk seluruh halaman/menu aplikasi,
> dijalankan bertahap via Playwright MCP (`playwright_browser_*`) dan di-deliver sebagai
> spec Playwright + update `docs/14`/`docs/15`/`docs/09`.
>
> **Status:** `[ ]` todo · `[~]` in-progress · `[x]` done
> **Aturan:** Setiap progres selesai → update checkpoint di file ini **SEBELUM** lanjut ke progres berikutnya.
> **Eksekusi:** belum dimulai — menunggu aba-aba user.

## Status Global
- **Mulai:** 2026-08-13
- **Target:** E01 – E18 (18 flow E2E)
- **Pendekatan:** Spec Playwright di `web/e2e/<area>.spec.ts` + Playwright MCP untuk eksplorasi awal.
- **Mode AI pipeline:** Skip tahap AI real (pakai pola `wizard.spec.ts:33-43` — assert pipeline screen render, jangan tunggu full AI run).
- **Login throttle mitigasi:** Pakai `web/e2e/.auth/state.json` dari global-setup, **JANGAN** login per test (throttle `5,1`).
- **Base URL:** `http://localhost:3000` (`E2E_BASE_URL` — Next.js dev server direct, no BFF).

## Daftar Progres

### [ ] E00 — Persiapan & extend helpers
- `web/e2e/helpers.ts`: tambah `consoleErrorCollector(page)`, `loginViaApi(context, baseURL)`, `createProjectViaApi(context, payload)`, `deleteProjectViaApi(context, id)`, `dataTestId(id)`.
- Verify `.auth/state.json` masih valid untuk stack `:4197` setelah restart Docker.
- Buat direktori `web/e2e/screens/` untuk screenshot E18 smoke.
- **Verification:** helper dipakai di 1 test stub, `npx playwright test --config=playwright.e2e.config.ts web/e2e/_helpers.spec.ts` hijau.

### [ ] E01 — Dashboard
- File: `web/e2e/dashboard.spec.ts`.
- Flow: login → `/dashboard` → assert stats badge + recent projects + recent activities → refresh → klik recent project → URL `/projects/[id]`.
- **Verification:** `npx playwright test dashboard.spec.ts` hijau + no console error.

### [ ] E02 — Projects list filter
- File: `web/e2e/projects-filters.spec.ts`.
- Flow: `/projects` → search debounce → heart favorit toggle (optimistic) → pin toggle (R12) → target select → archive per-kartu.
- **Verification:** hijau.

### [ ] E03 — Projects CRUD
- Extend `web/e2e/projects.spec.ts`.
- Flow: edit project (modal) → save → title/idea update; delete project → confirm dialog → row hilang.
- **Verification:** hijau.

### [ ] E04 — Projects archived (R13)
- File: `web/e2e/projects-archived.spec.ts`.
- Flow: archive dari `/projects` → row hilang; navigasi `/projects/archived` → kartu arsip (opacity) tampil; batal arsip dari kartu arsip; hapus permanen dari arsip.
- **Verification:** hijau.

### [ ] E05 — Project detail tabs (overview/web/tracking/activities + R14 regenerate)
- File: `web/e2e/project-detail.spec.ts`.
- Flow: 5 tabs visible → switch tab content render → target `both` tab Mobile tampil → version selector klik v2 → diff mode (versi >=2) → modal Versi Baru (from_last + baseline_notes) → Standards & Agents download/regenerate (skip AI) → Export MD/ZIP → edit project modal.
- **Verification:** hijau.

### [ ] E06 — Project tasks (R15)
- File: `web/e2e/project-tasks.spec.ts`.
- Flow: `/projects/[id]/tasks` → summary badges (Done/Running/Pending/Error/Total) → filter chip status → empty state → group per version card render.
- **Verification:** hijau.

### [ ] E07 — Project diff
- File: `web/e2e/project-diff.spec.ts`.
- Flow: detail page → diff mode aktif → pilih v1 → buka `/projects/[id]/diff?compare=v1id` → field berbeda tampil.
- **Verification:** hijau.

### [ ] E08 — Project API Tokens
- File: `web/e2e/project-tokens.spec.ts`.
- Flow: detail page section "API Tokens" → modal buat token (input nama) → list +1 → copy token (clipboard) → hapus token → confirm → row hilang.
- **Verification:** hijau.

### [ ] E09 — Wizard resume
- File: `web/e2e/new-wizard-resume.spec.ts`.
- Flow: buat project via API helper → `/new?resume=1&version=N` → loading spinner → pipeline stage screen (`[data-testid^="stage-"]`).
- **Verification:** hijau.

### [ ] E10 — Wizard inline edit + regenerate stage + restart-analisa (R14)
- File: `web/e2e/new-wizard-edit.spec.ts`.
- Flow: dari version dengan artifact seed → `/new?resume=1&version=N` → klik Sunting → textarea → Simpan (verify via API GET) → Approve & Lanjut → status berubah; Pipeline card di detail: regenerate per stage (hover-reveal) → loading → refresh; Restart dari Analisa → pertanyaan+analisa jadi done.
- **Verification:** hijau.

### [ ] E11 — Templates (R1+R2)
- File: `web/e2e/templates.spec.ts`.
- Flow: `/templates` → kartu visible (icon + target badge) → "Gunakan Template" → `/new?template=N` → form pre-fill dari seed → admin buat template baru → submit → list +1 → admin hapus template → confirm.
- **Verification:** hijau.

### [ ] E12 — Activities
- File: `web/e2e/activities.spec.ts`.
- Flow: `/activities` → feed paginated → klik link project → `/projects/[id]`; admin: badge "Semua Aktivitas" tampil.
- **Verification:** hijau.

### [ ] E13 — Help
- File: `web/e2e/help.spec.ts`.
- Flow: `/help` → accordion FAQ expand/collapse.
- **Verification:** hijau.

### [ ] E14 — Settings profile
- File: `web/e2e/settings-profile.spec.ts`.
- Flow: `/settings/profile` → edit nama → save → reload tetap → ganti password (old/new/confirm) → submit.
- **Verification:** hijau.

### [ ] E15 — Settings provider (admin)
- File: `web/e2e/settings-provider.spec.ts`.
- Flow: `/settings/provider` (admin) → list provider visible → tambah provider (base_url/model/api_key) → submit → list +1 → test connection → toast → set active → edit → delete.
- **Verification:** hijau.

### [ ] E16 — Settings users (admin)
- File: `web/e2e/settings-users.spec.ts`.
- Flow: `/settings/users` → list user role badge → pending user klik Approve → status aktif → tambah user → submit → list +1 → hapus → confirm; non-admin akses → 403/redirect.
- **Verification:** hijau.

### [ ] E17 — Command Palette (R6)
- File: `web/e2e/command-palette.spec.ts`.
- Flow: tekan `Ctrl+K` → modal muncul → ketik query → debounce 200ms → hasil grouped (Project/Versi) → klik hasil → navigasi → Escape → tutup.
- **Verification:** hijau.

### [ ] E18 — Smoke crawl (no console error / no 4xx-5xx)
- File: `web/e2e/smoke.spec.ts`.
- Flow: login → click seluruh nav menu berurutan: Dashboard / Projects / Favorit / Arsip / Buat Plan / Templates / Activities / Help / Settings/Profile. Tiap halaman: assert no `console error` + no 4xx/5xx network pada aksi natural. Screenshot tiap halaman → `/web/e2e/screens/`.
- **Verification:** hijau.

### [ ] E20 — Footer version badge + /settings/about
- File: `web/e2e/footer-about.spec.ts`.
- Flow: login → footer tampil `AI Plan Studio · vX.Y.Z` + `build <sha>` + link `About`. Klik About → `/settings/about` tampil Backend/Build/Stack cards dengan nilai yang match `/api/version`.
- **Verification:** hijau.

### [ ] E21 — Live progress widget
- File: `web/e2e/live-progress-widget.spec.ts`.
- Flow: login dengan minimal 1 project ber-status `running` (progress < total stages) → widget fixed bottom-right tampil dengan progress bar + counter `N/M stage selesai` → klik → navigasi ke `/projects/{id}?tab=tracking`. Saat project selesai, toast "Plan selesai!" 5s lalu widget hilang.
- **Verification:** hijau.

### [ ] E22 — What's new modal
- File: `web/e2e/whatsnew-modal.spec.ts`.
- Flow: clear `localStorage app:lastSeenVersion` → reload → modal tampil otomatis dengan version card + changelog entries. Klik "Got it" → modal close + `localStorage app:lastSeenVersion` ter-update. Reload lagi → modal tidak muncul.
- **Verification:** hijau.

### [ ] E23 — Onboarding tour dismiss
- File: `web/e2e/onboarding-tour.spec.ts`.
- Flow: clear `localStorage onboarding:completed` → reload `/dashboard` → tour tampil step 1/4 dengan highlight pada `Buat Plan Baru`. Navigate via "Lanjut" (atau ArrowRight) → step 2/3/4. Klik "Lewati" → `localStorage onboarding:completed=1` + tour hilang. Reload → tour tidak muncul.
- **Verification:** hijau.

### [ ] E24 — Confetti reduced-motion
- File: `web/e2e/confetti.spec.ts`.
- Flow: `prefers-reduced-motion=reduce` → trigger `allDone` (mock dengan set stage_status semua done di test DB) → buka `/new` → tidak ada element confetti di DOM. Tanpa reduce-motion → ada 80 element confetti dengan animasi `confetti-fall`.
- **Verification:** hijau.

### [ ] E25 — Accent color picker
- File: `web/e2e/accent-color.spec.ts`.
- Flow: `/settings/profile` → klik preset "Cyan" → `--color-brand` CSS var di `:root` = `#06b6d4` → save → reload → tetap cyan. Custom hex `#abcdef` → save → reload → tetap. Reset → var terhapus (kembali default).
- **Verification:** hijau.

### [ ] E19 — Final pass: Docker Playwright run + docs sync
- Jalankan: `docker run --rm --network host -v "$PWD/web":/work -w /work -e PLAYWRIGHT_BROWSERS_PATH=/ms-playwright -e E2E_BASE_URL=https://aiplanstudio.arsyiladm.my.id mcr.microsoft.com/playwright:v1.62.0-noble npx playwright test --config=playwright.e2e.config.ts`.
- Loop fix bug sampai hijau semua.
- Update `docs/14-frontend-testing.md`: tambah tabel cakupan spec baru.
- Update `docs/15-dev-log.md`: catat hasil run + tiap bug fix.
- Update `docs/09-roadmap.md`: tambah entry "[E2E-E01..E18] Playwright coverage expansion" → `[x]` bila hijau semua.
- **Verification:** exit code 0 + artefak nol.

## Log Checkpoint

_(Diisi per progres selesai.)_

