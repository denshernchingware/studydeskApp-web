<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\TimetableSlots\TimetableSlotResource;
use App\Filament\Widgets\DashboardAttendanceStats;
use App\Filament\Widgets\DashboardSubjectProgress;
use App\Filament\Widgets\DashboardTodaySchedule;
use App\Filament\Widgets\DashboardWeeklyConsistency;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Facades\Filament;
use Filament\Forms\Components\Select;
use Filament\Pages\Dashboard as BaseDashboard;
use Filament\Pages\Dashboard\Concerns\HasFiltersForm;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;

class Dashboard extends BaseDashboard
{
    use HasFiltersForm;

    protected static bool $isDiscovered = false;

    public function persistsFiltersInSession(): bool
    {
        return false;
    }

    /**
     * @return array<class-string>
     */
    public function getWidgets(): array
    {
        return [
            DashboardAttendanceStats::class,
            DashboardTodaySchedule::class,
            DashboardWeeklyConsistency::class,
            DashboardSubjectProgress::class,
        ];
    }

    /**
     * @return int|array<string, ?int>
     */
    public function getColumns(): int|array
    {
        return [
            '@xl' => 2,
            '!@xl' => 1,
        ];
    }

    public function filtersForm(Schema $schema): Schema
    {
        return $schema->components([
            Select::make('semester_id')
                ->label('Report semester')
                ->options(function (): array {
                    $user = Filament::auth()->user();

                    return $user instanceof User
                        ? $user->semesters()->orderByDesc('starts_on')->pluck('name', 'id')->all()
                        : [];
                })
                ->default(function (): ?int {
                    $user = Filament::auth()->user();

                    if (! $user instanceof User || blank($user->active_semester_id)) {
                        return null;
                    }

                    $semesterId = $user->semesters()->whereKey($user->active_semester_id)->value('id');

                    return filled($semesterId) ? (int) $semesterId : null;
                })
                ->placeholder('Select a semester')
                ->searchable(),
        ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('subjects')
                ->label('Subjects')
                ->icon(Heroicon::OutlinedBookOpen)
                ->color('gray')
                ->url(SubjectResource::getUrl('index')),
            Action::make('timetable')
                ->label('Timetable')
                ->icon(Heroicon::OutlinedCalendarDays)
                ->color('gray')
                ->url(TimetableSlotResource::getUrl('index')),
            Action::make('attendance')
                ->label('Attendance')
                ->icon(Heroicon::OutlinedClipboardDocumentCheck)
                ->url(Attendance::getUrl()),
            Action::make('semesters')
                ->label('Semesters')
                ->icon(Heroicon::OutlinedRectangleStack)
                ->color('gray')
                ->url(SemesterResource::getUrl('index')),
        ];
    }
}
