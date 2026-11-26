<?php

namespace App\Filament\Resources\TimetableSlots;

use App\Filament\Resources\TimetableSlots\Pages\CreateTimetableSlot;
use App\Filament\Resources\TimetableSlots\Pages\EditTimetableSlot;
use App\Filament\Resources\TimetableSlots\Pages\ListTimetableSlots;
use App\Filament\Resources\TimetableSlots\Schemas\TimetableSlotForm;
use App\Filament\Resources\TimetableSlots\Tables\TimetableSlotsTable;
use App\Models\TimetableSlot;
use BackedEnum;
use Filament\Facades\Filament;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimetableSlotResource extends Resource
{
    protected static ?string $model = TimetableSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCalendarDays;

    protected static ?string $recordTitleAttribute = 'label';

    protected static ?string $navigationLabel = 'Timetable';

    protected static ?string $modelLabel = 'study slot';

    protected static ?string $pluralModelLabel = 'timetable slots';

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
            ->with('subject.semester')
            ->orderByRaw('CASE WHEN weekday = 0 THEN 7 ELSE weekday END')
            ->orderBy('start_time');
    }

    public static function form(Schema $schema): Schema
    {
        return TimetableSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return TimetableSlotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListTimetableSlots::route('/'),
            'create' => CreateTimetableSlot::route('/create'),
            'edit' => EditTimetableSlot::route('/{record}/edit'),
        ];
    }
}
