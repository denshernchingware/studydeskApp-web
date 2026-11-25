# Module 2: Subject management

## Implementation status

The subject migration, owner relationship, owner-scoped Filament resource, searchable list, create/edit validation, and confirmed single-record deletion are implemented. Subject creation assigns both the signed-in owner and their active semester on the server. The list defaults to the active-semester filter and offers prior semesters and legacy unassigned records as filters. Timetable slots cascade when their subject is deleted, and attendance records cascade through those slots.

Subject-level weekly-slot counts, attendance rates, completion state, and page summaries remain for the shared-statistics module. Timetable-slot relations and cascading attendance cleanup are implemented in modules 03 and 04.

## Goal

Rebuild the source's subject list and add/edit/delete flows as an owner-scoped Filament resource.

## Source behavior

- Fields: subject name (required, max 70), short code (optional, max 12), description (optional), color (one of violet, blue, green, orange, pink, teal), required session count (required positive integer).
- List supports searching by name or code.
- Each subject shows weekly slot count, attendance rate when marks exist, and progress toward its required-session target.
- The subjects page summary shows total subjects, weekly slots, the best attendance rate among marked subjects, and marked subjects below 75%.
- A subject becomes complete when `present + absent >= required sessions`; attendance becomes view-only after completion.
- Deleting a subject also deletes its timetable slots and marks after confirmation.

## Implementation steps

1. [x] Create the subject migration and model with owner, semester, and timestamps. Add indexes and a composite constraint preventing a subject from referencing another owner's semester.
2. [x] Define the subject-to-user and subject-to-slot relationships, with slots cascading on subject deletion. Attendance records cascade through the deleted slots.
3. [x] Generate a Filament v5 Subject resource with a schema for the source fields and a searchable table for name/code.
4. [x] Validate name length, code length, color choices, and positive integer target on both create and edit.
5. [x] Scope every query and mutation to the current owner and default subject listing to the active semester. Ensure a crafted record URL cannot open or update another user's subject.
6. [ ] Add table columns or infolist details for code, target, marked count, attendance rate, and completion state. Keep computed values out of duplicated stored columns. (Code and target are implemented; attendance-derived values depend on module 04.)
7. [x] Add an explicit delete confirmation that says linked timetable slots and attendance marks will also be removed, matching the source's destructive behavior. Both cascades are implemented.
8. [ ] Add the subjects summary as a page header or dashboard widget only after the shared statistics logic exists; avoid duplicating formulas in the resource and dashboard.

## Acceptance checks

- A user can create, search, edit, and delete only their own subjects.
- New subjects are assigned to the user's active semester, and the list defaults to that semester while allowing historical semester filters.
- A subject cannot reference another user's semester; records created before semester support remain available under the legacy filter.
- Invalid or missing required-session targets are rejected.
- Completion is derived from marked attendance and does not treat unmarked sessions as marks.
- Deleting a subject follows the documented cascade behavior and does not leave orphaned slots or marks.
- The most-attended and below-75% summaries ignore subjects with no marks.

## Verification plan

- Test field validation, search, owner isolation, completion calculations, and delete behavior.
- Test boundary targets: one session, exactly at target, and more marks than target from legacy/imported data.
