# Module 1: Telegram account linking and webhook

## Status

Implemented. The account-linking and webhook foundation is available. Configure the bot credentials and run a queue worker before linking a real Telegram chat.

## Goal

Let the authenticated StudyDesk owner link a private Telegram chat, receive bot messages, and safely deliver incoming Telegram messages and button callbacks to Laravel.

## Implementation steps

1. [x] Confirm installed dependencies and existing app conventions. Use Laravel's HTTP client and Telegram's official Bot API; no Telegram package dependency was added.
2. [x] Add `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, and `TELEGRAM_WEBHOOK_SECRET` configuration. Read secrets through `config()` and keep `.env.example` values empty.
3. [x] Create owner-scoped Telegram account, single-use token, and update records. Unique constraints prevent one Telegram identity or webhook update from being linked/processed twice.
4. [x] Add the authenticated Filament Telegram settings page. It generates a random 10-minute link and stores only the SHA-256 token hash.
5. [x] Handle `/start <token>` only in private chats. Validate token expiry and one-time use, then associate Telegram sender/chat IDs with the token's StudyDesk owner.
6. [x] Register `POST /telegram/webhook`, exclude only that URI from CSRF checks, and validate Telegram's webhook secret header with a constant-time comparison.
7. [x] Persist supported private message/callback update IDs and enqueue each unprocessed update through a unique queue job. Group chats are ignored; raw message text is not stored.
8. [x] Add a Bot API client for `sendMessage` and `answerCallbackQuery`, with explicit timeouts, sanitized failures, and bounded queue retries.
9. [x] Add a confirmed disconnect action that deletes the linked account and stops future processing for that chat.

Telegram webhook updates are HTTPS POST requests. Configure `setWebhook` with the secret token and validate the matching request header on every update. Telegram may retry unsuccessful webhook deliveries, so update processing must be idempotent. [Telegram webhook API details](https://core.telegram.org/bots/api#setwebhook)

## Acceptance checks

- An expired, reused, malformed, or unknown link token cannot link a chat.
- Only the private Telegram account that redeems the token becomes linked to that StudyDesk owner.
- Requests with an invalid webhook secret cannot enqueue work or invoke the AI agent.
- Repeated webhook delivery of one `update_id` produces one processing job.
- An unlinked account, group chat, or disabled account receives no owner data and cannot trigger app actions.
- Secrets do not appear in source control, application responses, or logs.

## Local setup

Set `TELEGRAM_BOT_TOKEN`, `TELEGRAM_BOT_USERNAME`, and `TELEGRAM_WEBHOOK_SECRET` in the local environment, then run `php artisan config:clear`. Run a queue worker with `php artisan queue:work`. Open **Admin → Telegram**, generate a link, and redeem it in a private chat with the bot. Configure Telegram's webhook to the public HTTPS `/telegram/webhook` URL before expecting incoming updates; local Herd `.test` sites need an HTTPS tunnel.

## Verification plan

- After implementation, test valid, invalid, expired, and replayed link tokens.
- Test webhook secret validation and duplicate `update_id` handling with Laravel HTTP fakes.
- Test unlinking and owner isolation for inbound and outbound Telegram service calls.
- Use a public HTTPS development tunnel for Telegram webhook testing; a local Herd `.test` host is not reachable by Telegram.
