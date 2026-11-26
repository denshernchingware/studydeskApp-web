<?php

namespace App\Actions\Telegram;

use App\Enums\TelegramDeliveryStatus;
use App\Jobs\SendTelegramWeeklySummary;
use App\Models\Semester;
use App\Models\TelegramAccount;
use App\Models\TelegramDelivery;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class DispatchDueTelegramWeeklySummaries
{
    /**
     * Queue due reports using the configured weekday and local time.
     *
     * @return array<int, array{user_id: int, semester: string, week_start: string, queued: bool}>
     */
    public function dispatchDueAt(CarbonImmutable $moment): array
    {
        $localMoment = $moment->setTimezone((string) config('app.timezone'))->startOfMinute();
        $weekStart = $localMoment->startOfWeek(CarbonImmutable::MONDAY)->subWeek();
        $results = [];

        TelegramAccount::query()
            ->whereNull('disabled_at')
            ->where('weekly_summary_enabled', true)
            ->where('weekly_summary_day', $localMoment->dayOfWeek)
            ->whereTime('weekly_summary_time', $localMoment->format('H:i:s'))
            ->with('user')
            ->orderBy('id')
            ->chunkById(100, function ($accounts) use ($weekStart, &$results): void {
                foreach ($accounts as $account) {
                    $user = $account->user;

                    if (! $user instanceof User || ! is_numeric($user->active_semester_id)) {
                        continue;
                    }

                    $semester = $user->semesters()->whereKey((int) $user->active_semester_id)->first();

                    if (! $semester instanceof Semester) {
                        continue;
                    }

                    $result = $this->queueSummary($user, $account, $semester, $weekStart);
                    $results[] = [
                        'user_id' => (int) $user->getKey(),
                        'semester' => $semester->name,
                        'week_start' => $weekStart->toDateString(),
                        'queued' => $result,
                    ];
                }
            });

        return $results;
    }

    private function queueSummary(User $user, TelegramAccount $account, Semester $semester, CarbonImmutable $weekStart): bool
    {
        $summary = app(BuildWeeklyTelegramSummary::class)->build($user, $semester, $weekStart);
        $dedupeKey = sprintf('weekly-summary:%s:%s:%s', $user->getKey(), $semester->getKey(), $weekStart->toDateString());

        $deliveryId = DB::transaction(function () use ($account, $dedupeKey, $semester, $summary, $user): ?int {
            $delivery = TelegramDelivery::query()->firstOrCreate(
                ['dedupe_key' => $dedupeKey],
                [
                    'user_id' => $user->getKey(),
                    'telegram_account_id' => $account->getKey(),
                    'semester_id' => $semester->getKey(),
                    'notification_type' => 'weekly_summary',
                    'message_text' => $summary['message'],
                    'status' => TelegramDeliveryStatus::Pending,
                    'attempts' => 0,
                ],
            );

            if ((int) $delivery->user_id !== (int) $user->getKey()
                || (int) $delivery->telegram_account_id !== (int) $account->getKey()) {
                return null;
            }

            return $delivery->status === TelegramDeliveryStatus::Pending
                ? (int) $delivery->getKey()
                : null;
        });

        if ($deliveryId === null) {
            return false;
        }

        SendTelegramWeeklySummary::dispatch($deliveryId);

        return true;
    }
}
