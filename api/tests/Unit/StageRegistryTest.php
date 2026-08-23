<?php

namespace Tests\Unit;

use App\Models\Version;
use App\Services\StageRegistry;
use PHPUnit\Framework\TestCase;

class StageRegistryTest extends TestCase
{
    public function test_registry_matches_version_all_stages(): void
    {
        $this->assertSame(StageRegistry::keys(), Version::ALL_STAGES);
    }

    public function test_counts(): void
    {
        $this->assertCount(26, StageRegistry::keys());
        $this->assertCount(20, StageRegistry::keysForTarget('web'));
        $this->assertSame(StageRegistry::keys(), StageRegistry::keysForTarget('both'));
    }

    public function test_web_target_excludes_mobile_stages(): void
    {
        foreach (StageRegistry::keysForTarget('web') as $key) {
            $this->assertStringNotContainsString('mobile', $key);
        }
    }

    public function test_default_stage_status_covers_registry_order(): void
    {
        $this->assertSame(
            StageRegistry::keys(),
            array_keys(Version::defaultStageStatus()),
        );
    }

    public function test_no_duplicate_keys(): void
    {
        $this->assertCount(count(StageRegistry::KEYS), array_unique(StageRegistry::KEYS));
    }
}
