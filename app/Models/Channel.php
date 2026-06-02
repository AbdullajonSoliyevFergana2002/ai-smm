<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'telegram_channel_id', 'channel_name'])]
class Channel extends Model
{
    /**
     * Jadvalda faqat created_at mavjud.
     */
    const UPDATED_AT = null;

    /**
     * Kanal egasi (foydalanuvchi).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
