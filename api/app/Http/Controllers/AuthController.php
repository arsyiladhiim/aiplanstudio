<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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

        $request->session()->regenerate();

        // CP-17.L3: audit log for successful login.
        Activity::create([
            'user_id' => Auth::guard('web')->id(),
            'action' => Activity::ACTION_USER_LOGIN,
            'description' => sprintf('User "%s" login', Auth::guard('web')->user()?->email),
            'metadata' => ['ip' => $request->ip()],
        ]);

        return response()->json(['user' => Auth::guard('web')->user()]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(null, 204);
    }

    public function user(Request $request): JsonResponse
    {
        return response()->json($request->user());
    }
}
