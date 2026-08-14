<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

class InfoController extends Controller
{
    public function version(): JsonResponse
    {
        $composer = json_decode((string) file_get_contents(base_path('composer.json')), true);
        $version = $composer['extra']['app-version'] ?? '0.0.0';

        return response()->json([
            'version' => $version,
            'name' => $composer['name'] ?? 'aiplanstudio',
        ]);
    }
}
