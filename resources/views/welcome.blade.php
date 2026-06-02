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

    {{-- HEIC (iPhone/Android) rasmlarini JPEG ga aylantirish uchun --}}
    <script src="https://cdn.jsdelivr.net/npm/heic2any@0.0.4/dist/heic2any.min.js"></script>

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

        /* Mobil qurilmalarda standart kulrang tap-effektni o'chiramiz (o'rniga o'zimizniki) */
        button, label, .tone-option { -webkit-tap-highlight-color: transparent; }

        .tg-btn {
            background-color: var(--tg-button);
            color: var(--tg-button-text);
            transition: filter .1s ease, transform .1s ease;
        }
        .tg-btn:disabled { opacity: .55; cursor: not-allowed; }

        /* Bosilgan holat — JS orqali .is-pressed klassi qo'shiladi
           (iOS/Telegram WebView'da :active ishonchsiz ishlagani uchun).
           :active ham fallback sifatida qoldirildi. */
        .tg-btn:not(:disabled).is-pressed,
        .tg-btn:not(:disabled):active {
            filter: brightness(0.75);
            transform: scale(0.96);
        }

        /* Ohang tugmalari */
        .tone-btn {
            text-align: center;
            border-radius: .75rem;
            padding: .5rem 0;
            font-size: .875rem;
            background-color: rgba(127, 127, 127, .15); /* tanlanmagan: to'q fon */
            color: var(--tg-text);
            border: 1px solid transparent;
            transition: background-color .2s ease, color .2s ease, filter .1s ease, transform .1s ease;
        }
        /* Tanlangan ohang: yorqin ko'k (theme button rangi) + oq matn */
        .tone-btn.tone-active {
            background-color: var(--tg-button);
            color: var(--tg-button-text);
            font-weight: 600;
        }
        .tone-btn.is-pressed,
        .tone-btn:active { filter: brightness(0.85); transform: scale(0.96); }

        /* Rasm yuklash maydoni */
        .upload-box { transition: filter .1s ease, transform .1s ease; }
        .upload-box.is-pressed,
        .upload-box:active { filter: brightness(0.92); transform: scale(0.99); }

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

            <label for="image" class="upload-box block cursor-pointer rounded-xl border-2 border-dashed border-gray-300/60 hover:border-[var(--tg-link)] transition p-6 text-center">
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
                    <button type="button" class="tone-btn" data-tone="{{ $value }}">{{ $label }}</button>
                @endforeach
            </div>
        </section>

        {{-- Qisqacha izoh (ixtiyoriy) --}}
        <section class="tg-card rounded-2xl p-4 space-y-2">
            <label for="additional-info" class="block text-sm font-medium">
                Qisqacha izoh <span class="tg-hint font-normal">(ixtiyoriy)</span>
            </label>
            <textarea id="additional-info" rows="2"
                placeholder="Masalan: 20% chegirma bor, faqat 3 kun yoki dostavka bepul..."
                class="w-full rounded-xl p-3 text-sm bg-[var(--tg-bg)] border border-gray-300/40 focus:outline-none focus:ring-2 focus:ring-[var(--tg-link)]"></textarea>
        </section>

        {{-- Matn yaratish tugmasi --}}
        <button id="generate-btn" class="tg-btn w-full rounded-xl py-3 font-semibold flex items-center justify-center gap-2">
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
            <button id="publish-btn" class="tg-btn w-full rounded-xl py-3 font-semibold flex items-center justify-center gap-2">
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
        const additionalInfo = document.getElementById('additional-info');
        const messageBox = document.getElementById('message');

        let currentPostId = null;

        // --- Ohang tanlash (default: "Sotuvchi") ---
        let selectedTone = 'sotuvchi';
        const toneButtons = document.querySelectorAll('.tone-btn');

        function updateToneUI() {
            toneButtons.forEach((btn) => {
                btn.classList.toggle('tone-active', btn.dataset.tone === selectedTone);
            });
        }

        toneButtons.forEach((btn) => {
            btn.addEventListener('click', () => {
                selectedTone = btn.dataset.tone;
                updateToneUI();
                if (tg?.HapticFeedback) tg.HapticFeedback.selectionChanged();
            });
        });

        updateToneUI(); // boshlang'ich holatda "Sotuvchi" yonib turadi

        // --- Rasm preview ---
        imageInput.addEventListener('change', () => {
            const file = imageInput.files[0];
            if (!file) return;

            // HEIC brauzerда ko'rsatilmaydi — tasdiq belgisini ko'rsatamiz.
            const isHeic = /heic|heif/i.test(file.type || '') || /\.hei[cf]$/i.test(file.name || '');
            if (isHeic) {
                placeholder.innerHTML = '<div class="text-3xl">✅</div>'
                    + '<div class="text-sm mt-1">Rasm tanlandi</div>';
                preview.classList.add('hidden');
                placeholder.classList.remove('hidden');
                return;
            }

            preview.src = URL.createObjectURL(file);
            preview.classList.remove('hidden');
            placeholder.classList.add('hidden');
            // Agar preview yuklanmasa (noma'lum format), tasdiq belgisiga qaytamiz.
            preview.onerror = () => {
                preview.classList.add('hidden');
                placeholder.innerHTML = '<div class="text-3xl">✅</div>'
                    + '<div class="text-sm mt-1">Rasm tanlandi</div>';
                placeholder.classList.remove('hidden');
            };
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

        // Javobni xavfsiz JSON qilib o'qish (413/HTML xatolarda ham yiqilmasligi uchun).
        async function parseJsonSafe(res) {
            try { return await res.json(); }
            catch (e) { return {}; }
        }

        // HEIC/HEIF ni JPEG ga aylantiradi (brauzer canvas HEIC ni o'qiy olmaydi).
        async function ensureDecodable(file) {
            const isHeic = /heic|heif/i.test(file.type || '') || /\.hei[cf]$/i.test(file.name || '');
            if (!isHeic) return file;
            if (!window.heic2any) {
                throw new Error('HEIC kutubxonasi yuklanmadi (internet?). Telefon kamerasini "Most Compatible/JPEG" ga o\'zgartiring.');
            }
            const out = await heic2any({ blob: file, toType: 'image/jpeg', quality: 0.9 });
            const blob = Array.isArray(out) ? out[0] : out;
            return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
        }

        // Rasmni yuborishdan oldin brauzerda kichraytirib, JPEG ga o'giradi.
        // Telefon rasmlari ko'pincha 5-12 MB bo'ladi va server limitidan oshib yuboradi.
        // HEIC (iPhone/Android) ham JPEG ga aylantiriladi.
        async function compressImage(file, maxSize = 1280, quality = 0.8) {
            // HEIC bo'lsa avval JPEG ga aylantiramiz (bu xato bersa, yuqoriga uzatiladi).
            const decodable = await ensureDecodable(file);
            try {
                const dataUrl = await new Promise((resolve, reject) => {
                    const reader = new FileReader();
                    reader.onload = () => resolve(reader.result);
                    reader.onerror = reject;
                    reader.readAsDataURL(decodable);
                });
                const img = await new Promise((resolve, reject) => {
                    const i = new Image();
                    i.onload = () => resolve(i);
                    i.onerror = reject;
                    i.src = dataUrl;
                });

                let width = img.naturalWidth;
                let height = img.naturalHeight;
                if (width > maxSize || height > maxSize) {
                    if (width > height) {
                        height = Math.round(height * maxSize / width);
                        width = maxSize;
                    } else {
                        width = Math.round(width * maxSize / height);
                        height = maxSize;
                    }
                }

                const canvas = document.createElement('canvas');
                canvas.width = width;
                canvas.height = height;
                canvas.getContext('2d').drawImage(img, 0, 0, width, height);

                const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
                if (!blob) return file;
                return new File([blob], 'photo.jpg', { type: 'image/jpeg' });
            } catch (e) {
                // Dekod bo'lmasa (masalan eski brauzer), asl faylni qaytaramiz.
                return file;
            }
        }

        // --- 1. Matn yaratish ---
        generateBtn.addEventListener('click', async () => {
            const original = imageInput.files[0];
            if (!original) {
                showMessage('Avval rasm tanlang.', true);
                return;
            }
            const tone = selectedTone;

            setLoading(generateBtn, true);
            messageBox.classList.add('hidden');

            const kb = (b) => Math.round(b / 1024) + ' KB';

            try {
                // DIAGNOSTIKA: kichraytirishdan oldin/keyin hajm.
                const file = await compressImage(original);
                showMessage('Yuborilmoqda… (' + kb(original.size) + ' → ' + kb(file.size) + ', ' + file.type + ')');

                const formData = new FormData();
                formData.append('image', file);
                formData.append('tone', tone);

                // Ixtiyoriy qisqacha izoh (bo'sh bo'lmasa yuboramiz).
                const info = additionalInfo.value.trim();
                if (info) {
                    formData.append('additional_info', info);
                }

                let res;
                try {
                    res = await fetch('/api/posts/generate', {
                        method: 'POST',
                        headers: tgHeaders(),     // FormData uchun Content-Type ni brauzer o'zi qo'yadi
                        body: formData,
                    });
                } catch (netErr) {
                    // fetch o'zi yiqildi — tarmoq/SSL/CORS muammosi.
                    showMessage('Tarmoq xatosi: ' + (netErr?.message || netErr)
                        + ' | URL: ' + location.origin, true);
                    return;
                }

                const data = await parseJsonSafe(res);

                if (!res.ok) {
                    if (res.status === 413) {
                        showMessage('Rasm server uchun katta (413). Hajm: ' + kb(file.size), true);
                    } else {
                        showMessage('Server xatosi (kod: ' + res.status + '): '
                            + (data.message || 'noma\'lum'), true);
                    }
                    return;
                }

                currentPostId = data.post.id;
                generatedText.value = data.post.generated_text;
                resultSection.classList.remove('hidden');
                resultSection.scrollIntoView({ behavior: 'smooth' });
                showMessage('Matn tayyor! Tahrirlab, kanalga joylashingiz mumkin.');
            } catch (e) {
                showMessage('Kutilmagan xato: ' + (e?.name || '') + ' ' + (e?.message || e), true);
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
                const data = await parseJsonSafe(res);

                if (!res.ok) {
                    showMessage(data.message || ('Yuborishda xatolik (kod: ' + res.status + ')'), true);
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

        // --- Bosilish effekti (iOS/Telegram WebView'da :active ishonchsiz, shuning uchun JS bilan) ---
        function bindPressEffect(selector) {
            document.querySelectorAll(selector).forEach((el) => {
                const press = () => el.classList.add('is-pressed');
                const release = () => el.classList.remove('is-pressed');
                el.addEventListener('pointerdown', press);
                el.addEventListener('pointerup', release);
                el.addEventListener('pointerleave', release);
                el.addEventListener('pointercancel', release);
                // Yengil tebranish (qo'llab-quvvatlasa) — bosilgani "his qilinsin"
                el.addEventListener('pointerdown', () => {
                    if (tg?.HapticFeedback) tg.HapticFeedback.impactOccurred('light');
                });
            });
        }
        bindPressEffect('.tg-btn');
        bindPressEffect('.tone-btn');
        bindPressEffect('.upload-box');
    </script>
</body>
</html>
