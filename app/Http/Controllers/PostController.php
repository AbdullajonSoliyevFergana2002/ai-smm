<?php

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class PostController extends Controller
{
    /**
     * Rasmni qabul qiladi, saqlaydi va Gemini AI orqali marketing matni yaratadi.
     */
    public function generate(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:51200'],     // to'liq sifatli (kanal), maks. 50 MB
            'ai_image' => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'],   // Gemini uchun kichik nusxa
            'category' => ['required', 'string', 'max:50'],
            'mood' => ['required', 'string', 'max:80'],
            'additional_info' => ['nullable', 'string', 'max:500'],
        ]);

        $imageFile = $request->file('image');

        // 1. Gemini tahlili uchun manba: kichik nusxa bo'lsa o'shani, aks holda asl rasmni ishlatamiz.
        //    store() vaqtinchalik faylni KO'CHIRADI, shuning uchun base64 ni saqlashdan OLDIN o'qiymiz.
        $geminiSource = $request->file('ai_image') ?? $imageFile;
        $base64Image = base64_encode(file_get_contents($geminiSource->getRealPath()));
        $mimeType = $geminiSource->getMimeType();

        // 2. To'liq sifatli rasmni saqlaymiz — kanalga AYNAN shu yuboriladi (sifat buzilmaydi).
        $path = $imageFile->store('posts', 'public');

        $post = $request->user()->posts()->create([
            'category' => $validated['category'],
            'mood' => $validated['mood'],
            'image_path' => $path,
            'status' => 'pending',
        ]);

        // 3. Universal prompt tuzamiz (kategoriya + kayfiyat + kontekst asosida).
        //    category — kalit (masalan: commerce), uni o'qiladigan nomга aylantiramiz.
        $categoryNames = [
            'commerce' => 'Savdo / Bozor',
            'travel' => 'Sayohat / Blog',
            'education' => 'Kurslar / Ta\'lim',
            'food' => 'Kafe / Food',
        ];
        $category = $categoryNames[$validated['category']] ?? $validated['category'];
        $mood = $validated['mood'];

        // Foydalanuvchi kontekst yozgan bo'lsa — alohida ko'rsatma, aks holda bo'sh.
        $additionalInfo = trim($validated['additional_info'] ?? '');
        $additionalInfoPrompt = $additionalInfo !== ''
            ? "Matnni yozishda mana bu foydalanuvchi taqdim etgan real kontekst va ma'lumotlarni "
                ."albatta inobatga oling va matnga singdiring: [FOYDALANUVCHI MATNI: {$additionalInfo}]"
            : '';

        $prompt = "Siz ijtimoiy tarmoqlar (Instagram, Telegram) uchun professional kontent meykersiz. "
            ."Berilgan rasmni diqqat bilan tahlil qiling. Ushbu rasm uchun '{$category}' yo'nalishida va "
            ."'{$mood}' kayfiyatida (ohangida) chiroyli, jozibador, imlo xatolarisiz o'zbek tilida "
            ."sotsial tarmoq posti yozib bering. Matnda mavzuga mos emojilar va trenddagi hashtaglar bo'lsin. "
            .$additionalInfoPrompt;

        // 4. Gemini API'ga so'rov yuboramiz.
        $endpoint = config('services.gemini.endpoint');
        $model = config('services.gemini.model');
        $apiKey = config('services.gemini.api_key');

        $response = Http::timeout(60)
            ->withQueryParameters(['key' => $apiKey])
            ->post("{$endpoint}/{$model}:generateContent", [
                'contents' => [
                    [
                        'parts' => [
                            ['text' => $prompt],
                            [
                                'inline_data' => [
                                    'mime_type' => $mimeType,
                                    'data' => $base64Image,
                                ],
                            ],
                        ],
                    ],
                ],
            ]);

        if ($response->failed()) {
            // API xatosi — postni pending holatida qoldiramiz, xatoni log qilamiz.
            Log::error('Gemini API xatosi', [
                'post_id' => $post->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'Matn yaratishda xatolik yuz berdi. Keyinroq qayta urinib ko\'ring.',
                'post_id' => $post->id,
            ], 502);
        }

        $generatedText = $response->json('candidates.0.content.parts.0.text');

        if (empty($generatedText)) {
            Log::warning('Gemini API bo\'sh javob qaytardi', [
                'post_id' => $post->id,
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'AI matn qaytarmadi. Boshqa rasm bilan urinib ko\'ring.',
                'post_id' => $post->id,
            ], 422);
        }

        // 5. Natijani saqlaymiz va frontend'ga qaytaramiz.
        $post->update([
            'generated_text' => $generatedText,
            'status' => 'generated',
        ]);

        return response()->json([
            'message' => 'Marketing matni muvaffaqiyatli yaratildi.',
            'post' => [
                'id' => $post->id,
                'image_url' => Storage::disk('public')->url($path),
                'generated_text' => $generatedText,
                'status' => $post->status,
            ],
        ]);
    }

    /**
     * Tayyorlangan postni Telegram kanalga yuboradi.
     */
    public function publish(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'post_id' => ['required', 'integer'],
            'telegram_channel_id' => ['required', 'string'],
        ]);

        // Faqat o'ziga tegishli postni topamiz (boshqaning postini chop eta olmasin).
        $post = $request->user()->posts()->findOrFail($validated['post_id']);

        if (empty($post->generated_text)) {
            return response()->json([
                'message' => 'Bu post uchun matn hali yaratilmagan.',
            ], 422);
        }

        if (! Storage::disk('public')->exists($post->image_path)) {
            return response()->json([
                'message' => 'Post rasmi topilmadi.',
            ], 422);
        }

        $token = config('services.telegram.bot_token');

        // Telegram caption uzunligi 1024 belgidan oshmasligi kerak (multibyte-safe kesish).
        $caption = mb_substr($post->generated_text, 0, 1024);

        // Rasmni faylning o'zi sifatida (multipart) yuboramiz — bu lokal/ichki serverlarda ham ishlaydi.
        $response = Http::timeout(30)
            ->attach(
                'photo',
                Storage::disk('public')->get($post->image_path),
                basename($post->image_path),
            )
            ->post("https://api.telegram.org/bot{$token}/sendPhoto", [
                'chat_id' => $validated['telegram_channel_id'],
                'caption' => $caption,
            ]);

        // Telegram xato bo'lsa ham HTTP 200 qaytarishi mumkin — shuning uchun "ok" maydonini tekshiramiz.
        if ($response->failed() || $response->json('ok') !== true) {
            Log::error('Telegram sendPhoto xatosi', [
                'post_id' => $post->id,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return response()->json([
                'message' => 'Postni kanalga yuborishda xatolik yuz berdi.',
                'telegram_error' => $response->json('description'),
            ], 502);
        }

        // Muvaffaqiyatli — statusni yangilaymiz.
        $post->update(['status' => 'published']);

        return response()->json([
            'message' => 'Post kanalga muvaffaqiyatli yuborildi.',
            'post' => [
                'id' => $post->id,
                'status' => $post->status,
                'telegram_message_id' => $response->json('result.message_id'),
            ],
        ]);
    }
}
