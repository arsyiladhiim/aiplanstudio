<?php

use Illuminate\Support\Facades\Route;

Route::get('/', fn () => response()->json(['ok' => true, 'app' => 'AI Planning Studio']));
Route::get('/login', fn () => response()->json(['redirect' => '/login']))->name('login');
