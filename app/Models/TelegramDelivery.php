<?php

namespace App\Models;

use App\Enums\TelegramDeliveryStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'user_id',
    'telegram_account_id',
    'timetable_slot_id',
    'semester_id',
    'session_date',
    'notification_type',
    'message_text',
    'dedupe_key',
    'status',
    'attempts',
    'telegram_message_id',
    'last_error',
    'sent_at',
])]
#[Hidden(['message_text'])]
class TelegramDelivery extends Model
{
    /**
     * Get the owner of the delivery.
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the destination Telegram account.
     *
     * @return BelongsTo<TelegramAccount, $this>
     */
    public function telegramAccount(): BelongsTo
    {
        return $this->belongsTo(TelegramAccount::class);
    }

    /**
     * Get the timetable slot for a session reminder.
     *
     * @return BelongsTo<TimetableSlot, $this>
     */
    public function timetableSlot(): BelongsTo
    {
        return $this->belongsTo(TimetableSlot::class);
    }

    /**
     * Get the semester summarized by a weekly report.
     *
     * @return BelongsTo<Semester, $this>
     */
    public function semester(): BelongsTo
    {
        return $this->belongsTo(Semester::class);
    }

    /**
     * Get the attendance buttons attached to the delivery.
     *
     * @return HasMany<TelegramAttendanceAction, $this>
     */
    public function attendanceActions(): HasMany
    {
        return $this->hasMany(TelegramAttendanceAction::class);
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'session_date' => 'date',
            'status' => TelegramDeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }
}
