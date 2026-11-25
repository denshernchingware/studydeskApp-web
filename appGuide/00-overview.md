# StudyDesk implementation guide

## Purpose

Rebuild the existing `mba-learning-lms` application as a Laravel application using the installed Filament v5 panel. The source is a personal MBA study tracker, not a course-delivery LMS: it currently has subjects, recurring study slots, attendance, and progress summaries. Keep the first implementation aligned to those working features; do not add lessons, enrollments, grading, instructor roles, or messaging as part of this clone.

## Source reviewed

Source root: `C:\Users\Densher\Downloads\mba-learning-lms\mba-learning-lms`

- `index.html` and `assets/js/dashboard.js`: overview metrics, today's schedule, seven-day consistency chart, subject completion progress, and quick links.
- `pages/subjects.html` and `assets/js/subjects.js`: subject CRUD, search, required-session target, attendance summary, and completion status.
- `pages/timetable.html` and `assets/js/timetable.js`: recurring day/time slots, time-overlap validation, weekly totals, edit, and delete.
- `pages/attendance.html` and `assets/js/attendance.js`: date selection, scheduled-session marking, subject totals, and recent history.
- `assets/js/app.js`: localStorage persistence, date calculations, status helpers, theme toggle, and shared UI behavior.
- `README.md`: source behavior and explicit attendance rules.

## Target application state

The destination already has Laravel 13, Filament 5.12, Livewire 4, a registered `/admin` panel, and the official `filament-development` skill. The local SQLite database already has its standard Laravel migrations and a development admin account. Do not put account credentials in these guides or in source control.

Use Filament resources for the subject and timetable CRUD flows, Filament pages/widgets for attendance and dashboard workflows where they fit, and Laravel models/migrations for persisted data. Keep Filament v5 APIs consistent with the installed package; consult its official v5 documentation when implementation begins.

## Behavior to preserve

- A subject has a name, optional short code and description, a display color, and a required-session target.
- A weekly slot links one subject to one weekday and start/end time; it may have an optional label.
- A slot is expected on its weekday from its effective start date. The source uses the app's first-use date as the overall start and each slot's creation date as its own lower bound.
- Attendance is explicitly `present` or `absent`; unmarked sessions stay unmarked.
- Attendance rate is `present / (present + absent)`. Expected-but-unmarked sessions do not lower that rate.
- A subject is complete when its marked count (`present + absent`) reaches its required-session target. Completed subjects are read-only for attendance.
- Future sessions cannot be marked. A subject without a slot on a date is not expected that day.
- Same-day slot overlaps are rejected. Deleting a slot or subject in the source also removes its attendance history.
- The dashboard's weekly percentage is calculated from marked sessions in the last seven calendar days. Subject progress is shown against the required-session target.
- The source has a light/dark preference stored separately in browser localStorage.

## Data model proposal

Keep ownership explicit because the source describes a private workspace and the Laravel app uses authenticated Filament access.

1. `subjects`: owner (`user_id`), name, code, description, color, required session count, timestamps.
2. `timetable_slots`: owner (`user_id`), subject, weekday, start time, end time, label, effective-from date, timestamps.
3. `attendance_records`: owner (`user_id`), timetable slot, session date, status, timestamps; enforce one record per slot/date.
4. Store `study_start_date` on the user. Use Filament's built-in appearance switcher; its light/dark selection remains browser-side like the source preference.

Use foreign keys and indexes that support owner-scoped listing, date/slot attendance lookup, and overlap checks. Treat scheduled occurrences as derived from recurring slots; persist only marks unless a later requirement needs a generated session calendar.

## Implementation order

1. [Foundation, access, and navigation](01-foundation-access-and-shell.md)
2. [Subject management](02-subject-management.md)
3. [Weekly timetable](03-weekly-timetable.md)
4. [Attendance](04-attendance.md)
5. [Dashboard and reports](05-dashboard-and-reports.md)
6. [Preferences and local-data transition](06-preferences-and-data-transition.md)

Each module guide includes implementation steps and acceptance checks. Complete and review one module before moving to the next. The guides specify intended work only; they do not implement it.

## Cross-module decisions to keep consistent

- Scope all domain records and every resource/query to the authenticated owner. Only emails listed in `STUDYDESK_ADMIN_EMAILS` can enter the `admin` panel; the allowlist is configuration, not a development-domain check.
- Store session dates as date values and recurring clock times as local wall-clock times. `APP_TIMEZONE` controls date boundaries and defaults to `Asia/Kolkata`.
- Make attendance completion checks and writes atomic so two requests cannot exceed a subject's target.
- Define deletion behavior before enabling destructive actions. To match the source, deleting a slot removes its marks, and deleting a subject removes its slots and marks; show a confirmation that explains the loss.
- Historical expected-session counts depend on slot effective dates. Editing a slot's day or time must not silently rewrite past attendance.
- There is no export/import feature in the source. Browser localStorage data will not automatically appear in Laravel; migration from a user's browser is a separate optional feature.

## Out of scope for this first pass

- Course lessons, files, quizzes, assignments, grades, and enrollment.
- Instructor or institution administration beyond the existing Filament admin account.
- Notifications, calendar integrations, exports, and cloud sync.
- Importing existing browser localStorage records unless requested as a separate module.
