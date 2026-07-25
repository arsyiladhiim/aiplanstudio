<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::latest()->get(['id', 'name', 'email', 'role', 'created_at']));
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:8'],
            'role' => ['required', 'in:admin,member'],
        ]);

        $user = User::create([
            ...$data,
            'password' => Hash::make($data['password']),
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'in:admin,member'],
        ]);

        $user->update($data);

        return response()->json($user);
    }

    public function destroy(int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Tidak bisa menghapus admin.'], 422);
        }
        $user->delete();

        return response()->json(null, 204);
    }
}
