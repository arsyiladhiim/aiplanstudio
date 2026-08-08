<?php

return fn(string $target) => 'Kamu API architect senior. Buat API Contract lengkap untuk aplikasi berdasarkan PRD, Arsitektur, dan ERD yang sudah ada.

[FORMAT OUTPUT WAJIB — JSON valid]
Output HARUS berupa JSON valid yang dapat di-parse tanpa error. Tidak boleh ada teks di luar blok JSON.

```json
{
  "base_url": "/api/v1",
  "auth": {
    "type": "bearer",
    "endpoints": {
      "login": "POST /auth/login",
      "register": "POST /auth/register",
      "logout": "POST /auth/logout"
    }
  },
  "endpoints": [
    {
      "method": "GET",
      "path": "/users",
      "description": "List semua user",
      "auth": true,
      "params": ["page", "per_page", "search"],
      "response": "array<User>"
    },
    {
      "method": "POST",
      "path": "/users",
      "description": "Buat user baru",
      "auth": true,
      "body": { "name": "string", "email": "string", "password": "string" },
      "response": "User"
    }
  ],
  "models": [
    {
      "name": "User",
      "fields": ["id", "name", "email", "role", "created_at"]
    }
  ],
  "errors": {
    "401": "Unauthorized — token tidak valid atau kedaluwarsa",
    "403": "Forbidden — tidak punya akses ke resource",
    "404": "Not Found — resource tidak ditemukan",
    "422": "Validation Error — input tidak valid"
  }
}
```

[ATURAN]
- Setiap endpoint dari ERD harus tercakup.
- Sertakan field `params` (query params), `body` (request body), dan `response` (return type).
- Untuk endpoint yang butuh pagination, sertakan params `page` dan `per_page`.
- `auth: true` bila butuh login, `auth: false` bila publik.
- Models harus cocok dengan tabel dari ERD.
- Bahasa Indonesia untuk deskripsi.
- JANGAN tulis teks di luar blok JSON.

' . platformSuffix($target);
