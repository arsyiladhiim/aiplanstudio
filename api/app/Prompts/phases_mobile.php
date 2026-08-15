<?php

return fn (string $target) => 'Kamu project manager dan tech lead untuk aplikasi MOBILE (Flutter). Buat breakdown fase pembangunan MOBILE app.

IMPORTANT CONTEXT: Aplikasi web (backend + frontend) SUDAH 100% SELESAI. Mobile ini adalah KLIENT yang menyambung ke API backend web yang sudah jadi. Semua fase mobile menunggu web selesai.

[STRUKTUR]
Output format teks terstruktur. JANGAN JSON. Pisahkan tiap fase dengan baris "---".

FASE: m_{key} | {title}
TUJUAN: {tujuan spesifik fase}
TASK: {task detail 1}
TASK: {task detail 2}
TASK: {task detail 3}
FILE: {file path yang dibuat/dimodifikasi}
HALAMAN: m_{key}_halaman_{n} | {judul screen/halaman} | {deskripsi screen}
MENU: m_{key}_menu_{n} | {judul menu/drawer item} | {parent/navigasi}
FITUR: m_{key}_fitur_{n} | {judul fitur} | {fungsionalitas}
FLOW: m_{key}_flow_{n} | {nama user flow} | {step1 → step2 → step3}
API: m_{key}_api_{n} | {endpoint} | {method} | {deskripsi endpoint web yang dipakai}
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
- sebut endpoint API web yang dipakai di tiap fase (di baris API:)
- Jangan mulai fase sebelum fase sebelumnya selesai

[SUB-ITEM CHECKPOINT — WAJIB untuk tracking detail]
Sesuaikan kategori untuk konteks mobile:
- HALAMAN = Screen/Page Flutter. Format key: m_{key}_halaman_{n}. Minimal 1 per fase.
- MENU = Navigation Drawer item / Bottom Nav. Format key: m_{key}_menu_{n}.
- FITUR = Feature/fungsionalitas mobile. Format key: m_{key}_fitur_{n}. Minimal 2 per fase.
- FLOW = User Flow (alur pengguna di mobile). Format key: m_{key}_flow_{n}.
- API = API endpoint web yang dipanggil. Format key: m_{key}_api_{n}. Minimal 1 per fase yang pakai API.
- Tidak semua fase punya semua 5 kategori — isi yang relevant. Minimal HALAMAN + FITUR per fase.

' . platformSuffix($target) . '

Jawab langsung dengan format yang diminta, tanpa teks lain.';
