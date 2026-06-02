<!DOCTYPE html>
<html lang="uz">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>AI-SMM</title>

    {{-- 1. TailwindCSS CDN --}}
    <script src="https://cdn.tailwindcss.com"></script>

    {{-- 2. Telegram WebApp SDK --}}
    <script src="https://telegram.org/js/telegram-web-app.js"></script>

    <style>
        /* 3. Telegram mavzusiga (ThemeParams) moslashish uchun CSS o'zgaruvchilari */
        :root {
            --tg-bg: #ffffff;
            --tg-text: #000000;
            --tg-hint: #707579;
            --tg-link: #2481cc;
            --tg-button: #2481cc;
            --tg-button-text: #ffffff;
            --tg-secondary-bg: #f1f1f1;
        }
        body {
            background-color: var(--tg-bg);
            color: var(--tg-text);
        }
        .tg-card { background-color: var(--tg-secondary-bg); }
        .tg-hint { color: var(--tg-hint); }
        .tg-btn {
            background-color: var(--tg-button);
            color: var(--tg-button-text);
        }
        .tg-btn:disabled { opacity: .55; cursor: not-allowed; }

        /* Loading spinner */
        .spinner {
            width: 1.1rem; height: 1.1rem;
            border: 2px solid currentColor;
            border-top-color: transparent;
            border-radius: 9999px;
            animation: spin .7s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body class="min-h-screen">
    <main class="max-w-md mx-auto p-4 space-y-5">

        <header class="text-center pt-2">
            <h1 class="text-2xl font-bold">🪄 AI-SMM</h1>
            <p class="tg-hint text-sm mt-1">Mahsulot rasmidan marketing matni yarating</p>
        </header>

        {{-- Rasm yuklash maydoni --}}
        <section class="tg-card rounded-2xl p-4 space-y-3">
            <label class="block text-sm font-medium">Mahsulot rasmi</label>

            <label for="image" class="block cursor-pointer rounded-xl border-2 border-dashed border-gray-300/60 hover:border-[var(--tg-link)] transition p-6 text-center">
                <div id="upload-placeholder" class="space-y-1">
                    <div class="text-3xl">📷</div>
                    <div class="tg-hint text-sm">Rasm tanlash uchun bosing</div>
                </div>
                <img id="preview" class="hidden mx-auto max-h-56 rounded-xl object-contain" alt="Tanlangan rasm">
                <input id="image" type="file" accept="image/*" class="hidden">
            </label>
        </section>

        {{-- Ohang tanlash --}}
        <section class="tg-card rounded-2xl p-4 space-y-3">
            <label class="block text-sm font-medium">Matn ohangi</label>
            <div class="grid grid-cols-3 gap-2" id="tone-group">
                @foreach (['sotuvchi' => 'Sotuvchi', 'quvnoq' => 'Quvnoq', 'rasmiy' => 'Rasmiy'] as $value => $label)
                    <label class="tone-option cursor-pointer">
                        <input type="radio" name="tone" value="{{ $value }}" class="peer hidden" @checked($loop->first)>
                        <span class="block text-center text-sm rounded-xl py-2 border border-transparent bg-black/5 peer-checked:tg-btn peer-checked:font-semibold transition">
                            {{ $label }}
                        </span>
                    </label>
                @endforeach
            </div>
        </section>

        {{-- Matn yaratish tugmasi --}}
        <button id="generate-btn" class="tg-btn w-full rounded-xl py-3 font-semibold flex items-center justify-center gap-2 transition active:scale-95">
            <span id="generate-spinner" class="spinner hidden"></span>
            <span id="generate-label">✨ Matn yaratish</span>
        </button>

        {{-- Natija (boshida yashirin) --}}
        <section id="result-section" class="hidden tg-card rounded-2xl p-4 space-y-3">
            <label class="block text-sm font-medium">Yaratilgan matn <span class="tg-hint font-normal">(tahrirlash mumkin)</span></label>
            <textarea id="generated-text" rows="7"
                class="w-full rounded-xl p-3 text-sm bg-[var(--tg-bg)] border border-gray-300/40 focus:outline-none focus:ring-2 focus:ring-[var(--tg-link)]"></textarea>

            <label class="block text-sm font-medium pt-1">Kanal ID</label>
            <input id="channel-id" type="text" placeholder="@kanal_username yoki -100..."
                class="w-full rounded-xl p-3 text-sm bg-[var(--tg-bg)] border border-gray-300/40 focus:outline-none focus:ring-2 focus:ring-[var(--tg-link)]">

            {{-- Kanalga joylash tugmasi --}}
            <button id="publish-btn" class="tg-btn w-full rounded-xl py-3 font-semibold flex items-center justify-center gap-2 transition active:scale-95">
                <span id="publish-spinner" class="spinner hidden"></span>
                <span id="publish-label">🚀 Kanalga joylash</span>
            </button>
        </section>

        {{-- Xabar (toast) --}}
        <div id="message" class="hidden text-center text-sm rounded-xl p-3"></div>
    </main>

    <script>
        const tg = window.Telegram?.WebApp;

        // --- Telegram init va mavzuga moslashish ---
        function applyTheme() {
            if (!tg) return;
            const p = tg.themeParams || {};
            const root = document.documentElement.style;
            const map = {
                '--tg-bg': p.bg_color,
                '--tg-text': p.text_color,
                '--tg-hint': p.hint_color,
                '--tg-link': p.link_color,
                '--tg-button': p.button_color,
                '--tg-button-text': p.button_text_color,
                '--tg-secondary-bg': p.secondary_bg_color,
            };
            for (const [key, val] of Object.entries(map)) {
                if (val) root.setProperty(key, val);
            }
        }

        if (tg) {
            tg.ready();
            tg.expand();
            applyTheme();
            tg.onEvent('themeChanged', applyTheme);
        }

        // --- Elementlar ---
        const imageInput = document.getElementById('image');
        const preview = document.getElementById('preview');
        const placeholder = document.getElementById('upload-placeholder');
        const generateBtn = document.getElementById('generate-btn');
        const publishBtn = document.getElementById('publish-btn');
        const resultSection = document.getElementById('result-section');
        const generatedText = document.getElementById('generated-text');
        const channelIdInput = document.getElementById('channel-id');
        const messageBox = document.getElementById('message');

        let currentPostId = null;

        // --- Rasm preview ---
        imageInput.addEventListener('change', () => {
            const file = imageInput.files[0];
            if (!file) return;
            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
        });

        // --- Yordamchi funksiyalar ---
        function showMessage(text, isError = false) {
            messageBox.textContent = text;
            messageBox.className = 'text-center text-sm rounded-xl p-3 ' +
                (isError ? 'bg-red-500/15 text-red-500' : 'bg-green-500/15 text-green-600');
            messageBox.classList.remove('hidden');
        }

        function setLoading(btn, isLoading) {
            const spinner = btn.querySelector('.spinner');
            btn.disabled = isLoading;
            spinner.classList.toggle('hidden', !isLoading);
        }

        // Barcha so'rovlarga X-Telegram-Init-Data header qo'shamiz.
        function tgHeaders(extra = {}) {
            return {
                'X-Telegram-Init-Data': tg?.initData ?? '',
                'Accept': 'application/json',
                ...extra,
            };
        }

        // --- 1. Matn yaratish ---
        generateBtn.addEventListener('click', async () => {
            const file = imageInput.files[0];
            if (!file) {
                showMessage('Avval rasm tanlang.', true);
                return;
            }
            const tone = document.querySelector('input[name="tone"]:checked').value;

            const formData = new FormData();
            formData.append('image', file);
            formData.append('tone', tone);

            setLoading(generateBtn, true);
            messageBox.classList.add('hidden');
            try {
                const res = await fetch('/api/posts/generate', {
                    method: 'POST',
                    headers: tgHeaders(),     // FormData uchun Content-Type ni brauzer o'zi qo'yadi
                    body: formData,
                });
                const data = await res.json();

                if (!res.ok) {
                    showMessage(data.message || 'Xatolik yuz berdi.', true);
                    return;
                }

                currentPostId = data.post.id;
                generatedText.value = data.post.generated_text;
                resultSection.classList.remove('hidden');
                resultSection.scrollIntoView({ behavior: 'smooth' });
                showMessage('Matn tayyor! Tahrirlab, kanalga joylashingiz mumkin.');
            } catch (e) {
                showMessage('Tarmoq xatosi. Qayta urinib ko\'ring.', true);
            } finally {
                setLoading(generateBtn, false);
            }
        });

        // --- 2. Kanalga joylash ---
        publishBtn.addEventListener('click', async () => {
            const channelId = channelIdInput.value.trim();
            if (!channelId) {
                showMessage('Kanal ID kiriting.', true);
                return;
            }

            setLoading(publishBtn, true);
            messageBox.classList.add('hidden');
            try {
                const res = await fetch('/api/posts/publish', {
                    method: 'POST',
                    headers: tgHeaders({ 'Content-Type': 'application/json' }),
                    body: JSON.stringify({
                        post_id: currentPostId,
                        telegram_channel_id: channelId,
                        // Tahrirlangan matnni ham yuboramiz (backend qo'llab-quvvatlasa, ishlatadi).
                        generated_text: generatedText.value,
                    }),
                });
                const data = await res.json();

                if (!res.ok) {
                    showMessage(data.message || 'Yuborishda xatolik.', true);
                    return;
                }

                showMessage('✅ Post kanalga muvaffaqiyatli joylandi!');
                if (tg?.HapticFeedback) tg.HapticFeedback.notificationOccurred('success');
            } catch (e) {
                showMessage('Tarmoq xatosi. Qayta urinib ko\'ring.', true);
            } finally {
                setLoading(publishBtn, false);
            }
        });
    </script>
</body>
</html>
