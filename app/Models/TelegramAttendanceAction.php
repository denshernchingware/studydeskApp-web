<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable([
    'telegram_delivery_id',
    'user_id',
    'timetable_slot_id',
    'status',
    'token_hash',
    'token',
    'expires_at',
])]
#[Hidden(['token', 'token_hash'])]
class TelegramAttendanceAction extends Model
{
    /**
     * Get the reminder delivery containing this action.
     *
     * @return BelongsTo<TelegramDelivery, $this>
     */
    public function telegramDelivery(): BelongsTo
    {
        return $this->belongsTo(TelegramDelivery::class);
    }

    /**
     * Get the owner authorized to use this action.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the scheduled session for this action.
     *
     * @return BelongsTo<TimetableSlot, $this>
     */
    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'token' => 'encrypted',
            'expires_at' => 'datetime',
        ];
    }
}
