<?php

namespace App\Http\Middleware;

use App\Models\ProjectApiToken;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateProjectToken
{
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->bearerToken();
        if (!$token) {
            return response()->json(['message' => 'Token tidak ditemukan. Header Authorization: Bearer <token> required.'], 401);
        }

        $hashed = hash('sha256', $token);
        $projectToken = ProjectApiToken::where('token_hash', $hashed)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (!$projectToken) {
            return response()->json(['message' => 'Token tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $projectToken->touch('last_used_at');

        $request->merge([
            'project_token' => $projectToken,
            'project_id' => $projectToken->project_id,
        ]);

        return $next($request);
    }
}
