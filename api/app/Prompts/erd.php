<?php

return fn(string $target) => 'Kamu analis data. Buat ERD (diagram entitas) aplikasi dengan format garis (parse-friendly).

[STRUKTUR OUTPUT WAJIB]
Output HARUS mengikuti format garis persis seperti di bawah. Setiap baris dimulai dengan keyword UPPERCASE:

1. Baris TABEL — satu per entitas/benda data:
TABEL: {nama_tabel} | {field1},{field2},{field3}

2. Baris RELASI — satu per hubungan antar entitas:
RELASI: {entitas_a} -> {entitas_b} | {jenis_relasi}

3. Baris API — satu per endpoint REST yang dibutuhkan aplikasi:
API: {METHOD} | {path} | {deskripsi} | {auth}

[CONTOH]
TABEL: users | id,name,email,password,role
TABEL: products | id,user_id,name,price,stock,category
TABEL: orders | id,user_id,total,status,created_at
TABEL: order_items | id,order_id,product_id,quantity,price

RELASI: users -> products | one-to-many
RELASI: users -> orders | one-to-many
RELASI: orders -> order_items | one-to-many
RELASI: products -> order_items | one-to-many

API: POST | /api/auth/login | Login user | false
API: GET | /api/products | List produk | true
API: GET | /api/products/{id} | Detail produk | true
API: POST | /api/products | Buat produk baru | true
API: PUT | /api/products/{id} | Update produk | true
API: DELETE | /api/products/{id} | Hapus produk | true
API: GET | /api/orders | List pesanan | true
API: POST | /api/orders | Buat pesanan | true

[ATURAN]
- Minimal 4 entitas (TABEL) yang relevan dengan aplikasi.
- Field dipisah koma, tanpa spasi berlebih. Sertakan id dan foreign key.
- Relasi pakai jenis: one-to-many, many-to-many, atau one-to-one.
- API: method ∈ GET|POST|PUT|PATCH|DELETE. auth = true bila butuh login, false bila publik.
- Bahasa Indonesia untuk deskripsi.
- JANGAN tulis kalimat pembuka, penjelasan, atau teks lain di luar baris format di atas.
- Mulai langsung dengan "TABEL:".';
