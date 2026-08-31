# 51 — Fix ERD/API Contract: Regenerate 500 + Quality Score Mismatch

Sumber analisa: `docs/50` follow-up. Temuan:

1. **Regenerate → "Server Error"**: `PipelineRunner.php:261` — "(string) array" pada kolom JSONB (erd/api_contract) di guard idempotensi (dari fix #48/E1). PHP 500 → SSE mati → client retry 3× → pesan "Koneksi SSE terputus".
2. **Skor ERD/API Contract selalu ≤0.6 (praktis 0.4)**: `computeStageQuality()` adalah rubric markdown (0.4 untuk ≥4 heading markdown). Stage JSON tak pernah penuhi → plafon struktural rendah, bukan kualitas output buruk.

## Checklist

- [x] J1. Guard idempotensi: nilai array → `json_encode` (bukan cast string) + test regression
- [x] J2. Rubric JSON untuk stage `erd`, `api_contract`, `app_spec_web`, `app_spec_mobile`: skor dari coverage struktur (jumlah node/edge/endpoint, kelengkapan field) + non-generic; markdown rubric hanya untuk stage markdown
- [x] J3. Command `pipeline:recompute-quality` — hitung ulang skor versi eksisting
- [x] J4. Verifikasi penuh: `php artisan test`, pint, restart container, commit/push
