<?php

namespace App\Jobs;

use App\Enums\TelegramDeliveryStatus;
use App\Models\Semester;
use App\Models\TelegramDelivery;
use App\Models\User;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

class SendTelegramWeeklySummary implements ShouldBeUnique, ShouldQueue
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
            ->with(['telegramAccount', 'user', 'semester'])
            ->find($this->telegramDeliveryId);

        if (! $delivery instanceof TelegramDelivery || $delivery->status !== TelegramDeliveryStatus::Pending) {
            return;
        }

        $account = $delivery->telegramAccount;
        $user = $delivery->user;
        $semester = $delivery->semester;

        if ($account === null
            || ! $user instanceof User
            || ! $semester instanceof Semester
            || $account->disabled_at !== null
            || ! $account->weekly_summary_enabled
            || blank($account->chat_id)
            || (int) $account->user_id !== (int) $delivery->user_id
            || (int) $user->getKey() !== (int) $delivery->user_id
            || (int) $semester->user_id !== (int) $delivery->user_id
            || $delivery->notification_type !== 'weekly_summary'
            || blank($delivery->message_text)) {
            $delivery->forceFill([
                'status' => TelegramDeliveryStatus::Failed,
                'last_error' => 'The Telegram account or weekly summary is no longer available.',
            ])->save();

            return;
        }

        $delivery->forceFill([
            'status' => TelegramDeliveryStatus::Sending,
            'attempts' => $delivery->attempts + 1,
        ])->save();

        $response = $telegramBotClient->sendMessage($account->chat_id, $delivery->message_text);
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
        $delivery = TelegramDelivery::query()->select(['id', 'user_id', 'notification_type'])->find($this->telegramDeliveryId);

        Log::warning('Telegram notification delivery failed.', [
            'delivery_id' => $this->telegramDeliveryId,
            'user_id' => $delivery?->user_id,
            'notification_type' => $delivery?->notification_type,
            'exception_type' => $exception === null ? null : $exception::class,
        ]);

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
