<?php

return fn(string $target) => 'Buat file AGENTS.md untuk proyek ini. Output dalam format Markdown.

AGENTS.md berisi aturan perilaku untuk AI coding agent yang akan mengerjakan proyek ini.

WAJIB mencakup:

# AGENTS.md — AI Coding Agent Rules

## Project Context
- Nama Proyek: (dari data pipeline)
- Target Platform: (web/mobile/both)
- Tech Stack: (dari dokumen arsitektur)
- Database: (dari ERD)
- Deployment Target: (sesuai target)

## AI Behavior Rules
1. Baca STANDARDS.md SEBELUM menulis kode apapun
2. Jangan hapus atau rename file tanpa instruksi eksplisit
3. Ikuti struktur folder yang sudah ada di project
4. Setiap perubahan wajib di-commit dengan format konvensional
5. Jika tidak yakin dengan keputusan teknis, TANYA — jangan asumsi
6. Prioritaskan kode yang sudah ada daripada rewrite dari awal
7. Gunakan dependency yang sudah terinstall; jangan tambah baru tanpa alasan kuat

## File Structure
(dari dokumen arsitektur — struktur folder lengkap)

## Environment
- Development: localhost:3000 (frontend), localhost:8000 (backend)
- Database: PostgreSQL 18
- Cache: Redis
- Queue: Laravel Horizon

## Commands
- Backend: php artisan serve
- Frontend: npm run dev
- Migration: php artisan migrate
- Test BE: php artisan test
- Test FE: npx playwright test

## Available Tools
AI agent memiliki akses ke tools berikut:
- **Context7**: Fetch dokumentasi library terbaru. Gunakan untuk mencari API docs, contoh kode, dan versi terbaru dari dependency.
- **Web Search**: Cari pattern terbaik, best practice, dan solusi untuk masalah teknis.

WAJIB gunakan Context7 sebelum menulis kode yang menggunakan library asing (baru atau belum dikuasai).

' . platformSuffix($target);