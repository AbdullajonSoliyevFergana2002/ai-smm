<?php

use App\Http\Controllers\ChannelController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\TelegramWebhookController;
use Illuminate\Support\Facades\Route;

// Telegram WebApp autentifikatsiyasi talab qilinadigan route'lar.
Route::middleware('telegram.auth')->group(function () {
    Route::get('/channels', [ChannelController::class, 'index']);
    Route::post('/posts/generate', [PostController::class, 'generate']);
    Route::post('/posts/publish', [PostController::class, 'publish']);
});

// Telegram Bot webhook'i — maxfiy token orqali himoyalangan (setWebhook secret_token).
Route::post('/telegram/webhook', [TelegramWebhookController::class, 'handle'])
    ->middleware('telegram.webhook');
