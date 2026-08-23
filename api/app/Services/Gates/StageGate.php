<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * Kontrak Quality Gate — setiap gate memutuskan boleh/tidaknya sebuah stage berjalan.
 *
 * Gate BUKAN validator artifact (itu tugas StageArtifactValidator); Gate memutuskan
 * PRASYARAT sebelum stage dijalankan (artifact tersedia, gate sebelumnya passed).
 *
 * Gate result:
 *  - passed(): boleh lanjut, emit 'ready'.
 *  - reason:  alasan blocked (untuk UI diagnostic pack).
 *  - status:  'passed' | 'blocked' untuk disimpan di Version::gate_states[stage_key].
 */
interface StageGate
{
    /** @return array<int, string> Stage keys yang diproteksi gate ini. */
    public function appliesTo(): array;

    /** @return bool True jika gate dipenuhi untuk stage ini. */
    public function passes(Version $v, string $stageKey): bool;

    /** Pesan singkat untuk UI diagnostic pack (kapan gate blocked). */
    public function reason(Version $v, string $stageKey): string;

    /** Nama gate (untuk telemetry + Version::gate_states). */
    public function name(): string;
}
