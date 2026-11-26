<?php

namespace App\Console\Commands;

use App\Services\Telegram\TelegramBotClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use RuntimeException;

#[Signature('telegram:webhook {action=info : info, set, or delete} {--url=} {--drop-pending-updates}')]
#[Description('Inspect, register, or remove the Telegram webhook.')]
class TelegramWebhook extends Command
{
    public function handle(TelegramBotClient $telegramBotClient): int
    {
        if (blank(config('services.telegram.bot_token'))) {
            $this->error('Set TELEGRAM_BOT_TOKEN before managing the Telegram webhook.');

            return self::FAILURE;
        }

        $action = (string) $this->argument('action');

        try {
            return match ($action) {
                'info' => $this->showWebhookInfo($telegramBotClient),
                'set' => $this->setWebhook($telegramBotClient),
                'delete' => $this->deleteWebhook($telegramBotClient),
                default => $this->invalidAction(),
            };
        } catch (RuntimeException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
    }

    private function showWebhookInfo(TelegramBotClient $telegramBotClient): int
    {
        $info = $telegramBotClient->getWebhookInfo();
        $url = data_get($info, 'result.url');
        $host = is_string($url) ? parse_url($url, PHP_URL_HOST) : null;

        $this->line('Webhook: '.(filled($url) ? 'configured' : 'not configured'));
        $this->line('Host: '.(is_string($host) ? $host : 'none'));
        $this->line('Pending updates: '.(int) data_get($info, 'result.pending_update_count', 0));
        $this->line('Custom certificate: '.(data_get($info, 'result.has_custom_certificate') ? 'yes' : 'no'));

        $lastErrorDate = data_get($info, 'result.last_error_date');

        if (is_numeric($lastErrorDate)) {
            $this->line('Last webhook error time: '.now()->setTimestamp((int) $lastErrorDate)->toDateTimeString().' (application timezone)');
        }

        return self::SUCCESS;
    }

    private function setWebhook(TelegramBotClient $telegramBotClient): int
    {
        $url = $this->option('url');
        $secretToken = config('services.telegram.webhook_secret');
        $urlParts = is_string($url) ? parse_url($url) : false;

        if (! is_string($url)
            || filter_var($url, FILTER_VALIDATE_URL) === false
            || ! is_array($urlParts)
            || strtolower((string) ($urlParts['scheme'] ?? '')) !== 'https'
            || ! isset($urlParts['host'])
            || isset($urlParts['user'])
            || isset($urlParts['pass'])) {
            $this->error('Use --url with a public HTTPS webhook URL without embedded credentials.');

            return self::FAILURE;
        }

        if (! is_string($secretToken) || preg_match('/^[A-Za-z0-9_-]{1,256}$/', $secretToken) !== 1) {
            $this->error('Set TELEGRAM_WEBHOOK_SECRET to 1-256 letters, numbers, underscores, or hyphens.');

            return self::FAILURE;
        }

        $telegramBotClient->setWebhook($url, $secretToken);
        $this->info('Telegram webhook registered for host '.strtolower($urlParts['host']).'.');

        return self::SUCCESS;
    }

    private function deleteWebhook(TelegramBotClient $telegramBotClient): int
    {
        $dropPendingUpdates = (bool) $this->option('drop-pending-updates');
        $telegramBotClient->deleteWebhook($dropPendingUpdates);
        $this->info('Telegram webhook removed.'.($dropPendingUpdates ? ' Pending updates were dropped.' : ' Pending updates were retained.'));

        return self::SUCCESS;
    }

    private function invalidAction(): int
    {
        $this->error('Action must be info, set, or delete.');

        return self::FAILURE;
    }
}
