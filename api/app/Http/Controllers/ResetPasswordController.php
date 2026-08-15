<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;

class ResetPasswordController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $status = Password::reset(
            $data,
            function ($user, $password) {
                $user->forceFill([
                    'password' => Hash::make($password),
                ])->save();

                event(new PasswordReset($user));

                // CP-17.L3: audit log for password reset.
                Activity::create([
                    'user_id' => $user->id,
                    'action' => Activity::ACTION_USER_PASSWORD_RESET,
                    'description' => sprintf('User "%s" reset password', $user->email),
                    'metadata' => ['email' => $user->email],
                ]);
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            return response()->json(['message' => 'Password berhasil direset. Silakan login.']);
        }

        return response()->json(['message' => 'Tautan reset tidak valid atau sudah kedaluwarsa.'], 422);
    }
}
