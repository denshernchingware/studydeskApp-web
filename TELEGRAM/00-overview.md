# Telegram integration implementation plan

## Status

Modules 00 through 02 are implemented: Telegram configuration, owner-scoped linking, the Filament settings page, webhook validation, scheduled reminders, and attendance buttons are in place. Weekly summaries and the AI study advisor remain planned in modules 03 through 05.

## Goal

Connect the authenticated StudyDesk account to a private Telegram bot that can:

- Send each scheduled subject reminder 15 minutes before its start time.
- Let the owner mark that day's scheduled sessions Present or Absent using Telegram buttons.
- Answer study-planning questions using the owner's semester, timetable, attendance, targets, and syllabus data when available.
- Send a weekly statistics summary at a configurable local time.

## Existing app rules to preserve

- Domain records are owner-scoped and belong to a semester through subject and timetable relationships.
- Default to the active semester; allow an explicit historical semester when reviewing prior records.
- Expected occurrences use timetable weekday, `effective_from`, study start date, and inclusive semester dates.
- Attendance is Present or Absent; unmarked expected sessions stay unmarked. A completed subject is read-only.
- Future sessions cannot be marked. Reuse `SaveAttendanceRecord` and `AttendanceStatistics` rather than implementing Telegram-specific attendance rules.
- Use `APP_TIMEZONE` (currently `Asia/Kolkata`) for session dates, reminder times, and report windows.

## Module order

1. [Bot account linking and secure webhook](01-bot-linking-and-webhook.md)
2. [Subject reminders and attendance buttons](02-reminders-and-attendance.md)
3. [Weekly statistics summary](03-weekly-summary.md)
4. [Laravel AI SDK study advisor and tools](04-ai-study-advisor.md)
5. [Configuration, scheduled commands, and manual verification](05-setup-and-operations.md)

Complete modules in order. Modules 02to04 depend on secure account linking and Telegram delivery from module 01.

## Cross-module decisions

- Permit a single private Telegram chat linked to each StudyDesk user initially. Never accept bot commands or buttons from an unlinked chat or group.
- Use Telegram's official Bot API through Laravel's HTTP client unless a package is justified after checking the installed dependencies.
- Require a verified webhook secret header, deduplicate Telegram update IDs, queue slow AI/delivery work, and keep bot/Groq secrets in environment configuration.
- Track outbound deliveries with a unique idempotency key so scheduler overlap, retry, or a repeated webhook cannot create duplicate reminders, marks, or weekly summaries.
- Treat AI output as an explanation of app data, not as the source of attendance or schedule facts. The model cannot modify records through AI tools.
- Keep each module's test/verification commands as planned commands until that module is implemented. They are not available yet.

## Official references

- [Laravel AI SDK for Laravel 13](https://laravel.com/framework/docs/13.x/ai-sdk)
- [Laravel task scheduling for Laravel 13](https://laravel.com/framework/docs/13.x/scheduling)
- [Telegram Bot API](https://core.telegram.org/bots/api)
- [Telegram bot features and keyboards](https://core.telegram.org/bots/features)
