<?php

namespace App\Filament\Widgets;

use App\Actions\AttendanceStatistics;
use App\Filament\Widgets\Concerns\InteractsWithDashboardData;
use App\Models\Semester;
use App\Models\User;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;

class DashboardAttendanceStats extends StatsOverviewWidget
{
    use InteractsWithDashboardData;

    protected ?string $heading = 'Attendance overview';

    protected ?string $pollingInterval = '15s';

    public function hydrate(): void
    {
        $this->cachedStats = null;
    }

    protected function getStats(): array
    {
        $context = $this->getDashboardContext();
        $user = $context['user'];
        $semester = $context['semester'];

        if (! $user instanceof User || ! $semester instanceof Semester) {
            return $this->emptyStats();
        }

        $totals = app(AttendanceStatistics::class)->totalsForSemester($user, $semester);

        return [
            Stat::make('Overall attendance', is_null($totals['rate']) ? 'N/A' : number_format($totals['rate'], 1).'%')
                ->description(($totals['present'] + $totals['absent']).' marked sessions')
                ->color('primary'),
            Stat::make('Present sessions', $totals['present'])
                ->color('success'),
            Stat::make('Absent sessions', $totals['absent'])
                ->color('danger'),
            Stat::make('Active subjects', $totals['active_subjects']),
        ];
    }

    public function updatedPageFilters(): void
    {
        $this->cachedStats = null;
    }

    /**
     * @return array<Stat>
     */
    private function emptyStats(): array
    {
        return [
            Stat::make('Overall attendance', 'N/A'),
            Stat::make('Present sessions', 0),
            Stat::make('Absent sessions', 0),
            Stat::make('Active subjects', 0),
        ];
    }
}
