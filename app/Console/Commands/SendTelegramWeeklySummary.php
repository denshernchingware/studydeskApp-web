<?php

namespace App\Console\Commands;

use App\Actions\Telegram\BuildWeeklyTelegramSummary as BuildWeeklySummary;
use App\Actions\Telegram\DispatchDueTelegramWeeklySummaries;
use App\Models\Semester;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

#[Signature('telegram:send-weekly-summary {--week-start=} {--user=} {--dry-run}')]
#[Description('Preview a weekly Telegram summary or queue summaries due now.')]
class SendTelegramWeeklySummary extends Command
{
    public function handle(BuildWeeklySummary $buildWeeklySummary, DispatchDueTelegramWeeklySummaries $dispatchDueTelegramWeeklySummaries): int
    {
        $weekStartOption = $this->option('week-start');
        $userOption = $this->option('user');
        $dryRun = (bool) $this->option('dry-run');

        if (! $dryRun && (filled($weekStartOption) || filled($userOption))) {
            $this->error('Use --week-start and --user only with --dry-run.');

            return self::FAILURE;
        }

        if ($dryRun) {
            return $this->preview($buildWeeklySummary, $weekStartOption, $userOption);
        }

        if (blank(config('services.telegram.bot_token'))) {
            $this->warn('Set TELEGRAM_BOT_TOKEN before queuing Telegram weekly summaries.');

            return self::SUCCESS;
        }

        $results = $dispatchDueTelegramWeeklySummaries->dispatchDueAt(CarbonImmutable::now(config('app.timezone')));

        if ($results === []) {
            $this->info('No Telegram weekly summaries are due now.');

            return self::SUCCESS;
        }

        foreach ($results as $result) {
            $prefix = $result['queued'] ? 'Queued: ' : 'Already queued: ';
            $this->line($sprintf('%suser %d | %s | week of %s', $prefix, $result['user_id'], $result['semester'], $result['week_start']));
        }

        return self::SUCCESS;
    }

    private function preview(BuildWeeklySummary $buildWeeklySummary, mixed $weekStartOption, mixed $userOption): int
    {
        $timezone = (string) config('app.timezone');
        $currentWeekStart = CarbonImmutable::now($timezone)->startOfWeek(CarbonImmutable::MONDAY);
        $weekStartText = filled($weekStartOption)
            ? (string) $weekStartOption
            : $currentWeekStart->subWeek()->toDateString();

        try {
            Validator::make(
                ['week_start' => $weekStartText, 'user' => $userOption],
                [
                    'week_start' => ['required', 'date_format:Y-m-d'],
                    'user' => ['nullable', 'integer', 'min:1'],
                ],
            )->validate();
        } catch (ValidationException) {
            $this->error('Use --week-start=YYYY-MM-DD and an optional numeric --user=<id>.');

            return self::FAILURE;
        }

        $weekStart = CarbonImmutable::parse($weekStartText, $timezone)->startOfDay();

        if ($weekStart->dayOfWeek !== CarbonImmutable::MONDAY || $weekStart->gt($currentWeekStart)) {
            $this->error('The week start must be a Monday no later than the current week.');

            return self::FAILURE;
        }

        $users = User::query()
            ->whereNotNull('active_semester_id')
            ->when(filled($userOption), fn ($query) => $query->whereKey((int) $userOption))
            ->orderBy('id')
            ->get();

        if ($users->isEmpty()) {
            $this->info('No matching user with an active semester was found.');

            return self::SUCCESS;
        }

        foreach ($users as $user) {
            $semester = $user->semesters()->whereKey((int) $user->active_semester_id)->first();

            if (! $semester instanceof Semester) {
                $this->line(sprintf('User %d has no owner-matched active semester.', $user->getKey()));

                continue;
            }

            $summary = $buildWeeklySummary->build($user, $semester, $weekStart);
            $this->line(sprintf('Preview for user %d | %s', $user->getKey(), $semester->name));
            $this->line($summary['message']);
            $this->newLine();
        }

        return self::SUCCESS;
    }
}
