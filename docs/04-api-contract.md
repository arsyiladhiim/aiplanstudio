# 04 — API Contract

> Lihat juga: [03-database-schema](03-database-schema.md) · [06-ai-pipeline](06-ai-pipeline.md) · [08-frontend](08-frontend.md) · [AUTH.md](../AUTH.md)
> Base URL (via nginx, same-origin): `/api`. Auth: **Sanctum SPA Session (HttpOnly cookie + CSRF)** — bukan Bearer token.

## Konvensi
- Semua response JSON. Error: `{ "message": "...", "errors": {...}? }` dengan HTTP status sesuai.
- Endpoint terproteksi pakai middleware `auth:sanctum` (session cookie). Endpoint admin tambah middleware `role.admin`.
- Body request `application/json` kecuali disebut lain.
- Semua non-GET request membutuhkan header `X-XSRF-TOKEN` (diambil dari cookie `XSRF-TOKEN`).
- `XSRF-TOKEN` cookie tidak HttpOnly — bisa dibaca JavaScript untuk dikirim sebagai header.

## Auth
| Method | Path | Auth | Throttle | Body | Response |
|--------|------|------|----------|------|----------|
| GET | `/api/sanctum/csrf-cookie` | — | — | — | set `XSRF-TOKEN` cookie |
| POST | `/api/register` | — | 5/menit | `{name,email,password,password_confirmation}` | `201` `{user, pending?}` |
| POST | `/api/login` | — | 5/menit | `{email,password,remember?}` | `200` `{user}` |
| POST | `/api/logout` | Session | — | — | `204` |
| GET | `/api/user` | Session | — | — | user + role + status |

> **Catatan:** Auth menggunakan **Sanctum SPA session** — Laravel mengirim HttpOnly session cookie. User non-pertama dibuat dengan `status: pending` dan tidak bisa login sebelum di-approve admin. Tidak ada Bearer token, tidak ada token di JavaScript.

### Google OAuth
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/auth/google/redirect` | — | redirect ke Google |
| GET | `/api/auth/google/callback` | — | login + redirect ke `/dashboard` |

### Password Reset
| Method | Path | Auth | Throttle | Body | Response |
|--------|------|------|----------|------|----------|
| POST | `/api/forgot-password` | — | 5/menit | `{email}` | `200` |
| POST | `/api/reset-password` | — | — | `{token,email,password,password_confirmation}` | `200` |

## Dashboard
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/dashboard/stats` | Session | `{total_projects, total_versions, active_projects, projects_this_week, versions_this_week, recent_projects, favorite_projects, recent_activities[]}` |

## Projects
| Method | Path | Auth | Body / Query | Response |
|--------|------|------|--------------|----------|
| GET | `/api/projects` | Session | `?q=&favorite=true` | list project (search title/idea, filter favorit) |
| POST | `/api/projects` | Session | `{title, idea, target, stack?}` | project baru + auto-create version 1 |
| GET | `/api/projects/{id}` | Session (owner) | — | project + daftar versi (latest 50) |
| PATCH | `/api/projects/{id}` | Session (owner) | `{title?, idea?, target?}` | update project |
| DELETE | `/api/projects/{id}` | Session (owner) | — | `204` |
| PATCH | `/api/projects/{id}/favorite` | Session (owner) | — | toggle `is_favorite` |

### Activities
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/projects/{id}/activities` | Session (owner) | `{data: [{id, action, description, metadata, created_at, user: {name}}], meta}` |
| GET | `/api/activities` | Session + admin | semua activity (global, paginated) |

### Project API Tokens (Webhooks)
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| GET | `/api/projects/{id}/tokens` | Session (owner) | — | list `{id,name,last_used_at,expires_at,created_at}` |
| POST | `/api/projects/{id}/tokens` | Session (owner) | `{name}` | `201` `{token,id,name}` — token ditampilkan **sekali** |
| DELETE | `/api/projects/{id}/tokens/{tokenId}` | Session (owner) | — | `204` |

## Versions
| Method | Path | Auth | Body / Query | Response |
|--------|------|------|--------------|----------|
| POST | `/api/projects/{id}/versions` | Session (owner) | `{strategy?: "from_last"\|"blank", baseline_notes?: string}` | buat versi baru (version_no+1). Default `from_last` = clone baseline artefak+jawaban+status dari versi terakhir; `blank` = start kosong |
| GET | `/api/versions/{id}` | Session (owner) | — | artefak lengkap versi (all columns) |
| DELETE | `/api/versions/{id}` | Session (owner) | — | `204` (tidak bisa hapus versi terakhir) |
| PATCH | `/api/versions/{id}/artifacts` | Session (owner) | `{stage, content}` | inline edit artifact |
| PATCH | `/api/versions/{id}/answers` | Session (owner) | `{answers: {key:value}}` | update jawaban pertanyaan |
| GET | `/api/versions/{id}/diff` | Session (owner) | `?compare={otherId}` | structured diff semua artifact fields (incl. mobile: phases, standards, agents, master_prompt) |
| PATCH | `/api/versions/{id}/phases/{phaseKey}` | Session (owner) | `{done:bool}` | toggle phase progress checklist |
| GET | `/api/versions/{id}/export` | Session (owner) | `?format=md\|zip` | file unduhan — md menyertakan analisa, PRD, arsitektur, ERD, **API Contract**, **clarifikasi mobile**, phases, master prompt, mobile artifacts; zip = `{project}-v{n}.md` + `erd.json` + `mobile-standards.md` + `mobile-agents.md` (jika ada) |

### Standards & Agents Download
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/versions/{id}/standards` | Session (owner) | download standards (.txt) |
| GET | `/api/versions/{id}/agents` | Session (owner) | download agents (.txt) |
| POST | `/api/versions/{id}/regenerate-standards` | Session (owner) | regenerate standards + agents via AI |
| GET | `/api/versions/{id}/standards/mobile` | Session (owner) | download mobile standards |
| GET | `/api/versions/{id}/agents/mobile` | Session (owner) | download mobile agents |
| POST | `/api/versions/{id}/regenerate-standards/mobile` | Session (owner) | regenerate mobile standards + agents |

## Pipeline (SSE — streaming realtime)

> Frontend konsumsi via BFF route: `POST /api/generate/stream` (BFF proxy ke Laravel GET). Auth via cookies.

| Method | Path | Auth | Query | Response |
|--------|------|------|-------|----------|
| GET | `/api/generate/stream` | Session (owner) | `version={id}&stage={key}&auto={0\|1}` | `text/event-stream` |

**Format event SSE** (satu event per baris `data:`):
```
event: status
data: {"stage":"pertanyaan","state":"running"}

event: token
data: {"stage":"pertanyaan","delta":"teks..."}

event: artifact
data: {"stage":"pertanyaan","content":"...final..."}

event: done
data: {"stage":"pertanyaan"}

event: fail
data: {"stage":"pertanyaan","message":"..."}
```

- `stage` ∈ `pertanyaan|analisa|prd|architecture|erd|api_contract|phases_web|standards_web|master_web|pertanyaan_mobile|phases_mobile|standards_mobile|master_mobile|agents` (lihat [05-wizard-flow](05-wizard-flow.md)).
- `auto=1` → jalankan seluruh stage berurutan tanpa henti (setara resume auto-start); `auto=0` → hanya stage diminta lalu berhenti (per-stage manual — perilaku default wizard).
- Mobile track stages (`pertanyaan_mobile`, `phases_mobile`, `standards_mobile`, `master_mobile`) hanya aktif jika project target=`both`, dan menunggu `master_web` done (gate). Stage `agents` di akhir.
- Artefak disimpan ke `versions` oleh backend saat `artifact`/`done`.

## Webhook (Project API Token)
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| POST | `/api/webhooks/phase-complete` | Project Token (header) | `{project_id, version_id, phase_key, status, output}` | `200` `{ok:true, phase_key, status}` |

Auth via `Authorization: Bearer {token}` header, di mana token = project API token (bukan session). Middleware `auth.project-token`.

## Settings — Admin (`role.admin`)
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
| POST | `/api/settings/users` | `{name,email,password?,role}` | user baru |
| PATCH | `/api/settings/users/{id}` | `{role?, name?, status?}` | updated |
| DELETE | `/api/settings/users/{id}` | — | `204` |

## Profile
| Method | Path | Auth | Body | Response |
|--------|------|------|------|----------|
| GET | `/api/settings/profile` | Session | — | user data |
| PATCH | `/api/settings/profile` | Session | `{name?, email?}` | updated |

## Templates
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/templates` | Session | list template |
| POST | `/api/templates` | Session + admin | buat |
| DELETE | `/api/templates/{id}` | Session + admin | hapus |

## Health
| Method | Path | Auth | Response |
|--------|------|------|----------|
| GET | `/api/health` | — | `{"status":"ok"}` |

## Aturan Keamanan API
- `api_key` provider **tak pernah** dikirim mentah (selalu masked pada GET).
- Semua resource Project/Version dicek kepemilikan (`user_id`) sebelum akses.
- Auth via **session cookie** (HttpOnly) + CSRF token (`XSRF-TOKEN` cookie → `X-XSRF-TOKEN` header).
- State-changing requests (POST/PUT/PATCH/DELETE) membutuhkan CSRF token.
- Endpoint register/login/forgot-password di-throttle 5 request per menit.
- Project API token webhook auth alternative untuk integrasi eksternal.
