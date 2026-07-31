# 04 — API Contract

> Lihat juga: [03-database-schema](03-database-schema.md) · [06-ai-pipeline](06-ai-pipeline.md) · [08-frontend](08-frontend.md)
> Base URL (via nginx, same-origin): `/api`. Auth: **Sanctum SPA Session (HttpOnly cookie + CSRF)** — bukan Bearer token.

## Konvensi
- Semua response JSON. Error: `{ "message": "...", "errors": {...}? }` dengan HTTP status sesuai.
- Endpoint terproteksi pakai middleware `auth:sanctum` (session cookie). Endpoint admin tambah middleware `role.admin`.
- Body request `application/json` kecuali disebut lain.
- Semua non-GET request membutuhkan header `X-XSRF-TOKEN` (diambil dari cookie `XSRF-TOKEN`).

## Auth
| Method | Path | Auth | Throttle | Body | Response |
|--------|------|------|----------|------|----------|
| POST | `/api/register` | — | 5/menit | `{name,email,password}` | `201` `{user}` |
| POST | `/api/login` | — | 5/menit | `{email,password}` | `200` `{user}` |
| POST | `/api/logout` | Session | — | — | `204` |
| GET | `/api/user` | Session | — | — | user + role |

> **Catatan:** Auth menggunakan **Sanctum SPA session** — Laravel mengirim HttpOnly session cookie. Frontend mengirim `X-XSRF-TOKEN` header untuk non-GET requests. Tidak ada Bearer token, tidak ada token di JavaScript.

## Dashboard
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/dashboard/stats` | Session | `{total_projects, total_versions, active_projects, projects_this_week, versions_this_week, recent_projects, favorite_projects, recent_activities[]}` |

## Projects
| Method | Path | Auth | Body / Query | Response |
|--------|------|------|--------------|----------|
| GET | `/api/projects` | Session | `?q=&favorite=true` | list project (search title/idea, filter favorit) |
| POST | `/api/projects` | Session | `{title, idea, target, stack?}` | project baru |
| GET | `/api/projects/{id}` | Session (owner) | — | project + daftar versi |
| PATCH | `/api/projects/{id}` | Session (owner) | `{title?, idea?, target?}` | update project |
| DELETE | `/api/projects/{id}` | Session (owner) | — | `204` |
| PATCH | `/api/projects/{id}/favorite` | Session (owner) | — | toggle `is_favorite` |

### Activities
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/projects/{id}/activities` | Session (owner) | `{data: [{id, type, description, metadata, created_at, user: {name}}], meta: {current_page, last_page}}` |

### Versions
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| POST | `/api/projects/{id}/versions` | Session (owner) | — | buat versi baru (version_no+1), status awal |
| GET | `/api/versions/{id}` | Session (owner) | — | artefak lengkap versi |
| PATCH | `/api/versions/{id}/phases/{phaseKey}` | Session (owner) | `{done:bool}` | toggle checklist |
| GET | `/api/versions/{id}/export` | Session (owner) | `?format=md\|zip` | file unduhan |

### Pipeline (SSE — streaming realtime)
| Method | Path | Auth | Query | Response |
|--------|------|------|-------|----------|
| GET | `/api/generate/stream` | Session (owner) | `version={id}&stage={key}&auto={0\|1}` | `text/event-stream` |

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
| GET | `/api/settings/provider` | — | array of `{id,name,base_url,model,provider_type,is_active,api_key_masked,last_test_response,last_test_at}` |
| POST | `/api/settings/provider` | `{name,base_url,api_key,model,provider_type}` | provider baru |
| PATCH | `/api/settings/provider/{id}` | `{name?,base_url?,api_key?,model?,provider_type?}` | updated |
| DELETE | `/api/settings/provider/{id}` | — | `204` |
| POST | `/api/settings/provider/{id}/set-active` | — | set sebagai active provider |
| POST | `/api/settings/provider/{id}/test` | — | hasil test koneksi |
| POST | `/api/settings/provider/{id}/test-prompt` | `{prompt?}` | hasil test dengan prompt kustom |
| GET | `/api/settings/users` | — | list user |
| POST | `/api/settings/users` | `{name,email,password,role}` | user baru |
| PATCH | `/api/settings/users/{id}` | `{role?, name?}` | updated |
| DELETE | `/api/settings/users/{id}` | — | `204` |

### Templates
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/templates` | Session | list template |
| POST | `/api/templates` | Session + admin | buat |
| DELETE | `/api/templates/{id}` | Session + admin | hapus |

### Aturan Keamanan API
- `api_key` provider **tak pernah** dikirim mentah (selalu masked pada GET).
- Semua resource Project/Version dicek kepemilikan (`user_id`) sebelum akses.
- Auth via **session cookie** (HttpOnly) + CSRF token (`XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header).
- State-changing requests (POST/PUT/PATCH/DELETE) membutuhkan CSRF token.
- Endpoint register/login di-throttle 5 request per menit.
