# 03 — Database Schema

> Lihat juga: [04-api-contract](04-api-contract.md) · [06-ai-pipeline](06-ai-pipeline.md) · [02-architecture](02-architecture.md)
> DBMS: PostgreSQL. ORM: Eloquent (Laravel migrations). DB lama `aistack` **dihapus & dibuat ulang dari 0**.

## Schema PostgreSQL

Tabel dipisah ke 3 schema berdasarkan domain:

| Schema | Isi |
|--------|-----|
| `aiplanstudio_master` | Master data — `users`, `password_reset_tokens`, `personal_access_tokens`, `templates`, `migrations` |
| `aiplanstudio_project` | Data project — `projects`, `versions`, `phase_progress` |
| `aiplanstudio_settings` | Settings & sistem — `ai_providers`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` |

Konfigurasi `search_path` di `config/database.php`:
```php
'search_path' => 'aiplanstudio_master, aiplanstudio_project, aiplanstudio_settings, public'
```
Semua model dapat mengakses tabel tanpa prefix schema karena PostgreSQL mencari melalui `search_path`.

## Diagram Relasi (ringkas)
```
users ──1:N── projects ──1:N── versions ──1:N── phase_progress
ai_providers (singleton, global)
templates (standalone)
sessions, personal_access_tokens (Sanctum)
```

## Tabel

### users
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | hash bcrypt |
| role | enum `admin`\|`member` | default `member`; user pertama = `admin` |
| timestamps | | created_at, updated_at |

### ai_providers (multi-row, admin bisa tambah banyak)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string(100) | default `'AI Provider'`, nama tampilan |
| base_url | string(255) | endpoint AI, mis. `https://api.openai.com/v1` |
| provider_type | string(20) | `'openai'` \| `'anthropic'` \| `'custom'` (default `'openai'`) |
| api_key | text | **cast `encrypted`** (Laravel APP_KEY) |
| model | string(100) | default `'gpt-4o'` |
| is_active | boolean | default `false`; hanya 1 provider bisa active |
| last_test_response | text nullable | hasil test koneksi terakhir |
| last_test_at | timestamp nullable | |
| timestamps | | |

### templates
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string | mis. "SaaS Dashboard" |
| target | enum `web`\|`mobile`\|`both` | |
| description | text nullable | |
| seed | jsonb | nilai awal untuk mengisi wizard |
| timestamps | | |

### projects
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| user_id | bigint FK → users | scoping kepemilikan |
| title | string | |
| idea | text | ide awal |
| target | enum `web`\|`mobile`\|`both` | default `'web'`; menentukan output target-aware |
| stack | string nullable | preferensi stack (opsional) |
| timestamps | | |

### versions (snapshot 1 run pipeline)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| project_id | bigint FK → projects | |
| version_no | integer | 1, 2, 3 … ("update ke Versi 2") |
| stage_status | jsonb | status tiap tahap: `{analisa:'done', prd:'running', ...}` |
| analysis | text nullable | hasil tahap Analisa |
| prd | text nullable | markdown PRD |
| architecture | text nullable | arsitektur & tech stack |
| erd | jsonb nullable | `{nodes:[], edges:[]}` untuk React Flow |
| api_contract | jsonb nullable | daftar endpoint terstruktur — **belum diisi pipeline** (lihat [16-audit-fix-plan](16-audit-fix-plan.md#rp)) |
| phases | jsonb nullable | `[{key,title,prompt}, ...]` |
| master_prompt | text nullable | prompt utama gabungan |
| timestamps | | created_at = timestamp versi |

Unik: (`project_id`, `version_no`).

### phase_progress (checklist per phase)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| version_id | bigint FK → versions | |
| phase_key | string | merujuk `phases[].key` |
| done | boolean default false | |
| timestamps | | |
| **UNIQUE** | (version_id, phase_key) | mencegah duplikasi |

### Bawaan Framework & Sanctum
- `sessions` — penyimpanan session (SESSION_DRIVER=database)
- `personal_access_tokens` — Sanctum tokens
- `password_reset_tokens` — reset password
- `cache`, `cache_locks` — cache
- `jobs`, `job_batches`, `failed_jobs` — queue

## Aturan Data
- Hapus Project → cascade Versions → cascade phase_progress.
- `api_key` **tidak pernah** dikembalikan mentah lewat API (mask, mis. `sk-...abcd`).
- Semua query Project/Version **wajib** difilter `user_id` pemilik (kecuali admin bila diputuskan). Lihat [11-development-rules](11-development-rules.md).
- `api_contract` belum diisi oleh pipeline — lihat [16-audit-fix-plan](16-audit-fix-plan.md#rp).

## Seeder Awal
- 1 user admin default (kredensial ditaruh di `.env`, jangan hardcode di repo).
- 1 baris `ai_providers` dengan default OpenAI (base_url=`https://api.openai.com/v1`, model=`gpt-4o`, api_key kosong — diisi admin lewat Settings).
- 3 `templates` seed: "SaaS Dashboard" (web), "E-Commerce" (both), "Mobile CRUD" (mobile).
