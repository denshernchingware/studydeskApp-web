# Module 3: Weekly timetable

## Goal

Rebuild recurring weekly study slots and the seven-day schedule view.

## Source behavior

- A slot has one subject, weekday, start time, end time, optional label (max 60), and effective start date.
- A slot belongs to its subject's semester. The default list shows the active semester, and a semester filter opens historical schedules without changing their records.
- Slot effective dates and expected weekly occurrences are bounded by the semester start and end dates.
- Days repeat weekly; Sunday is day `0`, Monday is day `1` through Saturday `6`.
- End time must be later than start time.
- Any overlapping slot on the same weekday is rejected, including a slot for a different subject. Touching end/start times are allowed.
- The timetable shows slot count, scheduled subject count, total weekly hours, and slots grouped Monday through Sunday.
- Editing a slot preserves its original effective start date. Deleting it also deletes its attendance marks.

## Implementation steps

1. Create the timetable-slot migration and model with owner, subject, weekday, local start/end times, optional label, and `effective_from` date. A slot inherits its semester from the subject.
2. Add relationships to the owner and subject. Ensure a slot cannot reference another owner's subject or be created outside the selected semester.
3. Generate an owner-scoped Filament resource for slot CRUD, with subject and weekday filters and sensible sorting by weekday and start time.
4. Validate weekday range, start/end values, and label length. Reject end times less than or equal to start time.
5. Implement overlap validation in a reusable domain service or model-level application action, excluding the current slot during edits. Keep the check owner-scoped and transactional to reduce race conditions.
6. Preserve `effective_from` when editing a slot. A change to weekday or time affects future expected sessions only; it must not rewrite historical attendance. Clamp expected occurrences to the semester boundaries.
7. Provide a weekly page or grouped table that makes the Monday-to-Sunday schedule easy to scan. Include add-slot actions prefilled with the selected weekday.
8. Calculate weekly session count, distinct subjects scheduled, and total hours from the owner's slots in the selected semester.
9. Confirm before deleting a slot and explain that its attendance history is removed, matching the source behavior.

## Acceptance checks

- A valid slot is visible on the correct weekday with formatted start/end times.
- Overlapping slots on the same day are rejected; adjacent slots are accepted.
- Editing preserves the effective start date and excludes the edited record from conflict detection.
- Weekly totals use the current owner's slots only.
- The default schedule and weekly totals use the active semester; historical semester filters do not alter slot ownership.
- Deleting a slot removes its related attendance marks without affecting other slots.

## Verification plan

- Test Sunday/Monday weekday mapping, equal start/end, reversed times, overlapping intervals, adjacent intervals, and cross-subject conflicts.
- Test owner isolation and that edits do not alter past attendance dates.
