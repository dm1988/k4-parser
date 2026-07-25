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

## Naming and Architecture (Completed)

- Established `extract` / `extracted` as the terminology for the user-facing extraction workflow.
- Renamed the extraction DTOs, enum, exception, controller, actions, validation rules, view models, Blade components, Livewire operations, and command class.
- Renamed `ParseRequest` → `ExtractRequest` and `ParseRequestPolicy` → `ExtractRequestPolicy`, preserving Laravel's conventional policy discovery without explicit registration.
- Renamed the related Filament resource, pages, schema, table, widgets, and tests.
- Added a reversible migration that renames:
  - `parse_requests` → `extract_requests`
  - `parse_duration_ms` → `extraction_duration_ms`
  - `parser_version` → `extractor_version`
- Renamed extraction-facing environment and configuration values:
  - `PARSER_VERSION` → `EXTRACTOR_VERSION`
  - `PARSED_RESULTS_TTL` → `EXTRACTED_RESULTS_TTL`
  - `FEATURES_SCHEDULE_PARSER_*` → `FEATURES_SCHEDULE_EXTRACTOR_*`
- Retained backward-compatible fallbacks for the previous environment variable names.
- Retained `Parser` terminology for classes and metadata that specifically represent internal parser implementations.
- Retained the existing `/parse` routes, `parse.*` route names, and `parse:schedule` command signature as stable public interfaces.
- Verified the completed work with focused tests, Pint, Larastan, and the full test suite.

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
