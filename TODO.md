# Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

## Multi photo uploads feature

## Refine Card Margins and Navbar Typography (Completed)

- Removed duplicate schedule-page vertical padding and standardized authenticated page spacing on `py-6 sm:py-8`.
- Standardized page gutters on `px-4 sm:px-6 lg:px-8`.
- Brought profile and flight-plan pages into the same spacing rhythm.
- Removed the empty navbar logo offset and replaced desktop link spacing with a consistent gap.
- Balanced navbar link padding and resized the coffee button to fit within the navigation row.
- Standardized schedule cards on responsive `p-4 sm:p-6` or equivalent horizontal padding.
- Reduced flight-card padding and route-icon spacing on mobile.
- Updated event-card headers to stack safely on mobile instead of overflowing.
- Replaced the flight-card accordion's large top margin with parent-controlled section spacing.
- Replaced split hero/form padding and unrelated child margins with `gap-6` / `space-y-6`.
- Verified the changes with focused feature/component tests and a successful production Vite build.

Perform a final visual review at mobile and desktop breakpoints.

## CKS flight codes strings should be extracted to env file and not in the codebase
- add to user preferences

## Airport Lookup Client address should be in .env with a fallback
- migrate localhost value into .env and config

## Release 1
- All tests pass

## API
