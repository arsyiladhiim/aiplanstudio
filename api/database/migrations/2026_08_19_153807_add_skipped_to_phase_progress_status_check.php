<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    private function dropConstraint(): void
    {
        foreach (['phase_progress_status_check', 'aiplanstudio_project_phase_progress_status_check'] as $name) {
            DB::statement("ALTER TABLE phase_progress DROP CONSTRAINT IF EXISTS \"{$name}\"");
        }
    }

    private function addConstraint(array $statuses): void
    {
        $list = collect($statuses)->map(fn ($s) => "'{$s}'::character varying")->implode(', ');
        DB::statement("ALTER TABLE phase_progress ADD CONSTRAINT \"aiplanstudio_project_phase_progress_status_check\" CHECK (((status)::text = ANY ((ARRAY[{$list}])::text[])))");
    }

    public function up(): void
    {
        $this->dropConstraint();
        $this->addConstraint(['pending', 'running', 'done', 'error', 'skipped']);
    }

    public function down(): void
    {
        $this->dropConstraint();
        $this->addConstraint(['pending', 'running', 'done', 'error']);
    }
};