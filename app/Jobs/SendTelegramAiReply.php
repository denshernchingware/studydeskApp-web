<?php

namespace App\Jobs;

use App\Enums\TelegramDeliveryStatus;
use App\Models\TelegramDelivery;
use App\Services\Telegram\TelegramBotClient;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
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

        $response = $telegramBotClient->sendMessage(
            $account->chat_id,
            $this->formatTelegramHtml($delivery->message_text),
            parseMode: 'HTML',
        );
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

    private function formatTelegramHtml(string $text): string
    {
        $formattedText = htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE | ENT_HTML5, 'UTF-8');
        $codeSegments = [];
        $formattedText = preg_replace_callback('/```[^\r\n]*\r?\n(.*?)```/s', function (array $matches) use (&$codeSegments): string {
            $placeholder = "\x1Acode".count($codeSegments)."\x1A";
            $codeSegments[$placeholder] = '<pre><code>'.trim($matches[1]).'</code></pre>';

            return $placeholder;
        }, $formattedText) ?? $formattedText;
        $formattedText = preg_replace_callback('/`([^`\r\n]+)`/', function (array $matches) use (&$codeSegments): string {
            $placeholder = "\x1Acode".count($codeSegments)."\x1A";
            $codeSegments[$placeholder] = '<code>'.$matches[1].'</code>';

            return $placeholder;
        }, $formattedText) ?? $formattedText;
        $formattedText = preg_replace('/\*\*([^*\r\n]+)\*\*/', '<b>$1</b>', $formattedText) ?? $formattedText;
        $formattedText = preg_replace('/(?<!\*)\*([^*\r\n]+)\*(?!\*)/', '<i>$1</i>', $formattedText) ?? $formattedText;
        $formattedText = preg_replace('/^#{1,6}\s+(.+)$/m', '<b>$1</b>', $formattedText) ?? $formattedText;
        $formattedText = preg_replace('/^[ \t]*[-*][ \t]+/m', '• ', $formattedText) ?? $formattedText;

        return strtr($formattedText, $codeSegments);
    }
}
