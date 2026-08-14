<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class ChangelogController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => self::entries(),
        ]);
    }

    public static function entries(): array
    {
        return [
            [
                'version' => '0.2.0',
                'date' => '2026-08-13',
                'highlights' => [
                    'Live pipeline progress widget di seluruh app',
                    '"What\'s new" modal otomatis saat rilis baru',
                    'Footer version badge + halaman About',
                    'Structured logging dengan request ID correlation',
                    'Admin endpoints: health, migrations, demo seeder',
                ],
                'migrations' => [],
            ],
            [
                'version' => '0.1.0',
                'date' => '2026-08-01',
                'highlights' => [
                    'AI pipeline 14 stages (web + mobile track)',
                    'Project versioning dengan baseline notes',
                    'API contract + ERD generation',
                    'Sanctum SPA session auth via BFF',
                ],
                'migrations' => [],
            ],
        ];
    }
}
