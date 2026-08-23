<?php

use App\Http\Controllers\ActivityController;
use App\Http\Controllers\AgentEventController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\ChangelogController;
use App\Http\Controllers\EvidenceController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\ForgotPasswordController;
use App\Http\Controllers\GenerateStreamController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProjectTokenController;
use App\Http\Controllers\ProviderSettingsController;
use App\Http\Controllers\ResetPasswordController;
use App\Http\Controllers\SocialiteController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\TwoFactorController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\VersionController;
use App\Http\Controllers\WebhookController;
use App\Models\ProjectApiToken;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:register');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:login');
Route::post('/login/2fa', [AuthController::class, 'verify2fa'])->middleware('throttle:login');
Route::post('/login/2fa/cancel', [AuthController::class, 'cancel2fa']);
Route::post('/forgot-password', ForgotPasswordController::class)->middleware('throttle:forgot-password');
Route::post('/reset-password', ResetPasswordController::class)->middleware('throttle:reset-password');
Route::get('/auth/google/redirect', [SocialiteController::class, 'redirect'])->middleware('throttle:30,1');
Route::get('/auth/google/callback', [SocialiteController::class, 'callback'])->middleware('throttle:10,1');
Route::get('/health', fn() => response()->json(['status' => 'ok']));
Route::get('/version', [\App\Http\Controllers\InfoController::class, 'version']);
Route::get('/changelog', [ChangelogController::class, 'index']);
// CP-13: CSRF token endpoint for cross-origin direct routing.
// Browser can't read XSRF-TOKEN cookie from api subdomain (host-only cookie).
// Frontend fetches this GET (no CSRF check) and sends token via X-CSRF-TOKEN header.
// Laravel CSRF check accepts raw session token in X-CSRF-TOKEN (no cookie decrypt needed).
// CP-16.M1: return issued_at + expires_at so frontend can cache + lazy refetch on expiry.
// expires_at = session lifetime (config('session.lifetime') minutes from now).
Route::get('/csrf-token', function (Request $r) {
    $lifetimeMinutes = (int) config('session.lifetime', 120);
    return response()->json([
        'token' => $r->session()->token(),
        'issued_at' => time(),
        'expires_at' => time() + ($lifetimeMinutes * 60),
        'lifetime' => $lifetimeMinutes * 60,
    ]);
})->middleware('throttle:60,1');

// Webhook — external access via Project API Token (not session auth)
Route::post('/webhooks/phase-complete', [WebhookController::class, 'phaseComplete'])
    ->middleware(['auth.project-token', 'throttle:60,1']);
// CP-44 CP-07: Agent Event Protocol v1 — telemetry granular dari coding agent.
Route::post('/agent/events', [AgentEventController::class, 'store'])
    ->middleware(['auth.project-token', 'throttle:120,1']);
// CP-46.B: Evidence endpoint — agent posts per-stage verification result.
Route::post('/versions/{versionId}/evidence', [EvidenceController::class, 'store'])
    ->whereNumber('versionId')
    ->middleware(['auth.project-token', 'throttle:120,1']);

Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/settings/profile', [ProfileController::class, 'show']);
    Route::patch('/settings/profile', [ProfileController::class, 'update']);
    // CP-18.F1: 2FA management (admin only — enforced in controller).
    Route::get('/settings/2fa', [TwoFactorController::class, 'status']);
    Route::post('/settings/2fa/setup', [TwoFactorController::class, 'setup']);
    Route::post('/settings/2fa/confirm', [TwoFactorController::class, 'confirm']);
    Route::post('/settings/2fa/disable', [TwoFactorController::class, 'disable']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::get('/projects/search', [ProjectController::class, 'search']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::patch('/projects/{id}', [ProjectController::class, 'update']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/versions', [VersionController::class, 'store']);
    Route::get('/versions/{id}', [VersionController::class, 'show']);
    Route::match(['get', 'post'], '/versions/{id}/phase-progress/stream', [VersionController::class, 'phaseProgressStream'])->middleware('throttle:30,1');
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
    Route::post('/versions/{id}/skip-stage', [VersionController::class, 'skipStage']);
    Route::post('/versions/{id}/regenerate-standards', [VersionController::class, 'regenerateStandards']);
    Route::get('/versions/{id}/standards', [VersionController::class, 'downloadStandards']);
    Route::get('/versions/{id}/agents', [VersionController::class, 'downloadAgents']);
    Route::get('/versions/{id}/standards/mobile', [VersionController::class, 'downloadMobileStandards']);
    Route::get('/versions/{id}/agents/mobile', [VersionController::class, 'downloadMobileAgents']);
    Route::post('/versions/{id}/regenerate-standards/mobile', [VersionController::class, 'regenerateMobileStandards']);
    Route::get('/dashboard/stats', [ProjectController::class, 'dashboardStats']);
    // CP-44 CP-01: single stage registry exposed to frontend.
    // CP-46.A: enrich dengan `gate` field per stage (null bila tanpa gate).
    Route::get('/stages', function () {
        $gateMap = (new \App\Services\StageGateRegistry)->gateMap();

        $data = array_map(function ($stage) use ($gateMap) {
            $stage['gate'] = $gateMap[$stage['key']] ?? null;

            return $stage;
        }, \App\Services\StageRegistry::all());

        return response()->json(['data' => $data]);
    });
    // CP-44 CP-07: agent event feed untuk UI tracking.
    Route::get('/versions/{versionId}/agent-events', [AgentEventController::class, 'index'])->whereNumber('versionId');
    // CP-46.B: list evidence per version (sanctum user).
    Route::get('/versions/{versionId}/evidence', [EvidenceController::class, 'index'])->whereNumber('versionId');
    // CP-46.E: export package ZIP + production-readiness composite state.
    Route::get('/versions/{versionId}/export-package', [ExportController::class, 'package'])->whereNumber('versionId');
    Route::get('/versions/{versionId}/production-readiness', [ExportController::class, 'productionReadiness'])->whereNumber('versionId');
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/templates/{id}', [TemplateController::class, 'show']);
    Route::post('/templates/{id}/instantiate', [TemplateController::class, 'instantiate']);

    // AI endpoints — tighter rate limit (expensive calls)
    Route::post('/generate/stream', GenerateStreamController::class)->middleware('throttle:30,1');

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
        Route::get('/research/ideas', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'ideas']);
        Route::get('/research/settings', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'showSettings']);
        Route::patch('/research/settings', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'updateSettings']);
        Route::get('/research/ai-providers', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'aiProviders']);
        Route::post('/research/test-search', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'testSearch'])->middleware('throttle:research');
        Route::post('/research/run-now', [\App\Http\Controllers\Admin\ResearchAgentController::class, 'runNow'])->middleware('throttle:research');
        Route::get('/settings/provider', [ProviderSettingsController::class, 'index']);
        Route::post('/settings/provider', [ProviderSettingsController::class, 'store']);
        Route::patch('/settings/provider/{id}', [ProviderSettingsController::class, 'update']);
        Route::delete('/settings/provider/{id}', [ProviderSettingsController::class, 'destroy']);
        Route::post('/settings/provider/{id}/set-active', [ProviderSettingsController::class, 'setActive']);
        Route::post('/settings/provider/{id}/test', [ProviderSettingsController::class, 'test'])->middleware('throttle:10,1');
        Route::post('/settings/provider/{id}/test-prompt', [ProviderSettingsController::class, 'testPrompt'])->middleware('throttle:10,1');
        Route::get('/settings/users', [UserSettingsController::class, 'index']);
        Route::post('/settings/users', [UserSettingsController::class, 'store']);
        Route::post('/settings/users/bulk-action', [UserSettingsController::class, 'bulkAction']);
        Route::patch('/settings/users/{id}', [UserSettingsController::class, 'update']);
        Route::delete('/settings/users/{id}', [UserSettingsController::class, 'destroy']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::patch('/templates/{id}', [TemplateController::class, 'update']);
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    });
});
