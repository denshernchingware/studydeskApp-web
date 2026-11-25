<?php

namespace App\Filament\Pages;

use App\Actions\AttendanceStatistics;
use App\Actions\SaveAttendanceRecord;
use App\Enums\AttendanceStatus;
use App\Filament\Resources\Semesters\SemesterResource;
use App\Filament\Resources\TimetableSlots\TimetableSlotResource;
use App\Models\AttendanceRecord;
use App\Models\Semester;
use App\Models\Subject;
use App\Models\TimetableSlot;
use App\Models\User;
use Carbon\CarbonImmutable;
use Filament\Facades\Filament;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Url;

class Attendance extends Page
{
    protected static string|\BackedEnum|null $navigationIcon = Heroicon::OutlinedClipboardDocumentCheck;

    protected static ?string $navigationLabel = 'Attendance';

    protected static ?string $title = 'Attendance';

    protected string $view = 'filament.pages.attendance';

    #[Url(as: 'semester')]
    public ?int $selectedSemesterId = null;

    #[Url(as: 'date')]
    public ?string $selectedDate = null;

    public function mount(): void
    {
        $user = $this->authenticatedUser();

        if (! $user instanceof User) {
            abort(403);
        }

        $semester = $this->resolveSemester($user);
        $this->selectedSemesterId = $semester?->getKey();
        $this->selectedDate = $this->clampDateToSemester($this->selectedDate, $semester);
    }

    public static function canAccess(): bool
    {
        return Filament::auth()->user() instanceof User;
    }

    public function updatedSelectedSemesterId(): void
    {
        $user = $this->authenticatedUser();

        if (! $user instanceof User) {
            abort(403);
        }

        $semester = $this->resolveSemester($user);
        $this->selectedSemesterId = $semester?->getKey();
        $this->selectedDate = $this->clampDateToSemester($this->selectedDate, $semester);
        $this->resetValidation('selectedDate');
    }

    public function updatedSelectedDate(): void
    {
        $this->validateOnly('selectedDate', [
            'selectedDate' => ['required', 'date_format:Y-m-d'],
        ]);

        $semester = $this->selectedSemester();

        if (! $semester instanceof Semester) {
            return;
        }

        Validator::make(
            ['selectedDate' => $this->selectedDate],
            ['selectedDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$semester->starts_on->toDateString(),
                'before_or_equal:'.$semester->ends_on->toDateString(),
            ]],
        )->validate();
    }

    /**
     * Toggle a Present or Absent mark for a scheduled session.
     */
    public function toggleAttendance(int $slotId, string $status): void
    {
        $user = $this->authenticatedUser();
        $semester = $this->selectedSemester();
        $attendanceStatus = AttendanceStatus::tryFrom($status);

        if (! $user instanceof User || ! $semester instanceof Semester) {
            abort(403);
        }

        if (! $attendanceStatus instanceof AttendanceStatus) {
            throw ValidationException::withMessages([
                'selectedDate' => 'Choose Present or Absent.',
            ]);
        }

        $this->validateSelectedDate($semester);

        $record = app(SaveAttendanceRecord::class)->toggle(
            $user,
            $slotId,
            (int) $semester->getKey(),
            (string) $this->selectedDate,
            $attendanceStatus,
        );

        Notification::make()
            ->title($record instanceof AttendanceRecord ? 'Attendance saved' : 'Attendance cleared')
            ->success()
            ->send();
    }

    /**
     * @return Collection<int, Semester>
     */
    public function getSemestersProperty(): Collection
    {
        $user = $this->authenticatedUser();

        return $user instanceof User
            ? $user->semesters()->orderByDesc('starts_on')->get()
            : new Collection;
    }

    public function getSelectedSemesterProperty(): ?Semester
    {
        return $this->selectedSemester();
    }

    /**
     * @return array<int, array{slot: TimetableSlot, record: AttendanceRecord|null, completed: bool}>
     */
    public function getSessionsProperty(): array
    {
        $user = $this->authenticatedUser();
        $semester = $this->selectedSemester();
        $date = $this->selectedDateValue($semester);

        if (! $user instanceof User || ! $semester instanceof Semester || ! $date instanceof CarbonImmutable) {
            return [];
        }

        if ($date->lt($user->study_start_date->startOfDay())) {
            return [];
        }

        $summaries = collect(app(AttendanceStatistics::class)->forSemester($user, $semester))
            ->keyBy(fn (array $summary): int => (int) $summary['subject']->getKey());

        $slots = TimetableSlot::query()
            ->with([
                'subject',
                'attendanceRecords' => fn ($query) => $query
                    ->where('user_id', $user->getKey())
                    ->whereDate('session_date', $date->toDateString()),
            ])
            ->where('timetable_slots.user_id', $user->getKey())
            ->where('weekday', $date->dayOfWeek)
            ->whereDate('effective_from', '<=', $date->toDateString())
            ->whereHas('subject', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->where('semester_id', $semester->getKey()))
            ->orderBy('start_time')
            ->get();

        return $slots->map(fn (TimetableSlot $slot): array => [
            'slot' => $slot,
            'record' => $slot->attendanceRecords->first(),
            'completed' => (bool) ($summaries->get((int) $slot->subject_id)['completed'] ?? false),
        ])->all();
    }

    /**
     * @return array<int, array{subject: Subject, expected_sessions: int, present: int, absent: int, marked_sessions: int, remaining_sessions: int, rate: float|null, completed: bool}>
     */
    public function getSummariesProperty(): array
    {
        $user = $this->authenticatedUser();
        $semester = $this->selectedSemester();

        if (! $user instanceof User || ! $semester instanceof Semester) {
            return [];
        }

        return app(AttendanceStatistics::class)->forSemester($user, $semester);
    }

    /**
     * @return Collection<int, AttendanceRecord>
     */
    public function getRecentHistoryProperty(): Collection
    {
        $user = $this->authenticatedUser();
        $semester = $this->selectedSemester();

        if (! $user instanceof User || ! $semester instanceof Semester) {
            return new Collection;
        }

        return AttendanceRecord::query()
            ->select('attendance_records.*')
            ->join('timetable_slots', 'attendance_records.timetable_slot_id', '=', 'timetable_slots.id')
            ->with(['timetableSlot.subject'])
            ->where('attendance_records.user_id', $user->getKey())
            ->where('timetable_slots.user_id', $user->getKey())
            ->whereBetween('session_date', [
                $semester->starts_on->toDateString(),
                $semester->ends_on->toDateString(),
            ])
            ->whereHas('timetableSlot', fn ($query) => $query
                ->where('user_id', $user->getKey())
                ->whereHas('subject', fn ($subjectQuery) => $subjectQuery
                    ->where('user_id', $user->getKey())
                    ->where('semester_id', $semester->getKey())))
            ->orderByDesc('session_date')
            ->orderByDesc('timetable_slots.start_time')
            ->limit(10)
            ->get();
    }

    public function semesterIndexUrl(): string
    {
        return SemesterResource::getUrl('index');
    }

    public function timetableIndexUrl(): string
    {
        return TimetableSlotResource::getUrl('index');
    }

    private function authenticatedUser(): ?User
    {
        $user = Filament::auth()->user();

        return $user instanceof User ? $user : null;
    }

    private function selectedSemester(): ?Semester
    {
        $user = $this->authenticatedUser();

        if (! $user instanceof User || $this->selectedSemesterId === null) {
            return null;
        }

        return $user->semesters()->whereKey($this->selectedSemesterId)->first();
    }

    private function resolveSemester(User $user): ?Semester
    {
        $requestedSemester = $user->semesters()->whereKey($this->selectedSemesterId)->first();

        if ($requestedSemester instanceof Semester) {
            return $requestedSemester;
        }

        return $user->semesters()->whereKey($user->active_semester_id)->first();
    }

    private function clampDateToSemester(?string $selectedDate, ?Semester $semester): ?string
    {
        if (! $semester instanceof Semester) {
            return null;
        }

        $date = CarbonImmutable::today(config('app.timezone'));

        if (filled($selectedDate) && Validator::make(
            ['selectedDate' => $selectedDate],
            ['selectedDate' => ['date_format:Y-m-d']],
        )->passes()) {
            $date = CarbonImmutable::parse($selectedDate, config('app.timezone'));
        }

        if ($date->lt($semester->starts_on->startOfDay())) {
            return $semester->starts_on->toDateString();
        }

        if ($date->gt($semester->ends_on->startOfDay())) {
            return $semester->ends_on->toDateString();
        }

        return $date->toDateString();
    }

    private function selectedDateValue(?Semester $semester): ?CarbonImmutable
    {
        if (! $semester instanceof Semester || ! filled($this->selectedDate)) {
            return null;
        }

        if (! Validator::make(
            ['selectedDate' => $this->selectedDate],
            ['selectedDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$semester->starts_on->toDateString(),
                'before_or_equal:'.$semester->ends_on->toDateString(),
            ]],
        )->passes()) {
            return null;
        }

        return CarbonImmutable::parse($this->selectedDate, config('app.timezone'))->startOfDay();
    }

    private function validateSelectedDate(Semester $semester): void
    {
        Validator::make(
            ['selectedDate' => $this->selectedDate],
            ['selectedDate' => [
                'required',
                'date_format:Y-m-d',
                'after_or_equal:'.$semester->starts_on->toDateString(),
                'before_or_equal:'.$semester->ends_on->toDateString(),
            ]],
        )->validate();
    }
}
