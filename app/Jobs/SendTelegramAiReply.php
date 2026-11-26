<?php

namespace App\Jobs;

use App\Enums\TelegramDeliveryStatus;
use App\Models\TelegramDelivery;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use RuntimeException;
use Throwable;

class SendTelegramAiReply implements ShouldBeUnique, ShouldQueue
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
            ->with(['telegramAccount', 'user'])
            ->find($this->telegramDeliveryId);

        if (! $delivery instanceof TelegramDelivery || $delivery->status !== TelegramDeliveryStatus::Pending) {
            return;
        }

        $account = $delivery->telegramAccount;

        if ($account === null
            || $account->disabled_at !== null
            || blank($account->chat_id)
            || (int) $account->user_id !== (int) $delivery->user_id
            || $delivery->notification_type !== 'ai_response'
            || blank($delivery->message_text)) {
            $delivery->forceFill([
                'status' => TelegramDeliveryStatus::Failed,
                'last_error' => 'The Telegram account or AI response is no longer available.',
            ])->save();

            return;
        }

        $delivery->forceFill([
            'status' => TelegramDeliveryStatus::Sending,
            'attempts' => $delivery->attempts + 1,
        ])->save();

        $response = $telegramBotClient->sendMessage($account->chat_id, $delivery->message_text);
        $messageId = data_get($response, 'result.message_id');

        if (! is_int($messageId) && ! is_string($messageId)) {
            throw new RuntimeException('Telegram did not return a message ID.');
        }

        $delivery->forceFill([
            'status' => TelegramDeliveryStatus::Sent,
            'telegram_message_id' => (string) $messageId,
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
