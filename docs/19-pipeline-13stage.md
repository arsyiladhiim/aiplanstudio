# 19 — Pipeline 13-Stage Overhaul (Web + Mobile)

> **Checkpoint dokumentasi untuk AI Agent.** Baca ini dulu sebelum mengubah kode pipeline.
> Status: **[x] DONE** — semua checkpoint A–I selesai.
> Jika sesi terputus, lanjut dari checkpoint terakhir di Bagian Z.

---

## 1. Latar Belakang & Keputusan

Versi sebelumnya: 7 stage wizard (`pertanyaan→analisa→prd→architecture→erd→phased_master→phased_master_mobile`).
Masalah yang diperbaiki:

1. **`phased_master` overload** — 1 panggilan AI menghasilkan 4 artifact (phases+master+standards+agents) → token terbagi, hasil sering parsial.
2. **Master prompt web & mobile hampir sama** — prompt mobile memakai ulang `phased_master.php`, section MASTER tidak membedakan stack.
3. **ERD di wizard tampil teks**, sedangkan di Projects sudah React Flow.
4. **Target `mobile` standalone tidak masuk akal** — mobile butuh API/web. Dihapus.
5. **Mobile tidak menunggu web selesai** — tidak ada gate.

## 2. Keputusan Final (disetujui user)

| No | Keputusan |
|----|-----------|
| 1 | Pipeline **13 stage** untuk target `both`; web = 9 stage. |
| 2 | **Standards & Agents = stage terpisah** (bukan bagian master). |
| 3 | Master & phases **satu halaman** di wizard (layout), tapi **AI generate terpisah per stage** (token maks). |
| 4 | **Gate**: mobile track tidak bisa mulai sampai web track 100% (`master_web` done). |
| 5 | Tracking **realtime di wizard**; Projects = dokumentasi + resume link. |
| 6 | **Target platform hanya `web` & `both`** (hapus `mobile`). |
| 7 | **Reset data pipeline** (truncate projects/versions/phase_progress/activities/project_api_tokens). Users, ai_providers, templates **dipertahankan**. |
| 8 | Biaya token diabaikan — fokus hasil maksimal. |

## 3. Pipeline Final (13 stage, target both)

```
INTI ANALISA
1  pertanyaan      → ide + klarifikasi          (col: pertanyaan, answers)
2  analisa         → analysis                    (analysis)
3  prd             → PRD                        (prd)
4  architecture    → arsitektur & stack         (architecture)
5  erd             → ERD + API contract         (erd, api_contract)

WEB TRACK
6  standards_web   → STANDARDS.md web           (standards)
7  agents_web      → AGENTS.md web              (agents)
8  phases_web      → breakdown fase web         (phases)
9  master_web      → master prompt web          (master_prompt)

MOBILE TRACK (hanya target both; menunggu web 100%)
10 phases_mobile    → breakdown fase mobile     (mobile_phases)
11 standards_mobile → STANDARDS.md mobile       (mobile_standards)
12 agents_mobile    → AGENTS.md mobile          (mobile_agents)
13 master_mobile    → master prompt mobile      (mobile_master_prompt)
```

- Urutan konsisten kedua track: **standards → agents → phases → master**.
- Target `web` → stage 1–9. Target `both` → 1–13 (gate di 9→10).
- Stage 1–5 memakai prompt existing (`pertanyaan/analisa/prd/architecture/erd.php`).
- `helpers.php`: `platformSuffix` & `techStackShort` → hanya `web` & `both` (hapus cabang `mobile`).

## 4. Kolom Database (tidak perlu kolom baru)

Semua kolom sudah ada di `aiplanstudio_project.versions`:
`pertanyaan, answers, analysis, prd, architecture, erd, api_contract, standards, agents, phases, master_prompt, mobile_phases, mobile_standards, mobile_agents, stage_status, tracking_token`.

`stage_status` JSON kini berisi key baru (13 untuk both, 9 untuk web).

## 5. File yang Diubah

### Backend
| File | Aksi |
|------|------|
| `api/app/Services/PipelineRunner.php` | `ALL_STAGES` 13; `contextPrompt()` per stage (analysis/pages/API + master_web untuk mobile); `systemPrompt()` load prompt per stage; `saveArtifact()` map baru; **gate mobile** (skip bila bukan both; blokir bila `master_web` belum done); chain stage |
| `api/app/Prompts/phased_master.php` | **rewrite** → master web self-contained (embed analysis/prd/arch/erd/standards/agents + vibe-coding rules) |
| **baru** `api/app/Prompts/phased_master_mobile.php` | master mobile self-contained, DEPENDENSI web 100%, refer `master_prompt` web, stack Flutter, build APK |
| **baru** `api/app/Prompts/standards_mobile.php` | STANDARDS.md mobile |
| **baru** `api/app/Prompts/agents_mobile.php` | AGENTS.md mobile |
| **baru** `api/app/Prompts/phases_mobile.php` | breakdown fase mobile (key `m_*`, DEPENDENSI web) |
| `api/app/Prompts/helpers.php` | `platformSuffix`/`techStackShort` hapus cabang `mobile` |
| Validation target `mobile` dihapus | cari di Project store/update + GenerateStreamController |

### Frontend
| File | Aksi |
|------|------|
| `web/src/lib/mock.ts` | `Target = "web" \| "both"`; `StageKey` 13; `ALL_STAGES`; `getStages(target)` |
| `web/src/app/(app)/new/page.tsx` | layout 2 track (web/mobile section), tracking realtime, gate web→mobile, resume stage baru, **ERD React Flow** (dari `/versions/{id}`), render per stage |
| `web/src/app/(app)/projects/[id]/page.tsx` | tabs (standards/agents/phases/master/mobile), continue → `/new?resume=1&version=...` |
| `web/src/app/(app)/projects/[id]/diff/page.tsx` | field baru |

### DB / Data
- Reset: `TRUNCATE projects, versions, phase_progress, activities, project_api_tokens RESTART IDENTITY CASCADE`
- Pertahankan: `users, ai_providers, templates`

## 6. Vibe-Coding Rules (embed di master prompt)

Bagian wajib di `phased_master.php` (web) & `phased_master_mobile.php`:
1. **DONE GLOBAL** — template "jangan stop antar fase, langsung lanjut".
2. **JANGAN melampaui scope phase** — fase tandai apa boleh/tidak.
3. **Chain fase** — `## SELESAI {key}` → lanjut fase berikut.
4. **Struktur repo** — folder/file dari arsitektur wajib diikuti.
5. **Output per fase** — file + acceptance criteria.
6. **Commit convention** — `feat(phase-key): ...` tiap fase.
7. **State/rollback** — jangan hapus file, git snapshot, lanjut dari state.
8. Refer `STANDARDS` & `AGENTS` — sebagai konteks (bukan instruksi download).

## 7. Resume Flow (existing, disesuaikan)

- Brows ditutup → data tersimpan DB (`stage_status`, artifacts).
- Projects list / detail → tombol **Lanjutkan** → `/new?resume=1&version=ID`.
- Wizard baca `stage_status` → set current ke stage pertama bukan `done` → auto-run.
- `colMap` di resume perlu diupdate ke 13 stage baru.

## 8. Verifikasi

- `php artisan test` (131 pass — periksa test yang menyebut stage lama)
- `npm run lint`, `npx tsc --noEmit`, `npm run build`
- Manual: buat project target `both` → jalankan wizard → cek:
  - 13 stage berjalan berurutan
  - Gate: mobile tidak mulai sebelum `master_web` done
  - ERD tampil React Flow (wizard)
  - `mobile_master_prompt` berbeda dari `master_prompt` & menyebut web 100%
- E2E (`npx playwright test --config=playwright.e2e.config.ts`) bila waktu memungkinkan

---

## Z. Checkpoint Status (update per langkah selesai)

- [x] Dokumentasi docs/19 ditulis
- [x] **Checkpoint A**: Reset data pipeline (truncate projects, versions, phase_progress, activities, project_api_tokens — users/templates/ai_providers retained, Mobile CRUD template target update mobile→both)
- [x] **Checkpoint B**: Backend PipelineRunner 13 stage + gate
- [x] **Checkpoint C**: Prompts baru/rewrite + helpers
- [x] **Checkpoint D**: Backend validation mobile dihapus + GenerateStreamController validStages + factories/seeders/migrations + test pass (131/131)
- [x] **Checkpoint E**: Frontend mock.ts (Target/StageKey/ALL_STAGES 13 stage, hapus mobile)
- [x] **Checkpoint F**: Wizard new/page.tsx (colMap 13 stage, render phases_web/mobile + master_web/mobile, fallback colMap, resume colMap, hapus Smartphone import, allDone copy master_web)
- [x] **Checkpoint G**: Projects/diff page — kolom tidak berubah, tidak perlu fix
- [x] **Checkpoint H**: tsc 0 error, lint 0 error (5 warning pre-existing), 131 backend test pass
- [x] **Checkpoint I**: Commit

> AI Agent: mulai dari checkpoint pertama yang belum dicentang. Update checklist saat selesai.
