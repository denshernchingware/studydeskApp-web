<?php

namespace App\Filament\Resources\Semesters\Pages;

use App\Filament\Resources\Semesters\SemesterResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;

class CreateSemester extends CreateRecord
{
    protected static string $resource = SemesterResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        $data['user_id'] = $user->getKey();

        return $data;
    }

    protected function afterCreate(): void
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        $user->forceFill([
            'active_semester_id' => $this->record->getKey(),
        ])->save();
    }
}
