<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback(Request $request)
    {
        try {
            $socialUser = Socialite::driver('google')->user();
        } catch (\Throwable $e) {
            Log::error('[google-login] callback error: ' . $e->getMessage());
            return redirect()->away(config('app.frontend_url') . '/login?error=google');
        }

        $email = $socialUser->getEmail();
        if (! $email) {
            return redirect()->away(config('app.frontend_url') . '/login?error=google');
        }

        $user = User::where('email', $email)->first();

        if ($user) {
            if (! $user->isActive()) {
                return redirect()->away(config('app.frontend_url') . '/login?status=pending');
            }
            if (! $user->name) {
                $user->forceFill(['name' => $socialUser->getName()])->save();
            }
        } else {
            $isFirst = User::query()->doesntExist();
            $user = User::create([
                'name' => $socialUser->getName() ?? 'Google User',
                'email' => $email,
                'password' => Str::random(32),
                'role' => $isFirst ? 'admin' : 'member',
                'status' => $isFirst ? 'active' : 'pending',
            ]);
            event(new Registered($user));

            if (! $isFirst) {
                return redirect()->away(config('app.frontend_url') . '/login?status=pending');
            }
        }

        Auth::guard('web')->login($user);
        $request->session()->regenerate();

        return redirect()->away(config('app.frontend_url') . '/dashboard');
    }
}
