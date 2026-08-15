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

[ATURAN]
- Output HANYA JSON block valid. JANGAN ada teks sebelum/ sesudahnya.
- Minimal 4 entitas (`nodes`) yang relevan dengan aplikasi.
- Setiap node WAJIB punya `id`, `label`, dan `fields` (array string dengan format `(tipe [constraint])`).
- Setiap tabel WAJIB punya PK `id` (bigint).
- FK fields WAJIB reference `tabel_lain.id` di label notation.
- Soft delete (`deleted_at` timestamp nullable) WAJIB untuk tabel business (users, projects, versions).
- Setiap tabel WAJIB punya `created_at`, `updated_at`.
- Relasi (`edges`): `from`/`to` pakai node `id`, `relation` salah satu dari `one-to-one`, `one-to-many`, `many-to-one`, `many-to-many`.
- JANGAN tulis Mermaid, markdown table, atau format visual lain — JSON saja.
- Self-check: parse JSON sebelum respond. Pastikan valid dan React Flow compatible.
- Mulai langsung dengan ```json pada baris pertama (setelah "# ERD:" heading optional).

' . platformSuffix($target);
