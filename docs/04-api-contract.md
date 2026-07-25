# 04 — API Contract

> Lihat juga: [03-database-schema](03-database-schema.md) · [06-ai-pipeline](06-ai-pipeline.md) · [08-frontend](08-frontend.md)
> Base URL (via nginx, same-origin): `/api`. Auth: **Bearer Token (Sanctum PersonalAccessTokens)** — tidak ada CSRF/cookies.

## Konvensi
- Semua response JSON. Error: `{ "message": "...", "errors": {...}? }` dengan HTTP status sesuai.
- Endpoint terproteksi pakai middleware `auth:sanctum` (Bearer token). Endpoint admin tambah middleware `role.admin`.
- Body request `application/json` kecuali disebut lain.

## Auth
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| POST | `/api/register` | — | `{name,email,password}` | `201` `{token, user}` |
| POST | `/api/login` | — | `{email,password}` | `200` `{token, user}` |
| POST | `/api/logout` | Bearer | — | `204` |
| GET | `/api/user` | Bearer | — | user + role |

## Projects
| Method | Path | Auth | Body / Query | Response |
|--------|------|------|--------------|----------|
| GET | `/api/projects` | Bearer | — | list project milik user |
| POST | `/api/projects` | Bearer | `{title, idea, target, stack?}` | project baru |
| GET | `/api/projects/{id}` | Bearer (owner) | — | project + daftar versi |
| DELETE | `/api/projects/{id}` | Bearer (owner) | — | `204` |

### Versions
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| POST | `/api/projects/{id}/versions` | Bearer (owner) | — | buat versi baru (version_no+1), status awal |
| GET | `/api/versions/{id}` | Bearer (owner) | — | artefak lengkap versi |
| PATCH | `/api/versions/{id}/phases/{phaseKey}` | Bearer (owner) | `{done:bool}` | toggle checklist |
| GET | `/api/versions/{id}/export` | Bearer (owner) | `?format=md\|zip` | file unduhan |

### Pipeline (SSE — streaming realtime)
| Method | Path | Auth | Query | Response |
|--------|------|------|-------|----------|
| GET | `/api/generate/stream` | Bearer (owner) | `version={id}&stage={key}&auto={0\|1}` | `text/event-stream` |

**Format event SSE** (satu event per baris `data:`):
```
event: status
data: {"stage":"analisa","state":"running"}

event: token
data: {"stage":"analisa","delta":"teks..."}

event: artifact
data: {"stage":"analisa","content":"...final..."}

event: done
data: {"stage":"analisa"}

event: error
data: {"stage":"analisa","message":"..."}
```
- `stage` ∈ `analisa|prd|architecture|erd|phases|master` (lihat [05-wizard-flow](05-wizard-flow.md)).
- `auto=1` → jalankan seluruh stage berurutan tanpa henti; `auto=0` → hanya stage diminta lalu berhenti (checkpoint).
- Artefak disimpan ke `versions` oleh backend saat `artifact`/`done`.

### Settings — Admin (`role.admin`)
| Method | Path | Body | Response |
|--------|------|------|----------|
| GET | `/api/settings/provider` | — | `{base_url, model, api_key_masked}` |
| PUT | `/api/settings/provider` | `{base_url, api_key?, model}` | updated (api_key ditulis bila dikirim) |
| POST | `/api/settings/provider/test` | — | hasil test koneksi provider |
| GET | `/api/settings/users` | — | list user |
| POST | `/api/settings/users` | `{name,email,password,role}` | user baru |
| PATCH | `/api/settings/users/{id}` | `{role?, name?}` | updated |
| DELETE | `/api/settings/users/{id}` | — | `204` |

### Templates
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/templates` | Bearer | list template |
| POST | `/api/templates` | Bearer + admin | buat |
| DELETE | `/api/templates/{id}` | Bearer + admin | hapus |

### Aturan Keamanan API
- `api_key` provider **tak pernah** dikirim mentah (selalu masked pada GET).
- Semua resource Project/Version dicek kepemilikan (`user_id`) sebelum akses.
- Token expiry: 120 menit (Sanctum `expires_at`).
- Tidak ada CSRF/cookies — Bearer token via `Authorization` header.
