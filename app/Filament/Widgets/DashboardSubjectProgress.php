<?php

namespace App\Filament\Widgets;

use App\Actions\AttendanceStatistics;
use App\Filament\Widgets\Concerns\InteractsWithDashboardData;
use App\Models\Semester;
use App\Models\User;
use Filament\Widgets\Widget;

class DashboardSubjectProgress extends Widget
{
    use InteractsWithDashboardData;

    protected string $view = 'filament.widgets.dashboard-subject-progress';

    /**
     * @return array{semester: Semester|null, summaries: array}
     */
    protected function getViewData(): array
    {
        $context = $this->getDashboardContext();
        $user = $context['user'];
        $semester = $context['semester'];

        return [
            'semester' => $semester,
            'summaries' => $user instanceof User && $semester instanceof Semester
                ? app(AttendanceStatistics::class)->forSemester($user, $semester)
                : [],
        ];
    }
}
