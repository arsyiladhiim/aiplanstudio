<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\UserPendingNotification;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use PragmaRX\Google2FA\Google2FA;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $isFirst = User::query()->doesntExist();
        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
            'role' => $isFirst ? 'admin' : 'member',
            'status' => $isFirst ? 'active' : 'pending',
        ]);

        event(new Registered($user));

        // CP-17.L3: audit log for new registration.
        Activity::create([
            'user_id' => $user->id,
            'action' => Activity::ACTION_USER_REGISTERED,
            'description' => sprintf('User baru "%s" mendaftar (%s)', $user->email, $user->status),
            'metadata' => ['email' => $user->email, 'role' => $user->role, 'is_first_user' => $isFirst],
        ]);

        // CP-18.F4: notify user that registration is pending admin approval.
        if (! $isFirst) {
            $user->notify(new UserPendingNotification);
        }

        // Non-first users wait for admin approval before they can log in.
        if (! $isFirst) {
            return response()->json([
                'user' => $user->only(['id', 'name', 'email', 'role', 'status']),
                'pending' => true,
                'message' => 'Akun berhasil dibuat dan menunggu persetujuan admin.',
            ], 201);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return response()->json(['user' => $user], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
        ]);

        $user = User::where('email', $data['email'])->first();
        if ($user && ! $user->isActive()) {
            // CP-17.L3: log failed login (account not active).
            Activity::create([
                'user_id' => $user->id,
                'action' => Activity::ACTION_USER_FAILED_LOGIN,
                'description' => sprintf('Login gagal untuk "%s" (akun belum aktif)', $user->email),
                'metadata' => ['email' => $user->email, 'reason' => 'inactive'],
            ]);
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak cocok.'],
            ]);
        }

        if (! Auth::guard('web')->attempt($data, $request->boolean('remember'))) {
            // CP-17.L3: log failed login (bad credentials).
            Activity::create([
                'user_id' => $user?->id,
                'action' => Activity::ACTION_USER_FAILED_LOGIN,
                'description' => sprintf('Login gagal untuk "%s" (kredensial salah)', $data['email']),
                'metadata' => ['email' => $data['email'], 'reason' => 'bad_credentials'],
            ]);
            throw ValidationException::withMessages([
                'email' => ['Kredensial tidak cocok.'],
            ]);
        }

        $authenticated = Auth::guard('web')->user();

        // CP-18.F1: if admin has 2FA enabled, defer session finalization and demand TOTP code.
        if ($authenticated->hasTwoFactorEnabled()) {
            $request->session()->put('2fa.pending', $authenticated->id);
            // We do NOT regenerate session yet — wait until TOTP verified, then regenerate in verify2fa.
            return response()->json([
                'two_factor_required' => true,
                'message' => 'Masukkan kode 2FA dari authenticator app.',
            ]);
        }

        $request->session()->regenerate();

        // CP-17.L3: audit log for successful login.
        Activity::create([
            'user_id' => Auth::guard('web')->id(),
            'action' => Activity::ACTION_USER_LOGIN,
            'description' => sprintf('User "%s" login', Auth::guard('web')->user()?->email),
            'metadata' => ['ip' => $request->ip()],
        ]);

        return response()->json(['user' => $authenticated]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    // CP-18.F1: finalize login after admin submits 2FA TOTP code.
    public function verify2fa(Request $request, Google2FA $google2FA): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string'],
        ]);

        $pendingId = $request->session()->get('2fa.pending');
        if (! $pendingId) {
            return response()->json(['message' => 'Tidak ada login 2FA yang tertunda.'], 422);
        }

        $user = User::find($pendingId);
        if (! $user || ! $user->hasTwoFactorEnabled()) {
            $request->session()->forget('2fa.pending');
            return response()->json(['message' => 'User tidak valid.'], 422);
        }

        $secret = decrypt($user->two_factor_secret);
        $valid = $google2FA->verifyKey($secret, $data['code']);

        // Recovery code fallback.
        if (! $valid) {
            $codes = $user->two_factor_recovery_codes ?? [];
            $idx = array_search($data['code'], $codes, true);
            if ($idx !== false) {
                unset($codes[$idx]);
                $user->update(['two_factor_recovery_codes' => array_values($codes)]);
                $valid = true;
            }
        }

        if (! $valid) {
            throw ValidationException::withMessages(['code' => ['Kode 2FA salah.']]);
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();
        $request->session()->forget('2fa.pending');

        Activity::create([
            'user_id' => $user->id,
            'action' => Activity::ACTION_USER_LOGIN,
            'description' => sprintf('User "%s" login (2FA verified)', $user->email),
            'metadata' => ['ip' => $request->ip(), 'via' => '2fa'],
        ]);

        return response()->json(['user' => $user]);
    }

    public function cancel2fa(Request $request): JsonResponse
    {
        $request->session()->forget('2fa.pending');
        return response()->json(null, 204);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
