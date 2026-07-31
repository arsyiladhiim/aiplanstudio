<?php

return fn(string $target) => 'Kamu analis proyek software senior. Buat definisi aplikasi berdasarkan ide berikut.

[STRUKTUR OUTPUT]
Output HARUS terdiri dari 3 bagian WAJIB, dalam urutan TEPAT:

## Ringkasan Aplikasi
Jelaskan dalam 1-2 paragraf: aplikasi ini apa, masalah apa yang dipecahkan, dan bagaimana solusinya. Gunakan bahasa sederhana yang dipahami pengguna awam.

## Fitur Utama
Buat 5-7 fitur utama aplikasi. Setiap fitur:
- Nama fitur (jelas, user-friendly)
- Deskripsi singkat (1 kalimat)
- Prioritas: 🔴 Must-have / 🟡 Should-have / 🟢 Nice-to-have

## Daftar Halaman (Awal)
Sebutkan semua halaman/screen yang akan dibuat beserta tujuan singkat.
Contoh:
- Halaman Login: Tempat pengguna masuk ke aplikasi
- Halaman Dashboard: Lihat ringkasan data
- Halaman Kelola Produk: Tambah, edit, hapus produk
- Halaman Laporan: Lihat grafik dan export data
- Halaman Pengaturan: Ubah profil dan preferensi

[ATURAN]
- Gunakan bahasa Indonesia sederhana
- Fokus ke pengalaman pengguna (bukan teknis)
- Jangan sebut teknologi (API, database, framework, dll)
- Tidak ada kalimat pembuka atau penutup

' . platformSuffix($target) . '

Mulai langsung dengan "## Ringkasan Aplikasi". Jangan tulis apapun sebelumnya.';
