# Module 4: Attendance

## Implementation status

Implemented as an owner-scoped Filament Attendance page with date and semester filters, recurring-session eligibility, atomic Present/Absent toggles, subject target lockout, semester summaries, and recent history. Attendance records have a unique owner/slot/date constraint and cascade when their timetable slot is removed.

## Goal

Replace localStorage attendance marks with authenticated, persistent records tied to recurring timetable slots.

## Source behavior

- The selected date lists only slots scheduled for that weekday and already effective on that date.
- The selected date must be inside the selected semester's inclusive date range. The page defaults to the active semester and can filter to a previous semester.
- Users mark a session Present or Absent. Clicking the selected state clears it; unmarked sessions remain unmarked.
- Future dates cannot be marked.
- Attendance is view-only for a subject after its marked count reaches the required-session target.
- The source reports expected, present, absent, and attendance rate by subject, plus the ten most recent marked sessions.
- Rate is `present / (present + absent)`, or unset when no marks exist. Expected-but-unmarked sessions do not count as absent.

## Implementation steps

1. [x] Create an attendance-record migration and model with owner, timetable slot, session date, status (`present` or `absent`), and timestamps.
2. [x] Enforce at most one record per owner/slot/date. Add indexes for date history and slot lookups, with a composite owner/slot foreign key.
3. [x] Build a date-aware Filament page for the selected day's sessions. Use the shared `APP_TIMEZONE` policy from the foundation module.
4. [x] Derive expected sessions from recurring slots, their weekday, `effective_from`, the learner's study start date, and the selected semester's start/end dates. Do not generate absence records for unmarked sessions.
5. [x] Add Present/Absent actions that can toggle the same status off. Validate the selected date is not in the future, is inside the selected semester, and that the slot belongs to the current owner and selected day.
6. [x] Before writing a mark, recalculate the subject's marked count and enforce its target inside a transaction serialized on the owner and subject records.
7. [x] When the target is reached, render attendance as view-only while still allowing review of the saved status.
8. [x] Add a subject summary table with expected occurrences through today within the selected semester, present, absent, rate, and target progress.
9. [x] Add recent history sorted by session date and start time, newest first.
10. [x] Keep delete behavior consistent with the timetable module: removing a slot removes its attendance records through a cascading foreign key. Confirm this loss before deletion.

## Acceptance checks

- Only scheduled sessions appear for the selected date.
- Attendance dates outside the selected semester range are rejected, and historical filters show only marks linked to that semester's subjects.
- Future sessions cannot be marked, and unscheduled days do not change expected totals.
- Present/Absent can be set, changed, and cleared while the subject remains below its target.
- Unmarked expected sessions do not reduce the attendance rate.
- A completed subject cannot receive further marks or edits to marks.
- History is owner-scoped and ordered newest first.

## Verification plan

- Test status toggling, date boundaries, no-slot days, future dates, target completion, simultaneous/duplicate writes, and the present-rate denominator.
- Test that removing a slot cleans up its own marks only.
