# Module 4: AI study advisor and app-data tools

## Status

Not implemented. Depends on modules 01 and 05. The Laravel AI SDK is not installed yet.

## Goal

Use Laravel's AI SDK with the existing Groq API key to answer the linked owner's Telegram questions, especially which subjects to prioritize or study to make progress toward coverage goals.

## Important data limitation

The app currently stores subjects, timetable slots, attendance, and required-session targets. Those records do not describe chapters, readings, syllabus topics, or which pages the learner has completed. Until a syllabus/topic/reading-coverage module exists, the advisor may recommend **subjects and available study sessions** from schedule and progress data, but it must not invent chapter names or claim that it can calculate exact syllabus coverage. It should state when the data is insufficient and request or point to the missing syllabus information.

## Implementation steps

1. [ ] Confirm the installed Laravel version and check the current Laravel AI SDK documentation before installing. Install `laravel/ai` only as part of the approved implementation. Publish the AI configuration and conversation migrations, then run migrations.
2. [ ] Confirm `GROQ_API_KEY` is present without printing its value. Configure a supported Groq text model through application configuration/environment, keeping model choice configurable rather than hard-coding a model name.
3. [ ] Create a dedicated study-advisor Agent with clear instructions: use supplied tool results for facts, distinguish stored facts from recommendations, show its reasoning briefly, do not fabricate syllabus/reading data, and acknowledge uncertainty.
4. [ ] Create narrowly scoped read-only AI tools using Laravel AI SDK's `Tool` contract and schema/handler pattern. Suggested tools:
   - `GetSemesterSubjects`: current or explicitly selected semester subjects and target counts.
   - `GetStudySchedule`: upcoming/available slots and local start times.
   - `GetAttendanceProgress`: expected, present, absent, unmarked, rate, and target progress per subject.
   - `RecommendSubjectPriorities`: deterministic app-side ranking from incomplete targets, recent attendance, and upcoming schedule; return the factors so the model can explain the result.
5. [ ] Bind every tool to the already linked authenticated owner. Do not allow a model-supplied user ID, arbitrary semester ID from another owner, raw SQL, filesystem access, general web search, or hidden application actions.
6. [ ] Give the agent semester-aware context: active semester and date range, current local date/time, configured timezone, study start date, timetable, and relevant existing progress. Minimize payloads and limit returned history/data to what the question needs.
7. [ ] Persist Telegram conversation history with the AI SDK conversation support or a project-conforming owner-scoped mapping. Store the Telegram chat-to-conversation ID mapping with a unique owner/chat relationship, and ensure a linked chat can access only its own conversation.
8. [ ] Process incoming text via the queue after webhook validation. Apply message length/rate limits, handle unsupported input and provider failures gracefully, and send a concise Telegram response. Do not pass bot tokens, API keys, or unrelated profile data to the model.
9. [ ] Keep AI tools read-only in this module. Attendance changes remain explicit Telegram button actions; any future AI-requested write must require a separate confirmation step and authorization.
10. [ ] Add focused agent/tool tests using the SDK's testing helpers or faked provider responses. Include prompts such as “Which subjects should I read to cover my targets?” and verify the answer uses actual semester and progress values and states when topic-level data is missing.

Laravel's AI SDK supports Groq, custom agents/tools, and persisted conversations. A tool defines a purpose, JSON schema, and handler; tools should expose only the capabilities the app intends to authorize. [Laravel AI SDK installation and Groq configuration](https://laravel.com/framework/docs/13.x/ai-sdk#installation) [Laravel AI tools](https://laravel.com/framework/docs/13.x/ai-sdk#tools) [Laravel AI conversations](https://laravel.com/framework/docs/13.x/ai-sdk#remembering-conversations)

## Acceptance checks

- Telegram questions are processed only after verifying the webhook and linked owner.
- Tool results are constrained to the authenticated owner's selected/active semester.
- The advisor accurately distinguishes measured data from its recommendation and does not invent reading topics.
- If the user asks for specific chapters or syllabus coverage when none is stored, the advisor says the app lacks that data and asks for the syllabus/topic list.
- Conversation history is isolated per linked owner/chat, bounded, and persisted through the configured AI SDK storage.
- An AI tool call cannot write attendance, alter semester data, access another user's records, or make an external action.

## Verification plan

- Test owner isolation in every tool and cross-semester/date scoping.
- Test a normal prioritization request, an empty-data request, a missing-syllabus request, provider timeout/rate-limit behavior, and malformed/oversized Telegram messages.
- Use a mocked/faked AI provider for tests; reserve real Groq requests for an explicit local smoke-test command after configuration is complete.
