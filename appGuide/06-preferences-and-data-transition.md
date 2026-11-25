# Module 6: Preferences and local-data transition

## Goal

Preserve the source's appearance preference and document what happens to browser-local records when moving to the authenticated Laravel app.

## Source behavior and limits

- Light/dark mode is saved in `localStorage` under `studydesk_theme_v1`.
- All subjects, slots, attendance, and the app start date are saved under `studydesk_mba_v1` in the current browser.
- The source has no export/import or cloud synchronization. Records in a user's browser do not automatically move into the Laravel database.
- Semesters are a new Laravel capability and are not present in the source data. Any optional future import must ask which semester should receive imported subjects and their linked slots and attendance.

## Implementation steps

1. Use Filament v5's supported appearance controls where possible instead of rebuilding the source's custom theme toggle.
2. Decide whether the preference is account-wide or browser/session-specific. Store it with the user only if it should follow the user across devices.
3. Preserve StudyDesk's purple/neutral visual identity through panel configuration and a small set of theme tokens; avoid custom CSS unless the default panel cannot meet the requirement.
4. Do not attempt to read a user's old localStorage from the server. Treat migration as a separate opt-in import feature.
5. If import is requested later, design a validated JSON export/import format that maps a chosen semester, subjects, slots, effective dates, and attendance marks, detects duplicates, and reports skipped records before writing.
6. Document account bootstrap and recovery operationally without committing passwords or secret values.

## Acceptance checks

- Users can choose the supported light/dark appearance, and the choice behaves consistently with the selected account/browser preference model.
- The app does not claim that old browser records were migrated automatically.
- No credentials or private local data are added to the repository.

## Verification plan

- Verify appearance preference persistence according to the selected scope.
- If import is later approved, test malformed data, duplicate records, invalid references, ownership, and all-or-nothing transaction behavior.
