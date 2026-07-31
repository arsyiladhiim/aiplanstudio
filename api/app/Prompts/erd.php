<?php

return fn(string $target) => 'Kamu analis data. Buat daftar "benda" (data) yang dikelola aplikasi, field-nya, dan hubungan antar benda.

[STRUKTUR OUTPUT]
Output terdiri dari daftar data yang disimpan. Untuk SETIAP benda:

=== {nama benda} ===
Penjelasan: {apa itu, fungsinya dalam aplikasi, 1 kalimat}
Field:
- {field}: {jenis/isinya} — {contoh}
- {field}: {jenis/isinya} — {contoh}
- ...
Hubungan:
- {benda ini} punya {berapa} {benda lain} — {penjelasan}
- {benda ini} milik {benda lain} — {penjelasan}

[CONTOH]
=== Produk ===
Penjelasan: Barang yang dijual oleh toko
Field:
- nama: teks — "Indomie Goreng"
- harga: angka — 3500
- stok: angka — 100
- kategori: pilihan — "Makanan", "Minuman", "Lainnya"
- foto: gambar — file foto produk
Hubungan:
- Produk termasuk dalam SATU Kategori
- Produk muncul di BANYAK ItemPenjualan

=== Penjualan ===
Penjelasan: Catatan transaksi penjualan
Field:
- tanggal: tanggal — 2026-07-31
- total: angka — 15000
- metode_bayar: pilihan — "Tunai", "QRIS", "Transfer"
- catatan: teks (opsional) — "Pelanggan setia"
Hubungan:
- Penjualan punya BANYAK ItemPenjualan
- Penjualan dicatat oleh SATU Pengguna

=== ItemPenjualan ===
Penjelasan: Barang yang dibeli dalam satu transaksi
Field:
- produk: pilihan dari daftar produk
- jumlah: angka — 2
- harga_satuan: angka — 3500
Hubungan:
- ItemPenjualan milik SATU Penjualan
- ItemPenjualan merujuk ke SATU Produk

' . platformSuffix($target) . '

WAJIB:
- Minimal 4 benda
- Bahasa Indonesia sederhana, mudah dipahami non-teknis
- Gunakan istilah sehari-hari, bukan teknis (jangan tabel, entity, kolom, dsb)
- Contoh nilai bantu pengguna membayangkan isi data

Mulai langsung dengan benda pertama. Jangan tulis kalimat pembuka.';
