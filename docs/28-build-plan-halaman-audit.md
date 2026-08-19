# 28 — Build Plan & Projects: Perbaikan & Audit

> Audit mendalam halaman **Buat Plan** (`/new`, 1427 baris) + **Projects** (`/projects` + `/projects/[id]` 1007 baris) + backend prompt files + tracking webhook.
> Tujuan: perombakan UX maksimal, pipeline `target=both` tidak mandek, tracking webhook berjalan "dari awal hingga akhir".
> Status checklist di-update per progres (⬜ belum / 🔄 proses / ✅ selesai+restart).

## A. Temuan Kritis (blocker)
- **C1** `phased_master_mobile.php`: tidak ada marker `## SELESAI_ALL` → `validateMasterPrompt()` throw → stage `master_mobile` SELALU error → target=both mandek.
- **C2** `agents.php:200-202`: `target==='both'` → hanya `$flutterAgents` → web agents hilang dari AGENTS.md.
- **C3** `new/page.tsx:112`: `providerRate` selalu null → estimasi biaya selalu `$0.0000` (misleading).

## B. Temuan High
- **H1** `webhookUrl` relative (`/api/webhooks/phase-complete`) di `new:1284`, `projects/[id]:566`, `phased_master.php:103` → agent tidak tahu domain.
- **H2** `Version::progressCount()` hitung 14 stage tanpa normalisasi target; frontend bagi `getStages(target).length` (web=8) → persen salah.
- **H3** `new/page.tsx:685 reset()` "Mulai Ulang" → `apiDelete` hapus project permanen tanpa konfirmasi (destruktif).
- **H4** `McqForm.tsx:38`: `q.options.map` crash bila `q.options` bukan array.

## C. Temuan Medium
- **M1** `new/page.tsx:1175-1189`: `api_contract` render block dead code (tidak di stage list frontend).
- **M2** Tiada auto-run mode (14 stage = 13x klik Approve; backend punya `auto=1`).
- **M3** Resume tidak auto-detect dari `sessionStorage wizard:lostProject`.
- **M4** 3 panel progress redundan di `projects/[id]`.
- **M5** `phases_mobile.php` kurang detail (51 vs 211 lines web).
- **M6** Vestigial `{$idea}`/`{$stack}` di 8 prompt files.
- **M7** `master.php` dead code + `AiProviderTest.php:74` cek `'master'` (stale).
- **M8** Running stage tampil fake shimmer (padahal StreamingMarkdown support live).
- **M9** Pipeline list di `projects/[id]` pakai emoji (inkonsisten dgn wizard).
- **M10** `retryStage()` tidak clear error sebelum doStream.

---

## CHECKLIST EKSEKUSI

### Prioritas 1 — Pipeline target=both (blocker)
- [✅] **C1** `phased_master_mobile.php`: tambah `## SELESAI_ALL` + VERIFY check
- [✅] **C2** `agents.php`: gabung web+flutter agents untuk `both`
- [✅] **C3** `new/page.tsx`: hapus tampilan biaya `$0.0000`, pertahankan total token

### Prioritas 2 — Tracking & URL (H1)
- [✅] **H1** `new:1284` + `projects/[id]:566`: `webhookUrl` absolut dari `NEXT_PUBLIC_API_URL`
- [✅] **H1b** `phased_master.php:103` + `phased_master_mobile.php:102`: URL absolut
- [✅] **H1c** `phased_master.php` §6: instruksi running saat mulai fase 1, done setelah fase terakhir

### Prioritas 3 — UX correctness
- [✅] **H2** `progressCount()` normalisasi per target
- [✅] **H3** "Mulai Ulang" → modal konfirmasi destruktif
- [✅] **H4** `McqForm` guard `Array.isArray(q.options)`
- [✅] **M2** toggle "Jalankan Otomatis" (auto=1)
- [✅] **M3** resume auto-detect sessionStorage

### Prioritas 4 — Consistency & cleanup
- [✅] **M1** hapus dead code api_contract render
- [✅] **M4** konsolidasi 3 panel progress
- [✅] **M9** emoji → icon components
- [✅] **M5** align `phases_mobile` dengan web
- [✅] **M6** bersihkan vestigial vars di prompts
- [✅] **M7** hapus `master.php` + update test
- [✅] **M8** skeleton vs streaming consistency
- [✅] **M10** `retryStage` clear error

### Validasi Akhir
- [✅] `php artisan test` (backend) — 261 passed, 1 skipped, 1 pre-existing fail (SocialiteControllerTest isolation)
- [✅] `npm run lint && npx tsc --noEmit` (frontend) — file yang diubah clean; 2 error pre-existing di CommandPalette.tsx (bukan dari session ini)
- [✅] restart container + rebuild web image + smoke test end-to-end
- [✅] update checkpoint ini

## Status: SEMUA CHECKLIST ✅
Semua perbaikan P1-P4 selesai, diverifikasi via unit test + tinker probe + smoke test.

(Docker Rules & Production-Ready dibatalkan per permintaan user — tidak dikerjakan.)
