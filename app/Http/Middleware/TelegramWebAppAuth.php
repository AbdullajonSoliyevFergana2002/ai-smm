<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Telegram WebApp'dan kelgan so'rovlarni autentifikatsiya qiladi.
 *
 * Frontend `window.Telegram.WebApp.initData` qatorini yuboradi. Bu middleware
 * uni Telegram rasmiy hujjatlaridagi algoritm bo'yicha (HMAC-SHA-256) tekshiradi,
 * so'ng telegram_id orqali foydalanuvchini topadi (yo'q bo'lsa yaratadi) va tizimga kiritadi.
 *
 * @see https://core.telegram.org/bots/webapps#validating-data-received-via-the-mini-app
 */
class TelegramWebAppAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $initData = $this->extractInitData($request);

        if ($initData === null || $initData === '') {
            return $this->unauthorized('initData topilmadi.');
        }

        $botToken = config('services.telegram.bot_token');

        if (empty($botToken)) {
            // Sozlama xatosi — server tomonidagi muammo.
            return response()->json(['message' => 'Telegram bot token sozlanmagan.'], 500);
        }

        $data = $this->validate($initData, $botToken);

        if ($data === null) {
            return $this->unauthorized('initData validatsiyadan o\'tmadi.');
        }

        // user maydoni JSON ko'rinishida keladi.
        $telegramUser = json_decode($data['user'] ?? '', true);

        if (! is_array($telegramUser) || empty($telegramUser['id'])) {
            return $this->unauthorized('Foydalanuvchi ma\'lumotlari topilmadi.');
        }

        $user = User::firstOrCreate(
            ['telegram_id' => $telegramUser['id']],
            [
                'username' => $telegramUser['username'] ?? null,
                'first_name' => $telegramUser['first_name'] ?? null,
            ],
        );

        Auth::login($user);

        return $next($request);
    }

    /**
     * So'rovdan initData qatorini ajratib oladi.
     *
     * Frontend uni header (`X-Telegram-Init-Data`) yoki body/query (`initData`) orqali yuborishi mumkin.
     */
    protected function extractInitData(Request $request): ?string
    {
        return $request->header('X-Telegram-Init-Data')
            ?? $request->input('initData');
    }

    /**
     * initData ni HMAC-SHA-256 orqali tekshiradi.
     *
     * @return array<string, string>|null Tekshiruvdan o'tgan ma'lumotlar yoki null (xato bo'lsa).
     */
    protected function validate(string $initData, string $botToken): ?array
    {
        $data = $this->parse($initData);

        if (! isset($data['hash'])) {
            return null;
        }

        $providedHash = $data['hash'];
        unset($data['hash']);

        // data_check_string: kalitlar alifbo bo'yicha tartiblanib, "key=value" lar "\n" bilan birlashtiriladi.
        ksort($data);
        $pairs = [];
        foreach ($data as $key => $value) {
            $pairs[] = $key.'='.$value;
        }
        $dataCheckString = implode("\n", $pairs);

        // secret_key = HMAC_SHA256(bot_token, "WebAppData")  — kalit "WebAppData", xabar bot_token.
        $secretKey = hash_hmac('sha256', $botToken, 'WebAppData', true);

        // Yakuniy hash = HMAC_SHA256(data_check_string, secret_key) hex ko'rinishda.
        $calculatedHash = hash_hmac('sha256', $dataCheckString, $secretKey);

        // Timing-safe taqqoslash.
        if (! hash_equals($calculatedHash, $providedHash)) {
            return null;
        }

        // Eskirgan initData ni rad etish (replay hujumiga qarshi).
        $ttl = (int) config('services.telegram.auth_ttl', 0);
        if ($ttl > 0 && isset($data['auth_date'])) {
            if ((time() - (int) $data['auth_date']) > $ttl) {
                return null;
            }
        }

        return $data;
    }

    /**
     * initData query-string'ini assotsiativ massivga aylantiradi.
     *
     * parse_str() kalitlardagi "." va " " belgilarini "_" ga almashtirib yuborgani uchun
     * qo'lda parse qilamiz — bu hash hisoblashning aniqligini saqlaydi.
     *
     * @return array<string, string>
     */
    protected function parse(string $initData): array
    {
        $result = [];

        foreach (explode('&', $initData) as $pair) {
            if ($pair === '') {
                continue;
            }

            $parts = explode('=', $pair, 2);
            $key = urldecode($parts[0]);
            $result[$key] = isset($parts[1]) ? urldecode($parts[1]) : '';
        }

        return $result;
    }

    /**
     * 401 javobini qaytaradi.
     */
    protected function unauthorized(string $message): Response
    {
        return response()->json(['message' => $message], 401);
    }
}
