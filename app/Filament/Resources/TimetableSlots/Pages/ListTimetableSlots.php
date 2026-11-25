<?php

namespace App\Filament\Resources\TimetableSlots\Pages;

use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Filament\Resources\TimetableSlots\TimetableSlotResource;
use App\Filament\Widgets\TimetableSlotStats;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListTimetableSlots extends ListRecords
{
    protected static string $resource = TimetableSlotResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(function (): bool {
                    $user = Filament::auth()->user();

                    return $user instanceof User && filled($user->active_semester_id) && $user->subjects()
                        ->where('semester_id', $user->active_semester_id)
                        ->exists();
                }),
            Action::make('createSemester')
                ->label('Create a semester first')
                ->url(SemesterResource::getUrl('create'))
                ->visible(function (): bool {
                    $user = Filament::auth()->user();

                    return ! ($user instanceof User) || blank($user->active_semester_id);
                }),
            Action::make('createSubject')
                ->label('Add a subject to the active semester')
                ->url(SubjectResource::getUrl('index'))
                ->visible(function (): bool {
                    $user = Filament::auth()->user();

                    return $user instanceof User && filled($user->active_semester_id) && ! $user->subjects()
                        ->where('semester_id', $user->active_semester_id)
                        ->exists();
                }),
        ];
    }

    protected function getHeaderWidgets(): array
    {
        return [
            TimetableSlotStats::class,
        ];
    }

    public function getWidgetData(): array
    {
        return [
            'tableFilters' => $this->tableFilters,
        ];
    }
}
