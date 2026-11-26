<?php

namespace App\Filament\Widgets\Concerns;

use App\Actions\AttendanceStatistics;
use App\Models\Semester;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

trait InteractsWithDashboardData
{
    use InteractsWithPageFilters;

    /**
     * Resolve the dashboard's selected semester through the signed-in user's relation.
     *
     * @return array{user: User|null, semester: Semester|null}
     */
    protected function getDashboardContext(): array
    {
        $user = Filament::auth()->user();

        if (! $user instanceof User) {
            return [
                'user' => null,
                'semester' => null,
            ];
        }

        return [
            'user' => $user,
            'semester' => app(AttendanceStatistics::class)->selectedSemester(
                $user,
                data_get($this->pageFilters, 'semester_id'),
            ),
        ];
    }
}
