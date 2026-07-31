<?php

$flutterStandards = '
## Tech Stack
(dari dokumen arsitektur — Flutter/Dart, Android SDK, third-party packages)

## Coding Standards
### Flutter / Dart
- Dart null safety WAJIB diaktifkan
- Widget-based architecture — composition over inheritance
- State management: Riverpod 2.x (atau sesuai arahan arsitektur)
- Material Design 3 (M3) theming — gunakan tema dari ColorScheme
- snake_case untuk folder dan file names
- PascalCase untuk class dan widget names
- camelCase untuk variabel dan fungsi
- Setiap screen dalam folder terpisah di lib/features/
- Setiap widget publik dalam file terpisah
- GoRouter untuk navigasi — define all routes di app/router.dart
- Repository pattern untuk data layer — pisahkan API calls dari UI
- Jangan import material.dart secara boros — gunakan selective import

## Database Conventions (Lokal)
- SQLite via drift/sqflite untuk penyimpanan lokal
- Atau Firebase Firestore jika online sync diperlukan
- snake_case untuk field names di JSON API response
- Model class dengan factory fromJson() dan toJson()

## Architecture Patterns
(dari dokumen arsitektur — pola yang digunakan, alur data, state management)

## Git Convention
- feat: untuk fitur baru
- fix: untuk bug fix
- chore: untuk maintenance
- docs: untuk dokumentasi
- Format: "type(scope): deskripsi singkat"

## AI Coding Rules
- WAJIB baca STANDARDS.md ini sebelum menulis kode
- Jangan hapus/rename file yang sudah ada tanpa konfirmasi
- Ikuti struktur folder yang sudah ada di project
- Gunakan dependency yang sudah ada, jangan tambah baru jika tidak perlu
- Setiap function publik wajib punya unit test
';

$webStandards = '
## Tech Stack
(dari dokumen arsitektur — frontend, backend, database, infrastruktur, third-party)

## Coding Standards
### PHP/Laravel
- PSR-12 coding style
- Type hints untuk semua parameters dan return types
- DocBlock untuk setiap method public
- Form Request untuk validasi input
- API Resource untuk response formatting
- snake_case untuk database columns, camelCase untuk method

### Next.js / TypeScript
- App Router (bukan Pages Router)
- Server Components by default
- "use client" only when interactivity needed
- TypeScript strict mode
- PascalCase untuk components, camelCase untuk functions/variables

### Tailwind CSS
- Utility-first approach
- Class ordering: layout → spacing → typography → colors → states

## Database Conventions
(skema dari ERD — tabel utama, relasi, field convention)
- snake_case untuk table dan column names
- created_at, updated_at timestamps di setiap tabel
- Soft deletes dengan deleted_at

## Architecture Patterns
(dari dokumen arsitektur — pola yang digunakan, alur data)

## Git Convention
- feat: untuk fitur baru
- fix: untuk bug fix
- chore: untuk maintenance
- docs: untuk dokumentasi
- Format: "type(scope): deskripsi singkat"

## AI Coding Rules
- WAJIB baca STANDARDS.md ini sebelum menulis kode
- Jangan hapus/rename file yang sudah ada tanpa konfirmasi
- Ikuti struktur folder yang sudah ada di project
- Gunakan dependency yang sudah ada, jangan tambah baru jika tidak perlu
- Setiap function public wajib punya unit test
';

return fn(string $target) => 'Buat file STANDARDS.md untuk proyek ini. Output dalam format Markdown.

WAJIB mencakup:

# STANDARDS.md
' . ($target === 'mobile' || $target === 'both'
    ? $flutterStandards
    : $webStandards) . '

' . platformSuffix($target);
