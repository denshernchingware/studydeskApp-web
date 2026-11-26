<?php

namespace App\Actions\Telegram;

use App\Actions\SaveAttendanceRecord;
use App\Enums\AttendanceStatus;
use App\Enums\TelegramDeliveryStatus;
use App\Models\TelegramAccount;
use App\Models\TelegramAttendanceAction;
use App\Models\TelegramUpdate;
use Carbon\CarbonImmutable;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MarkTelegramAttendance
{
    public function __construct(private readonly SaveAttendanceRecord $saveAttendanceRecord) {}

    /**
     * Validate a Telegram callback and set attendance for its linked owner.
     *
     * @return array{callback_text: string, message_text: string|null}
     */
    public function handle(TelegramUpdate $update): array
    {
        $invalidAction = [
            'callback_text' => 'This attendance action is invalid or expired.',
            'message_text' => null,
        ];

        if (blank($update->callback_token_hash) || blank($update->telegram_user_id) || blank($update->chat_id)) {
            return $invalidAction;
        }

        $account = TelegramAccount::query()
            ->where('telegram_user_id', $update->telegram_user_id)
            ->where('chat_id', $update->chat_id)
            ->whereNull('disabled_at')
            ->first();

        $action = TelegramAttendanceAction::query()
            ->with(['telegramDelivery', 'timetableSlot.subject'])
            ->where('token_hash', $update->callback_token_hash)
            ->first();

        if ($account === null
            || $action === null
            || (int) $action->user_id !== (int) $account->user_id
            || $action->expires_at->isPast()) {
            return $invalidAction;
        }

        $slot = $action->timetableSlot;
        $subject = $slot?->subject;
        $delivery = $action->telegramDelivery;
        $status = AttendanceStatus::tryFrom($action->status);
        $user = $account->user;

        if ($slot === null || $subject === null || $delivery === null || $status === null || $user === null
            || $delivery->status !== TelegramDeliveryStatus::Sent
            || $delivery->session_date === null
            || (int) $delivery->user_id !== (int) $action->user_id
            || (int) $delivery->telegram_account_id !== (int) $account->getKey()
            || (int) $delivery->timetable_slot_id !== (int) $slot->getKey()
            || (int) $action->timetable_slot_id !== (int) $slot->getKey()
            || ! in_array($delivery->status, [TelegramDeliveryStatus::Sending, TelegramDeliveryStatus::Sent], true)
            || ! filled($update->telegram_message_id)
            || ($delivery->status === TelegramDeliveryStatus::Sent
                && (! filled($delivery->telegram_message_id)
                    || ! hash_equals($delivery->telegram_message_id, $update->telegram_message_id)))) {
            return $invalidAction;
        }

        try {
            $this->saveAttendanceRecord->set(
                $user,
                (int) $slot->getKey(),
                (int) $subject->semester_id,
                $delivery->session_date->toDateString(),
                $status,
            );
        } catch (ValidationException $exception) {
            $fieldErrors = $exception->errors();
            $firstFieldErrors = reset($fieldErrors);
            $message = is_array($firstFieldErrors) ? (string) reset($firstFieldErrors) : 'Attendance could not be saved.';

            return [
                'callback_text' => Str::limit($message, 180),
                'message_text' => null,
            ];
        }

        $statusLabel = $status->name;

        return [
            'callback_text' => "Attendance marked {$statusLabel}.",
            'message_text' => sprintf(
                'Attendance recorded: %s - %s (%s at %s).',
                $subject->name,
                $statusLabel,
                $delivery->session_date->toDateString(),
                CarbonImmutable::parse(
                    $delivery->session_date->toDateString().' '.$slot->start_time,
                    config('app.timezone'),
                )->format('g:i A'),
            ),
        ];
    }
}
