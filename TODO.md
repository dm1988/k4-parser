# Codex Usage Rules

Follow these rules for every remaining task:
4. Work on one numbered task at a time.
5. Run only focused tests while implementing a task.
6. Run Pint only when PHP files change.
7. Larastan once at the final integration checkpoint, not after every small edit.
9. Preserve unrelated working-tree changes.
10. Update this file with outcomes instead of adding another plan or duplicate checklist.

## Product and UI Polish

* Continue spelling out “Jeppesen Crew Access” before introducing the “JCA” abbreviation.

## Current focus: Naming and Architecture

### Completed

- Identified parser-related references for the completed rename scope.
- Established `extract` / `extracted` as the preferred terminology for completed renames.
- Renamed DTOs:
  - `ParsedEventDTO` → `ExtractedEventDTO`
  - `ParserResultData` → `ExtractedResultData`
- Renamed `ParserEventType` → `ScheduleEventType`.
- Renamed `ParseSourceResolutionException` → `ExtractSourceResolutionException`.
- Renamed `ParserController` → `ExtractController`.
- Renamed `BuildParserResult` → `BuildScheduleResult`.
- Updated affected namespaces and references atomically.
- Verified the completed renames with focused tests and Pint.

### Pending

- Review remaining parser-related names and decide whether each represents parsing behavior or should use `extract` / `extracted` terminology.
- Rename the `ParseRequest` model.
- Rename `ParseRequestPolicy` alongside the model so Laravel's conventional policy discovery continues to work without explicit registration.
- Evaluate whether the underlying database table should be renamed.
- Run the full test suite after the naming and architecture work is complete.
- env / config changes
- ParseSchedule command

Any reorganization must preserve behavior, update namespaces atomically, and be covered by focused tests during implementation and the full test suite at the final integration checkpoint.

## Multi photo uploads feature

## Refine Card Margins and Navbar Typography

- Spacing: Increase the vertical spacing (gap or margin-bottom) between the hero header card and the upload card if you keep them separate.

- The navbar items ("Parse Schedule", "Route Extractor", etc.) are quite close to the top edge of the viewport. Adding a bit more top and bottom padding to the navbar container will give the text room to breathe and look cleaner.

## CKS flight codes strings should be extracted to env file and not in the codebase
- add to user preferences

## Airport Lookup Client address should be in .env with a fallback
- migrate localhost value into .env and config

## Release 1
- All tests pass

## API
