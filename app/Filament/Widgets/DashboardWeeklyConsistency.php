<?php

namespace App\Filament\Widgets;

use App\Actions\AttendanceStatistics;
use App\Filament\Widgets\Concerns\InteractsWithDashboardData;
use App\Models\Semester;
use App\Models\User;
use Filament\Widgets\ChartWidget;

class DashboardWeeklyConsistency extends ChartWidget
{
    use InteractsWithDashboardData;

    protected ?string $heading = 'Weekly consistency';

    protected ?string $pollingInterval = '15s';

    public function hydrate(): void
    {
        $this->cachedData = null;
    }

    protected function getData(): array
    {
        $context = $this->getDashboardContext();
        $user = $context['user'];
        $semester = $context['semester'];

        if (! $user instanceof User || ! $semester instanceof Semester) {
            return [];
        }

        $weeklyData = app(AttendanceStatistics::class)->weeklyConsistency($user, $semester);

        return [
            'labels' => $weeklyData['labels'],
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $weeklyData['present'],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.75)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Absent',
                    'data' => $weeklyData['absent'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.75)',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getOptions(): array
    {
        return [
            'scales' => [
                'y' => [
                    'beginAtZero' => true,
                    'ticks' => [
                        'precision' => 0,
                    ],
                ],
            ],
        ];
    }

    public function updatedPageFilters(): void
    {
        $this->cachedData = null;
    }
}
