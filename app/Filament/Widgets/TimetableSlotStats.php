<?php

namespace App\Filament\Widgets;

use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Livewire\Attributes\Reactive;

class TimetableSlotStats extends StatsOverviewWidget
{
    protected static bool $isDiscovered = false;

    protected ?string $heading = 'Weekly timetable summary';

    /**
     * @var array<string, mixed> | null
     */
    #[Reactive]
    public ?array $tableFilters = null;

    public function updatedTableFilters(): void
    {
        $this->cachedStats = null;
    }

    protected function getStats(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return $this->emptyStats();
        }

        $selectedSemesterId = data_get($this->tableFilters, 'semester.value');
        $semesterId = filled($selectedSemesterId)
            ? $selectedSemesterId
            : $user->active_semester_id;

        if ($semesterId === null) {
            return $this->emptyStats();
        }

        $semester = $user->semesters()->whereKey($semesterId)->first();

        if ($semester === null) {
            return $this->emptyStats();
        }

        $slots = TimetableSlot::query()
            ->where('user_id', $user->getKey())
            ->whereHas('subject', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('semester_id', $semester->getKey()));

        $slotCount = (clone $slots)->count();
        $subjectCount = (clone $slots)->distinct()->count('subject_id');
        $weeklyMinutes = (clone $slots)
            ->get(['start_time', 'end_time'])
            ->sum(function (TimetableSlot $slot): int {
                $startTime = CarbonImmutable::parse($slot->start_time);
                $endTime = CarbonImmutable::parse($slot->end_time);

                return intdiv($endTime->getTimestamp() - $startTime->getTimestamp(), 60);
            });

        return [
            Stat::make('Weekly slots', $slotCount)
                ->description($semester->name),
            Stat::make('Subjects scheduled', $subjectCount)
                ->description($semester->name),
            Stat::make('Weekly hours', number_format($weeklyMinutes / 60, 1).' hrs')
                ->description($semester->name),
        ];
    }

    /**
     * @return array<Stat>
     */
    private function emptyStats(): array
    {
        return [
            Stat::make('Weekly slots', 0),
            Stat::make('Subjects scheduled', 0),
            Stat::make('Weekly hours', '0.0 hrs'),
        ];
    }
}
