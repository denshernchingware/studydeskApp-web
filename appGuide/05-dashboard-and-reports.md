# Module 5: Dashboard and reports

## Goal

Recreate the source dashboard using Filament widgets and shared attendance calculations.

All dashboard queries use the active semester by default. Provide a semester filter for historical reports, and clamp date-based metrics to the selected semester's boundaries.

## Source behavior

- KPI cards show overall attendance rate, present sessions, absent sessions, and active subjects.
- Today's timetable shows time, subject, label/code, and attendance state.
- Weekly consistency shows the last seven calendar days, separating marked Present and Absent sessions; days without slots are visually empty.
- Subject performance is actually target progress: marked sessions versus required sessions, remaining count, completion, and attendance rate.
- Quick links lead to subjects, timetable, and attendance.
- Rate uses marked records only: `present / (present + absent)`.

## Implementation steps

1. Define shared query/service methods for overall stats, per-subject stats, expected slot occurrences, and seven-day values. Reuse them in resources and widgets.
2. Add Filament stat widgets for overall rate, present, absent, and active subject count. Use owner- and semester-scoped queries and avoid loading all attendance history into memory.
3. Add a today's-schedule widget that derives slots for the current local date and joins each subject and saved mark.
4. Add a seven-day chart/widget with separate Present and Absent values and explicit empty days. Confirm the exact chart API against installed Filament v5 docs when implementing.
5. Add subject target-progress rows or a compact table showing marked/required, remaining, complete, and rate. Keep “target not set” available only if the business later makes the target optional; the source requires it.
6. Add links to the subject, timetable, and attendance modules using named routes or Filament navigation links.
7. Optimize stats queries and indexes using the actual data shape. Cache only if profiling shows a need, and include owner, selected semester, and date dimensions in cache keys.

## Acceptance checks

- Dashboard metrics match the attendance summary calculations for the same user and period.
- Dashboard metrics default to the active semester and match the selected historical semester when its filter changes.
- Today's rows match that day's slots and saved attendance states.
- Weekly chart groups data by seven local calendar dates, not an arbitrary rolling 168-hour window.
- Empty attendance and empty subject states are clear and actionable.
- No widget leaks records across owners.

## Verification plan

- Compare widget values with known fixtures containing present, absent, unmarked, completed, and unscheduled sessions.
- Test a week boundary and timezone midnight boundary.
- Check query counts on a realistic fixture and confirm all queries are owner-scoped.
