<?php

namespace App\Filament\Resources\Subjects\Pages;

use App\Filament\Resources\Subjects\SubjectResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Validation\ValidationException;

class CreateSubject extends CreateRecord
{
    protected static string $resource = SubjectResource::class;

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        $user = Filament::auth()->user();
        if (! $user instanceof User) {
            throw ValidationException::withMessages([
                'data.name' => 'Sign in before adding subjects.',
            ]);
        }

        $activeSemesterId = $user->active_semester_id;

        if ($activeSemesterId === null || ! $user->semesters()->whereKey($activeSemesterId)->exists()) {
            throw ValidationException::withMessages([
                'data.name' => 'Create a semester and make it active before adding subjects.',
            ]);
        }

        $data['user_id'] = $user->getKey();
        $data['semester_id'] = $activeSemesterId;

        return $data;
    }
}
