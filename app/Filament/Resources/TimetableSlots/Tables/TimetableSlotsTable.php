<?php

namespace App\Filament\Resources\TimetableSlots\Tables;

use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Facades\Filament;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class TimetableSlotsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('weekday_name')
                    ->label('Day')
                    ->badge(),
                TextColumn::make('start_time')
                    ->label('Start')
                    ->time('H:i')
                    ->sortable(),
                TextColumn::make('end_time')
                    ->label('End')
                    ->time('H:i'),
                TextColumn::make('subject.name')
                    ->label('Subject')
                    ->searchable()
                    ->sortable(),
                TextColumn::make('label')
                    ->placeholder('-'),
                TextColumn::make('effective_from')
                    ->label('Effective from')
                    ->date(),
            ])
            ->filters([
                SelectFilter::make('semester')
                    ->options(fn (): array => Semester::query()
                        ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                        ->orderByDesc('starts_on')
                        ->pluck('name', 'id')
                        ->all())
                    ->default(function (): ?int {
                        $user = Filament::auth()->user();

                        if (! $user instanceof User || $user->active_semester_id === null) {
                            return null;
                        }

                        $semesterId = $user->semesters()
                            ->whereKey($user->active_semester_id)
                            ->value('id');

                        return filled($semesterId) ? (int) $semesterId : null;
                    })
                    ->query(function (Builder $query, array $data): Builder {
                        $semesterId = $data['value'] ?? null;

                        if (blank($semesterId)) {
                            return $query;
                        }

                        return $query->whereHas('subject.semester', function (Builder $semesterQuery) use ($semesterId): void {
                            $semesterQuery
                                ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                                ->whereKey($semesterId);
                        });
                    }),
                SelectFilter::make('subject')
                    ->options(fn (): array => Subject::query()
                        ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->query(function (Builder $query, array $data): Builder {
                        $subjectId = $data['value'] ?? null;

                        if (blank($subjectId)) {
                            return $query;
                        }

                        return $query->whereHas('subject', function (Builder $subjectQuery) use ($subjectId): void {
                            $subjectQuery
                                ->where('user_id', Filament::auth()->user()?->getAuthIdentifier())
                                ->whereKey($subjectId);
                        });
                    }),
                SelectFilter::make('weekday')
                    ->label('Day')
                    ->options(TimetableSlot::WEEKDAYS),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make()
                    ->modalDescription('Deleting this slot also removes its attendance history.'),
            ])
            ->groups([
                Group::make('weekday')
                    ->label('Day')
                    ->getTitleFromRecordUsing(fn (TimetableSlot $record): string => $record->weekday_name)
                    ->orderQueryUsing(function (Builder $query, string $direction): Builder {
                        $direction = in_array($direction, ['asc', 'desc'], true) ? $direction : 'asc';

                        return $query->orderByRaw("CASE WHEN weekday = 0 THEN 7 ELSE weekday END {$direction}");
                    }),
            ])
            ->defaultGroup('weekday');
    }
}
