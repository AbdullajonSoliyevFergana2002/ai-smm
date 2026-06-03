<?php

namespace App\Http\Controllers;

use App\Models\Channel;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

/**
 * Telegram Bot API webhook'idan kelgan yangilanishlarni (update) qabul qiladi.
 *
 * Hozircha faqat `my_chat_member` yangilanishini qayta ishlaydi: bot kanalga
 * admin qilib qo'shilganda kanalni bazaga saqlaydi, adminlikdan/kanaldan
 * olib tashlanganda esa o'chiradi.
 *
 * @see https://core.telegram.org/bots/api#chatmemberupdated
 */
class TelegramWebhookController extends Controller
{
    /**
     * Webhook'ning yagona kirish nuqtasi.
     *
     * MUHIM: Telegram'ga doim 200 OK qaytaramiz. Aks holda u so'rovni
     * qayta-qayta yuborib (retry), navbatni to'ldirib yuboradi. Ichki xatolar
     * faqat log'ga yoziladi, lekin webhook "muvaffaqiyatli" deb belgilanadi.
     */
    public function handle(Request $request): JsonResponse
    {
        $myChatMember = $request->input('my_chat_member');

        // 1. Bizni faqat `my_chat_member` qiziqtiradi — boshqa update turlarini
        //    (message, channel_post va h.k.) e'tiborsiz qoldiramiz.
        if (! is_array($myChatMember)) {
            return $this->ok();
        }

        $chat = $myChatMember['chat'] ?? null;
        $newStatus = $myChatMember['new_chat_member']['status'] ?? null;
        $fromId = $myChatMember['from']['id'] ?? null;

        // 2. Faqat kanallar bilan ishlaymiz (guruh/superguruhlarni o'tkazib yuboramiz).
        if (! is_array($chat) || ($chat['type'] ?? null) !== 'channel') {
            return $this->ok();
        }

        $telegramChannelId = $chat['id'] ?? null;

        if ($telegramChannelId === null || $newStatus === null) {
            return $this->ok();
        }

        // 3. Holatga qarab tegishli amalni bajaramiz.
        if ($newStatus === 'administrator') {
            $this->linkChannel((int) $fromId, (string) $telegramChannelId, $chat['title'] ?? '');
        } elseif (in_array($newStatus, ['left', 'kicked'], true)) {
            $this->unlinkChannel((string) $telegramChannelId);
        }

        // 'member', 'restricted' kabi boshqa holatlar bot uchun ma'noga ega emas.

        return $this->ok();
    }

    /**
     * Bot kanalga admin bo'ldi — kanalni egasiga bog'lab saqlaymiz.
     */
    protected function linkChannel(int $fromId, string $telegramChannelId, string $title): void
    {
        // Botni qo'shgan odam tizimimizda ro'yxatdan o'tgan bo'lishi shart
        // (oldin mini-app orqali kirgan/start bosgan bo'lsa mavjud bo'ladi).
        $user = User::where('telegram_id', $fromId)->first();

        if ($user === null) {
            // Notanish foydalanuvchi — kanalni hech kimga bog'lay olmaymiz.
            Log::warning('Telegram webhook: kanalni qo\'shgan foydalanuvchi bazada topilmadi.', [
                'from_id' => $fromId,
                'telegram_channel_id' => $telegramChannelId,
            ]);

            return;
        }

        // telegram_channel_id — globally unique. Kanal qayta qo'shilsa yoki nomi
        // o'zgarsa, eski yozuvni yangilaymiz; aks holda yangisini yaratamiz.
        Channel::updateOrCreate(
            ['telegram_channel_id' => $telegramChannelId],
            [
                'user_id' => $user->id,
                'channel_name' => $title,
            ],
        );
    }

    /**
     * Bot adminlikdan olib tashlandi yoki kanaldan haydaldi — kanalni o'chiramiz.
     */
    protected function unlinkChannel(string $telegramChannelId): void
    {
        Channel::where('telegram_channel_id', $telegramChannelId)->delete();
    }

    /**
     * Telegram kutadigan standart 200 OK javobi.
     */
    protected function ok(): JsonResponse
    {
        return response()->json(['ok' => true]);
    }
}
