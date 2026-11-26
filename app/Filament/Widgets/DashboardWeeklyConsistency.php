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

    public ?string $filter = 'weekly';

    protected ?string $heading = 'Attendance consistency';

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

        $statistics = app(AttendanceStatistics::class);
        $periodData = $this->filter === 'monthly'
            ? $statistics->monthlyConsistency($user, $semester)
            : $statistics->weeklyConsistency($user, $semester);

        return [
            'labels' => $periodData['labels'],
            'datasets' => [
                [
                    'label' => 'Present',
                    'data' => $periodData['present'],
                    'backgroundColor' => 'rgba(34, 197, 94, 0.75)',
                    'borderRadius' => 4,
                ],
                [
                    'label' => 'Absent',
                    'data' => $periodData['absent'],
                    'backgroundColor' => 'rgba(239, 68, 68, 0.75)',
                    'borderRadius' => 4,
                ],
            ],
        ];
    }

    /**
     * @return array<string, string>|null
     */
    protected function getFilters(): ?array
    {
        return [
            'weekly' => 'Weekly',
            'monthly' => 'Monthly',
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
