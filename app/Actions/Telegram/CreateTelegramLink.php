<?php

namespace App\Actions\Telegram;

use App\Models\TelegramAccount;
use App\Models\TelegramLinkToken;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class CreateTelegramLink
{
    /**
     * Create a short-lived, single-use Telegram account link.
     */
    public function create(User $user): string
    {
        $botUsername = config('services.telegram.bot_username');
        $botToken = config('services.telegram.bot_token');
        $webhookSecret = config('services.telegram.webhook_secret');

        if (! is_string($botUsername) || ! preg_match('/^[A-Za-z0-9_]{5,32}$/', $botUsername)) {
            throw new RuntimeException('The Telegram bot username is not configured correctly.');
        }

        if (! is_string($botToken) || $botToken === '' || ! is_string($webhookSecret) || $webhookSecret === '') {
            throw new RuntimeException('Set the Telegram bot token and webhook secret before generating a link.');
        }

        if ($user->telegramAccount()->exists()) {
            throw new RuntimeException('A Telegram account is already linked.');
        }

        $plainTextToken = Str::random(64);

        DB::transaction(function () use ($plainTextToken, $user): void {
            TelegramLinkToken::query()
                ->where('user_id', $user->getKey())
                ->whereNull('redeemed_at')
                ->delete();

            TelegramLinkToken::query()->create([
                'user_id' => $user->getKey(),
                'token_hash' => hash('sha256', $plainTextToken),
                'expires_at' => now(config('app.timezone'))->addMinutes(10),
            ]);
        });

        return "https://t.me/{$botUsername}?start={$plainTextToken}";
    }

    /**
     * Redeem a token hash and attach the private Telegram identity to its owner.
     */
    public function redeem(string $tokenHash, string $telegramUserId, string $chatId): ?TelegramAccount
    {
        return DB::transaction(function () use ($chatId, $telegramUserId, $tokenHash): ?TelegramAccount {
            $linkToken = TelegramLinkToken::query()
                ->where('token_hash', $tokenHash)
                ->lockForUpdate()
                ->first();

            if (! $linkToken instanceof TelegramLinkToken) {
                return null;
            }

            $ownerId = (int) $linkToken->user_id;
            $linkedAccount = TelegramAccount::query()
                ->where('user_id', $ownerId)
                ->first();

            if ($linkToken->redeemed_at !== null) {
                return $linkedAccount instanceof TelegramAccount
                    && hash_equals($linkedAccount->telegram_user_id, $telegramUserId)
                    && hash_equals($linkedAccount->chat_id, $chatId)
                    ? $linkedAccount
                    : null;
            }

            if ($linkToken->expires_at->isPast()) {
                return null;
            }

            $identityInUse = TelegramAccount::query()
                ->where(function ($query) use ($chatId, $telegramUserId): void {
                    $query->where('telegram_user_id', $telegramUserId)
                        ->orWhere('chat_id', $chatId);
                })
                ->where('user_id', '!=', $ownerId)
                ->exists();

            if ($identityInUse) {
                return null;
            }

            $account = TelegramAccount::query()->updateOrCreate(
                ['user_id' => $ownerId],
                [
                    'telegram_user_id' => $telegramUserId,
                    'chat_id' => $chatId,
                    'linked_at' => now(config('app.timezone')),
                    'disabled_at' => null,
                ],
            );

            $linkToken->forceFill(['redeemed_at' => now(config('app.timezone'))])->save();

            return $account;
        });
    }
}
