<?php

namespace App\Services;

/**
 * Single source of truth untuk daftar & urutan stage pipeline (CP-44 plan).
 * Version::ALL_STAGES, defaultStageStatus(), dan GET /api/stages derivasi dari sini.
 * Urutan kanonik = urutan eksekusi pipeline; jangan ubah tanpa sinkron test
 * (PipelineRunnerTest, GenerateStreamTest) dan web/src/lib/mock.ts.
 */
class StageRegistry
{
    /**
     * @var array<int, array{key:string, group:string, label:string, mobile:bool}>
     */
    public const STAGES = [
        ['key' => 'pertanyaan', 'group' => 'discovery', 'label' => 'Klarifikasi', 'mobile' => false],
        ['key' => 'analisa', 'group' => 'discovery', 'label' => 'Analisa', 'mobile' => false],
        ['key' => 'prd', 'group' => 'definition', 'label' => 'PRD', 'mobile' => false],
        ['key' => 'architecture', 'group' => 'design', 'label' => 'Arsitektur', 'mobile' => false],
        ['key' => 'erd', 'group' => 'design', 'label' => 'ERD', 'mobile' => false],
        ['key' => 'api_contract', 'group' => 'design', 'label' => 'API Contract', 'mobile' => false],
        ['key' => 'design_system', 'group' => 'design', 'label' => 'Design System', 'mobile' => false],
        ['key' => 'phases_web', 'group' => 'web-build', 'label' => 'Web — Phases', 'mobile' => false],
        ['key' => 'standards_web', 'group' => 'web-build', 'label' => 'Web — Standards', 'mobile' => false],
        ['key' => 'master_web', 'group' => 'web-build', 'label' => 'Web — Master Prompt', 'mobile' => false],
        ['key' => 'app_spec_web', 'group' => 'web-build', 'label' => 'App Spec — Web', 'mobile' => false],
        ['key' => 'design_system_mobile', 'group' => 'mobile-build', 'label' => 'Design System Mobile', 'mobile' => true],
        ['key' => 'pertanyaan_mobile', 'group' => 'mobile-build', 'label' => 'Mobile — Klarifikasi', 'mobile' => true],
        ['key' => 'standards_mobile', 'group' => 'mobile-build', 'label' => 'Mobile — Standards', 'mobile' => true],
        ['key' => 'phases_mobile', 'group' => 'mobile-build', 'label' => 'Mobile — Phases', 'mobile' => true],
        ['key' => 'master_mobile', 'group' => 'mobile-build', 'label' => 'Mobile — Master Prompt', 'mobile' => true],
        ['key' => 'app_spec_mobile', 'group' => 'mobile-build', 'label' => 'App Spec — Mobile', 'mobile' => true],
        ['key' => 'env_config', 'group' => 'launch', 'label' => 'Env & Config', 'mobile' => false],
        ['key' => 'security', 'group' => 'launch', 'label' => 'Security Checklist', 'mobile' => false],
        ['key' => 'deployment', 'group' => 'launch', 'label' => 'Deployment', 'mobile' => false],
        ['key' => 'observability', 'group' => 'launch', 'label' => 'Observability', 'mobile' => false],
        ['key' => 'agents', 'group' => 'launch', 'label' => 'Agents', 'mobile' => false],
    ];

    /** @var array<int, string> */
    public const KEYS = [
        'pertanyaan', 'analisa', 'prd', 'architecture', 'erd', 'api_contract',
        'design_system',
        'phases_web', 'standards_web', 'master_web',
        'app_spec_web',
        'design_system_mobile',
        'pertanyaan_mobile', 'standards_mobile', 'phases_mobile', 'master_mobile',
        'app_spec_mobile',
        'env_config', 'security', 'deployment', 'observability',
        'agents',
    ];

    /** @return array<int, string> */
    public static function keys(): array
    {
        return self::KEYS;
    }

    /** @return array<int, array{key:string, group:string, label:string, mobile:bool}> */
    public static function all(): array
    {
        return self::STAGES;
    }

    /** Stage untuk target project: 'web' meniadakan stage mobile. @return array<int, string> */
    public static function keysForTarget(string $target): array
    {
        if ($target === 'both') {
            return self::KEYS;
        }

        return array_values(array_filter(self::KEYS, fn ($k) => ! str_contains($k, 'mobile')));
    }
}
