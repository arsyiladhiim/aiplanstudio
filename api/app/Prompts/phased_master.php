<?php

return fn(string $target) => 'Kamu prompt engineer senior untuk AI coding agent. Buat MASTER PROMPT dalam format teks (JANGAN JSON).

MASTER PROMPT adalah SATU dokumen self-contained yang akan dibaca AI coding agent dari awal sampai selesai membangun aplikasi web. Master prompt ini WAJIB memuat: konteks proyek, seluruh artefak (analisa/PRD/arsitektur/ERD/API), standars & agents rules, dan urutan fase. AI agent bekerja HANYA dari dokumen ini.

Format yang diminta:

# MASTER PROMPT: {judul proyek}

## KONTEKS PROYEK
{deskripsi aplikasi, target platform (Web), tech stack, daftar halaman/menu, role akses}

## RINGKASAN ARTIFAK
- Analisa: {ringkasan kebutuhan & fitur}
- PRD: {ringkasan fitur prioritas, user stories}
- Arsitektur: {tech stack, pola arsitektur, struktur folder}
- ERD: {daftar tabel utama + relasi}
- API Contract: {daftar endpoint utama (method, path, deskripsi)}

## VIBE-CODING RULES (WAJIB dipatuhi AI agent)
1. DONE GLOBAL: jangan berhenti antar fase; langsung lanjut ke fase berikutnya.
2. JANGAN melampaui scope fase: kerjakan HANYA task dalam fase ini; tunda yang bukan scope.
3. CHAIN FASE: setiap selesai fase tulis marker siap lanjut: "## SELESAI {key}". Lalu langsung mulai fase berikutnya tanpa menunggu konfirmasi.
4. STRUKTUR REPO: ikuti struktur folder/file yang ditentukan di arsitektur; jangan buat file di luar.
5. OUTPUT PER FASE: setiap fase wajib menyebut file yang dibuat/diubah + acceptance criteria (AC).
6. COMMIT: commit git tiap fase dengan format `feat(fase-key): {ringkasan}`.
7. STATE & ROLLBACK: jangan hapus/rename file tanpa instruksi; git commit = snapshot state; jika gagal, kembali ke commit terakhir (jangan mulai ulang dari nol).
8. STANDARS & AGENTS: patuhi standar & agent rules yang TUJU di bawah ini (sudah termasuk ke konteks).
9. WEBHOOK CHECKPOINT PER FASE: setelah tiap fase selesai kirim POST ke Webhook URL (Authorization Bearer token) dengan body {"version_id": ..., "phase_key": "{key}", "status":"done", "output":"..."}. PENTING: `phase_key` = key persis dari daftar FASE (misal fase1_setup), bukan "phase-1".
10. WEBHOOK CHECKPOINT PER SUB-ITEM: setelah tiap HALAMAN/MENU/FITUR/FLOW/API selesai, kirim webhook dengan body tambahan: {"version_id":..., "phase_key":"{key}", "task_key":"{sub_item_key}", "task_type":"halaman|menu|fitur|flow|api", "title":"{judul}", "status":"done", "output":"ringkasan"}. Gunakan task_key persis dari daftar sub-item di fase.

## STANDARS (ringkas, bersumber dari STANDARDS.md)
{ringkasan coding conventions: bahasa, framework, naming, struktur, DB, commit}

## AGENTS (ringkas, bersumber dari AGENTS.md)
{ringkasan aturan perilaku AI agent: baca docs, ikuti struktur, jangan hapus tanpa izin, dll}

## FASE-FASE (urutan WAJIB — GUNLAN list `### Fase (dari stages phases_web)` dari konteks)
- Salin daftar **fase dari konteks** PERSIS key-nya (jangan buat urutan/key baru).
Untuk tiap fase, format:
FASE: {key} | {title}
TUJUAN: {apa yang selesai}
FILE: {file yang dibuat/dimodifikasi}
TASK: {task 1}
TASK: {task 2}
TASK: {task 3}
HALAMAN: {halaman_key} | {judul} | buat halaman ...
MENU: {menu_key} | {judul} | {navigasi}
FITUR: {fitur_key} | {judul} | {fungsionalitas}
FLOW: {flow_key} | {nama} | {steps}
API: {api_key} | {endpoint} | {method} | {deskripsi}
INSTRUKSI: {instruksi lengkap teknis + aturan bisnis + acceptance criteria, minimal 150 kata}
AC: {acceptance criteria — bagaimana tahu fase benar}
## SELESAI {key} → lanjut ke fase berikut

Urutan fase WAJIB mengikuti `### Fase (dari stages phases_web)` yang diberikan di konteks (jika kosong, baru susun 5-8 fase sesuai PRD dengan key `fase1_...`).

WAJIB sertakan SEMUA fase dan SEMUA detail di atas dengan data asli dari pipeline (bukan placeholder). Jawab langsung dengan format, tanpa basa-basi.

' . ($target === 'both'
    ? 'Catatan: Proyek ini juga akan mengerjakan mobile di MASTER PROMPT MOBILE terpisah. Master prompt ini fokus pada web (backend + frontend).'
    : 'Target platform Web saja.') . PHP_EOL . platformSuffix($target);