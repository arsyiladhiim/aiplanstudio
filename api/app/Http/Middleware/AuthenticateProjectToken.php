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
        if (! $token) {
            return response()->json(['message' => 'Token tidak ditemukan. Header Authorization: Bearer <token> required.'], 401);
        }

        $hashed = hash('sha256', $token);
        $projectToken = ProjectApiToken::where('token_hash', $hashed)
            ->where(function ($q) {
                $q->whereNull('expires_at')->orWhere('expires_at', '>', now());
            })
            ->first();

        if (! $projectToken) {
            return response()->json(['message' => 'Token tidak valid atau sudah kedaluwarsa.'], 401);
        }

        $secret = $request->header('X-Token-Secret');
        if (! $secret && $projectToken->secret_hash) {
            return response()->json(['message' => 'Header X-Token-Secret wajib diisi untuk route webhook.'], 401);
        }
        if ($secret && $projectToken->secret_hash) {
            if (! hash_equals($projectToken->secret_hash, hash('sha256', $secret))) {
                return response()->json(['message' => 'Token secret tidak valid.'], 401);
            }
            $request->attributes->set('project_token_secret', $secret);
        }

        $projectToken->touch('last_used_at');

        $request->merge([
            'project_token' => $projectToken,
            'project_id' => $projectToken->project_id,
        ]);

        $request->attributes->set('project_token', $projectToken);

        return $next($request);
    }
}
