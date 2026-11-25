<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\CreateAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\ListRecords;

class ListSubjects extends ListRecords
{
    protected static string $resource = SubjectResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->visible(function (): bool {
                    $user = Filament::auth()->user();

                    return $user instanceof User && filled($user->active_semester_id);
                }),
            Action::make('createSemester')
                ->label('Create a semester first')
                ->url(SemesterResource::getUrl('create'))
                ->visible(function (): bool {
                    $user = Filament::auth()->user();

                    return ! ($user instanceof User) || blank($user->active_semester_id);
                }),
        ];
    }
}
