<x-filament-panels::page>
    <div class="space-y-6">
        {{-- Webhook o'rnatiladigan manzil --}}
        <x-filament::section>
            <x-slot name="heading">Webhook manzili</x-slot>
            <x-slot name="description">
                "Webhook'ni o'rnatish" tugmasi bosilganda bot ayni shu manzilga ulanadi.
                Ushbu sahifani prod (HTTPS) domeningizda oching va tugmani bosing.
            </x-slot>

            <code class="text-sm break-all">{{ $this->getWebhookUrl() }}</code>
        </x-filament::section>

        {{-- Joriy holat (Telegram getWebhookInfo) --}}
        <x-filament::section>
            <x-slot name="heading">Joriy holat</x-slot>
            <x-slot name="description">Telegram serveridagi botning hozirgi webhook sozlamalari.</x-slot>

            @if (filled($webhookInfo))
                @php
                    // Bo'sh ro'yxat = Telegram default'i, unga my_chat_member KIRADI.
                    // Faqat ro'yxat to'ldirilgan-u, unda my_chat_member yo'q bo'lsa — ogohlantiramiz.
                    $allowed = $webhookInfo['allowed_updates'] ?? [];
                    $missingMyChatMember = filled($allowed) && ! in_array('my_chat_member', $allowed, true);
                @endphp

                <dl class="grid grid-cols-1 gap-y-4 sm:grid-cols-2">
                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">O'rnatilgan URL</dt>
                        <dd class="mt-1 text-sm break-all">
                            {{ filled($webhookInfo['url'] ?? null) ? $webhookInfo['url'] : '— (o\'rnatilmagan)' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Kutilayotgan yangilanishlar</dt>
                        <dd class="mt-1 text-sm">{{ $webhookInfo['pending_update_count'] ?? 0 }}</dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Ruxsat etilgan update'lar</dt>
                        <dd class="mt-1 text-sm">
                            {{ filled($webhookInfo['allowed_updates'] ?? null) ? implode(', ', $webhookInfo['allowed_updates']) : 'barchasi (default)' }}
                        </dd>
                    </div>

                    <div>
                        <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Oxirgi xato</dt>
                        <dd class="mt-1 text-sm">
                            @if (filled($webhookInfo['last_error_message'] ?? null))
                                <span class="text-danger-600 dark:text-danger-400">
                                    {{ $webhookInfo['last_error_message'] }}
                                </span>
                            @else
                                <span class="text-success-600 dark:text-success-400">Xatosiz</span>
                            @endif
                        </dd>
                    </div>
                </dl>

                @if ($missingMyChatMember)
                    <x-filament::badge color="warning" class="mt-4">
                        Diqqat: "my_chat_member" ruxsat etilgan update'lar ro'yxatida yo'q — kanal qo'shilishini kuzatish uchun webhook'ni qayta o'rnating.
                    </x-filament::badge>
                @endif
            @else
                <p class="text-sm text-gray-500 dark:text-gray-400">
                    Holatni olishning iloji bo'lmadi. Bot token sozlanganini tekshiring yoki "Holatni yangilash" tugmasini bosing.
                </p>
            @endif
        </x-filament::section>
    </div>
</x-filament-panels::page>
