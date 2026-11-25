# Module 1: Foundation, access, and application shell

## Goal

Prepare the existing Filament panel to host the personal StudyDesk workflow. The destination already has Filament v5 installed, an `/admin` panel, and a local admin account; this module should refine the shell and establish data ownership before domain resources are added.

> Status: implemented. The panel uses a configured admin-email allowlist, stores a study start date on each user, and uses Filament's light/dark appearance switcher.

## Implementation steps

1. Confirm installed Laravel, Filament, Livewire, PHP, and database versions before using version-specific APIs.
2. Review the current panel provider, user model, auth configuration, migrations, and existing UI conventions. Preserve the existing `/admin` panel instead of creating a second panel.
3. Treat the first release as an owner-scoped private workspace. Future domain records must include an owner and every resource/query must constrain access to that owner.
4. Allow entry to the `admin` panel only for email addresses in `STUDYDESK_ADMIN_EMAILS`; configure local and deployed environments explicitly. A blank value intentionally denies access. Do not use email-domain checks or open registration as an access rule.
5. Store `study_start_date` on `users`, backfill existing accounts at migration time, and initialize it for newly created users. Use Filament's built-in browser-side appearance switcher rather than adding a second preference store.
6. Set the panel name from `APP_NAME`, use the source's violet primary color, and default to light mode while keeping the light/dark switcher available.
7. Use configurable `APP_TIMEZONE`, defaulting to `Asia/Kolkata`, consistently for study-start dates and all later date-boundary calculations.
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
