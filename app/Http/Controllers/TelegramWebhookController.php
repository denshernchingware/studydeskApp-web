<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessTelegramUpdate;
use App\Models\TelegramUpdate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TelegramWebhookController extends Controller
{
    public function __invoke(Request $request): JsonResponse
    {
        $secret = config('services.telegram.webhook_secret');
        $providedSecret = $request->header('X-Telegram-Bot-Api-Secret-Token', '');

        if (! is_string($secret) || $secret === '' || ! hash_equals($secret, $providedSecret)) {
            return response()->json(['ok' => false], 403);
        }

        if (strlen($request->getContent()) > 65_536) {
            return response()->json(['ok' => false], 413);
        }

        $payload = $request->json()->all();
        $updateId = filter_var($payload['update_id'] ?? null, FILTER_VALIDATE_INT);

        if (! is_int($updateId) || $updateId < 0) {
            return response()->json(['ok' => false], 400);
        }

        $attributes = $this->updateAttributes($payload);

        if ($attributes === null) {
            return response()->json(['ok' => true]);
        }

        $update = TelegramUpdate::query()->firstOrCreate(
            ['update_id' => $updateId],
            $attributes,
        );

        if ($update->processed_at === null) {
            ProcessTelegramUpdate::dispatch((int) $update->getKey());
        }

        return response()->json(['ok' => true]);
    }

    /**
     * Extract only the fields currently needed from a Telegram update.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private function updateAttributes(array $payload): ?array
    {
        $message = $payload['message'] ?? null;

        if (is_array($message) && is_array($message['chat'] ?? null) && is_array($message['from'] ?? null)) {
            $chat = $message['chat'];
            $from = $message['from'];

            if (($chat['type'] ?? null) !== 'private' || ! isset($chat['id'], $from['id'])) {
                return null;
            }

            $text = $message['text'] ?? '';
            $tokenHash = null;
            $updateType = 'message';

            if (is_string($text) && preg_match('/^\/start(?:@[A-Za-z0-9_]+)?(?:\s+([A-Za-z0-9_-]{32,128}))?$/', trim($text), $matches) === 1) {
                $updateType = 'start';

                if (isset($matches[1])) {
                    $tokenHash = hash('sha256', $matches[1]);
                }
            }

            return [
                'update_type' => $updateType,
                'telegram_user_id' => (string) $from['id'],
                'chat_id' => (string) $chat['id'],
                'link_token_hash' => $tokenHash,
            ];
        }

        $callback = $payload['callback_query'] ?? null;

        if (is_array($callback) && is_array($callback['from'] ?? null)) {
            $chat = $callback['message']['chat'] ?? null;
            $callbackData = $callback['data'] ?? null;

            return [
                'update_type' => 'callback_query',
                'telegram_user_id' => isset($callback['from']['id']) ? (string) $callback['from']['id'] : null,
                'chat_id' => is_array($chat) && isset($chat['id']) ? (string) $chat['id'] : null,
                'callback_query_id' => isset($callback['id']) ? (string) $callback['id'] : null,
                'callback_token_hash' => is_string($callbackData) && strlen($callbackData) >= 1 && strlen($callbackData) <= 64
                    ? hash('sha256', $callbackData)
                    : null,
                'telegram_message_id' => is_array($callback['message'] ?? null) && isset($callback['message']['message_id'])
                    ? (string) $callback['message']['message_id']
                    : null,
            ];
        }

        return null;
    }
}
