# Module 3: Weekly timetable

## Implementation status

The owner-scoped, semester-filtered Filament resource, weekday/time/subject filters, Monday-to-Sunday grouped schedule, weekly totals, and transactional overlap validation are implemented. New slots must use a subject in the active semester; editing preserves the original effective date and semester. Deleting a subject cascades to its slots. Slot deletion is confirmed, and attendance-history cleanup will be enforced when module 04 adds attendance records.

## Goal

Rebuild recurring weekly study slots and the seven-day schedule view.

## Source behavior

- A slot has one subject, weekday, start time, end time, optional label (max 60), and effective start date.
- A slot belongs to its subject's semester. The default list shows the active semester, and a semester filter opens historical schedules without changing their records.
- Slot effective dates and expected weekly occurrences are bounded by the semester start and end dates.
- Days repeat weekly; Sunday is day `0`, Monday is day `1` through Saturday `6`.
- End time must be later than start time.
- Any overlapping slot in the same semester and weekday is rejected, including a slot for a different subject. Touching end/start times are allowed.
- The timetable shows slot count, scheduled subject count, total weekly hours, and slots grouped Monday through Sunday.
- Editing a slot preserves its original effective start date. Deleting it also deletes its attendance marks.

## Implementation steps

1. [x] Create the timetable-slot migration and model with owner, subject, weekday, local start/end times, optional label, and `effective_from` date. A slot inherits its semester from the subject.
2. [x] Add relationships to the owner and subject. Ensure a slot cannot reference another owner's subject or be created outside the selected semester.
3. [x] Generate an owner-scoped Filament resource for slot CRUD, with subject, weekday, and semester filters and sensible sorting by weekday and start time.
4. [x] Validate weekday range, start/end values, and label length. Reject end times less than or equal to start time.
5. [x] Implement overlap validation in a reusable application action, excluding the current slot during edits. Keep the check owner-scoped and transactional to reduce race conditions.
6. [x] Preserve `effective_from` when editing. A change to weekday or time affects future expected sessions only; it must not rewrite historical attendance. Expected-session date clamping follows with module 04.
7. [x] Provide a grouped Monday-to-Sunday schedule and a clear add-slot entry point. Guide the user to create a semester or subject when the active semester has no available subjects.
8. [x] Calculate weekly session count, distinct subjects scheduled, and total hours from the owner's slots in the selected semester.
9. [x] Confirm before deleting a slot and explain that its attendance history is removed, matching the source behavior. Enforce attendance cascade when module 04 creates those records.

## Acceptance checks

- A valid slot is visible on the correct weekday with formatted start/end times.
- Overlapping slots on the same day and in the same semester are rejected; adjacent slots are accepted.
- Editing preserves the effective start date and excludes the edited record from conflict detection.
- Weekly totals use the current owner's slots only.
- The default schedule and weekly totals use the active semester; historical semester filters do not alter slot ownership.
- Deleting a slot removes its related attendance marks without affecting other slots.

## Verification plan

- Test Sunday/Monday weekday mapping, equal start/end, reversed times, overlapping intervals, adjacent intervals, and cross-subject conflicts within one semester.
- Test owner isolation and that edits do not alter past attendance dates.
