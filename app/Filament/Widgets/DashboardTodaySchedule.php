<?php

namespace App\Filament\Widgets;

use App\Actions\AttendanceStatistics;
use App\Filament\Widgets\Concerns\InteractsWithDashboardData;
use App\Models\Semester;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Widgets\Widget;

class DashboardTodaySchedule extends Widget
{
    use InteractsWithDashboardData;

    protected int|string|array $columnSpan = 'full';

    protected string $view = 'filament.widgets.dashboard-today-schedule';

    /**
     * @return array{semester: Semester|null, sessions: array, dateLabel: string}
     */
    protected function getViewData(): array
    {
        $context = $this->getDashboardContext();
        $user = $context['user'];
        $semester = $context['semester'];

        return [
            'semester' => $semester,
            'sessions' => $user instanceof User && $semester instanceof Semester
                ? app(AttendanceStatistics::class)->todaySchedule($user, $semester)
                : [],
            'dateLabel' => CarbonImmutable::today(config('app.timezone'))->format('l, F j, Y'),
        ];
    }
}
