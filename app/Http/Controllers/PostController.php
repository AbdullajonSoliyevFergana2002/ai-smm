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
            'image' => ['required', 'image', 'mimes:jpeg,jpg,png,webp', 'max:8192'], // maks. 8 MB
            'tone' => ['required', 'string', 'max:50'],
            'additional_info' => ['nullable', 'string', 'max:500'],
        ]);

        // 1. Rasmni storage/app/public/posts ga saqlaymiz va yangi yozuv yaratamiz.
        $imageFile = $request->file('image');
        $path = $imageFile->store('posts', 'public');

        $post = $request->user()->posts()->create([
            'image_path' => $path,
            'status' => 'pending',
        ]);

        // 2. Rasmni Base64 ga o'giramiz.
        $base64Image = base64_encode(Storage::disk('public')->get($path));
        $mimeType = $imageFile->getMimeType();

        // 3. tone asosida prompt tuzamiz.
        $tone = $validated['tone'];
        $prompt = "Ushbu rasmdagi mahsulotni tahlil qil. O'zbekiston bozori uchun {$tone} ohangda, "
            ."chiroyli va qisqa o'zbekcha marketing matni hamda hashtaglar yozib ber.";

        // Foydalanuvchi qo'shimcha izoh yozgan bo'lsa, promptga singdiramiz.
        $additionalInfo = trim($validated['additional_info'] ?? '');
        if ($additionalInfo !== '') {
            $prompt .= " Shuningdek, matnni tayyorlashda mana bu qo'shimcha ma'lumotlarni ham "
                ."hisobga ol va matnga singdir: {$additionalInfo}";
        }

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
