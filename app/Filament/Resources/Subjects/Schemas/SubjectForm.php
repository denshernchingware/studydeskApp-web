<?php

namespace App\Filament\Resources\Subjects\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class SubjectForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required()
                    ->maxLength(70),
                TextInput::make('code')
                    ->maxLength(12),
                Select::make('color')
                    ->options([
                        'violet' => 'Violet',
                        'blue' => 'Blue',
                        'green' => 'Green',
                        'orange' => 'Orange',
                        'pink' => 'Pink',
                        'teal' => 'Teal',
                    ])
                    ->rules(['in:violet,blue,green,orange,pink,teal'])
                    ->required()
                    ->default('violet'),
                TextInput::make('required_sessions')
                    ->label('Required sessions')
                    ->numeric()
                    ->integer()
                    ->minValue(1)
                    ->required(),
                Textarea::make('description')
                    ->rows(3)
                    ->columnSpanFull(),
            ]);
    }
}
