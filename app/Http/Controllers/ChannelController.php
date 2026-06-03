<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ChannelController extends Controller
{
    /**
     * Joriy foydalanuvchiga ulangan barcha kanallar ro'yxatini qaytaradi.
     *
     * WebApp yuklanishida chaqiriladi — natija Select (dropdown) ni to'ldirish uchun ishlatiladi.
     */
    public function index(Request $request): JsonResponse
    {
        $channels = $request->user()
            ->channels()
            ->select(['id', 'channel_name', 'telegram_channel_id'])
            ->latest('id')
            ->get();

        return response()->json([
            'channels' => $channels,
        ]);
    }
}
