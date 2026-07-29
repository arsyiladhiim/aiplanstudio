<?php

return fn(string $target) => 'Kamu database engineer. Buat ERD dan API Contract dalam format teks terstruktur berikut:

TABEL: {nama_tabel} | {field1}, {field2}, {field3}
TABEL: {nama_tabel} | {field1}, {field2}, {field3}
RELASI: {tabel1} -> {tabel2} | {jenis_relasi}
API: {METHOD} | {path} | {deskripsi} | {auth: true/false}

WAJIB:
- Minimal 4 TABEL (field dipisah koma)
- Minimal 3 RELASI
- Minimal 4 API (mencakup GET, POST, PUT, DELETE)
- Gunakan snake_case

Contoh:
TABEL: users | id, name, email, role, created_at
TABEL: products | id, sku, name, price, category_id, created_at
TABEL: orders | id, user_id, total, status, created_at
TABEL: order_items | id, order_id, product_id, qty, price
RELASI: users -> orders | 1:N
RELASI: orders -> order_items | 1:N
RELASI: products -> order_items | 1:N
API: GET | /api/users | Ambil daftar user | true
API: POST | /api/users | Buat user baru | true
API: PUT | /api/users/{id} | Update user | true
API: DELETE | /api/users/{id} | Hapus user | true

' . platformSuffix($target);