<?php

namespace App\Actions\Telegram;

use App\Enums\AttendanceStatus;
use App\Enums\TelegramDeliveryStatus;
use App\Jobs\SendTelegramReminder;
use App\Models\TelegramAttendanceAction;
use App\Models\TelegramDelivery;
use App\Models\TimetableSlot;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DispatchDueTelegramReminders
{
    /**
     * Find sessions starting in 15 minutes and queue each reminder once.
     *
     * @return array<int, array{label: string, queued: bool}>
     */
    public function dispatchDueAt(CarbonImmutable $moment, bool $dryRun = false): array
    {
        $reminderMinute = $moment->startOfMinute();
        $scheduledStart = $reminderMinute->addMinutes(15);
        $sessionDate = $scheduledStart->toDateString();

        $slots = TimetableSlot::query()
            ->select('timetable_slots.*')
            ->join('users', 'users.id', '=', 'timetable_slots.user_id')
            ->join('subjects', 'subjects.id', '=', 'timetable_slots.subject_id')
            ->join('semesters', 'semesters.id', '=', 'subjects.semester_id')
            ->whereColumn('users.id', 'timetable_slots.user_id')
            ->whereColumn('subjects.user_id', 'timetable_slots.user_id')
            ->whereColumn('users.active_semester_id', 'subjects.semester_id')
            ->where('users.study_start_date', '<=', $sessionDate)
            ->where('semesters.starts_on', '<=', $sessionDate)
            ->where('semesters.ends_on', '>=', $sessionDate)
            ->whereDate('timetable_slots.effective_from', '<=', $sessionDate)
            ->where('timetable_slots.weekday', $scheduledStart->dayOfWeek)
            ->whereTime('timetable_slots.start_time', '=', $scheduledStart->format('H:i:s'))
            ->whereHas('user.telegramAccount', fn (Builder $query) => $query
                ->whereNull('disabled_at')
                ->where('reminders_enabled', true))
            ->with(['user.telegramAccount', 'subject'])
            ->orderBy('timetable_slots.start_time')
            ->get();

        $results = [];

        foreach ($slots as $slot) {
            $account = $slot->user?->telegramAccount;

            if ($account === null || $slot->subject === null) {
                continue;
            }

            $subjectName = $slot->subject->name;
            $label = sprintf(
                '%s - %s at %s',
                $sessionDate,
                $subjectName,
                $scheduledStart->format('g:i A'),
            );

            if ($dryRun) {
                $results[] = ['label' => $label, 'queued' => false];

                continue;
            }

            $deliveryId = DB::transaction(function () use ($account, $scheduledStart, $sessionDate, $slot): ?int {
                $delivery = TelegramDelivery::query()->firstOrCreate(
                    [
                        'dedupe_key' => sprintf(
                            'reminder:%s:%s:%s',
                            $account->user_id,
                            $slot->getKey(),
                            $sessionDate,
                        ),
                    ],
                    [
                        'user_id' => $account->user_id,
                        'telegram_account_id' => $account->getKey(),
                        'timetable_slot_id' => $slot->getKey(),
                        'session_date' => $sessionDate,
                        'notification_type' => 'subject_reminder',
                        'status' => 'pending',
                        'attempts' => 0,
                    ],
                );

                if (! $delivery->wasRecentlyCreated) {
                    return $delivery->status === TelegramDeliveryStatus::Pending
                        ? (int) $delivery->getKey()
                        : null;
                }

                foreach ([AttendanceStatus::Present, AttendanceStatus::Absent] as $status) {
                    $token = Str::random(40);

                    TelegramAttendanceAction::query()->create([
                        'telegram_delivery_id' => $delivery->getKey(),
                        'user_id' => $account->user_id,
                        'timetable_slot_id' => $slot->getKey(),
                        'status' => $status->value,
                        'token_hash' => hash('sha256', $token),
                        'token' => $token,
                        'expires_at' => $scheduledStart->addHours(36),
                    ]);
                }

                return (int) $delivery->getKey();
            });

            if ($deliveryId !== null) {
                SendTelegramReminder::dispatch($deliveryId);
            }

            $results[] = ['label' => $label, 'queued' => $deliveryId !== null];
        }

        return $results;
    }
}
