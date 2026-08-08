# 20. Dynamic MCQ Clarifying Questions & Pertanyaan Mobile Pipeline (14 Stages)

> Status: `[ ] IN PROGRESS` (Checkpoint 1 Created)  
> Tanggal: 8 Agustus 2026

---

## Executive Summary
Dokumen ini mencatat rancangan, spesifikasi, serta checkpoint implementasi untuk fitur:
1. **Dynamic MCQ Clarifying Questions** pada Stage 1 (`pertanyaan`): AI mengidentifikasi 3-5 area samar/ambigu dari ide user, lalu menghasilkan 5-10 pertanyaan pilihan ganda (A, B, C, D) + Opsi E Custom Text ("Lainnya") lengkap dengan tanda `(Rekomendasi AI)`.
2. **Stage Pertanyaan Mobile** (`pertanyaan_mobile`): Stage klarifikasi khusus mobile yang berjalan setelah `master_web` selesai (Stage 10 untuk target `both`). Berfokus pada integrasi hardware (kamera/GPS/Bluetooth), offline sync, push notification, dan UX mobile.
3. **Penyempurnaan Target Pipeline**: Target `mobile` standalone ditiadakan, hanya menerima `web` (9 stage) dan `both` (14 stage).
4. **Interactive MCQ UI**: Dynamic card di wizard UI (`new/page.tsx`) dengan opsi A-D terpilih/terisi rekomendasi AI secara default + text area opsi E.

---

## Complete Pipeline Architecture (14 Stages for `both`)

### Stage Breakdown
| Step | Stage Key | Title | Target | Input Context | Output Field |
|------|-----------|-------|--------|---------------|--------------|
| 1 | `pertanyaan` | Clarifying Questions (MCQ) | web, both | user idea + target + stack | `pertanyaan` (JSON string) |
| 2 | `analisa` | Initial Analysis | web, both | idea + answers | `analisa` |
| 3 | `prd` | Product Requirements | web, both | idea + answers + analisa | `prd` |
| 4 | `architecture` | System Architecture | web, both | + prd | `architecture` |
| 5 | `erd` | Database ERD | web, both | + architecture | `erd` |
| 6 | `api_contract` | API Contract | web, both | + erd | `api_contract` |
| 7 | `phases_web` | Web Phased Implementation | web, both | + api_contract | `phases_web` |
| 8 | `standards_web` | Web Code Standards | web, both | + phases_web | `standards_web` |
| 9 | `master_web` | Web Master Prompt | web, both | + standards_web | `master_web` |
| **10** | `pertanyaan_mobile` | Mobile Clarifying Questions | **both only** | **master_web + api_contract + erd** | **`pertanyaan_mobile` (JSON)** |
| 11 | `phases_mobile` | Mobile Phased Implementation | both only | + mobile_answers | `phases_mobile` |
| 12 | `standards_mobile` | Mobile Code Standards | both only | + phases_mobile | `standards_mobile` |
| 13 | `master_mobile` | Mobile Master Prompt | both only | + standards_mobile | `master_mobile` |
| 14 | `agents` | AI Agent Specifications | web, both | master_web (+ master_mobile if both) | `agents` |

---

## JSON Format Specifications

### `pertanyaan` / `pertanyaan_mobile` Output (AI Prompt JSON Format)
```json
{
  "ambiguities": [
    "Skalabilitas metode autentikasi belum dijelaskan",
    "Mekanisme sinkronisasi data offline belum ditentukan"
  ],
  "questions": [
    {
      "id": "q1",
      "question": "Metode autentikasi utama apa yang ingin Anda gunakan?",
      "options": [
        { "key": "A", "text": "OAuth2 / Social Login (Google & GitHub)", "recommended": true },
        { "key": "B", "text": "Email & Password dengan OTP 2FA", "recommended": false },
        { "key": "C", "text": "Magic Link / Passwordless Email", "recommended": false },
        { "key": "D", "text": "SSO Enterprise (SAML / OIDC)", "recommended": false }
      ],
      "recommendation_reason": "Mudah diintegrasikan dan memberikan UX onboarding tercepat bagi pengembang."
    }
  ]
}
```

### `answers` & `mobile_answers` Format (Stored in DB & Sent to API)
```json
{
  "q1": { "selected": "A", "custom_text": "" },
  "q2": { "selected": "E", "custom_text": "Gunakan Custom Auth Server internal" }
}
```

---

## Checkpoint & Progress Tracker

### [x] Checkpoint 0: Architecture & Documentation Planning
- [x] Diskusi & persetujuan format JSON MCQ (A-E)
- [x] Penambahan stage `pertanyaan_mobile` (Total 14 stage)
- [x] Pembuatan dokumen checkpoint `docs/20-dynamic-mcq-questions.md`

### [x] Checkpoint 1: Database Migration & Model Layer
- [x] Buat migration menambah `pertanyaan_mobile` (text/json) & `mobile_answers` (jsonb) pada `versions` table
- [x] Update `Version.php`: `$fillable`, `$casts`, `defaultStageStatus()` (14 keys)
- [x] Run migration via docker (`php artisan migrate --force`) — DONE 23.02ms

### [x] Checkpoint 2: Backend Prompts & Controller Update
- [x] Rewrite `api/app/Prompts/pertanyaan.php` (Output JSON 5-10 MCQ A-E)
- [x] Buat `api/app/Prompts/pertanyaan_mobile.php` (Output JSON mobile MCQ)
- [x] Update `api/app/Http/Controllers/VersionController.php`: support `mobile_answers` endpoint & colMap `pertanyaan_mobile`
- [x] Update `api/app/Http/Controllers/GenerateStreamController.php`: validStages 14 keys

### [x] Checkpoint 3: Backend Pipeline Runner & Unit Tests
- [x] Update `api/app/Services/PipelineRunner.php`: `ALL_STAGES` 14, `MOBILE_STAGES` gate, `contextPrompt()`, `saveArtifact()`
- [x] Update Prompt templates downstream (`phased_master_mobile.php`, `phases_mobile.php`, dll) untuk menyertakan `mobile_answers`
- [x] Create `api/app/Prompts/api_contract.php` (JSON API contract prompt)
- [x] Update test files (`PipelineRunnerTest.php`, `GenerateStreamTest.php`, `ModelTest.php`)
- [x] Run `php artisan test` — 131/131 PASS (474 assertions)

### [x] Checkpoint 4: Frontend Types & State
- [x] Update `web/src/lib/mock.ts`: 14 `StageKey`, `ALL_STAGES`, `getStages()`, `StageInfo`
- [x] Update `web/src/lib/api.ts`: `Version` interface (`pertanyaan_mobile?`, `mobile_answers?`, MCQ Types)
- [x] Update `web/AGENTS.md` pipeline stages (14 keys)
- [x] Run tsc --noEmit — 0 errors

### [x] Checkpoint 5: Frontend Interactive UI
- [x] Update `web/src/app/(app)/new/page.tsx`: MCQ JSON parser (`mcqData`, `mcqMobileData`), MCQ card UI (A-D buttons + E textarea + recommendation badge), mobile MCQ (`pertanyaan_mobile` stage), legacy fallback
- [x] Run tsc --noEmit — 0 errors
- [x] Run npm run lint — 0 errors (5 pre-existing warnings)

### [x] Checkpoint 6: Full Verification & Sync
- [x] Run `php artisan test` — 131/131 PASS
- [x] Update `web/AGENTS.md` (14 stages keys)
- [x] Commit pending
