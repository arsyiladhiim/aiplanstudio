# 01 — Overview

> Lihat juga: [00-README](00-README.md) · [02-architecture](02-architecture.md) · [05-wizard-flow](05-wizard-flow.md)

## Tujuan Produk
Membantu **solo developer** menghasilkan **dokumentasi & prompt lengkap** dari sebuah ide, yang saling nyambung dan siap disuapkan ke **AI coding agent** (Claude Code, Cursor, dll) untuk membangun aplikasi nyata.

Nilai utama: solo dev sering gagal saat memberi prompt manual ke AI karena konteks terputus antar langkah. App ini menjaga **benang merah** dari ide → PRD → arsitektur → ERD → prompt per fase, sehingga output AI agent lebih konsisten.

## Target User
- Solo / indie developer.
- Membangun **Web** dan/atau **Mobile (APK Android / iOS)**.
- Ingin perencanaan cepat namun terstruktur tanpa menulis semua dokumen manual.

## Yang Dihasilkan (Output)
Dari satu ide, app menghasilkan artefak berikut (target-aware — menyesuaikan Web/Both):
1. Analisa & klarifikasi kebutuhan
2. PRD (Product Requirements Document)
3. Arsitektur & tech stack
4. ERD database + API contract
5. Breakdown fase/task pengembangan
6. Master Prompt + prompt siap-copy untuk tiap fase

## Prinsip Kunci
- **Bukan eksekutor kode.** App hanya menghasilkan dokumen & prompt. Eksekusi dilakukan AI agent eksternal.
- **Target-aware.** Output menyesuaikan platform tujuan (stack, ERD, prompt berbeda untuk Web vs Mobile dalam target both).
- **Checkpoint-driven.** Wizard berhenti minta approve tiap tahap (bisa auto-run penuh bila diinginkan).
- **Resumable & terdokumentasi.** Semua keputusan & progres tercatat agar bisa dilanjut kapan saja.

## Scope MVP
- Auth multi-user (admin/member), User Management.
- AI Provider global (OpenAI-compatible) yang diatur admin.
- Wizard "Buat Plan": 22 tahap (target both) / 16 tahap (target web).
- Projects: arsip + versioning (v1, v2, …) + timestamp + progress checklist + export (.md/.zip).
- Templates: menu ada, seed preset minimal.
- Landing page.

## Non-Scope (nanti)
- Eksekusi kode nyata / integrasi langsung ke agent eksternal.
- OAuth, provider per-user.
- Templates marketplace yang kaya.
- Blog/komunitas publik.
- Kolaborasi tim, diff visual antar versi.
- TLS/production hardening penuh.

## Glossary
- **Project** — satu ide yang direncanakan.
- **Version** — snapshot satu kali run pipeline atas sebuah Project (untuk "update ke Versi 2").
- **Stage / Tahap** — satu langkah wizard (Analisa, PRD, dst).
- **Phase** — fase pembangunan aplikasi target (Setup, Auth, Fitur, Deploy) hasil breakdown; tiap phase punya prompt.
