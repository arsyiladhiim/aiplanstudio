<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class TwoFactorController extends Controller
{
    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json([
            'enabled' => $user->hasTwoFactorEnabled(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
        ]);
    }

    // Step 1: generate a fresh secret, store unconfirmed, return secret + otpauth URL.
    public function setup(Request $request, Google2FA $google2fa): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, '2FA hanya tersedia untuk admin.');

        if ($user->two_factor_confirmed_at) {
            return response()->json(['message' => '2FA sudah aktif. Disable dulu untuk setup ulang.'], 422);
        }

        $secret = $google2fa->generateSecretKey(16);
        $user->update([
            'two_factor_secret' => encrypt($secret),
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        $issuer = config('app.name', 'AI Plan Studio');
        $otpauthUrl = $google2fa->getQRCodeUrl($issuer, $user->email, $secret);

        return response()->json([
            'secret' => $secret,
            'otpauth_url' => $otpauthUrl,
        ]);
    }

    // Step 2: verify first TOTP code, mark confirmed, generate recovery codes.
    public function confirm(Request $request, Google2FA $google2FA): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, '2FA hanya tersedia untuk admin.');

        $data = $request->validate([
            'code' => ['required', 'string', 'regex:/^\d{6}$/'],
        ]);

        if (! $user->two_factor_secret) {
            return response()->json(['message' => 'Setup 2FA belum dimulai.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        if (! $google2FA->verifyKey($secret, $data['code'])) {
            throw ValidationException::withMessages(['code' => ['Kode 2FA salah atau kedaluwarsa.']]);
        }

        $recovery = [];
        for ($i = 0; $i < 8; $i++) {
            $recovery[] = bin2hex(random_bytes(4));
        }

        $user->update([
            'two_factor_confirmed_at' => now(),
            'two_factor_recovery_codes' => $recovery,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'action' => Activity::ACTION_TWO_FACTOR_ENABLED,
            'description' => sprintf('User "%s" mengaktifkan 2FA', $user->email),
            'metadata' => ['email' => $user->email],
        ]);

        return response()->json([
            'confirmed_at' => $user->two_factor_confirmed_at->toIso8601String(),
            'recovery_codes' => $recovery,
        ]);
    }

    public function disable(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user->isAdmin(), 403, '2FA hanya tersedia untuk admin.');

        $data = $request->validate([
            'password' => ['required', 'string'],
        ]);

        if (! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['password' => ['Password salah.']]);
        }

        $user->update([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
        ]);

        Activity::create([
            'user_id' => $user->id,
            'action' => Activity::ACTION_TWO_FACTOR_DISABLED,
            'description' => sprintf('User "%s" menonaktifkan 2FA', $user->email),
            'metadata' => ['email' => $user->email],
        ]);

        return response()->json(['message' => '2FA dinonaktifkan.']);
    }
}
