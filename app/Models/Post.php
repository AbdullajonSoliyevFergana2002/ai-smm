<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'category', 'mood', 'image_path', 'generated_text', 'status'])]
class Post extends Model
{
    /**
     * Jadvalda faqat created_at mavjud.
     */
    const UPDATED_AT = null;

    /**
     * Post egasi (foydalanuvchi).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
