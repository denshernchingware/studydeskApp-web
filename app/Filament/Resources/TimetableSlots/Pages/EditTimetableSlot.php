<?php

namespace App\Filament\Resources\TimetableSlots\Pages;

use App\Actions\SaveTimetableSlot;
use App\Filament\Resources\TimetableSlots\TimetableSlotResource;
use App\Models\TimetableSlot;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Facades\Filament;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditTimetableSlot extends EditRecord
{
    protected static string $resource = TimetableSlotResource::class;

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $user = Filament::auth()->user();

        abort_unless($user instanceof User && $record instanceof TimetableSlot, 403);

        return app(SaveTimetableSlot::class)->update($user, $record, $data);
    }

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make()
                ->modalDescription('Deleting this slot also removes its attendance history.'),
        ];
    }
}
