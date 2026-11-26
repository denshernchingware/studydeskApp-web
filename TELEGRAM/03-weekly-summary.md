# Module 3: Weekly statistics summary

## Status

Not implemented. Depends on module 01 account linking and Telegram delivery.

## Goal

Send the owner a concise weekly view of study attendance and subject progress on a configurable scheduled time.

## Default reporting behavior

- Default delivery: Sunday at 18:00 in `APP_TIMEZONE` (currently `Asia/Kolkata`). Make weekday/time configurable so the user can change it later.
- Summarize the completed Monday–Sunday week, not a partial current week. Use explicit start/end dates in the message.
- Use the active semester by default and clearly name it. A week with no active-semester dates or no expected sessions should produce a brief no-activity message rather than misleading zeros.

## Implementation steps

1. [ ] Add per-user weekly-summary enabled, weekday, and local-time preferences. Validate the timezone against the app's configured timezone policy.
2. [ ] Add an idempotency key unique to owner, semester, week-start date, and notification type so retries do not send duplicate reports.
3. [ ] Build the summary from `AttendanceStatistics` and existing expected-session calculations. Include expected sessions, marked sessions, present, absent, unmarked expected sessions, attendance rate (`present / (present + absent)`), and subject target progress.
4. [ ] Keep all calculations owner-scoped and bound to the active semester's inclusive dates, timetable effective dates, and the completed week's dates. Expected-but-unmarked sessions must not count as absent or enter the attendance-rate denominator.
5. [ ] Schedule summary dispatch at each user's configured local weekday/time. Queue delivery, use scheduler overlap protection, and rely on the unique idempotency record as the final duplicate safeguard.
6. [ ] Format the Telegram message with the semester, reporting window, a small totals section, and the subjects needing attention. Keep the message concise and split it safely if Telegram's message length limit is reached.
7. [ ] Add a dry-run command such as `telegram:send-weekly-summary --week-start=YYYY-MM-DD --user=<id> --dry-run` that previews computed statistics without sending.

Laravel's scheduler supports weekly/day/time scheduling and timezone-specific events. Use `withoutOverlapping()` for the dispatch task; the outbox uniqueness check still protects against retries and multiple invocations. For local development, Laravel provides `php artisan schedule:work`. [Laravel 13 task scheduling](https://laravel.com/framework/docs/13.x/scheduling)

## Acceptance checks

- A completed week is calculated Monday through Sunday in the configured timezone and clipped to the selected semester range.
- The message totals match dashboard and attendance-page statistics for the same owner, semester, and date window.
- Unmarked expected sessions are reported separately and do not count as absent.
- Each linked, enabled user receives at most one summary per semester/week.
- No message is sent for an unlinked or disabled account; dry-run never contacts Telegram.

## Verification plan

- Test reports at Sunday/Monday boundaries, semester start/end, empty weeks, unmarked sessions, zero marked sessions, and completed-subject targets.
- Test per-user schedule preferences, duplicate jobs, retries, timezone behavior, and message splitting.
- Compare a dry-run report with the same date-filtered Filament attendance/dashboard totals.
