<?php

namespace App\Filament\Resources\TimetableSlots\Pages;

use App\Actions\SaveTimetableSlot;
use App\Filament\Resources\TimetableSlots\TimetableSlotResource;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTimetableSlot extends CreateRecord
{
    protected static string $resource = TimetableSlotResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User, 403);

        return app(SaveTimetableSlot::class)->create($user, $data);
    }
}
