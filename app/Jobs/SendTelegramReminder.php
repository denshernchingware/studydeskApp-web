<?php

namespace App\Jobs;

use App\Enums\TelegramDeliveryStatus;
use App\Models\TelegramDelivery;
use App\Services\Telegram\TelegramBotClient;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class SendTelegramReminder implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 900;

    public int $tries = 1;

    public function __construct(public readonly int $telegramDeliveryId) {}

    public function uniqueId(): string
    {
        return (string) $this->telegramDeliveryId;
    }

    public function handle(TelegramBotClient $telegramBotClient): void
    {
        $delivery = TelegramDelivery::query()
            ->with(['telegramAccount', 'timetableSlot.subject', 'attendanceActions'])
            ->find($this->telegramDeliveryId);

        if (! $delivery instanceof TelegramDelivery || $delivery->status !== TelegramDeliveryStatus::Pending) {
            return;
        }

        $account = $delivery->telegramAccount;
        $slot = $delivery->timetableSlot;
        $subject = $slot?->subject;
        $actions = $delivery->attendanceActions->keyBy('status');

        if ($account === null || $account->disabled_at !== null || ! $account->reminders_enabled
            || $slot === null || $subject === null
            || $delivery->session_date === null
            || (int) $delivery->user_id !== (int) $account->user_id
            || (int) $slot->user_id !== (int) $delivery->user_id
            || (int) $subject->user_id !== (int) $delivery->user_id
            || ! $actions->has('present') || ! $actions->has('absent')) {
            $delivery->forceFill([
                'status' => TelegramDeliveryStatus::Failed,
                'last_error' => 'The Telegram account or scheduled session is no longer available.',
            ])->save();

            return;
        }

        $scheduledStart = CarbonImmutable::parse(
            $delivery->session_date->toDateString().' '.$slot->start_time,
            config('app.timezone'),
        );

        $delivery->forceFill([
            'status' => TelegramDeliveryStatus::Sending,
            'attempts' => $delivery->attempts + 1,
        ])->save();

        $message = sprintf(
            '%s starts in 15 minutes at %s.',
            $subject->name.($subject->code ? " ({$subject->code})" : ''),
            $scheduledStart->format('g:i A'),
        );

        if (filled($slot->label)) {
            $message .= "\n{$slot->label}";
        }

        $message .= "\nAttendance buttons work from the scheduled start time.";

        $response = $telegramBotClient->sendMessage(
            $account->chat_id,
            $message,
            [
                'inline_keyboard' => [[
                    ['text' => 'Present', 'callback_data' => $actions->get('present')->token],
                    ['text' => 'Absent', 'callback_data' => $actions->get('absent')->token],
                ]],
            ],
        );

        $telegramMessageId = data_get($response, 'result.message_id');

        if (! is_int($telegramMessageId) && ! is_string($telegramMessageId)) {
            throw new RuntimeException('Telegram did not return a message ID.');
        }

        $delivery->forceFill([
            'status' => TelegramDeliveryStatus::Sent,
            'telegram_message_id' => (string) $telegramMessageId,
            'sent_at' => now(config('app.timezone')),
            'last_error' => null,
        ])->save();
    }

    public function failed(?Throwable $exception): void
    {
        TelegramDelivery::query()
            ->whereKey($this->telegramDeliveryId)
            ->where('status', '!=', TelegramDeliveryStatus::Sent->value)
            ->update([
                'status' => TelegramDeliveryStatus::Failed->value,
                'last_error' => 'Telegram delivery failed; inspect the queue failure without exposing credentials.',
                'updated_at' => now(),
            ]);
    }
}
