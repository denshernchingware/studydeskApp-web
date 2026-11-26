<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'telegram_user_id',
    'chat_id',
    'linked_at',
    'disabled_at',
    'reminders_enabled',
    'weekly_summary_enabled',
    'weekly_summary_day',
    'weekly_summary_time',
    'ai_conversation_id',
    'ai_conversation_semester_id',
])]
#[Hidden(['telegram_user_id', 'chat_id', 'ai_conversation_id'])]
class TelegramAccount extends Model
{
    /**
     * Get the owner of the linked Telegram account.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get notifications sent to this Telegram account.
     *
     * @return HasMany<TelegramDelivery, $this>
     */
    public function deliveries(): HasMany
    {
        return $this->hasMany(TelegramDelivery::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'linked_at' => 'datetime',
            'disabled_at' => 'datetime',
            'reminders_enabled' => 'boolean',
            'weekly_summary_enabled' => 'boolean',
            'weekly_summary_day' => 'integer',
        ];
    }
}
