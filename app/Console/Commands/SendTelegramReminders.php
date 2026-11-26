<?php

namespace App\Console\Commands;

use App\Actions\Telegram\DispatchDueTelegramReminders;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Signature('telegram:send-reminders {--date=} {--time=} {--dry-run}')]
#[Description('Queue Telegram subject reminders due in 15 minutes.')]
class SendTelegramReminders extends Command
{
    /**
     * Execute the console command.
     */
    public function handle(DispatchDueTelegramReminders $dispatchDueTelegramReminders): int
    {
        $dateOption = $this->option('date');
        $timeOption = $this->option('time');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && blank(config('services.telegram.bot_token'))) {
            $this->warn('Set TELEGRAM_BOT_TOKEN before sending Telegram reminders.');

            return self::SUCCESS;
        }

        if (! $dryRun && (filled($dateOption) || filled($timeOption))) {
            $this->error('Use --date and --time only with --dry-run.');

            return self::FAILURE;
        }

        $now = CarbonImmutable::now(config('app.timezone'));
        $date = filled($dateOption) ? (string) $dateOption : $now->toDateString();
        $time = filled($timeOption) ? (string) $timeOption : $now->format('H:i');

        try {
            Validator::make(
                ['date' => $date, 'time' => $time],
                ['date' => ['required', 'date_format:Y-m-d'], 'time' => ['required', 'date_format:H:i']],
            )->validate();
        } catch (ValidationException) {
            $this->error('Use --date=YYYY-MM-DD and --time=HH:MM.');

            return self::FAILURE;
        }

        $moment = CarbonImmutable::parse("{$date} {$time}", config('app.timezone'));
        $results = $dispatchDueTelegramReminders->dispatchDueAt($moment, $dryRun);

        if ($results === []) {
            $this->info($dryRun ? 'No reminders would be due at that time.' : 'No Telegram reminders are due now.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $prefix = $dryRun
                ? 'Would send: '
                : ($result['queued'] ? 'Queued: ' : 'Already queued: ');

            $this->line($prefix.$result['label']);
        }

        return self::SUCCESS;
    }
}
