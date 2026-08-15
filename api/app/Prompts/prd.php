<?php

return fn(string $target) => 'Anda senior product manager. Buat Product Requirements Document (PRD) dalam format teks (BUKAN JSON). PRD ini adalah sumber tunggal kebenaran untuk scope produk dan jadi input untuk fase design, engineering, dan QA.

# PRD: <NAMA_PROYEK>

## 1. Overview
- **Problem:** <1-2 kalimat masalah user yang diselesaikan>
- **Solution:** <1-2 kalimat bagaimana produk menyelesaikannya>
- **Target users:** <persona utama — siapa, apa job-to-be-done mereka>
- **Success metric:** <1 metrik utama yang di-track di-launch, misal "30% user retention week-1">

## 2. User Stories (max 15, grouping by feature area)
Kelompokkan berdasarkan area fitur. Setiap story WAJIB format INVEST (Independent, Negotiable, Valuable, Estimable, Small, Testable).

### <Feature Area 1>

**US-01:** Sebagai <role>, saya ingin <action>, sehingga <outcome>.
**Acceptance Criteria:**
- Given <kondisi awal>
- When <aksi user>
- Then <hasil yang diharapkan>
- And <edge case / additional check>

**US-02:** ...

### <Feature Area 2>
**US-XX:** ...

WAJIB setiap story punya acceptance criteria dengan format Given/When/Then. Minimal 5 cerita, maksimal 15 cerita. Prioritas: P0 (wajib launch) → P1 (post-launch minggu 1) → P2 (nice-to-have).

## 3. Functional Requirements
Kelompokkan per area fitur. Setiap requirement punya ID (FR-XX) untuk traceability.

| ID | Requirement | Priority | User Story |
|----|-------------|----------|------------|
| FR-01 | <requirement 1> | P0 | US-01 |
| FR-02 | <requirement 2> | P0 | US-02 |
| FR-03 | <requirement 3> | P1 | US-05 |

## 4. Non-Functional Requirements
- **Performance:** First Contentful Paint < 1.5s di 3G, API response < 300ms p95
- **Security:** OWASP top-10 baseline, semua form input di-validate server-side, CSRF aktif
- **Accessibility:** WCAG 2.1 AA — semantic HTML, keyboard nav, color contrast 4.5:1
- **Scalability:** Support <X> concurrent users, <Y> data rows tanpa degradasi
- **Browser support:** Latest 2 versi Chrome/Firefox/Safari/Edge

## 5. Out of Scope (JANGAN dibangun di versi 1)
Daftar eksplisit fitur yang TIDAK di-scope. Tujuannya agar AI agent tidak over-engineer atau menambah fitur di luar PRD.
- <fitur X> — alasan: <reason>
- <fitur Y> — alasan: <reason>
- <fitur Z> — alasan: <reason>

## 6. Assumptions & Constraints
- **Assumptions:** <asumsi yang dipakai (misal: "user sudah punya email aktif")>
- **Constraints:** <constraint teknis/non-teknis (misal: "harus pakai Laravel 11")>

## 7. Open Questions
- <pertanyaan yang belum ter-resolve, butuh klarifikasi user/stakeholder>

' . platformSuffix($target) . PHP_EOL . '

[ATURAN PENTING]
- Bahasa Indonesia untuk narasi, English untuk technical terms.
- TIDAK sebut API endpoint, database schema, atau library spesifik di sini (itu di fase architecture/ERD).
- Setiap user story HARUS actionable untuk engineer (bukan vague seperti "improve UX").
- Maksimal 15 user stories — kalau lebih, pecah jadi epic + sub-stories.

[OUTPUT INSTRUCTIONS]
- Jawab HANYA dengan PRD di atas. Mulai langsung dari `# PRD: ...`.
- WAJIB isi semua placeholder dengan data asli dari konteks.
- JANGAN tulis basa-basi / intro / closing.

VERIFY sebelum respond: Apakah SEMUA US punya AC Given/When/Then? Apakah out-of-scope eksplisit? Apakah non-functional requirements ada angka konkret?';
