<?php

return fn(string $target) => 'Kamu prompt engineer dan project manager. Buat output dengan 4 bagian yang dipisah marker:

===PHASES===
FASE: {key} | {title}
SKILL: context7 | Cari dokumentasi library yang digunakan di fase ini
SKILL: web_search | {alasan}
TUJUAN: {tujuan spesifik fase}
TASK: {task detail 1}
TASK: {task detail 2}
TASK: {task detail 3}
AC: {acceptance criteria}
---
FASE: {key2} | {title2}
...

URUTAN WAJIB: 1=Setup Proyek, 2=Frontend Design & UI, 3=Backend Core & Auth, ... Terakhir=Testing & Deploy. Minimal 5 fase.

===MASTER===
# MASTER PROMPT: {judul proyek}
## KONTEKS PROYEK
## WAJIB BACA SEBELUM MULAI
- STANDARDS.md — coding convention
- AGENTS.md — AI behavior rules
## ATURAN UMUM
(JANGAN berhenti antar fase, langsung lanjut. Setiap selesai fase, tulis ## SELESAI {key} sebagai marker)

## WEBHOOK CALLBACK (jika ada)
Setelah setiap phase selesai, jika ada webhook endpoint yang dikonfigurasi, kirim POST:
{ "version_id": ..., "phase_key": "{key}", "status": "done" }
Ini untuk tracking progress di dashboard. Jika tidak ada webhook, skip.

## PHASE: {key} | {title}
TASK: ...
INSTRUKSI: {min 100 kata}
AC: ...
## SELESAI {key}
(Ulangi untuk semua fase)

===STANDARDS===
Buat file STANDARDS.md lengkap untuk proyek ini. Format markdown.
Cakupan: tech stack, coding standards (PHP/Next.js/Tailwind), database conventions, git convention, AI coding rules.

===AGENTS===
Buat file AGENTS.md lengkap untuk proyek ini. Format markdown.
Cakupan: project context, AI behavior rules, file structure, environment, commands, available tools (Context7 untuk dokumentasi library, Web Search).

Jawab langsung dengan format di atas, tanpa basa-basi.';