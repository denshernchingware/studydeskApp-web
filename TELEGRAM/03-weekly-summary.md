# Module 3: Weekly statistics summary

## Status

Implemented. Depends on module 01 account linking and the Telegram delivery outbox.

## Goal

Send the owner a concise weekly view of study attendance and subject progress on a configurable scheduled time.

## Default reporting behavior

- Default delivery: Sunday at 18:00 in `APP_TIMEZONE` (currently `Asia/Kolkata`). Make weekday/time configurable so the user can change it later.
- Summarize the completed Monday–Sunday week, not a partial current week. Use explicit start/end dates in the message.
- Use the active semester by default and clearly name it. A week with no active-semester dates or no expected sessions should produce a brief no-activity message rather than misleading zeros.

## Implementation steps

1. [x] Add per-account weekly-summary enabled, weekday, and local-time preferences. Weekday/time are interpreted in `APP_TIMEZONE`.
2. [x] Add a unique outbox key scoped to owner, semester, and week-start date; queue retries cannot create a second report.
3. [x] Build the summary using `AttendanceStatistics` for semester target progress and period-bounded queries for expected, marked, present, absent, and unmarked sessions.
4. [x] Scope calculations to the owner, active semester, inclusive semester dates, study start, timetable effective dates, and the completed week's dates. Unmarked sessions are excluded from the attendance-rate denominator.
5. [x] Scan due preferences each minute, queue delivery, use scheduler overlap protection, and persist the unique outbox record before dispatch.
6. [x] Include the semester, Monday–Sunday report window, totals, attendance rate, and up to eight subjects needing attention. Cap the message below Telegram's text limit.
7. [x] Add a dry-run command that previews the calculations without writing delivery records or contacting Telegram.

Laravel's scheduler supports weekly/day/time scheduling and timezone-specific events. Use `withoutOverlapping()` for the dispatch task; the outbox uniqueness check still protects against retries and multiple invocations. For local development, Laravel provides `php artisan schedule:work`. [Laravel 13 task scheduling](https://laravel.com/framework/docs/13.x/scheduling)

## Local commands

Run the new account/outbox migrations before using this module:

```powershell
php artisan migrate
```

Preview the previous completed Monday–Sunday week for an account:

```powershell
php artisan telegram:send-weekly-summary --user=1 --dry-run
```

Preview a chosen completed week (the date must be a Monday):

```powershell
php artisan telegram:send-weekly-summary --week-start=2026-11-16 --user=1 --dry-run
```

The non-dry-run command queues summaries only when the user's configured weekday/time matches the current app-local minute. The scheduler registers this scan automatically; run `php artisan schedule:work` and a queue worker to process due deliveries.

## Acceptance checks

- A completed week is calculated Monday through Sunday in the configured timezone and clipped to the selected semester range.
- The message totals match dashboard and attendance-page statistics for the same owner, semester, and date window.
- Unmarked expected sessions are reported separately and do not count as absent.
- Each linked user with weekly summaries enabled receives at most one summary per semester/week.
- No message is sent for an unlinked or disabled account; dry-run never contacts Telegram.

## Verification plan

- [ ] Run the report at Sunday/Monday boundaries, semester start/end, empty weeks, unmarked sessions, zero marked sessions, and completed-subject targets.
- [ ] Check per-account schedule preferences and duplicate dispatch behavior.
- [ ] Compare a dry-run report with the same date-filtered Filament attendance/dashboard totals.
