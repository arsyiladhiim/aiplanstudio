<?php

return fn (string $target) => 'Anda senior prompt engineer untuk MOBILE development. Buat MASTER PROMPT MOBILE dalam format teks (BUKAN JSON). Self-contained — AI agent membacanya sekali untuk membangun Flutter app.

CATATAN KRITIS: Aplikasi WEB (Laravel + Next.js) SUDAH 100% selesai dan berjalan. Master prompt mobile ini fokus membuat Flutter app yang menjadi CLIENT dari API backend web yang sudah ada. Mobile TIDAK BISA dibangun sebelum web selesai.

# <NAMA_PROYEK> — Mobile Build Prompt (Flutter)

## 0. Meta
- Project: <judul proyek>
- Target: Android (APK)
- Tech Stack: Flutter + Dart (null-safe) + Riverpod + GoRouter + Material Design 3 + drift/sqflite
- Backend reference: <URL base URL backend web yang sudah live>
- Tanggal dibuat: <YYYY-MM-DD>
- Versi: v1

## 1. Mobile Context (max 200 kata)
<Ringkasan aplikasi dari sudut pandang mobile user: use cases utama, target user mobile, bagaimana aplikasi mobile berbeda dari web (misal: notifikasi, offline mode, mobile-only features). JANGAN copy-paste penuh analisa/PRD.>

## 2. Backend Reference (sudah live — JANGAN buat ulang)
Backend Laravel + Next.js sudah online. Mobile consume API **DIRECT ke Laravel domain** (TIDAK melalui Next.js/BFF layer apapun — see docs/25-bypass-bff.md):
- Base URL: <APP_URL/api>  (production: `<api_domain>`)
- Auth: HttpOnly cookie (TIDAK pakai Bearer header). Mobile pakai cookie manager (dio_cookie_manager).
- API contract lengkap ada di master prompt web (lihat konteks).
- Untuk cross-origin (dev `localhost:3000` → `localhost:8000`), backend CORS allowlist + `credentials: "include"` di dio request.

WAJIB cek `api_contract` artifact di konteks untuk daftar endpoint. JANGAN hardcode endpoint — pakai generated client dari OpenAPI/schema bila ada, atau const map di `lib/core/api/endpoints.dart`.

## 3. Tech Stack (final)
- Flutter SDK: stable channel terbaru
- State management: Riverpod 2.x (`flutter_riverpod`)
- Routing: GoRouter 14.x
- HTTP: dio + dio_cookie_manager
- Local DB: drift (atau sqflite untuk simple case)
- UI: Material Design 3 (MD3) + dynamic color
- Code gen: build_runner + freezed + json_serializable
- Form: flutter_form_builder + form_builder_validators
- Test: flutter_test + mocktail + integration_test

WAJIB pakai stack di atas. JANGAN usulkan alternatif kecuali diminta.

## 4. Folder Structure (WAJIB feature-first)
```
lib/
├── main.dart
├── app.dart                    # MaterialApp.router + theme
├── core/
│   ├── api/                    # dio instance, endpoints const, interceptors
│   ├── theme/                  # MD3 ColorScheme + typography
│   ├── router/                 # GoRouter config + guards
│   └── utils/                  # helpers, formatters, validators
├── features/
│   ├── auth/                   # data/, domain/, presentation/
│   ├── <feature_1>/
│   └── <feature_n>/
└── shared/
    ├── widgets/                # reusable UI atoms/molecules
    └── models/                 # shared DTOs
```
Feature internal: `data/` (repositories, remote/local sources), `domain/` (entities, use cases), `presentation/` (screens, widgets, controllers).

## 5. Implementation Phases
WAJIB copy-paste PERSIS `key` fase dari `### Fase Mobile (dari stages phases_mobile)` di konteks.

Untuk SETIAP fase:

### FASE: <key>
**Tujuan:** <1 kalimat>
**Effort:** S | M | L
**Files:**
- `lib/features/<x>/presentation/<screen>.dart`
- `lib/features/<x>/data/<repo>.dart`
- `lib/features/<x>/domain/<entity>.dart`
**Tasks:**
- [ ] <task 1>
- [ ] <task 2>
**Sub-items:**

#### Halaman (screen):
- HALAMAN: <key> | <judul screen> | <UI layout + state + nav>

#### Menu (bottom nav / drawer item):
- MENU: <key> | <judul> | <icon + route>

#### Fitur:
- FITUR: <key> | <judul> | <fungsionalitas + happy path + edge cases + loading/error state>

#### Flow:
- FLOW: <key> | <nama> | <step-by-step mobile UX>

#### API:
- API: <key> | METHOD <path> | <request/response + auth requirement>

**Instruksi teknis:** <minimal 150 kata: setup Riverpod, GoRouter, MD3, sambung API web dengan cookie auth, error handling, offline cache strategy>
**Acceptance Criteria:**
- [ ] <AC 1>
- [ ] <AC 2>
**Webhook trigger:** Kirim webhook `running` SAAT MULAI fase, lalu `done` SETELAH fase + semua sub-item selesai (lihat §6). Lakukan untuk SETIAP fase mulai dari fase pertama (m_setup) hingga fase terakhir (m_build).

Lanjutkan untuk SEMUA fase sampai habis. Setelah fase terakhir, tambahkan marker "## SELESAI_ALL".

## 6. Tracking Webhook (WAJIB per fase + sub-item)
Sama dengan master prompt web (mobile kirim direct ke Laravel, no Next.js layer). URL absolut + token + secret SUDAH ter-embed di master prompt ini dalam blok TRACKING CREDENTIALS — gunakan PERSIS nilai dari blok tersebut (jangan mengarang, jangan ubah). Jalankan snippet siap-pakai di blok TRACKING CREDENTIALS; JANGAN membentuk ulang path/header secara manual.
- Headers (sudah disiapkan snippet di TRACKING CREDENTIALS):
  - `Authorization: Bearer <TOKEN>` (dari blok TRACKING CREDENTIALS)
  - `X-Token-Secret: <SECRET>` (dari blok TRACKING CREDENTIALS)
  - `X-Timestamp: <unix_seconds>`
  - `X-Signature: hmac_sha256("<X-Timestamp>.<raw_body>", "<X-Token-Secret>")`
- Body per fase: `{"version_id": <int>, "phase_key": "<key>", "status": "done", "output": "<ringkasan>"}`
- Body per sub-item: `{"version_id": <int>, "phase_key": "<key>", "task_key": "<sub_item_key>", "task_type": "halaman|menu|fitur|flow|api", "title": "<judul>", "status": "done", "output": "<ringkasan>"}`

`version_id`, `phase_key`, dan `task_key` HARUS diambil dari prompt ini (lihat blok TRACKING CREDENTIALS untuk version_id, dan §4 untuk daftar phase_key + sub-item task_key). JANGAN re-nomor.

Aturan error handling webhook: retry 3x backoff 1s/2s/4s, timeout 10s, 409 = sudah tercatat lanjut, 422 = perbaiki key, gagal total = catat dan lanjut — JANGAN berhenti permanen. Bila blok TRACKING CREDENTIALS tidak ada atau TOKEN kosong, JANGAN mengarang nilai — berhenti, minta user melakukan Setup Tracking di website sebelum build.

## 7. Operational Readiness (WAJIB baca sebelum build)
Sebelum menulis kode, BACA dokumen operasional dari wizard (web track sudah selesai):
- **`env-config.md`** — bagian Mobile (`--dart-define=API_BASE_URL`, APP_ENV, Firebase/FCM, keystore signing).
- **`security-checklist.md`** — khusus: keystore aman, token session via dio_cookie_manager (JANGAN hardcode), cert pinning bila perlu.
- **`deployment.md`** — build APK via CI, versi signing, distribusi.
- **`observability.md`** — crash monitoring (Sentry mobile), structured log.

Aturan:
- `API_BASE_URL` Wajib HTTPS production (bukan localhost) — dari `--dart-define`.
- JANGAN hardcode secret/keystore di repo. Pakai env CI + keystore.properties (gitignored).
- Keystore + signing config WAJIB ikut `deployment.md`.

## 8. Self-Verify Checklist
- [ ] `flutter analyze` clean
- [ ] `flutter test` pass
- [ ] Tidak ada `print()` / `debugPrint()` di production code
- [ ] Tidak ada `// TODO` / `// FIXME` di kode baru
- [ ] Drift migration applied jika ada perubahan schema
- [ ] Cookie manager ter-setup di dio interceptor
- [ ] GoRouter guards untuk protected routes
- [ ] Build APK release sukses tanpa warning

## 9. Output Instructions
- Jawab HANYA dengan master prompt di atas.
- WAJIB isi semua placeholder dengan data asli dari konteks pipeline.
- JANGAN tulis basa-basi.
- Setiap fase WAJIB punya semua 7 bagian.

'.platformSuffix($target).PHP_EOL.'

VERIFY sebelum respond: apakah SEMUA placeholder terisi? Apakah SEMUA fase dari konteks ada? Apakah auth cookie strategy dijelaskan di §2? Apakah marker akhir SELESAI_ALL ada setelah fase terakhir?';
