<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;

class MigrationController extends Controller
{
    public function index(): JsonResponse
    {
        $applied = collect(DB::table('migrations')->orderBy('batch')->orderBy('migration')->get())
            ->map(fn ($row) => [
                'migration' => $row->migration,
                'batch' => (int) $row->batch,
                'ran_at' => null,
            ])
            ->values();

        $files = glob(database_path('migrations/*.php')) ?: [];
        $appliedNames = $applied->pluck('migration')->all();

        $pending = [];
        foreach ($files as $file) {
            $name = pathinfo($file, PATHINFO_FILENAME);
            $migrationName = preg_replace('/^\d{4}_\d{2}_\d{2}_\d{6}_/', '', $name);
            if (! in_array($migrationName, $appliedNames, true)) {
                $pending[] = [
                    'migration' => $migrationName,
                    'file' => basename($file),
                ];
            }
        }

        return response()->json([
            'pending' => $pending,
            'applied_count' => $applied->count(),
            'pending_count' => count($pending),
            'checked_at' => now()->toIso8601String(),
        ]);
    }
}
