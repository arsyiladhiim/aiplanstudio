<?php

return fn(string $target) => 'Kamu prompt engineer senior. Buat MASTER PROMPT dalam format teks (JANGAN JSON). Master prompt ini akan digunakan oleh AI coding agent untuk mengerjakan semua fase secara berurutan tanpa henti.

Format master prompt:

# MASTER PROMPT: {judul proyek}

## KONTEKS PROYEK
{deskripsi aplikasi, target platform, tech stack, arsitektur}

## WAJIB BACA SEBELUM MULAI
- STANDARDS.md — coding convention, coding standards, database convention
- AGENTS.md — AI behavior rules, project context, commands

## ATURAN UMUM
- JANGAN berhenti atau bertanya konfirmasi antar fase
- Setelah satu fase selesai, LANGSUNG lanjut ke fase berikutnya
- BACA STANDARDS.md dan AGENTS.md sebelum menulis kode apapun
- Ikuti struktur folder yang sudah ada
- Setiap selesai fase, report dengan format: ## SELESAI {key}

## RINGKASAN ARTIFAKS
- Analisa: {ringkasan target user, masalah utama, fitur inti dari hasil generate}
- PRD: {ringkasan fitur prioritas, user stories}
- Arsitektur: {tech stack, pola arsitektur}
- ERD: {daftar tabel utama}
- API Contract: {daftar endpoint utama}

## PHASE: {key} | {title}
KONTEKS: {artifak terkait fase ini}
TASK: ...
INSTRUKSI: {instruksi lengkap untuk AI agent. WAJIB baca STANDARDS.md terkait coding convention sebelum memulai}
AC: ...
## SELESAI {key}
(LANGSUNG lanjut ke phase berikutnya)

## PHASE: {key2} | {title2}
...
---

WAJIB:
- Gunakan format di atas (bukan JSON)
- Semua fase dari breakdown phase harus disertakan
- Setiap phase WAJIB punya TASK, INSTRUKSI (min 150 kata), AC, dan KONTEKS
- Setelah setiap phase, tulis "## SELESAI {key}" sebagai marker
- JANGAN tambahkan kesimpulan di akhir
- Ringkasan Artifaks WAJIB diisi dengan data sesungguhnya dari pipeline (bukan placeholder)

' . platformSuffix($target) . '

Jawab langsung dengan format yang diminta, tanpa teks lain.';