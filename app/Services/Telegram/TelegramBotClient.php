<?php

namespace App\Services\Telegram;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class TelegramBotClient
{
    /**
     * Send a text message to a Telegram chat.
     *
     * @param  array<string, mixed>|null  $replyMarkup
     */
    public function sendMessage(string $chatId, string $text, ?array $replyMarkup = null, ?string $parseMode = null): array
    {
        $payload = [
            'chat_id' => $chatId,
            'text' => $text,
        ];

        if ($replyMarkup !== null) {
            $payload['reply_markup'] = $replyMarkup;
        }

        if ($parseMode !== null) {
            $payload['parse_mode'] = $parseMode;
        }

        return $this->request('sendMessage', $payload)->json();
    }

    /**
     * Acknowledge a Telegram inline keyboard callback.
     */
    public function answerCallbackQuery(string $callbackQueryId, ?string $text = null): void
    {
        $payload = [
            'callback_query_id' => $callbackQueryId,
        ];

        if ($text !== null) {
            $payload['text'] = $text;
        }

        $this->request('answerCallbackQuery', $payload);
    }

    /**
     * Replace a reminder message after attendance is recorded and remove its buttons.
     */
    public function editMessageText(string $chatId, int $messageId, string $text): void
    {
        $this->request('editMessageText', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'reply_markup' => ['inline_keyboard' => []],
        ]);
    }

    /**
     * Read the current Telegram webhook status.
     *
     * @return array<string, mixed>
     */
    public function getWebhookInfo(): array
    {
        return $this->request('getWebhookInfo', [])->json();
    }

    /**
     * Register the application webhook with Telegram.
     *
     * @return array<string, mixed>
     */
    public function setWebhook(string $url, string $secretToken): array
    {
        return $this->request('setWebhook', [
            'url' => $url,
            'secret_token' => $secretToken,
            'allowed_updates' => ['message', 'callback_query'],
        ])->json();
    }

    /**
     * Remove the current Telegram webhook.
     *
     * @return array<string, mixed>
     */
    public function deleteWebhook(bool $dropPendingUpdates = false): array
    {
        return $this->request('deleteWebhook', [
            'drop_pending_updates' => $dropPendingUpdates,
        ])->json();
    }

    /**
     * Send a request to the Telegram Bot API.
     *
     * @param  array<string, mixed>  $payload
     */
    private function request(string $method, array $payload): Response
    {
        $token = config('services.telegram.bot_token');

        if (! is_string($token) || $token === '') {
            throw new RuntimeException('The Telegram bot token is not configured.');
        }

        try {
            $response = Http::acceptJson()
                ->asJson()
                ->connectTimeout(3)
                ->timeout(10)
                ->post("https://api.telegram.org/bot{$token}/{$method}", $payload);
        } catch (Throwable) {
            throw new RuntimeException('The Telegram Bot API request failed.');
        }

        if (! $response->successful() || $response->json('ok') !== true) {
            throw new RuntimeException('The Telegram Bot API rejected the request.');
        }

        return $response;
    }
}
