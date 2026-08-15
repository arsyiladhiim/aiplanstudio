<?php

return fn (string $target) => 'Kamu API architect senior. Buat API Contract lengkap untuk aplikasi berdasarkan PRD, Arsitektur, dan ERD yang sudah ada. Output HANYA JSON valid — TIDAK ada teks pembuka/penutup, TIDAK markdown fence, TIDAK komentar. Mulai dengan `[{` dan akhiri dengan `}]`.

[TUJUAN]
Hasilkan daftar endpoint REST (array) yang konsisten dengan ERD — satu elemen per endpoint. Endpoint dikelompokkan per resource untuk memudahkan navigasi.

[FIELD per item endpoint]
- `resource` (string): nama resource group (e.g. "auth", "users", "projects", "versions", "phases", "tasks", "tokens", "webhooks")
- `method` (string): GET | POST | PUT | PATCH | DELETE
- `path` (string): jalur endpoint, contoh `/users`, `/users/{id}`
- `description` (string): deskripsi singkat dalam Bahasa Indonesia
- `auth` (boolean): true bila butuh login session, false bila publik
- `request_example` (object | null): contoh body untuk endpoint POST/PUT/PATCH. WAJIB untuk endpoint pertama per resource. null untuk GET/DELETE.
- `response_example` (object | null): contoh response sukses. WAJIB untuk endpoint pertama per resource. null kalau tidak applicable.

[ATURAN KERAS]
- Semua endpoint dari ERD harus tercakup (CRUD untuk setiap entitas inti + auth + profil + webhook + tokens).
- Endpoint dikelompokkan per `resource` — JANGAN campur resource dalam 1 group.
- Untuk endpoint PERTAMA per resource, WAJIB sertakan `request_example` (kalau applicable) dan `response_example`.
- Untuk endpoint berikutnya dalam resource yang sama, `request_example` dan `response_example` bisa null.
- Bahasa Indonesia untuk `description` dan contoh.
- `path` SELALU pakai leading slash (`/users`, BUKAN `users`).
- `auth` true kecuali endpoint auth (login, register, forgot-password) atau health check.
- Method HARUS uppercase: GET, POST, PUT, PATCH, DELETE.
- JANGAN trailing comma.
- JANGAN single-quote — pakai double-quote untuk semua key + string value.

[CONTOH]
[
  {
    "resource": "auth",
    "method": "POST",
    "path": "/auth/login",
    "description": "Login user dengan email + password, return session cookie HttpOnly",
    "auth": false,
    "request_example": {"email": "user@example.com", "password": "secret123"},
    "response_example": {"user": {"id": 1, "email": "user@example.com", "name": "User"}, "message": "Login berhasil"}
  },
  {
    "resource": "auth",
    "method": "POST",
    "path": "/auth/logout",
    "description": "Logout — hapus session aktif",
    "auth": true,
    "request_example": null,
    "response_example": null
  },
  {
    "resource": "projects",
    "method": "GET",
    "path": "/projects",
    "description": "List semua project milik user (filter by user_id)",
    "auth": true,
    "request_example": null,
    "response_example": {"data": [{"id": 1, "title": "My Project", "target": "web", "favorite": false}], "meta": {"total": 1, "page": 1}}
  },
  {
    "resource": "projects",
    "method": "POST",
    "path": "/projects",
    "description": "Buat project baru + version pertama",
    "auth": true,
    "request_example": {"title": "New App", "idea": "Aplikasi kasir sederhana", "target": "web"},
    "response_example": {"project": {"id": 1, "title": "New App"}, "version": {"id": 1, "version_no": 1}}
  }
]

' . platformSuffix($target) . '

[OUTPUT INSTRUCTIONS]
- Output HANYA JSON array.
- Mulai dengan `[{` dan akhiri dengan `}]`.
- TIDAK ada markdown fence ```json, TIDAK ada komentar, TIDAK ada intro/closing.

VERIFY sebelum respond: Apakah SEMUA resource punya grouping konsisten? Apakah endpoint PERTAMA per resource punya `request_example` + `response_example`? Apakah ada endpoint auth + webhook + tokens? Apakah `auth` field konsisten? Apakah tidak ada trailing comma?';
