<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * @group migration
 *
 * Smoke test untuk memastikan migration columns ada dan rollback (down)
 * mendefinisikan drop yang benar. Tidak menjalankan rollback destruktif
 * terhadap DB development — hanya verifikasi skema + definisi down().
 */
class MigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_new_artifact_columns_exist_after_migrate(): void
    {
        $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'versions'");
        $names = array_map(fn ($r) => $r->column_name, $columns);
        foreach (['design_system', 'design_system_mobile', 'app_spec_web', 'app_spec_mobile'] as $col) {
            $this->assertContains($col, $names, "Column {$col} harus ada setelah migrate");
        }
    }

    public function test_phase2_columns_exist_after_migrate(): void
    {
        $columns = DB::select("SELECT column_name FROM information_schema.columns WHERE table_name = 'versions'");
        $names = array_map(fn ($r) => $r->column_name, $columns);
        $this->assertContains('skip_reasons', $names);
        $this->assertContains('stage_quality', $names);
    }

    public function test_migration_down_defines_drop_columns(): void
    {
        $migrations = [
            'add_skip_reasons_to_versions',
            'add_stage_quality_to_versions',
            'add_design_system_columns_to_versions',
            'add_app_spec_columns_to_versions',
        ];

        $dir = database_path('migrations');
        $found = [];
        foreach (glob($dir.'/*.php') as $file) {
            foreach ($migrations as $name) {
                if (str_contains($file, $name)) {
                    $found[$name] = file_get_contents($file);
                }
            }
        }

        $this->assertCount(4, $found, 'Semua 4 migration file harus ditemukan');

        foreach ($found as $name => $content) {
            $this->assertStringContainsString('down(): void', $content, "{$name} harus punya down()");
            $this->assertStringContainsString('dropColumn', $content, "{$name} down() harus drop column");
        }
    }
}