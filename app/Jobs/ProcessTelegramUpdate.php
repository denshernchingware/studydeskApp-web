<?php

namespace App\Jobs;

use App\Models\TelegramUpdate;
use App\Services\Telegram\TelegramUpdateProcessor;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessTelegramUpdate implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $uniqueFor = 300;

    public int $tries = 3;

    public function __construct(public readonly int $telegramUpdateId) {}

    public function uniqueId(): string
    {
        return (string) $this->telegramUpdateId;
    }

    /**
     * @return array<int, int>
     */
    public function backoff(): array
    {
        return [10, 30];
    }

    public function handle(TelegramUpdateProcessor $processor): void
    {
        $update = TelegramUpdate::query()->find($this->telegramUpdateId);

        if (! $update instanceof TelegramUpdate || $update->processed_at !== null) {
            return;
        }

        $processor->process($update);
    }
}
