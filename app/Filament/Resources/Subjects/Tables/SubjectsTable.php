<?php

namespace App\Filament\Resources\Subjects\Tables;

use App\Models\Semester;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class SubjectsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('semester.name')
                    ->label('Semester')
                    ->placeholder('No semester'),
                TextColumn::make('code')
                    ->searchable()
                    ->placeholder('—'),
                TextColumn::make('color')
                    ->badge()
                    ->formatStateUsing(fn (string $state): string => ucfirst($state)),
                TextColumn::make('required_sessions')
                    ->label('Required sessions')
                    ->sortable(),
            ])
            ->filters([
                SelectFilter::make('semester_id')
                    ->label('Semester')
                    ->options(fn (): array => [
                        'legacy' => 'No semester (legacy)',
                        ...Semester::query()
                            ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                            ->orderByDesc('starts_on')
                            ->pluck('name', 'id')
                            ->all(),
                    ])
                    ->default(function (): ?int {
                        $user = Filament::auth()->user();

                        return $user instanceof User && filled($user->active_semester_id)
                            ? (int) $user->active_semester_id
                            : null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $semesterId = $data['value'] ?? null;

                        if ($semesterId === 'legacy') {
                            return $query->whereNull('semester_id');
                        }

                        if (blank($semesterId)) {
                            return $query;
                        }

                        return $query->whereHas('semester', function (Builder $semesterQuery) use ($semesterId): void {
                            $semesterQuery
                                ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                                ->whereKey($semesterId);
                        });
                    }),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Deleting this subject also removes its timetable slots and attendance marks.'),
            ]);
    }
}
