<?php

use App\Http\Controllers\PostController;
use Illuminate\Support\Facades\Route;

// Telegram WebApp autentifikatsiyasi talab qilinadigan route'lar.
Route::middleware('telegram.auth')->group(function () {
    Route::post('/posts/generate', [PostController::class, 'generate']);
    Route::post('/posts/publish', [PostController::class, 'publish']);
});
