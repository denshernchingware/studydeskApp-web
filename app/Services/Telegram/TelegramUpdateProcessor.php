<?php

namespace App\Services\Telegram;

use App\Actions\Telegram\CreateTelegramLink;
use App\Actions\Telegram\MarkTelegramAttendance;
use App\Jobs\ProcessTelegramAiMessage;
use App\Models\TelegramAccount;
use App\Models\TelegramUpdate;

class TelegramUpdateProcessor
{
    /**
     * Process a stored Telegram update.
     */
    public function __construct(
        private readonly CreateTelegramLink $createTelegramLink,
        private readonly TelegramBotClient $telegramBotClient,
        private readonly MarkTelegramAttendance $markTelegramAttendance,
    ) {}

    public function process(TelegramUpdate $update): void
    {
        if ($update->processed_at !== null) {
            return;
        }

        if ($update->update_type === 'callback_query') {
            $result = $this->markTelegramAttendance->handle($update);
            $update->forceFill(['processed_at' => now()])->save();

            try {
                if (filled($update->callback_query_id)) {
                    $this->telegramBotClient->answerCallbackQuery(
                        $update->callback_query_id,
                        $result['callback_text'],
                    );
                }
            } finally {
                if (filled($result['message_text']) && filled($update->telegram_message_id)) {
                    $messageId = filter_var($update->telegram_message_id, FILTER_VALIDATE_INT);

                    if (is_int($messageId)) {
                        $this->telegramBotClient->editMessageText(
                            (string) $update->chat_id,
                            $messageId,
                            $result['message_text'],
                        );
                    }
                }
            }

            return;
        }

        if ($update->update_type === 'start' && filled($update->link_token_hash)) {
            $account = $this->createTelegramLink->redeem(
                $update->link_token_hash,
                (string) $update->telegram_user_id,
                (string) $update->chat_id,
            );

            $this->telegramBotClient->sendMessage(
                (string) $update->chat_id,
                $account instanceof TelegramAccount
                    ? 'Your private Telegram chat is now connected to StudyDesk.'
                    : 'That StudyDesk link is invalid, expired, or already used. Generate a new link in StudyDesk settings.',
            );

            $update->forceFill(['processed_at' => now()])->save();

            return;
        }

        if ($update->update_type === 'start' && filled($update->chat_id)) {
            $this->telegramBotClient->sendMessage(
                $update->chat_id,
                'Open Telegram settings in StudyDesk and generate a new link to connect your account.',
            );
        }

        if ($update->update_type === 'oversized_message' && filled($update->chat_id)) {
            $this->telegramBotClient->sendMessage(
                $update->chat_id,
                'Please keep study-advisor messages under 2,000 characters.',
            );
        }

        if ($update->update_type === 'unsupported_message' && filled($update->chat_id)) {
            $this->telegramBotClient->sendMessage(
                $update->chat_id,
                'The study advisor can answer text questions. Please send a text message.',
            );
        }

        if ($update->update_type === 'message' && filled($update->message_text)) {
            $account = TelegramAccount::query()
                ->where('telegram_user_id', $update->telegram_user_id)
                ->where('chat_id', $update->chat_id)
                ->whereNull('disabled_at')
                ->first();

            if ($account instanceof TelegramAccount) {
                ProcessTelegramAiMessage::dispatch((int) $update->getKey());
            } elseif (filled($update->chat_id)) {
                $this->telegramBotClient->sendMessage(
                    $update->chat_id,
                    'Connect this private chat from StudyDesk Telegram settings before asking the study advisor.',
                );
            }
        }

        $update->forceFill(['processed_at' => now()])->save();
    }
}
