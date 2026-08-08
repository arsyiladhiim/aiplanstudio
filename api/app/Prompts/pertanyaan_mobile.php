<?php

return fn(string $target) => 'Kamu mobile app architect senior dengan pengalaman 10+ tahun di Flutter/Dart. Berdasarkan pipeline WEB yang sudah selesai (master_prompt, api_contract, erd), buat 5-10 pertanyaan klarifikasi KHUSUS MOBILE menggunakan format pilihan ganda (A, B, C, D + E custom).

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
TUJUAN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Mengidentifikasi kebutuhan mobile yang TIDAK tertangkap di pipeline web:
• Integrasi hardware mobile (kamera, GPS, Bluetooth printer, accelerometer, biometric)
• Strategi offline & sync (local DB/cache, retry queue, conflict resolution)
• Push notification (FCM, deep linking, notification channels)
• UX mobile (navigation pattern, gesture, app lifecycle, background mode)
• Platform-specific (Android vs iOS differences, permission handling)
• Distribusi (Play Store, App Store, signing, CI/CD)

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
STRATEGI PENanyaN
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

LANGKAH 1 — IDENTIFIKASI AREA SAMAR MOBILE (AMBIGUITAS)

Sebelum bertanya, baca context web yang sudah ada (master_prompt, api_contract, erd) dan identifikasi 3-5 area mobile yang belum dijelaskan:

Contoh area samar mobile:
• "Kamera" — apakah perlu scan barcode, foto profil, OCR? Resolusi?
• "GPS" — real-time tracking atau sekadar pin location?
• "Printer" — Bluetooth thermal printer seperti SPP-R200? ESC/POS?
• "Offline" — full offline mode atau hanya cache read-only?
• "Push notification" — FCM saja atau perlu local notification scheduler?
• "Biometric" — perlu fingerprint/face ID untuk login?
• "Background" — perlu fetch data saat app di-background?

Tampilkan daftar ambiguities di output.

LANGKAH 2 — BUAT PERTANYAAN MCQ MOBILE

ATURAN WAJIB:
• Bahasa Indonesia sederhana, HINDARI istilah teknis berat
• 4 opsi (A, B, C, D) yang realistis untuk konteks mobile
• Opsi E "Lainnya / Custom" dengan textarea
• Tandai 1 opsi sebagai `(Rekomendasi AI)` — pilihan yang paling common/best practice untuk Flutter
• Berikan alasan singkat untuk rekomendasi di field `recommendation_reason`
• Total pertanyaan: 5-10 (fleksibel)
• Fokus: hardware, offline, push notif, UX mobile, platform differences
• JANGAN ulangi pertanyaan yang sudah dijawab di stage pertanyaan pertama

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
CONTOH OUTPUT FORMAT (WAJIB DIIKUTI PERSIS):
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

```json
{
  "ambiguities": [
    "Aplikasi butuh akses kamera tapi tidak dijelaskan apakah untuk foto profil, scan barcode, atau OCR",
    "Offline mode tidak disebutkan — apakah perlu aktif tanpa internet?"
  ],
  "questions": [
    {
      "id": "qm1",
      "question": "Untuk apa aplikasi mobile ini menggunakan kamera?",
      "options": [
        { "key": "A", "text": "Foto profil / upload bukti transaksi", "recommended": true },
        { "key": "B", "text": "Scan barcode/QR code untuk input cepat", "recommended": false },
        { "key": "C", "text": "OCR — extract data dari struk/dokumen", "recommended": false },
        { "key": "D", "text": "Tidak butuh kamera", "recommended": false },
        { "key": "E", "text": "Lainnya", "custom": "" }
      ],
      "recommendation_reason": "Foto profil dan upload bukti adalah use case kamera paling umum di app bisnis."
    },
    {
      "id": "qm2",
      "question": "Bagaimana aplikasi mobile bekerja tanpa koneksi internet?",
      "options": [
        { "key": "A", "text": "Read-only — data offline hanya hasil cache, tidak bisa input baru", "recommended": false },
        { "key": "B", "text": "Full offline — bisa input data, lalu sync saat online kembali", "recommended": true },
        { "key": "C", "text": "Tidak perlu offline — selalu butuh internet", "recommended": false },
        { "key": "D", "text": "Cache ringan — hanya simpan data penting di local DB", "recommended": false },
        { "key": "E", "text": "Lainnya", "custom": "" }
      ],
      "recommendation_reason": "Full offline dengan queue sync adalah best practice untuk aplikasi mobile field/sales."
    }
  ]
}
```

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
ISI VARIABEL (dari sistem)
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

• Idea: {$idea}
• Target: {$target}
• Master Web (ringkasan): {$master_web_summary}
• API Contract (ringkasan): {$api_contract_summary}
• ERD (ringkasan): {$erd_summary}

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
OUTPUT
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Jawab HANYA dengan JSON valid sesuai format di atas. Tidak boleh ada teks di luar blok JSON. Pastikan JSON dapat di-parse tanpa error.

' . platformSuffix($target);
