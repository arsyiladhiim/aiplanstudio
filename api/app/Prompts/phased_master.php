<?php

return fn(string $target) => 'Kamu project manager dan tech lead. Buat tahapan pembangunan aplikasi berdasarkan halaman-halaman yang sudah didefinisikan. Sertakan MASTER PROMPT, STANDARDS, dan AGENTS.

Output HARUS terdiri dari 4 bagian yang dipisah marker:

===PHASES===
FASE: {key} | {title}
TUJUAN: {tujuan spesifik fase — halaman apa yang selesai}
FILE: {file path yang akan dibuat/dimodifikasi}
TASK: {task detail 1}
TASK: {task detail 2}
TASK: {task detail 3}
AC: {acceptance criteria — bagaimana tahu fase selesai}
---
FASE: {key2} | {title2}
...

URUTAN WAJIB FASE (berdasarkan halaman):
1. Setup Project & Auth — Halaman Login, Register
2. Halaman Dashboard — halaman utama
3. Halaman CRUD — halaman kelola data utama
4. Halaman Pendukung — laporan, export, dll
5. Build & Polish — testing, build akhir

KHUSUS UNTUK MOBILE (jika target mobile/both):
- Prefix key fase dengan "m_", contoh: "FASE: m_setup | Setup Proyek Flutter"
- Fase 1: flutter create project, setup routing, GoRouter
- Fase 2-4: buat screen dengan widget Flutter + Riverpod state management
- Fase 5: flutter build apk --release, konfigurasi signing, testing

===MASTER===
# MASTER PROMPT: {judul proyek}
## KONTEKS PROYEK
{deskripsi aplikasi, target platform, daftar halaman}

## WAJIB BACA SEBELUM MULAI
- STANDARDS.md — coding convention
- AGENTS.md — AI behavior rules

## ATURAN UMUM
- JANGAN berhenti antar fase, langsung lanjut
- Setiap selesai fase, tulis ## SELESAI {key} sebagai marker
- Jangan hapus atau rename file yang sudah dibuat

## WEBHOOK CALLBACK
Setelah setiap phase selesai, kirim POST ke Webhook URL yang diberikan di KONTEKS PROYEK pada pesan pengguna (jangan pakai localhost).
Header: Authorization: Bearer {project_api_token} (ganti dengan token API project dari menu Settings project)
Body: { "version_id": {version_id}, "phase_key": "{key}", "status": "done", "output": "ringkasan hasil" }
Ini WAJIB untuk tracking progress. Isi version_id sesuai nilai yang ada di KONTEKS PROYEK.

## PHASE: {key} | {title}
TASK: ...
INSTRUKSI: {instruksi lengkap untuk AI agent. Minimal 100 kata}
AC: ...
## SELESAI {key}
(Ulangi untuk semua fase)

===STANDARDS===
Buat file STANDARDS.md lengkap untuk proyek ini. Format markdown.

' . ($target === 'mobile' || $target === 'both'
  ? 'Cakupan: Flutter/Dart coding standards, Material Design 3, Riverpod state management, GoRouter navigasi, folder structure lib/features/*, build APK commands.'
  : 'Cakupan: PHP/Laravel, Next.js/TypeScript, Tailwind CSS, database conventions, git convention, AI coding rules.') . '

===AGENTS===
Buat file AGENTS.md lengkap untuk proyek ini. Format markdown.
Cakupan: project context, AI behavior rules, file structure, environment, commands, available tools.

' . ($target === 'mobile' || $target === 'both'
  ? 'Commands: flutter pub get, flutter run, flutter build apk --release, flutter test, flutter analyze. Environment: Flutter SDK >= 3.24, Dart >= 3.5, Android SDK 34+, Java 17.'
  : 'Commands: composer install, npm install, php artisan serve, npm run dev, php artisan test, npx playwright test. Environment: Node 24, PHP 8.4, PostgreSQL 16, Redis.') . '

Jawab langsung dengan format 4 bagian di atas, tanpa basa-basi.';
