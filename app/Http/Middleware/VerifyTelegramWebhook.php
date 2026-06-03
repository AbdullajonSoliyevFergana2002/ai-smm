<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram webhook so'rovlarining haqiqiyligini tekshiradi.
 *
 * Webhook'ni o'rnatishda (setWebhook) `secret_token` parametri beriladi. Telegram
 * keyin har bir so'rovda uni `X-Telegram-Bot-Api-Secret-Token` header'i orqali
 * qaytaradi. Bu — endpoint ochiq (autentifikatsiyasiz) bo'lsa-da, unga faqat
 * Telegram murojaat qila olishini kafolatlaydigan yagona ishonchli usul.
 *
 * @see https://core.telegram.org/bots/api#setwebhook
 */
class VerifyTelegramWebhook
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.telegram.webhook_secret');

        // Maxfiy token sozlanmagan bo'lsa — endpoint himoyasiz qoladi, shuning uchun
        // so'rovni rad etamiz (xavfsiz default — "fail closed").
        if (empty($expected)) {
            return response()->json(['ok' => false], 403);
        }

        $provided = $request->header('X-Telegram-Bot-Api-Secret-Token');

        // Timing-safe taqqoslash (token qiymatini chetdan topib bo'lmasligi uchun).
        if (! is_string($provided) || ! hash_equals($expected, $provided)) {
            return response()->json(['ok' => false], 403);
        }

        return $next($request);
    }
}
