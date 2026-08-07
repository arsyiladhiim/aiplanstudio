# 03 — Database Schema

> Lihat juga: [04-api-contract](04-api-contract.md) · [06-ai-pipeline](06-ai-pipeline.md) · [02-architecture](02-architecture.md)
> DBMS: PostgreSQL. ORM: Eloquent (Laravel migrations). DB lama `aistack` **dihapus & dibuat ulang dari 0**.

## Schema PostgreSQL

Tabel dipisah ke 3 schema berdasarkan domain:

| Schema | Isi |
|--------|-----|
| `aiplanstudio_master` | Master data — `users`, `password_reset_tokens`, `personal_access_tokens`, `templates`, `migrations` |
| `aiplanstudio_project` | Data project — `projects`, `versions`, `phase_progress`, `activities`, `project_api_tokens` |
| `aiplanstudio_settings` | Settings & sistem — `ai_providers`, `sessions`, `cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs` |

Konfigurasi `search_path` di `config/database.php`:
```php
'search_path' => 'aiplanstudio_master, aiplanstudio_project, aiplanstudio_settings, public'
```
Semua model dapat mengakses tabel tanpa prefix schema karena PostgreSQL mencari melalui `search_path`.

## Diagram Relasi (ringkas)
```
users ──1:N── projects ──1:N── versions ──1:N── phase_progress
                    │
                    └──1:N── activities
                    └──1:N── project_api_tokens
projects ──1:N── versions
ai_providers (singleton, global)
templates (standalone)
sessions, personal_access_tokens (Sanctum)
```

### project_api_tokens (Webhooks)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| project_id | bigint FK → projects | |
| name | string | label token |
| token_hash | string(64) unique | SHA-256 hash; token ditampilkan sekali saat pembuatan |
| last_used_at | timestamp nullable | |
| expires_at | timestamp nullable | |
| timestamps | | |

## Tabel

### users
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string | |
| email | string unique | |
| password | string | hash bcrypt |
| role | enum `admin`\|`member` | default `member`; user pertama = `admin` |
| status | string | default `'active'`; `'pending'` untuk user baru (butuh approve admin) |
| email_verified_at | timestamp nullable | bawaan Laravel |
| remember_token | string nullable | bawaan Laravel |
| timestamps | | created_at, updated_at |

**User approval flow:** user pertama otomatis `admin` + `active`. User berikutnya `member` + `pending` (harus di-approve admin sebelum login).

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
| is_favorite | boolean default false | ditandai sebagai favorit |
| timestamps | | |

### versions (snapshot 1 run pipeline)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| project_id | bigint FK → projects | |
| version_no | integer | 1, 2, 3 … ("update ke Versi 2") |
| stage_status | jsonb | status 7 tahap: `{pertanyaan:'pending', analisa:'done', prd:'running', ...}` |

Stage status keys: `pertanyaan`, `analisa`, `prd`, `architecture`, `erd`, `phased_master`, `phased_master_mobile`. Nilai: `pending` | `running` | `done` | `error`.

| pertanyaan | text nullable | output tahap Pertanyaan Klarifikasi (migration 2026_08_06_000000) |
| analysis | text nullable | hasil tahap Analisa |
| prd | text nullable | markdown PRD |
| architecture | text nullable | arsitektur & tech stack |
| erd | jsonb nullable | `{nodes:[], edges:[]}` untuk React Flow; `api_contract` juga disimpan di kolom terpisah |
| api_contract | jsonb nullable | daftar endpoint terstruktur `{method,path,description,auth}`, diekstrak dari output ERD stage |
| phases | jsonb nullable | `[{key,title,tasks,prompt}, ...]` — roadmap fase pembangunan |
| master_prompt | text nullable | prompt utama gabungan (web/mobile tergantung target) |
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

### activities (Activity Log)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| project_id | bigint FK → projects | cascade delete |
| version_id | bigint FK → versions nullable | bisa null (activity tidak terkait versi tertentu) |
| user_id | bigint FK → users nullable | pelaku (null untuk system) |
| action | string | kategorisasi — nilai dibatasi konstanta `Activity::ACTIONS` (lihat tabel di bawah) |
| description | text | narasi aktivitas |
| metadata | jsonb nullable | data tambahan polymorphic |
| timestamps | | |

**Nilai `activities.action`** (sumber: `api/app/Models/Activity.php` — `Activity::ACTIONS`):

| Action | Kapan Dicatat | Call Site |
|--------|---------------|-----------|
| `created_version` | versi baru dibuat (auto v1 saat project dibuat, atau manual) | `VersionController::store()` |
| `deleted_version` | versi dihapus | `VersionController::destroy()` |

> Saat menambah aksi baru: daftarkan konstanta `ACTION_*` + tambahkan ke `Activity::ACTIONS`, lalu panggil via `Project::logActivity(Activity::ACTION_XXX, ...)`.

### versions — kolom tambahan (migrasi lanjutan)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| answers | jsonb nullable | jawaban pertanyaan klarifikasi `{key: value}` (migration 2026_07_31_120000) |
| tracking_token | string nullable | token untuk webhook phase tracking (migration 2026_07_31_120000) |
| standards | text nullable | standar/target quality hasil dari phased_master (migration 2026_07_27_130000) |
| agents | text nullable | daftar agen AI hasil dari phased_master (migration 2026_07_27_130000) |
| mobile_phases | jsonb nullable | phases untuk mobile (target='both' saja) |
| mobile_master_prompt | text nullable | master prompt untuk mobile |
| mobile_standards | text nullable | standards untuk mobile |
| mobile_agents | text nullable | agents untuk mobile |

> **Catatan:** Kolom `mobile_analysis`, `mobile_prd`, `mobile_architecture` **tidak ada** di schema. Output mobile menggunakan `architecture` dan `prd` yang sama; hanya phases dan master prompt yang memiliki versi mobile terpisah.

### phase_progress — kolom tambahan
| Kolom | Tipe | Catatan |
|-------|------|---------|
| output | text nullable | output yang dihasilkan (migration 2026_07_27_120001) |

### Bawaan Framework & Sanctum
- `sessions` — penyimpanan session (SESSION_DRIVER=database)
- `personal_access_tokens` — Sanctum tokens
- `password_reset_tokens` — reset password
- `cache`, `cache_locks` — cache
- `jobs`, `job_batches`, `failed_jobs` — queue

## Aturan Data
- Hapus Project → cascade Versions → cascade phase_progress + activities + project_api_tokens.
- `api_key` **tidak pernah** dikembalikan mentah lewat API (mask, mis. `sk-...abcd`).
- Semua query Project/Version **wajib** difilter `user_id` pemilik (kecuali admin bila diputuskan). Lihat [11-development-rules](11-development-rules.md).
- `api_contract` diekstrak dari response ERD stage via regex parsing (`API: GET | /path | desc | auth`), dengan fallback JSON block (`nodes`/`edges`/`api_contract`) bila output AI bukan format baris — lihat `PipelineRunner::parseErdText()`/`parseJsonErd()`.
- Aktivitas otomatis tercatat via `Project::logActivity()` di controller.

## Seeder Awal
- 1 user admin default (kredensial ditaruh di `.env`, jangan hardcode di repo).
- 1 baris `ai_providers` dengan default OpenAI (base_url=`https://api.openai.com/v1`, model=`gpt-4o`, api_key kosong — diisi admin lewat Settings).
- 3 `templates` seed: "SaaS Dashboard" (web), "E-Commerce" (both), "Mobile CRUD" (mobile).
