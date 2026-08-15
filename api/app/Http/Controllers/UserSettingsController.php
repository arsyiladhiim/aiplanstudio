<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class UserSettingsController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(User::latest()->get(['id', 'name', 'email', 'role', 'status', 'created_at']));
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
            'status' => 'active',
        ]);

        return response()->json($user, 201);
    }

    public function update(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:255'],
            'role' => ['sometimes', 'in:admin,member'],
            'status' => ['sometimes', 'in:active,pending'],
        ]);

        // Prevent system lockout: the last active admin cannot be demoted or deactivated.
        $removesAdmin = ($data['role'] ?? null) === 'member' || ($data['status'] ?? null) === 'pending';
        if ($removesAdmin && $user->isAdmin() && $user->status === 'active') {
            $activeAdmins = User::query()->where('role', 'admin')->where('status', 'active')->count();
            if ($activeAdmins <= 1) {
                return response()->json(['message' => 'Tidak bisa menonaktifkan admin terakhir.'], 422);
            }
        }

        $previousStatus = $user->status;
        $user->update($data);

        // CP-16.M3: audit log for approve/reject status changes.
        if (isset($data['status']) && $data['status'] !== $previousStatus) {
            $action = $data['status'] === 'active'
                ? Activity::ACTION_USER_APPROVED
                : Activity::ACTION_USER_REJECTED;
            Activity::create([
                'user_id' => $request->user()?->id,
                'action' => $action,
                'description' => sprintf(
                    '%s menyetujui/menolak user "%s" (%s → %s)',
                    $request->user()?->name ?? 'system',
                    $user->email,
                    $previousStatus,
                    $data['status'],
                ),
                'metadata' => ['target_user_id' => $user->id, 'target_email' => $user->email],
            ]);
        }

        return response()->json($user);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $user = User::findOrFail($id);
        if ($user->isAdmin()) {
            return response()->json(['message' => 'Tidak bisa menghapus admin.'], 422);
        }
        $deletedEmail = $user->email;
        $user->delete();

        // CP-16.M3: audit log for user deletion.
        Activity::create([
            'user_id' => $request->user()?->id,
            'action' => Activity::ACTION_USER_DELETED,
            'description' => sprintf(
                '%s menghapus user "%s"',
                $request->user()?->name ?? 'system',
                $deletedEmail,
            ),
            'metadata' => ['target_email' => $deletedEmail],
        ]);

        return response()->json(null, 204);
    }
}
