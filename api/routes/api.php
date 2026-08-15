<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GenerateStreamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTokenController;
use App\Http\Controllers\ProviderSettingsController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WebhookController;
use App\Models\ProjectApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:5,1');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:5,1');
Route::get('/auth/google/redirect', [SocialiteController::class, 'redirect'])->middleware('throttle:30,1');
Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->middleware('throttle:10,1');
Route::get('/health', fn() => response()->json(['status' => 'ok']));
Route::get('/version', [\App\Http\Controllers\InfoController::class, 'version']);
Route::get('/changelog', [ChangelogController::class, 'index']);

// Webhook — external access via Project API Token (not session auth)
Route::post('/webhooks/phase-complete', [WebhookController::class, 'phaseComplete'])
    ->middleware(['auth.project-token', 'throttle:60,1']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/settings/profile', [ProfileController::class, 'show']);
    Route::patch('/settings/profile', [ProfileController::class, 'update']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/search', [ProjectController::class, 'search']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/versions', [VersionController::class, 'store']);
    Route::get('/versions/{id}', [VersionController::class, 'show']);
    Route::get('/versions/{id}/phase-progress/stream', [VersionController::class, 'phaseProgressStream'])->middleware('throttle:30,1');
    Route::delete('/versions/{id}', [VersionController::class, 'destroy']);
    Route::patch('/versions/{id}/answers', [VersionController::class, 'updateAnswers']);
    Route::patch('/versions/{id}/phases/{phaseKey}', [VersionController::class, 'togglePhase']);
    Route::patch('/versions/{id}/tasks/{taskKey}', [VersionController::class, 'toggleTask']);
    Route::get('/versions/{id}/export', [VersionController::class, 'export']);
    Route::patch('/versions/{id}/artifacts', [VersionController::class, 'updateArtifact']);
    Route::get('/versions/{id}/diff', [VersionController::class, 'diff']);
    Route::get('/projects/{project}/activities', [ActivityController::class, 'index']);
    Route::patch('/projects/{id}/favorite', [ProjectController::class, 'toggleFavorite']);
    Route::patch('/projects/{id}/pin', [ProjectController::class, 'togglePin']);
    Route::patch('/projects/{id}/archive', [ProjectController::class, 'toggleArchive']);
    Route::get('/projects/{id}/tasks', [ProjectController::class, 'tasks']);
    Route::get('/projects/{id}/export-all', [ProjectController::class, 'exportAll']);
    Route::post('/versions/{id}/regenerate', [VersionController::class, 'regenerateStage']);
    Route::post('/versions/{id}/restart-from-analisa', [VersionController::class, 'restartFromAnalisa']);
    Route::post('/versions/{id}/regenerate-standards', [VersionController::class, 'regenerateStandards']);
    Route::get('/versions/{id}/standards', [VersionController::class, 'downloadStandards']);
    Route::get('/versions/{id}/agents', [VersionController::class, 'downloadAgents']);
    Route::get('/versions/{id}/standards/mobile', [VersionController::class, 'downloadMobileStandards']);
    Route::get('/versions/{id}/agents/mobile', [VersionController::class, 'downloadMobileAgents']);
    Route::post('/versions/{id}/regenerate-standards/mobile', [VersionController::class, 'regenerateMobileStandards']);
    Route::get('/dashboard/stats', [ProjectController::class, 'dashboardStats']);
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::post('/templates/{id}/instantiate', [TemplateController::class, 'instantiate']);

    // AI endpoints — tighter rate limit (expensive calls)
    Route::post('/generate/stream', GenerateStreamController::class)->middleware('throttle:10,1');

    // Project API Token management
    Route::get('/projects/{id}/tokens', function (Request $request, int $id) {
        $project = \App\Models\Project::where('user_id', $request->user()->id)->findOrFail($id);
        return response()->json($project->apiTokens()->select('id', 'name', 'last_used_at', 'expires_at', 'created_at')->get());
    });
    Route::post('/projects/{id}/tokens', function (Request $request, int $id) {
        $data = $request->validate(['name' => ['required', 'string', 'max:255']]);
        $project = \App\Models\Project::where('user_id', $request->user()->id)->findOrFail($id);
        $result = ProjectApiToken::generate($project, $data['name']);
        return response()->json([
            'token' => $result['token'],
            'secret' => $result['secret'],
            'id' => $result['model']->id,
            'name' => $result['model']->name,
        ], 201);
    });
    Route::delete('/projects/{id}/tokens/{tokenId}', function (Request $request, int $id, int $tokenId) {
        $project = \App\Models\Project::where('user_id', $request->user()->id)->findOrFail($id);
        $project->apiTokens()->where('id', $tokenId)->delete();
        return response()->json(null, 204);
    });
    // CP-6: Setup Tracking auto-create per-version ProjectApiToken.
    Route::post('/projects/{project}/versions/{version}/tokens/auto-tracking', [ProjectTokenController::class, 'autoTrackingForVersion']);

    Route::middleware(['role.admin'])->group(function () {
        Route::get('/activities', [ActivityController::class, 'globalIndex']);
        Route::get('/admin/health', [\App\Http\Controllers\Admin\HealthController::class, 'index']);
        Route::get('/admin/migrations', [\App\Http\Controllers\Admin\MigrationController::class, 'index']);
        Route::get('/settings/provider', [ProviderSettingsController::class, 'index']);
        Route::post('/settings/provider', [ProviderSettingsController::class, 'store']);
        Route::patch('/settings/provider/{id}', [ProviderSettingsController::class, 'update']);
        Route::delete('/settings/provider/{id}', [ProviderSettingsController::class, 'destroy']);
        Route::post('/settings/provider/{id}/set-active', [ProviderSettingsController::class, 'setActive']);
        Route::post('/settings/provider/{id}/test', [ProviderSettingsController::class, 'test'])->middleware('throttle:10,1');
        Route::post('/settings/provider/{id}/test-prompt', [ProviderSettingsController::class, 'testPrompt'])->middleware('throttle:10,1');
        Route::get('/settings/users', [UserSettingsController::class, 'index']);
        Route::post('/settings/users', [UserSettingsController::class, 'store']);
        Route::patch('/settings/users/{id}', [UserSettingsController::class, 'update']);
        Route::delete('/settings/users/{id}', [UserSettingsController::class, 'destroy']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::patch('/templates/{id}', [TemplateController::class, 'update']);
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    });
});
