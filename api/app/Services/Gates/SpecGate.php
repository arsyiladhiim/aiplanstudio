<?php

namespace App\Services\Gates;

use App\Models\Version;

/**
 * SpecGate — gate generik untuk specification-style stages.
 * Prasyarat: artifact pendahulu harus done (sesuai STAGE_DEPENDENTS).
 */
class SpecGate implements StageGate
{
    public function appliesTo(): array
    {
        return [
            'analisa', 'prd',
            'app_spec_web', 'app_spec_mobile',
            'design_system', 'design_system_mobile',
            'standards_web', 'standards_mobile',
            'phases_web', 'phases_mobile',
            'testing_strategy',
        ];
    }

    /**
     * Stage-level deps minimal — SpecGate menerima jika SEMUA prerequisite stage sudah 'done'.
     * Sumber: STAGE_DEPENDENTS di PipelineRunner (existing); kita inverse-nya.
     *
     * @var array<string, array<int, string>>
     */
    private const PREREQ = [
        'analisa' => ['pertanyaan'],
        'prd' => ['analisa'],
        'design_system' => ['prd'],
        'phases_web' => ['design_system', 'api_contract'],
        'standards_web' => ['design_system', 'phases_web'],
        'testing_strategy' => ['standards_web', 'phases_web'],
        'app_spec_web' => ['master_web'],
        'design_system_mobile' => ['app_spec_web', 'design_system'],
        'pertanyaan_mobile' => ['design_system_mobile', 'app_spec_web'],
        'phases_mobile' => ['pertanyaan_mobile'],
        'standards_mobile' => ['design_system_mobile', 'phases_mobile'],
        'app_spec_mobile' => ['master_mobile'],
    ];

    public function passes(Version $v, string $stageKey): bool
    {
        $deps = self::PREREQ[$stageKey] ?? [];
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];

        foreach ($deps as $dep) {
            if (($statuses[$dep] ?? 'pending') !== 'done') {
                return false;
            }
        }

        return true;
    }

    public function reason(Version $v, string $stageKey): string
    {
        $deps = self::PREREQ[$stageKey] ?? [];
        $missing = [];
        $statuses = is_array($v->stage_status) ? $v->stage_status : [];
        foreach ($deps as $dep) {
            if (($statuses[$dep] ?? 'pending') !== 'done') {
                $missing[] = $dep;
            }
        }

        return $missing ? 'Menunggu: '.implode(', ', $missing) : 'SpecGate ok';
    }

    public function name(): string
    {
        return 'SpecGate';
    }
}
