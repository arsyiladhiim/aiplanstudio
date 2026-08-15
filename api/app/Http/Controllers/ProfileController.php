<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class ProfileController extends Controller
{
    public function show(Request $request): JsonResponse
    {
        return response()->json($request->user()->only(['id', 'name', 'email', 'role', 'accent_color']));
    }

    public function update(Request $request): JsonResponse
    {
        $user = $request->user();

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'email' => ['sometimes', 'email', 'max:255', Rule::unique('users')->ignore($user->id)],
            'password' => ['sometimes', 'string', 'min:8', 'confirmed'],
            'current_password' => ['required_with:password', 'string'],
            'accent_color' => ['sometimes', 'nullable', 'string', 'regex:/^#([0-9a-fA-F]{3}|[0-9a-fA-F]{6}|[0-9a-fA-F]{8})$/'],
        ]);

        // CP-17.L4: verify current password before allowing password change.
        if (isset($data['password']) && ! Hash::check($data['current_password'], $user->password)) {
            return response()->json(['message' => 'Password saat ini salah.'], 422);
        }

        if (isset($data['password'])) {
            $data['password'] = Hash::make($data['password']);
            unset($data['current_password']);
        }

        $user->update($data);

        return response()->json($user->fresh()->only(['id', 'name', 'email', 'role', 'accent_color']));
    }
}
