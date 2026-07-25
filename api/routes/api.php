<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\GenerateStreamController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\ProviderSettingsController;
use App\Http\Controllers\TemplateController;
use App\Http\Controllers\UserSettingsController;
use App\Http\Controllers\VersionController;
use Illuminate\Support\Facades\Route;

Route::post('/register', [AuthController::class, 'register'])->middleware('throttle:5,1');
Route::post('/login', [AuthController::class, 'login'])->middleware('throttle:5,1');
Route::get('/health', fn() => response()->json(['status' => 'ok']));

Route::middleware(['auth:sanctum'])->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/user', [AuthController::class, 'user']);
    Route::get('/projects', [ProjectController::class, 'index']);
    Route::post('/projects', [ProjectController::class, 'store']);
    Route::get('/projects/{id}', [ProjectController::class, 'show']);
    Route::delete('/projects/{id}', [ProjectController::class, 'destroy']);
    Route::post('/projects/{id}/versions', [VersionController::class, 'store']);
    Route::get('/versions/{id}', [VersionController::class, 'show']);
    Route::patch('/versions/{id}/phases/{phaseKey}', [VersionController::class, 'togglePhase']);
    Route::get('/versions/{id}/export', [VersionController::class, 'export']);
    Route::get('/templates', [TemplateController::class, 'index']);
    Route::get('/generate/stream', GenerateStreamController::class);

    Route::middleware(['role.admin'])->group(function () {
        Route::get('/settings/provider', [ProviderSettingsController::class, 'index']);
        Route::post('/settings/provider', [ProviderSettingsController::class, 'store']);
        Route::patch('/settings/provider/{id}', [ProviderSettingsController::class, 'update']);
        Route::delete('/settings/provider/{id}', [ProviderSettingsController::class, 'destroy']);
        Route::post('/settings/provider/{id}/set-active', [ProviderSettingsController::class, 'setActive']);
        Route::post('/settings/provider/{id}/test', [ProviderSettingsController::class, 'test']);
        Route::post('/settings/provider/{id}/test-prompt', [ProviderSettingsController::class, 'testPrompt']);
        Route::get('/settings/users', [UserSettingsController::class, 'index']);
        Route::post('/settings/users', [UserSettingsController::class, 'store']);
        Route::patch('/settings/users/{id}', [UserSettingsController::class, 'update']);
        Route::delete('/settings/users/{id}', [UserSettingsController::class, 'destroy']);
        Route::post('/templates', [TemplateController::class, 'store']);
        Route::delete('/templates/{id}', [TemplateController::class, 'destroy']);
    });
});
