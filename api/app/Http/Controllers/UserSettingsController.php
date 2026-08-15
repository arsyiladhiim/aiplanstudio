<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\User;
use App\Notifications\UserApprovedNotification;
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

            // CP-18.F4: email user when status becomes active.
            if ($data['status'] === 'active') {
                $user->notify(new UserApprovedNotification);
            }
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

    // CP-18.F2: bulk action endpoint.
    public function bulkAction(Request $request): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', 'in:approve,reject,delete'],
            'user_ids' => ['required', 'array', 'min:1'],
            'user_ids.*' => ['integer'],
        ]);

        $actor = $request->user();
        $users = User::query()->whereIn('id', $data['user_ids'])->get();

        if ($users->isEmpty()) {
            return response()->json(['message' => 'Tidak ada user yang cocok.'], 422);
        }

        $approved = $rejected = $deleted = 0;
        $skipped = [];

        foreach ($users as $user) {
            if ($user->id === $actor?->id) {
                $skipped[] = ['id' => $user->id, 'reason' => 'cannot_act_on_self'];
                continue;
            }
            if ($user->isAdmin()) {
                $skipped[] = ['id' => $user->id, 'reason' => 'cannot_act_on_admin'];
                continue;
            }

            switch ($data['action']) {
                case 'approve':
                    if ($user->status !== 'pending') {
                        $skipped[] = ['id' => $user->id, 'reason' => 'not_pending'];
                        continue 2;
                    }
                    $user->update(['status' => 'active']);
                    Activity::create([
                        'user_id' => $actor?->id,
                        'action' => Activity::ACTION_USER_APPROVED,
                        'description' => sprintf('%s menyetujui user "%s" (bulk)', $actor?->name ?? 'system', $user->email),
                        'metadata' => ['target_user_id' => $user->id, 'target_email' => $user->email, 'bulk' => true],
                    ]);
                    $user->notify(new UserApprovedNotification);
                    $approved++;
                    break;
                case 'reject':
                    if ($user->status !== 'pending') {
                        $skipped[] = ['id' => $user->id, 'reason' => 'not_pending'];
                        continue 2;
                    }
                    // Reject = remove the pending user. Same as delete for pending accounts.
                    $deletedEmail = $user->email;
                    $user->delete();
                    Activity::create([
                        'user_id' => $actor?->id,
                        'action' => Activity::ACTION_USER_REJECTED,
                        'description' => sprintf('%s menolak user "%s" (bulk)', $actor?->name ?? 'system', $deletedEmail),
                        'metadata' => ['target_email' => $deletedEmail, 'bulk' => true],
                    ]);
                    $rejected++;
                    break;
                case 'delete':
                    $deletedEmail = $user->email;
                    $user->delete();
                    Activity::create([
                        'user_id' => $actor?->id,
                        'action' => Activity::ACTION_USER_DELETED,
                        'description' => sprintf('%s menghapus user "%s" (bulk)', $actor?->name ?? 'system', $deletedEmail),
                        'metadata' => ['target_email' => $deletedEmail, 'bulk' => true],
                    ]);
                    $deleted++;
                    break;
            }
        }

        return response()->json([
            'action' => $data['action'],
            'affected' => ['approved' => $approved, 'rejected' => $rejected, 'deleted' => $deleted],
            'skipped' => $skipped,
        ]);
    }
}
