# Module 4: AI study advisor and app-data tools

## Status

Implemented. Depends on Telegram account linking. The AI SDK conversation tables and Telegram AI fields are installed by the application migrations.

## Goal

Use Laravel AI SDK with Groq to answer the linked owner's Telegram questions, especially which subjects to prioritize to make progress toward coverage targets.

## Data limitation

StudyDesk stores subjects, timetable slots, attendance, and required-session targets. It does not store chapters, readings, syllabus topics, or completed pages. The advisor may recommend subjects and available sessions from stored progress, but it must not invent chapter names or claim exact syllabus coverage.

## Implementation steps

1. [x] Confirm Laravel 13 and install `laravel/ai` v1.4.2. Publish AI configuration and conversation migrations.
2. [x] Configure Groq with `GROQ_API_KEY` and `GROQ_MODEL`. The model defaults to `openai/gpt-oss-20b`; the existing `GROQ_API` environment name is also accepted as a fallback.
3. [x] Add the StudyAdvisor agent with grounding, brief reasoning, and missing-syllabus instructions.
4. [x] Add read-only tools for semester subjects and targets, upcoming schedule, attendance progress, and deterministic subject priorities.
5. [x] Bind tools to the Telegram-linked owner and their active semester. Tool schemas do not accept user or semester IDs.
6. [x] Give each prompt a compact owner-scoped snapshot of subjects, progress, schedule, priorities, timezone, and semester dates.
7. [x] Persist AI SDK conversations for the owner, keep the conversation ID on the Telegram account, check participant ownership before continuing, and start a new conversation after semester changes. Limit loaded history to 24 messages.
8. [x] Queue incoming private-chat text after webhook validation. Reject input over 2,000 characters, rate limit to 10 questions per owner per minute, use the Telegram outbox for replies, and return a safe fallback on provider failure.
9. [x] Keep all AI tools read-only. Attendance remains controlled by explicit Telegram attendance buttons.
10. [ ] Run the manual prompts below and confirm responses use stored progress and disclose missing topic-level data.

## Local setup and manual check

Run database migrations:

```powershell
php artisan migrate
```

Set `GROQ_API_KEY` in the environment. The app also accepts the existing `GROQ_API` variable as a fallback. Change `GROQ_MODEL` if you want another Groq model that supports tool calling. Keep keys out of source control.

Run a queue worker and message the linked private Telegram bot chat with prompts such as:

```text
Which subjects should I read to cover my targets?
What should I study next based on my timetable and attendance?
Which chapters should I read for my weakest subject?
```

The advisor should use stored subject, target, attendance, and schedule data for the first two prompts. For the chapter question, it should explain that syllabus and chapter information is not stored and must not invent chapter names. A real Groq request occurs only when a linked Telegram user sends a text question.

## Acceptance checks

- Telegram questions are processed only after webhook validation and linked-owner verification.
- Every tool is constrained to the linked owner's active semester.
- The advisor distinguishes recorded values from recommendations and does not invent reading topics.
- Missing syllabus/topic data is stated clearly when requested.
- Conversation history is owner-scoped, semester-aware, and bounded.
- AI tools cannot write attendance or alter semester data.

## Manual verification checklist

- [ ] Check owner isolation and semester/date bounds for each tool.
- [ ] Check prioritization, empty data, missing syllabus, provider failures, throttling, and oversized messages.
- [ ] Confirm conversation continuity and Telegram outbox delivery from a linked private chat.
