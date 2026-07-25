# 03 — Database Schema

> Lihat juga: [04-api-contract](04-api-contract.md) · [06-ai-pipeline](06-ai-pipeline.md) · [02-architecture](02-architecture.md)
> DBMS: PostgreSQL. ORM: Eloquent (Laravel migrations). DB lama `aistack` **dihapus & dibuat ulang dari 0**.

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

### ai_providers (singleton — 1 baris, global)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| base_url | string | endpoint OpenAI-compatible, mis. `https://api.openai.com/v1` |
| api_key | text | **cast `encrypted`** (Laravel APP_KEY) |
| model | string | mis. `gpt-4o`, `deepseek-chat` |
| timestamps | | |

### templates
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| name | string | mis. "SaaS Dashboard" |
| target | enum `web`\|`mobile`\|`both` | |
| description | text | |
| seed | jsonb | nilai awal untuk mengisi wizard |
| timestamps | | |

### projects
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| user_id | bigint FK → users | scoping kepemilikan |
| title | string | |
| idea | text | ide awal |
| target | enum `web`\|`mobile`\|`both` | menentakan output target-aware |
| stack | string nullable | preferensi stack (opsional) |
| timestamps | | |

### versions (snapshot 1 run pipeline)
| Kolom | Tipe | Catatan |
|-------|------|---------|
| id | bigint PK | |
| project_id | bigint FK → projects | |
| version_no | int | 1, 2, 3 … ("update ke Versi 2") |
| stage_status | jsonb | status tiap tahap: `{analisa:'done', prd:'running', ...}` |
| analysis | text nullable | hasil tahap Analisa |
| prd | text nullable | markdown PRD |
| architecture | text nullable | arsitektur & tech stack |
| erd | jsonb nullable | `{nodes:[], edges:[]}` untuk React Flow |
| api_contract | jsonb nullable | daftar endpoint terstruktur |
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

### Bawaan Sanctum
- `sessions` (jika SESSION_DRIVER=database) dan/atau `personal_access_tokens`. Lihat [04-api-contract](04-api-contract.md) untuk mode auth.

## Aturan Data
- Hapus Project → cascade Versions → cascade phase_progress.
- `api_key` **tidak pernah** dikembalikan mentah lewat API (mask, mis. `sk-...abcd`).
- Semua query Project/Version **wajib** difilter `user_id` pemilik (kecuali admin bila diputuskan). Lihat [11-development-rules](11-development-rules.md).

## Seeder Awal
- 1 user admin default (kredensial ditaruh di `.env`, jangan hardcode di repo).
- 1 baris `ai_providers` kosong (diisi admin lewat Settings).
- Beberapa `templates` seed minimal (SaaS, E-commerce, Mobile CRUD).
