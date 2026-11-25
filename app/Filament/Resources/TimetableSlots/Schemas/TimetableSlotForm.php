<?php

namespace App\Filament\Resources\TimetableSlots\Schemas;

use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Filament\Facades\Filament;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Schema;

class TimetableSlotForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('subject_id')
                    ->label('Subject')
                    ->options(function (?TimetableSlot $record): array {
                        $user = Filament::auth()->user();

                        if (! $user instanceof User) {
                            return [];
                        }

                        $semesterId = $record?->subject?->semester_id ?? $user->active_semester_id;

                        if ($semesterId === null) {
                            return [];
                        }

                        return Subject::query()
                            ->where('user_id', $user->getAuthIdentifier())
                            ->where('semester_id', $semesterId)
                            ->orderBy('name')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->searchable()
                    ->required(),
                Select::make('weekday')
                    ->options(TimetableSlot::WEEKDAYS)
                    ->required(),
                TimePicker::make('start_time')
                    ->seconds(false)
                    ->required(),
                TimePicker::make('end_time')
                    ->seconds(false)
                    ->after('start_time')
                    ->required(),
                TextInput::make('label')
                    ->maxLength(60),
                DatePicker::make('effective_from')
                    ->label('Effective from')
                    ->required()
                    ->default(function (?TimetableSlot $record): ?string {
                        if ($record instanceof TimetableSlot) {
                            return $record->effective_from->toDateString();
                        }

                        $user = Filament::auth()->user();

                        if (! $user instanceof User || $user->active_semester_id === null) {
                            return null;
                        }

                        return $user->semesters()
                            ->whereKey($user->active_semester_id)
                            ->value('starts_on');
                    })
                    ->disabled(fn (?TimetableSlot $record): bool => $record instanceof TimetableSlot),
            ]);
    }
}
