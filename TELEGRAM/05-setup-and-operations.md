# Module 5: Configuration, schedules, and local operations

## Status

Not implemented. This guide describes the eventual setup and manual verification workflow; the commands below may not exist until the preceding modules are implemented.

## Configuration checklist

- `TELEGRAM_BOT_TOKEN`: token from BotFather, secret.
- `TELEGRAM_WEBHOOK_SECRET`: high-entropy secret used with Telegram's webhook header.
- `TELEGRAM_BOT_USERNAME`: bot username used to construct the private linking URL.
- `GROQ_API_KEY`: already supplied by the owner; do not print its value. The SDK should consume it via config/environment.
- `GROQ_MODEL` or equivalent app config: supported Groq model selected after checking provider availability.
- `APP_TIMEZONE`: keep `Asia/Kolkata` unless the user chooses a different timezone.
- Queue configuration: use a worker for outgoing reminders, AI responses, and weekly summaries; keep queue retries/backoff bounded.

Add variable names, but never real credentials, to `.env.example`. Ensure deployment/local logs and diagnostics redact Telegram/Groq secrets.

## Scheduler and queue implementation steps

1. [ ] Register a minute-level reminder dispatch schedule and per-user weekly-summary schedule in the Laravel 13 scheduler using the app timezone.
2. [ ] Add `withoutOverlapping()` for scans and use `onOneServer()` only if the app is later deployed with multiple scheduler hosts and a shared supported cache backend.
3. [ ] Schedule only dispatch/scanning work; queue network calls and AI prompts so scheduler ticks remain short.
4. [ ] Make outbox uniqueness and Telegram `update_id` uniqueness authoritative for idempotency; scheduler locks alone are not sufficient.
5. [ ] Add health/error logging with safe context such as delivery type, owner record ID, update ID, and error category. Exclude tokens and sensitive prompt payloads.
6. [ ] Add CLI commands for dry-run reminders, dry-run weekly reports, webhook registration/status (or documented Bot API calls), and an optional real Groq smoke test with an explicit prompt.

## Local test sequence after implementation

Run commands from the project root, each in its own terminal if it needs to stay running:

```powershell
# Confirm registered schedule entries
php artisan schedule:list

# Run the scheduler in the foreground; stop with Ctrl+C
php artisan schedule:work

# Process queued Telegram and AI jobs
php artisan queue:work

# Preview a reminder without sending (planned command)
php artisan telegram:send-reminders --date=2026-11-25 --dry-run

# Preview the completed week's report without sending (planned command)
php artisan telegram:send-weekly-summary --week-start=2026-11-16 --user=1 --dry-run

# Optional real provider smoke test (planned command; sends a request to Groq)
php artisan ai:telegram-smoke-test
```

Replace sample dates/user IDs with the test account and current dates. The dry-run options must guarantee that no Telegram API or attendance-write request is made.

## Webhook testing

Telegram requires a publicly reachable HTTPS webhook endpoint. A local Herd `.test` domain cannot receive Telegram's requests directly; use a secure HTTPS development tunnel and configure `setWebhook` only for that temporary endpoint. A webhook and `getUpdates` long polling cannot be active at the same time. For production, point the webhook at the deployed HTTPS domain and keep the secret token configured in Laravel. [Telegram `setWebhook` and polling behavior](https://core.telegram.org/bots/api#setwebhook)

## Acceptance checks

- `schedule:list` shows the minute reminder scan and the configured weekly summary.
- `schedule:work` can run repeatedly without duplicate notifications; queue workers deliver jobs and honor retries.
- Dry-run commands print the target session/report window and computed data without network sends or mutations.
- The real webhook works only on a valid HTTPS endpoint with the correct secret header.
- A real AI smoke-test requires explicit execution and reports a safe pass/failure without printing API credentials.
