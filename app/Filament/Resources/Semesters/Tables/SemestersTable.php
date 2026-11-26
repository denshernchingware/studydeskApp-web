<?php

namespace App\Filament\Resources\Semesters\Tables;

use App\Actions\DeleteSemester;
use App\Models\Semester;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class SemestersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('starts_on')
                    ->label('Start date')
                    ->date()
                    ->sortable(),
                TextColumn::make('ends_on')
                    ->label('End date')
                    ->date()
                    ->sortable(),
                TextColumn::make('active_status')
                    ->label('Status')
                    ->state(function (Semester $record): string {
                        $user = Filament::auth()->user();

                        return $user instanceof User && (int) $record->getKey() === (int) $user->active_semester_id ? 'Active' : 'Previous';
                    })
                    ->badge()
                    ->color(fn (string $state): string => $state === 'Active' ? 'success' : 'gray'),
            ])
            ->filters([])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->label('Delete')
                    ->modalDescription('Deleting this semester permanently deletes its subjects, timetable slots, and attendance history. If this is your active semester, you will need to select another one.')
                    ->using(function (Semester $record, DeleteSemester $deleteSemester): bool {
                        $user = Filament::auth()->user();

                        abort_unless($user instanceof User, 403);

                        return $deleteSemester->handle($user, $record);
                    }),
                Action::make('makeActive')
                    ->label('Make active')
                    ->visible(function (Semester $record): bool {
                        $user = Filament::auth()->user();

                        return $user instanceof User && (int) $record->getKey() !== (int) $user->active_semester_id;
                    })
                    ->action(function (Semester $record): void {
                        $user = Filament::auth()->user();

                        abort_unless($user instanceof User, 403);

                        $user->forceFill(['active_semester_id' => $record->getKey()])->save();

                        Notification::make()
                            ->title('Active semester updated')
                            ->success()
                            ->send();
                    }),
            ]);
    }
}
