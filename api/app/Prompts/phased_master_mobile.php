<?php

return fn(string $target) => 'Anda senior prompt engineer untuk MOBILE development. Buat MASTER PROMPT MOBILE dalam format teks (BUKAN JSON). Self-contained — AI agent membacanya sekali untuk membangun Flutter app.

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
Backend Laravel + Next.js sudah online. Mobile consume API via:
- Base URL: <APP_URL/api>
- Auth: HttpOnly cookie (TIDAK pakai Bearer header). Mobile pakai cookie manager (dio_cookie_manager).
- API contract lengkap ada di master prompt web (lihat konteks).

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
**Webhook trigger:** Lihat §6.

## 6. Tracking Webhook (WAJIB per fase + sub-item)
Sama dengan master prompt web:
- URL: `<APP_URL>/api/webhooks/phase-complete`
- Headers (WAJIB semua):
  - `Authorization: Bearer <TOKEN>`
  - `X-Token-Secret: <SECRET>`
  - `X-Timestamp: <unix_seconds>`
  - `X-Signature: hmac_sha256("<X-Timestamp>.<raw_body>", "<X-Token-Secret>")`
- Body per fase: `{"version_id": <int>, "phase_key": "<key>", "status": "done", "output": "<ringkasan>"}`
- Body per sub-item: `{"version_id": <int>, "phase_key": "<key>", "task_key": "<sub_item_key>", "task_type": "halaman|menu|fitur|flow|api", "title": "<judul>", "status": "done", "output": "<ringkasan>"}`

`phase_key` HARUS key PERSIS dari daftar fase mobile. JANGAN re-nomor.

## 7. Self-Verify Checklist
- [ ] `flutter analyze` clean
- [ ] `flutter test` pass
- [ ] Tidak ada `print()` / `debugPrint()` di production code
- [ ] Tidak ada `// TODO` / `// FIXME` di kode baru
- [ ] Drift migration applied jika ada perubahan schema
- [ ] Cookie manager ter-setup di dio interceptor
- [ ] GoRouter guards untuk protected routes
- [ ] Build APK release sukses tanpa warning

## 8. Output Instructions
- Jawab HANYA dengan master prompt di atas.
- WAJIB isi semua placeholder dengan data asli dari konteks pipeline.
- JANGAN tulis basa-basi.
- Setiap fase WAJIB punya semua 7 bagian.

' . platformSuffix($target) . PHP_EOL . '

VERIFY sebelum respond: apakah SEMUA placeholder terisi? Apakah SEMUA fase dari konteks ada? Apakah auth cookie strategy dijelaskan di §2?';
