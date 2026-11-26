<?php

namespace App\Filament\Resources\Semesters\Pages;

use App\Actions\DeleteSemester;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Models\Semester;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;

class EditSemester extends EditRecord
{
    protected static string $resource = SemesterResource::class;

    protected function getFormActions(): array
    {
        return [
            ...parent::getFormActions(),
            DeleteAction::make()
                ->label('Delete semester')
                ->modalDescription('Deleting this semester permanently deletes its subjects, timetable slots, and attendance history. If this is your active semester, you will need to select another one.')
                ->using(function (Semester $record, DeleteSemester $deleteSemester): bool {
                    $user = Filament::auth()->user();

                    abort_unless($user instanceof User, 403);

                    return $deleteSemester->handle($user, $record);
                })
                ->successRedirectUrl(SemesterResource::getUrl('index')),
        ];
    }
}
