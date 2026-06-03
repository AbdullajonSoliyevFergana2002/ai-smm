<?php

namespace App\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use UnitEnum;

/**
 * Telegram bot webhook'ini boshqaradigan admin sahifasi.
 *
 * Bu sahifa orqali admin bir tugma bosib, botning webhook'ini AYNAN shu
 * sahifa ochilgan domen (prod) orqali Telegram'da ro'yxatdan o'tkazadi.
 * Shuningdek joriy webhook holatini ko'rsatadi va uni o'chirish imkonini beradi.
 */
class TelegramSettings extends Page
{
    protected static string | BackedEnum | null $navigationIcon = 'heroicon-o-paper-airplane';

    protected static string | UnitEnum | null $navigationGroup = 'Sozlamalar';

    protected static ?string $navigationLabel = 'Telegram webhook';

    protected static ?string $title = 'Telegram bot webhook';

    protected string $view = 'filament.pages.telegram-settings';

    /**
     * Telegram'dan olingan joriy webhook ma'lumotlari (getWebhookInfo natijasi).
     *
     * @var array<string, mixed>|null
     */
    public ?array $webhookInfo = null;

    /**
     * Sahifa ochilganda joriy webhook holatini yuklaymiz.
     */
    public function mount(): void
    {
        $this->refreshWebhookInfo();
    }

    /**
     * Webhook yuboriladigan to'liq URL — joriy so'rov domenidan quriladi.
     *
     * Shu sababli sahifani prod domenida ochib tugmani bossangiz, webhook
     * aynan o'sha domenga o'rnatiladi (lokalda — lokal manzilga).
     */
    public function getWebhookUrl(): string
    {
        return url('/api/telegram/webhook');
    }

    /**
     * Sahifadagi header tugmalari.
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('setWebhook')
                ->label('Webhook\'ni o\'rnatish')
                ->icon('heroicon-o-link')
                ->color('primary')
                ->requiresConfirmation()
                ->modalHeading('Webhook\'ni o\'rnatish')
                ->modalDescription(fn (): string => 'Webhook quyidagi manzilga o\'rnatiladi: '.$this->getWebhookUrl())
                ->action(fn () => $this->setWebhook()),

            Action::make('refresh')
                ->label('Holatni yangilash')
                ->icon('heroicon-o-arrow-path')
                ->color('gray')
                ->action(fn () => $this->refreshWebhookInfo()),

            Action::make('deleteWebhook')
                ->label('Webhook\'ni o\'chirish')
                ->icon('heroicon-o-trash')
                ->color('danger')
                ->requiresConfirmation()
                ->action(fn () => $this->deleteWebhook()),
        ];
    }

    /**
     * Botning webhook'ini joriy domen orqali Telegram'da ro'yxatdan o'tkazadi.
     */
    public function setWebhook(): void
    {
        $token = config('services.telegram.bot_token');
        $secret = config('services.telegram.webhook_secret');
        $url = $this->getWebhookUrl();

        // 1. Bot token sozlangan bo'lishi shart.
        if (empty($token)) {
            $this->notifyDanger('Bot token sozlanmagan', 'TELEGRAM_BOT_TOKEN ni .env faylга qo\'shing.');

            return;
        }

        // 2. Maxfiy token sozlanmagan bo'lsa, webhook himoyasiz qoladi (middleware uni talab qiladi).
        if (empty($secret)) {
            $this->notifyDanger(
                'Maxfiy token sozlanmagan',
                'TELEGRAM_WEBHOOK_SECRET ni .env faylga qo\'shing, aks holda webhook so\'rovlari rad etiladi.',
            );

            return;
        }

        // 3. Telegram faqat HTTPS manzilni qabul qiladi — lokal http manzilni bloklaymiz.
        if (! str_starts_with($url, 'https://')) {
            $this->notifyDanger(
                'HTTPS talab qilinadi',
                'Telegram webhook uchun HTTPS manzil kerak. Bu tugmani prod (HTTPS) domenida bosing. Joriy manzil: '.$url,
            );

            return;
        }

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/setWebhook", [
                'url' => $url,
                'secret_token' => $secret,
                // my_chat_member default holda yuborilmaydi — uni ALBATTA aniq so'raymiz.
                'allowed_updates' => ['my_chat_member', 'message'],
                'drop_pending_updates' => true,
            ]);
        } catch (\Throwable $e) {
            Log::error('setWebhook so\'rovi muvaffaqiyatsiz', ['error' => $e->getMessage()]);
            $this->notifyDanger('Tarmoq xatosi', $e->getMessage());

            return;
        }

        if ($response->failed() || $response->json('ok') !== true) {
            Log::error('Telegram setWebhook xatosi', ['body' => $response->body()]);
            $this->notifyDanger('Webhook o\'rnatilmadi', (string) $response->json('description', 'Noma\'lum xato.'));

            return;
        }

        Notification::make()
            ->title('Webhook muvaffaqiyatli o\'rnatildi')
            ->body($url)
            ->success()
            ->send();

        $this->refreshWebhookInfo();
    }

    /**
     * Botning webhook'ini o'chiradi (deleteWebhook).
     */
    public function deleteWebhook(): void
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            $this->notifyDanger('Bot token sozlanmagan', 'TELEGRAM_BOT_TOKEN ni .env faylga qo\'shing.');

            return;
        }

        try {
            $response = Http::timeout(15)->post("https://api.telegram.org/bot{$token}/deleteWebhook", [
                'drop_pending_updates' => false,
            ]);
        } catch (\Throwable $e) {
            $this->notifyDanger('Tarmoq xatosi', $e->getMessage());

            return;
        }

        if ($response->failed() || $response->json('ok') !== true) {
            $this->notifyDanger('Webhook o\'chirilmadi', (string) $response->json('description', 'Noma\'lum xato.'));

            return;
        }

        Notification::make()->title('Webhook o\'chirildi')->success()->send();

        $this->refreshWebhookInfo();
    }

    /**
     * Telegram'dan joriy webhook holatini oladi va sahifaga yuklaydi.
     */
    public function refreshWebhookInfo(): void
    {
        $token = config('services.telegram.bot_token');

        if (empty($token)) {
            $this->webhookInfo = null;

            return;
        }

        try {
            $response = Http::timeout(10)->get("https://api.telegram.org/bot{$token}/getWebhookInfo");
            $this->webhookInfo = $response->json('ok') === true ? $response->json('result') : null;
        } catch (\Throwable $e) {
            // Tarmoq xatosi sahifani buzmasin — shunchaki holat noma'lum bo'lib qoladi.
            $this->webhookInfo = null;
        }
    }

    /**
     * Qisqa "xato" bildirishnomasi.
     */
    protected function notifyDanger(string $title, string $body): void
    {
        Notification::make()->title($title)->body($body)->danger()->send();
    }
}
