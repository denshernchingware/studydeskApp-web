# Module 1: Foundation, access, and application shell

## Goal

Prepare the existing Filament panel to host the personal StudyDesk workflow. The destination already has Filament v5 installed, an `/admin` panel, and a local admin account; this module should refine the shell and establish data ownership before domain resources are added.

## Implementation steps

1. Confirm installed Laravel, Filament, Livewire, PHP, and database versions before using version-specific APIs.
2. Review the current panel provider, user model, auth configuration, migrations, and existing UI conventions. Preserve the existing `/admin` panel instead of creating a second panel.
3. Decide whether the first release is single-learner or supports multiple learners. Default to owner-scoped records because the source calls the workspace private; the configured admin account is the first owner.
4. Define the owner access rule for the panel and all records. Add an explicit `FilamentUser` access check if the app will be used outside local development; avoid granting access based only on a development email suffix in production.
5. Choose where to store a learner's study start date and appearance preference: user columns or a user-preferences model. Record the decision before migrations.
6. Keep Filament's navigation labels and brand aligned with StudyDesk. Use a purple primary color similar to the source, and organize future resources under clear workspace navigation groups.
7. Add a shared date/timezone policy. Use the same timezone for today's date, weekly slot occurrences, attendance eligibility, and dashboard periods.
8. Create a small representative test matrix for panel access and owner isolation before adding domain modules.

## Acceptance checks

- An authorized user can sign in to the existing panel; an unauthorized user cannot access it outside local development.
- The panel remains at `/admin` and its login route remains available.
- The owner-scope and timezone decisions are documented and can be used by every later module.
- No StudyDesk domain data is shared across users by default.

## Verification plan

- Check the panel routes and login flow.
- Test allowed and denied panel access.
- Add an isolation test proving one account cannot list or open another account's records when the first resource is introduced.
