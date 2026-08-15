<?php

return fn(string $target) => 'Kamu analis data senior. Buat ERD (Entity Relationship Diagram) dalam format teks parse-friendly + Mermaid erDiagram syntax. Output DOUBLE FORMAT — line-format untuk machine parsing DAN Mermaid block untuk visual rendering.

# ERD: <NAMA_PROYEK>

## 1. Mermaid erDiagram (visual rendering)

```mermaid
erDiagram
    USERS ||--o{ PROJECTS : creates
    USERS ||--o{ VERSIONS : owns
    PROJECTS ||--|{ VERSIONS : has
    PROJECTS ||--o{ TOKENS : has
    USERS {
        bigint id PK
        string email UK
        string name
        string role
        string status
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    PROJECTS {
        bigint id PK
        bigint user_id FK
        string title
        text idea
        string target
        timestamp created_at
        timestamp updated_at
        timestamp deleted_at
    }
    VERSIONS {
        bigint id PK
        bigint project_id FK
        int version_no
        jsonb stages
        text pertanyaan
        text analysis
        text prd
        text architecture
        jsonb erd
        jsonb api_contract
        jsonb phases
        text standards
        text master_prompt
        timestamp created_at
        timestamp updated_at
    }
    TOKENS {
        bigint id PK
        bigint project_id FK
        string name
        string token_hash
        string secret_hash
        string secret_salt
        timestamp last_used_at
        timestamp expires_at
        timestamp created_at
    }
```

Tambahkan tabel spesifik sesuai domain project. Untuk setiap tabel baru, sertakan: PK, FK (jika ada), field penting dengan tipe data (bigint, string, text, jsonb, timestamp), dan constraint (UK untuk unique).

## 2. Line Format (WAJIB, parse-friendly)

Output WAJIB dalam format garis persis seperti di bawah. Setiap baris dimulai dengan keyword UPPERCASE.

1. Baris TABEL — satu per entitas:
TABEL: {nama_tabel} | {field1},{field2},{field3},...,{index_name}:{field}

2. Baris RELASI — satu per hubungan:
RELASI: {entitas_a} -> {entitas_b} | {jenis_relasi}

3. Baris INDEX — untuk kolom yang perlu index (performance):
INDEX: {tabel} | {field} | {unique|index}

WAJIB sertakan index untuk: foreign keys, kolom yang sering di-query (status, created_at, user_id), kolom yang dipakai untuk lookup (email, slug).

[CONTOH]
TABEL: products | id,user_id,name,price,stock,category,created_at,updated_at,deleted_at,idx_products_user_id:user_id
TABEL: orders | id,user_id,total,status,created_at,updated_at,idx_orders_user_id:user_id,idx_orders_status:status
TABEL: order_items | id,order_id,product_id,quantity,price,idx_order_items_order_id:order_id,idx_order_items_product_id:product_id

RELASI: users -> products | one-to-many
RELASI: users -> orders | one-to-many
RELASI: orders -> order_items | one-to-many
RELASI: products -> order_items | one-to-many

INDEX: products | user_id | index
INDEX: orders | status | index

[ATURAN]
- Minimal 4 entitas (TABEL) yang relevan dengan aplikasi.
- Field dipisah koma, tanpa spasi berlebih. Sertakan id dan foreign key.
- Tipe data WAJIB eksplisit di Mermaid block (bigint, string, text, jsonb, timestamp, dll).
- Soft delete (`deleted_at`) WAJIB untuk tabel business (users, projects, versions).
- Setiap tabel WAJIB punya `created_at`, `updated_at`.
- Relasi pakai jenis: one-to-many, many-to-many, atau one-to-one.
- Bahasa Indonesia untuk deskripsi.
- Mermaid block WAJIB di ATAS line format.
- JANGAN tulis kalimat pembuka, penjelasan, atau teks lain di luar baris format di atas.
- Mulai langsung dengan "# ERD:" + Mermaid block.

' . platformSuffix($target);
