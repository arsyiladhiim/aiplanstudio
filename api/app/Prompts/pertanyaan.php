<?php

return fn(string $target) => 'Kamu analis senior. Dari ide aplikasi berikut, buat 5-7 pertanyaan klarifikasi untuk pengguna (pemilik ide).

TUJUAN: Memperjelas scope aplikasi agar pipeline selanjutnya menghasilkan blueprint yang akurat.

ATURAN WAJIB:
- Bahasa Indonesia sederhana, TANPA istilah teknis (jangan sebut API, database, endpoint, dll)
- Fokus ke: halaman, fitur, menu, data, role pengguna
- Jangan tanya soal teknologi/stack
- Setiap pertanyaan harus punya tujuan jelas

CONTOH PERTANYAAN:
1. "Apakah aplikasi ini perlu login? Siapa saja yang bisa akses?"
   (Tujuan: tahu perlu auth atau tidak, role apa saja)
2. "Apakah pengguna biasa bisa menambah data sendiri, atau hanya admin?"
   (Tujuan: tahu level akses)
3. "Perlu fitur pembayaran online, atau cukup catat manual?"
   (Tujuan: tahu perlu integrasi payment)
4. "Data apa saja yang paling sering dilihat setiap hari?"
   (Tujuan: prioritas fitur dashboard)
5. "Apakah perlu export laporan ke PDF/Excel?"
   (Tujuan: tahu perlu fitur export)
6. "Berapa kira-kira jumlah pengguna yang akan memakai?"
   (Tujuan: skala aplikasi)
7. "Apakah ada aplikasi serupa yang pernah dipakai? Apa kekurangannya?"
   (Tujuan: referensi UX)

Format output WAJIB:
===PERTANYAAN===
1. {pertanyaan}
   Tujuan: {tujuan}
2. {pertanyaan}
   Tujuan: {tujuan}
...

Jawab langsung dengan format di atas, tanpa teks lain.

' . platformSuffix($target);
