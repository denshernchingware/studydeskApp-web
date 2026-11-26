# Module 5: Configuration, schedules, and local operations

## Status

Implemented. Commands are available for scheduler inspection, dry-run previews, Telegram webhook management, and an explicit Groq smoke test. No real provider or Bot API call is made unless its corresponding command or a linked Telegram message is run.

## Configuration

- `TELEGRAM_BOT_TOKEN`: secret from BotFather.
- `TELEGRAM_WEBHOOK_SECRET`: 1-256 letters, numbers, underscores, or hyphens; sent to Telegram when registering the webhook and checked on incoming requests.
- `TELEGRAM_BOT_USERNAME`: used to create Telegram linking URLs.
- `GROQ_API_KEY`: AI SDK key. The project also supports its previous `GROQ_API` name as a fallback.
- `GROQ_MODEL`: optional supported Groq model; defaults to `openai/gpt-oss-20b`.
- `APP_TIMEZONE`: controls timetable and Telegram schedule times; currently `Asia/Kolkata`.
- Queue and cache: the scheduler scans due work; queue workers send Telegram and Groq requests. Keep queue attempts bounded and use a cache store that supports locks for unique jobs.

`.env.example` contains variable names and safe defaults only. Do not commit real Telegram or Groq credentials.

## Scheduler and delivery

1. [x] Register minute-level reminder and per-account weekly-summary scans using the app timezone.
2. [x] Use `withoutOverlapping()` on scheduler scans.
3. [x] Queue network sends and AI work outside scheduler scans and the webhook controller.
4. [x] Use the Telegram `update_id` uniqueness and database outbox dedupe keys as idempotency safeguards.
5. [x] Log safe failure context: delivery/update/user IDs, delivery type, and exception class. Do not log credentials, prompts, chat IDs, or message text.
6. [x] Provide reminder and weekly-summary dry runs, webhook info/set/delete commands, and a Groq smoke-test command that requires an explicit prompt.

## Local setup and command sequence

Run migrations after pulling changes:

```powershell
php artisan migrate
```

Check schedule registration:

```powershell
php artisan schedule:list
```

Run the scheduler in one terminal and the queue worker in another:

```powershell
php artisan schedule:work
php artisan queue:work
```

Preview work without contacting Telegram:

```powershell
php artisan telegram:send-reminders --date=YYYY-MM-DD --time=HH:MM --dry-run
php artisan telegram:send-weekly-summary --week-start=YYYY-MM-DD --user=1 --dry-run
```

The weekly-summary start date must be a Monday for a completed week. Dry-run commands do not write delivery records or call Telegram.

Inspect Telegram webhook status (the output omits the URL path and credentials):

```powershell
php artisan telegram:webhook info
```

Register the public HTTPS URL shown by `php artisan route:list --name=telegram.webhook`:

```powershell
php artisan telegram:webhook set --url=https://your-public-host.example/telegram/webhook
```

Remove a webhook while retaining pending Telegram updates:

```powershell
php artisan telegram:webhook delete
```

To explicitly discard pending updates, add `--drop-pending-updates`. Telegram webhooks require a publicly reachable HTTPS endpoint; a local Herd `.test` URL is not publicly reachable. Use an HTTPS development tunnel for local webhook work. Telegram webhook and `getUpdates` long polling cannot be active at the same time. See [Telegram webhook setup](https://core.telegram.org/bots/webhooks) and [Bot API webhook methods](https://core.telegram.org/bots/api#setwebhook).

Run a real Groq request from the console without sending a Telegram message:

```powershell
php artisan ai:telegram-smoke-test --user=1 --question="Which subjects should I focus on this week?"
```

This command requires an active linked user and semester and makes one real Groq request. Normal Telegram questions also call Groq when their queued job runs.

## Acceptance checks

- `schedule:list` shows both minute-level dispatch scans.
- Scheduler and queue workers run independently and repeated scans do not create duplicate deliveries.
- Reminder and weekly-summary dry runs report expected work without writes or network calls.
- `telegram:webhook info` reports whether a webhook is registered without revealing its full URL.
- The webhook accepts only Telegram requests with the configured secret and is registered to a public HTTPS URL.
- The Groq smoke-test requires an explicit prompt and reports success/failure without printing credentials or sending a Telegram message.
- Logs contain operational IDs and failure categories but no tokens, prompts, chat IDs, or private message content.
