<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     *
     * Bir Telegram kanali tizimda faqat bitta yozuvga ega bo'lishi kerak.
     * Bu indeks updateOrCreate'ni ishonchli (race-condition'siz) qiladi va
     * dublikat kanallarni bazaviy darajada bloklaydi.
     */
    public function up(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->unique('telegram_channel_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('channels', function (Blueprint $table) {
            $table->dropUnique(['telegram_channel_id']);
        });
    }
};
