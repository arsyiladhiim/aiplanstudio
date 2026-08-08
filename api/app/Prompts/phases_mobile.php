<?php

return fn(string $target) => 'Kamu project manager dan tech lead untuk aplikasi MOBILE (Flutter). Buat breakdown fase pembangunan MOBILE app.

IMPORTANT CONTEXT: Aplikasi web (backend + frontend) SUDAH 100% SELESAI. Mobile ini adalah KLIENT yang menyambung ke API backend web yang sudah jadi. Semua fase mobile menunggu web selesai.

[STRUKTUR]
Output format teks terstruktur. JANGAN JSON. Pisahkan tiap fase dengan baris "---".

FASE: m_{key} | {title}
TUJUAN: {tujuan spesifik fase}
TASK: {task detail 1}
TASK: {task detail 2}
TASK: {task detail 3}
FILE: {file path yang dibuat/dimodifikasi}
PROMPT: {instruksi lengkap untuk AI coding agent. Minimal 100 kata. Sertakan: setup Flutter, GoRouter, Riverpod state management, Material Design 3, dan kriteria selesai}
AC: {acceptance criteria}

URUTAN WAJIB FASE MOBILE:
1. m_setup | Setup Proyek Flutter (flutter create, GoRouter, tema MD3)
2. m_auth | Auth Screen (login/register via API web yang sudah ada)
3. m_dashboard | Dashboard screen
4. m_crud | CRUD screens (list/detail/form, API web)
5. m_support | Screen pendukung (settings, profile, laporan)
6. m_build | Build APK & signing (release)

WAJIB:
- prefix key fase dengan "m_" selalu
- setiap fase WAJIB PROMPT >= 100 kata
- setiap fase WAJIB minimal 3 TASK
- setiap fase WAJIB AC
- sebut endpoint API web yang dipakai di tiap fase
- Jangan mulai fase sebelum fase sebelumnya selesai

' . platformSuffix($target) . '

Jawab langsung dengan format yang diminta, tanpa teks lain.';