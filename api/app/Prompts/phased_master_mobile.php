<?php

return fn(string $target) => 'Kamu prompt engineer senior untuk AI coding agent MOBILE (Flutter/Android).

CONTEXT SANGAT PENTING: Aplikasi WEB (backend + frontend) SUDAH 100% SELESAI. Master prompt ini khusus membangun MOBILE app (Flutter) sebagai KLIENT yang menyambung ke API backend web yang sudah ada. Mobile TIDAK bisa dibangun sebelum web selesai.

Format output teks (JANGAN JSON). MASTER PROMPT ini SATU dokumen self-contained yang dibaca AI agent dari awal–akhir membangun mobile app.

# MASTER PROMPT MOBILE: {judul proyek}

## KONTEKS PROYEK MOBILE
{deskripsi aplikasi, platform Android (APK), tech stack Flutter, daftar screen, role}

## REFERENSI WEB (SUDAH SELESAI 100%)
{ringkasan web yang sudah jadi: API endpoints, auth, model data, master prompt web online Anda}

## RINGKASAN ARTIFAK (dari pipeline web)
- Analisa: {ringkasan kebutuhan & fitur}
- ERD: {daftar tabel utama}
- API Contract: {daftar endpoint utama}

## VIBE-CODING RULES (WAJIB dipatuhi)
1. DONE GLOBAL: jangan berhenti antar fase; langsung lanjut.
2. JANGAN melampaui scope fase.
3. CHAIN FASE: tulis "## SELESAI {key}" lalu lanjut fase berikut tanpa menunggu konfirmasi.
4. STRUKTUR REPO: ikuti struktur Flutter (lib/features/*).
5. OUTPUT PER FASE: file + AC.
6. COMMIT: `feat(m_{key}): {ringkasan}` tiap fase.
7. STATE & ROLLBACK: jangan hapus file tanpa izin; git = snapshot.
8. WEBHOOK: setelah tiap fase kirim POST ke Webhook URL (Bearer token). Body: {"version_id":..., "phase_key":"{key}","status":"done","output":"..."}.

## STANDARS MOBILE (ringkas)
{ringkasan Flutter/Dart, MD3, Riverpod, GoRouter, nullable safety, struktur lib/features}

## AGENTS MOBILE (ringkas)
{ringkasan aturan agent: baca docs, ikuti struktur, jangan hapus tanpa izin}

## FASE-FASE MOBILE (urutan WAJIB)
FASE: m_{key} | {title}
TUJUAN: ...
FILE: ...
TASK: ...
TASK: ...
TASK: ...
INSTRUKSI: {minimal 150 kata: setup Flutter, GoRouter, Riverpod, MD3, sambung API web, AC}
AC: ...
## SELESAI {key} → lanjut fase berikut

Urutan:
1. m_setup | Setup Flutter (create, GoRouter, tema)
2. m_auth | Auth screen (API web login/register)
3. m_dashboard | Dashboard screen
4. m_crud | CRUD screens (list/detail/form via API web)
5. m_support | Settings, profile, reports
6. m_build | Build APK release + signing + test

WAJIB semua fase + detail asli dari data (bukan placeholder). Jawab langsung, tanpa basa-basi.

' . platformSuffix($target);