<?php

namespace App\Filament\Resources\Semesters\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SemesterForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(80),
                DatePicker::make('starts_on')
                    ->label('Start date')
                    ->required(),
                DatePicker::make('ends_on')
                    ->label('End date')
                    ->required()
                    ->afterOrEqual('starts_on'),
            ]);
    }
}
