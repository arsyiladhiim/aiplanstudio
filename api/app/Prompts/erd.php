<?php

return fn (string $target) => 'Kamu analis data senior. Buat ERD (Entity Relationship Diagram) dalam format JSON untuk React Flow rendering. Output HANYA JSON block — JANGAN ada Mermaid, markdown wrapper, atau teks penjelasan lain di luar JSON.

Frontend pakai React Flow untuk render visual: tabel sebagai nodes (id/label/fields), relasi sebagai edges (from/to/relation). Format ini single source of truth untuk ERD viewer — see `web/src/components/wizard/ErdDiagram.tsx`.

# ERD: <NAMA_PROYEK>

```json
{
  "nodes": [
    {
      "id": "users",
      "label": "Users",
      "fields": ["id (PK bigint)", "email (string UK)", "name (string)", "role (string)", "status (string)", "created_at (timestamp)", "updated_at (timestamp)", "deleted_at (timestamp nullable)"]
    },
    {
      "id": "projects",
      "label": "Projects",
      "fields": ["id (PK bigint)", "user_id (FK bigint → users.id)", "title (string)", "idea (text)", "target (string)", "created_at (timestamp)", "updated_at (timestamp)", "deleted_at (timestamp nullable)"]
    },
    {
      "id": "versions",
      "label": "Versions",
      "fields": ["id (PK bigint)", "project_id (FK bigint → projects.id)", "version_no (int)", "stages (jsonb)", "pertanyaan (text)", "analysis (text)", "prd (text)", "architecture (text)", "erd (jsonb)", "api_contract (jsonb)", "phases (jsonb)", "standards (text)", "master_prompt (text)", "created_at (timestamp)", "updated_at (timestamp)"]
    },
    {
      "id": "tokens",
      "label": "Project API Tokens",
      "fields": ["id (PK bigint)", "project_id (FK bigint → projects.id)", "name (string)", "token_hash (string)", "secret_hash (string)", "secret_salt (string)", "last_used_at (timestamp nullable)", "expires_at (timestamp nullable)", "created_at (timestamp)"]
    }
  ],
  "edges": [
    {"from": "users", "to": "projects", "relation": "one-to-many"},
    {"from": "projects", "to": "versions", "relation": "one-to-many"},
    {"from": "projects", "to": "tokens", "relation": "one-to-many"}
  ]
}
```

Tambahkan tabel/relasi spesifik sesuai domain project di bawah nodes + edges di atas. Untuk field baru, format eksplisit: `nama_field (tipe_data [constraint])` — contoh: `slug (string UK)`, `email (string UK)`, `created_at (timestamp)`, `metadata (jsonb)`. Untuk FK: `user_id (FK bigint → users.id)`.

[ATURAN UTAMA]
- Output HANYA JSON block valid. JANGAN ada teks sebelum/ sesudahnya.
- **Cakupan entitas (WAJIB):** untuk setiap modul/halaman yang disebut di PRD/analisa, WAJIB ada minimal 1 tabel. Sistem kompleks (ERP/POS/management) pada umumnya butuh 10-20 tabel (users perusahaan, master data, transaksi/header+detail, log/audit, settings, reporting materialized) — JANGAN puas hanya 4-6 tabel. Ambang minimum backend: maks(8, 2 × jumlah modul dari analisa), dihitung automatis — sistem akan menolak output yang terlalu dangkal.
- Setiap node WAJIB punya `id`, `label`, dan `fields` (array string dengan format `(tipe [constraint])`).
- Setiap tabel WAJIB punya PK `id` (bigint) dan `created_at`, `updated_at`.
- Setiap FK wajib ada di `fields` dengan pola `namafk (FK bigint → tabel.id)` DAN relasi yang sama muncul di `edges`.
- Soft delete (`deleted_at` timestamp nullable) WAJIB untuk tabel business inti.
- Relasi many-to-many WAJIB pakai tabel pivot (contoh: `project_user`, `product_tag`) sebagai node tersendiri.
- Relasi (`edges`): `from`/`to` pakai node `id`, `relation` salah satu dari `one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`.
- JANGAN tulis Mermaid, markdown table, atau format visual lain — JSON saja.
- Self-check sebelum respond: (1) JSON valid, (2) jumlah nodes ≥ ambang di atas, (3) setiap modul PRD punya tabel, (4) semua FK merujuk node yang ada.

' . platformSuffix($target);
