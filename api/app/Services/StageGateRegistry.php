<?php

namespace App\Services;

use App\Models\Version;
use App\Services\Gates\ArchGate;
use App\Services\Gates\DeployGate;
use App\Services\Gates\DiscoveryGate;
use App\Services\Gates\ProductionReadinessGate;
use App\Services\Gates\ReviewGate;
use App\Services\Gates\SecurityGate;
use App\Services\Gates\SmokeTestGate;
use App\Services\Gates\SpecGate;
use App\Services\Gates\StageGate;

/**
 * CP-46.A: registry of quality gates. Single source of truth untuk
 * gate→stage mapping + eksekusi gate check.
 *
 * PipelineRunner memanggil {@see assert()} sebelum emit `running` untuk sebuah stage.
 * Hasil disimpan di Version::gate_states[stage_key] untuk UI consumption.
 */
class StageGateRegistry
{
    /** @var array<int, StageGate> */
    private array $gates;

    public function __construct(?array $gates = null)
    {
        $this->gates = $gates ?? [
            new DiscoveryGate,
            new SpecGate,
            new ArchGate,
            new SecurityGate,
            new DeployGate,
            new ReviewGate,
            new SmokeTestGate,
            new ProductionReadinessGate,
        ];
    }

    /** @return array<int, StageGate> */
    public function gates(): array
    {
        return $this->gates;
    }

    /** @return array<string, string> Map stage_key → gate_name (untuk GET /api/stages). */
    public function gateMap(): array
    {
        $map = [];
        foreach ($this->gates as $g) {
            foreach ($g->appliesTo() as $stageKey) {
                $map[$stageKey] = $g->name();
            }
        }

        return $map;
    }

    /**
     * Cek gate untuk sebuah stage. Return:
     *  - passes: true|false
     *  - gate:   nama gate yang applicable
     *  - reason: pesan singkat
     *
     * @return array{passes:bool, gate:?string, reason:string}
     */
    public function check(Version $v, string $stageKey): array
    {
        foreach ($this->gates as $g) {
            if (in_array($stageKey, $g->appliesTo(), true)) {
                return [
                    'passes' => $g->passes($v, $stageKey),
                    'gate' => $g->name(),
                    'reason' => $g->reason($v, $stageKey),
                ];
            }
        }

        // Tidak ada gate yang applicable — implicit pass.
        return ['passes' => true, 'gate' => null, 'reason' => 'No gate'];
    }

    /**
     * Assert gate + persist hasil ke Version::gate_states.
     * Return array hasil check.
     */
    public function assert(Version $v, string $stageKey): array
    {
        $result = $this->check($v, $stageKey);
        $states = is_array($v->gate_states) ? $v->gate_states : [];
        $states[$stageKey] = [
            'gate' => $result['gate'],
            'passed' => $result['passes'],
            'reason' => $result['reason'],
            'checked_at' => now()->toIso8601String(),
        ];
        $v->gate_states = $states;
        $v->save();

        return $result;
    }
}
