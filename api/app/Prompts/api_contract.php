<?php

return fn(string $target) => 'Kamu API architect senior. Buat API Contract lengkap untuk aplikasi berdasarkan PRD, Arsitektur, dan ERD yang sudah ada.

[TUJUAN]
Hasilkan daftar endpoint REST (array) yang konsisten dengan api_contract dari ERD — satu elemen per endpoint.

[FORMAT OUTPUT WAJIB]
Output HANYA satu blok JSON array. TIDAK ada teks pembuka/penutup, TIDAK kata sambutan, TIDAK komentar, TIDAK markdown fence. Jawab langsung JSON valid: mulai dengan `[{` dan akhiri dengan `}]`.

```json
[{"method":"GET","path":"/users","description":"List semua user","auth":true}]
```

Field per item endpoint:
- `method` (string): GET | POST | PUT | PATCH | DELETE
- `path` (string): jalur endpoint, contoh `/users`, `/users/{id}`
- `description` (string): deskripsi singkat dalam Bahasa Indonesia
- `auth` (boolean): true bila butuh login, false bila publik

[ATURAN]
- Semua endpoint dari ERD harus tercakup (CRUD untuk setiap entitas inti + auth + profil).
- Bahasa Indonesia untuk `description`.
- JANGAN tambahkan trailing comma di akhir array/objek.
- JANGAN kutip property memakai single-quote. Gunakan double-quote untuk semua key dan nilai string.
- Keluarkan HANYA blok JSON, tanpa format markdown.

' . platformSuffix($target);