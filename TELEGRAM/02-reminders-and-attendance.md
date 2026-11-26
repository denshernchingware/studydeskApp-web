# Module 2: Subject reminders and attendance buttons

## Status

Implemented. The minute-level scheduler, idempotent reminder outbox, Present/Absent buttons, callback ownership checks, and shared scheduled-start attendance rule are in place.

## Goal

Send a reminder 15 minutes before each current-semester study slot and provide Present/Absent actions for that scheduled session.

## Implementation steps

1. [x] Add the per-user `reminders_enabled` preference, defaulting to enabled for newly linked accounts, with an enable/pause action in Telegram settings. Use `APP_TIMEZONE` (`Asia/Kolkata`).
2. [x] Add an outbox record keyed by owner, slot, and session date. It stores status, attempt count, Telegram message ID, and safe failure metadata without credentials.
3. [x] Schedule an every-minute scan for the session starting 15 minutes after the current local minute. Filter by active semester, inclusive semester dates, study start date, slot effective date, weekday, linked account, and reminder preference.
4. [x] Send the subject, local start time, optional slot label, and Present/Absent buttons. A unique outbox key prevents duplicate scheduling. The send job makes one API attempt because Telegram does not provide a send-message idempotency key.
5. [x] Use random opaque callback tokens within Telegram's 1-to-64-byte limit. Persist only the token hash for lookup and an encrypted token for queue redelivery, with owner/slot/date/status and expiry attached.
6. [x] Validate the private sender/chat against the linked account and verify token expiry and ownership before processing a callback. `SaveAttendanceRecord` enforces semester boundaries, weekday/effective date, target, and owner checks.
7. [x] Reuse `SaveAttendanceRecord` through its idempotent `set` operation for Telegram callbacks; the Filament page keeps its existing toggle behavior. User/status IDs never come from callback data.
8. [x] Enforce attendance at or after the scheduled start time in the shared attendance action. Early button presses create no record and receive an explanatory callback response.
9. [x] After a mark, replace the Telegram reminder text with the saved status and remove its buttons. A completed subject remains read-only under the shared attendance rules.
10. [x] Add `telegram:send-reminders` with dry-run and simulated `--date` / `--time` options. Dry-run performs no writes or Telegram requests.

Telegram inline keyboards deliver button presses as callback queries. Telegram requires callback data to be 1 to 64 bytes and clients keep showing a progress indicator until the callback is answered. [Telegram inline keyboard and callback API](https://core.telegram.org/bots/api#inlinekeyboardbutton) [Callback acknowledgement](https://core.telegram.org/bots/api#answercallbackquery)

## Local commands

```powershell
# Preview reminders due 15 minutes after the simulated time; no writes or sends
php artisan telegram:send-reminders --date=2026-11-26 --time=09:45 --dry-run

# Run the due-reminder scan at the current time
php artisan telegram:send-reminders

# Keep the scheduled scan running locally; stop with Ctrl+C
php artisan schedule:work

# Process queued Telegram messages
php artisan queue:work
```

## Acceptance checks

- A slot reminder is queued exactly once for a linked owner, on the correct local day, 15 minutes before its start.
- Slots outside the active semester, semester dates, effective dates, or selected weekday are not sent.
- A retry or repeated scheduler invocation does not send a second reminder for the same slot/date.
- Only the linked owner can mark the session; forged, expired, early, wrong-date, future-date, or wrong-semester callbacks create no record.
- Present/Absent changes are reflected in existing attendance history and statistics; target lockout remains enforced.
- Dry-run commands report intended messages but make no Telegram request or attendance change.

## Verification plan

- Test reminder boundaries around `start_time - 15 minutes`, including midnight, semester edges, and DST-safe timezone handling where relevant.
- Test duplicate scheduler runs, Telegram retries, callback ownership, action expiry, early taps, target completion, and existing toggle behavior.
- Use Telegram's test bot or a linked development chat only after the dry-run output is correct.
